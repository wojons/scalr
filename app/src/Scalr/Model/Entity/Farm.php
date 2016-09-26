<?php

namespace Scalr\Model\Entity;

use DateTime;
use DBFarm;
use FarmLaunchedEvent;
use Exception;
use FARM_STATUS;
use LogicException;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\LockedException;
use Scalr\Exception\Model\Entity\Farm\FarmInUseException;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Model\Collections\EntityIterator;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Model\Entity\Account\Team;
use Scalr\Model\Entity\Account\User;
use Scalr\Service\Aws;
use Scalr_SchedulerTask;
use Scalr_Scripting_GlobalVariables;
use Scalr\Modules\Platforms\Ec2;
use Scalr\Acl\Acl;


/**
 * Farm entity
 *
 * @author N.V.
 *
 * @property    SettingsCollection  $settings   The list of Farm Settings
 * @property    FarmRole[]          $farmRoles  The list of Farm roles contained in Farm
 * @property    Server[]            $servers    The list of Servers related to the Farm
 *
 * @Entity
 * @Table(name="farms")
 */
class Farm extends AbstractEntity implements AccessPermissionsInterface
{

    const STATUS_RUNNING       = 1;
    const STATUS_TERMINATED    = 0;
    const STATUS_TERMINATING   = 2;
    const STATUS_SYNCHRONIZING = 3;

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
     * Account Id
     *
     * @Column(name="clientid",type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * Environment Id
     *
     * @Column(type="integer")
     * @var int
     */
    public $envId;

    /**
     * Farm name
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Completion status
     *
     * @Column(name="iscompleted",type="boolean")
     * @var bool
     */
    public $isCompleted;

    /**
     * Hash
     *
     * @Column(type="string")
     * @var string
     */
    public $hash;

    /**
     * Added time
     *
     * @Column(name="dtadded",type="datetime")
     * @var DateTime
     */
    public $added;

    /**
     * Status
     *
     * @Column(type="integer")
     * @var int
     */
    public $status;

    /**
     * Launched time
     *
     * @Column(name="dtlaunched",type="datetime")
     * @var DateTime
     */
    public $launched;

    /**
     * Synchronization fail flag
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $termOnSyncFail;

    /**
     * Region name
     *
     * @Column(type="string")
     * @var string
     */
    public $region;

    /**
     * Launch order type
     *
     * @Column(name="farm_roles_launch_order",type="boolean")
     * @var bool
     */
    public $launchOrder;

    /**
     * Comments
     *
     * @Column(type="string")
     * @var string
     */
    public $comments;

    /**
     * Creator Id
     *
     * @Column(name="created_by_id",type="integer",nullable=true)
     * @var int
     */
    public $ownerId;

    /**
     * Creator E-Mail
     *
     * @Column(type="string")
     * @var string
     */
    public $createdByEmail;

    /**
     * Last editor Id
     *
     * @Column(type="integer")
     * @var int
     */
    public $changedById;

    /**
     * Last change time
     * TODO: convert to DateTime with MySQL 5.6
     *
     * @Column(name="changed_time",type="string")
     * @var string
     */
    public $changed;

    /**
     * Farm settings collection
     *
     * @var SettingsCollection
     */
    protected $_settings;

    /**
     * Farm roles collection
     *
     * @var EntityIterator|FarmRole[]
     */
    protected $_farmRoles;

    /**
     * Servers collection
     *
     * @var EntityIterator|Server[]
     */
    protected $_servers;

    /**
     * @var Environment
     */
    protected $_environment;

    /**
     * The Farm teams
     *
     * @var EntityIterator|Team[]
     */
    protected $_teams;

    /**
     * True value indicates that the set of the Teams associated with the Farm has been changed
     * within the object with respect to database.
     *
     * @var bool
     */
    protected $_teamsChanged = false;

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
                        'Scalr\Model\Entity\FarmSetting',
                        [[ 'farmId' => &$this->id ]],
                        [ 'farmId' => &$this->id ]
                    );
                }

                return $this->_settings;

            case 'farmRoles':
                if (empty($this->_farmRoles)) {
                    $this->_farmRoles = FarmRole::findByFarmId($this->id);
                }

                return $this->_farmRoles;

            case 'servers':
                if (empty($this->_servers)) {
                    $this->_servers = Server::findByFarmId($this->id);
                }

                return $this->_servers;

            default:
                return parent::__get($name);
        }
    }

    /**
     * Reset farm id on clone
     */
    public function __clone()
    {
        if (empty($this->_settings)) {
            $this->settings->load();
        }

        $unref = null;
        $this->id = &$unref;

        $this->_settings = clone $this->_settings;

        $this->_settings->setCriteria([[ 'farmId' => &$this->id ]]);
        $this->_settings->setDefaultProperties([ 'farmId' => &$this->id ]);
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::save()
     */
    public function save()
    {
        $this->changed = microtime();

        $db = $this->db();

        try {
            $db->BeginTrans();

            if (empty($this->id)) {
                $this->ownerId = $this->changedById;

                $this->settings[FarmSetting::CREATED_BY_ID] = $this->ownerId;
                $this->settings[FarmSetting::CREATED_BY_EMAIL] = User::findPk($this->ownerId)->email;

                $this->added = new DateTime();

                $this->status = FARM_STATUS::TERMINATED;

                if (empty($this->settings[FarmSetting::TIMEZONE])) {
                    $this->settings[FarmSetting::TIMEZONE] = date_default_timezone_get();
                }

                $this->settings[FarmSetting::CRYPTO_KEY] = \Scalr::GenerateRandomKey(40);

                /* @var $governance Governance */
                $governance = Governance::findPk($this->envId, Governance::CATEGORY_GENERAL, Governance::GENERAL_LEASE);
                if (!empty($governance) && $governance->enabled) {
                    $this->settings[FarmSetting::LEASE_STATUS] = 'Active';
                }

                //TODO: unused field
                $this->region = Aws::REGION_US_EAST_1;
            }

            parent::save();

            if (!empty($this->_settings)) {
                $this->_settings->save();
            }

            if (isset($this->_teamsChanged)) {
                $this->saveTeams($this->_teams);

                $this->_teamsChanged = false;
            }

            $db->CommitTrans();
        } catch (Exception $e) {
            $db->RollbackTrans();

            throw $e;
        }
    }

    /**
     * Checks if farm is locked
     *
     * @throws  LockedException
     */
    public function checkLocked()
    {
        if ($this->settings[FarmSetting::LOCK]) {
            throw new LockedException($this->settings[FarmSetting::LOCK_BY], $this, $this->settings[FarmSetting::LOCK_COMMENT]);
        }
    }

    /**
     * Locks this farm
     *
     * @param   User    $user                The User on whose behalf the lock set
     * @param   string  $comment             Comment describing the reason and/or purpose of the lock
     * @param   bool    $restrict   optional Strict lock flag (I have no idea what that means [author's note])
     *
     * @return  Farm
     */
    public function lock(User $user, $comment, $restrict = false)
    {
        $this->_settings->saveSettings([
            FarmSetting::LOCK => 1,
            FarmSetting::LOCK_BY =>  $user->id,
            FarmSetting::LOCK_COMMENT =>  $comment,
            FarmSetting::LOCK_UNLOCK_BY =>  '',
            FarmSetting::LOCK_RESTRICT => $restrict
        ]);

        return $this;
    }

    /**
     * Unlocks this farm
     *
     * @param   User    $user The User on whose behalf the lock unset
     * @return  Farm
     */
    public function unlock(User $user)
    {
        $this->_settings->saveSettings([
            FarmSetting::LOCK => '',
            FarmSetting::LOCK_BY =>  '',
            FarmSetting::LOCK_UNLOCK_BY => $user->id,
            FarmSetting::LOCK_COMMENT =>  '',
            FarmSetting::LOCK_RESTRICT =>  ''
        ]);

        return $this;
    }

    /**
     * Launch this farm
     *
     * @param   User    $user optional
     *          The user who initiates Farm launch
     *
     * @param   array   $auditLogExtra optional
     *          Audit log extra fields to record
     *
     * @return  Farm
     *
     * @throws  Exception
     * @throws  LockedException
     */
    public function launch($user = null, array $auditLogExtra = null)
    {
        $this->checkLocked();

        \Scalr::FireEvent($this->id, new FarmLaunchedEvent(true, !empty($user->id) ? $user->id : null, $auditLogExtra));

        return $this;
    }

    /**
     * Creates clone for the farm
     * TODO: refactor farms cloning
     *
     * @param   string  $name   The name of a new Farm
     * @param   User    $user   The User that initiates cloning
     *
     * @return Farm Returns clone
     * @throws Exception
     */
    public function cloneFarm($name, User $user)
    {
        $farm = clone $this;

        $farm->changedById = $user->id;
        $farm->name = $name;

        $farm->save();

        $variables = new Scalr_Scripting_GlobalVariables($farm->accountId, $farm->envId, ScopeInterface::SCOPE_FARM);
        $variables->setValues($variables->getValues(0, $this->id), 0, $farm->id);

        $dbFarm = DBFarm::LoadByID($this->id);
        $dbFarmClone = DBFarm::LoadByID($farm->id);

        $dbFarm->cloneFarmRoles($dbFarmClone);

        $ft = new FarmTeam();
        $this->db()->Execute("
            INSERT INTO {$ft->table()} ({$ft->columnFarmId}, {$ft->columnTeamId} )
            SELECT ?, {$ft->columnTeamId}
            FROM {$ft->table()}
            WHERE {$ft->columnFarmId} = ?
        ", [$farm->id, $this->id]);

        return $farm;
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::delete()
     */
    public function delete()
    {
        if ($this->status != FARM_STATUS::TERMINATED) {
            throw new FarmInUseException("Cannot delete a running farm, please terminate a farm before deleting it");
        }

        $servers = Server::find([
            ['farmId' => $this->id],
            ['status' => ['$ne' => Server::STATUS_TERMINATED]]
        ]);

        if (count($servers)) {
            throw new FarmInUseException(sprintf("Cannot delete a running farm, %s server%s still running on this farm", count($servers), count($servers) > 1 ? 's are' : ' is'));
        }

        $db = $this->db();

        try {
            $db->BeginTrans();

            foreach ($this->farmRoles as $farmRole) {
                $farmRole->delete();
            }

            $this->deleteScheduled();

            $db->Execute("DELETE FROM `logentries` WHERE `farmid` = ?", [ $this->id ]);
            $db->Execute("DELETE FROM `elastic_ips` WHERE `farmid` = ?", [ $this->id ]);
            $db->Execute("DELETE FROM `events` WHERE `farmid` = ?", [ $this->id ]);
            $db->Execute("DELETE FROM `ec2_ebs` WHERE `farm_id` = ?", [ $this->id ]);

            $db->Execute("DELETE FROM `farm_lease_requests` WHERE `farm_id` = ?", [ $this->id ]);

            foreach ($this->servers as $server) {
                $server->delete();
            }

            $db->Execute("UPDATE `dns_zones` SET `farm_id` = '0', `farm_roleid` ='0' WHERE `farm_id` = ?", [ $this->id ]);
            $db->Execute("UPDATE `apache_vhosts` SET `farm_id` = '0', `farm_roleid` ='0' WHERE `farm_id` = ?", [ $this->id ]);

            parent::delete();

            $db->CommitTrans();
        } catch (Exception $e) {
            $db->RollbackTrans();

            throw $e;
        }

        $db->Execute("DELETE FROM `orchestration_log` WHERE `farmid` = ?", [ $this->id ]);
    }

    /**
     * Delete scheduled tasks
     */
    public function deleteScheduled()
    {
        $this->db()->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type = ?", array(
            $this->id,
            Scalr_SchedulerTask::TARGET_FARM
        ));
    }

    /**
     * Check if given user belongs to team, which has access to farm
     *
     * @param   Account\User    $user   User entity
     * @return  bool
     */
    public function hasUserTeamOwnership($user)
    {
        return !!$this->db()->GetOne("SELECT " . self::getUserTeamOwnershipSql($user->id, $this->id));
    }

    /**
     * Generate SQL query like "EXISTS(SELECT 1 FROM farm_teams .... WHERE ...) to check FARM_TEAMS permission.
     * Table `farms` should have alias `f`.
     * If farmId is set, when JOIN table farms to get envId from it.
     *
     * @param   int     $userId             Identifier of User
     * @param   int     $farmId optional    Identifier of Farm
     * @return  string
     */
    public static function getUserTeamOwnershipSql($userId, $farmId = null)
    {
        $farm = new Farm();
        $farmTeam = new FarmTeam();
        $accountTeamUser = new Account\TeamUser();
        $accountTeamEnv = new Account\TeamEnvs();

        $sql = "EXISTS("
            . "SELECT 1 FROM {$farmTeam->table()}"
            . "JOIN {$accountTeamUser->table()} ON {$accountTeamUser->columnTeamId} = {$farmTeam->columnTeamId} "
            . "JOIN {$accountTeamEnv->table()} ON {$accountTeamEnv->columnTeamId} = {$farmTeam->columnTeamId} "
            . ($farmId ? "JOIN {$farm->table('f')} ON {$farmTeam->columnFarmId} = {$farm->columnId('f')}" : "")
            . "WHERE {$accountTeamEnv->columnEnvId()} = {$farm->columnEnvId('f')} "
            . "AND " . ($farmId ?
                ("{$farm->columnId('f')} = " . $farm->db()->qstr($farmId)) :
                ("{$farm->columnId('f')} = {$farmTeam->columnFarmId}")) . " "
            . "AND {$accountTeamUser->columnUserId} = " . $farm->db()->qstr($userId)
            . ")";

        return $sql;
    }

    /**
     * @param   User            $user           The User Entity
     * @param   Environment     $environment    optional The Environment Entity
     * @param   string|bool     $modify         optional ACL Permission Identifier or boolean flag whether it should check modify
     *                                          permissions or not.
     * @return  bool
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        $access = $environment && $this->envId == $environment->id;

        if (is_bool($modify)) {
            $modify = $modify ? Acl::PERM_FARMS_UPDATE : null;
        }

        if ($access) {
            $superposition = $user->getAclRolesByEnvironment($environment->id);
            $access = $superposition->isAllowed(Acl::RESOURCE_FARMS, $modify);

            if (!$access && $this->hasUserTeamOwnership($user)) {
                $access = $superposition->isAllowed(Acl::RESOURCE_TEAM_FARMS, $modify);
            }

            if (!$access && $this->ownerId && $user->id == $this->ownerId) {
                $access = $superposition->isAllowed(Acl::RESOURCE_OWN_FARMS, $modify);
            }
        }

        return $access;
    }

    /**
     * Return Environment entity
     *
     * @return  Environment|null
     */
    public function getEnvironment()
    {
        if (!empty($this->envId) && empty($this->_environment)) {
            $this->_environment = Environment::findPk($this->envId);
        }

        return $this->_environment;
    }

    /**
     * Sets the list of the Teams which own the Farm
     *
     * @param   EntityIterator|Team[]   $teams           The list of the Teams that which own the Farm
     */
    public function setTeams($teams)
    {
        $this->_teams = $teams;

        $this->_teamsChanged = true;
    }

    /**
     * Associates a Farm with specified Teams.
     * 
     * This method should only be called within a transaction.
     *
     * @param   EntityIterator|Team[]   $teams    Collection of the Teams
     */
    public function saveTeams($teams)
    {
        if (!$this->db()->transCnt) {
            throw new LogicException("This method should only be called within a transaction!");
        }

        $farmTeam = new FarmTeam();

        if (!empty($teams)) {
            $values = [];
            $args = [];

            $farmIdType = $farmTeam->getIterator()->getField('farmId')->type;
            $farmIdWh = $farmIdType->wh();

            $teamIdType = $farmTeam->getIterator()->getField('teamId')->type;
            $teamIdWh = $teamIdType->wh();

            foreach ($teams as $team) {
                $values[] = "({$farmIdWh}, {$teamIdWh})";
                $args[] = $farmIdType->toDb($this->id);
                $args[] = $teamIdType->toDb($team->id);
            }
        }


        FarmTeam::deleteByFarmId($this->id);

        if (!empty($values)) {
            $this->db()->Execute("INSERT IGNORE INTO {$farmTeam->table()} (`farm_id`, `team_id`) VALUES " . implode(', ', $values), $args);
        }
    }

    /**
     * Gets the list of the Teams which own the Farm
     *
     * @return EntityIterator|Team[]
     */
    public function getTeams()
    {
        if (empty($this->_teams)) {
            $team = new Team();
            $farmTeam = new FarmTeam();

            $this->_teams = Team::find([
                AbstractEntity::STMT_FROM => " {$team->table()}
                    JOIN {$farmTeam->table('ft')} ON {$farmTeam->columnTeamId('ft')} = {$team->columnId()}
                        AND {$farmTeam->columnFarmId('ft')} = " . $farmTeam->qstr('farmId', $this->id) . "
                    "
            ]);
        }

        return $this->_teams;
    }

    /**
     * Appends Teams list
     *
     * @param   Team    $team                The Team entity
     *
     * @return Farm
     */
    public function appendTeams(Team $team)
    {
        $this->_teams[$team->id] = $team;

        $this->_teamsChanged = true;

        return $this;
    }

    /**
     * Checks whether the set of the Teams associated with the Farm has been changed.
     *
     * @return bool Returns true if the set of the Teams that is associated with the Farm has been changed
     */
    public function isTeamsChanged()
    {
        return $this->_teamsChanged;
    }

    /**
     * Searches farms by criteria and selecting and initiating their Teams
     *
     * @param    array        $criteria     optional The search criteria.
     * @param    array        $group        optional The group by looks like [property1, ...], by default groups by `id`
     * @param    array        $order        optional The results order looks like [property1 => true|false, ... ]
     * @param    int          $limit        optional The records limit
     * @param    int          $offset       optional The offset
     * @param    bool         $countRecords optional True to calculate total number of the records without limit
     *
     * @return   ArrayCollection|Farm[] Returns collection of the entities.
     *
     * @throws \Scalr\Exception\ModelException
     */
    public static function findWithTeams(array $criteria = null, array $group = null, array $order = null, $limit = null, $offset = null, $countRecords = null)
    {
        $farm = new Farm();

        /* @var $farms Farm[] */
        $farms = [];

        if (!isset($group)) {
            $group = ['id'];
        }

        $collection = $farm->result(AbstractEntity::RESULT_ENTITY_COLLECTION)->find($criteria, $group, $order, $limit, $offset, $countRecords);
        /* @var $farm Farm */
        foreach ($collection as $farm) {
            $farms[$farm->id] = $farm;
        }

        $team = new Team();
        $farmTeam = new FarmTeam();

        $stmt = "
            SELECT {$team->fields()}, {$farmTeam->fields('ft', true)}
            FROM {$team->table()}
            JOIN {$farmTeam->table('ft')} ON {$farmTeam->columnTeamId('ft')} = {$team->columnId()}
              AND {$farmTeam->columnFarmId('ft')} IN ('" . implode("', '", array_keys($farms)) . "')
        ";

        foreach ($team->db()->Execute($stmt) as $row) {
            $team = new Team();
            $team->load($row);
            $farmTeam->load($row, 'ft');

            $farms[$farmTeam->farmId]->_teams[$team->id] = $team;
        }

        return $collection;
    }

    /**
     * Translates status codes to status names
     *
     * @param   int $status Status code
     *
     * @return  string  Status name
     */
    public static function getStatusName($status)
    {
        static $statuses = [
            self::STATUS_RUNNING         => "Running",
            self::STATUS_TERMINATED      => "Terminated",
            self::STATUS_TERMINATING     => "Terminating",
            self::STATUS_SYNCHRONIZING   => "Synchronizing"
        ];

        return $statuses[$status];
    }
}
