<?php
    class Scalr_Role_Behavior_Apache extends Scalr_Role_Behavior implements Scalr_Role_iBehavior
    {
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

            $config = new stdClass();
            $config->virtualHosts = array();
            $vhosts = $this->db->Execute("SELECT * FROM `apache_vhosts` WHERE farm_id = ? AND farm_roleid = ?", array(
                $dbServer->farmId, $dbServer->farmRoleId
            ));
            while ($vhost = $vhosts->FetchRow()) {

                if ($vhost['is_ssl_enabled']) {
                    $itm = new stdClass();
                    $itm->hostname = $vhost['name'];
                    $itm->port = 80;
                    $itm->template = $vhost['httpd_conf'];
                    $itm->ssl = 0;
                    $itm->sslCertificateId = null;

                    $vars = unserialize($vhost['httpd_conf_vars']);
                    $vars['host'] = $vhost['name'];
                    $vKeys = array_keys($vars);

                    $keys = array_map(function($item) {
                        return '{$' . $item . '}';
                    }, $vKeys);

                    $vValues = array_values($vars);

                    $itm->template = str_replace($keys, $vValues, $itm->template);

                    $dbServer->applyGlobalVarsToValue($itm->template);

                    array_push($config->virtualHosts, $itm);
                }

                $itm = new stdClass();
                $itm->hostname = $vhost['name'];
                $itm->port = ($vhost['is_ssl_enabled']) ? 443 : 80;
                $itm->template = ($vhost['is_ssl_enabled']) ? $vhost['httpd_conf_ssl'] : $vhost['httpd_conf'];
                $itm->ssl = $vhost['is_ssl_enabled'];
                $itm->sslCertificateId = $vhost['ssl_cert_id'];

                $vars = unserialize($vhost['httpd_conf_vars']);
                $vars['host'] = $vhost['name'];
                $vKeys = array_keys($vars);


                $keys = array_map(function($item) {
                    return '{$' . $item . '}';
                }, $vKeys);

                $vValues = array_values($vars);

                $itm->template = str_replace($keys, $vValues, $itm->template);

                $dbServer->applyGlobalVarsToValue($itm->template);

                array_push($config->virtualHosts, $itm);
            }

            return $config;
        }

        public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
        {
            $message = parent::extendMessage($message, $dbServer);

            switch (get_class($message))
            {
                case "Scalr_Messaging_Msg_HostInitResponse":
                    $message->apache = $this->getConfiguration($dbServer);
                    break;
            }

            return $message;
        }
    }