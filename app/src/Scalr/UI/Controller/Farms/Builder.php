<?php

class Scalr_UI_Controller_Farms_Builder extends Scalr_UI_Controller
{
	public function getFarmRolesAction()
	{

	}

	public function xGetScriptingDataAction()
	{
		$data =  array(
			array(EVENT_TYPE::HOST_UP, EVENT_TYPE::GetEventDescription(EVENT_TYPE::HOST_UP)),
			array(EVENT_TYPE::HOST_INIT, EVENT_TYPE::GetEventDescription(EVENT_TYPE::HOST_INIT)),
			array(EVENT_TYPE::HOST_DOWN, EVENT_TYPE::GetEventDescription(EVENT_TYPE::HOST_DOWN)),
			array(EVENT_TYPE::REBOOT_COMPLETE, EVENT_TYPE::GetEventDescription(EVENT_TYPE::REBOOT_COMPLETE)),
			array(EVENT_TYPE::INSTANCE_IP_ADDRESS_CHANGED, EVENT_TYPE::GetEventDescription(EVENT_TYPE::INSTANCE_IP_ADDRESS_CHANGED)),
			array(EVENT_TYPE::NEW_MYSQL_MASTER, EVENT_TYPE::GetEventDescription(EVENT_TYPE::NEW_MYSQL_MASTER)),
			array(EVENT_TYPE::EBS_VOLUME_MOUNTED, EVENT_TYPE::GetEventDescription(EVENT_TYPE::EBS_VOLUME_MOUNTED)),
			array(EVENT_TYPE::BEFORE_INSTANCE_LAUNCH, EVENT_TYPE::GetEventDescription(EVENT_TYPE::BEFORE_INSTANCE_LAUNCH)),
			array(EVENT_TYPE::BEFORE_HOST_TERMINATE, EVENT_TYPE::GetEventDescription(EVENT_TYPE::BEFORE_HOST_TERMINATE)),
			array(EVENT_TYPE::DNS_ZONE_UPDATED, EVENT_TYPE::GetEventDescription(EVENT_TYPE::DNS_ZONE_UPDATED)),
			array(EVENT_TYPE::EBS_VOLUME_ATTACHED, EVENT_TYPE::GetEventDescription(EVENT_TYPE::EBS_VOLUME_ATTACHED)),
			array(EVENT_TYPE::BEFORE_HOST_UP, EVENT_TYPE::GetEventDescription(EVENT_TYPE::BEFORE_HOST_UP))
		);

		$this->response->data(array('events' => $data, 'scripts' => self::loadController('Scripts', 'Scalr_UI_Controller')->getList()));
	}

	public function xBuildAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int'),
			'roles' => array('type' => 'json'),
			'farm' => array('type' => 'json'),
			'roleUpdate' => array('type' => 'int')
		));

		$Validator = new Validator();

		$cloudFoundryStack = array();
		$nginxFound = 0;
		
		foreach ($this->getParam('roles') as $role) {
			$dbRole = DBRole::loadById($role['role_id']);

			if (!$dbRole->getImageId($role['platform'], $role['cloud_location'])) {
				throw new Exception(sprintf(_("Role '%s' is not available in %s on %s"),
					$dbRole->name, $role['platform'], $role['cloud_location'])
				);
			}
			
			// Validate deployments
			$appId = $role[Scalr_Role_Behavior::ROLE_DM_APPLICATION_ID];
			if ($appId) {
				$application = Scalr_Dm_Application::init()->loadById($appId);
				$this->user->getPermissions()->validate($application);
				
				if (!$role[Scalr_Role_Behavior::ROLE_DM_REMOTE_PATH])
					throw new Exception(sprintf("Remote path reuired for deployment on role '%s'", $dbRole->name));
			}
			
			//-- CloudFoundryStuff
			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER))
				$cloudFoundryStack[ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER] = true;
			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_DEA))
				$cloudFoundryStack[ROLE_BEHAVIORS::CF_DEA] = true;
			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_HEALTH_MANAGER))
				$cloudFoundryStack[ROLE_BEHAVIORS::CF_HEALTH_MANAGER] = true;
			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_ROUTER))
				$cloudFoundryStack[ROLE_BEHAVIORS::CF_ROUTER] = true;
			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_SERVICE))
				$cloudFoundryStack[ROLE_BEHAVIORS::CF_SERVICE] = true;
				
			
			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::NGINX))
				$nginxFound++;
			//-- End CloudFoundry stuff	

			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
				$role['settings'][DBFarmRole::SETTING_SCALING_MAX_INSTANCES] = $role['settings'][DBFarmRole::SETTING_SCALING_MIN_INSTANCES];
				
				$role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] = (int)$role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO];
				if ($role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] < 1 || $role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] > 100)
					throw new Exception(sprintf("Nodes ratio for RabbitMq role '%s' should be between 1 and 100", $dbRole->name));
			}
			
			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
				if ($role['settings'][Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_ENGINE] == 'ebs') {
					if ($role['settings'][Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_EBS_SIZE] < 10 || $role['settings'][Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_EBS_SIZE] > 1000)
						throw new Exception(sprintf("EBS size for mongoDB role should be between 10 and 1000 GB", $dbRole->name));
				}
			}
				
			/* Validate scaling */
			$minCount = (int)$role['settings'][DBFarmRole::SETTING_SCALING_MIN_INSTANCES];
			if (!$minCount && $minCount != 0)
				$minCount = 1;

			if ($minCount < 0 || $minCount > 400)
				throw new Exception(sprintf(_("Min instances for '%s' must be a number between 1 and 400"), $dbRole->name));

			$maxCount = (int)$role['settings'][DBFarmRole::SETTING_SCALING_MAX_INSTANCES];
			if (!$maxCount)
				$maxCount = 1;

			if ($maxCount < 1 || $maxCount > 400)
				throw new Exception(sprintf(_("Max instances for '%s' must be a number between 1 and 400"), $dbRole->name));
				
			if ($maxCount < $minCount)
				throw new Exception(sprintf(_("Max instances should be greater or equal than Min instances for role '%s'"), $dbRole->name));

			if (isset($role['settings'][DBFarmRole::SETTING_SCALING_POLLING_INTERVAL]) && $role['settings'][DBFarmRole::SETTING_SCALING_POLLING_INTERVAL] > 0)
				$polling_interval = (int)$role['settings'][DBFarmRole::SETTING_SCALING_POLLING_INTERVAL];
			else
				$polling_interval = 2;
			 

			if ($polling_interval < 1 || $polling_interval > 50)
				throw new Exception(sprintf(_("Polling interval for role '%s' must be a number between 1 and 50"), $dbRole->name));

			/** Validate platform specified settings **/
			switch($role['platform']) {
				case SERVER_PLATFORMS::EC2:
					Modules_Platforms_Ec2_Helpers_Ebs::farmValidateRoleSettings($role['settings'], $dbRole->name);
					Modules_Platforms_Ec2_Helpers_Eip::farmValidateRoleSettings($role['settings'], $dbRole->name);
					Modules_Platforms_Ec2_Helpers_Elb::farmValidateRoleSettings($role['settings'], $dbRole->name);

					if ($dbRole->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
						if ($role['settings'][DBFarmRole::SETTING_MYSQL_DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::EBS) {

							if ($dbRole->generation != 2) {
								if ($role['settings'][DBFarmRole::SETTING_AWS_AVAIL_ZONE] == "" ||
									$role['settings'][DBFarmRole::SETTING_AWS_AVAIL_ZONE] == "x-scalr-diff" ||
									stristr($role['settings'][DBFarmRole::SETTING_AWS_AVAIL_ZONE], 'x-scalr-custom')
								)
									throw new Exception(sprintf(_("Requirement for EBS MySQL data storage is specific 'Placement' parameter for role '%s'"), $dbRole->name));
							}
						}
					}
					
					if ($dbRole->getDbMsrBehavior())
					{
						if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::EPH) {
							if (!$role['settings'][Scalr_Db_Msr::DATA_STORAGE_EPH_DISK])
								throw new Exception(sprintf(_("Ephemeral disk settings is required for role '%s'"), $dbRole->name));
						}
						
						if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::RAID_EBS) {
							if (!$this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_RAID)) {
								throw new Exception('RAID arrays are not available for your pricing plan. <a href="#/billing">Please upgrade your account to be able to use this feature.</a>');
							}
						}
						
						if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_FSTYPE] && $role['settings'][Scalr_Db_Msr::DATA_STORAGE_FSTYPE] != 'ext3') {
							if (!$this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_MFS)) {
								throw new Exception('Only ext3 filesystem available for your pricing plan. <a href="#/billing">Please upgrade your account to be able to use other filesystems.</a>');
							}
						}
					}
					
					if ($dbRole->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
						if ($role['settings'][Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::RAID_EBS) {
							if (!$this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_RAID)) {
								throw new Exception('RAID arrays are not available for your pricing plan. <a href="#/billing">Please upgrade your account to be able to use this feature.</a>');
							}
						}
					}
					
					if ($role['settings'][DBFarmRole::SETTING_AWS_AVAIL_ZONE] == 'x-scalr-custom=')
						throw new Exception(sprintf(_("Availability zone for role \"%s\" should be selected"), $dbRole->name));

					break;

				case SERVER_PLATFORMS::RDS:
						Modules_Platforms_Rds_Helpers_Rds::farmValidateRoleSettings($role['settings'], $dbRole->name);
					break;

				case SERVER_PLATFORMS::EUCALYPTUS:
						Modules_Platforms_Eucalyptus_Helpers_Eucalyptus::farmValidateRoleSettings($role['settings'], $dbRole->name);
					break;

				case SERVER_PLATFORMS::CLOUDSTACK:
						Modules_Platforms_Cloudstack_Helpers_Cloudstack::farmValidateRoleSettings($role['settings'], $dbRole->name);
					break;
				
				case SERVER_PLATFORMS::RACKSPACE:
						Modules_Platforms_Rackspace_Helpers_Rackspace::farmValidateRoleSettings($role['settings'], $dbRole->name);
					break;
			}

			Scalr_Helpers_Dns::farmValidateRoleSettings($role['settings'], $dbRole->name);
		}
		
		//Validate ClouFoundry stuff
		if (!empty($cloudFoundryStack)) {
			if (!$cloudFoundryStack[ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER])
				throw new Exception("CF CloudContoller role required for CloudFoundry stack. Please add All-in-one CF or separate CCHM role to farm");
				
			if (!$cloudFoundryStack[ROLE_BEHAVIORS::CF_HEALTH_MANAGER])
				throw new Exception("CF HealthManager role required for CloudFoundry stack. Please add All-in-one CF or separate CCHM role to farm");
				
			if (!$cloudFoundryStack[ROLE_BEHAVIORS::CF_ROUTER])
				throw new Exception("CF Router role required for CloudFoundry stack. Please add All-in-one CF or separate CF Router role to farm");
				
			if (!$cloudFoundryStack[ROLE_BEHAVIORS::CF_DEA])
				throw new Exception("CF DEA role required for CloudFoundry stack. Please add All-in-one CF or separate CF DEA role to farm");
				
			if (!$nginxFound)
				throw new Exception("Nginx load balancer role required for CloudFoundry stack. Please add it to the farm");
				
			if ($cloudFoundryStack[ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER] > 1)
				throw new Exception("CloudFoundry stack can work only with ONE CF CloudController role. Please leave only one CloudController role in farm");
				
			if ($cloudFoundryStack[ROLE_BEHAVIORS::CF_HEALTH_MANAGER] > 1)
				throw new Exception("CloudFoundry stack can work only with ONE CF HealthManager role. Please leave only one HealthManager role in farm");
				
			if ($nginxFound > 1)
				throw new Exception("CloudFoundry stack can work only with ONE nginx role. Please leave only one nginx role in farm");
		}

		$client = Client::Load($this->user->getAccountId());

		if ($this->getParam('farmId')) {
			$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
			$this->user->getPermissions()->validate($dbFarm);
			$dbFarm->isLocked();
		}
		else {
			$this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_FARMS, 1);

			$dbFarm = new DBFarm();
			$dbFarm->Status = FARM_STATUS::TERMINATED;
			
			$dbFarm->createdByUserId = $this->user->getId();
			$dbFarm->createdByUserEmail = $this->user->getEmail();
		}

		if ($this->getParam('farm')) {
			$farm = $this->getParam('farm');

			$dbFarm->Name = strip_tags($farm['name']);
			$dbFarm->RolesLaunchOrder = $farm['roles_launch_order'];
			$dbFarm->Comments = trim(strip_tags($farm['description']));
		}

		if (!$Validator->IsNotEmpty($dbFarm->Name))
			throw new Exception(_("Farm name required"));

		$dbFarm->save();

		if (!$dbFarm->GetSetting(DBFarm::SETTING_CRYPTO_KEY))
			$dbFarm->SetSetting(DBFarm::SETTING_CRYPTO_KEY, Scalr::GenerateRandomKey(40));


		$usedPlatforms = array();
		$dbFarmRolesList = array();
		$newFarmRolesList = array();
		foreach ($this->getParam('roles') as $role) {
			if ($role['farm_role_id']) {
				$update = true;
				$dbFarmRole = DBFarmRole::LoadByID($role['farm_role_id']);
				$dbRole = DBRole::loadById($dbFarmRole->RoleID);
				$role['role_id'] = $dbFarmRole->RoleID;
				
				if ($dbFarmRole->Platform == SERVER_PLATFORMS::GCE)
					$dbFarmRole->CloudLocation = $role['cloud_location'];
				
			} else {
				$update = false;
				$dbRole = DBRole::loadById($role['role_id']);
				$dbFarmRole = $dbFarm->AddRole($dbRole, $role['platform'], $role['cloud_location'], (int)$role['launch_index']);
			}
			
			if ($dbRole->hasBehavior(ROLE_BEHAVIORS::RABBITMQ))
				$role['settings'][DBFarmRole::SETTING_SCALING_MAX_INSTANCES] = $role['settings'][DBFarmRole::SETTING_SCALING_MIN_INSTANCES];

			if ($dbFarmRole->NewRoleID) {
				continue;
			}

			if ($update) {
				$dbFarmRole->LaunchIndex = (int)$role['launch_index'];
				$dbFarmRole->Save();
			}

			$usedPlatforms[$role['platform']] = 1;

			$oldRoleSettings = $dbFarmRole->GetAllSettings();
			foreach ($role['scaling_settings'] as $k => $v) {
				$dbFarmRole->SetSetting($k, $v);
			}
			
			foreach ($role['settings'] as $k => $v)
				$dbFarmRole->SetSetting($k, $v);

			/****** Scaling settings ******/
			$scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
			$scalingManager->setFarmRoleMetrics($role['scaling']);

			//TODO: optimize this code...
			$this->db->Execute("DELETE FROM farm_role_scaling_times WHERE farm_roleid=?",
				array($dbFarmRole->ID)
			);

			// 5 = Time based scaling -> move to constants
			if ($role['scaling'][5]) {
				foreach ($role['scaling'][5] as $scal_period) {
					$chunks = explode(":", $scal_period['id']);
					$this->db->Execute("INSERT INTO farm_role_scaling_times SET
						farm_roleid		= ?,
						start_time		= ?,
						end_time		= ?,
						days_of_week	= ?,
						instances_count	= ?
					", array(
						$dbFarmRole->ID,
						$chunks[0],
						$chunks[1],
						$chunks[2],
						$chunks[3]
					));
				}
			}
			/*****************/

			/* Update role params */
			$dbFarmRole->SetParameters($role['params']);
			/* End of role params management */

			/* Add script options to databse */
			$dbFarmRole->SetScripts($role['scripting'], $role['scripting_params']);
			/* End of scripting section */

			/* Add services configuration */
			$dbFarmRole->SetServiceConfigPresets($role['config_presets']);
			/* End of scripting section */

			Scalr_Helpers_Dns::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);

			foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
				$behavior->onFarmSave($dbFarm, $dbFarmRole);
			
			/**
			 * Platfrom specified updates
			 */
			if ($dbFarmRole->Platform == SERVER_PLATFORMS::EC2) {
				Modules_Platforms_Ec2_Helpers_Ebs::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
				Modules_Platforms_Ec2_Helpers_Eip::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
				Modules_Platforms_Ec2_Helpers_Elb::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
			}

			$dbFarmRolesList[] = $dbFarmRole;
			$newFarmRolesList[] = $dbFarmRole->ID;
		}

		if (!$this->getParam('roleUpdate')) {
			foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {
				if (!$dbFarmRole->NewRoleID && !in_array($dbFarmRole->ID, $newFarmRolesList))
					$dbFarmRole->Delete();
			}
		}

		if ($usedPlatforms[SERVER_PLATFORMS::CLOUDSTACK])
			Modules_Platforms_Cloudstack_Helpers_Cloudstack::farmSave($dbFarm, $dbFarmRolesList);

		if ($usedPlatforms[SERVER_PLATFORMS::EC2])
			Modules_Platforms_Ec2_Helpers_Ec2::farmSave($dbFarm, $dbFarmRolesList);

		if ($usedPlatforms[SERVER_PLATFORMS::EUCALYPTUS])
			Modules_Platforms_Eucalyptus_Helpers_Eucalyptus::farmSave($dbFarm, $dbFarmRolesList);

		$dbFarm->save();

		if (!$client->GetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED))
			$client->SetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED, time());

		$this->response->success('Farm successfully saved');
		$this->response->data(array('farmId' => $dbFarm->ID));
	}

	public function getFarm($farmId)
	{
		$dbFarm = DBFarm::LoadByID($farmId);
		$this->user->getPermissions()->validate($dbFarm);

		$farmRoleId = $this->getParam('farmRoleId');
		$farmRoles = array();

		foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {
			if ($farmRoleId && $farmRoleId != $dbFarmRole->ID)
				continue;

			$scripts = $this->db->GetAll("SELECT farm_role_scripts.*, scripts.name FROM farm_role_scripts
				INNER JOIN scripts ON scripts.id = farm_role_scripts.scriptid
				WHERE farm_roleid=? AND issystem='1'", array($dbFarmRole->ID)
			);
			$scriptsObject = array();
			foreach ($scripts as $script) {
				$scriptsObject[] = array(
					'script_id'		=> $script['scriptid'],
					'script'		=> $script['name'],
					'params'		=> unserialize($script['params']),
					'target'		=> $script['target'],
					'version'		=> $script['version'],
					'timeout'		=> $script['timeout'],
					'issync'		=> $script['issync'],
					'order_index'	=> $script['order_index'],
					'event' 		=> $script['event_name']
				);
			}
			
			//Scripting params
			$scriptingParams = $this->db->Execute("SELECT * FROM farm_role_scripting_params WHERE farm_role_id = ? AND farm_role_script_id = '0'", array($dbFarmRole->ID));
			$sParams = array();
			while ($p = $scriptingParams->FetchRow()){
				$sParams[] = array('role_script_id' => $p['role_script_id'], 'params' => unserialize($p['params']));
			}
			

			$scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
			$scaling = array();
			foreach ($scalingManager->getFarmRoleMetrics() as $farmRoleMetric)
				$scaling[$farmRoleMetric->metricId] = $farmRoleMetric->getSettings();

			$dbPresets = $this->db->GetAll("SELECT * FROM farm_role_service_config_presets WHERE farm_roleid=?", array($dbFarmRole->ID));
			$presets = array();
			foreach ($dbPresets as $preset)
				$presets[$preset['behavior']] = $preset['preset_id'];

			if ($dbFarmRole->NewRoleID) {
				$roleName = DBRole::loadById($dbFarmRole->NewRoleID)->name;
				$isBundling = true;
			} else {
				$roleName = $dbFarmRole->GetRoleObject()->name;
				$isBundling = false;
			}

			$imageDetails = $dbFarmRole->GetRoleObject()->getImageDetails($dbFarmRole->Platform, $dbFarmRole->CloudLocation);
			
			$farmRoles[] = array(
				'farm_role_id'	=> $dbFarmRole->ID,
				'role_id'		=> $dbFarmRole->RoleID,
				'platform'		=> $dbFarmRole->Platform,
				'os'			=> $dbFarmRole->GetRoleObject()->os,
				'generation'	=> $dbFarmRole->GetRoleObject()->generation,
				'arch'			=> $dbFarmRole->GetRoleObject()->architecture,
				'group'			=> ROLE_GROUPS::GetConstByBehavior($dbFarmRole->GetRoleObject()->getBehaviors()),
				'name'			=> $roleName,
				'is_bundle_running'	=> $isBundling,
				'behaviors'		=> implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
				'scripting'		=> $scriptsObject,
				'scripting_params' => $sParams,
				'settings'		=> $dbFarmRole->GetAllSettings(),
				'cloud_location'=> $dbFarmRole->CloudLocation,
				'launch_index'	=> (int)$dbFarmRole->LaunchIndex,
				'scaling'		=> $scaling,
				'config_presets'=> $presets,
				'tags'			=> $dbFarmRole->GetRoleObject()->getTags(),
				'image_os_family' 	=> $imageDetails['os_family'],
				'image_os_version' 	=> $imageDetails['os_version'],
				'image_os_name'	 	=> $imageDetails['os_name']
			);
		}

		return array(
			'farm' => array(
				'name' => $dbFarm->Name,
				'description' => $dbFarm->Comments,
				'rolesLaunchOrder' => $dbFarm->RolesLauchOrder
			),
			'roles' => $farmRoles,
			'lock' => $dbFarm->isLocked(false)
		);
	}

	public function xGetFarmAction()
	{
		$res = $this->getFarm($this->getParam('farmId'));
		$this->response->data($res);
	}

	public function xGetScriptsAction()
	{
		$dbRole = DBRole::loadById($this->getParam('roleId'));
		if ($dbRole->origin == ROLE_TYPE::CUSTOM)
			$this->user->getPermissions()->validate($dbRole);

		
		$data = self::loadController('Scripts')->getScriptingData();
		$data['roleScripts'] = $dbRole->getScripts();
		
		$this->response->data($data);
	}
}
