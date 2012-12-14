<?php

	class Modules_Platforms_Cloudstack_Helpers_Cloudstack
	{   		
		public static function farmSave(DBFarm $DBFarm, array $roles)
		{
			foreach ($roles as $DBFarmRole)
			{
				if (!in_array($DBFarmRole->Platform, array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::UCLOUD)))
					continue;
				
				$location = $DBFarmRole->CloudLocation;
				
				$platform = PlatformFactory::NewPlatform($DBFarmRole->Platform);
				
				$cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
					$platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $DBFarm->GetEnvironmentObject()),
					$platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $DBFarm->GetEnvironmentObject()),
					$platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $DBFarm->GetEnvironmentObject()),
					$DBFarmRole->Platform
				);
				
				$sshKey = Scalr_SshKey::init();
				if (!$sshKey->loadGlobalByFarmId($DBFarm->ID, $location, $DBFarmRole->Platform))
				{
					$key_name = "FARM-{$DBFarm->ID}-".SCALR_ID;
					
					$result = $cs->createSSHKeyPair($key_name);
					if ($result->keypair->privatekey)
					{	
						$sshKey->farmId = $DBFarm->ID;
						$sshKey->clientId = $DBFarm->ClientID;
						$sshKey->envId = $DBFarm->EnvID;
						$sshKey->type = Scalr_SshKey::TYPE_GLOBAL;
						$sshKey->cloudLocation = $location;
						$sshKey->cloudKeyName = $key_name;
						$sshKey->platform = $DBFarmRole->Platform;
						
						$sshKey->setPrivate($result->keypair->privatekey);
						$sshKey->setPublic($sshKey->generatePublicKey());
						
						$sshKey->save();
		            }
				}
				
				$networkId = $DBFarmRole->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_NETWORK_ID);
				$set = fasle;
				foreach ($cs->listNetworks("", "", "", $networkId) as $network) {
					if ($network->id == $networkId) {
						$DBFarmRole->SetSetting(DBFarmRole::SETTING_CLOUDSTACK_NETWORK_TYPE, $network->type);
						$set = true;
					}
				}
				
				if (!$set)
					throw new Exception("Unable to get GuestIPType for Network #{$networkId}. Please try again later or choose another network offering.");
			}
		}
		
		
		public static function farmValidateRoleSettings($settings, $rolename)
		{
			if (!$settings[DBFarmRole::SETTING_CLOUDSTACK_SERVICE_OFFERING_ID])
				throw new Exception(sprintf(_("Service offering for '%s' cloudstack role should be selected on 'Placement and type' tab"), $rolename));
				
			if (!$settings[DBFarmRole::SETTING_CLOUDSTACK_NETWORK_ID])
				throw new Exception(sprintf(_("Network offering for '%s' cloudstack role should be selected on 'Placement and type' tab"), $rolename));
		}
		
		public static function farmUpdateRoleSettings(DBFarmRole $DBFarmRole, $oldSettings, $newSettings)
		{
			
		}
	}

?>