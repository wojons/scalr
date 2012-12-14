<?php
class Scalr_Scripting_Manager
{
	private static function makeSeed()
	{
		list($usec, $sec) = explode(' ', microtime());
		return (float) $sec + ((float) $usec * 100000);
	}	
	
	public static function generateEventName($prefix)
	{
		mt_srand(self::makeSeed());
		return "{$prefix}-" . date("YmdHis") . '-' . mt_rand(100000,999999);
	}
	
	public static function extendMessage(Scalr_Messaging_Msg $message, DBServer $targetDbServer, Event $event = null)
	{
		$db = Core::GetDBInstance();
		
		switch (get_class($message))
		{
			case "Scalr_Messaging_Msg_ExecScript":
				$scriptSettings = $db->GetAll("SELECT * FROM farm_role_scripts WHERE event_name=?", 
				array(
					$message->eventName
				));	
				break;
				
			/*TODO:
			case "Scalr_Messaging_Msg_HostInit":
				$scriptSettings = $db->GetAll("SELECT * FROM farm_role_scripts WHERE event_name=? AND farmid=? ORDER BY order_index ASC", 
				array(
					$event->GetName(),
					$event->DBServer->farmId
				));
				break;
			*/
		}
		$scripts = array();
		foreach ($scriptSettings as $settings) {
			$script = self::prepareScript($settings, $targetDbServer, $event);
			if (!$script)
				continue;
			
			$itm = new stdClass();
			$itm->asynchronous = ($script['issync'] == 1) ? '0' : '1';
			$itm->timeout = $script['timeout'];
			$itm->name = $script['name'];
			$itm->body = $script['body'];
			$scripts[] = $itm;	
		}
		
		$message->scripts = $scripts;
		
		return $message;
	}
	
	private static function prepareScript($scriptSettings, DBServer $targetServer, Event $event = null)
	{
		$db = Core::GetDBInstance();
		
		//$scriptSettings['version'] = (int)$scriptSettings['version'];
		
		if ($scriptSettings['version'] == 'latest' || (int)$scriptSettings['version'] == -1) {
			$version = (int)$db->GetOne("SELECT MAX(revision) FROM script_revisions WHERE scriptid=?",
				array($scriptSettings['scriptid'])
			);
		}
		else
			$version = (int)$scriptSettings['version'];
			
		$template = $db->GetRow("SELECT name,id FROM scripts WHERE id=?", 
			array($scriptSettings['scriptid'])
		);
		$template['timeout'] = $scriptSettings['timeout'];
		$template['issync'] = $scriptSettings['issync'];
		
		
		$template['body'] = $db->GetOne("SELECT script FROM script_revisions WHERE scriptid=? AND revision=?",
			array($template['id'], $version)
		);
		
		if (!$template['body'])
			return false;
			
		$params = array_merge($targetServer->GetScriptingVars(), (array)unserialize($scriptSettings['params']));

		if ($event) {
			$eventServer = $event->DBServer;
			foreach ($eventServer->GetScriptingVars() as $k => $v) {
				$params["event_{$k}"] = $v;
			}
			
			foreach ($event->GetScriptingVars() as $k=>$v)
				$params[$k] = $event->{$v};
			
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
		
		// Generate script contents
		$script_contents = str_replace($keys, $values, $template['body']);
		$template['body'] = str_replace('\%', "%", $script_contents);
		$template['name'] = preg_replace("/[^A-Za-z0-9]+/", "_", $template['name']);
		
		return $template;
	}
	
	public static function getEventScriptList(Event $event, DBServer $eventServer, DBServer $targetServer) 
	{
		$db = Core::GetDBInstance();
		
		$roleScripts = $db->GetAll("SELECT * FROM role_scripts WHERE event_name=? AND role_id=? ORDER BY order_index ASC", array($event->GetName(), $eventServer->roleId));
		
		$scripts = $db->GetAll("SELECT * FROM farm_role_scripts WHERE event_name=? AND farmid=? ORDER BY order_index ASC", array($event->GetName(), $eventServer->farmId));
		
		foreach ($roleScripts as $script) {
			
			$params = $db->GetOne("SELECT params FROM farm_role_scripting_params WHERE farm_role_id = ? AND role_script_id = ? AND farm_role_script_id = '0'", array(
				$eventServer->farmRoleId,
				$script['id']
			));
			if ($params)
				$script['params'] = $params;
			
			$scripts[] = array(
			 "id" => "r{$script['id']}",
			 "scriptid" => $script['script_id'], 
			 "params" => $script['params'],
			 "event_name" => $script['event_name'], 
			 "target" => $script['target'], 
			 "version" => $script['version'], 
			 "timeout" => $script['timeout'], 
			 "issync" => $script['issync'], 
			 "order_index" => $script['order_index'],
			 "type"   => "role" 
			);
		}

		$retval = array();
		foreach ($scripts as $scriptSettings) {
			$scriptSettings['order_index'] = (float)$scriptSettings['order_index'];
			
			if ($scriptSettings['target'] == SCRIPTING_TARGET::INSTANCE && $eventServer->serverId != $targetServer->serverId)
				continue;
				
			if ($scriptSettings['target'] == SCRIPTING_TARGET::ROLE && $eventServer->farmRoleId != $targetServer->farmRoleId)
				continue;

			if ($scriptSettings['type'] != 'role' && $scriptSettings['target'] == SCRIPTING_TARGET::FARM && $eventServer->farmRoleId != $scriptSettings['farm_roleid'])
				continue;
				
			if ($scriptSettings['type'] != 'role' && $scriptSettings['target'] != SCRIPTING_TARGET::FARM && $targetServer->farmRoleId != $scriptSettings['farm_roleid'])
				continue;
				
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