<?php

namespace Scalr\Model\Entity;

use Exception;
use Scalr\DependencyInjection\BaseContainer;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\SettingsCollection;
use Scalr_Environment;
use UnderflowException;

/**
 * CloudCredentials entity
 *
 * @author N.V.
 *
 * @property    SettingsCollection              $properties     Properties collection
 * @property    EnvironmentCloudCredentials[]   $environments   Collection of environments bindings for this credentials
 *
 * @Entity
 * @Table(name="cloud_credentials")
 */
class CloudCredentials extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{

    const STATUS_ENABLED = 1;

    const STATUS_DISABLED = 0;

    const STATUS_SUSPENDED = -1;

    /**
     * CLoud credentials unique id
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="uuidShort")
     *
     * @var string
     */
    public $id;

    /**
     * Account id
     *
     * @Column(type="integer",nullable=true)
     *
     * @var int
     */
    public $accountId;

    /**
     * Environment id
     *
     * @Column(type="integer",nullable=true)
     *
     * @var int
     */
    public $envId;

    /**
     * Cloud credential name
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $name;

    /**
     * Cloud name
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $cloud;

    /**
     * Cloud credentials status
     *
     * @Column(type="integer")
     *
     * @var string
     */
    public $status = 0;

    /**
     * Description
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $description;

    /**
     * Cloud credentials properties collection
     *
     * @var SettingsCollection
     */
    public $_properties;

    /**
     * Environments bindings
     *
     * @var EnvironmentCloudCredentials[]
     */
    public $_envBinds;

    /**
     * Flag indicates whether environments bindings is loaded from DB
     *
     * @var bool
     */
    private $envsLoaded = false;

    /**
     * Gets statuses logically considered as "enabled"
     *
     * @return array
     */
    public static function getEnabledStatuses()
    {
        return [static::STATUS_ENABLED, static::STATUS_SUSPENDED];
    }

    /**
     * Reset cloud credentials id on clone
     */
    public function __clone()
    {
        $this->_properties = clone $this->properties;

        $unref = null;
        $this->id = &$unref;

        $this->name = "{$this->envId}-{$this->accountId}-{$this->cloud}-" . \Scalr::GenerateUID(true);

        $this->_properties->setCriteria([[ 'cloudCredentialsId' => &$this->id ]]);
        $this->_properties->setDefaultProperties([ 'cloudCredentialsId' => &$this->id ]);
    }

    /**
     * Magic getter.
     * Gets the values of the properties that require initialization.
     *
     * @param   string  $name   Property name
     *
     * @return  mixed   Requested property
     */
    public function __get($name)
    {
        switch ($name) {
            case 'properties':
                if (empty($this->_properties)) {
                    $this->_properties = new SettingsCollection(
                        'Scalr\Model\Entity\CloudCredentialsProperty',
                        [[ 'cloudCredentialsId' => &$this->id ]],
                        [ 'cloudCredentialsId' => &$this->id ]
                    );
                }

                return $this->_properties;

            case 'environments':
                if (!$this->envsLoaded) {
                    $this->_envBinds = [];
                    /* @var $envCloudCreds EnvironmentCloudCredentials */
                    foreach (EnvironmentCloudCredentials::findByCloudCredentialsId($this->id) as $envCloudCreds) {
                        $this->_envBinds[$envCloudCreds->envId] = $envCloudCreds;
                    }

                    $this->envsLoaded = true;
                }

            return $this->_envBinds;

            default:
                return parent::__get($name);
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::save()
     */
    public function save()
    {
        try {
            $this->db()->BeginTrans();

            parent::save();

            if (!empty($this->_properties)) {
                $this->_properties->save();
            }

            $this->db()->CommitTrans();
        } catch (Exception $e) {
            $this->db()->RollbackTrans();

            throw $e;
        }


    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::delete()
     */
    public function delete()
    {
        EnvironmentCloudCredentials::deleteByCloudCredentialsId($this->id);

        parent::delete();
    }

    /**
     * Binds current cloud credential to environment
     *
     * @param Scalr_Environment $environment Environment which linked credentials
     *
     * @return CloudCredentials
     *
     * @throws Exception
     * @throws \Scalr\Exception\ModelException
     */
    public function bindToEnvironment(Scalr_Environment $environment)
    {
        if (empty($this->id)) {
            throw new UnderflowException("CloudCredentials entity must be saved before!");
        }

        /* @var $previousCloudCreds CloudCredentials */
        $previousCloudCreds = $environment->keychain($this->cloud);

        if (!empty($previousCloudCreds->id)) {
            if ($previousCloudCreds->id == $this->id) {
                return $this;
            } else {
                $previousCloudCreds->release();
            }
        }

        $envCloudCreds = EnvironmentCloudCredentials::findPk($environment->id, $this->cloud);

        if (empty($envCloudCreds)) {
            $envCloudCreds = new EnvironmentCloudCredentials();
            $envCloudCreds->envId = $environment->id;
            $envCloudCreds->cloud = $this->cloud;
        }

        $db = $this->db();

        try {
            $db->BeginTrans();

            $envCloudCreds->cloudCredentialsId = $this->id;
            $envCloudCreds->save();

            $db->CommitTrans();
        } catch (Exception $e) {
            $db->RollbackTrans();

            throw $e;
        }

        $this->_envBinds[$envCloudCreds->envId] = $envCloudCreds;

        return $this;
    }

    /**
     * Sets environment binding
     *
     * @param   int $envId Environment identifier
     *
     * @return  EnvironmentCloudCredentials Returns new binding
     */
    public function bindEnvironment($envId)
    {
        if (empty($this->id)) {
            throw new UnderflowException();
        }

        $envCloudCreds = new EnvironmentCloudCredentials();
        $envCloudCreds->cloudCredentialsId = $this->id;
        $envCloudCreds->envId = $envId;
        $envCloudCreds->cloud = $this->cloud;

        $this->_envBinds[$envCloudCreds->envId] = $envCloudCreds;

        return $envCloudCreds;
    }

    /**
     * Releases cached self in DI
     */
    public function release()
    {
        $container = \Scalr::getContainer();

        $container->release("keychain.cloud_creds.{$this->id}");

        if (isset($this->_envBinds)) {
            foreach ($this->_envBinds as $envCloudCreds) {
                $container->release("keychain.env_cloud_creds.{$envCloudCreds->envId}.{$envCloudCreds->cloud}");
            }
        }
    }

    /**
     * Cache self in specified container
     */
    public function cache()
    {
        $container = \Scalr::getContainer();

        $contCloudCredId = "keychain.cloud_creds.{$this->id}";
        $container->setShared($contCloudCredId, function () {
            return $this;
        });

        if (isset($this->_envBinds)) {
            foreach ($this->_envBinds as $envCloudCreds) {
                $envCloudCredId = "keychain.env_cloud_creds.{$envCloudCreds->envId}.{$this->cloud}";
                $container->setShared($envCloudCredId, function () {
                    return $this->id;
                });
            }
        }
    }

    /**
     * Indicates whether cloud credentials are enabled
     *
     * @return bool Returns true if cloud credentials consider as enabled, false otherwise
     */
    public function isEnabled()
    {
        return in_array($this->status, static::getEnabledStatuses());
    }

    public function isUsed()
    {
        $environments = $this->environments;

        return !empty($environments);
    }

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->envId) ? static::SCOPE_ENVIRONMENT : (!empty($this->accountId) ? static::SCOPE_ACCOUNT : static::SCOPE_SCALR);
    }

    /**
     * {@inheritdoc}
     * @see AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        switch ($this->getScope()) {
            case static::SCOPE_ACCOUNT:
                return $this->accountId == $user->accountId && (empty($environment) || !$modify);

            case static::SCOPE_ENVIRONMENT:
                return $environment
                    ? $this->envId == $environment->id
                    : $user->hasAccessToEnvironment($this->envId);

            case static::SCOPE_SCALR:
                return !$modify;

            default:
                return false;
        }
    }

    /**
     * Gets filter criteria by the setting
     *
     * @param   string  $name                Setting name
     * @param   string  $value      optional Setting value
     * @param   array   $criteria   optional Criteria, if already exists
     *
     * @return  array   Returns extended criteria
     */
    public function getSettingCriteria($name, $value = null, array $criteria = null)
    {
        $cloudCredentialsProperty = new CloudCredentialsProperty();

        $alias = "ccp_" . trim($this->db()->qstr($name), "'");

        $join = "
            JOIN {$cloudCredentialsProperty->table($alias)} ON {$this->columnId()} = {$cloudCredentialsProperty->columnCloudCredentialsId($alias)}
                AND {$cloudCredentialsProperty->columnName($alias)} = {$cloudCredentialsProperty->qstr('name', $name)}";

        if (isset($criteria[AbstractEntity::STMT_FROM])) {
            $criteria[AbstractEntity::STMT_FROM] .= " {$join}";
        } else {
            $criteria[AbstractEntity::STMT_FROM] = " {$join}";
        }

        if (isset($value)) {
            $where = "{$cloudCredentialsProperty->columnValue($alias)} = {$cloudCredentialsProperty->qstr('value', $value)}";

            if (isset($criteria[AbstractEntity::STMT_WHERE])) {
                $criteria[AbstractEntity::STMT_WHERE] .= " AND ($where)";
            } else {
                $criteria[AbstractEntity::STMT_WHERE] = $where;
            }
        }

        return $criteria;
    }
}