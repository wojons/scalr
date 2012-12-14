<?php

	require_once dirname(__FILE__).'/../../../externals/google-api-php-client-r488/src/Google_Client.php';
	require_once dirname(__FILE__).'/../../../externals/google-api-php-client-r488/src/contrib/Google_ComputeService.php';

	class Modules_Platforms_GoogleCE implements IPlatformModule
	{
		/** Properties **/
		const CLIENT_ID 			= 'gce.client_id';
		const SERVICE_ACCOUNT_NAME	= 'gce.service_account_name';
		const KEY					= 'gce.key';
		const PROJECT_ID			= 'gce.project_id';
		const ACCESS_TOKEN			= 'gce.access_token';
		
		const RESOURCE_BASE_URL = 'https://www.googleapis.com/compute/v1beta12/projects/';
		
		private function getClient(Scalr_Environment $environment, $cloudLocation)
		{
			$client = new Google_Client();
			$client->setApplicationName("Scalr GCE");
			$client->setScopes(array('https://www.googleapis.com/auth/compute'));
			
			$key = base64_decode($environment->getPlatformConfigValue(self::KEY));
			$client->setAssertionCredentials(new Google_AssertionCredentials(
				$environment->getPlatformConfigValue(self::SERVICE_ACCOUNT_NAME),
				array('https://www.googleapis.com/auth/compute'),
				$key
			));
			
			$client->setUseObjects(true);
			$client->setClientId($environment->getPlatformConfigValue(self::CLIENT_ID));
			
			$gce = new Google_ComputeService($client);
			
			//**** Store access token ****//
			$jsonAccessToken = $environment->getPlatformConfigValue(self::ACCESS_TOKEN);
			$accessToken = @json_decode($jsonAccessToken);
			if ($accessToken && $accessToken->created+$accessToken->expires_in > time())
				$client->setAccessToken($jsonAccessToken);
			else {
				$gce->zones->listZones($environment->getPlatformConfigValue(self::PROJECT_ID));
				$token = $client->getAccessToken();
				$environment->setPlatformConfig(array(
					self::ACCESS_TOKEN => $token	
				));
			}
			
			return $gce;
		}
		
		public function __construct()
		{
			
		}
		
		public function getRoleBuilderBaseImages()
		{
			return array(
				'google/images/ubuntu-12-04-v20120621'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'us-central1-a', 'architecture' => 'x86_64'),
				'google/images/centos-6-2-v20120621'	=> array('name' => 'CentOS 6.2', 'os_dist' => 'centos', 'location' => 'us-central1-a', 'architecture' => 'x86_64')
			);
		}
		
		public function getLocations()
		{
			return array(
				'us-central1-a' => 'GCE / us-central1-a',
				'us-central2-a'	=> 'GCE / us-central2-a'		
			);
		}
		
		public function getPropsList()
		{
			return array(
				self::CLIENT_ID	=> 'Client ID',
				self::SERVICE_ACCOUNT_NAME	=> 'E-mail',
				self::KEY	=> "Key",
				self::PROJECT_ID => "Project ID"
			);
		}
		
		public function GetServerCloudLocation(DBServer $DBServer)
		{
			return $DBServer->GetProperty(GCE_SERVER_PROPERTIES::CLOUD_LOCATION);
		}
		
		public function GetServerID(DBServer $DBServer)
		{
			return $DBServer->GetProperty(GCE_SERVER_PROPERTIES::SERVER_NAME);
		}
		
		public function GetServerFlavor(DBServer $DBServer)
		{
			return $DBServer->GetProperty(GCE_SERVER_PROPERTIES::MACHINE_TYPE);
		}
		
		public function IsServerExists(DBServer $DBServer, $debug = false)
		{
			return in_array(
				$DBServer->serverId,
				array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(GCE_SERVER_PROPERTIES::CLOUD_LOCATION), true))
			);
		}
		
		public function GetServerIPAddresses(DBServer $DBServer)
		{
			$gce = $this->getClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(GCE_SERVER_PROPERTIES::CLOUD_LOCATION));
				
			$result = $gce->instances->get(
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PROJECT_ID),
				$DBServer->serverId
			);
			
			$network = $result->getNetworkInterfaces();
			
			return array(
				'localIp'	=> $network[0]->networkIP,
				'remoteIp'	=> $network[0]->accessConfigs[0]->natIP
			);
		}
		
		public function GetServersList(Scalr_Environment $environment, $cloudLocation, $skipCache = false)
		{
			if (!$this->instancesListCache[$environment->id][$cloudLocation] || $skipCache)
			{
				$gce = $this->getClient($environment, $cloudLocation);
				$result = $gce->instances->listInstances($environment->getPlatformConfigValue(self::PROJECT_ID));
			
				foreach ($result->items as $server)
					$this->instancesListCache[$environment->id][$cloudLocation][$server->name] = $server->status;
			}
			 
			return $this->instancesListCache[$environment->id][$cloudLocation];
		}
		
		public function GetServerRealStatus(DBServer $DBServer)
		{
			$cloudLocation = $DBServer->GetProperty(GCE_SERVER_PROPERTIES::CLOUD_LOCATION);
			$environment = $DBServer->GetEnvironmentObject();
				
			$iid = $DBServer->serverId;
			if (!$iid) {
				$status = 'not-found';
			}
			elseif (!$this->instancesListCache[$environment->id][$cloudLocation][$iid])
			{
				$gce = $this->getClient($environment, $cloudLocation);
			
				try {
					$result = $gce->instances->get(
						$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PROJECT_ID),
						$DBServer->serverId
					);
					$status = $result->status;
				}
				catch(Exception $e)
				{
					if (stristr($e->getMessage(), "not found"))
						$status = 'not-found';
					else
						throw $e;
				}
			}
			else
			{
				$status = $this->instancesListCache[$environment->id][$cloudLocation][$DBServer->serverId];
			}
				
			return Modules_Platforms_GoogleCE_Adapters_Status::load($status);
		}
		
		public function TerminateServer(DBServer $DBServer)
		{
			$gce = $this->getClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(GCE_SERVER_PROPERTIES::CLOUD_LOCATION));
			 
			$gce->instances->delete(
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PROJECT_ID), 
				$DBServer->serverId
			);
			 
			return true;
		}
		
		public function RebootServer(DBServer $DBServer)
		{
			//NOT_SUPPORTED
		}
		
		public function RemoveServerSnapshot(DBRole $DBRole)
		{			
			foreach ($DBRole->getImageId(SERVER_PLATFORMS::GCE) as $location => $imageId) {
			
				$gce = $this->getClient($DBRole->GetEnvironmentObject(), $location);
			
				try {
					
					$projectId = $DBRole->GetEnvironmentObject()->getPlatformConfigValue(self::PROJECT_ID);
					$imageId = str_replace("{$projectId}/images/", "", $imageId);
					
					$gce->images->delete($projectId, $imageId);
				}
				catch(Exception $e)
				{
					throw $e;
				}
			}
				
			return true;
		}
		
		public function CheckServerSnapshotStatus(BundleTask $BundleTask)
		{
			
		}
		
		public function CreateServerSnapshot(BundleTask $BundleTask)
		{
			$DBServer = DBServer::LoadByID($BundleTask->serverId);
			$BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
			$BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::GCE_STORAGE;
			 
			$msg = new Scalr_Messaging_Msg_Rebundle(
				$BundleTask->id,
				$BundleTask->roleName,
				array()
			);
			
			if (!$DBServer->SendMessage($msg))
			{
				$BundleTask->SnapshotCreationFailed("Cannot send rebundle message to server. Please check event log for more details.");
				return;
			}
			else
			{
				$BundleTask->Log(sprintf(_("Snapshot creating initialized (MessageID: %s). Bundle task status changed to: %s"),
						$msg->messageId, $BundleTask->status
				));
			}
				
			$BundleTask->setDate('started');
			$BundleTask->Save();
		}
		
		private function ApplyAccessData(Scalr_Messaging_Msg $msg)
		{
			
			
		}
		
		public function GetServerConsoleOutput(DBServer $DBServer)
		{
			
		}
		
		private function getObjectUrl($objectName, $objectType, $projectName) {
			
			if ($objectType != 'images')
				return self::RESOURCE_BASE_URL."{$projectName}/{$objectType}/{$objectName}";
			else
				return self::RESOURCE_BASE_URL."{$objectName}";
		}
		
		private function getObjectName($objectURL)
		{
			return substr($objectURL, strrpos($objectURL, "/")+1);
		}
		
		public function GetServerExtendedInformation(DBServer $DBServer)
		{
			try
			{
				try	{
					$gce = $this->getClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(GCE_SERVER_PROPERTIES::CLOUD_LOCATION));
					
					$info = $gce->instances->get(
						$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PROJECT_ID),
						$DBServer->serverId
					);
				}
				catch(Exception $e){}
			
				if ($info) {
					$network = $info->getNetworkInterfaces();
					
					return array(
						'ID'					=> $info->id,
						'Image ID'				=> $this->getObjectName($info->image),
						'Machine Type'			=> $this->getObjectName($info->machineType),
						'Public IP'				=> $network[0]->accessConfigs[0]->natIP,
						'Private IP'			=> $network[0]->networkIP,
						'Status'				=> $info->status,
						'Name'					=> $info->name,
						'Zone'					=> $this->getObjectName($info->zone)
					);
				}
			}
			catch(Exception $e){}
				
			return false;
		}
		
		public function LaunchServer(DBServer $DBServer, Scalr_Server_LaunchOptions $launchOptions = null)
		{
			$environment = $DBServer->GetEnvironmentObject();
			
			if (!$launchOptions)
			{
				$launchOptions = new Scalr_Server_LaunchOptions();
				$DBRole = DBRole::loadById($DBServer->roleId);
			
				$launchOptions->imageId = $DBRole->getImageId(SERVER_PLATFORMS::GCE, $DBServer->GetProperty(GCE_SERVER_PROPERTIES::CLOUD_LOCATION));
				
				$launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_GCE_MACHINE_TYPE);
				
				$launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;
			
				$userData = $DBServer->GetCloudUserData();
			
				$launchOptions->architecture = 'x86_64';
				
				$networkName = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_GCE_NETWORK);
			} else {
				$userData = array();
				$networkName = 'default';
			}
			
			if ($DBServer->status == SERVER_STATUS::TEMPORARY)
				$keyName = "SCALR-ROLESBUILDER-".SCALR_ID;
			else
				$keyName = "FARM-{$DBServer->farmId}-".SCALR_ID;
			
			$sshKey = Scalr_SshKey::init();
			if (!$sshKey->loadGlobalByName($keyName, "", $DBServer->envId, SERVER_PLATFORMS::GCE)) {
				$keys = $sshKey->generateKeypair();
				if ($keys['public']) {
					$sshKey->farmId = $DBServer->farmId;
					$sshKey->clientId = $DBServer->clientId;
					$sshKey->envId = $DBServer->envId;
					$sshKey->type = Scalr_SshKey::TYPE_GLOBAL;
					$sshKey->cloudLocation = "";
					$sshKey->cloudKeyName = $keyName;
					$sshKey->platform = SERVER_PLATFORMS::GCE;
					$sshKey->save();
					
					$publicKey = $keys['public']; 
				} else {
					throw new Exception("Scalr unable to generate ssh keypair");
				}
			} else {
				$publicKey = $sshKey->getPublic();
			}
			
			$gce = $this->getClient($environment, $launchOptions->cloudLocation);
			
			$instance = new Google_Instance();
			$instance->setKind("compute#instance");
			
			$network = new Google_NetworkInterface();
			$network->setKind("compute#instanceNetworkInterface");
			$network->setNetwork($this->getObjectUrl(
				$networkName,
				'networks',
				$environment->getPlatformConfigValue(self::PROJECT_ID)
			));
			
			$accessConfig = new Google_AccessConfig();
			$accessConfig->setName("External NAT");
			$accessConfig->setType("ONE_TO_ONE_NAT");
			$network->setAccessConfigs(array($accessConfig));
			$instance->setNetworkInterfaces(array($network));
			
			$serviceAccount = new Google_ServiceAccount();
			$serviceAccount->setKind("compute#serviceAccount");
			$serviceAccount->setEmail("default");
			$serviceAccount->setScopes(array(
				"https://www.googleapis.com/auth/userinfo.email",
				"https://www.googleapis.com/auth/compute",
				"https://www.googleapis.com/auth/devstorage.full_control"
			));
			$instance->setServiceAccounts(array($serviceAccount));
			$instance->setZone($this->getObjectUrl(
				$launchOptions->cloudLocation,
				'zones',
				$environment->getPlatformConfigValue(self::PROJECT_ID)
			));
			$instance->setMachineType($this->getObjectUrl(
				$launchOptions->serverType,
				'machine-types',
				$environment->getPlatformConfigValue(self::PROJECT_ID)
			));
			$instance->setImage($this->getObjectUrl(
				$launchOptions->imageId,
				'images'
			));
			$instance->setName($DBServer->serverId);
			
			$metadata = new Google_Metadata();
			$items = array();
			foreach ($userData as $key => $value) {
				if ($value) {
					$item = new Google_MetadataItems();
					$item->setKey($key);
					$item->setValue($value);
					$items[] = $item;
				}
			}
			
			// Add SSH Key
			$item = new Google_MetadataItems();
			$item->setKey("sshKeys");
			$item->setValue("scalr:{$publicKey}");
			$items[] = $item;
			
			$metadata->setItems($items);
			
			$instance->setMetadata($metadata);
			
			try {
				$result = $gce->instances->insert(
					$environment->getPlatformConfigValue(self::PROJECT_ID), 
					$instance
				);				
			} catch (Exception $e) {				
				throw new Exception(sprintf(_("Cannot launch new instance. %s (%s, %s)"), $e->getMessage(), $launchOptions->imageId, $launchOptions->serverType));
			}
			 
			if ($result->id) {
				
				$DBServer->SetProperty(GCE_SERVER_PROPERTIES::PROVISIONING_OP_ID, $result->name);
				$DBServer->SetProperty(GCE_SERVER_PROPERTIES::SERVER_NAME, $DBServer->serverId);
				$DBServer->SetProperty(GCE_SERVER_PROPERTIES::CLOUD_LOCATION, $launchOptions->cloudLocation);
				$DBServer->SetProperty(GCE_SERVER_PROPERTIES::MACHINE_TYPE, $launchOptions->serverType);
				$DBServer->SetProperty(SERVER_PROPERTIES::ARCHITECTURE, $launchOptions->architecture);
				
				return $DBServer;
			}
			else
				throw new Exception(sprintf(_("Cannot launch new instance. %s (%s, %s)"), serialize($result), $launchOptions->imageId, $launchOptions->serverType));
		}
		
		public function PutAccessData(DBServer $DBServer, Scalr_Messaging_Msg $message)
		{
			$put = false;
			$put |= $message instanceof Scalr_Messaging_Msg_Rebundle;
			$put |= $message instanceof Scalr_Messaging_Msg_HostInitResponse;
			$put |= $message instanceof Scalr_Messaging_Msg_Mysql_PromoteToMaster;
			$put |= $message instanceof Scalr_Messaging_Msg_Mysql_NewMasterUp;
			$put |= $message instanceof Scalr_Messaging_Msg_Mysql_CreateDataBundle;
			$put |= $message instanceof Scalr_Messaging_Msg_Mysql_CreateBackup;
				
			$put |= $message instanceof Scalr_Messaging_Msg_DbMsr_PromoteToMaster;
			$put |= $message instanceof Scalr_Messaging_Msg_DbMsr_CreateDataBundle;
			$put |= $message instanceof Scalr_Messaging_Msg_DbMsr_CreateBackup;
			$put |= $message instanceof Scalr_Messaging_Msg_DbMsr_NewMasterUp;
				
			if ($put) {
				$environment = $DBServer->GetEnvironmentObject();
				$accessData = new stdClass();
				$accessData->clientId = $environment->getPlatformConfigValue(self::CLIENT_ID);
				$accessData->serviceAccountName = $environment->getPlatformConfigValue(self::SERVICE_ACCOUNT_NAME);
				$accessData->projectId = $environment->getPlatformConfigValue(self::PROJECT_ID);
				$accessData->key = $environment->getPlatformConfigValue(self::KEY);
			
				$message->platformAccessData = $accessData;
			}
		}
		
		public function ClearCache ()
		{
			$this->instancesListCache = array();
		}
	}

	
	
?>
