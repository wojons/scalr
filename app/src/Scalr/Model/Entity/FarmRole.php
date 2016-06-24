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
use SERVER_PLATFORMS;
use Scalr\Exception\NotSupportedException;
use Scalr_Governance;

/**
 * FarmRole entity
 *
 * @author N.V.
 *
 * @property   SettingsCollection       $settings        Settings collection
 * @property   Farm                     $farm            Farm entity
 * @property   Role                     $role            Role entity
 * @property   FarmRoleScalingMetric[]  $farmRoleMetrics The list of FarmRoleScalingMetric entities
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

    /**
     * Image entity
     *
     * @var Image
     */
    protected $_image;

    /**
     * The list of farm role scaling metrics
     *
     * @var FarmRoleScalingMetric[]
     */
    protected $_farmRoleMetrics = [];

    /**
     * Magic getter.
     * Gets the values of the properties that require initialization.
     *
     * @param   string  $name   Name of property that is accessed
     *
     * @return  mixed   Returns property value
     */
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

            // @deprecated
            case 'farm':
                if (empty($this->_farm) && !empty($this->farmId)) {
                    $this->_farm = Farm::findPk($this->farmId);
                }

                return $this->_farm;

            // @deprecated
            case 'role':
                if (!empty($this->roleId)) {
                    if (empty($this->_role) || $this->_role->id != $this->roleId) {
                        $this->_role = Role::findPk($this->roleId);
                    }
                }

                return $this->_role;

            case 'farmRoleMetrics':
                if (empty($this->_farmRoleMetrics)) {
                    $farmRoleMetrics = [];
                    /* @var $farmRoleMetric FarmRoleScalingMetric */
                    foreach (FarmRoleScalingMetric::findByFarmRoleId($this->id) as $farmRoleMetric) {
                        $farmRoleMetrics[$farmRoleMetric->metricId] = $farmRoleMetric;
                    }

                    if (!empty($farmRoleMetrics)) {
                        /* @var  $metric ScalingMetric */
                        foreach (ScalingMetric::find([['id' => ['$in' => array_keys($farmRoleMetrics)]]]) as $metric) {
                            /* @var $farmRoleMetric FarmRoleScalingMetric */
                            $farmRoleMetric = $farmRoleMetrics[$metric->id];
                            $farmRoleMetric->metric = $metric;
                            $this->_farmRoleMetrics[$metric->name] = $farmRoleMetric;
                        }
                    }
                }

                return $this->_farmRoleMetrics;

            default:
                return parent::__get($name);
        }
    }

    /**
     * Get Role entity
     *
     * @return Role|null
     */
    public function getRole()
    {
        if (!empty($this->roleId)) {
            if (empty($this->_role) || $this->_role->id != $this->roleId) {
                $this->_role = Role::findPk($this->roleId);
            }
        }

        return $this->_role;
    }

    /**
     * Get Farm entity
     *
     * @return Farm|null
     */
    public function getFarm()
    {
        if (empty($this->_farm) && !empty($this->farmId)) {
            $this->_farm = Farm::findPk($this->farmId);
        }

        return $this->_farm;
    }

    /**
     * Gets the Image Entity
     *
     * @return  Image|null   Returns the Image that corresponds to the Server
     */
    public function getImage()
    {
        if (empty($this->_image) && !empty($this->roleId) && !empty($this->platform)) {
            $i = new Image();
            $ri = new RoleImage();

            $rec = $this->db()->GetRow("
                SELECT {$i->fields()}
                FROM {$i->table()}
                LEFT JOIN {$ri->table()} ON {$i->columnPlatform} = {$ri->columnPlatform}
                    AND {$i->columnCloudLocation} = {$ri->columnCloudLocation}
                    AND {$i->columnId} = {$ri->columnImageId}
                WHERE {$ri->columnRoleId} = ?
                AND {$ri->columnPlatform} = ?
                AND {$ri->columnCloudLocation} = ?
                AND ({$i->columnAccountId} IS NULL OR {$i->columnAccountId} = ?
                    AND ({$i->columnEnvId} IS NULL OR {$i->columnEnvId} = ?)
                )
            ", [
                $this->roleId,
                $this->platform,
                in_array($this->platform, [SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::AZURE]) ? '' : $this->cloudLocation,
                $this->getFarm()->accountId,
                $this->getFarm()->envId
            ]);

            if ($rec) {
                $this->_image = $i;
                $this->_image->load($rec);
            }
        }

        return $this->_image;
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
            // we should set scaling to manual to prevent starting new instances while we are deleting FarmRole
            $frs = new FarmRoleSetting();
            $db->Execute("
                 UPDATE {$frs->table()}
                 SET {$frs->columnValue} = ?
                 WHERE {$frs->columnFarmRoleId} = ?
                 AND {$frs->columnName} = ?
            ", [0, $this->id, FarmRoleSetting::SCALING_ENABLED]);

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

        if ($minInstances < 0) {
            $minInstances = 0;
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

    /**
     * Return ServerImport class
     *
     * @param   Account\User $user
     * @return  Scalr\Server\Import\AbstractServerImport
     * @throws  NotSupportedException
     */
    public function getServerImport($user)
    {
        $path = sprintf("Scalr/Server/Import/Platforms/%sServerImport", ucfirst(strtolower($this->platform)));
        if (!file_exists(SRCPATH . '/' . $path . '.php')) {
            throw new NotSupportedException(sprintf('Platform "%s" is not supported', $this->platform));
        }

        $cls = str_replace('/', '\\', $path);
        return new $cls($this, $user);
    }

    /**
     * Get a list of names farm-role scaling metrics.
     *
     * @param int $farmRoleId farm-role identifier
     * @return array
     */
    public static function listFarmRoleMetric($farmRoleId)
    {
        $result = [];
        /* @var $metric ScalingMetric */
        $metric = new ScalingMetric();
        /* @var $farmRoleMetric FarmRoleScalingMetric */
        $farmRoleMetric = new FarmRoleScalingMetric();
        $criteria[static::STMT_FROM] = "{$metric->table()} JOIN  {$farmRoleMetric->table()}  ON {$farmRoleMetric->columnMetricId} = {$metric->columnId}";
        $criteria[static::STMT_WHERE] = "{$farmRoleMetric->columnFarmRoleId} = {$farmRoleId}";
        $farmRoleMetrics = ScalingMetric::find($criteria);

        if ($farmRoleMetrics->count() > 0) {
            /* @var $scalingMetric ScalingMetric */
            foreach ($farmRoleMetrics as $scalingMetric) {
                $result[] = $scalingMetric->name;
            }
        }

        return $result;
    }

    /**
     * This method was done for server import, but it has been already refactored in SCALRCORE-2867.
     *
     * Return list of tags that should be applied on cloud resources.
     * GV interpolation IS NOT applied on it yet.
     *
     * @param   bool    $addNameTag     optional    If true add tag Name (on EC2 cloud)
     * @return  array   Return list of tags [key => value]
     */
    public function getCloudTags($addNameTag = false)
    {
        $tags = [];
        $governance = new Scalr_Governance($this->getFarm()->envId);

        if ($addNameTag && $this->platform == SERVER_PLATFORMS::EC2) {
            $nameFormat = $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_INSTANCE_NAME_FORMAT);
            if (!$nameFormat) {
                $nameFormat = $this->settings[FarmRoleSetting::AWS_INSTANCE_NAME_FORMAT];
                if (!$nameFormat) {
                    $nameFormat = "{SCALR_FARM_NAME} -> {SCALR_FARM_ROLE_ALIAS} #{SCALR_INSTANCE_INDEX}";
                }
            }
            $tags['Name'] = $nameFormat;
        }

        $tags[Scalr_Governance::SCALR_META_TAG_NAME] = Scalr_Governance::SCALR_META_TAG_VALUE;

        $gTags = (array)$governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_TAGS);
        $gAllowAdditionalTags = $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_TAGS, 'allow_additional_tags');
        if (count($gTags) > 0) {
            foreach ($gTags as $tKey => $tValue) {
                if ($tKey && count($tags) < 10 && !isset($tags[$tKey]))
                    $tags[$tKey] = $tValue;
            }
        }
        if (count($gTags) == 0 || $gAllowAdditionalTags) {
            //Custom tags
            $cTags = $this->settings[FarmRoleSetting::AWS_TAGS_LIST];
            $tagsList = @explode("\n", $cTags);
            foreach ((array)$tagsList as $tag) {
                $tag = trim($tag);
                if ($tag && count($tags) < 10) {
                    $tagChunks = explode("=", $tag);
                    if (!isset($tags[trim($tagChunks[0])]))
                        $tags[trim($tagChunks[0])] = trim($tagChunks[1]);
                }
            }
        }

        return $tags;
    }
}
