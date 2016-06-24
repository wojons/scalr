<?php

namespace Scalr\Model\Entity\Account;

use Exception;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity;
use Scalr\Exception\ObjectInUseException;

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
    const STATUS_ACTIVE = 'Active';
    const STATUS_INACTIVE = 'Inactive';

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
     *
     * @var string
     */
    public $status;

    /**
     * The default priority of the environment
     *
     * @var int
     */
    public $defaultPriority = 0;

    /**
     * Properties collection
     *
     * @var EnvironmentProperty[]
     */
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
     * {@inheritdoc}
     * @see AbstractEntity::save()
     */
    public function save()
    {
        parent::save();

        foreach ($this->_properties as $property) {
            if ($property->value == false) {
                $property->delete();
            } else {
                $property->save();
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::delete()
     *
     * @param   bool    $force Delete ignoring restrictions
     */
    public function delete($force = false)
    {
        $db = $this->db();

        if (!$force) {
            if ($db->GetOne("SELECT 1 FROM `farms` WHERE `env_id` = ? LIMIT 1", [$this->id])) {
                throw new ObjectInUseException('Cannot remove environment. You need to remove all your farms first.');
            }

            if ($db->GetOne("SELECT COUNT(*) FROM client_environments WHERE client_id = ?", [$this->accountId]) < 2) {
                throw new ObjectInUseException('At least one environment should be in account. You cannot remove the last one.');
            }
        }

        parent::delete();

        try {
            $db->Execute("DELETE FROM client_environment_properties WHERE env_id=?", [$this->id]);
            $db->Execute("DELETE FROM apache_vhosts WHERE env_id=?", [$this->id]);
            $db->Execute("DELETE FROM autosnap_settings WHERE env_id=?", [$this->id]);
            $db->Execute("DELETE FROM bundle_tasks WHERE env_id=?", [$this->id]);
            $db->Execute("DELETE FROM dns_zones WHERE env_id=?", [$this->id]);
            $db->Execute("DELETE FROM ec2_ebs WHERE env_id=?", [$this->id]);
            $db->Execute("DELETE FROM elastic_ips WHERE env_id=?", [$this->id]);
            $db->Execute("DELETE FROM farms WHERE env_id=?", [$this->id]);
            $db->Execute("DELETE FROM roles WHERE env_id=?", [$this->id]);

            $servers = \DBServer::listByFilter(['envId' => $this->id]);

            foreach ($servers as $server) {
                /* @var $server \DBServer */
                $server->Remove();
            }

            Entity\EnvironmentCloudCredentials::deleteByEnvId($this->id);
            Entity\CloudCredentials::deleteByEnvId($this->id);

            TeamEnvs::deleteByEnvId($this->id);

        } catch (Exception $e) {
            throw new Exception (sprintf(_("Cannot delete record. Error: %s"), $e->getMessage()), $e->getCode());
        }
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
     * Sets Environment property
     *
     * @param   string  $name   Property name
     * @param   string  $value  Property value
     */
    public function setProperty($name, $value)
    {
        $property = new EnvironmentProperty();
        $property->envId = &$this->id;
        $property->name = $name;
        $property->value = $value;

        $this->_properties[$name] = $property;
    }

    /**
     * Gets environment properties list
     *
     * TODO: need to implement proper properties handling @see \Scalr_Environment::getEncryptedVariables()
     * NOTE: currently there are no properties that require encryption
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

        return isset($this->_properties[$name]) ? $this->_properties[$name]->value : null;
    }

    /**
     * Gets specified cloud credentials for this environment
     *
     * @param   string  $cloud  The cloud name
     *
     * @return Entity\CloudCredentials
     */
    public function keychain($cloud)
    {
        return \Scalr::getContainer()->keychain($cloud, $this->id);
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
        $cloudCredProps = new Entity\CloudCredentialsProperty();
        $envCloudCredentials = new Entity\EnvironmentCloudCredentials();

        $criteria = $credentialsFilter;
        $from[] = empty($criteria[AbstractEntity::STMT_FROM]) ? " {$cloudCredentials->table()} " : $criteria[AbstractEntity::STMT_FROM];
        $where = empty($criteria[AbstractEntity::STMT_WHERE]) ? [] : [$criteria[AbstractEntity::STMT_WHERE]];

        $from[] = "
            JOIN {$envCloudCredentials->table('cecc')} ON
                {$cloudCredentials->columnId()} = {$envCloudCredentials->columnCloudCredentialsId('cecc')} AND
                {$cloudCredentials->columnCloud()} = {$envCloudCredentials->columnCloud('cecc')}
        ";

        $where[] = "{$envCloudCredentials->columnEnvId('cecc')} = {$envCloudCredentials->qstr('envId', $this->id)}";

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

        /* @var $cloudsCredentials Entity\CloudCredentials[] */
        $cloudsCredentials = Entity\CloudCredentials::find($criteria);

        $result = [];
        foreach ($cloudsCredentials as $cloudCredentials) {
            $result[$cloudCredentials->cloud] = $cloudCredentials;

            if ($cacheResult) {
                $cloudCredentials->bindEnvironment($this->id);
                $cloudCredentials->cache();
            }
        }

        return $result;
    }

    /**
     * Gets an Amazon Web Service (Aws) factory instance
     *
     * This method ensures that aws instance is always from the
     * current environment scope.
     *
     * @param   string|\DBServer|\DBFarmRole|\DBEBSVolume $awsRegion optional
     *          The region or object which has both Scalr_Environment instance and cloud location itself
     *
     * @param   string  $awsAccessKeyId     optional The AccessKeyId
     * @param   string  $awsSecretAccessKey optional The SecretAccessKey
     * @param   string  $certificate        optional Contains x.509 certificate
     * @param   string  $privateKey         optional The private key for the certificate
     * @return  \Scalr\Service\Aws Returns Aws instance
     */
    public function aws($awsRegion = null, $awsAccessKeyId = null, $awsSecretAccessKey = null,
                        $certificate = null, $privateKey = null)
    {
        $arguments = func_get_args();
        if (count($arguments) <= 1) {
            $arguments[0] = isset($arguments[0]) ? $arguments[0] : null;
            //Adds Environment as second parameter
            $arguments[1] = $this;
        }

        //Retrieves an instance from the DI container
        return call_user_func_array([\Scalr::getContainer(), 'aws'], $arguments);
    }

}
