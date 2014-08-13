<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Services_Configurations extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_DB_SERVICE_CONFIGURATION);
    }

    public function manageAction()
    {
        $farmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
        $this->user->getPermissions()->validate($farmRole);

        $behavior = $this->getParam('behavior');

        if (!$farmRole->GetRoleObject()->hasBehavior($behavior))
            throw new Exception("Behavior not assigned to this role");

        if ($farmRole->GetRoleObject()->getDbMsrBehavior()) {
            foreach ($farmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING)) as $dbServer) {
                if ($dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER)) {
                    $masterServer = $dbServer;
                    break;
                }
            }
        }

        if ($behavior == ROLE_BEHAVIORS::MYSQL) {
            foreach ($farmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING)) as $dbServer) {
                if ($dbServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER)) {
                    $masterServer = $dbServer;
                    break;
                }
            }
        }

        $params = array(
            'farmRoleId' => $farmRole->ID,
            'behavior'	 => $behavior,
            'farmName' 	 => $farmRole->GetFarmObject()->Name,
            'roleName' 	 => $farmRole->GetRoleObject()->name,
            'behaviorName' => ROLE_BEHAVIORS::GetName($behavior)
        );

        if ($masterServer) {
            $params['masterServer'] = array(
                'serverId'	=> $masterServer->serverId,
                'localIp'	=> $masterServer->localIp,
                'remoteIp'	=> $masterServer->remoteIp
            );

            $params['masterServerId'] = $masterServer->serverId;
            $config = (array)$this->getConfig($masterServer, $behavior);
            if (is_array($config)) {
                foreach ($config as $file => $conf) {
                    $conf = (array)$conf;
                    ksort($conf, SORT_ASC | SORT_STRING);
                    $params['config'][$file] = $conf;
                }
            } else
                throw new Exception("Unable to retrieve configuration from master server");
        } else {
            $params['config'] = $farmRole->GetServiceConfiguration2($behavior);
        }

        $this->response->page( 'ui/services/configurations/manage.js', $params, array('ui/services/configurations/configfield.js'));
    }

    private function getConfig(DBServer $dbServer, $behavior)
    {
        $result = $dbServer->scalarizr->service->getPreset($behavior);
        return $result->result;
    }

    private function setConfig(DBServer $dbServer, $behavior, $config)
    {
        $service = $dbServer->scalarizr->service;
        $service->timeout = 45;
        $result = $service->setPreset($behavior, $config);
        return $result->result;
    }

    public function getConfigurationAction()
    {

    }

    public function xSaveAction()
    {
        //TODO: Think about WebSockets

        $this->request->defineParams(array(
            'config' => array('type' => 'json')
        ));

        $farmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
        $this->user->getPermissions()->validate($farmRole);

        $behavior = $this->getParam('behavior');
        $updateFarmRoleSettings = false;

        if (!$farmRole->GetRoleObject()->hasBehavior($behavior))
            throw new Exception("Behavior not assigned to this role");

        $config = array();
        foreach ($this->getParam('config') as $conf) {
            if (!$config[$conf['configFile']])
                $config[$conf['configFile']] = array();

            if ($config[$conf['configFile']][$conf['key']] === null)
                $config[$conf['configFile']][$conf['key']] = $conf['value'];
            else
                throw new Exception("Variable {$conf['key']} from {$conf['configFile']} already defined. Please remove second definition");
        }

        // Update master
        if ($this->getParam('masterServerId')) {
            $dbServer = DBServer::LoadByID($this->getParam('masterServerId'));
            $this->user->getPermissions()->validate($dbServer);
            if ($dbServer->farmRoleId != $farmRole->ID)
                throw new Exception("Server not found");
            if ($dbServer->status != SERVER_STATUS::RUNNING)
                throw new Exception("Master server is not running. Config cannot be applied.");

            $this->setConfig($dbServer, $behavior, $config);

            $servers = 0;
            $savedServers = 1;
            $updateFarmRoleSettings = true;

            foreach ($farmRole->GetServersByFilter(array('status' => array(SERVER_STATUS::RUNNING, SERVER_STATUS::INIT))) as $server) {
                $servers++;
                try {
                    if ($server->serverId == $dbServer->serverId)
                        continue;

                    $this->setConfig($server, $behavior, $config);
                    $savedServers++;
                } catch (Exception $e) {
                    $warn[] = sprintf("Cannot update configuration on %s (%s): %s", $server->serverId, $server->remoteIp, $e->getMessage());
                }
            }
        } else
            $updateFarmRoleSettings = true;

        if ($updateFarmRoleSettings)
            $farmRole->SetServiceConfiguration($behavior, $config);

        if (!$warn)
            $this->response->success(sprintf("Config successfully applied on %s of %s servers", $savedServers, $servers));
        else {
            $this->response->warning(sprintf("Config was applied on %s of %s servers: %s", $savedServers, $servers, implode("\n", $warn)));
        }
    }
}
