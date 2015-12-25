<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\EventDefinition;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Schedulertasks extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'schedulerTaskId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GENERAL_SCHEDULERTASKS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $events = array_map(
            function($item) {
                if ($item->envId) {
                    $scope = 'environment';
                } else if ($item->accountId) {
                    $scope = 'account';
                } else {
                    $scope = 'scalr';
                }
                return [
                    'name' => $item->name,
                    'description' => $item->description,
                    'scope' => $scope
                ];
            },
            EventDefinition::result(EventDefinition::RESULT_ENTITY_COLLECTION)->find([
                ['$or' => [['accountId' => null], ['accountId' => $this->user->getAccountId()]]],
                ['$or' => [['envId' => null], ['envId' => $this->getEnvironmentId()]]]
            ], null, ['name' => true])->getArrayCopy()
        );

        $this->response->page('ui/schedulertasks/view.js', [
            'farmWidget' => self::loadController('Farms')->getFarmWidget(array(), 'addAll'),
            'timezones' => Scalr_Util_DateTime::getTimezones(true),
            'scripts' => Script::getList($this->user->getAccountId(), $this->getEnvironmentId()),
            'events' => $events,
            'defaultTimezone' => $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE)
        ]);
    }

    public function xGetAction()
    {
        $this->request->defineParams(array(
            'schedulerTaskId' => array('type' => 'int')
        ));

        $events = array_map(
            function($item) {
                if ($item->envId) {
                    $scope = 'environment';
                } else if ($item->accountId) {
                    $scope = 'account';
                } else {
                    $scope = 'scalr';
                }
                return [
                    'name' => $item->name,
                    'description' => $item->description,
                    'scope' => $scope
                ];
            },
            EventDefinition::find([
                ['$or' => [['accountId' => null], ['accountId' => $this->user->getAccountId()]]],
                ['$or' => [['envId' => null], ['envId' => $this->getEnvironmentId()]]]
            ], null, ['name' => true])->getArrayCopy()
        );

        //$DBFarmRole->FarmID;
        $task = Scalr_SchedulerTask::init();
        $task->loadById($this->getParam(self::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($task);

        $taskValues = array(
            'targetId' => $task->targetId,
            'targetType' => $task->targetType,
            'id' => $task->id,
            'name' => $task->name,
            'type' => $task->type,
            'comments' => $task->comments,
            'config' => $task->config,
            'startTime' => $task->startTime ? Scalr_Util_DateTime::convertTimeZone(new DateTime($task->startTime), $task->timezone)->format('H:i') : '',
            'startTimeDate' => $task->startTime ? Scalr_Util_DateTime::convertTimeZone(new DateTime($task->startTime), $task->timezone)->format('Y-m-d') : '',
            'restartEvery' => $task->restartEvery,
            'timezone' => $task->timezone
        );
        $taskValues['config']['scriptId'] = (int) $taskValues['config']['scriptId'];
        $taskValues['config']['scriptIsSync'] = (int) $taskValues['config']['scriptIsSync'];

        $farmWidget = array();

        switch($task->targetType) {
            case Scalr_SchedulerTask::TARGET_FARM:
                $farmWidget = self::loadController('Farms')->getFarmWidget(array(
                    'farmId' => $task->targetId
                ), 'addAll');
            break;

            case Scalr_SchedulerTask::TARGET_ROLE:
                $farmWidget = self::loadController('Farms')->getFarmWidget(array(
                    'farmRoleId' => $task->targetId
                ), 'addAll');
                break;

            case Scalr_SchedulerTask::TARGET_INSTANCE:
                try {
                    $DBServer = DBServer::LoadByFarmRoleIDAndIndex($task->targetId, $task->targetServerIndex);
                    $farmWidget = self::loadController('Farms')->getFarmWidget(array(
                        'serverId' => $DBServer->serverId
                    ), 'addAll');
                } catch (Exception $e) {
                    $farmWidget = self::loadController('Farms')->getFarmWidget(array(
                        'farmRoleId' => $task->targetId
                    ), 'addAll');
                }
                break;

            default: break;
        }

        if ($task->type == Scalr_SchedulerTask::LAUNCH_FARM || $task->type == Scalr_SchedulerTask::TERMINATE_FARM)
            $farmWidget['options'][] = 'disabledFarmRole';

        $this->response->data([
            'farmWidget' => $farmWidget,
            'task' => $taskValues,
            'scripts' => Script::getList($this->user->getAccountId(), $this->getEnvironmentId())
        ]);
    }

    public function xListAction()
    {
        $sql = "SELECT `id`, `name`, `type`, `comments`, `target_id` as `targetId`, `target_server_index` as `targetServerIndex`, `target_type` as `targetType`, `start_time` as `startTime`,
            `end_time` as `endTime`, `last_start_time` as `lastStartTime`, `restart_every` as `restartEvery`, `config`, `status`, `timezone` FROM `scheduler` WHERE `env_id` = ? AND :FILTER:";

        $response = $this->buildResponseFromSql2($sql, ['id', 'name', 'type', 'startTime', 'lastStartTime', 'timezone', 'status'], ['name'], [$this->getEnvironmentId()]);

        foreach ($response['data'] as &$row) {
            switch($row['targetType']) {
                case Scalr_SchedulerTask::TARGET_FARM:
                    try {
                        $DBFarm = DBFarm::LoadByID($row['targetId']);
                        $row['targetName'] = $DBFarm->Name;
                    } catch ( Exception  $e) {}
                    break;

                case Scalr_SchedulerTask::TARGET_ROLE:
                    try {
                        $DBFarmRole = DBFarmRole::LoadByID($row['targetId']);
                        $row['targetName'] = $DBFarmRole->GetRoleObject()->name;
                        $row['targetFarmId'] = $DBFarmRole->FarmID;
                        $row['targetFarmName'] = $DBFarmRole->GetFarmObject()->Name;
                    } catch (Exception $e) {}
                    break;

                case Scalr_SchedulerTask::TARGET_INSTANCE:
                    try {
                        $DBServer = DBServer::LoadByFarmRoleIDAndIndex($row['targetId'], $row['targetServerIndex']);
                        $row['targetName'] = "({$DBServer->remoteIp})";
                        $DBFarmRole = $DBServer->GetFarmRoleObject();
                        $row['targetFarmId'] = $DBServer->farmId;
                        $row['targetFarmName'] = $DBFarmRole->GetFarmObject()->Name;
                        $row['targetRoleId'] = $DBServer->farmRoleId;
                        $row['targetRoleName'] = $DBFarmRole->GetRoleObject()->name;
                    } catch(Exception $e) {}
                    break;

                default: break;
            }

            //$row['type'] = Scalr_SchedulerTask::getTypeByName($row['type']);
            $row['startTime'] = $row['startTime'] ? Scalr_Util_DateTime::convertDateTime($row['startTime'], $row['timezone']) : 'Now';
            $row['endTime'] = $row['endTime'] ? Scalr_Util_DateTime::convertDateTime($row['endTime'], $row['timezone']) : 'Never';
            $row['lastStartTime'] = $row['lastStartTime'] ? Scalr_Util_DateTime::convertDateTime($row['lastStartTime'], $row['timezone']) : '';

            $row['config'] = unserialize($row['config']);
            $script = Script::findPk($row['config']['scriptId']);

            if (!empty($script)) {
                $row['config']['scriptName'] = $script->name;
            }
        }

        $this->response->data($response);
    }

    public function xSaveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_SCHEDULERTASKS, Acl::PERM_GENERAL_SCHEDULERTASKS_MANAGE);

        $this->request->defineParams(array(
            'id' => array('type' => 'integer'),
            'name' => array('type' => 'string', 'validator' => array(
                Scalr_Validator::REQUIRED => true,
                Scalr_Validator::NOHTML => true
            )),
            'type' => array('type' => 'string', 'validator' => array(
                Scalr_Validator::RANGE => array(
                    Scalr_SchedulerTask::SCRIPT_EXEC,
                    Scalr_SchedulerTask::LAUNCH_FARM,
                    Scalr_SchedulerTask::TERMINATE_FARM,
                    Scalr_SchedulerTask::FIRE_EVENT
                ),
                Scalr_Validator::REQUIRED => true
            )),
            'startTime', 'startTimeDate', 'restartEvery',
            'timezone' => array('type' => 'string', 'validator' => array(
                Scalr_Validator::REQUIRED => true
            )),
            'farmId' => array('type' => 'integer'),
            'farmRoleId' => array('type' => 'integer'),
            'serverId' => array('type' => 'string'),
            'scriptOptions' => array('type' => 'array'),
            'eventParams' => array('type' => 'array'),
            'eventName' => array('type' => 'string')
        ));

        $task = Scalr_SchedulerTask::init();
        if ($this->getParam('id')) {
            $task->loadById($this->getParam('id'));
            $this->user->getPermissions()->validate($task);
        } else {
            $task->accountId = $this->user->getAccountId();
            $task->envId = $this->getEnvironmentId();
            $task->status = Scalr_SchedulerTask::STATUS_ACTIVE;
        }

        $this->request->validate();
        $params = array();

        $timezone = new DateTimeZone($this->getParam('timezone'));
        $startTm = $this->getParam('startTime') ? new DateTime($this->getParam('startTimeDate') . " " . $this->getParam('startTime'), $timezone) : NULL;

        if ($startTm)
            Scalr_Util_DateTime::convertTimeZone($startTm, NULL);

        $curTm = new DateTime();
        if ($startTm && $startTm < $curTm && !$task->id)
            $this->request->addValidationErrors('startTimeDate', array('Start time must be greater then current time'));

        switch ($this->getParam('type')) {
            case Scalr_SchedulerTask::FIRE_EVENT:
            case Scalr_SchedulerTask::SCRIPT_EXEC:
                if($this->getParam('serverId')) {
                    $dbServer = DBServer::LoadByID($this->getParam('serverId'));
                    $this->user->getPermissions()->validate($dbServer);

                    $task->targetId = $dbServer->GetFarmRoleObject()->ID;
                    $task->targetServerIndex = $dbServer->index;
                    $task->targetType = Scalr_SchedulerTask::TARGET_INSTANCE;
                }
                else {
                    if($this->getParam('farmRoleId')) {
                        $dbFarmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
                        $this->user->getPermissions()->validate($dbFarmRole);
                        $task->targetId = $dbFarmRole->ID;
                        $task->targetType = Scalr_SchedulerTask::TARGET_ROLE;
                    }
                    else {
                        if ($this->getParam('farmId')) {
                            $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
                            $this->user->getPermissions()->validate($dbFarm);
                            $task->targetId = $dbFarm->ID;
                            $task->targetType = Scalr_SchedulerTask::TARGET_FARM;
                        }
                        else {
                            $this->request->addValidationErrors('farmId', array('Farm ID is required'));
                        }
                    }
                }

                if ($this->getParam('type') == Scalr_SchedulerTask::SCRIPT_EXEC) {
                    /* @var $script Script */
                    $script = Script::findPk($this->getParam('scriptId'));
                    try {
                        if ($script) {
                            $script->checkPermission($this->user, $this->getEnvironmentId());

                            $task->scriptId = $this->getParam('scriptId');
                            $params['scriptId'] = $this->getParam('scriptId');
                            $params['scriptIsSync'] = $this->getParam('scriptIsSync');
                            $params['scriptTimeout'] = $this->getParam('scriptTimeout');
                            $params['scriptVersion'] = $this->getParam('scriptVersion');
                            $params['scriptOptions'] = $this->getParam('scriptOptions');
                        } else {
                            throw new Exception();
                        }
                    } catch (Exception $e) {
                        $this->request->addValidationErrors('scriptId', array('Script ID is required'));
                    }
                } elseif ($this->getParam('type') == Scalr_SchedulerTask::FIRE_EVENT) {
                    if (!EventDefinition::findOne([
                        ['name' => $this->getParam('eventName')],
                        ['$or'  => [['accountId' => null], ['accountId' => $this->user->getAccountId()]]],
                        ['$or'  => [['envId' => null], ['envId' => $this->getEnvironmentId()]]]
                    ])) {
                        throw new Exception("Event definition not found");
                    }

                    $params['eventName'] = $this->getParam('eventName');
                    $params['eventParams'] = $this->getParam('eventParams');
                }
                break;

            case Scalr_SchedulerTask::LAUNCH_FARM:
                if ($this->getParam('farmId')) {
                    $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
                    $this->user->getPermissions()->validate($dbFarm);
                    $task->targetId = $dbFarm->ID;
                    $task->targetType = Scalr_SchedulerTask::TARGET_FARM;
                } else {
                    $this->request->addValidationErrors('farmId', array('Farm ID is required'));
                }
                break;

            case Scalr_SchedulerTask::TERMINATE_FARM:
                if ($this->getParam('farmId')) {
                    $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
                    $this->user->getPermissions()->validate($dbFarm);
                    $task->targetId = $dbFarm->ID;
                    $task->targetType = Scalr_SchedulerTask::TARGET_FARM;
                } else {
                    $this->request->addValidationErrors('farmId', array('Farm ID is required'));
                }
                $params['deleteDNSZones'] = $this->getParam('deleteDNSZones');
                $params['deleteCloudObjects'] = $this->getParam('deleteCloudObjects');
                break;
        }

        if (! $this->request->isValid()) {
            $this->response->failure();
            $this->response->data($this->request->getValidationErrors());
            return;
        }

        $task->name = $this->getParam('name');
        $task->type = $this->getParam('type');
        $task->comments = $this->getParam('comments');
        $task->timezone = $this->getParam('timezone');
        $task->startTime = $startTm ? $startTm->format('Y-m-d H:i:s') : NULL;
        //$task->endTime = $endTm ? $endTm->format('Y-m-d H:i:s') : NULL;
        $task->restartEvery = $this->getParam('restartEvery');
        $task->config = $params;

        $task->save();
        $this->response->success();
    }

    /**
     * @param JsonData $tasksIds
     */
    public function xActivateAction(JsonData $tasksIds)
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_SCHEDULERTASKS, Acl::PERM_GENERAL_SCHEDULERTASKS_MANAGE);

        $processed = [];

        foreach ($tasksIds as $taskId) {
            $task = Scalr_SchedulerTask::init()->loadById($taskId);
            $this->user->getPermissions()->validate($task);

            if ($task->status === Scalr_SchedulerTask::STATUS_SUSPENDED) {
                $task->status = Scalr_SchedulerTask::STATUS_ACTIVE;
                $task->save();

                $processed[] = $taskId;
            }
        }

        $this->response->data(['processed' => $processed]);
        $this->response->success(sprintf("%d of %d selected task(s) successfully activated.", count($processed), count($tasksIds)));
    }

    /**
     * @param JsonData $tasksIds
     */
    public function xSuspendAction(JsonData $tasksIds)
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_SCHEDULERTASKS, Acl::PERM_GENERAL_SCHEDULERTASKS_MANAGE);

        $processed = [];

        foreach ($tasksIds as $taskId) {
            $task = Scalr_SchedulerTask::init()->loadById($taskId);
            $this->user->getPermissions()->validate($task);

            if ($task->status === Scalr_SchedulerTask::STATUS_ACTIVE) {
                $task->status = Scalr_SchedulerTask::STATUS_SUSPENDED;
                $task->save();

                $processed[] = $taskId;
            }
        }

        $this->response->data(['processed' => $processed]);
        $this->response->success(sprintf("%d of %d selected task(s) successfully suspended.", count($processed), count($tasksIds)));
    }

    /**
     * @param JsonData $tasksIds
     */
    public function xExecuteAction(JsonData $tasksIds)
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_SCHEDULERTASKS, Acl::PERM_GENERAL_SCHEDULERTASKS_MANAGE);

        $executed = [];
        $processed = [];

        foreach ($tasksIds as $taskId) {
            $task = new Scalr_SchedulerTask();
            $task->loadById($taskId);
            $this->user->getPermissions()->validate($task);

            if ($task->status == Scalr_SchedulerTask::STATUS_FINISHED) {
                continue;
            }

            if ($task->execute(true)) {
                $executed[] = $task->name;
                $processed[] = $taskId;
            }
        }

        if (count($executed)) {
            $this->response->data(['processed' => $processed]);
            $this->response->success("Task(s): " . implode($executed, ', ') . " successfully executed.");
        } else {
            $this->response->warning('Target of task(s) could not be found.');
        }
    }

    public function xDeleteAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_SCHEDULERTASKS, Acl::PERM_GENERAL_SCHEDULERTASKS_MANAGE);

        $this->request->defineParams(array(
            'tasks' => array('type' => 'json')
        ));
        $processed = [];

        foreach ($this->getParam('tasks') as $taskId) {
            $task = Scalr_SchedulerTask::init()->loadById($taskId);
            $this->user->getPermissions()->validate($task);
            $task->delete();
            $processed[] = $task->id;
        }

        $this->response->success("Selected task(s) successfully removed");
        $this->response->data(['processed' => $processed]);
    }
}
