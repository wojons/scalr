<?php
use Scalr\Acl\Acl;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\EntityIterator;
use Scalr\Model\Entity;
use Scalr\Model\Entity\Event;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\OrchestrationLog;
use Scalr\Model\Entity\OrchestrationLogManualScript;
use Scalr\Model\Entity\SchedulerTask;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\Server;
use Scalr\Model\Entity\WebhookHistory;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Logs extends Scalr_UI_Controller
{
    public function systemAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SYSTEM_LOGS);

        $farms = self::loadController('Farms')->getList();
        array_unshift($farms, ['id' => 0, 'name' => 'All farms']);

        $this->response->page('ui/logs/system.js', [
            'farms' => $farms,
            'params' => [
                'severity[1]' => 0,
                'severity[2]' => 1,
                'severity[3]' => 1,
                'severity[4]' => 1,
                'severity[5]' => 1
            ]
        ]);
    }

    public function getOrchestrationLogAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_ORCHESTRATION_LOGS);

        $this->request->defineParams([
            'executionId' => ['type' => 'string']
        ]);

        $info = $this->db->GetRow("SELECT * FROM orchestration_log WHERE execution_id = ? LIMIT 1", [$this->getParam('executionId')]);
        if (!$info)
            throw new Exception('Script execution log not found');

        try {
            $dbServer = DBServer::LoadByID($info['server_id']);
            if (!in_array($dbServer->status, [SERVER_STATUS::INIT, SERVER_STATUS::RUNNING]))
                throw new Exception();

        } catch (Exception $e) {
            throw new Exception('This server has been terminated and its logs are no longer available');
        }

        //Note! We should not check not-owned-farms permission here. It's approved by Igor.
        if ($dbServer->envId != $this->environment->id) {
            throw new \Scalr_Exception_InsufficientPermissions();
        }

        $logs = $dbServer->scalarizr->system->getScriptLogs($this->getParam('executionId'));
        $msg = sprintf("STDERR:\n%s \n\n STDOUT:\n%s", base64_decode($logs->stderr), base64_decode($logs->stdout));
        $msg = nl2br(htmlspecialchars($msg));

        $this->response->data(['message' => $msg]);
    }

    public function orchestrationAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_ORCHESTRATION_LOGS);

        $farms = self::loadController('Farms')->getList();
        array_unshift($farms, ['id' => '0', 'name' => 'All farms']);
        //todo: use Script::getScriptingData
        $scripts = array_map(function ($s) {
            return ['id' => $s['id'], 'name' => $s['name']];
        }, Script::getList($this->user->getAccountId(), $this->getEnvironmentId()));
        array_unshift($scripts, ['id' => 0, 'name' => '']);

        $glEvents = array_keys(EVENT_TYPE::getScriptingEvents());
        sort($glEvents);
        array_unshift($glEvents, '');
        $events = array_merge($glEvents,
            array_keys(\Scalr\Model\Entity\EventDefinition::getList($this->user->getAccountId(), $this->getEnvironmentId()))
        );

        $tasks = $this->db->GetAll('SELECT id, name FROM scheduler WHERE env_id = ? ORDER BY name ASC', [$this->getEnvironmentId()]);
        array_unshift($tasks, ['id' => 0, 'name' => '']);

        $this->response->page('ui/logs/orchestration.js', [
            'farms' => $farms,
            'scripts' => $scripts,
            'events' => $events,
            'tasks' => $tasks
        ]);
    }

    public function apiAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_API_LOGS);
        $this->response->page('ui/logs/api.js');
    }

    /**
     * @param string $serverId optional
     * @param int $farmId optional
     * @param string $severity optional
     * @param string $byDate optional
     * @param string $fromTime optional
     * @param string $toTime optional
     * @param string $action optional
     * @param string $query optional
     *
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xListLogsAction($serverId = null, $farmId = null, $severity = null, $byDate = null, $fromTime = null, $toTime = null, $action = null, $query = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SYSTEM_LOGS);

        $sql = "SELECT logentries.* FROM logentries JOIN farms f ON f.id = logentries.farmid WHERE f.env_id = ? AND " . $this->request->getFarmSqlQuery();
        $args = [$this->getEnvironmentId()];

        $query = trim($query);
        if ($query) {
            $sql .= " AND (`message` LIKE ? OR `serverid` LIKE ? OR `source` LIKE ? )";
            $args = array_merge($args, ["%$query%", "$query%", "%$query%"]);
        }

        if ($serverId) {
            $sql .= " AND serverid = ?";
            $args[] = $serverId;
        }

        if ($farmId) {
            $sql .= " AND farmid = ?";
            $args[] = $farmId;
        }

        if ($severity) {
            $severities = [];
            foreach (explode(',', $severity) as $sevId) {
                $sevId = intval($sevId);
                if ($sevId > 0 && $sevId < 6) {
                    $severities[] = $sevId;
                }
            }

            if (count($severities)) {
                $severities = implode(",", $severities);
                $sql .= " AND severity IN ($severities)";
            } else {
                $sql .= " AND 0"; // is it right ?
            }
        }

        if ($byDate) {
            try {
                $tz = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);
                if (! $tz) {
                    $tz = 'UTC';
                }

                $tz = new DateTimeZone($tz);
                $dtS = new DateTime($byDate, $tz);
                $dtE = new DateTime($byDate, $tz);

                if ($fromTime) {
                    $dtS = DateTime::createFromFormat('Y-m-d H:i', $byDate . ' ' . $fromTime, $tz);
                }

                if ($toTime) {
                    $dtE = DateTime::createFromFormat('Y-m-d H:i', $byDate . ' ' . $toTime, $tz);
                } else {
                    $dtE = $dtE->add(new DateInterval('P1D'));
                }

                $sql .= " AND time > ? AND time < ?";
                $args[] = $dtS->getTimestamp();
                $args[] = $dtE->getTimestamp();
            } catch (Exception $e) {
            }
        }

        $severities = [1 => "Debug", 2 => "Info", 3 => "Warning", 4 => "Error", 5 => "Fatal"];
        if ($action && 'download' == $action) {
            $fileContent = [];
            $farmNames = [];
            $fileContent[] = "Type;Time;Farm;Caller;Message;Count;\r\n";

            $response = $this->buildResponseFromSql2($sql, ['time'], [], $args, true);

            foreach ($response["data"] as &$data) {
                $data["time"] = Scalr_Util_DateTime::convertTz((int)$data["time"]);
                $data["s_severity"] = $severities[$data["severity"]];

                if (!$farmNames[$data['farmid']]) {
                    $farmNames[$data['farmid']] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", [$data['farmid']]);
                }

                $data['farm_name'] = $farmNames[$data['farmid']];

                $data['message'] = str_replace("<br />", "", $data['message']);
                $data['message'] = str_replace("\n", "", $data['message']);

                $fileContent[] = "{$data['s_severity']};{$data['time']};{$data['farm_name']};{$data['source']};{$data['message']};{$data['cnt']};";
            }

            $this->response->setHeader('Content-Encoding', 'utf-8');
            $this->response->setHeader('Content-Type', 'text/csv', true);
            $this->response->setHeader('Expires', 'Mon, 10 Jan 1997 08:00:00 GMT');
            $this->response->setHeader('Pragma', 'no-cache');
            $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            $this->response->setHeader('Cache-Control', 'post-check=0, pre-check=0');
            $this->response->setHeader('Content-Disposition', 'attachment; filename=' . "EventLog_" . Scalr_Util_DateTime::convertTz(time(), 'M_j_Y_H:i:s') . ".csv");
            $this->response->setResponse(implode("\n", $fileContent));
        } else {
            $farmNames = [];
            $response = $this->buildResponseFromSql2($sql, ['time'], [], $args);
            foreach ($response["data"] as &$row) {
                $row['id'] = bin2hex($row['id']);
                $row["time"] = Scalr_Util_DateTime::convertTz((int)$row["time"]);

                $row["servername"] = $row["serverid"];
                $row["s_severity"] = $severities[$row["severity"]];
                $row["severity"] = (int)$row["severity"];

                if (!isset($farmNames[$row['farmid']])) {
                    $farmNames[$row['farmid']] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", [$row['farmid']]);
                }

                $row['farm_name'] = $farmNames[$row['farmid']];
                $row['message'] = nl2br(htmlspecialchars($row['message']));
            }

            $this->response->data($response);
        }
    }

    /**
     * @param   int         $farmId
     * @param   string      $serverId
     * @param   string      $eventId
     * @param   int         $scriptId
     * @param   string      $eventServerId
     * @param   int         $schedulerId
     * @param   string      $byDate
     * @param   string      $fromTime
     * @param   string      $toTime
     * @param   string      $status
     * @param   string      $event
     * @param   JsonData    $sort
     * @param   int         $start
     * @param   int         $limit
     * @param   string      $query
     * @throws  Scalr_Exception_Core
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xListOrchestrationLogsAction($farmId = 0, $serverId = '', $eventId = '', $scriptId = 0, $eventServerId = '',
                                             $schedulerId = 0, $byDate = '', $fromTime = '', $toTime = '', $status = '', $event = '',
                                             JsonData $sort, $start = 0, $limit = 20, $query = '')
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_ORCHESTRATION_LOGS);

        $o = new Entity\OrchestrationLog();
        $f = new Entity\Farm();
        $criteria = [
            Entity\Farm::STMT_FROM => "{$o->table()} JOIN {$f->table('f')} ON {$f->columnId('f')} = {$o->columnFarmId}",
            Entity\Farm::STMT_WHERE => $this->request->getFarmSqlQuery() . " AND {$f->columnEnvId('f')} = " . $this->db->qstr($this->getEnvironmentId())
        ];

        if ($farmId) {
            $criteria[] = ['farmId' => $farmId];
        }

        if ($serverId) {
            $criteria[] = ['serverId' => $serverId];
        }

        if ($eventId) {
            $criteria[] = ['eventId' => $eventId];
        }

        if ($eventServerId) {
            $criteria[] = ['eventServerId' => $eventServerId];
        }

        if ($scriptId) {
            /* @var $script Script */
            $script = Script::findPk($scriptId);

            if ($script && $this->request->hasPermissions($script)) {
                $scriptName = preg_replace("/[^A-Za-z0-9]+/", "_", $script->name);
                $criteria[] = ['scriptName' => $scriptName];
            }
        }

        if ($query || $event) {
            $logEntity = new OrchestrationLog();
            $eventEntity = new Event();

            $criteria[AbstractEntity::STMT_FROM] = $criteria[AbstractEntity::STMT_FROM] . "
                LEFT JOIN {$eventEntity->table('e')}
                ON {$logEntity->columnEventId} = {$eventEntity->columnEventId('e')}
            ";

            if ($event && $query) {
                $query = $this->db->qstr('%' . $query . '%');

                $criteria[AbstractEntity::STMT_WHERE] = $criteria[AbstractEntity::STMT_WHERE] . " AND (
                    {$eventEntity->columnType('e')} = {$this->db->qstr($event)}
                    OR ({$logEntity->columnType} LIKE {$query}
                    AND {$logEntity->columnScriptName} LIKE {$query})
                )";
            } else if ($event) {
                $criteria[AbstractEntity::STMT_WHERE] = $criteria[AbstractEntity::STMT_WHERE] . " AND (
                    {$eventEntity->columnType('e')} = {$this->db->qstr($event)}
                )";
            } else {
                $query = $this->db->qstr('%' . $query . '%');

                $criteria[AbstractEntity::STMT_WHERE] = $criteria[AbstractEntity::STMT_WHERE] . " AND (
                    ({$eventEntity->columnType('e')} LIKE {$query}
                    OR {$logEntity->columnType} LIKE {$query}
                    OR {$logEntity->columnScriptName} LIKE {$query})
                )";
            }
        }

        if ($schedulerId) {
            $criteria[] = ['taskId' => $schedulerId];
        }

        if ($byDate) {
            try {
                $tz = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE) ?: 'UTC';

                $tz = new DateTimeZone($tz);
                $dtS = new DateTime($byDate, $tz);
                $dtE = new DateTime($byDate, $tz);

                if ($fromTime) {
                    $dtS = DateTime::createFromFormat('Y-m-d H:i', "{$byDate} {$fromTime}", $tz);
                }

                if ($toTime) {
                    $dtE = DateTime::createFromFormat('Y-m-d H:i', "{$byDate} {$toTime}", $tz);
                } else {
                    $dtE = $dtE->add(new DateInterval('P1D'));
                }

                if ($dtS && $dtE) {
                    Scalr_Util_DateTime::convertTimeZone($dtS);
                    Scalr_Util_DateTime::convertTimeZone($dtE);

                    $criteria[] = ['added' => ['$gt' => $dtS]];
                    $criteria[] = ['added' => ['$lt' => $dtE]];
                }
            } catch (Exception $e) {}
        }

        if ($status === 'success') {
            $criteria[] = ['execExitCode' => 0];
        } else if ($status === 'failure') {
            $criteria[] = ['execExitCode' => ['$ne' => 0]];
        }

        $logs = OrchestrationLog::find(
            $criteria,
            null,
            Scalr\UI\Utils::convertOrder($sort, ['id' => false], ['id', 'added']),
            $limit,
            $start,
            true
        );

        $data = $this->prepareOrchestrationLogData($logs);
        $this->response->data(['data' => $data, 'total' => $logs->totalNumber]);
    }

    /**
     * Returns prepared orchestration log data for response
     *
     * @param EntityIterator $logs  List of Orchestration Log objects
     * @return array
     */
    private function prepareOrchestrationLogData($logs)
    {
        $farmIds = [];
        $serverIds = [];
        $taskIds = [];
        $eventIds = [];
        $ids = [];

        foreach ($logs as $row) {
            /* @var $row OrchestrationLog */
            $farmIds[] = $row->farmId;
            $serverIds[] = $row->serverId;

            if ($row->eventServerId) {
                $serverIds[] = $row->eventServerId;
            }

            if ($row->taskId) {
                $taskIds[] = $row->taskId;
            }

            if ($row->eventId) {
                $eventIds[] = $row->eventId;
            }

            if ($row->type == OrchestrationLog::TYPE_MANUAL) {
                $ids[] = $row->id;
            }
        }

        if (!empty($farmIds)) {
            $farms = Farm::find([['id' => ['$in' => array_unique($farmIds)]]]);

            foreach ($farms as $farm) {
                /* @var $farm Farm */
                $farmData[$farm->id] = $farm->name;
            }
        }

        if (!empty($serverIds)) {
            $servers = Server::find([['serverId' => ['$in' => array_unique($serverIds)]]]);

            $farmRoleIds = [];
            $serverFarmIds = [];

            foreach ($servers as $server) {
                /* @var $server Server */
                $serverData[$server->serverId]['serverIndex'] = $server->index;
                $farmRoleIds[$server->serverId] = $server->farmRoleId;
                $serverFarmIds[$server->serverId] = $server->farmId;
            }

            $farms = Farm::find([['id' => ['$in' => array_unique(array_values($serverFarmIds))]]]);

            foreach ($farms as $farm) {
                /* @var $farm Farm */
                foreach ($serverFarmIds as $serverId => $farmId) {
                    if ($farmId == $farm->id) {
                        $serverData[$serverId]['farmName'] = $farm->name;
                        $serverData[$serverId]['farmId'] = $farm->id;
                    }
                }
            }

            $farmRoles = FarmRole::find([['id' => ['$in' => array_unique(array_values($farmRoleIds))]]]);

            foreach ($farmRoles as $farmRole) {
                /* @var $farmRole FarmRole */
                foreach ($farmRoleIds as $serverId => $farmRoleId) {
                    if ($farmRoleId == $farmRole->id) {
                        $serverData[$serverId]['alias'] = $farmRole->alias;
                        $serverData[$serverId]['farmRoleId'] = $farmRole->id;
                    }
                }
            }
        }

        if (!empty($taskIds)) {
            $tasks = SchedulerTask::find([['id' => ['$in' => array_unique($taskIds)]]]);

            foreach ($tasks as $task) {
                /* @var $task SchedulerTask */
                $taskData[$task->id] = $task->name;
            }
        }

        if (!empty($eventIds)) {
            $events = Event::find([['eventId' => ['$in' => array_unique($eventIds)]]]);

            foreach ($events as $event) {
                /* @var $event Event */
                $eventData[$event->eventId] = $event->type;
            }
        }

        if (!empty($ids)) {
            $manualLogs = OrchestrationLogManualScript::find([['orchestrationLogId' => ['$in' => array_unique($ids)]]]);

            foreach ($manualLogs as $manualLog) {
                /* @var $manualLog OrchestrationLogManualScript */
                $scriptData[$manualLog->orchestrationLogId] = $manualLog->userEmail;
            }
        }

        $data = [];

        foreach ($logs as $row) {
            /* @var $row OrchestrationLog */
            $dataRow = get_object_vars($row);
            $dataRow['targetFarmName'] = isset($farmData[$row->farmId]) ? $farmData[$row->farmId] : null;
            $dataRow['targetFarmId'] = $row->farmId;
            $dataRow['targetServerId'] = $row->serverId;
            $dataRow['targetServerIndex'] = isset($serverData[$row->serverId]['serverIndex']) ? $serverData[$row->serverId]['serverIndex'] : null;
            $dataRow['targetFarmRoleId'] = isset($serverData[$row->serverId]['farmRoleId']) ? $serverData[$row->serverId]['farmRoleId'] : null;
            $dataRow['targetRoleName'] = isset($serverData[$row->serverId]['alias']) ? $serverData[$row->serverId]['alias'] : null;
            $dataRow['added'] = Scalr_Util_DateTime::convertTz($row->added);

            if (\Scalr::config('scalr.system.scripting.logs_storage') == 'scalr') {
                $dataRow['executionId'] = null;
            }

            if ($dataRow['message']) {
                $dataRow['message'] = nl2br(htmlspecialchars($dataRow['message']));
            }

            if ($row->eventServerId) {
                $dataRow['eventFarmName'] = isset($serverData[$row->eventServerId]['farmName']) ? $serverData[$row->eventServerId]['farmName'] : null;
                $dataRow['eventFarmId'] = isset($serverData[$row->eventServerId]['farmId']) ? $serverData[$row->eventServerId]['farmId'] : null;
                $dataRow['eventFarmRoleId'] = isset($serverData[$row->eventServerId]['farmRoleId']) ? $serverData[$row->eventServerId]['farmRoleId'] : null;
                $dataRow['eventRoleName'] = isset($serverData[$row->eventServerId]['alias']) ? $serverData[$row->eventServerId]['alias'] : null;
                $dataRow['eventServerIndex'] = isset($serverData[$row->eventServerId]['serverIndex']) ? $serverData[$row->eventServerId]['serverIndex'] : null;
            }

            $dataRow['event'] = null;

            if ($row->taskId) {
                $dataRow['event'] = isset($taskData[$row->taskId]) ? $taskData[$row->taskId] : null;
            }

            if ($row->eventId) {
                $dataRow['event'] = isset($eventData[$row->eventId]) ? $eventData[$row->eventId] : null;
            }

            if ($row->type == OrchestrationLog::TYPE_MANUAL) {
                $dataRow['event'] = isset($scriptData[$row->id]) ? $scriptData[$row->id] : null;
            }

            $data[] = $dataRow;
        }

        return $data;
    }

    public function xListApiLogsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_API_LOGS);

        $this->request->defineParams([
            'sort' => ['type' => 'json', 'default' => ['property' => 'id', 'direction' => 'DESC']]
        ]);

        $sql = "SELECT * from api_log WHERE env_id = ?";
        $args = [$this->getEnvironmentId()];

        if ($this->getParam('byDate')) {
            try {
                $tz = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);
                if (! $tz)
                    $tz = 'UTC';

                $tz = new DateTimeZone($tz);
                $dtS = new DateTime($this->getParam('byDate'), $tz);
                $dtE = new DateTime($this->getParam('byDate'), $tz);

                if ($this->getParam('fromTime'))
                    $dtS = $dtS->createFromFormat('Y-m-d H:i', $this->getParam('byDate') . ' ' . $this->getParam('fromTime'), $tz);

                if ($this->getParam('toTime'))
                    $dtE = $dtE->createFromFormat('Y-m-d H:i', $this->getParam('byDate') . ' ' . $this->getParam('toTime'), $tz);
                else
                    $dtE = $dtE->add(new DateInterval('P1D'));

                $sql .= ' AND dtadded > ? AND dtadded < ?';
                $args[] = $dtS->getTimestamp();
                $args[] = $dtE->getTimestamp();
            } catch (Exception $e) {}
        }

        $response = $this->buildResponseFromSql($sql, ['id', 'dtadded', 'action', 'ipaddress'], ['transaction_id'], $args);
        foreach ($response["data"] as &$row) {
            $row["dtadded"] = Scalr_Util_DateTime::convertTz((int)$row["dtadded"]);
        }

        $this->response->data($response);
    }

    public function apiLogEntryDetailsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_API_LOGS);

        $entry = $this->db->GetRow("SELECT * FROM api_log WHERE transaction_id = ? AND clientid = ? LIMIT 1", [$this->getParam('transactionId'), $this->user->getAccountId()]);
        if (empty($entry))
            throw new Exception ('Unknown transaction');

        $entry['dtadded'] = Scalr_Util_DateTime::convertTz((int)$entry['dtadded']);

        $form = [
            [
                'xtype' => 'fieldset',
                'title' => 'General information',
                'labelWidth' => 120,
                'items' => [
                    [
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Transaction ID',
                        'value' => $entry['transaction_id']
                    ],
                    [
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Action',
                        'value' => $entry['action']
                    ],
                    [
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'IP address',
                        'value' => $entry['ipaddress']
                    ],
                    [
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Time',
                        'value' => $entry['dtadded']
                    ]
                ]
            ],
            [
                'xtype' => 'fieldset',
                'title' => 'Request',
                'layout' => 'fit',
                'items' => [
                    [
                        'xtype' => 'textarea',
                        'grow' => true,
                        'growMax' => 200,
                        'readOnly' => true,
                        'hideLabel' => true,
                        'value' => $entry['request']
                    ]
                ]
            ],
            [
                'xtype' => 'fieldset',
                'title' => 'Response',
                'layout' => 'fit',
                'items' => [
                    [
                        'xtype' => 'textarea',
                        'grow' => true,
                        'growMax' => 200,
                        'readOnly' => true,
                        'hideLabel' => true,
                        'value' => $entry['response']
                    ]
                ]
            ]
        ];

        $this->response->page('ui/logs/apilogentrydetails.js', $form);
    }

    public function eventsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_EVENT_LOGS);

        $farms = self::loadController('Farms')->getList();
        array_unshift($farms, ['id' => 0, 'name' => 'All farms']);

        $this->response->page('ui/logs/events.js', [
            'farms' => $farms
        ]);
    }

    /**
     * @param   int        $farmId
     * @param   string     $eventServerId
     * @param   string     $eventId
     * @throws Scalr_Exception_Core
     */
    public function xListEventLogsAction($farmId = null, $eventServerId = null, $eventId = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_EVENT_LOGS);

        $sql = "SELECT `events`.* FROM `farms` f INNER JOIN `events` ON `f`.`id` = `events`.`farmid` WHERE `f`.`env_id` = ? AND :FILTER: AND " . $this->request->getFarmSqlQuery();
        $args = [$this->getEnvironmentId()];

        if ($farmId) {
            $sql .= " AND farmid = ?";
            $args[] = $farmId;
        }

        if ($eventServerId) {
            $sql .= " AND event_server_id = ?";
            $args[] = $eventServerId;
        }

        if ($eventId) {
            $sql .= " AND event_id = ?";
            $args[] = $eventId;
        }

        $response = $this->buildResponseFromSql2($sql, ['dtadded'], ["events.message", "events.type", "events.dtadded", "events.event_server_id", "events.event_id"], $args);

        $cache = [];

        foreach ($response['data'] as &$row) {
            $row['message'] = nl2br($row['message']);
            $row["dtadded"] = Scalr_Util_DateTime::convertTz($row["dtadded"]);

            if ($row['is_suspend'] == 1)
                $row['type'] = "{$row['type']} (Suspend)";

            if ($row['event_server_id']) {

                try {
                    $es = DBServer::LoadByID($row['event_server_id']);

                    if (!$cache['farm_names'][$es->farmId])
                        $cache['farm_names'][$es->farmId] = $this->db->GetOne("SELECT name FROM farms WHERE id=?", [$es->farmId]);
                    $row['event_farm_name'] = $cache['farm_names'][$es->farmId];
                    $row['event_farm_id'] = $es->farmId;

                    $row['event_farm_roleid'] = $es->farmRoleId;

                    if (!$cache['role_names'][$es->GetFarmRoleObject()->RoleID])
                        $cache['role_names'][$es->GetFarmRoleObject()->RoleID] = $es->GetFarmRoleObject()->Alias;
                    $row['event_role_name'] = $cache['role_names'][$es->GetFarmRoleObject()->RoleID];

                    $row['event_server_index'] = $es->index;
                } catch (Exception $e) {}

            }

            $row['scripts'] = [
                'total' => $row['scripts_total'],
                'complete' => $row['scripts_completed'],
                'failed' => $row['scripts_failed'],
                'timeout' => $row['scripts_timedout'],
                'pending' => $row['scripts_total'] - $row['scripts_completed'] - $row['scripts_failed'] - $row['scripts_timedout']
            ];
            $row['webhooks'] = [
                'total' => $row['wh_total'],
                'complete' => $row['wh_completed'],
                'failed' => $row['wh_failed'],
                'pending' => $row['wh_total'] - $row['wh_completed'] - $row['wh_failed']
            ];
        }

        $this->response->data($response);
    }


}
