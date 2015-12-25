<?php

namespace Scalr\Model\Entity\Account;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity;

/**
 * Environment entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4.0 (23.02.2015)
 *
 * @Entity
 * @Table(name="client_environments")
 */
class Environment extends AbstractEntity
{
    /**
     * The identifier of the Environment
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * The name of the environment
     *
     * @var string
     */
    public $name;

    /**
     * The identifier of the Account
     *
     * @Column(name="client_id",type="integer")
     * @var int
     */
    public $accountId;

    /**
     * The timestamp when this environment was added
     *
     * @Column(name="dt_added",type="datetime")
     * @var \DateTime
     */
    public $added;

    /**
     * The status of the environment
     * @var string
     */
    public $status;

    private $_properties = [];

    /**
     * Constructor
     *
     * @param   string $accountId optional The identifier of the account
     */
    public function __construct($accountId = null)
    {
        $this->accountId = $accountId;
        $this->added = new \DateTime();
    }

    /**
     * Gets identifier of the Account
     *
     * @return   int  Returns identifier of the Account
     */
    public function getAccountId()
    {
        return $this->accountId;
    }

    /**
     * Gets environment properties list
     *
     * TODO: need to implement proper properties handling @see \Scalr_Environment::getEncryptedVariables()
     *
     * @return EnvironmentProperty[]
     */
    public function getProperties()
    {
        if (empty($this->_properties)) {
            /* @var $properties EnvironmentProperty[] */
            $properties = EnvironmentProperty::findByEnvId($this->id);

            if (!empty($properties)) {
                foreach ($properties as $property) {
                    $this->_properties[$property->name] = $property;
                }
            }
        }

        return $this->_properties;
    }

    /**
     * Gets environment property
     *
     * @param   string  $name Property name
     *
     * @return EnvironmentProperty
     */
    public function getProperty($name)
    {
        if (empty($this->_properties)) {
            $this->getProperties();
        }

        return isset($this->_properties[$name]) ? $this->_properties[$name] : null;
    }

    /**
     * Gets specified cloud credentials for this environment
     *
     * @param   string  $cloud  The cloud name
     *
     * @return Entity\CloudCredentials
     */
    public function cloudCredentials($cloud)
    {
        return \Scalr::getContainer()->cloudCredentials($cloud, $this->id);
    }

    /**
     * Gets cloud credentials for listed clouds
     *
     * @param   string[]    $clouds             optional Clouds list
     * @param   array       $credentialsFilter  optional Criteria to filter by CloudCredentials properties
     * @param   array       $propertiesFilter   optional Criteria to filter by CloudCredentialsProperties
     * @param   bool        $cacheResult        optional Cache result
     *
     * @return Entity\CloudCredentials[]
     */
    public function cloudCredentialsList(array $clouds = null, array $credentialsFilter = [], array $propertiesFilter = [], $cacheResult = true)
    {
        if (!is_array($clouds)) {
            $clouds = (array) $clouds;
        }

        $cloudCredentials = new Entity\CloudCredentials();
        $envCloudCredentials = new Entity\EnvironmentCloudCredentials();
        $cloudCredProps = new Entity\CloudCredentialsProperty();

        $criteria = array_merge($credentialsFilter, [
            AbstractEntity::STMT_FROM => $cloudCredentials->table(),
            AbstractEntity::STMT_WHERE => ''
        ]);

        if (!empty($clouds)) {
            $criteria[AbstractEntity::STMT_FROM] .= "
                JOIN {$envCloudCredentials->table('cecc')} ON
                    {$cloudCredentials->columnId()} = {$envCloudCredentials->columnCloudCredentialsId('cecc')} AND
                    {$cloudCredentials->columnCloud()} = {$envCloudCredentials->columnCloud('cecc')}
            ";

            $clouds = implode(", ", array_map(function ($cloud) use ($envCloudCredentials) {
                return $envCloudCredentials->qstr('cloud', $cloud);
            }, $clouds));

            $criteria[AbstractEntity::STMT_WHERE] = "
                {$envCloudCredentials->columnEnvId('cecc')} = {$envCloudCredentials->qstr('envId', $this->id)} AND
                {$envCloudCredentials->columnCloud('cecc')} IN ({$clouds})
            ";
        }

        if (!empty($propertiesFilter)) {
            foreach ($propertiesFilter as $property => $propCriteria) {
                $criteria[AbstractEntity::STMT_FROM] .= "
                    LEFT JOIN {$cloudCredProps->table('ccp')} ON
                        {$cloudCredentials->columnId()} = {$cloudCredProps->columnCloudCredentialsId('ccp')} AND
                        {$cloudCredProps->columnName('ccp')} = {$cloudCredProps->qstr('name', $property)}
                ";

                $conjunction = empty($criteria[AbstractEntity::STMT_WHERE]) ? "" : "AND";

                $criteria[AbstractEntity::STMT_WHERE] .= "
                    {$conjunction} {$cloudCredProps->_buildQuery($propCriteria, 'AND', 'ccp')}
                ";
            }
        }

        /* @var $cloudsCredentials Entity\CloudCredentials[] */
        $cloudsCredentials = Entity\CloudCredentials::find($criteria);

        $result = [];
        $cont = \Scalr::getContainer();
        foreach ($cloudsCredentials as $cloudCredentials) {
            $result[$cloudCredentials->cloud] = $cloudCredentials;

            if ($cacheResult) {
                $cloudCredentials->bindEnvironment($this->id);
                $cloudCredentials->cache($cont);
            }
        }

        return $result;
    }
}
