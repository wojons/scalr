<?php

class Scalr_UI_Controller_Scripts extends Scalr_UI_Controller
{
	const CALL_PARAM_NAME = 'scriptId';
	
	public function hasAccess()
	{
		return true;
	}

	public static function getPermissionDefinitions()
	{
		return array();
	}
	
	public function getFilterSql()
	{
		return "(" .
			" origin='".SCRIPT_ORIGIN_TYPE::SHARED."'" .
			" OR (origin='".SCRIPT_ORIGIN_TYPE::CUSTOM."' AND clientid='".$this->user->getAccountId()."')" .
			" OR (origin='".SCRIPT_ORIGIN_TYPE::USER_CONTRIBUTED."' AND (scripts.approval_state='".APPROVAL_STATE::APPROVED."' OR clientid='".$this->user->getAccountId()."'))" .
		")";
	}

	static public function getCustomVariables($template)
	{
		$text = preg_replace('/(\\\%)/si', '$$scalr$$', $template);
		preg_match_all("/\%([^\%\s]+)\%/si", $text, $matches);
		return $matches[1];
	}

	public function defaultAction()
	{
		$this->viewAction();
	}

	public function viewAction()
	{
		if ($this->getParam(self::CALL_PARAM_NAME)) {
			$scriptInfo = $this->db->GetRow("SELECT * FROM scripts WHERE id=? AND {$this->getFilterSql()}", array($this->getParam(self::CALL_PARAM_NAME)));
			if (!$scriptInfo)
				throw new Exception("Script not found");

			$latestRevision = $this->db->GetOne("SELECT MAX(revision) as rev FROM script_revisions WHERE scriptid=? GROUP BY scriptid", array(
				$this->getParam(self::CALL_PARAM_NAME)
			));
            $revisions = $this->db->GetAll("SELECT revision as rev FROM script_revisions WHERE scriptid=? ORDER BY revision DESC", array(
                $this->getParam(self::CALL_PARAM_NAME)
            ));
            $content = array();
            $revision = array();

            foreach ($revisions as $value) {
                $rev = $this->db->GetRow("SELECT script, revision FROM script_revisions WHERE scriptid=? AND revision=?", array(
                    $this->getParam(self::CALL_PARAM_NAME), $value['rev']
                ));
                $revision[] = $rev['revision'];
	            $content[$rev['revision']] = $rev['script'];
            }

			$this->response->page('ui/scripts/viewcontent.js', array(
				'script' => $scriptInfo,
				'content' => $content,
                'revision' => $revision,
                'latest' => $latestRevision
			), array('codemirror/codemirror.js'), array('codemirror/codemirror.css'));
		} else 
			$this->response->page('ui/scripts/view.js', array(
				'isScalrAdmin' => $this->user->getType() == Scalr_Account_User::TYPE_SCALR_ADMIN,
				'clientId' => $this->user->getAccountId()
			));
	}

	public function xGetScriptContentAction()
	{
		$this->request->defineParams(array(
			'scriptId' => array('type' => 'int'),
			'version' => array('type' => 'int')
		));

		$scriptInfo = $this->db->GetRow("SELECT * FROM scripts WHERE id=? AND {$this->getFilterSql()}", array($this->getParam('scriptId')));
		if (!$scriptInfo)
			throw new Exception("Script not found");

		$content = $this->db->GetOne("SELECT script FROM script_revisions WHERE scriptid = ? AND revision =?", array(
			$this->getParam('scriptId'), $this->getParam('version')
		));

		$this->response->data(array(
			'scriptContents' => $content
		));
	}

	public function xRemoveAction()
	{
		$this->request->defineParams(array(
			'scriptId' => array('type' => 'int')
		));

		// Get template infor from database
		$script = $this->db->GetRow("SELECT * FROM scripts WHERE id=? AND {$this->getFilterSql()}", array($this->getParam('scriptId')));
		if (!$script)
			throw new Exception(_("You don't have permissions to remove this script"));

		// Check template usage
		$rolesCount = $this->db->GetOne("SELECT COUNT(*) FROM farm_role_scripts WHERE scriptid=? AND event_name NOT LIKE 'CustomEvent-%'",
			array($this->getParam('scriptId'))
		);

		// If script used redirect and show error
		if ($rolesCount > 0)
			throw new Exception(_("This script being used and can't be deleted"));

		$this->db->BeginTrans();

		// Delete template and all revisions
		$this->db->Execute("DELETE FROM farm_role_scripts WHERE scriptid=?", array($this->getParam('scriptId')));
		$this->db->Execute("DELETE FROM scripts WHERE id=?", array($this->getParam('scriptId')));
		$this->db->Execute("DELETE FROM script_revisions WHERE scriptid=?", array($this->getParam('scriptId')));

		$this->db->CommitTrans();

		$this->response->success('Script successfully removed');
	}

	public function xSaveAction()
	{
		$this->request->defineParams(array(
			'scriptId' => array('type' => 'int'),
			'scriptName', 'scriptDescription', 'scriptContents',
			'version' => array('type' => 'int'),
			'saveCurrentRevision' => array('type' => 'int')
		));

		$content = str_replace("\r\n", "\n", $this->getParam('scriptContents'));
		
		$nonascii = array();
		/*
		$lines = explode("\n", $content);
		foreach ($lines as $i => $line) {
			$lineNum = $i+1;
			if (preg_match('/[^(\x20-\x7F)]+/', $line, $matches, PREG_OFFSET_CAPTURE) > 0) {
				$pos = $matches[0][1]+1;
				$nonascii[] = "line: {$lineNum} position: {$pos}";
			}
		}
		*/
		
		if (count($nonascii) > 0)
			throw new Exception("Found non ASCII symbols in the script at ".implode(", ", $nonascii).". Please remove them.");
		
		
		if (!$this->getParam('scriptId')) {
			// Add new script
			$this->db->Execute("INSERT INTO scripts SET
				name = ?,
				description = ?,
				origin = ?,
				dtadded = NOW(),
				clientid = ?,
				approval_state = ?
			", array(
				htmlspecialchars($this->getParam('scriptName')),
				htmlspecialchars($this->getParam('scriptDescription')),
				($this->user->getType() == Scalr_Account_User::TYPE_SCALR_ADMIN) ? SCRIPT_ORIGIN_TYPE::SHARED : SCRIPT_ORIGIN_TYPE::CUSTOM,
				$this->user->getAccountId(),
				APPROVAL_STATE::APPROVED
			));

			$scriptId = $this->db->Insert_ID();
		} else {

			$scriptInfo = $this->db->GetRow("SELECT * FROM scripts WHERE id=? AND {$this->getFilterSql()}", array($this->getParam('scriptId')));
			if (!$scriptInfo)
				throw new Exception("Script not found");

			$this->db->Execute("UPDATE scripts SET
				name = ?,
				description = ?
				WHERE id = ?
			", array(
				htmlspecialchars($this->getParam('scriptName')),
				htmlspecialchars($this->getParam('scriptDescription')),
				$this->getParam('scriptId')
			));

			$scriptId = $this->getParam('scriptId');
		}

		foreach ((array)self::getCustomVariables($this->getParam('scriptContents')) as $var) {
			if (! in_array($var, array_keys(CONFIG::getScriptingBuiltinVariables())))
				$vars[$var] = ucwords(str_replace("_", " ", $var));
		}
		
		if (!$this->getParam('saveCurrentRevision')) {
			$revision = $this->db->GetOne("SELECT IF(MAX(revision), MAX(revision), 0) FROM script_revisions WHERE scriptid=?",
				array($scriptId)
			);

			$this->db->Execute("INSERT INTO script_revisions SET
				scriptid	= ?,
				revision    = ?,
				script      = ?,
				dtcreated   = NOW(),
				approval_state = ?,
				`variables` = ?
			", array(
				$scriptId,
				$revision+1,
				$content,
				APPROVAL_STATE::APPROVED,
				serialize($vars)
			));
		} else {
			$this->db->Execute("UPDATE script_revisions SET
				script      = ?, dtcreated = NOW(), `variables` = ?
				WHERE scriptId = ? AND revision = ?
			", array(
				$content,
				serialize($vars),
				$scriptId,
				$this->getParam('scriptVersion')
			));
		}

		$this->response->success('Script successfully saved');
	}

	public function xForkAction()
	{
		$this->request->defineParams(array(
			'scriptId' => array('type' => 'int')
		));

		$scriptInfo = $this->db->GetRow("SELECT * FROM scripts WHERE id=? AND {$this->getFilterSql()}", array($this->getParam('scriptId')));
		if (!$scriptInfo)
			throw new Exception("Script not found");

		$this->db->Execute("INSERT INTO scripts SET
			name = ?,
			description = ?,
			origin = ?,
			dtadded = NOW(),
			clientid = ?,
			approval_state = ?
		", array(
			'Custom ' . $scriptInfo['name'],
			$scriptInfo['description'],
			SCRIPT_ORIGIN_TYPE::CUSTOM,
			$this->user->getAccountId(),
			APPROVAL_STATE::APPROVED
		));

		$s = $this->db->GetRow("SELECT script, variables FROM script_revisions WHERE scriptid = ? ORDER BY id DESC LIMIT 0,1", array($this->getParam('scriptId')));
		$content = $s['script'];
		$variables = $s['variables'];
		$scriptId = $this->db->Insert_ID();

		$this->db->Execute("INSERT INTO script_revisions SET
			scriptid	= ?,
			revision    = ?,
			script      = ?,
			dtcreated   = NOW(),
			approval_state = ?,
			variables 	= ?
		", array(
			$scriptId,
			1,
			$content,
			APPROVAL_STATE::APPROVED,
			$variables
		));

		$this->response->success('Script successfully forked');
	}

	public function editAction()
	{
		$this->request->defineParams(array(
			'scriptId' => array('type' => 'int'),
			'version' => array('type' => 'int')
		));

		$vars = CONFIG::getScriptingBuiltinVariables();

		$scriptInfo = $this->db->GetRow("SELECT * FROM scripts WHERE id=? AND {$this->getFilterSql()}", array($this->getParam('scriptId')));
		if (!$scriptInfo)
			throw new Exception("Script not found");

		$latestRevision = $this->db->GetRow("SELECT MAX(revision) as rev FROM script_revisions WHERE scriptid=? GROUP BY scriptid", array($this->getParam('scriptId')));
		if ($this->getParam('version'))
			$rev = $this->db->GetRow("SELECT revision as rev, script FROM script_revisions WHERE scriptid=? AND revision=?", array($this->getParam('scriptId'), $this->getParam('version')));
		else
			$rev = $latestRevision;

		$this->response->page('ui/scripts/create.js', array(
			'scriptName'	=> $scriptInfo['name'],
			'scriptId'		=> $scriptInfo['id'],
			'description'	=> $scriptInfo['description'],
			'scriptContents'=> $this->db->GetOne("SELECT script FROM script_revisions WHERE scriptid=? AND revision=?", array($this->getParam('scriptId'), $rev['rev'])),
			'latestVersion'=> $latestRevision['rev'],
			'version'		=> $rev['rev'],
			'versions'		=> range(1, $latestRevision['rev']),
			'variables'		=> "%" . implode("%, %", array_keys($vars)) . "%"
		), array('codemirror/codemirror.js'), array('codemirror/codemirror.css'));
	}

	public function createAction()
	{
		$vars = CONFIG::getScriptingBuiltinVariables();

		$this->response->page('ui/scripts/create.js', array(
			'scriptName'	=> '',
			'scriptId'		=> 0,
			'description'	=> '',
			'scriptContents'=> '',
			'version'		=> 1,
			'versions'		=> array(1),
			'variables'		=> "%" . implode("%, %", array_keys($vars)) . "%"
		), array('codemirror/codemirror.js'), array('codemirror/codemirror.css'));
	}

	public function xListScriptsAction()
	{
		$this->request->defineParams(array(
			'scriptId', 'origin', 'approvalState',
			'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'desc'))
		));

		if ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN) {
			$filterSql = $this->getFilterSql();
		}
		else {
			$filterSql = " (origin='".SCRIPT_ORIGIN_TYPE::SHARED."' OR origin='".SCRIPT_ORIGIN_TYPE::USER_CONTRIBUTED."')";
		}

		$args = array();
		$sql = "SELECT
			scripts.id,
			scripts.name,
			scripts.description,
			scripts.origin,
			scripts.clientid,
			scripts.approval_state,
			MAX(script_revisions.dtcreated) as dtupdated, MAX(script_revisions.revision) AS version FROM scripts
		INNER JOIN script_revisions ON script_revisions.scriptid = scripts.id
		WHERE {$filterSql} AND :FILTER:";

		if ($this->getParam('origin'))
			$sql .= " AND origin=".$this->db->qstr($this->getParam('origin'));

		if ($this->getParam('approvalState'))
			$sql .= " AND scripts.approval_state=".$this->db->qstr($this->getParam('approvalState'));

		$sql .= ' GROUP BY script_revisions.scriptid';

		$response = $this->buildResponseFromSql($sql, array('id', 'name', 'description', 'dtupdated'), array('scripts.name', 'scripts.description'));

		foreach ($response['data'] as &$row) {
			if ($row['clientid'] != 0) {
				if ($row['clientid'] == $this->user->getAccountId())
					$row["client_name"] = $this->user->fullname;
				else 
					$row['client_name'] = 'User Contribution';
			}

			$row['dtupdated'] = Scalr_Util_DateTime::convertTz($row["dtupdated"]);
		}

		$this->response->data($response);
	}

	public function getList()
	{
		$scripts = array();

		$sql = "SELECT scripts.*, MAX(script_revisions.dtcreated) as dtupdated from scripts INNER JOIN script_revisions
			ON script_revisions.scriptid = scripts.id WHERE {$this->getFilterSql()} GROUP BY script_revisions.scriptid ORDER BY name ASC";

		foreach ($this->db->GetAll($sql) as $script) {
			$dbVersions = $this->db->Execute("SELECT * FROM script_revisions WHERE scriptid=? AND (approval_state=? OR (SELECT clientid FROM scripts WHERE scripts.id=script_revisions.scriptid) = '".$this->user->getAccountId()."')",
				array($script['id'], APPROVAL_STATE::APPROVED)
			);

			if ($dbVersions->RecordCount() > 0) {
				$revisions = array();
				while ($version = $dbVersions->FetchRow()) {
					$revisions[] = array('revision' => $version['revision'], 'revisionName' => $version['revision'], 'fields' => unserialize($version['variables']));
				}

				$scripts[] = array(
					'id'			=> $script['id'],
					'name'			=> $script['name'],
					'description'	=> $script['description'],
					'issync'		=> $script['issync'],
					'timeout'		=> ($script['issync'] == 1) ? CONFIG::$SYNCHRONOUS_SCRIPT_TIMEOUT : CONFIG::$ASYNCHRONOUS_SCRIPT_TIMEOUT,
					'revisions'		=> $revisions
				);
			}
		}

		return $scripts;
	}

	public function getScriptingData()
	{
		$retval = array('events' => EVENT_TYPE::getScriptingEvents(), 'scripts' => $this->getList());
		
		try {
			$envId = $this->getEnvironmentId();
			if ($envId) {
				$events = $this->db->Execute("SELECT * FROM event_definitions WHERE env_id = ?", array($envId));
				while ($event = $events->FetchRow()) {
					$retval['events'][$event['name']] = $event['description'];
				}
			}
		} catch (Exception $e) {}
		
		return $retval;
	}

	public function xGetScriptingDataAction()
	{
		$this->response->data($this->getScriptingData());
	}

	// TODO: remove
	public function getFarmRolesAction()
	{
		$this->request->defineParams(array(
			'allValue' => array('type' => 'bool')
		));

		$farmRolesController = self::loadController('Roles', 'Scalr_UI_Controller_Farms');
		if (is_null($farmRolesController))
			throw new Exception('Controller Farms_Roles not created');

		$farmRoles = $farmRolesController->getList();
		if (count($farmRoles) && $this->getParam('allValue'))
			$farmRoles[0] = array('id' => 0, 'name' =>'On all roles');

		$this->response->data(array(
			'farmRoles' => $farmRoles
		));
	}

	// TODO: remove
	public function getServersAction()
	{
		$this->request->defineParams(array(
			'allValue' => array('type' => 'bool')
		));

		$dbFarmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
		$dbFarm = DBFarm::LoadById($dbFarmRole->FarmID);
		$servers = array();
		
		$this->user->getPermissions()->validate($dbFarm);

		foreach ($dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING)) as $key => $value)
			$servers[$value->serverId] = "{$value->remoteIp} ({$value->localIp})";

		if (count($servers) && $this->getParam('allValue'))
			$servers[0] = 'On all servers';

		$this->response->data(array(
			'servers' => $servers
		));
	}

	public function executeAction()
	{
		$farmId = $this->getParam('farmId');
		$farmRoleId = $this->getParam('farmRoleId');
		$serverId = $this->getParam('serverId');
		$scriptId = $this->getParam('scriptId');
		$eventName = $this->getParam('eventName');

		$scripts = $this->getList();

		if ($eventName) {
			$scriptInfo = $this->db->GetRow("SELECT * FROM farm_role_scripts WHERE event_name=?", array($eventName));
			if (!$scriptInfo)
				throw new Exception("Scalr unable to find script execution options for used link");

			$farmId = $scriptInfo['farmid'];
			$farmRoleId = $scriptInfo['farm_roleid'];

			$scriptId = $scriptInfo['scriptid'];
		}

		$farmWidget = self::loadController('Farms', 'Scalr_UI_Controller')->getFarmWidget(array(
			'farmId' => $farmId,
			'farmRoleId' => $farmRoleId,
			'serverId' => $serverId
		), 'addAll');

		$this->response->page('ui/scripts/execute.js', array(
			'farmWidget' => $farmWidget,

			'scripts' => $scripts,
			'farmId' => $farmId,
			'farmRoleId' => $farmRoleId,
			'serverId' => $serverId,
			'scriptId' => $scriptId,

			'scriptIsSync' =>  $scriptInfo['issync'],
			'scriptTimeout' => $scriptInfo['timeout'],
			'scriptVersion' => $scriptInfo['version'],
			'scriptOptions' => @unserialize($scriptInfo['params'])
		));
	}

	public function xExecuteAction()
	{
		$this->request->defineParams(array(
			'farmId' => array('type' => 'int'),
			'farmRoleId' => array('type' => 'int'),
			'serverId' => array('type' => 'string'),
			'scriptId' => array('type' => 'int'),
			'scriptIsSync' => array('type' => 'int'),
			'scriptTimeout' => array('type' => 'int'),
			'scriptVersion' => array('type' => 'int'),
			'scriptOptions' => array('type' => 'array'),
			'createMenuLink' => array('type' => 'int')
		));

		$eventName = Scalr_Scripting_Manager::generateEventName('CustomEvent');
		$target = '';

		// @TODO: validation
		if ($this->getParam('serverId')) {
			$dbServer = DBServer::LoadByID($this->getParam('serverId'));
			$this->user->getPermissions()->validate($dbServer);

			$target = SCRIPTING_TARGET::INSTANCE;
			$serverId = $dbServer->serverId;
			$farmRoleId = $dbServer->farmRoleId;
			$farmId = $dbServer->farmId;

		} else if ($this->getParam('farmRoleId')) {
			$dbFarmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
			$this->user->getPermissions()->validate($dbFarmRole);

			$target = SCRIPTING_TARGET::ROLE;
			$farmRoleId = $dbFarmRole->ID;
			$farmId = $dbFarmRole->FarmID;

		} else {
			$dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
			$this->user->getPermissions()->validate($dbFarm);

			$target = SCRIPTING_TARGET::FARM;
			$farmId = $dbFarm->ID;
		}

		if (! $this->getParam('eventName')) {
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
				$this->getParam('scriptId'),
				(int)$farmId,
				(int)$farmRoleId,
				serialize($this->getParam('scriptOptions')),
				$eventName,
				$target,
				$this->getParam('scriptVersion'),
				$this->getParam('scriptTimeout'),
				$this->getParam('scriptIsSync'),
				$this->getParam('createMenuLink')
			));

			$farmScriptId = $this->db->Insert_ID();

			$executeScript = true;
		} else {

			$info = $this->db->Execute("SELECT farmid FROM farm_role_scripts WHERE event_name=?", array($this->getParam('eventName')));
			if ($info['farmid'] != $dbFarm->ID)
				throw new Exception("You cannot change farm for script shortcut");

			$this->db->Execute("UPDATE farm_role_scripts SET
				scriptid	= ?,
				farm_roleid	= ?,
				params		= ?,
				target		= ?,
				version		= ?,
				timeout		= ?,
				issync		= ?
			WHERE event_name = ? AND farmid = ?
			", array(
				$this->getParam('scriptId'),
				(int)$farmRoleId,
				serialize($this->getParam('scriptOptions')),
				$target,
				$this->getParam('scriptVersion'),
				$this->getParam('scriptTimeout'),
				$this->getParam('scriptIsSync'),
				$this->getParam('eventName'),
				$farmId
			));

			if (!$this->getParam('isShortcut'))
				$executeScript = true;
		}

		if ($executeScript) {
			switch($target) {
				case SCRIPTING_TARGET::FARM:
					$servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status IN (?,?) AND farm_id=?",
						array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmId)
					);
					break;
				case SCRIPTING_TARGET::ROLE:
					$servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status IN (?,?) AND farm_roleid=?",
						array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmRoleId)
					);
					break;
				case SCRIPTING_TARGET::INSTANCE:
					$servers = $this->db->GetAll("SELECT server_id FROM servers WHERE status IN (?,?) AND server_id=?",
						array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $serverId)
					);
					break;
			}

			if (count($servers) > 0) {
				foreach ($servers as $server) {
					$DBServer = DBServer::LoadByID($server['server_id']);
					$message = new Scalr_Messaging_Msg_ExecScript($eventName);
					$message->meta[Scalr_Messaging_MsgMeta::EVENT_ID] = "FRSID-{$farmScriptId}";
					
					/*****/
					$message = Scalr_Scripting_Manager::extendMessage($message, $DBServer);
					/*****/
					
					$DBServer->SendMessage($message, false, true);
				}
			}
		}

		$this->response->success('Script execution has been queued. Script will be executed on selected instance(s) within couple of minutes.');
	}
	
	private function getScripts(Event $event, DBServer $eventServer, DBServer $targetServer) 
	{
		$retval = array();
		
		try {
			$scripts = Scalr_Scripting_Manager::getEventScriptList($event, $eventServer, $targetServer);
			if (count($scripts) > 0)
			{
				foreach ($scripts as $script)
				{						
					$retval[] = $itm;
				}
			}
		} catch (Exception $e) {}
		
		return $retval;
	}
}
