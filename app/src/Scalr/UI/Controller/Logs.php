<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\WebhookHistory;

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

    public function getScriptingLogAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SCRIPTING_LOGS);

        $this->request->defineParams([
            'executionId' => ['type' => 'string']
        ]);

        $info = $this->db->GetRow("SELECT * FROM scripting_log WHERE execution_id = ? LIMIT 1", [$this->getParam('executionId')]);
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

    public function scriptingAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SCRIPTING_LOGS);

        $farms = self::loadController('Farms')->getList();
        array_unshift($farms, ['id' => '0', 'name' => 'All farms']);
        //todo: use Script::getScriptingData
        $scripts = array_map(function($s) { return ['id' => $s['id'], 'name' => $s['name']]; }, Script::getList($this->user->getAccountId(), $this->getEnvironmentId()));
        array_unshift($scripts, ['id' => 0, 'name' => '']);

        $glEvents = array_keys(EVENT_TYPE::getScriptingEvents());
        sort($glEvents);
        array_unshift($glEvents, '');
        $events = array_merge($glEvents,
            array_keys(\Scalr\Model\Entity\EventDefinition::getList($this->user->getAccountId(), $this->getEnvironmentId()))
        );

        $tasks = $this->db->GetAll('SELECT id, name FROM scheduler WHERE env_id = ? ORDER BY name ASC', [$this->getEnvironmentId()]);
        array_unshift($tasks, ['id' => 0, 'name' => '']);

        $this->response->page('ui/logs/scripting.js', [
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

        $sql = "SELECT * FROM logentries WHERE ";
        $args = [];

        if ($query) {
            $query = trim($query);
            $sql .= " (`message` LIKE ? OR `serverid` LIKE ? OR `source` LIKE ? )";
            $args = [ "%$query%", "$query%", "%$query%"];
        } else {
            $sql .= ' true ';
        }

        if ($serverId) {
            $sql .= ' AND serverid = ?';
            $args[] = $serverId;
        }

        $farmSql = "SELECT id FROM farms WHERE env_id = ?";
        $farmArgs = [$this->getEnvironmentId()];
        list($farmSql, $farmArgs) = $this->request->prepareFarmSqlQuery($farmSql, $farmArgs);
        $farms = $this->db->GetCol($farmSql, $farmArgs);

        if ($farmId && in_array($farmId, $farms)) {
            $sql .= ' AND farmid = ?';
            $args[] = $farmId;
        } else {
            if (count($farms)) {
                $sql .= ' AND farmid IN (' . implode(',', $farms) . ')';
            } else {
                $sql .= ' AND 0';
            }
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

                $sql .= ' AND time > ? AND time < ?';
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
     * @param   int     $farmId
     * @param   string  $serverId
     * @param   string  $event
     * @param   string  $eventId
     * @param   string  $eventServerId
     * @param   int     $scriptId
     * @param   int     $schedulerId
     * @param   string  $byDate
     * @param   string  $fromTime
     * @param   string  $toTime
     * @param   string  $status
     * @throws  Scalr_Exception_Core
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xListScriptingLogsAction($farmId = 0, $serverId = '', $event = '', $eventId = '', $eventServerId = '',
                                             $scriptId = 0, $schedulerId = 0, $byDate = '', $fromTime = '', $toTime = '', $status = '')
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SCRIPTING_LOGS);

        $sql = "SELECT * FROM scripting_log WHERE :FILTER:";
        $args = [];

        $farmSql = "SELECT id FROM farms WHERE env_id = ?";
        $farmArgs = [$this->getEnvironmentId()];
        list($farmSql, $farmArgs) = $this->request->prepareFarmSqlQuery($farmSql, $farmArgs);
        $farms = $this->db->GetCol($farmSql, $farmArgs);

        if ($farmId && in_array($farmId, $farms)) {
            $sql .= ' AND farmid = ?';
            $args[] = $farmId;
        } else {
            if (count($farms)) {
                $sql .= ' AND farmid IN (' . implode(',', $farms) . ')';
            } else {
                $sql .= ' AND 0';
            }
        }

        if ($serverId) {
            $sql .= ' AND server_id = ?';
            $args[] = $serverId;
        }

        if ($eventServerId) {
            $sql .= ' AND event_server_id = ?';
            $args[] = $eventServerId;
        }

        if ($eventId) {
            $sql .= ' AND event_id = ?';
            $args[] = $eventId;
        }

        if ($scriptId) {
            /* @var $script Script */
            $script = Script::findPk($scriptId);
            if ($script && (!$script->accountId || $script->accountId == $this->user->getAccountId())) {
                $scriptName = substr(preg_replace("/[^A-Za-z0-9]+/", "_", $script->name), 0, 50); // because of column's length
                $sql .= ' AND script_name = ?';
                $args[] = $scriptName;
            }
        }

        if ($schedulerId) {
            $sql .= ' AND event = ?';
            $args[] = 'Scheduler (TaskID: ' . $schedulerId . ')';
        } else if ($event) {
            $sql .= ' AND event = ?';
            $args[] = $event;
        }

        if ($byDate) {
            try {
                $tz = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);
                if (! $tz)
                    $tz = 'UTC';

                $tz = new DateTimeZone($tz);
                $dtS = new DateTime($byDate, $tz);
                $dtE = new DateTime($byDate, $tz);

                if ($fromTime)
                    $dtS = DateTime::createFromFormat('Y-m-d H:i', "{$byDate} {$fromTime}", $tz);

                if ($toTime)
                    $dtE = DateTime::createFromFormat('Y-m-d H:i', "{$byDate} {$toTime}", $tz);
                else
                    $dtE = $dtE->add(new DateInterval('P1D'));

                if ($dtS && $dtE) {
                    Scalr_Util_DateTime::convertTimeZone($dtS);
                    Scalr_Util_DateTime::convertTimeZone($dtE);

                    $sql .= ' AND dtadded > ? AND dtadded < ?';
                    $args[] = $dtS->format('Y-m-d H:i:s');
                    $args[] = $dtE->format('Y-m-d H:i:s');
                }
            } catch (Exception $e) {}
        }

        if ($status === 'success') {
            $sql .= ' AND exec_exitcode = ?';
            $args[] = 0;
        } else if ($status === 'failure') {
            $sql .= ' AND exec_exitcode <> ?';
            $args[] = 0;
        }

        $response = $this->buildResponseFromSql2($sql, ['id', 'dtadded'], ['event', 'script_name'], $args);
        $cache = [];
        foreach ($response["data"] as &$row) {
            //
            //target_data
            //
            if (!$cache['farm_names'][$row['farmid']])
                $cache['farm_names'][$row['farmid']] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", [$row['farmid']]);
            $row['target_farm_name'] = $cache['farm_names'][$row['farmid']];
            $row['target_farm_id'] = $row['farmid'];

            $sInfo = $this->db->GetRow("SELECT farm_roleid, `index` FROM servers WHERE server_id = ? LIMIT 1", [$row['server_id']]);
            $row['target_farm_roleid'] = $sInfo['farm_roleid'];

            if (!$cache['role_names'][$sInfo['farm_roleid']])
                $cache['role_names'][$sInfo['farm_roleid']] = $this->db->GetOne("SELECT alias FROM farm_roles WHERE id=?", [$sInfo['farm_roleid']]);
            $row['target_role_name'] = $cache['role_names'][$sInfo['farm_roleid']];

            $row['target_server_index'] = $sInfo['index'];
            $row['target_server_id'] = $row['server_id'];

            //
            //event_data
            //
            if ($row['event_server_id']) {
                $esInfo = $this->db->GetRow("SELECT farm_roleid, `index`, farm_id FROM servers WHERE server_id = ? LIMIT 1", [$row['event_server_id']]);

                if (!$cache['farm_names'][$esInfo['farm_id']])
                    $cache['farm_names'][$esInfo['farm_id']] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", [$esInfo['farm_id']]);
                $row['event_farm_name'] = $cache['farm_names'][$esInfo['farm_id']];
                $row['event_farm_id'] = $esInfo['farm_id'];

                $row['event_farm_roleid'] = $esInfo['farm_roleid'];

                if (!$cache['role_names'][$esInfo['farm_roleid']])
                    $cache['role_names'][$esInfo['farm_roleid']] = $this->db->GetOne("SELECT alias FROM farm_roles WHERE id=? LIMIT 1", [$esInfo['farm_roleid']]);
                $row['event_role_name'] = $cache['role_names'][$esInfo['farm_roleid']];

                $row['event_server_index'] = $esInfo['index'];
            }

            $row['dtadded'] = Scalr_Util_DateTime::convertTz($row['dtadded']);

            if(\Scalr::config('scalr.system.scripting.logs_storage') == 'scalr')
                $row['execution_id'] = null;

            if ($row['message'])
                $row['message'] = nl2br(htmlspecialchars($row['message']));
        }

        $this->response->data($response);
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

    /**
     * @deprecated since 4.0
     * @throws Exception
     */
    public function scriptingMessageAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SCRIPTING_LOGS);

        $entry = $this->db->GetRow("SELECT * FROM scripting_log WHERE id = ?", [$this->getParam('eventId')]);
        if (empty($entry))
            throw new Exception ('Unknown event');

        $farm = DBFarm::LoadByID($entry['farmid']);
        $this->user->getPermissions()->validate($farm);

        $form = [
            [
                'xtype' => 'fieldset',
                'title' => 'Message',
                'layout' => 'fit',
                'items' => [
                    [
                        'xtype' => 'textarea',
                        'readOnly' => true,
                        'hideLabel' => true,
                        'height' => 400,
                        'value' => $entry['message']
                    ]
                ]
            ]
        ];

        $this->response->page('ui/logs/scriptingmessage.js', $form);
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

        $sql = "SELECT `events`.* FROM `farms` INNER JOIN `events` ON `farms`.`id` = `events`.`farmid` WHERE `farms`.`env_id` = ? AND :FILTER:";
        $args = [$this->getEnvironmentId()];

        list($sql, $args) = $this->request->prepareFarmSqlQuery($sql, $args, 'farms');

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
