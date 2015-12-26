<?php

namespace Scalr\Model\Entity;

use DateTime;
use DBServer;
use Exception;
use HostDownEvent;
use ROLE_BEHAVIORS;
use Scalr;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Exception\InvalidEntityConfigurationException;
use Scalr\Exception\Model\Entity\Image\ImageNotFoundException;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr_Role_Behavior_MongoDB;
use Scalr_Role_Behavior_MySql;
use Scalr_Role_Behavior_Nginx;
use Scalr_Role_Behavior_RabbitMQ;
use Scalr_SchedulerTask;
use Scalr_Role_Behavior_Router;

/**
 * FarmRole entity
 *
 * @author N.V.
 *
 * @property    string[]    $settings   Settings collection
 * @property    Farm        $farm       Farm entity
 * @property    Role        $role       Role entity
 *
 * @Entity
 * @Table(name="farm_roles")
 */
class FarmRole extends AbstractEntity implements AccessPermissionsInterface
{

    const ALIAS_REGEXP = '/^[a-z0-9]+[a-z0-9-]*[a-z0-9]+$/si';

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
     * Farm Id
     *
     * @Column(name="farmid",type="integer",nullable=true)
     * @var int
     */
    public $farmId;

    /**
     * Farm alias
     *
     * @Column(type="string")
     * @var string
     */
    public $alias;

    /**
     * Time of last synchronization
     *
     * @Column(name="dtlastsync",type="datetime",nullable=true)
     * @var DateTime
     */
    public $lastSync;

    /**
     * Reboot timeout
     *
     * @Column(type="integer")
     * @var int
     */
    public $rebootTimeout = 300;

    /**
     * Launch timeout
     *
     * @Column(type="integer")
     * @var int
     */
    public $launchTimeout = 300;

    /**
     * Status refresh timeout
     *
     * @Column(type="integer")
     * @var int
     */
    public $statusTimeout = 600;

    /**
     * launch index
     *
     * @Column(type="integer")
     * @var int
     */
    public $launchIndex;

    /**
     * Role Id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $roleId;

    /**
     * Role platform
     *
     * @Column(type="string")
     * @var string
     */
    public $platform;

    /**
     * Cloud location
     *
     * @Column(type="string")
     * @var string
     */
    public $cloudLocation;

    /**
     * Farm role settings collection
     *
     * @var SettingsCollection
     */
    protected $_settings;

    /**
     * Farm entity
     *
     * @var Farm
     */
    protected $_farm;

    /**
     * Role entity
     *
     * @var Role
     */
    protected $_role;

    public function __get($name)
    {
        switch ($name) {
            case 'settings':
                if (empty($this->_settings)) {
                    $this->_settings = new SettingsCollection(
                        'Scalr\Model\Entity\FarmRoleSetting',
                        [[ 'farmRoleId' => &$this->id ]],
                        [ 'farmRoleId' => &$this->id ]
                    );
                }

                return $this->_settings;

            case 'farm':
                if (empty($this->_farm) && !empty($this->farmId)) {
                    $this->_farm = Farm::findPk($this->farmId);
                }

                return $this->_farm;

            case 'role':
                if (empty($this->_role) && !empty($this->roleId)) {
                    $this->_role = Role::findPk($this->roleId);
                }

                return $this->_role;

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

        $this->setupRole();

        $this->setupAlias();

        $this->setupScaling();

        $this->setupBehaviors();

        $this->setupPolling();

        parent::save();

        $this->setupAnalytics();

        if (!empty($this->_settings)) {
            $this->_settings->save();
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::delete()
     */
    public function delete()
    {
        $db = $this->db();

        try {
            $this->terminateServers();

            $db->BeginTrans();

            // Clear farm role options & scripts
            $db->Execute("DELETE FROM farm_role_service_config_presets WHERE farm_roleid=?", [$this->id]);
            $db->Execute("DELETE FROM farm_role_scaling_times WHERE farm_roleid=?", [$this->id]);
            $db->Execute("DELETE FROM farm_role_service_config_presets WHERE farm_roleid=?", [$this->id]);
            $db->Execute("DELETE FROM farm_role_scripting_targets WHERE `target`=? AND `target_type` = 'farmrole'", [$this->id]);

            $db->Execute("DELETE FROM ec2_ebs WHERE farm_roleid=?", [$this->id]);
            $db->Execute("DELETE FROM elastic_ips WHERE farm_roleid=?", [$this->id]);

            $db->Execute("DELETE FROM storage_volumes WHERE farm_roleid=?", [$this->id]);

            // Clear apache vhosts and update DNS zones
            $db->Execute("UPDATE apache_vhosts SET farm_roleid='0', farm_id='0' WHERE farm_roleid=?", [$this->id]);
            $db->Execute("UPDATE dns_zones SET farm_roleid='0' WHERE farm_roleid=?", [$this->id]);

            $this->deleteScheduled();

            $db->Execute("DELETE FROM farm_role_scripts WHERE farm_roleid=?", [ $this->id ]);

            parent::delete();

            $db->CommitTrans();
        } catch (Exception $e) {
            $db->RollbackTrans();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     * @see AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        /* @var $farm Farm */
        $farm = Farm::findPk($this->farmId);

        return $environment
            ? $farm->envId == $environment->id
            : $user->hasAccessToEnvironment($farm->envId);
    }

    public function setupRole()
    {
        if (empty($this->roleId)) {
            throw new InvalidEntityConfigurationException("Missed roleId");
        }

        if ($this->role->isDeprecated) {
            throw new InvalidEntityConfigurationException("Role '{$this->roleId}' is deprecated");
        }

        try {
            $this->role->getImage($this->platform, $this->cloudLocation);
        } catch (ImageNotFoundException $e) {
            throw new InvalidEntityConfigurationException($e->getMessage(), $e->getCode(), $e);
        }

    }

    /**
     * Setups farm role settings related to role behaviors
     */
    public function setupBehaviors()
    {
        if ($this->role->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
            Scalr_Role_Behavior_RabbitMQ::setupBehavior($this);
        }

        if ($this->role->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
            Scalr_Role_Behavior_MongoDB::setupBehavior($this);
        }

        if ($this->role->hasBehavior(ROLE_BEHAVIORS::NGINX)) {
            Scalr_Role_Behavior_Nginx::setupBehavior($this);
        }

        if ($this->role->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
            Scalr_Role_Behavior_MySql::setupBehavior($this);
        }

        if ($this->role->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
            Scalr_Role_Behavior_Router::setupBehavior($this);
        }
    }

    /**
     * Setups farm role scaling settings
     */
    public function setupScaling()
    {
        $minInstances = $this->settings[FarmRoleSetting::SCALING_MIN_INSTANCES];
        $maxInstances = $this->settings[FarmRoleSetting::SCALING_MAX_INSTANCES];

        if ($minInstances < 1) {
            $minInstances = 1;
        } else if ($minInstances > 400) {
            $minInstances = 400;
        }

        if ($maxInstances < $minInstances) {
            $maxInstances = $minInstances;
        } else if ($maxInstances > 400) {
            $maxInstances = 400;
        }

        $this->settings[FarmRoleSetting::SCALING_MIN_INSTANCES] = $minInstances;
        $this->settings[FarmRoleSetting::SCALING_MAX_INSTANCES] = $maxInstances;
    }

    /**
     * Setups farm role alias
     */
    public function setupAlias()
    {
        if (empty($this->alias)) {
            $aliases = [];

            /* @var $role FarmRole */
            foreach (static::findByFarmId($this->farmId) as $role) {
                $aliases[] = $role->alias;
            }

            $this->alias = $this->role->name;

            $n = 1;

            do {
                $this->alias = "{$this->role->name}-{$n}";
                $n++;
            } while (in_array($this->alias, $aliases));
        } else if (count(static::find([
            ['id' => ['$ne' => $this->id]],
            ['farmId' => $this->farmId],
            ['alias' => $this->alias]
        ]))) {
            throw new InvalidEntityConfigurationException("Alias must be unique within a farm");
        }
    }

    /**
     * Setups farm role analytics tags
     */
    public function setupAnalytics()
    {
        if (\Scalr::getContainer()->analytics->enabled) {
            \Scalr::getContainer()->analytics->tags->syncValue(
                $this->farm->accountId, TagEntity::TAG_ID_FARM_ROLE, $this->id,
                $this->alias
            );
        }
    }

    /**
     * Setups polling settings
     *
     * @throws InvalidEntityConfigurationException
     */
    public function setupPolling()
    {
        if (empty($this->settings[FarmRoleSetting::SCALING_POLLING_INTERVAL])) {
            $this->settings[FarmRoleSetting::SCALING_POLLING_INTERVAL] = 1;
        } else {
            $pollingInterval = $this->settings[FarmRoleSetting::SCALING_POLLING_INTERVAL];

            if ($pollingInterval < 1 || $pollingInterval > 50) {
                throw new InvalidEntityConfigurationException("Polling interval for role must be a number between 1 and 50");
            }
        }
    }

    /**
     * Deletes scheduled tasks
     */
    public function deleteScheduled()
    {
        $this->db()->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type IN (?, ?)", [
            $this->id,
            Scalr_SchedulerTask::TARGET_ROLE,
            Scalr_SchedulerTask::TARGET_INSTANCE
        ]);
    }

    /**
     * Terminates servers used this farm role
     */
    public function terminateServers()
    {
        /* @var $server Server */
        foreach (Server::findByFarmRoleId($this->id) as $server) {
            $DBServer = \DBServer::LoadByID($server->serverId);

            $DBServer->terminate(DBServer::TERMINATE_REASON_ROLE_REMOVED);

            $event = new HostDownEvent($DBServer);
            Scalr::FireEvent($DBServer->farmId, $event);
        }
    }
}