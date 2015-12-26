<?php

namespace Scalr;

use Exception;
use Scalr\Exception\AuditLoggerException;
use Scalr\Model\Entity;
use Scalr_Account_User;
use Scalr_Session;
use DBFarm;


/**
 * AuditLogger
 */
class AuditLogger extends \Scalr\Util\Logger
{
    /**
     * UI request type
     */
    const REQUEST_TYPE_UI = 'ui';

    /**
     * API request type
     */
    const REQUEST_TYPE_API = 'api';

    /**
     * SYSTEM request type
     */
    const REQUEST_TYPE_SYSTEM = 'system';

    /**
     * User object
     *
     * @var Entity\Account\User|Scalr_Account_User
     */
    protected $user;

    /**
     * Real user ID
     *
     * @var int
     */
    protected $realUserId;

    /**
     * Environment ID
     *
     * @var int
     */
    protected $envId;

    /**
     * Identifier of the Account
     *
     * @var int
     */
    protected $accountId;

    /**
     * IP address of a remote user
     *
     * @var string
     */
    protected $ipAddress;

    /**
     * Event subscribers
     *
     * @var array
     */
    protected $subscribers;

    /**
     * Request type
     *
     * @var string
     */
    protected $requestType;

    /**
     * System task name
     *
     * @var string
     */
    protected $systemTask;

    /**
     * Constructor. Instantiates AuditLogger, prepares backend
     *
     * @param   Entity\Account\User|Scalr_Account_User        $user        optional User object
     * @param   int                                           $envId       optional Environment ID
     * @param   string                                        $ipAddress   optional IP address of a remote client
     * @param   int                                           $realUserId  optional ID of a real user
     * @param   string                                        $requestType optional The type of the request
     * @param   string                                        $systemTask  optional The name of the system task
     */
    public function __construct(
        $user = null,
        $envId = null,
        $ipAddress = "unknown",
        $realUserId = null,
        $requestType = null,
        $systemTask = null
    ) {
        parent::__construct(\Scalr::config('scalr.auditlog'));

        $this->user        = $user;
        $this->ipAddress   = $ipAddress;
        $this->realUserId  = $realUserId;
        $this->systemTask  = $systemTask;
        $this->accountId   = isset($user) ? $this->user->getAccountId() : null;

        $this->setEnvironmentId($envId);

        $this->setRequestType($requestType ?: static::REQUEST_TYPE_UI);

        $this->initializeSubscribers();
    }

    /**
     * Initializes Event subscribers
     *
     * The use of the subscribers is to transform object to array
     */
    protected function initializeSubscribers()
    {
        $this->subscribers = [];

        $this->subscribers['farm.terminate'] = [$this, 'handlerFarmLaunch'];
        $this->subscribers['farm.launch'] = [$this, 'handlerFarmLaunch'];
        $this->subscribers['user.auth.login'] = [$this, 'handlerUserAuthLogin'];
    }

    /**
     * Prepares extra data to pass to a backend
     * Those include RUID, EUID, AccountID, environment etc.
     *
     * @return array Prepared extra data for logging
     */
    protected function getCommonData()
    {
        $data = empty($this->user) ? [
            "login"        => $this->systemTask ? '_' . $this->systemTask : 'guest',
            "ruid"         => null,
            "euid"         => null,
            "env_id"       => $this->envId ?: 0,
            "account_id"   => $this->accountId,
        ] : [
            "login"        => $this->user->getEmail(),
            "ruid"         => $this->realUserId ?: $this->user->id,
            "euid"         => $this->user->id,
            "env_id"       => $this->envId ?: 0,
            "account_id"   => empty($this->accountId) ? $this->user->getAccountId() : $this->accountId,
        ];

        $data['ip_address'] = $this->ipAddress;
        $data['timestamp'] = AuditLogger::getTimestamp();
        $data['request_type'] = $this->requestType;

        return $data;
    }

    /**
     * Logs event to a specified backend
     *
     * @param  string  $event      Event tag
     * @param  mixed   $extra      optional Extra data to pass.
     * @param  mixed   $extra,...  optional
     * @return boolean Indicates whether operation was successful
     * @throws AuditLoggerException
     */
    public function auditLog($event, ...$extra)
    {
        if (!$this->enabled) {
            return true;
        }

        if (!empty($extra)) {
            if (array_key_exists($event, $this->subscribers)) {
                $extra = $this->subscribers[$event](...$extra);
            } else {
                $extra = $extra[0];
            }
        } else {
            $extra = [];
        }

        $adjusted = [];

        foreach ($extra as $key => $val) {
            if (($pos = strpos($key, '.')) == 0) {
                //It will adjust data key with the event name when the key either does not contain
                //dot or starts with dot.
                $adjusted[$event . ($pos === false ? '.' : '') . $key] = $val;
            } else {
                $adjusted[$key] = $val;
            }
        }

        $adjusted = array_merge($this->getCommonData(), $adjusted);

        $adjusted["tags"] = [$event];

        if (!empty($this->defaultTag)) {
            $adjusted["tags"][] = $this->defaultTag;
        }

        $data = [
            "tag"      => $this->defaultTag,
            "message"  => $event,
            "extra"    => $adjusted,
        ];

        try {
            $result = $this->writer->send($data);
        } catch (Exception $e) {
            \Scalr::logException(new Exception(sprintf("Audit logger couldn't log the record: %s", $e->getMessage()), $e->getCode(), $e));

            $result = false;
        }

        return $result;
    }

    /**
     * Sets identifier of the Environment
     *
     * @param   int          $envId Identifier of the User's Environment
     * @return  AuditLogger
     */
    public function setEnvironmentId($envId)
    {
        $this->envId = $envId;

        return $this;
    }

    /**
     * Sets identifier of the Account
     *
     * @param   int          $accountId Identifier of the User's Account
     * @return  AuditLogger
     */
    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;

        return $this;
    }

    /**
     * Sets request type
     *
     * @param    string   $requestType  Request type
     * @return   AuditLogger
     */
    public function setRequestType($requestType)
    {
        $this->requestType = $requestType;

        return $this;
    }

    /**
     * farm.launch | farm.terminate handler
     *
     * @param  Entity\Farm|DBFarm  $farm       Farm instance
     * @param  array               $additional Additional fields to log
     * @return array Returns array of the fields to log
     */
    private function handlerFarmLaunch($farm, array $additional = null)
    {
        if ($farm instanceof DBFarm) {
            //We have to start the data key with the dot because it contains dot itself.
            $data = [
                'farm_id'           => $farm->ID,
                'farm_name'         => $farm->Name,
                '.owner.user_id'    => $farm->createdByUserId,
                '.owner.user_email' => $farm->createdByUserEmail,
                '.owner.team_id'    => $farm->teamId
            ];
        } else if ($farm instanceof Entity\Farm) {
            $data = [
                'farm_id'           => $farm->id,
                'farm_name'         => $farm->name,
                '.owner.user_id'    => $farm->createdById,
                '.owner.user_email' => $farm->createdByEmail,
                '.owner.team_id'    => $farm->teamId
            ];
        }

        $result = isset($data) ? $data : $farm;

        if (!empty($additional)) {
            $result = array_merge($result, $additional);
        }

        return $result;
    }

    /**
     * user.auth.login handler
     *
     * @param  Entity\Account\User|Scalr_Account_User|array $user User object
     * @param  int     $envId Identifier of the Environment
     * @param  string  $ip    Remote Addres of the User
     * @param  int     $ruid  Real user identifier
     * @return array   Returns array of the fields to log
     */
    private function handlerUserAuthLogin($user, $envId = null, $ip = null, $ruid = null)
    {
        if ($user instanceof Scalr_Account_User || $user instanceof Entity\Account\User) {
            $this->user = $user;
            $this->envId = $envId;
            $this->ipAddress = $ip;
            $this->realUserId = $ruid;

            // Teams are needed for the audit log
            $teams = [];
            foreach ($user->getTeams() as $rec) {
                $teams[$rec['id']] = $rec['name'];
            }

            $lastVisit = $user->dtLastLogin ?: $user->dtCreated;

            $data = [
                'result'     => 'success',
                'teams'      => $teams,
                'last_login' => $lastVisit ? static::getTimestamp(strtotime($lastVisit)) : null,
                'user_type'  => $user->type,
            ];
        }

        return isset($data) ? $data : $user;
    }
}
