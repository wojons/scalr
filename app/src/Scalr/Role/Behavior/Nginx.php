<?php
    class Scalr_Role_Behavior_Nginx extends Scalr_Role_Behavior implements Scalr_Role_iBehavior
    {
        const ROLE_PROXIES = 'nginx.proxies';

        public function __construct($behaviorName)
        {
            parent::__construct($behaviorName);
        }

        public function getSecurityRules()
        {
            return array(
                "tcp:80:80:0.0.0.0/0",
                "tcp:443:443:0.0.0.0/0"
            );
        }

        public function getConfiguration(DBServer $dbServer) {

            $configuration = new stdClass();
            $configuration->proxies = json_decode($dbServer->GetFarmRoleObject()->GetSetting(self::ROLE_PROXIES), true);

            return $configuration;
        }

        public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
        {
            $message = parent::extendMessage($message, $dbServer);

            switch (get_class($message))
            {
                case "Scalr_Messaging_Msg_HostInitResponse":

                    $message->www = $this->getConfiguration($dbServer);

                    break;
            }

            return $message;
        }
    }