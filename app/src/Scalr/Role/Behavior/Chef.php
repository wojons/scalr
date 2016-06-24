<?php

class Scalr_Role_Behavior_Chef extends Scalr_Role_Behavior implements Scalr_Role_iBehavior
{
    /** DBFarmRole settings **/
    const ROLE_CHEF_SERVER_ID               = 'chef.server_id';
    const ROLE_CHEF_BOOTSTRAP               = 'chef.bootstrap';
    const ROLE_CHEF_ROLE_NAME               = 'chef.role_name';
    const ROLE_CHEF_ENVIRONMENT             = 'chef.environment';
    const ROLE_CHEF_ATTRIBUTES              = 'chef.attributes';
    const ROLE_CHEF_NODENAME_TPL            = 'chef.node_name_tpl';
    const ROLE_CHEF_SSL_VERIFY_MODE         = 'chef.ssl_verify_mode';
    const ROLE_CHEF_RUNLIST                 = 'chef.runlist';
    const ROLE_CHEF_COOKBOOK_URL            = 'chef.cookbook_url';
    const ROLE_CHEF_COOKBOOK_URL_TYPE       = 'chef.cookbook_url_type';
    const ROLE_CHEF_SSH_PRIVATE_KEY         = 'chef.ssh_private_key';
    const ROLE_CHEF_RELATIVE_PATH           = 'chef.relative_path';
    const ROLE_CHEF_LOG_LEVEL               = 'chef.log_level';
    const ROLE_CHEF_CLIENT_RB_TEMPLATE      = 'chef.client_rb_template';
    const ROLE_CHEF_SOLO_RB_TEMPLATE        = 'chef.solo_rb_template';
    const ROLE_CHEF_RUNLIST_APPEND          = 'chef.runlist_append';
    const ROLE_CHEF_ALLOW_TO_APPEND_RUNLIST = 'chef.allow_to_append_runlist';

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

    /**
     * {@inheritdoc}
     * @see Scalr_Role_Behavior::onHostDown()
     */
    public function onHostDown(DBServer $dbServer, HostDownEvent $event)
    {
        if ($event->isSuspended)
            return;

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
                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                    $dbServer,
                    sprintf("Chef node '%s' removed from chef server", $nodeName)
                ));
            } else {
                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                    $dbServer,
                    sprintf("Unable to remove chef node '%s' from chef server: %s", $nodeName, $status)
                ));
            }
        } catch (Exception $e) {
            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                $dbServer,
                sprintf("Unable to remove chef node '%s' from chef server: %s", $nodeName, $e->getMessage())
            ));
        }

        try {
            $status2 = $chefClient->removeClient($nodeName);
            if ($status2) {
                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
                    $dbServer,
                    sprintf("Chef client '%s' removed from chef server", $nodeName)
                ));
            } else {
                \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                    $dbServer,
                    sprintf("Unable to remove chef client '%s' from chef server: %s", $nodeName, $status2)
                ));
            }
        } catch (Exception $e) {
            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->error(new FarmLogMessage(
                $dbServer,
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

        if (empty($message->chef))
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
            $configuration->runList = $dbServer->applyGlobalVarsToValue($this->mergeRunlists($chefSettings[self::ROLE_CHEF_RUNLIST], $chefSettings[self::ROLE_CHEF_RUNLIST_APPEND]));
            $configuration->cookbookUrlType = $chefSettings[self::ROLE_CHEF_COOKBOOK_URL_TYPE];
            $configuration->sshPrivateKey = isset($chefSettings[self::ROLE_CHEF_SSH_PRIVATE_KEY]) ? $chefSettings[self::ROLE_CHEF_SSH_PRIVATE_KEY] : false;
            $configuration->relativePath = isset($chefSettings[self::ROLE_CHEF_RELATIVE_PATH]) ? $chefSettings[self::ROLE_CHEF_RELATIVE_PATH] : false;
            if ($chefSettings[self::ROLE_CHEF_SOLO_RB_TEMPLATE]) {
                $configuration->soloRbTemplate = $chefSettings[self::ROLE_CHEF_SOLO_RB_TEMPLATE];
            }
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

            if (!empty($chefSettings[self::ROLE_CHEF_SSL_VERIFY_MODE])) {
                $configuration->sslVerifyMode = $chefSettings[self::ROLE_CHEF_SSL_VERIFY_MODE];
            }

            $configuration->serverUrl = $chefServerInfo['url'];
            $configuration->validatorName = $chefServerInfo['v_username'];
            $configuration->validatorKey = $chefServerInfo['v_auth_key'];

            if (!empty($chefSettings[self::ROLE_CHEF_ROLE_NAME]))
                $configuration->role = $chefSettings[self::ROLE_CHEF_ROLE_NAME];
            else
                $configuration->runList = $dbServer->applyGlobalVarsToValue($this->mergeRunlists($chefSettings[self::ROLE_CHEF_RUNLIST], $chefSettings[self::ROLE_CHEF_RUNLIST_APPEND]));

            $configuration->environment = $chefSettings[self::ROLE_CHEF_ENVIRONMENT];
            $configuration->daemonize = $chefSettings[self::ROLE_CHEF_DAEMONIZE];

            if ($chefSettings[self::ROLE_CHEF_CLIENT_RB_TEMPLATE]) {
                $configuration->clientRbTemplate = $chefSettings[self::ROLE_CHEF_CLIENT_RB_TEMPLATE];
            }
        }

        $configuration->logLevel = !$chefSettings[self::ROLE_CHEF_LOG_LEVEL] ? 'auto' : $chefSettings[self::ROLE_CHEF_LOG_LEVEL];

        if ($jsonAttributes)
            $configuration->jsonAttributes = $dbServer->applyGlobalVarsToValue($jsonAttributes);

        return $configuration;
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Role_Behavior::extendMessage()
     */
    public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
    {
        $message = parent::extendMessage($message, $dbServer);

        switch (get_class($message)) {
            case 'Scalr_Messaging_Msg_HostInitResponse':
                $config = $this->getConfiguration($dbServer);

                if (!empty($config->serverUrl) || !empty($config->cookbookUrl)) {
                    $message->chef = $config;

                    $message->chef->scriptName = '[Scalr built-in] Chef bootstrap';
                    $message->chef->executionId = Scalr::GenerateUID();
                    $message->chef->eventName = 'HostInit';
                }

                break;
        }

        return $message;
    }

    /**
     * Merges two chef runlists
     *
     * @param   string  $runlist1 runlist 1
     * @param   string  $runlist2 runlist 2
     * @return  string  resulting runlist
     */
    private function mergeRunlists($runlist1, $runlist2)
    {
        if (!empty($runlist1) && !empty($runlist2)) {
            $runlistDecoded1 = json_decode($runlist1, true);
            $runlistDecoded2 = json_decode($runlist2, true);
            $result = json_encode(array_merge(is_array($runlistDecoded1) ? $runlistDecoded1 : [], is_array($runlistDecoded2) ? $runlistDecoded2 : []));
        } else {
            $result = (!empty($runlist1) ? $runlist1 : '') . (!empty($runlist2) ? $runlist2 : '');
        }

        return $result;
    }
}