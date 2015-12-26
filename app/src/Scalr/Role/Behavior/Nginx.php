<?php
use Scalr\Exception\InvalidEntityConfigurationException;
use Scalr\Model\Entity\FarmRole;

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
            
            $dbFarm = $dbServer->GetFarmObject();
            
            if (count($configuration->proxies) > 0) {
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

                    $message->www = $this->getConfiguration($dbServer);

                    break;
            }

            return $message;
        }

        /**
         * {@inheritdoc}
         * @see Scalr_Role_Behavior::setupBehavior()
         */
        public static function setupBehavior(FarmRole $farmRole)
        {
            $proxies = (array) @json_decode($farmRole->settings[Scalr_Role_Behavior_Nginx::ROLE_PROXIES], true);
            foreach ($proxies as $proxyIndex => $proxy) {
                if ($proxy['ssl'] == 1) {
                    if (empty($proxy['ssl_certificate_id'])) {
                        throw new InvalidEntityConfigurationException("SSL certificate is required for proxy {$proxyIndex}");
                    }

                    if ($proxy['port'] == $proxy['ssl_port']) {
                        throw new InvalidEntityConfigurationException("HTTP and HTTPS ports cannot be the same for proxy {$proxyIndex}");
                    }
                }

                if (count($proxy['backends']) > 0) {
                    foreach ($proxy['backends'] as $backend) {
                        if (empty($backend['farm_role_id']) && empty($backend['farm_role_alias']) && empty($backend['host'])) {
                            throw new InvalidEntityConfigurationException("Destination is required for proxy {$proxyIndex}");
                        }
                    }
                }
            }
        }
    }