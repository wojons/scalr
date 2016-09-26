<?php

namespace Scalr\LogCollector;

use AbstractServerEvent;
use DBServer;
use Scalr\Model\Entity;
use Scalr_Account_User;
use DBFarm;

/**
 * AuditLogger
 */
class AuditLogger extends AbstractLogger
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
     * @var Scalr_Account_User
     */
    private $user;

    /**
     * Account id
     *
     * @var int
     */
    private $accountId;

    /**
     * Environment id
     *
     * @var int
     */
    private $envId;

    /**
     * Ip address
     *
     * @var string
     */
    private $remoteAddr;

    /**
     * Real user id
     *
     * @var int
     */
    private $ruid;

    /**
     * Request type
     *
     * @var string
     */
    private $requestType;

    /**
     * System task
     *
     * @var string
     */
    private $systemTask;

    /**
     * Constructor. Instantiates AuditLogger, prepares backend
     *
     * @param AuditLoggerConfiguration $config  Audit logger config data
     */
    public function __construct(AuditLoggerConfiguration $config) {
        parent::__construct(\Scalr::config('scalr.logger.audit'));

        $this->user         = $config->user;
        $this->accountId    = $config->accountId;
        $this->envId        = $config->envId;
        $this->remoteAddr   = $config->remoteAddr;
        $this->ruid         = $config->ruid;
        $this->requestType  = $config->requestType;
        $this->systemTask   = $config->systemTask;
    }

    /**
     * Sets user object
     *
     * @param   Scalr_Account_User   $user User object
     * @return  AuditLogger
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
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
     *
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
     *
     * @return   AuditLogger
     */
    public function setRequestType($requestType)
    {
        $this->requestType = $requestType;

        return $this;
    }

    /**
     * Sets system task
     *
     * @param    string   $systemTask  System task
     *
     * @return   AuditLogger
     */
    public function setSystemTask($systemTask)
    {
        $this->systemTask = $systemTask;

        return $this;
    }

    /**
     * Sets user identifier
     *
     * @param    int   $ruid  Real user identifier
     *
     * @return   AuditLogger
     */
    public function setRuid($ruid)
    {
        $this->ruid = $ruid;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see AbstractLogger::initializeSubscribers()
     */
    protected function initializeSubscribers()
    {
        parent::initializeSubscribers();

        $this->subscribers['farm.terminate']    = [$this, 'handlerFarmLaunch'];
        $this->subscribers['farm.launch']       = [$this, 'handlerFarmLaunch'];
        $this->subscribers['user.auth.login']   = [$this, 'handlerUserAuthLogin'];
        $this->subscribers['script.execute']    = [$this, 'handleScriptExecute'];
    }

    /**
     * {@inheritdoc}
     * @see AbstractLogger::getCommonData()
     */
    protected function getCommonData()
    {
        $data = parent::getCommonData();

        if (empty($this->user)) {
            $data['login']      = $this->systemTask ? '_' . $this->systemTask : 'guest';
            $data['ruid']       = null;
            $data['euid']       = null;
            $data['account_id'] = $this->accountId;
        } else {
            $data['login']      = $this->user->getEmail();
            $data['ruid']       = $this->ruid ?: $this->user->id;
            $data['euid']       = $this->user->id;
            $data['account_id'] = $this->user->getAccountId();
        }

        $data['env_id']       = $this->envId;
        $data['ip_address']   = $this->remoteAddr;
        $data['request_type'] = $this->requestType;

        return $data;
    }

    /**
     * farm.launch | farm.terminate handler
     *
     * @param  Entity\Farm|DBFarm  $farm       Farm instance
     * @param  array               $additional Additional fields to log
     *
     * @return array Returns array of the fields to log
     */
    protected function handlerFarmLaunch($farm, array $additional = null)
    {
        if ($farm instanceof DBFarm) {
            //We have to start the data key with the dot because it contains dot itself.
            $data = [
                '.farm_id'          => $farm->ID,
                '.farm_name'        => $farm->Name,
                '.owner.user_id'    => $farm->ownerId,
                '.owner.user_email' => $farm->createdByUserEmail,
                '.owner.teams'      => Entity\FarmTeam::getTeamIdsByFarmId($farm->ID)
            ];
        } else if ($farm instanceof Entity\Farm) {
            $data = [
                '.farm_id'          => $farm->id,
                '.farm_name'        => $farm->name,
                '.owner.user_id'    => $farm->ownerId,
                '.owner.user_email' => $farm->createdByEmail,
                '.owner.teams'      => Entity\FarmTeam::getTeamIdsByFarmId($farm->id)
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
     * @param Scalr_Account_User|Entity\Account\User  $user  User object
     * @return array   Returns array of the fields to log
     */
    protected function handlerUserAuthLogin($user)
    {
        if ($user instanceof Scalr_Account_User || $user instanceof Entity\Account\User) {
            $this->user = $user;

            $teams = [];
            // Teams are needed for the audit log
            foreach ($user->getTeams() as $rec) {
                $teams[$rec['id']] = $rec['name'];
            }

            $lastVisit = $user->dtLastLogin ?: $user->dtCreated;

            $data = [
                '.result'     => 'success',
                '.teams'      => $teams,
                '.last_login' => $lastVisit ? static::getTimestamp(strtotime($lastVisit)) : null,
                '.user_type'  => $user->type,
            ];
        }

        return isset($data) ? $data : $user;
    }

    /**
     * script.execute handler
     *
     * @param array               $script                   Script settings
     * @param DBServer            $targetServer             Target server object
     * @param int                 $taskId        optional   Scheduler task identifier
     * @param AbstractServerEvent $event         optional   Event object
     * @return array Returns array of the fields to log
     */
    protected function handleScriptExecute(array $script, DBServer $targetServer, $taskId = null, AbstractServerEvent $event = null)
    {
        $data = [
            '.script.id'           => $script['id'],
            '.script.name'         => $script['name'],
            '.script.version'      => $script['scriptVersion'],
            '.target.account_id'   => $targetServer->clientId,
            '.target.env_id'       => $targetServer->envId,
            '.target.farm_id'      => $targetServer->farmId,
            '.target.farm_role_id' => $targetServer->farmRoleId,
            '.target.server_id'    => $targetServer->serverId
        ];

        if (isset($event)) {
            $data['.executed_by']        = 'event';
            $data['.event.name']         = $event->GetName();
            $data['.event.triggered_by'] = $event->DBServer->serverId;
        } else if (isset($taskId)) {
            $data['.executed_by']        = 'scheduler';
            $data['.event.triggered_by'] = $targetServer->serverId;
            $data['service.scheduler.task_id'] = $taskId;
        } else {
            $data['.executed_by']        = 'user';
            $data['.event.triggered_by'] = $targetServer->serverId;
        }

        if (!empty($script['path'])) {
            $data['.script.path'] = $script['path'];
        }

        return $data;
    }

}
