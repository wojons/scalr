<?php

namespace Scalr\Model\Entity;

use DateTime;
use FarmLaunchedEvent;
use FarmTerminatedEvent;
use Exception;
use FARM_STATUS;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Exception\LockedException;
use Scalr\Exception\Model\Entity\Farm\FarmInUseException;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity\Account\User;
use Scalr\Service\Aws;
use Scalr_SchedulerTask;
use Scalr\AuditLogger;


/**
 * Farm entity
 *
 * @author N.V.
 *
 * @property    string[]    $settings   The list of Farm Settings
 * @property    FarmRole[]  $farmRoles  The list of Farm roles contained in Farm
 * @property    Server[]    $servers    The list of Servers related to the Farm
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
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $createdById;

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
     * Team Id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $teamId;

    /**
     * Farm settings collection
     *
     * @var SettingsCollection
     */
    protected $_settings;

    /**
     * Farm roles collection
     *
     * @var FarmRole[]
     */
    protected $_farmRoles;

    /**
     * Servers collection
     *
     * @var Server[]
     */
    protected $_servers;

    /**
     * Magic getter
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
     * {@inheritdoc}
     * @see AbstractEntity::save()
     */
    public function save()
    {
        $this->changed = microtime();

        if (empty($this->id)) {
            $this->createdById = $this->changedById;

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

        $db->Execute("DELETE FROM `scripting_log` WHERE `farmid` = ?", [ $this->id ]);
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
     * {@inheritdoc}
     * @see AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        return $environment
            ? $this->envId == $environment->id
            : $user->hasAccessToEnvironment($this->envId);
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