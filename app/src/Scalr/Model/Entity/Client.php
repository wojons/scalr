<?php

namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\EntityIterator;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;

/**
 * Client entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="clients")
 */
class Client extends AbstractEntity
{

    /**
     * Identifier
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * Client short name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $name;

    /**
     * Status
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $status;

    /**
     * Billed flag
     *
     * @Column(name="isbilled",type="boolean")
     * @var bool
     */
    public $billed = 0;

    /**
     * Due time
     *
     * @Column(name="dtdue",type="datetime",nullable=true)
     * @var DateTime
     */
    public $due;

    /**
     * Activ flag
     *
     * @Column(name="isactive",type="boolean")
     * @var bool
     */
    public $active = 0;

    /**
     * Client full name
     *
     * @Column(name="fullname",type="string",nullable=true)
     * @var string
     */
    public $fullName;

    /**
     * Client organization name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $org;

    /**
     * Client country
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $country;

    /**
     * Client state
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $state;

    /**
     * Client city
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $city;

    /**
     * Client ZIP-code
     *
     * @Column(name="zipcode",type="string",nullable=true)
     * @var string
     */
    public $zipCode;

    /**
     * Client address
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $address1;

    /**
     * Client address
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $address2;

    /**
     * Client phone
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $phone;

    /**
     * Client fax
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $fax;

    /**
     * Added time
     *
     * @Column(name="dtadded",type="datetime",nullable=true)
     * @var DateTime
     */
    public $added;

    /**
     * Welcome e-mail sent flag
     *
     * @Column(name="iswelcomemailsent",type="boolean")
     * @var bool
     */
    public $welcomeMailSent = false;

    /**
     * Login attempts
     *
     * @Column(type="integer")
     * @var int
     */
    public $loginAttempts = 0;

    /**
     * Last login attempt time
     *
     * @Column(name="dtlastloginattempt",type="datetime",nullable=true)
     * @var DateTime
     */
    public $lastLoginAttempt;

    /**
     * Comments
     *
     * @Column(type="string")
     * @var string
     */
    public $comments;

    /**
     * Priority
     *
     * @Column(type="integer",nullable=false)
     * @var int
     */
    public $priority = 0;

    /**
     * Checks account limits
     *
     * @param  string  $limitName
     * @param  integer $limitValue
     * @return boolean
     */
    public function checkLimit($limitName, $limitValue)
    {
        return Limit::findOne([
            ['accountId' => $this->id],
            ['name'      => $limitName],
        ])->check($limitValue);
    }

    public function delete()
    {
        parent::delete();

        ReportEntity::deleteByAccountId($this->id);
        NotificationEntity::deleteByAccountId($this->id);
    }

    /**
     * Gets cloud credentials for listed clouds
     *
     * @param   string[]    $clouds             optional Clouds list
     * @param   array       $credentialsFilter  optional Criteria to filter by CloudCredentials properties
     * @param   array       $propertiesFilter   optional Criteria to filter by CloudCredentialsProperties
     *
     * @return  EntityIterator|CloudCredentials[]
     */
    public function cloudCredentialsList(array $clouds = null, array $credentialsFilter = [], array $propertiesFilter = [])
    {
        if (!is_array($clouds)) {
            $clouds = (array) $clouds;
        }

        $cloudCredentials = new CloudCredentials();
        $cloudCredProps = new CloudCredentialsProperty();

        $criteria = $credentialsFilter;
        $from[] = empty($criteria[AbstractEntity::STMT_FROM]) ? " {$cloudCredentials->table()} " : $criteria[AbstractEntity::STMT_FROM];
        $where = empty($criteria[AbstractEntity::STMT_WHERE]) ? [] : [$criteria[AbstractEntity::STMT_WHERE]];

        $criteria[] = ['accountId' => $this->id];

        if (!empty($clouds)) {
            $clouds = implode(", ", array_map(function ($cloud) use ($cloudCredentials) {
                return $cloudCredentials->qstr('cloud', $cloud);
            }, $clouds));

            $where[] = "{$cloudCredentials->columnCloud()} IN ({$clouds})";
        }

        if (!empty($propertiesFilter)) {
            foreach ($propertiesFilter as $property => $propCriteria) {
                $alias = "ccp_" . trim($cloudCredentials->db()->qstr($property), "'");

                $from[] = "
                    LEFT JOIN {$cloudCredProps->table($alias)} ON
                        {$cloudCredentials->columnId()} = {$cloudCredProps->columnCloudCredentialsId($alias)} AND
                        {$cloudCredProps->columnName($alias)} = {$cloudCredProps->qstr('name', $property)}
                ";

                $built = $cloudCredProps->_buildQuery($propCriteria, 'AND', $alias);

                if (!empty($built['where'])) {
                    $where[] = $built['where'];
                }
            }
        }

        $criteria[AbstractEntity::STMT_FROM] = implode("\n", $from);

        if (!empty($where)) {
            $criteria[AbstractEntity::STMT_WHERE] = "(" . implode(") AND (", $where) . ")";
        }

        return CloudCredentials::find($criteria);
    }
}