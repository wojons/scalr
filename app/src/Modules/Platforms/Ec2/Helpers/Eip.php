<?php

	class Modules_Platforms_Ec2_Helpers_Eip
	{
		public static function farmValidateRoleSettings($settings, $rolename)
		{
			
		}
		
		private static function associateIpAddress(DBServer $dbServer, $ipAddress, $AmazonEC2Client) {
			$assign_retries = 1;
			$retval = false;
			while (true)
			{
				try
				{
					// Associate elastic ip address with instance
					$AmazonEC2Client->AssociateAddress(
						$dbServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID), 
						$ipAddress
					);
					
					$retval = true;
					break;
				}
				catch(Exception $e)
				{
					if (!stristr($e->getMessage(), "does not belong to you") || $assign_retries == 3)
						throw new Exception($e->getMessage());
					else
					{
						// Waiting...
						Logger::getLogger(__CLASS__)->debug(_("Waiting 2 seconds..."));
						sleep(2);
						$assign_retries++;
						continue;
					}
				}
				
				break;
			}
			
			return $retval;
		}
		
		public static function farmUpdateRoleSettings(DBFarmRole $DBFarmRole, $oldSettings, $newSettings)
		{
			$db = Core::GetDBInstance();
			
			$DBFarm = $DBFarmRole->GetFarmObject();
			
			$DBFarmRole->SetSetting(DBFarmRole::SETTING_AWS_ELASIC_IPS_MAP, null);
			
			$AmazonEC2Client = Scalr_Service_Cloud_Aws::newEc2(
				$DBFarmRole->CloudLocation, 
				$DBFarm->GetEnvironmentObject()->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY), 
				$DBFarm->GetEnvironmentObject()->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE)
			);
			
			// Disassociate IP addresses if checkbox was unchecked
			if (!$newSettings[DBFarmRole::SETTING_AWS_USE_ELASIC_IPS] && $oldSettings[DBFarmRole::SETTING_AWS_USE_ELASIC_IPS]) {
				$eips = $db->Execute("SELECT * FROM elastic_ips WHERE farm_roleid = ?", array($DBFarmRole->ID));
				while ($eip = $eips->FetchRow()) {
					try {
						$AmazonEC2Client->DisassociateAddress($eip['ipaddress']);
					} catch (Exception $e) {}
				}
				
				$db->Execute("DELETE FROM elastic_ips WHERE farm_roleid = ?", array($DBFarmRole->ID));
			}
			
			
			//TODO: Handle situation when tab was not opened, but max instances setting was changed.
			
			if ($newSettings[DBFarmRole::SETTING_AWS_ELASIC_IPS_MAP] && $newSettings[DBFarmRole::SETTING_AWS_USE_ELASIC_IPS]) {
				$map = explode(";", $newSettings[DBFarmRole::SETTING_AWS_ELASIC_IPS_MAP]);
				foreach ($map as $ipconfig) {
					list ($serverIndex, $ipAddress) = explode("=", $ipconfig);
					
					if (!$serverIndex)
						continue;
					
					try {
						$dbServer = DBServer::LoadByFarmRoleIDAndIndex($DBFarmRole->ID, $serverIndex);
					} catch (Exception $e) {}
					
					// Allocate new IP if needed
					if (!$ipAddress || $ipAddress == '0.0.0.0') {
						if ($dbServer) {
							$address = $AmazonEC2Client->AllocateAddress();
							$ipAddress = $address->publicIp;
						} else
							continue;
					}
					
					// Remove old association
					$db->Execute("DELETE FROM elastic_ips WHERE farm_roleid = ? AND instance_index=?", array($DBFarmRole->ID, $serverIndex));
					
					// Associate IP with server in our db
					$db->Execute("INSERT INTO elastic_ips SET env_id=?, farmid=?, farm_roleid=?, ipaddress=?, state='0', instance_id='', clientid=?, instance_index=?",
						array($DBFarm->EnvID, $DBFarmRole->FarmID, $DBFarmRole->ID, $ipAddress, $DBFarm->ClientID, $serverIndex)
					);
					
					
					// Associate IP on AWS with running server
					try {
						$dbServer = DBServer::LoadByFarmRoleIDAndIndex($DBFarmRole->ID, $serverIndex);
						
						$db->Execute("UPDATE elastic_ips SET state='1', server_id = ? WHERE ipaddress = ?", array(
							$dbServer->serverId, $ipAddress
						));
						
						$update = false;
						if ($dbServer->remoteIp != $ipAddress) {
							if ($dbServer && $dbServer->status == SERVER_STATUS::RUNNING) {
								$fireEvent = self::associateIpAddress($dbServer, $ipAddress, $AmazonEC2Client);
							}
						}
						
						if ($fireEvent) {
							$event = new IPAddressChangedEvent($dbServer, $ipAddress, $dbServer->localIp);
							Scalr::FireEvent($dbServer->farmId, $event);
						}
					} catch (Exception $e) {}
				}
			}
		}
	}

?>