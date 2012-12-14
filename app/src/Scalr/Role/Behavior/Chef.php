<?php
	class Scalr_Role_Behavior_Chef extends Scalr_Role_Behavior implements Scalr_Role_iBehavior
	{
		/** DBFarmRole settings **/
		const ROLE_CHEF_SERVER_ID		= 'chef.server_id';
		const ROLE_CHEF_BOOTSTRAP		= 'chef.bootstrap';
		const ROLE_CHEF_ROLE_NAME		= 'chef.role_name';
		const ROLE_CHEF_RUNLIST_ID		= 'chef.runlist_id';
		const ROLE_CHEF_ENVIRONMENT		= 'chef.environment';
		const ROLE_CHEF_ATTRIBUTES		= 'chef.attributes';
		const ROLE_CHEF_CHECKSUM		= 'chef.checksum';
		const ROLE_CHEF_NODENAME_TPL	= 'chef.node_name_tpl';
		
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
		
		public function onFarmSave(DBFarm $dbFarm, DBFarmRole $dbFarmRole)
		{
			try {
				$account = Scalr_Account::init()->loadById($dbFarm->ClientID);
				if (!$account->isFeatureEnabled(Scalr_Limits::FEATURE_CHEF)) {
					$dbFarmRole->ClearSettings("chef.");
					return false;
				}
				
				$db = Core::GetDBInstance();
				$useChefBootstrap = $dbFarmRole->GetSetting(self::ROLE_CHEF_BOOTSTRAP);
				$runListId = $dbFarmRole->GetSetting(self::ROLE_CHEF_RUNLIST_ID);
				$attributes = $dbFarmRole->GetSetting(self::ROLE_CHEF_ATTRIBUTES); 
				$checksum = $dbFarmRole->GetSetting(self::ROLE_CHEF_CHECKSUM);
				$chefRoleName = $dbFarmRole->GetSetting(self::ROLE_CHEF_ROLE_NAME);
				$chefServerId = $dbFarmRole->GetSetting(self::ROLE_CHEF_SERVER_ID);
				
				$defaultRoleName = "scalr-{$dbFarmRole->ID}";
				
				// Need to remove chef role if chef was disabled for current farmrole
				if (!$useChefBootstrap && $chefRoleName == $defaultRoleName) {
					$this->removeChefRole($chefServerId, $chefRoleName);
					$dbFarmRole->ClearSettings("chef.");
					return true;
				} elseif (!$useChefBootstrap) {
					$dbFarmRole->ClearSettings("chef.");
					return true;	
				}
				
				if ($chefRoleName != $defaultRoleName) {
					$runListId = null;
					$dbFarmRole->SetSetting(self::ROLE_CHEF_RUNLIST_ID, null);
				}
					
				if ($useChefBootstrap && $runListId)
				{
					$runListInfo = $this->db->GetRow("SELECT chef_server_id, runlist FROM services_chef_runlists WHERE id=?", array($runListId));	
					$newChefServerId = $runListInfo['chef_server_id'];
					if ($newChefServerId != $chefServerId && $chefServerId) {
						// Remove role from old server
						if ($chefRoleName == $defaultRoleName) {
							$this->removeChefRole($chefServerId, $chefRoleName);
							$createNew = true;
						}
					}
					
					$chefServerInfo = $this->db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefServerId));
					$chefServerInfo['auth_key'] = $this->getCrypto()->decrypt($chefServerInfo['auth_key'], $this->cryptoKey);
					
					$chefClient = Scalr_Service_Chef_Client::getChef($chefServerInfo['url'], $chefServerInfo['username'], trim($chefServerInfo['auth_key']));
					
					if (!$chefRoleName)	{
						$roleName = $defaultRoleName;
						$createNew = true;
					}
					else
						$roleName = $chefRoleName;
					
					$setSettings = false;
					
					if ($createNew) {
						$chefClient->createRole($roleName, $roleName, json_decode($runListInfo['runlist']), json_decode($attributes), $runListInfo['chef_environment']);
						$setSettings = true;					
					} else {
						if ($dbFarmRole->GetSetting(self::ROLE_CHEF_CHECKSUM) != md5("{$runListInfo['runlist']}.$attributes")) {
							$chefClient->updateRole($roleName, $roleName, json_decode($runListInfo['runlist']), json_decode($attributes), $runListInfo['chef_environment']);
							$setSettings = true;
						}
					}
					
					if ($setSettings) {
						$dbFarmRole->SetSetting(self::ROLE_CHEF_ROLE_NAME, $roleName);
						$dbFarmRole->SetSetting(self::ROLE_CHEF_CHECKSUM, md5("{$runListInfo['runlist']}.$attributes"));
					}
				}
			} catch (Exception $e) {
				throw new Exception("Chef settings error: {$e->getMessage()} ({$e->getTraceAsString()})");
			}
		}
		
		public function handleMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
		{
			if (!$message->chef)
				return;
			
			switch (get_class($message))
			{
				case "Scalr_Messaging_Msg_HostUp":
					$dbServer->SetProperty(self::SERVER_CHEF_NODENAME, $message->chef->nodeName);
					break;
			}
		}
		
		public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
		{
			$message = parent::extendMessage($message, $dbServer);
			
			$dbFarmRole = $dbServer->GetFarmRoleObject();
			$chefServerId = $dbFarmRole->GetSetting(self::ROLE_CHEF_SERVER_ID);
			$chefRoleName = $dbFarmRole->GetSetting(self::ROLE_CHEF_ROLE_NAME);
			$chefEnvironment = $dbFarmRole->GetSetting(self::ROLE_CHEF_ENVIRONMENT);
			if (!$chefServerId || !$chefRoleName)
				return $message;
			
			$chefServerInfo = $this->db->GetRow("SELECT * FROM services_chef_servers WHERE id=?", array($chefServerId));
			$chefServerInfo['v_auth_key'] = trim($this->getCrypto()->decrypt($chefServerInfo['v_auth_key'], $this->cryptoKey));
			
			$nodeNameTpl = $dbFarmRole->GetSetting(self::ROLE_CHEF_NODENAME_TPL);
			if ($nodeNameTpl) {
				$params = $dbServer->GetScriptingVars();
				$keys = array_keys($params);
				$f = create_function('$item', 'return "%".$item."%";');
				$keys = array_map($f, $keys);
				$values = array_values($params);
					
				
				$nodeName = str_replace($keys, $values, $nodeNameTpl);
			}
			
			switch (get_class($message))
			{
				case "Scalr_Messaging_Msg_HostInitResponse":
					
					$message->chef = new stdClass();
					$message->chef->serverUrl = $chefServerInfo['url'];
					$message->chef->role = $chefRoleName;
					$message->chef->validatorName = $chefServerInfo['v_username'];
					$message->chef->validatorKey = $chefServerInfo['v_auth_key'];
					$message->chef->jsonAttributes = $dbFarmRole->GetSetting(self::ROLE_CHEF_ATTRIBUTES);
					$message->chef->environment = $chefEnvironment;
					
					if ($nodeName)
						$message->chef->nodeName = $nodeName;
					
					break;
			}
			
			return $message;
		}
	}