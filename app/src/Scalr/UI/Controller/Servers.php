<?php

use Scalr\Server\Alerts;

class Scalr_UI_Controller_Servers extends Scalr_UI_Controller
{
	const CALL_PARAM_NAME = 'serverId';

	public function defaultAction()
	{
		$this->viewAction();
	}

	public function getList(array $status = array())
	{
		$retval = array();
		
		$sql = "SELECT * FROM servers WHERE env_id = ".$this->db->qstr($this->getEnvironmentId());
		if ($this->getParam('farmId'))
			$sql .= " AND farm_id = ".$this->db->qstr($this->getParam('farmId'));
			
		if ($this->getParam('farmRoleId'))
			$sql .= " AND farm_roleid = ".$this->db->qstr($this->getParam('farmRoleId'));
		
		if (!empty($status))
			$sql .= "AND status IN ('".implode("','", $status)."')";
			
		$s = $this->db->execute($sql);
		while ($server = $s->fetchRow()) {
			$retval[$server['server_id']] = $server;
		}

		return $retval;
	}
	
	public function xImportWaitHelloAction()
	{
		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		if ($dbServer->status != SERVER_STATUS::IMPORTING)
			throw new Exception('Server is not in importing state');

		$row = $this->db->GetRow("SELECT * FROM messages WHERE server_id = ? AND type = ?",
			array($dbServer->serverId, "in"));

		if ($row) {
			$bundleTaskId = $this->db->GetOne(
				"SELECT id FROM bundle_tasks WHERE server_id = ? ORDER BY dtadded DESC LIMIT 1",
				array($dbServer->serverId)
			);
		}

		if ($bundleTaskId) {
			$this->response->success('Communication successfully established. Role creation process has been initialized');
			$this->response->data(array('bundleTaskId' => $bundleTaskId));
		} else {
			$this->response->failure();
		}
	}

	public function xImportStartAction()
	{
		$validator = new Validator();

		if (!$this->getParam('remoteIp') && !$this->getParam('behavior'))
			$newImport = true;
		else
			$newImport = false;
		
		if (!$newImport) {
			if ($validator->IsDomain($this->getParam('remoteIp'))) {
				$remoteIp = @gethostbyname($this->getParam('remoteIp'));
			} else {
				$remoteIp = $this->getParam('remoteIp');
			}

			if (!$validator->IsIPAddress($remoteIp, _("Server IP address")))
				$err['remoteIp'] = 'Server IP address is incorrect';
				
			// Find server in the database
			$existingServer = $this->db->GetRow("SELECT * FROM servers WHERE remote_ip = ?", array($remoteIp));
			if ($existingServer["client_id"] == $this->user->getAccountId())
				$err['remoteIp'] = sprintf(_("Server %s is already in Scalr with a server_id: %s"), $remoteIp, $existingServer["server_id"]);
			else if ($existingServer)
				$err['remoteIp'] = sprintf(_("Server with selected IP address cannot be imported"));
		}

		if (!$validator->IsNotEmpty($this->getParam('roleName')))
			$err['roleName'] = 'Role name cannot be empty';

		if (strlen($this->getParam('roleName')) < 3)
			$err['roleName'] = _("Role name should be greater than 3 chars");

		if (! preg_match("/^[A-Za-z0-9-]+$/si", $this->getParam('roleName')))
			$err['roleName'] = _("Role name is incorrect");

		if ($this->db->GetOne("SELECT id FROM roles WHERE name=? AND (env_id = '0' OR env_id = ?)",
			array($this->getParam('roleName'), $this->getEnvironmentId()))
		)
			$err['roleName'] = 'Selected role name is already used. Please select another one.';

		if ($this->getParam('add2farm')) {

		}

		if (count($err) == 0) {
			$cryptoKey = Scalr::GenerateRandomKey(40);

			$creInfo = new ServerCreateInfo($this->getParam('platform'), null, 0, 0);
			$creInfo->clientId = $this->user->getAccountId();
			$creInfo->envId = $this->getEnvironmentId();
			$creInfo->farmId = (int)$this->getParam('farmId');
			$creInfo->remoteIp = $remoteIp;
			$creInfo->SetProperties(array(
				SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME => $this->getParam('roleName'),
				SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR => $this->getParam('behavior'),
				SERVER_PROPERTIES::SZR_KEY => $cryptoKey,
				SERVER_PROPERTIES::SZR_KEY_TYPE => SZR_KEY_TYPE::PERMANENT,
				SERVER_PROPERTIES::SZR_VESION => "0.9.r4482",
				SERVER_PROPERTIES::SZR_IMPORTING_OS_FAMILY => $this->getParam('os')
			));

			if ($this->getParam('platform') == SERVER_PLATFORMS::EUCALYPTUS)
				$creInfo->SetProperties(array(EUCA_SERVER_PROPERTIES::REGION => $this->getParam('cloudLocation')));

			if ($this->getParam('platform') == SERVER_PLATFORMS::RACKSPACE)
				$creInfo->SetProperties(array(RACKSPACE_SERVER_PROPERTIES::DATACENTER => $this->getParam('cloudLocation')));

			if ($this->getParam('platform') == SERVER_PLATFORMS::OPENSTACK)
				$creInfo->SetProperties(array(OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $this->getParam('cloudLocation')));

			if ($this->getParam('platform') == SERVER_PLATFORMS::NIMBULA)
				$creInfo->SetProperties(array(NIMBULA_SERVER_PROPERTIES::CLOUD_LOCATION => 'nimbula-default'));

			$dbServer = DBServer::Create($creInfo, true);
			$this->response->data(array('serverId' => $dbServer->serverId));
		} else {
			$this->response->failure();
			$this->response->data(array('errors' => $err));
		}
	}

	public function importCheckAction()
	{
		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		if ($dbServer->status != SERVER_STATUS::IMPORTING)
			throw new Exception('Server is not in importing state');

		$cryptoKey = $dbServer->GetKey();
		
		if (!$dbServer->remoteIp) {
			$options = array(
				'server-id' 	=> $dbServer->serverId,
				'role-name' 	=> $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME),
				'crypto-key' 	=> $cryptoKey,
				'platform' 		=> $dbServer->platform,
				'queryenv-url' 	=> CONFIG::$HTTP_PROTO."://".CONFIG::$EVENTHANDLER_URL."/query-env",
				'messaging-p2p.producer-url' => CONFIG::$HTTP_PROTO."://".CONFIG::$EVENTHANDLER_URL."/messaging",
				'env-id'		=> $dbServer->envId,
				'region'		=> $dbServer->GetCloudLocation()
			);
			
			$command = 'scalarizr --import -y';
		} else {
			$behavior = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR);
	
			$options = array(
				'server-id' 	=> $dbServer->serverId,
				'role-name' 	=> $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME),
				'crypto-key' 	=> $cryptoKey,
				'platform' 		=> $dbServer->platform,
				'behaviour' 	=> $behavior == ROLE_BEHAVIORS::BASE ? '' : $behavior,
				'queryenv-url' 	=> CONFIG::$HTTP_PROTO."://".CONFIG::$EVENTHANDLER_URL."/query-env",
				'messaging-p2p.producer-url' => CONFIG::$HTTP_PROTO."://".CONFIG::$EVENTHANDLER_URL."/messaging",
				'env-id'		=> $dbServer->envId,
				'region'=> $dbServer->GetCloudLocation()
			);
			
			if ($dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OS_FAMILY) != 'windows')
				$command = 'scalarizr --import -y';
			else
				$command = 'C:\Program Files\Scalarizr\scalarizr.bat --import -y';
		}

		foreach ($options as $k => $v) {
			$command .= sprintf(' -o %s=%s', $k, $v);
		}

		$this->response->page('ui/servers/import_step2.js', array(
			'serverId' => $this->getParam('serverId'),
			'cmd'	   => $command
		));
	}

	public function import2Action()
	{
		
		$platforms = array();
		$env = Scalr_Environment::init()->loadById($this->getEnvironmentId());
		$enabledPlatforms = $env->getEnabledPlatforms();
		foreach (SERVER_PLATFORMS::getList() as $k => $v) {
			if (in_array($k, $enabledPlatforms)) {
				
				if ($k == 'rds')
					continue;
				
				$platforms[] = array($k, $v);
				foreach (PlatformFactory::NewPlatform($k)->getLocations() as $lk=>$lv)
					$locations[$k][] = array('id' => $lk, 'name' => $lv);
			}
		}
		unset($platforms['rds']);

		$this->response->page('ui/servers/import_step1_2.js', array(
			'platforms' 	=> $platforms,
			'locations'		=> $locations
		));
	}

	public function importAction()
	{
		$behaviors = array(
			array(ROLE_BEHAVIORS::BASE, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::BASE)),
			array(ROLE_BEHAVIORS::APACHE, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::APACHE)),
			array(ROLE_BEHAVIORS::MYSQL, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::MYSQL)),
			array(ROLE_BEHAVIORS::MYSQL2, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::MYSQL2)),
			array(ROLE_BEHAVIORS::PERCONA, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::PERCONA)),
			array(ROLE_BEHAVIORS::NGINX, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::NGINX)),
			array(ROLE_BEHAVIORS::MEMCACHED, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::MEMCACHED)),
			array(ROLE_BEHAVIORS::POSTGRESQL, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::POSTGRESQL)),
			array(ROLE_BEHAVIORS::REDIS, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::REDIS)),
			array(ROLE_BEHAVIORS::RABBITMQ, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::RABBITMQ)),
			array(ROLE_BEHAVIORS::MONGODB, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::MONGODB))
		);
		
		if ($this->getParam('beta') == 1) {
			array_push($behaviors, array(ROLE_BEHAVIORS::HAPROXY, ROLE_BEHAVIORS::GetName(ROLE_BEHAVIORS::HAPROXY)));
		}
		
		$euca_locations = array();
		$rs_locations = array();

		$platforms = array();
		$env = Scalr_Environment::init()->loadById($this->getEnvironmentId());
		$enabledPlatforms = $env->getEnabledPlatforms();
		foreach (SERVER_PLATFORMS::getList() as $k => $v) {
			if (in_array($k, $enabledPlatforms)) {
				
				if ($k == 'rds')
					continue;
				
				$platforms[] = array($k, $v);
				if ($k == SERVER_PLATFORMS::EUCALYPTUS) {
					foreach (PlatformFactory::NewPlatform($k)->getLocations() as $lk=>$lv)
						$euca_locations[$lk] = array('id' => $lk, 'name' => $lv);
				}
				elseif ($k == SERVER_PLATFORMS::RACKSPACE) {
					foreach (PlatformFactory::NewPlatform($k)->getLocations() as $lk=>$lv)
						$rs_locations[$lk] = array('id' => $lk, 'name' => $lv);
				}
				elseif ($k == SERVER_PLATFORMS::OPENSTACK) {
					foreach (PlatformFactory::NewPlatform($k)->getLocations() as $lk=>$lv)
						$os_locations[$lk] = array('id' => $lk, 'name' => $lv);
				}
			}
		}
		unset($platforms['rds']);

		$this->response->page('ui/servers/import_step1.js', array(
			'platforms' 	=> $platforms,
			'behaviors'		=> $behaviors,
			'euca_locations'=> $euca_locations,
			'rs_locations'	=> $rs_locations,
			'os_locations'	=> $os_locations,
		));
	}

	public function xResendMessageAction()
	{
		$message = $this->db->GetRow("SELECT * FROM messages WHERE server_id=? AND messageid=?",array(
			$this->getParam('serverId'), $this->getParam('messageId')
		));

		if ($message) {
			$serializer = new Scalr_Messaging_XmlSerializer();

			$msg = $serializer->unserialize($message['message']);

			$dbServer = DBServer::LoadByID($this->getParam('serverId'));
			$this->user->getPermissions()->validate($dbServer);

			if (in_array($dbServer->status, array(SERVER_STATUS::RUNNING, SERVER_STATUS::INIT))) {
				$this->db->Execute("UPDATE messages SET status=?, handle_attempts='0' WHERE id=?", array(MESSAGE_STATUS::PENDING, $message['id']));
				$dbServer->SendMessage($msg);
			}
			else
				throw new Exception("Scalr unable to re-send message. Server should be in running state.");

			$this->response->success('Message successfully re-sent to the server');
		} else {
			throw new Exception("Message not found");
		}
	}

	public function xListMessagesAction()
	{
		$this->request->defineParams(array(
			'serverId',
			'sort' => array('type' => 'string', 'default' => 'id'),
			'dir' => array('type' => 'string', 'default' => 'DESC')
		));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$sql = "SELECT *, message_name as message_type FROM messages WHERE server_id='{$dbServer->serverId}'";
		$response = $this->buildResponseFromSql($sql, array("server_id", "message", "messageid"));

		foreach ($response["data"] as &$row) {
			
			if (!$row['message_type']) {
				preg_match("/^<\?xml [^>]+>[^<]*<message(.*?)name=\"([A-Za-z0-9_]+)\"/si", $row['message'], $matches);
				$row['message_type'] = $matches[2];
			}
				
			$row['message'] = '';
			$row['dtlasthandleattempt'] = Scalr_Util_DateTime::convertTz($row['dtlasthandleattempt']);
		}

		$this->response->data($response);
	}

	public function messagesAction()
	{
		$this->response->page('ui/servers/messages.js', array('serverId' => $this->getParam('serverId')));
	}

	public function viewAction()
	{
		$this->response->page('ui/servers/view.js');
	}

	public function sshConsoleAction()
	{
		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		if ($dbServer->remoteIp) {
			$dBFarm = $dbServer->GetFarmObject();
			$dbRole = DBRole::loadById($dbServer->roleId);

			$sshPort = $dbRole->getProperty(DBRole::PROPERTY_SSH_PORT);
			if (!$sshPort)
				$sshPort = 22;
			
			$cSshPort = $dbServer->GetProperty(SERVER_PROPERTIES::CUSTOM_SSH_PORT);
			if ($cSshPort)
				$sshPort = $cSshPort; 

			$sshKey = Scalr_SshKey::init()->loadGlobalByFarmId(
				$dbServer->farmId,
				$dbServer->GetFarmRoleObject()->CloudLocation,
				$dbServer->platform
			);

			$this->response->page('ui/servers/sshconsole.js', array(
				'serverId' => $dbServer->serverId,
				'remoteIp' => $dbServer->remoteIp,
				'localIp' => $dbServer->localIp,
				'farmName' => $dBFarm->Name,
				'farmId' => $dbServer->farmId,
				'roleName' => $dbRole->name,
				'port' => $sshPort,
				'username' => $dbServer->platform == SERVER_PLATFORMS::GCE ? 'scalr' : 'root',
				"key" => base64_encode($sshKey->getPrivate())
			));
		}
		else
			throw new Exception(_("Server not initialized yet"));
	}

	public function xServerCancelOperationAction()
	{
		$this->request->defineParams(array(
			'serverId'
		));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$bt_id = $this->db->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND
			prototype_role_id='0' AND status NOT IN (?,?,?)", array(
			$dbServer->serverId,
			SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
			SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
			SERVER_SNAPSHOT_CREATION_STATUS::CANCELLED
		));
		if ($bt_id) {
			$BundleTask = BundleTask::LoadById($bt_id);
			$BundleTask->SnapshotCreationFailed("Server was terminated before snapshot was created.");
		}

		try {
			if ($dbServer->status == SERVER_STATUS::TEMPORARY) {
				if (PlatformFactory::NewPlatform($dbServer->platform)->IsServerExists($dbServer))
					PlatformFactory::NewPlatform($dbServer->platform)->TerminateServer($dbServer);
				
				Scalr_Server_History::init($dbServer)->markAsTerminated("Cancelled snapshotting operation");
			}
		} catch (Exception $e) {}

		$dbServer->Delete();

		$this->response->success("Server importing successfully canceled. Server removed from database.");
	}

	public function xUpdateUpdateClientAction()
	{
		$this->request->defineParams(array(
			'serverId'
		));
		
		if (!$this->db->GetOne("SELECT id FROM scripts WHERE id='3803' AND clientid='0'"))
			throw new Exception("Automatical scalarizr update doesn't supported by this scalr version");
		
		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);
		
		$eventName = Scalr_Scripting_Manager::generateEventName('CustomEvent');
		$target = SCRIPTING_TARGET::INSTANCE;
		$serverId = $dbServer->serverId;
		$farmRoleId = $dbServer->farmRoleId;
		$farmId = $dbServer->farmId;
		
		$this->db->Execute("INSERT INTO farm_role_scripts SET
			scriptid	= ?,
			farmid		= ?,
			farm_roleid	= ?,
			params		= ?,
			event_name	= ?,
			target		= ?,
			version		= ?,
			timeout		= ?,
			issync		= ?,
			ismenuitem	= ?
		", array(
			3803,
			(int)$farmId,
			(int)$farmRoleId,
			serialize(array()),
			$eventName,
			$target,
			$this->db->GetOne("SELECT MAX(revision) FROM script_revisions WHERE scriptid='3803'"),
			300,
			0,
			0
		));
		
		$farmScriptId = $this->db->Insert_ID();
		
		$message = new Scalr_Messaging_Msg_ExecScript($eventName);
		$message->meta[Scalr_Messaging_MsgMeta::EVENT_ID] = "FRSID-{$farmScriptId}";
		
		$message = Scalr_Scripting_Manager::extendMessage($message, $dbServer);
		
		$dbServer->SendMessage($message);
		
		$this->response->success('Scalarizr update-client update successfully initiated');
	}
	
	public function xUpdateAgentAction()
	{
		$this->request->defineParams(array(
			'serverId'
		));

		if (!$this->db->GetOne("SELECT id FROM scripts WHERE id='2102' AND clientid='0'"))
			throw new Exception("Automatical scalarizr update doesn't supported by this scalr version");
		
		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$eventName = Scalr_Scripting_Manager::generateEventName('CustomEvent');
		$target = SCRIPTING_TARGET::INSTANCE;
		$serverId = $dbServer->serverId;
		$farmRoleId = $dbServer->farmRoleId;
		$farmId = $dbServer->farmId;
	
		$this->db->Execute("INSERT INTO farm_role_scripts SET
			scriptid	= ?,
			farmid		= ?,
			farm_roleid	= ?,
			params		= ?,
			event_name	= ?,
			target		= ?,
			version		= ?,
			timeout		= ?,
			issync		= ?,
			ismenuitem	= ?
		", array(
			2102,
			(int)$farmId,
			(int)$farmRoleId,
			serialize(array()),
			$eventName,
			$target,
			$this->db->GetOne("SELECT MAX(revision) FROM script_revisions WHERE scriptid='2102'"),
			300,
			0,
			0
		));

		$farmScriptId = $this->db->Insert_ID();

		$message = new Scalr_Messaging_Msg_ExecScript($eventName);
		$message->meta[Scalr_Messaging_MsgMeta::EVENT_ID] = "FRSID-{$farmScriptId}";
		
		$message = Scalr_Scripting_Manager::extendMessage($message, $dbServer);
		
		$dbServer->SendMessage($message);

		$this->response->success('Scalarizr update successfully initiated. Please wait a few minutes and then refresh the page');
	}
	
	public function xListServersAction()
	{
		$this->request->defineParams(array(
			'roleId' => array('type' => 'int'),
			'farmId' => array('type' => 'int'),
			'farmRoleId' => array('type' => 'int'),
			'serverId',
			'hideTerminated' => array('type' => 'bool'),
			'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'ASC'))
		));

		$sql = "SELECT servers.*, farms.name AS farm_name, roles.name AS role_name FROM servers LEFT JOIN farms ON servers.farm_id = farms.id
				LEFT JOIN roles ON roles.id = servers.role_id WHERE servers.env_id='{$this->getEnvironmentId()}'";

		if ($this->getParam('farmId'))
			$sql .= " AND farm_id='{$this->getParam('farmId')}'";

		if ($this->getParam('farmRoleId'))
			$sql .= " AND farm_roleid='{$this->getParam('farmRoleId')}'";

		if ($this->getParam('roleId'))
			$sql .= " AND role_id='{$this->getParam('roleId')}'";

		if ($this->getParam('serverId'))
			$sql .= " AND server_id={$this->db->qstr($this->getParam('serverId'))}";

		if ($this->getParam('hideTerminated'))
			$sql .= " AND servers.status != '".SERVER_STATUS::TERMINATED."'";

		$response = $this->buildResponseFromSql($sql, array("server_id", "farm_id", "`farms`.`name`", "remote_ip", "local_ip", "`servers`.`status`"), NULL, false);

		foreach ($response["data"] as &$row) {
			try {
				$dbServer = DBServer::LoadByID($row['server_id']);

				$row['cloud_server_id'] = $dbServer->GetCloudServerID();
				$row['ismaster'] = $dbServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER) || $dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER);

				$row['cloud_location'] = $dbServer->GetCloudLocation();
				if ($dbServer->platform == SERVER_PLATFORMS::EC2) {
					$loc = $dbServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE);
					if ($loc && $loc != 'x-scalr-diff')
						$row['cloud_location'] .= "/".substr($loc, -1, 1);
				}

				if ($dbServer->platform == SERVER_PLATFORMS::EC2) {
					$row['has_eip'] = $this->db->GetOne("SELECT id FROM elastic_ips WHERE server_id = ?", array($dbServer->serverId));
				}
				
				if ($dbServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
					$shardIndex = $dbServer->GetProperty(Scalr_Role_Behavior_MongoDB::SERVER_SHARD_INDEX);
					$replicaSetIndex = $dbServer->GetProperty(Scalr_Role_Behavior_MongoDB::SERVER_REPLICA_SET_INDEX);
					$row['cluster_position'] = "{$shardIndex}-{$replicaSetIndex}";
				}
			}
			catch(Exception $e){  }

			$rebooting = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=?", array(
				$row['server_id'], SERVER_PROPERTIES::REBOOTING
			));
			if ($rebooting) {
				$row['status'] = "Rebooting";
			}

			$subStatus = $dbServer->GetProperty(SERVER_PROPERTIES::SUB_STATUS);
			if ($subStatus) {
				$row['status'] = ucfirst($subStatus);
			}

			$row['is_szr'] = $dbServer->IsSupported("0.5");
			$row['initDetailsSupported'] = $dbServer->IsSupported("0.7.181");
			
			if ($dbServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED) && in_array($dbServer->status, array(SERVER_STATUS::INIT, SERVER_STATUS::PENDING)))
				$row['isInitFailed'] = 1;
			
			$launchError = $dbServer->GetProperty(SERVER_PROPERTIES::LAUNCH_ERROR);
			if ($launchError)
				$row['launch_error'] = "1";
				
			$serverAlerts = new Alerts($dbServer);
				
			$row['agent_version'] = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_VESION);
			$row['agent_update_needed'] = $dbServer->IsSupported("0.7") && !$dbServer->IsSupported("0.7.189");
			$row['agent_update_manual'] = !$dbServer->IsSupported("0.5"); 
			$row['os_family'] = $dbServer->GetOsFamily();
			$row['flavor'] = $dbServer->GetFlavor();
			$row['alerts'] = $serverAlerts->getActiveAlertsCount();
			if (!$row['flavor'])
				$row['flavor'] = '';
			
			if ($dbServer->status == SERVER_STATUS::RUNNING) {
				$tm = (int)$dbServer->GetProperty(SERVER_PROPERTIES::INITIALIZED_TIME);

				if (!$tm)
					$tm = (int)strtotime($row['dtadded']);

				if ($tm > 0) {
					$row['uptime'] = Formater::Time2HumanReadable(time() - $tm, false);
				}
			}
			else
				$row['uptime'] = '';

			$r_dns = $this->db->GetOne("SELECT value FROM farm_role_settings WHERE farm_roleid=? AND `name`=?", array(
				$row['farm_roleid'], DBFarmRole::SETTING_EXCLUDE_FROM_DNS
			));

			$row['excluded_from_dns'] = (!$dbServer->GetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS) && !$r_dns) ? false : true;
		}

		$this->response->data($response);
	}

	public function xListServersUpdateAction()
	{
		$this->request->defineParams(array(
			'servers' => array('type' => 'json')
		));

		$retval = array();
		$sql = array();
		foreach ($this->getParam('servers') as $serverId)
			$sql[] = $this->db->qstr($serverId);

		if (count($sql)) {
			foreach ($this->db->GetAll('SELECT server_id, status, remote_ip, local_ip FROM servers WHERE server_id IN (' . join($sql, ',') . ') AND env_id = ?', array($this->getEnvironmentId())) as $server) {
				
				$rebooting = $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=?", array(
					$server['server_id'], SERVER_PROPERTIES::REBOOTING
				));
				if ($rebooting) {
					$server['status'] = "Rebooting";
				}
	
				$subStatus =  $this->db->GetOne("SELECT value FROM server_properties WHERE server_id=? AND `name`=?", array(
					$server['server_id'], SERVER_PROPERTIES::SUB_STATUS
				));
				if ($subStatus) {
					$server['status'] = ucfirst($subStatus);
				}
				
				$retval[$server['server_id']] = $server;
			}
		}

		$this->response->data(array(
			'servers' => $retval
		));
	}

	public function xSzrUpdateAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);
		
		$updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer);
		$status = $updateClient->updateScalarizr();
		
		$this->response->success('Scalarizr successfully updated to the latest version');
	}
	
	public function xSzrRestartAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);
		
		$updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer);
		$status = $updateClient->restartScalarizr();
		
		$this->response->success('Scalarizr successfully restarted');
	}
	
	public function extendedInfoAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);
        
		$info = PlatformFactory::NewPlatform($dbServer->platform)->GetServerExtendedInformation($dbServer);
		$form = array(
			array(
				'xtype' => 'container',
				'layout' => array(
					'type' => 'hbox',
					'align' => 'stretchmax'
				),
				'cls' => 'x-container-form-item',
				'hideLabel' => true,
				'items' => array(
					array(
					'xtype' => 'fieldset',
					'title' => 'General',
					'flex'  => 1,
					'defaults' => array(
						'labelWidth' => 100
					),
					'items' => array(
						array(
							'xtype' => 'displayfield',
							'fieldLabel' => 'Server ID',
							'value' => $dbServer->serverId
						),
						array(
							'xtype' => 'displayfield',
							'fieldLabel' => 'Platform',
							'value' => $dbServer->platform
						),
						array(
							'xtype' => 'displayfield',
							'fieldLabel' => 'Remote IP',
							'value' => ($dbServer->remoteIp) ? $dbServer->remoteIp : ''
						),
						array(
							'xtype' => 'displayfield',
							'fieldLabel' => 'Local IP',
							'value' => ($dbServer->localIp) ? $dbServer->localIp : ''
						),
						array(
							'xtype' => 'displayfield',
							'fieldLabel' => 'Status',
							'value' => $dbServer->status
						),
						array(
							'xtype' => 'displayfield',
							'fieldLabel' => 'Index',
							'value' => $dbServer->index
						),
						array(
							'xtype' => 'displayfield',
							'fieldLabel' => 'Added at',
							'value' => Scalr_Util_DateTime::convertTz($dbServer->dateAdded)
						)
					)
				))
			)
		);
		
		/***** Scalr agent *****/
		if ($dbServer->status == SERVER_STATUS::RUNNING) {
			try {
				$port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_UPDC_PORT);
				if (!$port)
					$port = 8008;
				
				$updateClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer, $port);
				$status = $updateClient->getStatus();
			} catch (Exception $e) {
				$oldUpdClient = stristr($e->getMessage(), "Method not found");
				$error = $e->getMessage();
			}
			
			if ($status) {
				$items = array(
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Scalarizr status',
						'value' => $status->service_status == 'running' ? "<span style='color:green;'>Running</span>" : "<span style='color:red;'>".ucfirst($status->service_status)."</span>"
					),
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Version',
						'value' => $status->installed
					),
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Repository',
						'value' => ucfirst($status->repository)
					),
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Last update',
						'value' => Scalr_Util_DateTime::convertTz($status->executed_at)
					),
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Last update status',
						'value' => $status->error ? "<span style='color:red;'>Error: ".nl2br($status->error)."</span>" : "<span style='color:green;'>Success</span>"
					),
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Next update',
						'value' => ($status->installed != $status->candidate) ? "Update to <b>{$status->candidate}</b> scheduled on <b>".Scalr_Util_DateTime::convertTz($status->scheduled_on)."</b>" : "Scalarizr is up to date"
					),
					array(
						'xtype' => 'displayfield',
						'fieldLabel' => 'Schedule',
						'value' => $status->schedule
					),
					array(
						'xtype' => 'fieldcontainer',
						'layout' => 'hbox',
						'hideLabel' => true,
						'items' => array(
							array(
								'xtype' => 'button',
								'itemId' => 'updateSzrBtn',
								'text' => 'Update scalarizr now',
								'disabled' => ($status->installed == $status->candidate),
								'flex' => 1
							),
							array(
								'xtype' => 'button',
								'itemId' => 'restartSzrBtn',
								'text' => 'Restart scalarizr',
								'flex' => 1,
								'margin' => '0 0 0 5'
							)	
						)
					)
				);
			} else {
				if ($oldUpdClient) {
					$items = array(array(
						'xtype' => 'button',
						'itemId' => 'upgradeUpdClientBtn',
						'text' => 'Upgrade scalarizr upd-client',
						'flex' => 1
					));
				} else {
					$items = array(array(
						'xtype' => 'displayfield',
						'hideLabel' => true,
						'value' => "<span style='color:red;'>Scalarizr status is not available: {$error}</span>"
					));
				}
			}
			
			$form[0]['items'][] = array(
				'xtype' => 'fieldset',
				'labelWidth' => 240,
				'flex'   => 1,
				'margin' => '0 0 0 10',
				'title' => 'Scalr agent status',
				'items' => $items
			);
		}
		/***** Scalr agent *****/
		

		$it = array();
		if (is_array($info) && count($info)) {
			foreach ($info as $name => $value) {
				$it[] = array(
					'xtype' => 'displayfield',
					'fieldLabel' => $name,
					'value' => $value
				);
			}
		} else {
			$it[] = array(
				'xtype' => 'displayfield',
				'hideLabel' => true,
				'value' => 'Platform specific details not available for this server'
			);
		}

		$form[] = array(
			'xtype' => 'fieldset',
			'labelWidth' => 240,
			'title' => 'Platform specific details',
			'collapsible' => true, 
			'collapsed' => false,
			'items' => $it
		);

/*

<tr>
	<td width="20%">CloudWatch monitoring:</td>
	<td>{if $info->instancesSet->item->monitoring->state == 'enabled'}
			<a href="/aws_cw_monitor.php?ObjectId={$info->instancesSet->item->instanceId}&Object=InstanceId&NameSpace=AWS/EC2">{$info->instancesSet->item->monitoring->state}</a>
			&nbsp;(<a href="aws_ec2_cw_manage.php?action=Disable&iid={$info->instancesSet->item->instanceId}&region={$smarty.request.region}">Disable</a>)
		{else}
			{$info->instancesSet->item->monitoring->state}
			&nbsp;(<a href="aws_ec2_cw_manage.php?action=Enable&iid={$info->instancesSet->item->instanceId}&region={$smarty.request.region}">Enable</a>)
		{/if}
	</td>
</tr>
-->
{include file="inc/intable_footer.tpl" color="Gray"}
*/


		if (count($dbServer->GetAllProperties())) {
			$it = array();
			foreach ($dbServer->GetAllProperties() as $name => $value) {
				$it[] = array(
					'xtype' => 'displayfield',
					'fieldLabel' => $name,
					'value' => $value
				);
			}

			$form[] = array(
				'xtype' => 'fieldset',
				'title' => 'Scalr internal server properties',
				'collapsible' => true, 
				'collapsed' => true,
				'labelWidth' => 220,
				'items' => $it
			);
		}


		if (!$dbServer->IsSupported('0.5'))
		{
			$authKey = $dbServer->GetKey();
			if (!$authKey) {
				$authKey = Scalr::GenerateRandomKey(40);
				$dbServer->SetProperty(SERVER_PROPERTIES::SZR_KEY, $authKey);
			}

			$dbServer->SetProperty(SERVER_PROPERTIES::SZR_KEY_TYPE, SZR_KEY_TYPE::PERMANENT);

			$form[] = array(
				'xtype' => 'fieldset',
				'title' => 'Upgrade from ami-scripts to scalarizr',
				'labelWidth' => 220,
				'items' => array(
					'xtype' => 'textarea',
					'hideLabel' => true,
					'readOnly' => true,
					'anchor' => '-20',
					'value' => sprintf("wget ".CONFIG::$HTTP_PROTO."://".CONFIG::$EVENTHANDLER_URL."/storage/scripts/amiscripts-to-scalarizr.py && python amiscripts-to-scalarizr.py -s %s -k %s -o queryenv-url=%s -o messaging_p2p.producer_url=%s",
						$dbServer->serverId,
						$authKey,
						CONFIG::$HTTP_PROTO."://".CONFIG::$EVENTHANDLER_URL."/query-env",
						CONFIG::$HTTP_PROTO."://".CONFIG::$EVENTHANDLER_URL."/messaging"
					)
			));
		}


		$this->response->page('ui/servers/extendedinfo.js', $form);
	}

	public function operationDetailsAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);
		
		$operation = 'Initialization';
		
		$details = array();
		$details['Boot OS']['status'] = 'running';
		$details['Start & Initialize scalarizr']['status'] = 'pending';
		
		try {
			if ($dbServer->GetRealStatus(true)->isRunning()) {
				$details['Boot OS']['status'] = 'complete';
				
				if ($dbServer->status == SERVER_STATUS::PENDING)
					$details['Start & Initialize scalarizr']['status'] = 'running';
				else
					$details['Start & Initialize scalarizr']['status'] = 'complete';
			}
		} catch (Exception $e) {
			if ($dbServer->status == SERVER_STATUS::PENDING_LAUNCH) {
				$details['Boot OS']['status'] = 'pending';
				$details['Start & Initialize scalarizr']['status'] = 'pending';
			} else {
				$details['Boot OS']['status'] = 'complete';
				$details['Start & Initialize scalarizr']['status'] = 'complete';
			}
		}
		
		$operation = $this->db->GetRow("SELECT * FROM server_operations WHERE server_id = ? AND name = ?", array($dbServer->serverId, $operation));
		$intSteps = $this->db->GetOne("SELECT COUNT(*) FROM server_operation_progress WHERE operation_id = ? AND phase = ? ORDER BY stepno ASC", array($operation['id'], 'Scalarizr routines'));
		$phases = json_decode($operation['phases']);		
		if ($intSteps > 0) {
			$c = new stdClass();
			$c->name = "Scalarizr routines";
			$c->steps = array();
			array_push($phases, $c);
		}
		
		foreach ($phases as $phase) {
			$definedSteps = $phase->steps;
			$stats = array();
		
			$steps = $this->db->Execute("SELECT step, status, progress, message FROM server_operation_progress WHERE operation_id = ? AND phase = ? ORDER BY stepno ASC", array($operation['id'], $phase->name));
			while ($step = $steps->FetchRow()) {
				$details[$phase->name]['steps'][$step['step']] = array(
					'status' => $step['status'],
					'progress' => $step['progress'],
					'message' => nl2br($step['message']),
				);
				
				switch($step['status']) {
					case "running":
						$stats['pending']--;
						$stats['running']++;
					break;
					case "complete":
					case "warning":
						$stats['pending']--;
						$stats['complete']++;
					break;
					case "error":
						$stats['error']++;
					break;
				}
			}
			
			foreach ($definedSteps as $step) {
				if (!$details[$phase->name]['steps'][$step]) {
					$details[$phase->name]['steps'][$step] = array('status' => 'pending');
					$stats['pending']++;
				}
			}
			
			if ($stats['error'] > 0)
				$details[$phase->name]['status'] = 'error';
			elseif ($stats['running'] > 0 || ($stats['pending'] > 0 && $stats['complete'] > 0))
				$details[$phase->name]['status'] = 'running';
			elseif ($stats['pending'] <= 0 && $stats['running'] == 0 && count($details[$phase->name]['steps']) != 0)
				$details[$phase->name]['status'] = 'complete';
			else
				$details[$phase->name]['status'] = 'pending';
		}
		
		//scalr-operation-status-
		$content = '<div style="margin:10px;">';
		foreach ($details as $phaseName => $phase) {
			$cont = ($phase['status'] != 'running') ? "&nbsp;" : "<img src='/ui2/images/icons/running.gif' />";
			$content .= "<div style='clear:both;'><div class='scalr-operation-status-{$phase['status']}'>{$cont}</div> {$phaseName}</div>";
				
			foreach ($phase['steps'] as $stepName => $step) {
				$cont = ($step['status'] != 'running') ? "&nbsp;" : "<img src='/ui2/images/icons/running.gif' />";
				$content .= "<div style='clear:both;padding-left:15px;'><div class='scalr-operation-status-{$step['status']}'>{$cont}</div> {$stepName}</div>";
				
				if ($step['status'] == 'error')
					$message = $step['message'];
			}
		}
		$content .= '</div>';
		
		$status = ($dbServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED)) ? '<span style="color:red;">Initialization failed</span>' : $dbServer->status;
		
		$this->response->page('ui/servers/operationdetails.js', array(
			'serverId' => $dbServer->serverId,
			'status' => $status,
			'content' => $content,
			'message' => $message
		));
	}
	
	public function consoleOutputAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$output = PlatformFactory::NewPlatform($dbServer->platform)->GetServerConsoleOutput($dbServer);

		if ($output) {
			$output = trim(base64_decode($output));
			$output = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $output);
			$output = nl2br($output);

			$output = str_replace("\033[74G", "</span>", $output);
			$output = str_replace("\033[39;49m", "</span>", $output);
			$output = str_replace("\033[80G <br />", "<span style='padding-left:20px;'></span>", $output);
			$output = str_replace("\033[80G", "<span style='padding-left:20px;'>&nbsp;</span>", $output);
			$output = str_replace("\033[31m", "<span style='color:red;'>", $output);
			$output = str_replace("\033[33m", "<span style='color:brown;'>", $output);
		} else
			$output = 'Console output not available yet';

		$this->response->page('ui/servers/consoleoutput.js', array(
			'name' => $dbServer->serverId,
			'content' => $output
		));
	}

	public function xServerExcludeFromDnsAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$dbServer->SetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS, 1);

		$zones = DBDNSZone::loadByFarmId($dbServer->farmId);
		foreach ($zones as $DBDNSZone)
		{
			$DBDNSZone->updateSystemRecords($dbServer->serverId);
			$DBDNSZone->save();
		}

		$this->response->success("Server successfully removed from DNS");
	}

	public function xServerIncludeInDnsAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$dbServer->SetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS, 0);

		$zones = DBDNSZone::loadByFarmId($dbServer->farmId);
		foreach ($zones as $DBDNSZone)
		{
			$DBDNSZone->updateSystemRecords($dbServer->serverId);
			$DBDNSZone->save();
		}

		$this->response->success("Server successfully added to DNS");
	}

	public function xServerCancelAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$bt_id = $this->db->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND
			prototype_role_id='0' AND status NOT IN (?,?,?)", array(
			$dbServer->serverId,
			SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
			SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
			SERVER_SNAPSHOT_CREATION_STATUS::CANCELLED
		));

		if ($bt_id) {
			$BundleTask = BundleTask::LoadById($bt_id);
			$BundleTask->SnapshotCreationFailed("Server was cancelled before snapshot was created.");
		}

		$dbServer->Delete();
		$this->response->success("Server successfully cancelled and removed from database.");
	}

	public function xServerRebootServersAction()
	{
		$this->request->defineParams(array(
			'servers' => array('type' => 'json')
		));

		foreach ($this->getParam('servers') as $serverId) {
			try {
				$dbServer = DBServer::LoadByID($serverId);
				$this->user->getPermissions()->validate($dbServer);

				PlatformFactory::NewPlatform($dbServer->platform)->RebootServer($dbServer);
			}
			catch (Exception $e) {}
		}

		$this->response->success();
	}

	public function xServerTerminateServersAction()
	{
		$this->request->defineParams(array(
			'servers' => array('type' => 'json'),
			'descreaseMinInstancesSetting' => array('type' => 'bool'),
			'forceTerminate' => array('type' => 'bool')
		));

		foreach ($this->getParam('servers') as $serverId) {
			$dbServer = DBServer::LoadByID($serverId);
			$this->user->getPermissions()->validate($dbServer);

			if (! $this->getParam('forceTerminate')) {
				Logger::getLogger(LOG_CATEGORY::FARM)->info(new FarmLogMessage($dbServer->farmId,
					sprintf("Scheduled termination for server %s (%s). It will be terminated in 3 minutes.",
						$dbServer->serverId,
						$dbServer->remoteIp
				)
				));
			}
			
			Scalr::FireEvent($dbServer->farmId, new BeforeHostTerminateEvent($dbServer, $this->getParam('forceTerminate')));

			Scalr_Server_History::init($dbServer)->markAsTerminated("Manually terminated via UI");
		}

		if ($this->getParam('descreaseMinInstancesSetting')) {
			$servers = $this->getParam('servers');
			$dbServer = DBServer::LoadByID($servers[0]);
			$dbFarmRole = $dbServer->GetFarmRoleObject();

			$minInstances = $dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MIN_INSTANCES);
			if ($minInstances > count($servers)) {
				$dbFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_MIN_INSTANCES,
					$minInstances - count($servers)
				);
			}
		}

		$this->response->success();
	}

	public function xServerGetLaAction()
	{
		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$snmpClient = new Scalr_Net_Snmp_Client();

		$port = 161;
		if ($dbServer->GetProperty(SERVER_PROPERTIES::SZR_SNMP_PORT))
			$port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_SNMP_PORT);

		$snmpClient->connect($dbServer->remoteIp, $port, $dbServer->GetFarmObject()->Hash);

		$this->response->data(array('la' => trim($snmpClient->get('.1.3.6.1.4.1.2021.10.1.3.1'), '"')));
	}

	public function createSnapshotAction()
	{
		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$dbFarmRole = $dbServer->GetFarmRoleObject();

		if ($dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
			$this->response->warning("You are about to synchronize MySQL instance. The bundle will not include DB data. <a href='#/dbmsr/status?farmId={$dbServer->farmId}&type=mysql'>Click here if you wish to bundle and save DB data</a>.");
			
			if (!$dbServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER)) {
				$dbSlave = true;
			}
		}
		
		$dbMsrBehavior = $dbFarmRole->GetRoleObject()->getDbMsrBehavior();
		if ($dbMsrBehavior) {
			
			if ($dbMsrBehavior == ROLE_BEHAVIORS::MYSQL2 || $dbMsrBehavior == ROLE_BEHAVIORS::PERCONA)
				$dbMsrBehavior = 'mysql';
			
			$this->response->warning("You are about to synchronize DB instance. The bundle will not include DB data. <a href='#/dbmsr/status?farmId={$dbServer->farmId}&type={$dbMsrBehavior}'>Click here if you wish to bundle and save DB data</a>.");
			
			if (!$dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER)) {
				$dbSlave = true;
			}
		}

		//Check for already running bundle on selected instance
		$chk = $this->db->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status NOT IN ('success', 'failed')",
			array($dbServer->serverId)
		);

		if ($chk)
			throw new Exception(sprintf(_("This server is already synchonizing. <a href='#/bundletasks/%s/logs'>Check status</a>."), $chk));

		if (!$dbServer->IsSupported("0.2-112"))
			throw new Exception(sprintf(_("You cannot create snapshot from selected server because scalr-ami-scripts package on it is too old.")));

		//Check is role already synchronizing...
		$chk = $this->db->GetOne("SELECT server_id FROM bundle_tasks WHERE prototype_role_id=? AND status NOT IN ('success', 'failed')", array(
			$dbServer->roleId
		));

		if ($chk && $chk != $dbServer->serverId) {
			try {
				$bDBServer = DBServer::LoadByID($chk);
			}
			catch(Exception $e) {}

			if ($bDBServer->farmId == $dbServer->farmId)
				throw new Exception(sprintf(_("This role is already synchonizing. <a href='#/bundletasks/%s/logs'>Check status</a>."), $chk));
		}

		$roleName = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;
		$this->response->page('ui/servers/createsnapshot.js', array(
			'serverId' 	=> $dbServer->serverId,
			'platform'	=> $dbServer->platform,
			'dbSlave'	=> $dbSlave,
			'isVolumeSizeSupported'=> (int)$dbServer->IsSupported('0.7'),
			'farmId' => $dbServer->farmId,
			'farmName' => $dbServer->GetFarmObject()->Name,
			'roleName' => $roleName,
			'replaceNoReplace' => "<b>DO NOT REPLACE</b> any roles on any farms, just create new one.</td>",
			'replaceFarmReplace' => "Replace role '{$roleName}' with new one <b>ONLY</b> on current farm '{$dbServer->GetFarmObject()->Name}'</td>",
			'replaceAll' => "Replace role '{$roleName}' with new one on <b>ALL MY FARMS</b> <span style=\"font-style:italic;font-size:11px;\">(You will be able to bundle role with the same name. Old role will be renamed.)</span></td>"
		));
	}

	public function xServerCreateSnapshotAction()
	{
		$this->request->defineParams(array(
			'rootVolumeSize' => array('type' => 'int')
		));

		if (! $this->getParam('serverId'))
			throw new Exception(_('Server not found'));

		$dbServer = DBServer::LoadByID($this->getParam('serverId'));
		$this->user->getPermissions()->validate($dbServer);

		$err = array();

		if (strlen($this->getParam('roleName')) < 3)
			$err[] = _("Role name should be greater than 3 chars");

		if (! preg_match("/^[A-Za-z0-9-]+$/si", $this->getParam('roleName')))
			$err[] = _("Role name is incorrect");

		$roleinfo = $this->db->GetRow("SELECT * FROM roles WHERE name=? AND (env_id=? OR env_id='0')", array($this->getParam('roleName'), $dbServer->envId, $dbServer->roleId));
		if ($this->getParam('replaceType') != SERVER_REPLACEMENT_TYPE::REPLACE_ALL) {
			if ($roleinfo && $roleinfo['id'] != $dbServer->roleId)
				$err[] = _("Specified role name is already used by another role. You can use this role name only if you will replace old on on ALL your farms.");
		} else {
			if ($roleinfo && $roleinfo['env_id'] == 0)
				$err[] = _("Selected role name is reserved and cannot be used for custom role");
		}
		
		//Check for already running bundle on selected instance
        $chk = $this->db->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status NOT IN ('success', 'failed')", 
			array($dbServer->serverId)
		);
            
		if ($chk)
			$err[] = sprintf(_("Server '%s' is already synchonizing."), $dbServer->serverId);
            
        //Check is role already synchronizing...
        $chk = $this->db->GetOne("SELECT server_id FROM bundle_tasks WHERE prototype_role_id=? AND status NOT IN ('success', 'failed')", array(
			$dbServer->roleId
		));
		
		if ($chk && $chk != $dbServer->serverId) {
			try	{
				$bDBServer = DBServer::LoadByID($chk);
				if ($bDBServer->farmId == $DBServer->farmId)
					$err[] = sprintf(_("Role '%s' is already synchonizing."), $dbServer->GetFarmRoleObject()->GetRoleObject()->name);
            } catch(Exception $e) {}
		}
		
		if ($dbServer->GetFarmRoleObject()->NewRoleID)
			$err[] = sprintf(_("Role '%s' is already synchonizing."), $dbServer->GetFarmRoleObject()->GetRoleObject()->name);

		if (count($err))
			throw new Exception(nl2br(implode('\n', $err)));

		$ServerSnapshotCreateInfo = new ServerSnapshotCreateInfo(
			$dbServer,
			$this->getParam('roleName'),
			$this->getParam('replaceType'),
			false,
			$this->getParam('roleDescription'),
			$this->getParam('rootVolumeSize'),
			$this->getParam('noServersReplace') == 'on' ? true : false
		);
		$BundleTask = BundleTask::Create($ServerSnapshotCreateInfo);

		$protoRole = DBRole::loadById($dbServer->roleId);
		$details = $protoRole->getImageDetails(
			SERVER_PLATFORMS::EC2, 
			$dbServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)
		);
				
		$BundleTask->osFamily = $details['os_family'];
		$BundleTask->osName = $details['os_name'];
		$BundleTask->osVersion = $details['os_version'];
		$BundleTask->save();
		
		
		$this->response->success("Bundle task successfully created. <a href='#/bundletasks/{$BundleTask->id}/logs'>Click here to check status.</a>");
	}
}
