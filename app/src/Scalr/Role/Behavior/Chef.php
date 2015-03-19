<?php

class Scalr_Role_Behavior_Chef extends Scalr_Role_Behavior implements Scalr_Role_iBehavior
{
    /** DBFarmRole settings **/
    const ROLE_CHEF_SERVER_ID           = 'chef.server_id';
    const ROLE_CHEF_BOOTSTRAP           = 'chef.bootstrap';
    const ROLE_CHEF_ROLE_NAME           = 'chef.role_name';
    const ROLE_CHEF_ENVIRONMENT         = 'chef.environment';
    const ROLE_CHEF_ATTRIBUTES          = 'chef.attributes';
    const ROLE_CHEF_NODENAME_TPL        = 'chef.node_name_tpl';
    const ROLE_CHEF_RUNLIST             = 'chef.runlist';
    const ROLE_CHEF_COOKBOOK_URL        = 'chef.cookbook_url';
    const ROLE_CHEF_COOKBOOK_URL_TYPE   = 'chef.cookbook_url_type';
    const ROLE_CHEF_SSH_PRIVATE_KEY     = 'chef.ssh_private_key';
    const ROLE_CHEF_RELATIVE_PATH       = 'chef.relative_path';
    const ROLE_CHEF_LOG_LEVEL           = 'chef.log_level';

    /**
     * @deprecated
     */
    const ROLE_CHEF_RUNLIST_ID		= 'chef.runlist_id';
    const ROLE_CHEF_CHECKSUM		= 'chef.checksum';
    const ROLE_CHEF_DAEMONIZE       = 'chef.daemonize';



    const SERVER_CHEF_NODENAME		= 'chef.node_name';

    public function __construct($behaviorName)
    {
        parent::__construct($behaviorName);
    }

    public function getSecurityRules()
    {
        return array();
    }

    private function removeChefRole($chefServerId, $chefRoleName)
    {
        //Remove role and clear chef settings
        $chefServerInfo = $this->db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefServerId));
        $chefServerInfo['auth_key'] = $this->getCrypto()->decrypt($chefServerInfo['auth_key']);
        $chefClient = Scalr_Service_Chef_Client::getChef($chefServerInfo['url'], $chefServerInfo['username'], trim($chefServerInfo['auth_key']));

        $chefClient->removeRole($chefRoleName);
    }

    public function onBeforeHostTerminate(DBServer $dbServer) {
        /*
        $nodeName = $dbServer->GetProperty(self::SERVER_CHEF_NODENAME);
        $config = $this->getConfiguration($dbServer);
        if (!empty($nodeName) && isset($config->serverUrl)) {
            $this->removeNodeFromChefServer($dbServer, $config, $nodeName);
            $dbServer->SetProperty(self::SERVER_CHEF_NODENAME, "");
        }
        */
    }

    public function onHostDown(DBServer $dbServer) {
        $nodeName = $dbServer->GetProperty(self::SERVER_CHEF_NODENAME);
        $config = $this->getConfiguration($dbServer);
        if (!empty($nodeName) && isset($config->serverUrl)) {
            $this->removeNodeFromChefServer($dbServer, $config, $nodeName);
            $dbServer->SetProperty(self::SERVER_CHEF_NODENAME, "");
        }
    }

    private function removeNodeFromChefServer(DBServer $dbServer, $config, $nodeName)
    {
        $chefSettings = $dbServer->GetFarmRoleObject()->getChefSettings();
        $chefServerInfo = $this->db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefSettings[self::ROLE_CHEF_SERVER_ID]));
        $chefServerInfo['auth_key'] = trim($this->getCrypto()->decrypt($chefServerInfo['auth_key']));

        $chefClient = Scalr_Service_Chef_Client::getChef($config->serverUrl, $chefServerInfo['username'], trim($chefServerInfo['auth_key']));

        try {
            $status = $chefClient->removeNode($nodeName);
            if ($status) {
                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Chef node '%s' removed from chef server", $nodeName)
                ));
            } else {
                Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Unable to remove chef node '%s' from chef server: %s", $nodeName, $status)
                ));
            }
        } catch (Exception $e) {
            Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                $dbServer->farmId,
                sprintf("Unable to remove chef node '%s' from chef server: %s", $nodeName, $e->getMessage())
            ));
        }

        try {
            $status2 = $chefClient->removeClient($nodeName);
            if ($status2) {
                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Chef client '%s' removed from chef server", $nodeName)
                ));
            } else {
                Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Unable to remove chef client '%s' from chef server: %s", $nodeName, $status2)
                ));
            }
        } catch (Exception $e) {
            Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                $dbServer->farmId,
                sprintf("Unable to remove chef node '%s' from chef server: %s", $nodeName, $e->getMessage())
            ));
        }
    }

    public function onFarmSave(DBFarm $dbFarm, DBFarmRole $dbFarmRole)
    {

    }

    public function handleMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
    {
        parent::handleMessage($message, $dbServer);

        if (!$message->chef)
            return;

        switch (get_class($message))
        {
            case "Scalr_Messaging_Msg_HostUp":
                $dbServer->SetProperty(self::SERVER_CHEF_NODENAME, $message->chef->nodeName);
                break;

            case "Scalr_Messaging_Msg_HostUpdate":
                $dbServer->SetProperty(self::SERVER_CHEF_NODENAME, $message->chef->nodeName);
                break;
        }
    }

    public function getConfiguration(DBServer $dbServer) {
        $configuration = new stdClass();
        $chefSettings = $dbServer->GetFarmRoleObject()->getChefSettings();

        if (empty($chefSettings[self::ROLE_CHEF_BOOTSTRAP]))
            return $configuration;

        $jsonAttributes = $chefSettings[self::ROLE_CHEF_ATTRIBUTES];
        if (!empty($chefSettings[self::ROLE_CHEF_COOKBOOK_URL])) {
            $configuration->cookbookUrl = $chefSettings[self::ROLE_CHEF_COOKBOOK_URL];
            $configuration->runList = $dbServer->applyGlobalVarsToValue($chefSettings[self::ROLE_CHEF_RUNLIST]);
            $configuration->cookbookUrlType = $chefSettings[self::ROLE_CHEF_COOKBOOK_URL_TYPE];
            $configuration->sshPrivateKey = isset($chefSettings[self::ROLE_CHEF_SSH_PRIVATE_KEY]) ? $chefSettings[self::ROLE_CHEF_SSH_PRIVATE_KEY] : false;
            $configuration->relativePath = isset($chefSettings[self::ROLE_CHEF_RELATIVE_PATH]) ? $chefSettings[self::ROLE_CHEF_RELATIVE_PATH] : false;
        } else {

            // Get chef server info
            $chefServerInfo = $this->db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefSettings[self::ROLE_CHEF_SERVER_ID]));
            $chefServerInfo['v_auth_key'] = trim($this->getCrypto()->decrypt($chefServerInfo['v_auth_key']));

            // Prepare node name
            $configuration->nodeName = $chefSettings[self::SERVER_CHEF_NODENAME];
            if (!$configuration->nodeName) {
                $nodeNameTpl = $chefSettings[self::ROLE_CHEF_NODENAME_TPL];
                if ($nodeNameTpl)
                    $configuration->nodeName = $dbServer->applyGlobalVarsToValue($nodeNameTpl);
            }

            $configuration->serverUrl = $chefServerInfo['url'];
            $configuration->validatorName = $chefServerInfo['v_username'];
            $configuration->validatorKey = $chefServerInfo['v_auth_key'];

            if (!empty($chefSettings[self::ROLE_CHEF_ROLE_NAME]))
                $configuration->role = $chefSettings[self::ROLE_CHEF_ROLE_NAME];
            else
                $configuration->runList = $dbServer->applyGlobalVarsToValue($chefSettings[self::ROLE_CHEF_RUNLIST]);

            $configuration->environment = $chefSettings[self::ROLE_CHEF_ENVIRONMENT];
            $configuration->daemonize = $chefSettings[self::ROLE_CHEF_DAEMONIZE];
        }

        $configuration->logLevel = !$chefSettings[self::ROLE_CHEF_LOG_LEVEL] ? 'auto' : $chefSettings[self::ROLE_CHEF_LOG_LEVEL];

        if ($jsonAttributes)
            $configuration->jsonAttributes = $dbServer->applyGlobalVarsToValue($jsonAttributes);

        return $configuration;
    }

    public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
    {
        $message = parent::extendMessage($message, $dbServer);

        switch (get_class($message))
        {
            case "Scalr_Messaging_Msg_HostInitResponse":

                $config = $this->getConfiguration($dbServer);
                if ($config->serverUrl || $config->cookbookUrl) {
                    $message->chef = $config;
                    
                    $message->chef->scriptName = '[Scalr built-in] Chef bootstrap';
                    $message->chef->executionId = Scalr::GenerateUID();
                    $message->chef->eventName = 'HostInit';
                }

                break;
        }

        return $message;
    }
}