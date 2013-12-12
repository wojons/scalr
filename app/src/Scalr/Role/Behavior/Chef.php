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
        $chefServerInfo['auth_key'] = $this->getCrypto()->decrypt($chefServerInfo['auth_key'], $this->cryptoKey);
        $chefClient = Scalr_Service_Chef_Client::getChef($chefServerInfo['url'], $chefServerInfo['username'], trim($chefServerInfo['auth_key']));

        $chefClient->removeRole($chefRoleName);
    }

    public function onBeforeHostTerminate(DBServer $dbServer) {
        $config = $this->getConfiguration($dbServer);
        if ($config->nodeName) {
            $this->removeNodeFromChefServer($dbServer, $config);
            $dbServer->SetProperty(self::SERVER_CHEF_NODENAME, "");
        }
    }

    public function onHostDown(DBServer $dbServer) {
        $config = $this->getConfiguration($dbServer);
        if ($config->nodeName) {
            $this->removeNodeFromChefServer($dbServer, $config);
            $dbServer->SetProperty(self::SERVER_CHEF_NODENAME, "");
        }
    }

    private function removeNodeFromChefServer(DBServer $dbServer, $config)
    {
        $chefServerId = $dbServer->GetFarmRoleObject()->GetSetting(self::ROLE_CHEF_SERVER_ID);
        $chefServerInfo = $this->db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefServerId));
        $chefServerInfo['auth_key'] = trim($this->getCrypto()->decrypt($chefServerInfo['auth_key'], $this->cryptoKey));

        $chefClient = Scalr_Service_Chef_Client::getChef($chefServerInfo['url'], $chefServerInfo['username'], trim($chefServerInfo['auth_key']));

        try {
            $status = $chefClient->removeNode($config->nodeName);
            if ($status) {
                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Chef node '%s' removed from chef server", $config->nodeName)
                ));
            } else {
                Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Unable to remove chef node '%s' from chef server: %s", $config->nodeName, $status)
                ));
            }
        } catch (Exception $e) {
            Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                $dbServer->farmId,
                sprintf("Unable to remove chef node '%s' from chef server: %s", $config->nodeName, $e->getMessage())
            ));
        }

        try {
            $status2 = $chefClient->removeClient($config->nodeName);
            if ($status2) {
                Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Chef client '%s' removed from chef server", $config->nodeName)
                ));
            } else {
                Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                    $dbServer->farmId,
                    sprintf("Unable to remove chef client '%s' from chef server: %s", $config->nodeName, $status2)
                ));
            }
        } catch (Exception $e) {
            Logger::getLogger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                $dbServer->farmId,
                sprintf("Unable to remove chef node '%s' from chef server: %s", $config->nodeName, $e->getMessage())
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
        }
    }

    public function getConfiguration(DBServer $dbServer) {
        $configuration = new stdClass();
        $dbFarmRole = $dbServer->GetFarmRoleObject();

        if (!$dbFarmRole->GetSetting(self::ROLE_CHEF_BOOTSTRAP))
            return $configuration;

        $jsonAttributes = $dbFarmRole->GetSetting(self::ROLE_CHEF_ATTRIBUTES);
        $chefCookbookUrl = $dbFarmRole->GetSetting(self::ROLE_CHEF_COOKBOOK_URL);
        if ($chefCookbookUrl) {
            $configuration->cookbookUrl = $chefCookbookUrl;
            $configuration->runList = $dbFarmRole->GetSetting(self::ROLE_CHEF_RUNLIST);
            $configuration->cookbookUrlType = $dbFarmRole->GetSetting(self::ROLE_CHEF_COOKBOOK_URL_TYPE);
            $configuration->sshPrivateKey = $dbFarmRole->GetSetting(self::ROLE_CHEF_SSH_PRIVATE_KEY);
            $configuration->relativePath = $dbFarmRole->GetSetting(self::ROLE_CHEF_RELATIVE_PATH);
        } else {

            // Get chef server info
            $chefServerId = $dbFarmRole->GetSetting(self::ROLE_CHEF_SERVER_ID);
            $chefServerInfo = $this->db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefServerId));
            $chefServerInfo['v_auth_key'] = trim($this->getCrypto()->decrypt($chefServerInfo['v_auth_key'], $this->cryptoKey));

            // Prepare node name
            $configuration->nodeName = $dbServer->GetProperty(self::SERVER_CHEF_NODENAME);
            if (!$configuration->nodeName) {
                $nodeNameTpl = $dbFarmRole->GetSetting(self::ROLE_CHEF_NODENAME_TPL);
                if ($nodeNameTpl) {
                    $params = $dbServer->GetScriptingVars();
                    $keys = array_keys($params);
                    $f = create_function('$item', 'return "%".$item."%";');
                    $keys = array_map($f, $keys);
                    $values = array_values($params);

                    $configuration->nodeName = str_replace($keys, $values, $nodeNameTpl);

                    //TODO: Add support for Global variables
                }
            }

            $configuration->serverUrl = $chefServerInfo['url'];
            $configuration->validatorName = $chefServerInfo['v_username'];
            $configuration->validatorKey = $chefServerInfo['v_auth_key'];

            if ($dbFarmRole->GetSetting(self::ROLE_CHEF_ROLE_NAME))
                $configuration->role = $dbFarmRole->GetSetting(self::ROLE_CHEF_ROLE_NAME);
            else
                $configuration->runList = $dbFarmRole->GetSetting(self::ROLE_CHEF_RUNLIST);

            $configuration->environment = $dbFarmRole->GetSetting(self::ROLE_CHEF_ENVIRONMENT);
            $configuration->daemonize = $dbFarmRole->GetSetting(self::ROLE_CHEF_DAEMONIZE);
        }

        if ($jsonAttributes) {
            $params = $dbServer->GetScriptingVars();
            // Prepare keys array and array with values for replacement in script
            $keys = array_keys($params);
            $f = create_function('$item', 'return "%".$item."%";');
            $keys = array_map($f, $keys);
            $values = array_values($params);
            $contents = str_replace($keys, $values, $jsonAttributes);

            $configuration->jsonAttributes = str_replace('\%', "%", $contents);

            //TODO: Add support for Global variables
        }

        return $configuration;
    }

    public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
    {
        $message = parent::extendMessage($message, $dbServer);

        switch (get_class($message))
        {
            case "Scalr_Messaging_Msg_HostInitResponse":

                $config = $this->getConfiguration($dbServer);
                if ($config->serverUrl || $config->cookbookUrl)
                    $message->chef = $config;

                break;
        }

        return $message;
    }
}