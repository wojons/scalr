<?php

use Scalr\Model\Entity;

class ScalrEnvironment20081125 extends ScalrEnvironment
{
    protected function ListScripts()
    {
        $ResponseDOMDocument = $this->CreateResponse();

        $ScriptsDOMNode = $ResponseDOMDocument->createElement("scripts");

        if (!$this->DBServer->IsSupported(0.5)) {
            throw new Exception("ami-scripts roles cannot execute scripts anymore. Please upgrade your roles to scalarizr: http://scalr.net/blog/announcements/ami-scripts/");
        } elseif (!$this->DBServer->IsSupported(0.9) && $this->DBServer->IsSupported(0.8)) {
            throw new Exception("Windows scalarizr doesn't support script executions");
        } else {
            
        }

        $ResponseDOMDocument->documentElement->appendChild($ScriptsDOMNode);

        return $ResponseDOMDocument;
    }

    protected function ListVirtualhosts()
    {
        $ResponseDOMDocument = $this->CreateResponse();

        $type = $this->GetArg("type");
        $name = $this->GetArg("name");
        $https = $this->GetArg("https");

        $virtual_hosts = $this->DB->GetAll("SELECT * FROM apache_vhosts WHERE farm_roleid=?",
            array($this->DBServer->farmRoleId)
        );

        $VhostsDOMNode = $ResponseDOMDocument->createElement("vhosts");

        $DBFarmRole = $this->DBServer->GetFarmRoleObject();

        if ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::NGINX))
        {
            //Still used for compatibility mode.

            $vhost_info = $this->DB->GetRow("SELECT * FROM apache_vhosts WHERE farm_id=? AND is_ssl_enabled='1' LIMIT 1",
                array($this->DBServer->farmId)
            );

            if ($vhost_info)
            {
                $template = file_get_contents(APPPATH."/templates/services/nginx/ssl.vhost.tpl");
                if ($template) {
                    $vars = unserialize($vhost_info['httpd_conf_vars']);
                    $vars['host'] = $vhost_info['name'];
                    $vKeys = array_keys($vars);

                    $f = create_function('$item', 'return "{\$".$item."}";');
                    $keys = array_map($f, $vKeys);
                    $vValues = array_values($vars);

                    $contents = str_replace($keys, $vValues, $template);

                    $this->DBServer->applyGlobalVarsToValue($contents);

                    $VhostDOMNode =  $ResponseDOMDocument->createElement("vhost");
                    $VhostDOMNode->setAttribute("hostname", $vhost_info['name']);
                    $VhostDOMNode->setAttribute("https", "1");
                    $VhostDOMNode->setAttribute("type", "nginx");

                    $RawDOMNode = $ResponseDOMDocument->createElement("raw");
                    $RawDOMNode->appendChild($ResponseDOMDocument->createCDATASection($contents));

                    $VhostDOMNode->appendChild($RawDOMNode);
                    $VhostsDOMNode->appendChild($VhostDOMNode);
                }
                else
                    throw new Exception("Virtualhost template not found in database. (farm roleid: {$DBFarmRole->ID})");
            }
        }
        elseif ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::APACHE))
        {
            while (count($virtual_hosts) > 0)
            {
                $virtualhost = array_shift($virtual_hosts);

                if ($virtualhost['is_ssl_enabled'])
                {
                    $nonssl_vhost = $virtualhost;
                    $nonssl_vhost['is_ssl_enabled'] = 0;
                    array_push($virtual_hosts, $nonssl_vhost);
                }

                //Filter by name
                if ($this->GetArg("name") !== null && $this->GetArg("name") != $virtualhost['name'])
                    continue;

                // Filter by https
                if ($this->GetArg("https") !== null && $virtualhost['is_ssl_enabled'] != $this->GetArg("https"))
                    continue;

                $VhostDOMNode =  $ResponseDOMDocument->createElement("vhost");
                $VhostDOMNode->setAttribute("hostname", $virtualhost['name']);
                $VhostDOMNode->setAttribute("https", $virtualhost['is_ssl_enabled']);
                $VhostDOMNode->setAttribute("type", "apache");

                $vars = unserialize($virtualhost['httpd_conf_vars']);
                $vars['host'] = $virtualhost['name'];
                $vKeys = array_keys($vars);

                $f = create_function('$item', 'return "{\$".$item."}";');
                $keys = array_map($f, $vKeys);
                $vValues = array_values($vars);

                if (!$virtualhost['is_ssl_enabled'])
                    $template = $virtualhost['httpd_conf'];
                else
                    $template = $virtualhost['httpd_conf_ssl'];

                $contents = str_replace($keys, $vValues, $template);

                $this->DBServer->applyGlobalVarsToValue($contents);

                $RawDOMNode = $ResponseDOMDocument->createElement("raw");
                $RawDOMNode->appendChild($ResponseDOMDocument->createCDATASection($contents));

                $VhostDOMNode->appendChild($RawDOMNode);
                $VhostsDOMNode->appendChild($VhostDOMNode);
            }
        }

        $ResponseDOMDocument->documentElement->appendChild($VhostsDOMNode);

        return $ResponseDOMDocument;
    }

    protected function ListRoleParams()
    {
        $ResponseDOMDocument = $this->CreateResponse();

        // No longer supported. Method here for backward compatibility

        return $ResponseDOMDocument;
    }

    /**
     * Return HTTPS certificate and private key
     * @return DOMDocument
     */
    protected function GetHttpsCertificate()
    {
        $sslInfo = null;

        $ResponseDOMDocument = $this->CreateResponse();

        if (in_array($this->DBServer->status, array(SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::TERMINATED, SERVER_STATUS::SUSPENDED)))
            return $ResponseDOMDocument;

        $hostName = $this->GetArg("hostname") ? " AND name=".$this->qstr($this->GetArg("hostname")) : "";

        if ($this->GetArg("id")) {
            $sslInfo = Entity\SslCertificate::findPk($this->GetArg("id"));
            if ($sslInfo->envId != $this->DBServer->envId)
                $sslInfo = null;
        } else {
            if ($this->DBServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::NGINX)) {
                $vhost_info = $this->DB->GetRow("SELECT * FROM apache_vhosts WHERE farm_id=? AND is_ssl_enabled='1' {$hostName} LIMIT 1",
                    array($this->DBServer->farmId)
                );
            }
            else {
                $vhost_info = $this->DB->GetRow("SELECT * FROM apache_vhosts WHERE farm_roleid=? AND is_ssl_enabled='1' {$hostName} LIMIT 1",
                    array($this->DBServer->farmRoleId)
                );
            }

            if ($vhost_info) {
                $sslInfo = Entity\SslCertificate::findPk($vhost_info['ssl_cert_id']);
                if ($sslInfo->envId != $this->DBServer->envId)
                    $sslInfo = null;
            }
        }

        if ($sslInfo) {

            $vhost = $ResponseDOMDocument->createElement("virtualhost");
            $vhost->setAttribute("name", $sslInfo->name);

            $vhost->appendChild(
                $ResponseDOMDocument->createElement("cert", $sslInfo->certificate)
            );
            $vhost->appendChild(
                $ResponseDOMDocument->createElement("pkey", $sslInfo->privateKey)
            );
            $vhost->appendChild(
                $ResponseDOMDocument->createElement("ca_cert", $sslInfo->caBundle)
            );

            $ResponseDOMDocument->documentElement->appendChild(
                $vhost
            );
        }

        return $ResponseDOMDocument;
    }

    /**
     * List farm roles and hosts list for each role
     * Allowed args: role=(String Role Name) | behaviour=(app|www|mysql|base|memcached)
     * @return DOMDocument
     */
    protected function ListRoles()
    {
        $ResponseDOMDocument = $this->CreateResponse();

        $RolesDOMNode = $ResponseDOMDocument->createElement('roles');
        $ResponseDOMDocument->documentElement->appendChild($RolesDOMNode);

        $sql_query = "SELECT id FROM farm_roles WHERE farmid=?";
        $sql_query_args = array($this->DBServer->farmId);

        // Filter by behaviour
        if ($this->GetArg("behaviour"))
        {
            $sql_query .= " AND role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?)";
            array_push($sql_query_args, $this->GetArg("behaviour"));
        }

        // Filter by role
        if ($this->GetArg("role"))
        {
            $sql_query .= " AND role_id IN (SELECT id FROM roles WHERE name=?)";
            array_push($sql_query_args, $this->GetArg("role"));
        }

        if ($this->GetArg("role-id"))
        {
            $sql_query .= " AND role_id = ?";
            array_push($sql_query_args, $this->GetArg("role-id"));
        }

        if ($this->GetArg("farm-role-id"))
        {
            $sql_query .= " AND id = ?";
            array_push($sql_query_args, $this->GetArg("farm-role-id"));
        }

        $farm_roles = $this->DB->GetAll($sql_query, $sql_query_args);
        foreach ($farm_roles as $farm_role)
        {
            $DBFarmRole = DBFarmRole::LoadByID($farm_role['id']);

            // Create role node
            $RoleDOMNode = $ResponseDOMDocument->createElement('role');
            $RoleDOMNode->setAttribute('behaviour', implode(",", $DBFarmRole->GetRoleObject()->getBehaviors()));
            $RoleDOMNode->setAttribute('name', DBRole::loadById($DBFarmRole->RoleID)->name);
            $RoleDOMNode->setAttribute('alias', $DBFarmRole->Alias);
            $RoleDOMNode->setAttribute('id', $DBFarmRole->ID);
            $RoleDOMNode->setAttribute('role-id', $DBFarmRole->RoleID);

            $HostsDomNode = $ResponseDOMDocument->createElement('hosts');
            $RoleDOMNode->appendChild($HostsDomNode);

            // List instances (hosts)
            $serversSql = "SELECT server_id FROM servers WHERE farm_roleid=?";
            $serversArgs = array($farm_role['id'], SERVER_STATUS::RUNNING);

            if ($this->GetArg("showInitServers")) {
                $serversSql .= " AND status IN (?,?)";
                $serversArgs[] = SERVER_STATUS::INIT;
            } else {
                $serversSql .= " AND status=?";
            }

            $servers = $this->DB->GetAll($serversSql, $serversArgs);

            // Add hosts to response
            if (count($servers) > 0)
            {
                foreach ($servers as $server)
                {
                    $DBServer = DBServer::LoadByID($server['server_id']);

                    $HostDOMNode = $ResponseDOMDocument->createElement("host");
                    $HostDOMNode->setAttribute('internal-ip', $DBServer->localIp);
                    $HostDOMNode->setAttribute('external-ip', $DBServer->remoteIp);
                    $HostDOMNode->setAttribute('index', $DBServer->index);
                    $HostDOMNode->setAttribute('status', $DBServer->status);
                    $HostDOMNode->setAttribute('cloud-location', $DBServer->GetCloudLocation());
                    $HostDOMNode->setAttribute('cloud-location-zone', $DBServer->cloudLocationZone);
                    $HostDOMNode->setAttribute('hostname', $DBServer->GetProperty(Scalr_Role_Behavior::SERVER_BASE_HOSTNAME));

                    if ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB))
                    {
                        $HostDOMNode->setAttribute('replica-set-index', (int)$DBServer->GetProperty(Scalr_Role_Behavior_MongoDB::SERVER_REPLICA_SET_INDEX));
                        $HostDOMNode->setAttribute('shard-index', (int)$DBServer->GetProperty(Scalr_Role_Behavior_MongoDB::SERVER_SHARD_INDEX));
                    }

                    if ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL))
                        $HostDOMNode->setAttribute('replication-master', (int)$DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER));

                    if ($DBFarmRole->GetRoleObject()->getDbMsrBehavior())
                        $HostDOMNode->setAttribute('replication-master', (int)$DBServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER));

                    $HostsDomNode->appendChild($HostDOMNode);
                }
            }

            // Add role node to roles node
            $RolesDOMNode->appendChild($RoleDOMNode);
        }

        return $ResponseDOMDocument;
    }
}
