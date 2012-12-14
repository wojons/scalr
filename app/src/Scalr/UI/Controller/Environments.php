<?php

class Scalr_UI_Controller_Environments extends Scalr_UI_Controller
{
	const CALL_PARAM_NAME = 'envId';
	
	private $checkVarError;
	
	public static function getApiDefinitions()
	{
		return array('xListEnvironments', 'xGetInfo', 'xCreate', 'xSave', 'xRemove');
	}

	public function hasAccess()
	{
		if (parent::hasAccess()) {
			return ($this->user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER || $this->user->isTeamOwner()) ? true : false;
		} else
			return false;
	}

	public function defaultAction()
	{
		$this->viewAction();
	}

	public function viewAction()
	{
		$this->response->page('ui/environments/view.js');
	}

	public function xListEnvironmentsAction()
	{
		$this->request->defineParams(array(
			'sort' => array('type' => 'json')
		));
		
		if ($this->user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER) {
			$sql = "SELECT
				id,
				name,
				dt_added AS dtAdded,
				is_system AS isSystem,
				status
				FROM client_environments
				WHERE client_id = ? AND :FILTER:
				GROUP BY id
			";
			$params = array($this->user->getAccountId());
		} else {
			$sql = "SELECT
				client_environments.id,
				client_environments.name,
				client_environments.dt_added AS dtAdded,
				client_environments.is_system AS isSystem,
				client_environments.status
				FROM client_environments
				JOIN account_team_envs ON client_environments.id = account_team_envs.env_id
				JOIN account_team_users ON account_team_envs.team_id = account_team_users.team_id
				WHERE client_environments.client_id = ? AND account_team_users.permissions = ? AND account_team_users.user_id = ? AND :FILTER:
				GROUP BY client_environments.id
			";

			$params = array($this->user->getAccountId(), Scalr_Account_Team::PERMISSIONS_OWNER, $this->user->id);
		}

		$response = $this->buildResponseFromSql($sql, array('id', 'name', 'dtAdded', 'status'), array(), $params);
		foreach ($response['data'] as &$row) {
			foreach (Scalr_Environment::init()->loadById($row['id'])->getEnabledPlatforms() as $platform)
				$row['platforms'][] = SERVER_PLATFORMS::GetName($platform);

			$row['platforms'] = implode(', ', $row['platforms']);
			$row['dtAdded'] = Scalr_Util_DateTime::convertTz($row['dtAdded']);
		}

		$this->response->data($response);
	}

	public function xSetStatusAction()
	{
		$env = Scalr_Environment::init();
		$env->loadById($this->getParam('envId'));
		$this->user->getPermissions()->validate($env);

		if (! ($this->user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER || $this->user->isTeamUserInEnvironment($env->id, Scalr_Account_Team::PERMISSIONS_OWNER)))
			throw new Scalr_Exception_InsufficientPermissions();

		$env->status = $this->getParam('status') == Scalr_Environment::STATUS_ACTIVE ? Scalr_Environment::STATUS_ACTIVE : Scalr_Environment::STATUS_INACTIVE;
		$env->save();

		$this->response->success("Environment's status successfully changed");
	}

	public function xSetSystemAction()
	{
		$env = Scalr_Environment::init();
		$env->loadById($this->getParam('envId'));
		$this->user->getPermissions()->validate($env);

		if ($this->user->getType() != Scalr_Account_User::TYPE_ACCOUNT_OWNER)
			throw new Scalr_Exception_InsufficientPermissions();

		$env->setSystem();

		$this->response->success("System environment successfully changed");
	}

	public function xRemoveAction()
	{
		if ($this->user->getType() != Scalr_Account_User::TYPE_ACCOUNT_OWNER)
			throw new Scalr_Exception_InsufficientPermissions();

		$env = Scalr_Environment::init()->loadById($this->getParam('envId'));
		$this->user->getPermissions()->validate($env);
		$env->delete();
		
		if ($env->id == $this->getEnvironmentId())
			Scalr_Session::getInstance()->setEnvironmentId(null); // reset
		
		$this->response->success("Environment successfully removed");
		$this->response->data(array('env' => array('id' => $env->id), 'flagReload' => $env->id == $this->getEnvironmentId() ? true : false));
	}
	
	public function createAction()
	{
		if ($this->user->getType() != Scalr_Account_User::TYPE_ACCOUNT_OWNER)
			throw new Scalr_Exception_InsufficientPermissions();

		$this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_ENVIRONMENTS, 1);
		$this->response->page('ui/environments/create.js', array());
	}
	
	public function xCreateAction()
	{
		if ($this->user->getType() != Scalr_Account_User::TYPE_ACCOUNT_OWNER)
			throw new Scalr_Exception_InsufficientPermissions();

		$this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_ENVIRONMENTS, 1);
		$env = $this->user->getAccount()->createEnvironment($this->getParam('name'), false);
		
		$this->response->success("Environment successfully created");
		$this->response->data(array(
			'env' => array(
				'id' => $env->id,
				'name' => $env->name
			)
		));
	}

	public function xRenameAction()
	{
		if ($this->user->getType() != Scalr_Account_User::TYPE_ACCOUNT_OWNER)
			throw new Scalr_Exception_InsufficientPermissions();

		$this->request->defineParams(array(
			'name' => array('type' => 'string', 'validator' => array(
				Scalr_Validator::REQUIRED => true,
				Scalr_Validator::NOHTML => true
			))
		));

		if ($this->request->validate()->isValid()) {
			$env = Scalr_Environment::init();
			$env->loadById($this->getParam('envId'));
			$this->user->getPermissions()->validate($env);

			$env->name = $this->getParam('name');
			$env->save();

			$this->response->success("Environment's name successfully changed");
			$this->response->data(array('env' => array('id' => $env->id, 'name' => $env->name)));
		} else {
			$this->response->failure('Illegal name for environment');
		}
	}

	protected function getEnvironmentInfo()
	{
		$env = Scalr_Environment::init();
		$env->loadById($this->getParam('envId'));
		$this->user->getPermissions()->validate($env);
		
		$params = array();

		$params[ENVIRONMENT_SETTINGS::TIMEZONE] = $env->getPlatformConfigValue(ENVIRONMENT_SETTINGS::TIMEZONE);
		
		return array(
			'id' => $env->id,
			'name' => $env->name,
			'params' => $params,
			'enabledPlatforms' => $env->getEnabledPlatforms()
		);
	}

	public function editAction()
	{
		$env = $this->getEnvironmentInfo();

		if (! ($this->user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER || $this->user->isTeamUserInEnvironment($env['id'], Scalr_Account_Team::PERMISSIONS_OWNER)))
			throw new Scalr_Exception_InsufficientPermissions();

		$platforms = SERVER_PLATFORMS::GetList();
		unset($platforms[SERVER_PLATFORMS::RDS]);

		//TODO:
		if (!$this->getParam('beta')) {
			unset($platforms[SERVER_PLATFORMS::OPENSTACK]);
			unset($platforms[SERVER_PLATFORMS::UCLOUD]);
			//unset($platforms[SERVER_PLATFORMS::RACKSPACENG]);
			unset($platforms[SERVER_PLATFORMS::GCE]);
		}

		$timezones = array();
		$timezoneAbbreviationsList = timezone_abbreviations_list();
		foreach ($timezoneAbbreviationsList as $timezoneAbbreviations) {
			foreach ($timezoneAbbreviations as $value) {
				if (preg_match( '/^(America\/|Antartica\/|Arctic\/|Asia\/|Atlantic\/|Europe\/|Indian\/|Pacific\/|Australia\/|UTC)/', $value['timezone_id']))
					$timezones[$value['timezone_id']] = $value['offset'];
			}
		}

		@ksort($timezones);
		$timezones = array_keys($timezones);

		$this->response->page('ui/environments/edit.js', array(
			'environment' => $env,
			'platforms' => $platforms,
			'timezones' => $timezones
		), array(), array('ui/environments/edit.css'));

		/*
		 * $this->response->page('ui/environments/edit.js', array(
			'environment' => $env,
			'platforms' => $platforms,
			'timezones' => Scalr_Util_DateTime::getTimezones()
		));
		 */
	}

	public function xGetInfoAction()
	{
		$this->response->data(array('environment' => $this->getEnvironmentInfo()));
	}

	private function checkVar($name, $type, $env, $requiredError = '', $group = '')
	{
		$varName = str_replace('.', '_', ($group != '' ? $name . '.' . $group : $name));

		switch ($type) {
			case 'int':
				if ($this->getParam($varName)) {
					return intval($this->getParam($varName));
				} else {
					$value = $env->getPlatformConfigValue($name, true, $group);
					if (!$value && $requiredError)
						$this->checkVarError[$name] = $requiredError;

					return $value;
				}
				break;

			case 'string':
				if ($this->getParam($varName)) {
					return $this->getParam($varName);
				} else {
					$value = $env->getPlatformConfigValue($name, true, $group);
					if ($value == '' && $requiredError)
						$this->checkVarError[$name] = $requiredError;

					return $value;
				}
				break;

			case 'password':
				if ($this->getParam($varName) && $this->getParam($varName) != '******') {
					return $this->getParam($varName);
				} else {
					$value = $env->getPlatformConfigValue($name, true, $group);
					if ($value == '' && $requiredError)
						$this->checkVarError[$name] = $requiredError;

					return $value;
				}
				break;

			case 'bool':
				return $this->getParam($varName) ? 1 : 0;
		}
	}
	
	public function xSaveAction()
	{
		$this->request->defineParams(array('envId' => array('type' => 'int')));

		$env = Scalr_Environment::init()->loadById($this->getParam('envId'));
		$this->user->getPermissions()->validate($env);

		if (! ($this->user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER || $this->user->isTeamUserInEnvironment($env->id, Scalr_Account_Team::PERMISSIONS_OWNER)))
			throw new Scalr_Exception_InsufficientPermissions();

		$pars = array();

		// check for settings
		$pars[ENVIRONMENT_SETTINGS::TIMEZONE] = $this->checkVar(ENVIRONMENT_SETTINGS::TIMEZONE, 'string', $env, "Timezone required");

		$env->setPlatformConfig($pars);
		
		if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
			$this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

		$this->response->success('Environment saved');
	}
}
