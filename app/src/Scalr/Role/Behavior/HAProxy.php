<?php
    class Scalr_Role_Behavior_HAProxy extends Scalr_Role_Behavior implements Scalr_Role_iBehavior
    {
        const ROLE_PROXIES = 'haproxy.proxies';
        const ROLE_TEMPLATE = 'haproxy.template';

        public function __construct($behaviorName)
        {
            parent::__construct($behaviorName);
        }

        public function getSecurityRules()
        {
            return array();
        }

        public function getConfiguration(DBServer $dbServer) {
            $configuration = new stdClass();
            $configuration->proxies = json_decode($dbServer->GetFarmRoleObject()->GetSetting(self::ROLE_PROXIES), true);
            if ($dbServer->GetFarmRoleObject()->GetSetting(self::ROLE_TEMPLATE)) {
                $configuration->template = $dbServer->GetFarmRoleObject()->GetSetting(self::ROLE_TEMPLATE);
            }

            if (count($configuration->proxies) > 0) {
                $dbFarm = $dbServer->GetFarmObject();
                foreach ($configuration->proxies as &$proxy) {
                    if (count($proxy['backends']) > 0) {
                        foreach ($proxy['backends'] as &$backend) {
                            if (isset($backend['farm_role_alias']) && !empty($backend['farm_role_alias']))
                                $backend['farm_role_id'] = $dbFarm->GetFarmRoleIdByAlias($backend['farm_role_alias']);
                        }
                    }
                }
            }
            
            return $configuration;
        }

        public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
        {
            $message = parent::extendMessage($message, $dbServer);

            switch (get_class($message))
            {
                case "Scalr_Messaging_Msg_HostInitResponse":

                    $message->haproxy = $this->getConfiguration($dbServer);

                    break;
            }

            return $message;
        }
    }