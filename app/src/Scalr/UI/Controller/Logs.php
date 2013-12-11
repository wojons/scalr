<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Logs extends Scalr_UI_Controller
{
    public function systemAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SYSTEM_LOGS);

        $farms = self::loadController('Farms')->getList();
        array_unshift($farms, array('id' => 0, 'name' => 'All farms'));

        $this->response->page('ui/logs/system.js', array(
            'farms' => $farms,
            'params' => array(
                'severity[1]' => 0,
                'severity[2]' => 1,
                'severity[3]' => 1,
                'severity[4]' => 1,
                'severity[5]' => 1
            )
        ));
    }

    public function getScriptingLogAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SCRIPTING_LOGS);

        $this->request->defineParams(array(
            'executionId' => array('type' => 'string')
        ));

        $info = $this->db->GetRow("SELECT * FROM scripting_log WHERE execution_id = ? LIMIT 1", array($this->getParam('executionId')));
        if (!$info)
            throw new Exception('Script execution log not found');

        try {
            $dbServer = DBServer::LoadByID($info['server_id']);
            if (!in_array($dbServer->status, array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING)))
                throw new Exception();

        } catch (Exception $e) {
            throw new Exception('Server was terminated and logs no longer available');
        }

        //Note! We should not check not-owned-farms permission here. It's approved by Igor.
        if ($dbServer->envId != $this->environment->id) {
            throw new \Scalr_Exception_InsufficientPermissions();
        }

        $client = Scalr_Net_Scalarizr_Client::getClient(
            $dbServer,
            Scalr_Net_Scalarizr_Client::NAMESPACE_SYSTEM,
            $dbServer->getPort(DBServer::PORT_API)
        );

        $logs = $client->getScriptLogs($this->getParam('executionId'));
        $msg = sprintf("STDERR: %s \n\n STDOUT: %s", base64_decode($logs->stderr), base64_decode($logs->stdout));
        $msg = nl2br(htmlspecialchars($msg));

        $this->response->data(array('message' => $msg));
    }

    public function scriptingAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SCRIPTING_LOGS);

        $farms = self::loadController('Farms')->getList();
        array_unshift($farms, array('id' => '0', 'name' => 'All farms'));

        $this->response->page('ui/logs/scripting.js', array(
            'farms' => $farms
        ));
    }

    public function apiAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_API_LOGS);

        $this->response->page('ui/logs/api.js');
    }

    public function xListLogsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SYSTEM_LOGS);

        $this->request->defineParams(array(
            'serverId' => array('type' => 'string'),
            'farmId' => array('type' => 'int'),
            'severity' => array('type' => 'array'),
            'query' => array('type' => 'string'),
            'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'DESC'))
        ));

        $sql = "SELECT * FROM logentries WHERE :FILTER:";
        $args = array();

        if ($this->getParam('serverId')) {
            $sql .= ' AND serverid = ?';
            $args[] = $this->getParam('serverId');
        }

        $farms = $this->db->GetCol("SELECT id FROM farms WHERE env_id=?", array($this->getEnvironmentId()));
        if ($this->getParam('farmId') && in_array($this->getParam('farmId'), $farms)) {
            $sql .= ' AND farmid = ?';
            $args[] = $this->getParam('farmId');
        } else {
            if (count($farms)) {
                $sql .= ' AND farmid IN (' . implode(',', $farms) . ')';
            } else {
                $sql .= ' AND 0';
            }
        }

        if ($this->getParam('severity')) {
            $severities = array();
            foreach ($this->getParam('severity') as $key => $value) {
                if ($value == 1)
                    $severities[] = intval($key);
            }
            if (count($severities)) {
                $severities = implode(",", $severities);
                $sql .= " AND severity IN ($severities)";
            } else {
                $sql .= " AND 0"; // is it right ?
            }
        }

        if ($this->getParam('byDate')) {
            try {
                $tz = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);
                if (! $tz)
                    $tz = 'UTC';

                $tz = new DateTimeZone($tz);
                $dtS = new DateTime($this->getParam('byDate'), $tz);
                $dtE = new DateTime($this->getParam('byDate'), $tz);

                if ($this->getParam('fromTime'))
                    $dtS = DateTime::createFromFormat('Y-m-d H:i', $this->getParam('byDate') . ' ' . $this->getParam('fromTime'), $tz);

                if ($this->getParam('toTime'))
                    $dtE = DateTime::createFromFormat('Y-m-d H:i', $this->getParam('byDate') . ' ' . $this->getParam('toTime'), $tz);
                else
                    $dtE = $dtE->add(new DateInterval('P1D'));

                $sql .= ' AND time > ? AND time < ?';
                $args[] = $dtS->getTimestamp();
                $args[] = $dtE->getTimestamp();
            } catch (Exception $e) {}
        }

        $severities = array(1 => "Debug", 2 => "Info", 3 => "Warning", 4 => "Error", 5 => "Fatal");
        if ($this->getParam('action') == "download") {
            $fileContent = array();
            $farmNames = array();
            $fileContent[] = "Type;Time;Farm;Caller;Message\r\n";

            $response = $this->buildResponseFromSql2($sql, array('id', 'time'), array('message', 'serverid', 'source'), $args, true);

            foreach($response["data"] as &$data) {
                $data["time"] = Scalr_Util_DateTime::convertTz((int)$data["time"]);
                $data["s_severity"] = $severities[$data["severity"]];

                if (!$farmNames[$data['farmid']])
                    $farmNames[$data['farmid']] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", array($data['farmid']));

                $data['farm_name'] = $farmNames[$data['farmid']];

                $data['message'] = str_replace("<br />","",$data['message']);
                $data['message'] = str_replace("\n","",$data['message']);

                $fileContent[] = "{$data['s_severity']};{$data['time']};{$data['farm_name']};{$data['source']};{$data['message']}";
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
            $farmNames = array();
            $response = $this->buildResponseFromSql2($sql, array('id', 'time'), array('message', 'serverid', 'source'), $args);
            foreach ($response["data"] as &$row) {
                $row["time"] = Scalr_Util_DateTime::convertTz((int)$row["time"]);

                $row["servername"] = $row["serverid"];
                $row["s_severity"] = $severities[$row["severity"]];
                $row["severity"] = (int)$row["severity"];

                if (!isset($farmNames[$row['farmid']]))
                    $farmNames[$row['farmid']] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", array($row['farmid']));

                $row['farm_name'] = $farmNames[$row['farmid']];
                $row['message'] = nl2br(htmlspecialchars($row['message']));
            }

            $this->response->data($response);
        }
    }

    public function xListScriptingLogsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_SCRIPTING_LOGS);

        $this->request->defineParams(array(
            'farmId' => array('type' => 'int'),
            'serverId' => array('type' => 'string'),
            'query' => array('type' => 'string'),
            'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'DESC'))
        ));

        $sql = "SELECT * FROM scripting_log WHERE :FILTER:";
        $args = array();

        $farms = $this->db->GetCol("SELECT id FROM farms WHERE env_id=?", array($this->getEnvironmentId()));
        if ($this->getParam('farmId') && in_array($this->getParam('farmId'), $farms)) {
            $sql .= ' AND farmid = ?';
            $args[] = $this->getParam('farmId');
        } else {
            if (count($farms)) {
                $sql .= ' AND farmid IN (' . implode(',', $farms) . ')';
            } else {
                $sql .= ' AND 0';
            }
        }

        if ($this->getParam('serverId')) {
            $sql .= ' AND server_id = ?';
            $args[] = $this->getParam('serverId');
        }

        if ($this->getParam('eventId')) {
            $sql .= ' AND event_id = ?';
            $args[] = $this->getParam('eventId');
        }

        if ($this->getParam('byDate')) {
            try {
                $tz = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);
                if (! $tz)
                    $tz = 'UTC';

                $tz = new DateTimeZone($tz);
                $dtS = new DateTime($this->getParam('byDate'), $tz);
                $dtE = new DateTime($this->getParam('byDate'), $tz);

                if ($this->getParam('fromTime'))
                    $dtS = DateTime::createFromFormat('Y-m-d H:i', $this->getParam('byDate') . ' ' . $this->getParam('fromTime'), $tz);

                if ($this->getParam('toTime'))
                    $dtE = DateTime::createFromFormat('Y-m-d H:i', $this->getParam('byDate') . ' ' . $this->getParam('toTime'), $tz);
                else
                    $dtE = $dtE->add(new DateInterval('P1D'));

                Scalr_Util_DateTime::convertTimeZone($dtS);
                Scalr_Util_DateTime::convertTimeZone($dtE);

                $sql .= ' AND dtadded > ? AND dtadded < ?';
                $args[] = $dtS->format('Y-m-d H:i:s');
                $args[] = $dtE->format('Y-m-d H:i:s');
            } catch (Exception $e) {}
        }

        $response = $this->buildResponseFromSql2($sql, array('id', 'dtadded'), array('script_name', 'server_id', 'event_server_id', 'event'), $args);
        $cache = array();
        foreach ($response["data"] as &$row) {
            //
            //target_data
            //
            if (!$cache['farm_names'][$row['farmid']])
                $cache['farm_names'][$row['farmid']] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", array($row['farmid']));
            $row['target_farm_name'] = $cache['farm_names'][$row['farmid']];
            $row['target_farm_id'] = $row['farmid'];

            $sInfo = $this->db->GetRow("SELECT role_id, farm_roleid, `index` FROM servers WHERE server_id = ? LIMIT 1", array($row['server_id']));
            $row['target_farm_roleid'] = $sInfo['farm_roleid'];

            if (!$cache['role_names'][$sInfo['role_id']])
                $cache['role_names'][$sInfo['role_id']] = $this->db->GetOne("SELECT name FROM roles WHERE id=?", array($sInfo['role_id']));
            $row['target_role_name'] = $cache['role_names'][$sInfo['role_id']];

            $row['target_server_index'] = $sInfo['index'];
            $row['target_server_id'] = $row['server_id'];

            //
            //event_data
            //
            if ($row['event_server_id']) {
                $esInfo = $this->db->GetRow("SELECT role_id, farm_roleid, `index`, farm_id FROM servers WHERE server_id = ? LIMIT 1", array($row['event_server_id']));

                if (!$cache['farm_names'][$esInfo['farm_id']])
                    $cache['farm_names'][$esInfo['farm_id']] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", array($esInfo['farm_id']));
                $row['event_farm_name'] = $cache['farm_names'][$esInfo['farm_id']];
                $row['event_farm_id'] = $esInfo['farm_id'];

                $row['event_farm_roleid'] = $esInfo['farm_roleid'];

                if (!$cache['role_names'][$esInfo['role_id']])
                    $cache['role_names'][$esInfo['role_id']] = $this->db->GetOne("SELECT name FROM roles WHERE id=? LIMIT 1", array($esInfo['role_id']));
                $row['event_role_name'] = $cache['role_names'][$esInfo['role_id']];

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

        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'DESC'))
        ));

        $sql = "SELECT * from api_log WHERE env_id = ?";
        $args = array($this->getEnvironmentId());

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

        $response = $this->buildResponseFromSql($sql, array('id', 'dtadded', 'action', 'ipaddress'), array('transaction_id'), $args);
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

        $entry = $this->db->GetRow("SELECT * FROM scripting_log WHERE id = ?", array($this->getParam('eventId')));
        if (empty($entry))
            throw new Exception ('Unknown event');

        $farm = DBFarm::LoadByID($entry['farmid']);
        $this->user->getPermissions()->validate($farm);

        $form = array(
            array(
                'xtype' => 'fieldset',
                'title' => 'Message',
                'layout' => 'fit',
                'items' => array(
                    array(
                        'xtype' => 'textarea',
                        'readOnly' => true,
                        'hideLabel' => true,
                        'height' => 400,
                        'value' => $entry['message']
                    )
                )
            )
        );

        $this->response->page('ui/logs/scriptingmessage.js', $form);
    }

    public function apiLogEntryDetailsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_LOGS_API_LOGS);

        $entry = $this->db->GetRow("SELECT * FROM api_log WHERE transaction_id = ? AND clientid = ? LIMIT 1", array($this->getParam('transactionId'), $this->user->getAccountId()));
        if (empty($entry))
            throw new Exception ('Unknown transaction');

        $entry['dtadded'] = Scalr_Util_DateTime::convertTz((int)$entry['dtadded']);

        $form = array(
            array(
                'xtype' => 'fieldset',
                'title' => 'General information',
                'labelWidth' => 120,
                'items' => array(
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Transaction ID',
                        'value' => $entry['transaction_id']
                    ),
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Action',
                        'value' => $entry['action']
                    ),
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'IP address',
                        'value' => $entry['ipaddress']
                    ),
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Time',
                        'value' => $entry['dtadded']
                    )
                )
            ),
            array(
                'xtype' => 'fieldset',
                'title' => 'Request',
                'layout' => 'fit',
                'items' => array(
                    array(
                        'xtype' => 'textarea',
                        'grow' => true,
                        'growMax' => 200,
                        'readOnly' => true,
                        'hideLabel' => true,
                        'value' => $entry['request']
                    )
                )
            ),
            array(
                'xtype' => 'fieldset',
                'title' => 'Response',
                'layout' => 'fit',
                'items' => array(
                    array(
                        'xtype' => 'textarea',
                        'grow' => true,
                        'growMax' => 200,
                        'readOnly' => true,
                        'hideLabel' => true,
                        'value' => $entry['response']
                    )
                )
            )
        );

        $this->response->page('ui/logs/apilogentrydetails.js', $form);
    }
}
