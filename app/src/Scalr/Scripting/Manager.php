<?php

use Scalr\Model\Entity\Script;

class Scalr_Scripting_Manager
{
    private static $BUILTIN_VARIABLES_LOADED = false;
    private static $BUILTIN_VARIABLES = array(
        "image_id" 		=> 1,
        "role_name" 	=> 1,
        "isdbmaster" 	=> 1,
        "farm_id"		=> 1,
        "farm_name"		=> 1,
        "behaviors"		=> 1,
        "server_id"		=> 1,
        "env_id"		=> 1,
        "env_name"		=> 1,
        "farm_role_id"  => 1,
        "event_name"	=> 1,
        "cloud_location"=> 1,

        //TODO: Remove this vars
        "ami_id" 		=> 1,
        "instance_index"=> 1,
        "region" 		=> 1,
        "avail_zone" 	=> 1,
        "external_ip" 	=> 1,
        "internal_ip" 	=> 1,
        "instance_id" 	=> 1
    );

    const ORCHESTRATION_SCRIPT_TYPE_SCALR = 'scalr';
    const ORCHESTRATION_SCRIPT_TYPE_LOCAL = 'local';
    const ORCHESTRATION_SCRIPT_TYPE_CHEF = 'chef';

    public static function getScriptingBuiltinVariables()
    {
        if (!self::$BUILTIN_VARIABLES_LOADED)
        {
            $ReflectEVENT_TYPE = new ReflectionClass("EVENT_TYPE");
            $event_types = $ReflectEVENT_TYPE->getConstants();
            foreach ($event_types as $event_type)
            {
                if (class_exists("{$event_type}Event"))
                {
                    $ReflectClass = new ReflectionClass("{$event_type}Event");
                    $retval = $ReflectClass->getMethod("GetScriptingVars")->invoke(null);
                    if (!empty($retval))
                    {
                        foreach ($retval as $k=>$v)
                        {
                            if (!self::$BUILTIN_VARIABLES[$k])
                            {
                                self::$BUILTIN_VARIABLES[$k] = array(
                                    "PropName"	=> $v,
                                    "EventName" => "{$event_type}"
                                );
                            }
                            else
                            {
                                if (!is_array(self::$BUILTIN_VARIABLES[$k]['EventName']))
                                    $events = array(self::$BUILTIN_VARIABLES[$k]['EventName']);
                                else
                                    $events = self::$BUILTIN_VARIABLES[$k]['EventName'];

                                $events[] = $event_type;

                                self::$BUILTIN_VARIABLES[$k] = array(
                                    "PropName"	=> $v,
                                    "EventName" => $events
                                );
                            }
                        }
                    }
                }
            }

            foreach (self::$BUILTIN_VARIABLES as $k=>$v)
                self::$BUILTIN_VARIABLES["event_{$k}"] = $v;

            self::$BUILTIN_VARIABLES_LOADED = true;
        }

        return self::$BUILTIN_VARIABLES;
    }


    private static function makeSeed()
    {
        list($usec, $sec) = explode(' ', microtime());
        return (float) $sec + ((float) $usec * 100000);
    }

    public static function extendMessage(Scalr_Messaging_Msg $message, Event $event, DBServer $eventServer, DBServer $targetServer)
    {
        $db = \Scalr::getDb();

        $retval = array();

        try {
            $scripts = self::getEventScriptList($event, $eventServer, $targetServer);
            if (count($scripts) > 0) {
                foreach ($scripts as $script) {
                    $itm = new stdClass();
                    // Script
                    $itm->asynchronous = ($script['issync'] == 1) ? '0' : '1';
                    $itm->timeout = $script['timeout'];

                    if ($script['type'] == self::ORCHESTRATION_SCRIPT_TYPE_SCALR) {
                        $itm->name = $script['name'];
                        $itm->body = $script['body'];
                    } elseif ($script['type'] == self::ORCHESTRATION_SCRIPT_TYPE_LOCAL) {
                        $itm->name = "local-".crc32($script['path']).mt_rand(100, 999);
                        $itm->path = $script['path'];
                    } elseif ($script['type'] == self::ORCHESTRATION_SCRIPT_TYPE_CHEF) {
                        $itm->name = "chef-".crc32($script['path']).mt_rand(100, 999);
                        $itm->chef = $script['chef'];
                    }

                    if ($script['run_as'])
                        $itm->runAs = $script['run_as'];

                    $itm->executionId = $script['execution_id'];

                    $retval[] = $itm;
                }
            }
        } catch (Exception $e) {
            $scriptingError = $e->getMessage();
        }

        $message->scripts = $retval;
        $message->eventId = $event->GetEventID();
        $message->debugScriptingCount = count($scripts);
        $message->debugScriptingError = $scriptingError;
        $message->setGlobalVariables($targetServer, true, $event);

        return $message;
    }

    public static function prepareScript($scriptSettings, DBServer $targetServer, Event $event = null)
    {
        $db = \Scalr::getDb();

        $template = array(
            'type'    => $scriptSettings['type'],
            'timeout' => $scriptSettings['timeout'],
            'issync'  => $scriptSettings['issync'],
            'run_as'  => $scriptSettings['run_as'],
            'execution_id' => Scalr::GenerateUID()
        );

        if ($scriptSettings['type'] == self::ORCHESTRATION_SCRIPT_TYPE_SCALR) {
            /* @var Script $script */
            $script = Script::findPk($scriptSettings['scriptid']);
            if (! $script)
                return false;
            // TODO: validate permission to access script ?

            if ($scriptSettings['version'] == 'latest' || (int)$scriptSettings['version'] == -1) {
                $version = $script->getLatestVersion();
            }
            else {
                $version = $script->getVersion((int)$scriptSettings['version']);
            }

            if (! $version)
                return false;

            $template['name'] = $script->name;
            $template['id'] = $script->id;
            $template['body'] = $version->content;

            $scriptParams = (array) $version->variables; // variables could be null
            foreach ($scriptParams as &$val)
                $val = "";

            $params = array_merge($scriptParams, $targetServer->GetScriptingVars(), (array)unserialize($scriptSettings['params']));

            if ($event) {
                $eventServer = $event->DBServer;
                foreach ($eventServer->GetScriptingVars() as $k => $v) {
                    $params["event_{$k}"] = $v;
                }

                foreach ($event->GetScriptingVars() as $k=>$v)
                    $params[$k] = $event->{$v};

                if (isset($event->params) && is_array($event->params))
                    foreach ($event->params as $k=>$v)
                        $params[$k] = $v;

                $params['event_name'] = $event->GetName();
            }

            if ($event instanceof CustomEvent) {
                if (count($event->params) > 0)
                    $params = array_merge($params, $event->params);
            }

            // Prepare keys array and array with values for replacement in script
            $keys = array_keys($params);
            $f = create_function('$item', 'return "%".$item."%";');
            $keys = array_map($f, $keys);
            $values = array_values($params);
            $script_contents = str_replace($keys, $values, $template['body']);
            $template['body'] = str_replace('\%', "%", $script_contents);

            // Generate script contents
            $template['name'] = preg_replace("/[^A-Za-z0-9]+/", "_", $template['name']);
        } elseif ($scriptSettings['type'] == self::ORCHESTRATION_SCRIPT_TYPE_LOCAL) {
            $template['path'] = $targetServer->applyGlobalVarsToValue($scriptSettings['script_path']);
        } elseif ($scriptSettings['type'] == self::ORCHESTRATION_SCRIPT_TYPE_CHEF) {
            $chef = new stdClass();
            $chefSettings = (array)unserialize($scriptSettings['params']);

            if ($chefSettings['chef.cookbook_url'])
                $chef->cookbookUrl = $chefSettings['chef.cookbook_url'];

            if ($chefSettings['chef.cookbook_url_type'])
                $chef->cookbookUrlType = $chefSettings['chef.cookbook_url_type'];

            if ($chefSettings['chef.relative_path'])
                $chef->relativePath = $chefSettings['chef.relative_path'];

            if ($chefSettings['chef.ssh_private_key'])
                $chef->sshPrivateKey = $chefSettings['chef.ssh_private_key'];

            $chef->runList = $chefSettings['chef.runlist'];
            $chef->jsonAttributes = $chefSettings['chef.attributes'];

            $template['chef'] = $chef;
        }

        return $template;
    }

    public static function getEventScriptList(Event $event, DBServer $eventServer, DBServer $targetServer)
    {
        $db = \Scalr::getDb();

        $accountScripts = $db->GetAll("SELECT * FROM account_scripts WHERE (event_name=? OR event_name='*') AND account_id=?", array($event->GetName(), $eventServer->clientId));

        $roleScripts = $db->GetAll("SELECT * FROM role_scripts WHERE (event_name=? OR event_name='*') AND role_id=?", array($event->GetName(), $eventServer->roleId));

        $scripts = $db->GetAll("SELECT *, `script_type` as `type` FROM farm_role_scripts WHERE (event_name=? OR event_name='*') AND farmid=?", array($event->GetName(), $eventServer->farmId));

        foreach ($accountScripts as $script) {
            $scripts[] = array(
                "id" => "a{$script['id']}",
                "type" => $script['script_type'],
                "scriptid" => $script['script_id'],
                "params" => $script['params'],
                "event_name" => $event->GetName(),
                "target" => $script['target'],
                "version" => $script['version'],
                "timeout" => $script['timeout'],
                "issync" => $script['issync'],
                "order_index" => $script['order_index'],
                "scope"   => "account",
                'script_path' => $script['script_path'],
                'run_as' => $script['run_as'],
                'script_type' => $script['script_type']
            );
        }

        foreach ($roleScripts as $script) {
            $params = $db->GetOne("SELECT params FROM farm_role_scripting_params WHERE farm_role_id = ? AND `hash` = ? AND farm_role_script_id = '0' LIMIT 1", array(
                $eventServer->farmRoleId,
                $script['hash']
            ));
            if ($params)
                $script['params'] = $params;

            $scripts[] = array(
             "id" => "r{$script['id']}",
             "scriptid" => $script['script_id'],
             "type" => $script['script_type'],
             "params" => $script['params'],
             "event_name" => $event->GetName(),
             "target" => $script['target'],
             "version" => $script['version'],
             "timeout" => $script['timeout'],
             "issync" => $script['issync'],
             "order_index" => $script['order_index'],
             "scope"   => "role",
             'script_path' => $script['script_path'],
             'run_as' => $script['run_as'],
             'script_type' => $script['script_type']
            );
        }

        $retval = array();
        foreach ($scripts as $scriptSettings) {
            $scriptSettings['order_index'] = (float)$scriptSettings['order_index'];

            // If target set to that instance only
            if ($scriptSettings['target'] == Script::TARGET_INSTANCE && $eventServer->serverId != $targetServer->serverId)
                continue;

            // If target set to all instances in specific role
            if ($scriptSettings['target'] == Script::TARGET_ROLE && $eventServer->farmRoleId != $targetServer->farmRoleId)
                continue;

            if (!$scriptSettings['scope']) {
                // Validate that event was triggered on the same farmRoleId as script
                if ($eventServer->farmRoleId != $scriptSettings['farm_roleid'])
                    continue;

                // Validate that target server has the same farmRoleId as event server with target ROLE
                if ($scriptSettings['target'] == Script::TARGET_ROLE && $targetServer->farmRoleId != $scriptSettings['farm_roleid'])
                    continue;
            }

            if ($scriptSettings['target'] == Script::TARGET_ROLES || $scriptSettings['target'] == Script::TARGET_BEHAVIORS) {

                if ($scriptSettings['scope'] != 'role')
                    $targets = $db->GetAll("SELECT * FROM farm_role_scripting_targets WHERE farm_role_script_id = ?", array($scriptSettings['id']));
                else
                    $targets = array();

                $execute = false;
                foreach ($targets as $target) {
                    switch ($target['target_type']) {
                        case "farmrole":
                            if ($targetServer->farmRoleId == $target['target'])
                                $execute = true;
                            break;
                        case "behavior":
                            if ($targetServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior($target['target']))
                                $execute = true;
                            break;
                    }
                }

                if (!$execute)
                    continue;
            }

            if ($scriptSettings['target'] == "" || $scriptSettings['id'] == "")
                continue;

            $script = self::prepareScript($scriptSettings, $targetServer, $event);

            if ($script) {
                while (true) {
                    $index = (string)$scriptSettings['order_index'];
                    if (!$retval[$index]) {
                        $retval[$index] = $script;
                        break;
                    }
                    else
                        $scriptSettings['order_index'] += 0.01;
                }
            }
        }

        @ksort($retval);

        return $retval;
    }
}