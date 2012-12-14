<?php

class Scalr_UI_Controller_Core extends Scalr_UI_Controller
{
	public function hasAccess()
	{
		return true;
	}

	public function supportAction()
	{
		if ($this->user) {
			$args = array(
				"name"		=> $this->user->fullname,
				"AccountID" => $this->user->getAccountId(),
				"email"		=> $this->user->getEmail(),
				"expires" => date("D M d H:i:s O Y", time()+120)
			);

			$token = GenerateTenderMultipassToken(json_encode($args));

			$this->response->setRedirect("http://support.scalr.net/?sso={$token}");
		} else {
			$this->response->setRedirect("/");
		}
	}

	public function apiAction()
	{
		if (! $this->user->getSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY) ||
			! $this->user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY)
		) {
			$keys = Scalr::GenerateAPIKeys();
			
			$this->user->setSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY, $keys['id']);
			$this->user->setSetting(Scalr_Account_User::SETTING_API_SECRET_KEY, $keys['key']);
		}

		$params[Scalr_Account_User::SETTING_API_ENABLED] = $this->user->getSetting(Scalr_Account_User::SETTING_API_ENABLED) == 1 ? true : false;
		$params[Scalr_Account_User::SETTING_API_IP_WHITELIST] = (string)$this->user->getSetting(Scalr_Account_User::SETTING_API_IP_WHITELIST);
		$params[Scalr_Account_User::SETTING_API_ACCESS_KEY] = $this->user->getSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY);
		$params[Scalr_Account_User::SETTING_API_SECRET_KEY] = $this->user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY);

		$this->response->page('ui/core/api.js', $params);
	}
	
	public function disasterAction()
	{
		$this->response->page('ui/core/disaster.js');
	}

	public function xSaveApiSettingsAction()
	{
		$apiEnabled = $this->getParam(str_replace(".", "_", Scalr_Account_User::SETTING_API_ENABLED)) == 'on' ? true : false;
		$ipWhitelist = $this->getParam(str_replace(".", "_", Scalr_Account_User::SETTING_API_IP_WHITELIST));

		$this->user->setSetting(Scalr_Account_User::SETTING_API_ENABLED, $apiEnabled);
		$this->user->setSetting(Scalr_Account_User::SETTING_API_IP_WHITELIST, $ipWhitelist);

		$this->response->success('API settings successfully saved');
	}

	public function xRegenerateApiKeysAction()
	{
		$keys = Scalr::GenerateAPIKeys();

		$this->user->setSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY, $keys['id']);
		$this->user->setSetting(Scalr_Account_User::SETTING_API_SECRET_KEY, $keys['key']);

		$this->response->success('Keys have been regenerated');
		$this->response->data(array('keys' => $keys));
	}

	public function profileAction()
	{
		$params = $this->db->GetRow("SELECT email, fullname FROM `account_users` WHERE id=?", array($this->user->getId()));
		$this->response->page('ui/core/profile.js', $params);
	}

	public function xProfileSaveAction()
	{
		$this->request->defineParams(array(
			'fullname' => array('type' => 'string'),
			'password' => array('type' => 'string'),
			'cpassword' => array('type' => 'string')
		));

		if (!$this->getParam('password'))
			$err['password'] = "Password is required";

		if ($this->getParam('password') != $this->getParam('cpassword'))
			$err['cpassword'] = "Two passwords are not equal";

		if (count($err) == 0) {
			$updateSession = false;

			if ($this->getParam('password') != '******') {
				$this->user->updatePassword($this->getParam('password'));
				$updateSession = true;
			}

			$this->user->fullname = $this->getParam("fullname");
			$this->user->save();

			if ($updateSession)
				Scalr_Session::create($this->user->getId());
			
			$this->response->success('Profile successfully updated');
		}
		else {
			$this->response->failure();
			$this->response->data(array('errors' => $err));
		}
	}

	public function searchAction()
	{

	}

	public function settingsAction()
	{
		$panel = $this->user->getDashboard($this->getEnvironmentId());

		$params = array(
			'rss_login' => $this->user->getSetting(Scalr_Account_User::SETTING_RSS_LOGIN),
			'rss_pass' => $this->user->getSetting(Scalr_Account_User::SETTING_RSS_PASSWORD),
			'default_environment' => $this->user->getSetting(Scalr_Account_User::SETTING_DEFAULT_ENVIRONMENT) ? $this->user->getSetting(Scalr_Account_User::SETTING_DEFAULT_ENVIRONMENT) : '',
			'default_environment_list' => $this->user->getEnvironments(),
			'user_email' => $this->user->getEmail(),
			'settings' => array(
				'security_2fa' => $this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_2FA),
				'security_2fa_ggl' => $this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL)
			),
			'security_ip_whitelist' => $this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_IP_WHITELIST),
			'dashboard_columns' => count($panel['configuration'])
		);

		$this->response->page('ui/core/settings.js', $params);
	}

	public function xSaveSettingsAction()
	{
		$this->request->defineParams(array(
			'rss_login', 'rss_pass', 'default_environment'
		));

		$rssLogin = htmlspecialchars($this->getParam('rss_login'));
		$rssPass = htmlspecialchars($this->getParam('rss_pass'));

		if ($rssLogin != '' || $rssPass != '') {
			if (strlen($rssLogin) < 6)
				$err['rss_login'] = "RSS feed login must be 6 chars or more";

			if (strlen($rssPass) < 6)
				$err['rss_pass'] = "RSS feed password must be 6 chars or more";
		}

		$panel = $this->user->getDashboard($this->getEnvironmentId());
		if ($this->getParam('dashboard_columns') > count($panel['configuration'])) {
			while ($this->getParam('dashboard_columns') > count($panel['configuration'])) {
				$panel['configuration'][] = array();
			}
		}
		if ($this->getParam('dashboard_columns') < count($panel['configuration'])) {
			for ($i = count($panel['configuration']); $i > $this->getParam('dashboard_columns'); $i--) {
				foreach($panel['configuration'][$i-1] as $widg) {
					$panel['configuration'][0][] = $widg;
				}
				unset($panel['configuration'][$i-1]);
			}
		}
		$this->user->setDashboard($this->getEnvironmentId(), $panel);

		$panel = self::loadController('Dashboard')->fillDash($panel);
		if (count($err) == 0) {
			$this->user->setSetting(Scalr_Account_User::SETTING_RSS_LOGIN, $rssLogin);
			$this->user->setSetting(Scalr_Account_User::SETTING_RSS_PASSWORD, $rssPass);
			$this->user->setSetting(Scalr_Account_User::SETTING_DEFAULT_ENVIRONMENT, $this->getParam('default_environment'));
			$this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_IP_WHITELIST, trim($this->getParam('security_ip_whitelist')));
			$this->user->setSetting(Scalr_Account_User::SETTING_DASHBOARD_ENABLED, $this->getParam('dashboard_enabled'));

			$this->response->success('Settings successfully updated');
			$this->response->data(array('panel' => $panel));
		} else {
			$this->response->failure();
			$this->response->data(array('errors' => $err));
		}
	}

	public function xSettingsDisable2FaGglAction()
	{
		$this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL, 0);
		$this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY, '');

		$this->response->success();
	}

	public function xSettingsEnable2FaGglAction()
	{
		if ($this->getParam('qr') && $this->getParam('code')) {
			if (Scalr_Util_Google2FA::verifyKey($this->getParam('qr'), $this->getParam('code'))) {
				$this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL, 1);
				$this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY, 
					$this->getCrypto()->encrypt($this->getParam('qr'), $this->cryptoKey)
				);

				$this->response->success();
			} else {
				$this->response->failure('Code is invalid. Please try again.');
			}
		} else {
			$this->response->failure();
		}
	}

	public function xChangeEnvironmentAction()
	{
		$env = Scalr_Environment::init()->loadById($this->getParam('envId'));

		foreach ($this->user->getEnvironments() as $e) {
			if ($env->id == $e['id']) {
				Scalr_Session::getInstance()->setEnvironmentId($e['id']);
				$this->response->success();
				return;
			}
		}

		throw new Scalr_Exception_InsufficientPermissions();
	}
}
