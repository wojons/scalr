<?php

use Scalr\Model\Entity;
use Scalr\DataType\ScopeInterface;

class ScalrEnvironment20120701 extends ScalrEnvironment20120417
{
    public function GetGlobalConfig()
    {
        $ResponseDOMDocument = $this->CreateResponse();
        $configNode = $ResponseDOMDocument->createElement("settings");

        $config = array(
            'dns.static.endpoint' => \Scalr::config('scalr.dns.static.domain_name'),
            'scalr.version'       => SCALR_VERSION,
            'scalr.id'            => SCALR_ID
        );

        foreach ($config as $key => $value) {
            $settingNode = $ResponseDOMDocument->createElement("setting", $value);
            $settingNode->setAttribute("key", $key);
            $configNode->appendChild($settingNode);
        }


        $ResponseDOMDocument->documentElement->appendChild($configNode);
        return $ResponseDOMDocument;
    }

    public function SetGlobalVariable()
    {
        $scope = $this->GetArg("scope");
        $paramName = $this->GetArg("param-name");
        $paramValue = $this->GetArg("param-value");
        $final = (int)$this->GetArg("flag-final");

        if ($scope != ScopeInterface::SCOPE_SERVER && $scope != ScopeInterface::SCOPE_FARMROLE && $scope != ScopeInterface::SCOPE_FARM)
        	throw new Exception("query-env allows you to set global variables only on server/farmrole/farm scopes");

        $globalVariables = new Scalr_Scripting_GlobalVariables($this->DBServer->clientId, $this->DBServer->envId, $scope);
        $globalVariables->setValues(
            array(array(
                'name' 	=> $paramName,
                'value'	=> $paramValue,
                'flagFinal' => $final,
                'flagRequired' => 0
            )),
            $this->DBServer->GetFarmRoleObject()->RoleID,
            $this->DBServer->farmId,
            $this->DBServer->farmRoleId,
        	$this->DBServer->serverId
        );

        $ResponseDOMDocument = $this->CreateResponse();
        $configNode = $ResponseDOMDocument->createElement("variables");

        $settingNode = $ResponseDOMDocument->createElement("variable", htmlspecialchars($paramValue));
        $settingNode->setAttribute("name", $paramName);
        $configNode->appendChild($settingNode);

        $ResponseDOMDocument->documentElement->appendChild($configNode);
        return $ResponseDOMDocument;
    }

    public function ListGlobalVariables()
    {
        $ResponseDOMDocument = $this->CreateResponse();
        $configNode = $ResponseDOMDocument->createElement("variables");

        $globalVariables = new Scalr_Scripting_GlobalVariables($this->DBServer->clientId, $this->DBServer->envId, ScopeInterface::SCOPE_SERVER);
        $vars = $globalVariables->listVariables($this->DBServer->GetFarmRoleObject()->RoleID, $this->DBServer->farmId, $this->DBServer->farmRoleId, $this->DBServer->serverId);
        foreach ($vars as $key => $value) {
            $settingNode = $ResponseDOMDocument->createElement("variable");

            if (preg_match("/[\<\>\&]+/", $value['value']))
                $valueEl = $ResponseDOMDocument->createCDATASection($value['value']);
            else
                $valueEl = $ResponseDOMDocument->createTextNode($value['value']);

            $settingNode->appendChild($valueEl);
            $settingNode->setAttribute("name", $value['name']);
            $settingNode->setAttribute("private", $value['private']);
            $configNode->appendChild($settingNode);
        }

        $formats = \Scalr::config("scalr.system.global_variables.format");

        foreach ($this->DBServer->GetScriptingVars() as $name => $value) {
            $name = "SCALR_".strtoupper($name);
            $value = trim($value);

            if (isset($formats[$name]))
               $value = @sprintf($formats[$name], $value);

            $settingNode = $ResponseDOMDocument->createElement("variable");

            if (preg_match("/[\<\>\&]+/", $value))
                $valueEl = $ResponseDOMDocument->createCDATASection($value);
            else
                $valueEl = $ResponseDOMDocument->createTextNode($value);

            $settingNode->appendChild($valueEl);
            $settingNode->setAttribute("name", $name);
            $configNode->appendChild($settingNode);
        }

        $ResponseDOMDocument->documentElement->appendChild($configNode);
        return $ResponseDOMDocument;
    }

    public function ListFarmRoleParams()
    {
        $farmRoleId = $this->GetArg("farm-role-id");
        if (!$farmRoleId)
            throw new Exception("'farm-role-id' required");

        $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
        if ($dbFarmRole->FarmID != $this->DBServer->farmId)
            throw new Exception("You can request this information ONLY for roles within server farm");

        $ResponseDOMDocument = $this->CreateResponse();

        // Base configuration
        if ($this->DBServer->farmRoleId == $farmRoleId) {
            $data = Scalr_Role_Behavior::loadByName(ROLE_BEHAVIORS::BASE)->getBaseConfiguration($this->DBServer);

            foreach ((array)$data as $k => $v) {
                $bodyEl = $this->serialize($v, $k, $ResponseDOMDocument);
                $ResponseDOMDocument->documentElement->appendChild($bodyEl);
            }
        }

        $role = $dbFarmRole->GetRoleObject();
        $behaviors = $role->getBehaviors();
        foreach ($behaviors as $behavior) {
            $data = null;

            if ($behavior == ROLE_BEHAVIORS::MONGODB || $behavior == ROLE_BEHAVIORS::CHEF || $behavior == ROLE_BEHAVIORS::HAPROXY ||
                $behavior == ROLE_BEHAVIORS::NGINX || $behavior == ROLE_BEHAVIORS::RABBITMQ || $behavior == ROLE_BEHAVIORS::APACHE ||
                $behavior == ROLE_BEHAVIORS::VPC_ROUTER) {
                $data = Scalr_Role_Behavior::loadByName($behavior)->getConfiguration($this->DBServer);
            }

            if ($data === null) {
                if ($behavior == ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER) {
                    $data = new stdClass();
                    $data->version = $dbFarmRole->GetSetting(Scalr_Role_Behavior_CfCloudController::ROLE_VERSION);
                }
                else if ($behavior == ROLE_BEHAVIORS::MYSQL) {
                    $data = new stdClass();
                    $data->logFile = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_LOG_FILE);
                    $data->logPos = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_LOG_POS);
                    $data->rootPassword = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_ROOT_PASSWORD);
                    $data->replPassword = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_REPL_PASSWORD);
                    $data->statPassword = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_STAT_PASSWORD);
                    $data->replicationMaster = (int)$this->DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER);
                    //TODO: Storage

                } else {
                    try {
                        $dbMsrInfo = Scalr_Db_Msr_Info::init($dbFarmRole, $this->DBServer, $behavior);
                        $data = $dbMsrInfo->getMessageProperties();
                    } catch (Exception $e) {
                    }
                }
            }

            if ($data) {
                $bodyEl = $this->serialize($data, $behavior, $ResponseDOMDocument);
                $ResponseDOMDocument->documentElement->appendChild($bodyEl);
            }
        }

        return $ResponseDOMDocument;
    }

    private function serialize($object, $behavior, $doc)
    {
        $this->debugObject->{$behavior} = $object;

        $bodyEl = $doc->createElement($behavior);
        $body = array();
        if (is_object($object)) {
            foreach (get_object_vars($object) as $k => $v) {
                $body[$k] = $v;
            }
        } else {
            $body = $object;
        }

        $this->walkSerialize($body, $bodyEl, $doc);

        return $bodyEl;
    }

    private function walkSerialize($value, $el, $doc)
    {
        if (is_array($value) || is_object($value)) {
            if (is_array($value) && array_keys($value) === range(0, count($value)-1)) {
                // Numeric indexes array
                foreach ($value as $v) {
                    $itemEl = $doc->createElement("item");
                    $el->appendChild($itemEl);
                    $this->walkSerialize($v, $itemEl, $doc);
                }
            } else {
                // Assoc arrays and objects
                foreach ($value as $k => $v) {
                    if (!stristr($k, ":")) {
                        $itemEl = $doc->createElement($this->under_scope($k));
                        $el->appendChild($itemEl);
                        $this->walkSerialize($v, $itemEl, $doc);
                    }
                }
            }
        } else {
            if (preg_match("/[\<\>\&]+/", $value)) {
                $valueEl = $doc->createCDATASection($value);
            } else {
                $valueEl = $doc->createTextNode($value);
            }
            $el->appendChild($valueEl);
        }
    }

    private function under_scope ($name)
    {
        if (preg_match("/^[A-Z]+$/", $name))
            return $name;

        $parts = preg_split("/[A-Z]/", $name, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $ret = "";
        foreach ($parts as $part) {
            if ($part[1]) {
                $ret .= "_" . strtolower($name{$part[1]-1});
            }
            $ret .= $part[0];
        }
        return $ret;
    }
}
