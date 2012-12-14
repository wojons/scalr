<?php

class Scalr_UI_Controller_Farms extends Scalr_UI_Controller
{
	const CALL_PARAM_NAME = 'farmId';
	
	public static function getPermissionDefinitions()
	{
		return array(
			'edit' => 'Edit',
			'build' => 'Edit',
			'xClone' => 'Clone',
			'xTerminate' => 'Terminate',
			'xLaunch' => 'Launch',
			'xRemove' => 'Edit'
		);
	}

	public function defaultAction()
	{
		$this->viewAction();
	}

	public function xSaveSzrUpdSettingsAction()
	{
		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
        
        $schedule = implode(" ", array($this->getParam("hh"), $this->getParam("dd"), $this->getParam("dw")));
        $repo = $this->getParam("szrRepository");
        
        if ($schedule != $dbFarm->GetSetting(DBFarm::SETTING_SZR_UPD_SCHEDULE) || $repo != $dbFarm->GetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY)) {
        	$dbFarm->SetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY, $repo);
        	$dbFarm->SetSetting(DBFarm::SETTING_SZR_UPD_SCHEDULE, $schedule);
        	
        	$servers = $dbFarm->GetServersByFilter(array('status' => array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING)));
        	foreach ($servers as $dbServer) {
        		
        		try {
        			$updClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer);
        			$updClient->configure($repo, $schedule);
        		} catch (Exception $e) {
        			Logger::getLogger('Farm')->error(new FarmLogMessage($dbFarm->ID, sprintf("Unable to update scalarizr update settings on server %s: %s",
        				$dbServer->serverId, $e->getMessage()
        			)));
        			$err = true;
        		}
        	}
        }
		
        if (!$err)
			$this->response->success('Scalarizr auto-update settings successfully saved');
		else 
			$this->response->warning('Scalarizr auto-update settings successfully saved, but some servers were not updated. Please check "Logs->Event log" for more details.');
	}
	
	public function extendedInfoAction()
	{
		if (! $this->getParam('farmId'))
			throw new Exception(_('Server not found'));

		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
        
		$form = array(
			array(
				'xtype' => 'fieldset',
				'title' => 'General',
				'labelWidth' => 220,
				'items' => array(
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'ID',
						'value' => $dbFarm->ID
					),
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Name',
						'value' => $dbFarm->Name
					),
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Hash',
						'value' => $dbFarm->Hash
					)
				)
			)
		);
	
		///Update settings
		$repo = $dbFarm->GetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY);
		if (!$repo)
			$repo = 'stable';
				
		$schedule = $dbFarm->GetSetting(DBFarm::SETTING_SZR_UPD_SCHEDULE);
		if (!$schedule)
			$schedule = "* * *";
			
		$sChunks = explode(" ", $schedule); 
		
		$store = new stdClass();
		$store->fields = array('name', 'description');
		$store->proxy = 'object';
			
		$itm = array(
			'xtype' => 'fieldset',
			'title' => 'Scalr agent update settings',
			'labelWidth' => 220,
			'items' => array(
				array(
					'xtype' => 'combo',
					'itemId' => 'repo',
					'editable' => false,
					'name' => 'szrRepository',
					'fieldLabel' => 'Repository',
					'queryMode' => 'local',
					'store' => $store,
					'value' => $repo,
					'valueField' => 'name',
					'displayField' => 'description'
				),
				array(
					'xtype' => 'fieldcontainer',
					'fieldLabel' => 'Schedule',
					'layout' => 'hbox',
					'items' => array(
						array(
							'xtype' => 'textfield',
							'hideLabel' => true,
							'width' => 50,
							'margin' => '0 3 0 0',
							'value' => $sChunks[0],
							'name' => 'hh'
						), array(
							'xtype' => 'textfield',
							'hideLabel' => true,
							'value' => $sChunks[1],
							'width' => 50,
							'margin' => '0 3 0 0',
							'name' => 'dd'
						), array(
							'xtype' => 'textfield',
							'hideLabel' => true,
							'width' => 50,
							'value' => $sChunks[2],
							'name' => 'dw',
							'margin' => '0 3 0 0'
						), array(
							'xtype' => 'displayinfofield',
							'info' => '
*&nbsp;&nbsp;&nbsp;*&nbsp;&nbsp;&nbsp;*<br>
─&nbsp;&nbsp;&nbsp;─&nbsp;&nbsp;&nbsp;─<br>
│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>
│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>
│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;└───── day of week (0 - 6) (0 is Sunday)<br>
│&nbsp;&nbsp;&nbsp;└─────── day of month (1 - 31)<br>
└───────── hour (0 - 23)<br>'
						)
					)
				),
				array(
					'xtype' => 'button',
					'itemId' => 'updSettingsSave',
					'text' => 'Save',
					'flex' => 1
				)
			)
		);
		
		$form[] = $itm;
		//////////////////
		

		$haveMysqlRole = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
			array(ROLE_BEHAVIORS::MYSQL, $this->getParam('farmId'), SERVER_PLATFORMS::RDS)
		);
		
		if (!$haveMysqlRole)
			$haveMysql2Role = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::MYSQL2, $this->getParam('farmId'), SERVER_PLATFORMS::RDS)
			);
		
		if (!$haveMysql2Role && !$haveMysqlRole)
			$havePerconaRole = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::PERCONA, $this->getParam('farmId'), SERVER_PLATFORMS::RDS)
			);

		$havePgRole = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
			array(ROLE_BEHAVIORS::POSTGRESQL, $this->getParam('farmId'), SERVER_PLATFORMS::RDS)
		);

		$haveRedisRole = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
			array(ROLE_BEHAVIORS::REDIS, $this->getParam('farmId'), SERVER_PLATFORMS::RDS)
		);
		
		$haveCFController = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER, $this->getParam('farmId'), SERVER_PLATFORMS::RDS)
		);
		
		$type = array();
		
		if ($havePgRole)
			$type['postgresql'] = 'PostgreSQL';
		
		if ($haveRedisRole)
			$type['redis'] = 'Redis';
		
		
		if ($haveMysqlRole)
			$type['mysql'] = 'MySQL';
		
		if ($haveMysql2Role)
			$type['mysql2'] = 'MySQL 5.5';
			
		if ($havePerconaRole)
			$type['percona'] = 'Percona Server';
		
		foreach ($type as $dbMsr => $name) {
			$it = array(
				array(
					'xtype' => 'displayfield',
					'fieldCls' => 'x-form-field-info',
					'anchor' => '100%',
					'value' => 'Public - To connect to the service from the Internet<br / >Private - To connect to the service from another instance'
				),
				array(
					'xtype' => 'displayfield',
					'fieldLabel' => 'Writes endpoint (Public)',
					'value' => "ext.master.{$dbMsr}.{$dbFarm->Hash}.scalr-dns.net"
				),
				array(
					'xtype' => 'displayfield',
					'fieldLabel' => 'Reads endpoint (Public)',
					'value' => "ext.slave.{$dbMsr}.{$dbFarm->Hash}.scalr-dns.net"
				),
				array(
					'xtype' => 'displayfield',
					'fieldLabel' => 'Writes endpoint (Private)',
					'value' => "int.master.{$dbMsr}.{$dbFarm->Hash}.scalr-dns.net"
				),
				array(
					'xtype' => 'displayfield',
					'fieldLabel' => 'Reads endpoint (Private)',
					'value' => "int.slave.{$dbMsr}.{$dbFarm->Hash}.scalr-dns.net"
				)
			);
			
			$form[] = array(
				'xtype' => 'fieldset',
				'title' => "{$name} DNS endpoints",
				'labelWidth' => 220,
				'items' => $it
			);
		}
		
		if ($haveCFController) {
			$it = array(
				array(
					'xtype' => 'displayfield',
					'fieldLabel' => 'VMC target endpoint',
					'value' => "api.ext.cloudfoundry.{$dbFarm->Hash}.scalr-dns.net"
				)
			);
				
			$form[] = array(
					'xtype' => 'fieldset',
					'title' => "CloudFoundry connection information",
					'labelWidth' => 220,
					'items' => $it
			);
		}

		$this->response->page('ui/farms/extendedinfo.js', array('name' => $dbFarm->Name, 'info' => $form));
	}
	
	public function getList(array $filterArgs = array())
	{
		$retval = array();
		
		$sql = "SELECT  name, id FROM farms WHERE env_id = ?";
		$args = array($this->getEnvironmentId());
		foreach ((array)$filterArgs as $k=>$v) {
			if (is_array($v)) {	
				foreach ($v as $vv)
					array_push($args, $vv);
				
				$sql .= " AND `{$k}` IN (".implode(",", array_fill(0, count($v), "?")).")";
			}
			else {
				$sql .= " AND `{$k}`=?";
				array_push($args, $v);
			}
		}
		
		$s = $this->db->execute($sql, $args);
		while ($farm = $s->fetchRow()) {
			$retval[$farm['id']] = $farm;
		}
		sort($retval);
		return $retval;
	}

	/**
	 * @param $values array (farmId, farmRoleId, serverId)
	 * @param $options array|string
	 *      'addAll' - add "On all *" option to roles and servers
	 *      'disabledFarmRole' - remove farmRole field
	 *      'disabledServer' - remove server field
	 *      'addEmpty' - add "*empty*" option
	 *      'requiredFarm', 'requiredFarmRole', 'requiredServer' - add allowBlank = false to field
	 * @return array
	 */
	public function getFarmWidget($values = array(), $options)
	{
		if ($options) {
			if (!is_array($options))
				$options = array($options);

		} else
			$options = array();

		if ($values['serverId']) {
			$dbServer = DBServer::LoadByID($values['serverId']);
			$this->user->getPermissions()->validate($dbServer);
			$values['farmRoleId'] = $dbServer->farmRoleId;
		}

		if ($values['farmRoleId']) {
			$dbFarmRole = DBFarmRole::LoadByID($values['farmRoleId']);
			$this->user->getPermissions()->validate($dbFarmRole);

			$values['dataServers'] = $this->getFarmWidgetServers($values['farmRoleId'], $options);
			$values['farmId'] = $dbFarmRole->FarmID;

			if (! $values['serverId'])
				$values['serverId'] = 0;
		}

		if ($values['farmId']) {
			$values['dataFarmRoles'] = $this->getFarmWidgetRoles($values['farmId'], $options);
		}

		$values['dataFarms'] = $this->getFarmWidgetFarms($options);
		$values['options'] = $options;

		return $values;
	}

	public function getFarmWidgetFarms($options)
	{
		$farms = $this->db->GetAll('SELECT id, name FROM farms WHERE env_id = ? ORDER BY name', $this->getEnvironmentId());
		if (in_array('addEmpty', $options))
			array_unshift($farms, array('id' => '', 'name' => ''));

		return $farms;
	}

	public function getFarmWidgetRoles($farmId, $options)
	{
		$dbFarm = DBFarm::LoadById($farmId);
		$this->user->getPermissions()->validate($dbFarm);
		$dataFarmRoles = array();
		$behaviors = array();

		foreach($options as $key => $value) {
			$matches = explode('_', $value);
			if ($matches[0] == 'behavior' && $matches[1])
				$behaviors[] = $matches[1];
		}

		foreach($this->db->GetAll("SELECT id, platform, role_id FROM farm_roles WHERE farmid = ?", array($dbFarm->ID)) as $farmRole) {
			try {
				$dbRole = DBRole::loadById($farmRole['role_id']);
				$farmRole['name'] = $dbRole->name;

				if (!empty($behaviors)) {
					$bFilter = false;
					foreach($behaviors as $behavior) {
						if ($dbRole->hasBehavior($behavior)) {
							$bFilter = true;
							break;
						}
					}

					if (!$bFilter)
						continue;
				}

			} catch (Exception $e) {
				$farmRole['name'] = '*removed*';
			}

			array_push($dataFarmRoles, $farmRole);
		}

		if (count($dataFarmRoles) && in_array('addAll', $options))
			array_unshift($dataFarmRoles, array('id' => '0', 'name' => 'On all roles'));

		if (in_array('addEmpty', $options))
			array_unshift($dataFarmRoles, array('id' => '', 'name' => ''));

		if (!empty($dataFarmRoles))
			return $dataFarmRoles;
		else
			return null;
	}

	public function xGetFarmWidgetRolesAction()
	{
		$this->response->data(array(
			'dataFarmRoles' => $this->getFarmWidgetRoles($this->getParam('farmId'), explode(',', $this->getParam('options')))
		));
	}

	public function getFarmWidgetServers($farmRoleId, $options)
	{
		$servers = array();
		$dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
		$this->user->getPermissions()->validate($dbFarmRole);

		foreach ($dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING)) as $value)
			array_push($servers, array('id' => $value->serverId, 'name' => $value->remoteIp));

		if (count($servers) && in_array('addAll', $options)) {
			array_unshift($servers, array('id' => 0, 'name' => 'On all servers'));
		}

		if (in_array('addEmpty', $options))
			array_unshift($servers, array('id' => '', 'name' => ''));

		if (!empty($servers))
			return $servers;
		else
			return null;
	}

	public function xGetFarmWidgetServersAction()
	{
		$this->response->data(array(
			'dataServers' => $this->getFarmWidgetServers($this->getParam('farmRoleId'), explode(',', $this->getParam('options')))
		));
	}

	public function viewAction()
	{
		$enabled = $this->user->getSetting(Scalr_Account_User::SETTING_DASHBOARD_ENABLED);
		$this->response->page('ui/farms/view.js', array('dashboard_enabled' => $enabled));
	}

	public function editAction()
	{
		$this->buildAction();
	}

	public function dnszonesAction()
	{
		$this->request->setParams(array('farmId' => $this->getParam('farmId')));
		self::loadController('Dnszones')->viewAction();
	}

	public function vhostsAction()
	{
		$this->request->setParams(array('farmId' => $this->getParam('farmId')));
		self::loadController('Vhosts', 'Scalr_UI_Controller_Services_Apache')->viewAction();
	}

	public function serversAction()
	{
		$this->request->setParams(array('farmId' => $this->getParam('farmId')));
		self::loadController('Servers')->viewAction();
	}

	public function xCloneAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int')
		));

		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
		$this->user->getPermissions()->validate($dbFarm);

		$newDbFarm = $dbFarm->cloneFarm(null, $this->user, $this->getEnvironmentId());

		$this->response->success("Farm successfully cloned. New farm: '{$newDbFarm->Name}'");
	}

	public function xLockAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int'),
			'comment', 'restrict'
		));

		if (! $this->getParam('comment')) {
			$this->response->failure('Comment is required');
			return;
		}

		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
		$this->user->getPermissions()->validate($dbFarm);
		$dbFarm->isLocked();

		$dbFarm->lock($this->user->getId(), $this->getParam('comment'), !!$this->getParam('restrict'));

		$this->response->success('Farm successfully locked');
	}

	public function xUnlockAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int')
		));

		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
		$this->user->getPermissions()->validate($dbFarm);
		if ($dbFarm->isLocked(false)) {
			if ($dbFarm->GetSetting(DBFarm::SETTING_LOCK_RESTRICT) &&
				$dbFarm->createdByUserId != $this->user->getId() &&
				$this->user->getType() != Scalr_Account_User::TYPE_ACCOUNT_OWNER
			) {
				// farm lock restricted, user has no access
				throw new Exception('You can\'t unlock farm. Only farm owner or account owner can do that.');
			}

			$dbFarm->unlock($this->user->getId());
			$this->response->success('Farm successfully unlocked');
		} else {
			$this->response->failure('Farm isn\'t locked');
		}
	}

	public function xTerminateAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int'),
			'deleteDNSZones' => array('type' => 'string'),
			'deleteCloudObjects' => array('type' => 'string'),
			'unTermOnFail' => array('type' => 'string'),
			'forceTerminate' => array('type' => 'string'),
			'sync' => array('type' => 'array'),
			'syncInstances' => array('type' => 'array'),
		));

		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
		$this->user->getPermissions()->validate($dbFarm);
		$dbFarm->isLocked();

		$syncInstances = $this->getParam('syncInstances');
		foreach ($this->getParam('sync') as $farmRoleId) {
			$serverId = $syncInstances[$farmRoleId];

			$dbServer = DBServer::LoadByID($serverId);
			$this->user->getPermissions()->validate($dbServer);

			$serverSnapshotCreateInfo = new ServerSnapshotCreateInfo(
				$dbServer,
				BundleTask::GenerateRoleName($dbServer->GetFarmRoleObject(), $dbServer),
				SERVER_REPLACEMENT_TYPE::REPLACE_FARM,
				false,
				sprintf(_("Server snapshot created during farm '%s' termination at %s"),
					$dbServer->GetFarmObject()->Name,
				date("M j, Y H:i:s"))
			);

			BundleTask::Create($serverSnapshotCreateInfo);
		}

		$removeZoneFromDNS = ($this->getParam('deleteDNSZones') == 'on') ? 1 : 0;
		$keepCloudObjects = ($this->getParam('deleteCloudObjects') == 'on') ? 0 : 1;
		$termOnFail = ($this->getParam('unTermOnFail') == 'on') ? 0 : 1;
		$forceTerminate = ($this->getParam('forceTerminate') == 'on') ? 1 : 0;

		$event = new FarmTerminatedEvent($removeZoneFromDNS, $keepCloudObjects, $termOnFail, $keepCloudObjects, $forceTerminate);
		Scalr::FireEvent($this->getParam('farmId'), $event);

		$this->response->success('Farm successfully terminated. Instances termination can take a few minutes.');
	}

	public function xGetTerminationDetailsAction()
	{
		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
		$this->user->getPermissions()->validate($dbFarm);
		$dbFarm->isLocked();

		$outdatedFarmRoles = $this->db->GetAll("SELECT id FROM farm_roles WHERE farmid=?",
			array($dbFarm->ID)
		);
		$data = array();
		$isMongoDbClusterRunning = false;
		$isMysql = false;
		foreach ($outdatedFarmRoles as $farmRole) {
			$dbFarmRole = DBFarmRole::LoadByID($farmRole['id']);

			if (!$isMongoDbClusterRunning)
				$isMongoDbClusterRunning = $dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB) && ($dbFarmRole->GetSetting(Scalr_Role_Behavior_MongoDB::ROLE_CLUSTER_STATUS) != Scalr_Role_Behavior_MongoDB::STATUS_TERMINATED);
			
			if (!$isMysql)
				$isMysql = $dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL);
				
			$row = array(
				'dtLastSync'	=> (strtotime($dbFarmRole->dtLastSync)) ? Formater::FuzzyTimeString(strtotime($dbFarmRole->dtLastSync), false) : "Never",
				'name'			=> $dbFarmRole->GetRoleObject()->name,
				'id'			=> $dbFarmRole->ID,
				'isBundleRunning'=> $this->db->GetOne("SELECT id FROM bundle_tasks WHERE status NOT IN ('success','failed') AND role_id=? AND farm_id IN (SELECT id FROM farms WHERE client_id=?)", array(
					$dbFarmRole->RoleID,
					$dbFarm->ClientID
				))
			);

			foreach ($dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING)) as $dbServer) {
				$row['servers'][] = array(
					'server_id'	=> $dbServer->serverId,
					'remoteIp'	=> $dbServer->remoteIp
				);
			}

			$data[] = $row;
		}

		$this->response->data(array(
			'roles' => $data,
			'isMongoDbClusterRunning' => $isMongoDbClusterRunning,
			'isMysqlRunning' => $isMysql,
			'farmId' => $dbFarm->ID,
			'farmName' => $dbFarm->Name
		));
	}

	public function buildAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int'),
			'roleId' => array('type' => 'int')
		));

		$farmId = $this->getParam('farmId');
		$roleId = $this->getParam('roleId');

		$moduleParams = array(
			'farmId' => $farmId,
			'roleId' => $roleId,
			'currentTimeZone' => $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_TIMEZONE),
			'currentTime' => Scalr_Util_DateTime::convertTz(time()),
			'currentEnvId' => $this->getEnvironmentId(),
			'groups' => ROLE_GROUPS::GetName(null, true)
		);
		
		$platforms = $this->getEnvironment()->getEnabledPlatforms();
		if (empty($platforms))
			throw new Exception('Before building new farm you need to configure environment and setup cloud credentials');

		if ($farmId) {
			$c = self::loadController('Builder', 'Scalr_UI_Controller_Farms');
			$moduleParams['farm'] = $c->getFarm($farmId);
		}

		$moduleParams['tabs'] = array('scaling', 'mysql', 'dbmsr', 'cloudfoundry', 'rabbitmq', 'mongodb', 'haproxy', 'balancing', 'placement', 
			'openstack', 'cloudstack', 'rsplacement', 'gce', 'params', 'rds', 'eips', 'ebs', 'ebs2',  'dns', 'scripting',
			'timeouts', 'cloudwatch', 'euca', 'nimbula', 'ec2', 'servicesconfig', 'deployments', 'devel', 'storage'
		);
		
		if ($this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_CHEF)) {
			$moduleParams['tabs'][] = 'chef';
		}

		$moduleParams['tabParams'] = array(
			'farmId' => $farmId,
			'currentTimeZone' => $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_TIMEZONE),
			'currentTime' => Scalr_Util_DateTime::convertTz(time()),
			'currentEnvId' => $this->getEnvironmentId()
		);
		
		//TODO: Features
		$moduleParams['tabParams']['featureRAID'] = $this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_RAID);
		$moduleParams['tabParams']['featureMFS'] = $this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_MFS);

		$this->response->page('ui/farms/builder.js', $moduleParams, array(
			'ui/farms/builder/selroles.js',
			'ui/farms/builder/roleedit.js',
			'ui/farms/builder/allroles.js',
			'ui/farms/builder/tab.js',
			'ui/farms/builder/tabs/balancing.js',
			'ui/farms/builder/tabs/cloudwatch.js',
			'ui/farms/builder/tabs/dbmsr.js',
			'ui/farms/builder/tabs/cloudfoundry.js',
			'ui/farms/builder/tabs/rabbitmq.js',
			'ui/farms/builder/tabs/mongodb.js',
			'ui/farms/builder/tabs/haproxy.js',
			'ui/farms/builder/tabs/dns.js',
			'ui/farms/builder/tabs/ebs.js',
			'ui/farms/builder/tabs/ebs2.js',
			'ui/farms/builder/tabs/eips.js',
			'ui/farms/builder/tabs/euca.js',
			'ui/farms/builder/tabs/mysql.js',
			'ui/farms/builder/tabs/nimbula.js',
			'ui/farms/builder/tabs/params.js',
			'ui/farms/builder/tabs/placement.js',
			'ui/farms/builder/tabs/rsplacement.js',
			'ui/farms/builder/tabs/cloudstack.js',
			'ui/farms/builder/tabs/openstack.js',
			'ui/farms/builder/tabs/gce.js',
			'ui/farms/builder/tabs/rds.js',
			'ui/farms/builder/tabs/scaling.js',
			'ui/farms/builder/tabs/scripting.js',
			'ui/farms/builder/tabs/servicesconfig.js',
			'ui/farms/builder/tabs/timeouts.js',
			'ui/farms/builder/tabs/vpc.js',
			'ui/farms/builder/tabs/ec2.js',
			'ui/farms/builder/tabs/chef.js',
			'ui/farms/builder/tabs/deployments.js',
			'ui/farms/builder/tabs/devel.js',
			'ui/farms/builder/tabs/storage.js',
			'ui/scripts/scriptfield.js'
		), array(
			'ui/farms/builder/tabs/scripting.css',
			'ui/farms/builder/selroles.css',
			'ui/farms/builder/allroles.css'
		));
	}

	public function xLaunchAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int')
		));

		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
		$this->user->getPermissions()->validate($dbFarm);
		$dbFarm->isLocked();

		Scalr::FireEvent($dbFarm->ID, new FarmLaunchedEvent(true));

		$this->response->success('Farm successfully launched');
	}

	public function xRemoveAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int')
		));

		$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
		$this->user->getPermissions()->validate($dbFarm);
		$dbFarm->isLocked();

		if ($dbFarm->Status != FARM_STATUS::TERMINATED)
			throw new Exception(_("Cannot delete a running farm. Please terminate a farm before deleting it."));

		$servers = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id=? AND status!=?", array($dbFarm->ID, SERVER_STATUS::TERMINATED));
		if ($servers != 0)
			throw new Exception(sprintf(_("Cannot delete a running farm. %s server are still running on this farm."), $servers));

		$this->db->BeginTrans();

		try
		{
			foreach ($this->db->GetAll("SELECT * FROM farm_roles WHERE farmid = ?", array($dbFarm->ID)) as $value) {
				$this->db->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type = ?", array(
					$value['id'],
					Scalr_SchedulerTask::TARGET_ROLE
				));

				$this->db->Execute("DELETE FROM scheduler WHERE target_id LIKE '" . $value['id'] . ":%' AND target_type = ?", array(
					Scalr_SchedulerTask::TARGET_INSTANCE
				));
			}

			$this->db->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type = ?", array(
				$dbFarm->ID,
				Scalr_SchedulerTask::TARGET_FARM
			));

			$this->db->Execute("DELETE FROM farms WHERE id=?", array($dbFarm->ID));
			$this->db->Execute("DELETE FROM farm_role_settings WHERE farm_roleid IN (SELECT id FROM farm_roles WHERE farmid=?)", array($dbFarm->ID));
			$this->db->Execute("DELETE FROM farm_roles WHERE farmid=?", array($dbFarm->ID));
			$this->db->Execute("DELETE FROM logentries WHERE farmid=?", array($dbFarm->ID));
			$this->db->Execute("DELETE FROM elastic_ips WHERE farmid=?", array($dbFarm->ID));
			$this->db->Execute("DELETE FROM events WHERE farmid=?", array($dbFarm->ID));
			$this->db->Execute("DELETE FROM ec2_ebs WHERE farm_id=?", array($dbFarm->ID));

			$this->db->Execute("DELETE FROM farm_role_options WHERE farmid=?", array($dbFarm->ID));
			$this->db->Execute("DELETE FROM farm_role_scripts WHERE farmid=?", array($dbFarm->ID));
			$this->db->Execute("DELETE FROM ssh_keys WHERE farm_id=?", array($dbFarm->ID));



			//TODO: Remove servers
			$servers = $this->db->Execute("SELECT server_id FROM servers WHERE farm_id=?", array($dbFarm->ID));
			while ($server = $servers->FetchRow()) {
				$dbServer = DBServer::LoadByID($server['server_id']);
				$dbServer->Remove();
			}

			// Clean observers
			$observers = $this->db->Execute("SELECT * FROM farm_event_observers WHERE farmid=?", array($dbFarm->ID));
			while ($observer = $observers->FetchRow()) {
				$this->db->Execute("DELETE FROM farm_event_observers WHERE id=?", array($observer['id']));
				$this->db->Execute("DELETE FROM farm_event_observers_config WHERE observerid=?", array($observer['id']));
			}

			$this->db->Execute("UPDATE dns_zones SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($dbFarm->ID));
			$this->db->Execute("UPDATE apache_vhosts SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($dbFarm->ID));
		} catch(Exception $e) {
			$this->db->RollbackTrans();
			throw new Exception(_("Cannot delete farm at the moment ({$e->getMessage()}). Please try again later."));
		}

		$this->db->CommitTrans();

		$this->db->Execute("DELETE FROM scripting_log WHERE farmid=?", array($dbFarm->ID));
		
		$this->response->success('Farm successfully removed');
	}

	public function xListFarmsAction()
	{
		$this->request->defineParams(array(
			'clientId' => array('type' => 'int'),
			'farmId' => array('type' => 'int'),
			'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'asc'))
		));

		$sql = "SELECT clientid, id, name, status, dtadded, created_by_id, created_by_email FROM farms WHERE env_id='" . $this->getEnvironmentId() . "'";

		if ($this->getParam('farmId'))
			$sql .= " AND id=".$this->db->qstr($this->getParam('farmId'));

		if ($this->getParam('clientId'))
			$sql .= " AND clientid=".$this->db->qstr($this->getParam('clientId'));

		if ($this->getParam('status') != '')
			$sql .= " AND status=".$this->db->qstr($this->getParam('status'));;

		$response = $this->buildResponseFromSql($sql, array("name", "id", "comments"));

		foreach ($response["data"] as &$row) {
			$row["running_servers"] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id='{$row['id']}' AND status IN ('Pending', 'Initializing', 'Running', 'Temporary', 'Pending launch')");
			$row["non_running_servers"] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id='{$row['id']}' AND status NOT IN ('Pending', 'Initializing', 'Running', 'Temporary')");

			$row["roles"] = $this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE farmid='{$row['id']}'");
			$row["zones"] = $this->db->GetOne("SELECT COUNT(*) FROM dns_zones WHERE farm_id='{$row['id']}'");
			
			//TODO: Use Alerts class
			$row['alerts'] = $this->db->GetOne("SELECT COUNT(*) FROM server_alerts WHERE farm_id='{$row['id']}' AND status='failed'");

			$row['dtadded'] = Scalr_Util_DateTime::convertTz($row["dtadded"]);
			$dbFarm = DBFarm::LoadByID($row['id']);
			$row['lock'] = $dbFarm->GetSetting(DBFarm::SETTING_LOCK);
			if ($row['lock'])
				$row['lock_comment'] = $dbFarm->isLocked(false);

			$row["havemysqlrole"] = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::MYSQL, $row['id'], SERVER_PLATFORMS::RDS)
			);
			
			$row["havemysql2role"] = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::MYSQL2, $row['id'], SERVER_PLATFORMS::RDS)
			);
			
			$row["havepgrole"] = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::POSTGRESQL, $row['id'], SERVER_PLATFORMS::RDS)
			);

			$row["haveredisrole"] = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::REDIS, $row['id'], SERVER_PLATFORMS::RDS)
			);

			$row["haverabbitmqrole"] = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::RABBITMQ, $row['id'], SERVER_PLATFORMS::RDS)
			);
			
			$row["havemongodbrole"] = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
				array(ROLE_BEHAVIORS::MONGODB, $row['id'], SERVER_PLATFORMS::RDS)
			);
			
			$row["haveperconarole"] = (bool)$this->db->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? AND platform != ?",
					array(ROLE_BEHAVIORS::PERCONA, $row['id'], SERVER_PLATFORMS::RDS)
			);

			$row['status_txt'] = FARM_STATUS::GetStatusName($row['status']);

			if ($row['status'] == FARM_STATUS::RUNNING)
			{
				$row['shortcuts'] = $this->db->GetAll("SELECT * FROM farm_role_scripts WHERE farmid=? AND (farm_roleid IS NULL OR farm_roleid='0') AND ismenuitem='1'",
					array($row['id'])
				);
				foreach ($row['shortcuts'] as &$shortcut)
					$shortcut['name'] = $this->db->GetOne("SELECT name FROM scripts WHERE id=?", array($shortcut['scriptid']));
			}
		}

		$this->response->data($response);
	}
}
