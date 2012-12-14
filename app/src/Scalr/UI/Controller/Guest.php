<?php
//require_once(SRCPATH . '/externals/recaptcha-php-1.11/recaptchalib.php');

class Scalr_UI_Controller_Guest extends Scalr_UI_Controller
{
	public function logoutAction()
	{
		Scalr_Session::destroy();
		$this->response->setRedirect('/');
	}

	public function hasAccess()
	{
		return true;
	}

	public function xInitAction()
	{
		$initParams = array();

		$initParams['extjs'] = array(
			$this->response->getModuleName("init.js"),
			$this->response->getModuleName("theme.js"),
			$this->response->getModuleName("override.js"),
			$this->response->getModuleName("utils.js"),
			$this->response->getModuleName("ui-plugins.js"),
			$this->response->getModuleName("ui.js")
		);

		$initParams['css'] = array(
			$this->response->getModuleName("theme.css"),
			$this->response->getModuleName("ui.css"),
			$this->response->getModuleName("utils.css")
		);

		$initParams['context'] = $this->getContext();

		$this->response->data(array('initParams' => $initParams));
	}

	public function getContext()
	{
		$data = array();
		if ($this->user) {
			$data['user'] = array(
				'userId' => $this->user->getId(),
				'clientId' => $this->user->getAccountId(),
				'userName' => $this->user->getEmail(),
				'envId' => $this->getEnvironment() ? $this->getEnvironmentId() : 0,
				'envName'  => $this->getEnvironment() ? $this->getEnvironment()->name : '',
				'type' => $this->user->getType()
			);

			if ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN) {
				$data['farms'] = $this->db->getAll('SELECT id, name FROM farms WHERE env_id = ? ORDER BY name', array($this->getEnvironmentId()));

				if ($this->user->getAccountId() != 0) {
					$initParams['flags'] = $this->user->getAccount()->getFeaturesList();
					$initParams['user']['userIsTrial'] = $this->user->getAccount()->getSetting(Scalr_Account::SETTING_IS_TRIAL) == '1' ? true : false;
				} else {
					$initParams['flags'] = array();
				}

				$data['flags']['platformEc2Enabled'] = !!$this->environment->isPlatformEnabled(SERVER_PLATFORMS::EC2);
				$data['flags']['platformCloudstackEnabled'] = !!$this->environment->isPlatformEnabled(SERVER_PLATFORMS::CLOUDSTACK);
				$data['flags']['platformIdcfEnabled'] = !!$this->environment->isPlatformEnabled(SERVER_PLATFORMS::IDCF);
				$data['flags']['platformUcloudEnabled'] = !!$this->environment->isPlatformEnabled(SERVER_PLATFORMS::UCLOUD);
				$data['flags']['platformRackspaceEnabled'] = !!$this->environment->isPlatformEnabled(SERVER_PLATFORMS::RACKSPACE);

				$data['flags']['billingExists'] = false;

				if ($this->user->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER) {
					if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED)) {
						if (count($this->environment->getEnabledPlatforms()) == 0)
							$data['flags']['needEnvConfig'] = Scalr_Environment::init()->loadDefault($this->user->getAccountId())->id;
					}
				}

				$data['environments'] = $this->user->getEnvironments();

				if ($this->getEnvironment() && $this->user->isTeamOwner()) {
					$data['user']['isTeamOwner'] = true;
				}
			}
		}

		return $data;
	}

	public function xGetContextAction()
	{
		$this->response->data($this->getContext());
	}

	/**
	 * Accumulates emails in app/cache/.remind-me-later-emails file.
	 * Registration from is in the http://scalr.net/l/re-invent-2012/
	 */
	public function xRemindMeLaterAction()
	{
		$this->response->setHeader('Access-Control-Allow-Origin', '*');
		$this->request->defineParams(array('email'));
		$email = $this->getParam('email');
		$file = APPPATH . '/cache/.remind-me-later-emails';
		$fp = fopen($file, 'a');
		if (!$fp) {
			$this->response->failure('Cannot open file for writing.');
			return;
		} else {
			fputcsv($fp, array(gmdate('c'), $email));
			fclose($fp);
		}
		$this->response->data(array('status' => 'ok'));
	}

	public function xCreateAccountAction()
	{
		global $Mailer; //FIXME: [postponed] this needs to be removed as part of LibWebta

		if (!class_exists("Scalr_Billing"))
			exit();

		$this->request->defineParams(array(
			'name', 'org', 'email', 'password', 'agreeTerms', 'newBilling',
			'country', 'phone', 'lastname', 'firstname', 'v', 'numServers'
		));

		$Validator = new Validator();

		if ($this->getParam('v') == 2) {
			if (!$this->getParam('firstname'))
				$err['firstname'] = _("First name required");

			if (!$this->getParam('lastname'))
				$err['lastname'] = _("Last name required");

			if (!$this->getParam('org'))
				$err['org'] = _("Organization required");

			$name = $this->getParam('firstname')." ".$this->getParam('lastname');

		} else {
			if (!$this->getParam('name'))
				$err['name'] = _("Account name required");

			$name = $this->getParam("name");
		}


		$password = $this->getParam('password');
		if (!$password)
			$password = $this->getCrypto()->sault(10);

		if (!$Validator->IsEmail($this->getParam('email')))
			$err['email'] = _("Invalid E-mail address");

		if (strlen($password) < 6)
			$err['password'] = _("Password should be longer than 6 chars");

	    // Check email
		$DBEmailCheck = $this->db->GetOne("SELECT COUNT(*) FROM account_users WHERE email=?", array($this->getParam('email')));
		if ($DBEmailCheck > 0)
			$err['email'] = _("E-mail already exists in database");

		if (!$this->getParam('agreeTerms'))
			$err['agreeTerms'] = _("You need to agree with terms and conditions");

		if (count($err) == 0) {
			$account = Scalr_Account::init();
			$account->name = $this->getParam("org") ? $this->getParam("org") : $name;
			$account->status = Scalr_Account::STATUS_ACTIVE;
			$account->save();

			$account->createEnvironment("default", true);

			$user = $account->createUser($this->getParam('email'), $password, Scalr_Account_User::TYPE_ACCOUNT_OWNER);
			$user->fullname = $name;
			$user->save();

			if ($this->getParam('v') == 2) {
				$user->setSetting('website.phone', $this->getParam('phone'));
				$user->setSetting('website.country', $this->getParam('country'));
				$user->setSetting('website.num_servers', $this->getParam('numServers'));
			}

			/**
			 * Limits
			 */
			try {
				$billing = new Scalr_Billing();
				$billing->loadByAccount($account);
				$billing->createSubscription(Scalr_Billing::PACKAGE_SEED, "", "", "", "");
				/*******************/
		    } catch (Exception $e) {
		    	$account->delete();
		    	header("Location: https://scalr.net/order/?error={$e->getMessage()}");
		    	exit();
		    }



			if ($_COOKIE['__utmz']) {
				$gaParser = new Scalr_Service_GoogleAnalytics_Parser();

				$clientSettings[CLIENT_SETTINGS::GA_CAMPAIGN_CONTENT] = $gaParser->campaignContent;
				$clientSettings[CLIENT_SETTINGS::GA_CAMPAIGN_MEDIUM] = $gaParser->campaignMedium;
				$clientSettings[CLIENT_SETTINGS::GA_CAMPAIGN_NAME] = $gaParser->campaignName;
				$clientSettings[CLIENT_SETTINGS::GA_CAMPAIGN_SOURCE] = $gaParser->campaignSource;
				$clientSettings[CLIENT_SETTINGS::GA_CAMPAIGN_TERM] = $gaParser->campaignTerm;
				$clientSettings[CLIENT_SETTINGS::GA_FIRST_VISIT] = $gaParser->firstVisit;
				$clientSettings[CLIENT_SETTINGS::GA_PREVIOUS_VISIT] = $gaParser->previousVisit;
				$clientSettings[CLIENT_SETTINGS::GA_TIMES_VISITED] = $gaParser->timesVisited;
			}

			$clientSettings[CLIENT_SETTINGS::RSS_LOGIN] = $this->getParam('email');
			$clientSettings[CLIENT_SETTINGS::RSS_PASSWORD] = $this->getCrypto()->sault(10);

			foreach ($clientSettings as $k=>$v)
				$account->setSetting($k, $v);

			try {
				$this->db->Execute("INSERT INTO default_records SELECT null, '{$account->id}', rtype, ttl, rpriority, rvalue, rkey FROM default_records WHERE clientid='0'");
			}
			catch(Exception $e){}

			$clientinfo = array(
				'fullname'	=> $name,
				'firstname'	=> ($this->getParam('firstname')) ? $this->getParam('firstname') : $name,
				'email'		=> $this->getParam('email'),
				'password'	=> $password
			);


			$mailer = new PHPMailer();
			$mailer->Subject = 'Welcome to the Scalr revolution!';
			$mailer->From = 'sales@scalr.com';
			$mailer->FromName = 'Scalr';
			$mailer->AddAddress($this->getParam('email'));
			$mailer->IsHTML(true);
			$mailer->Body = @file_get_contents(dirname(__FILE__).'/../../../../templates/en_US/emails/welcome.html');
			$mailer->Body = str_replace(array('{{FirstName}}','{{Password}}'), array($clientinfo['firstname'], $clientinfo['password']), $mailer->Body);

			$mailer->Send();

			$user->getAccount()->setSetting(Scalr_Account::SETTING_IS_TRIAL, 1);

			//AutoLogin
			$user->updateLastLogin();
			Scalr_Session::create($user->getId());
			Scalr_Session::keepSession();

			header("Location: http://scalr.net/thanks.html");
		}
		else {
			$errors = array_values($err);
			$error = $errors[0];
			header("Location: https://scalr.net/order/?error={$error}");
			//$this->response->failure();
			//$this->response->data(array('errors' => $err));
		}
		exit();
	}


	public function loginAction()
	{
		$this->response->page('ui/guest/login.js', array('loginAttempts' => 0));
	}

	private function loginUserGet()
	{
		$this->request->defineParams(array(
			'scalrLogin', 'scalrPass'
		));

		if ($this->getParam('scalrLogin') != '' && $this->getParam('scalrPass') != '') {
			$user = Scalr_Account_User::init()->loadByEmail($this->getParam('scalrLogin'));
			if (!$user)
				throw new Exception("Incorrect login or password (0)");

			if ($user->getSetting(Scalr_Account_User::SETTING_SECURITY_IP_WHITELIST)) {
				$ips = explode(',', $user->getSetting(Scalr_Account_User::SETTING_SECURITY_IP_WHITELIST));
				$inList = false;
				foreach ($ips as $ip) {
					$ip = trim($ip);
					if($ip && preg_match('/^'.$ip.'$/', $_SERVER['REMOTE_ADDR']))
						$inList = true;
				}
				if (!$inList)
					throw new Exception('Forbidden IP');
			}

			$pass = $user->checkPassword($this->getParam('scalrPass'));
			if ($pass)
				return $user;
			else
				throw new Exception('Incorrect login or password (2)');
		} else
			throw new Exception('Incorrect login or password (1)');
	}

	private function loginUserCreate($user)
	{
		$user->updateLastLogin();

		Scalr_Session::create($user->getId());

		if ($this->getParam('scalrKeepSession') == 'on')
			Scalr_Session::keepSession();

		$this->response->data(array('userId' => $user->getId()));
	}

	public function xLoginFakeAction()
	{
		$this->response->setBody(file_get_contents(APPPATH . '/www/login.html'));
	}

	public function xLoginAction()
	{
		$loginattempt = $this->db->GetRow("SELECT loginattempts FROM account_users WHERE `email` = ?",
			array($this->getParam('scalrLogin'))
		);
		if ($loginattempt['loginattempts'] > 2) {
			$text = file_get_contents('http://www.google.com/recaptcha/api/challenge?k=6Le9Us4SAAAAAOlqx9rkyJq0g3UBZtpoETmqUsmY');
			$start = strpos($text, "challenge : '")+13;
			$length = strpos($text, ",", $start)-$start;
			$curl = curl_init();
			// TODO: move private key to config.ini
			curl_setopt($curl, CURLOPT_URL, 'http://www.google.com/recaptcha/api/verify?privatekey=6Le9Us4SAAAAAHcdRP6Dx44BIwVU0MOOGhBuOxf6&remoteip=my.scalr.net&challenge='.substr($text, $start, $length).'&response='.$this->getParam('scalrCaptcha'));
			curl_setopt($curl, CURLOPT_TIMEOUT, 10);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			$response = curl_exec($curl);
			curl_close($curl);
			if (preg_match('/false?.*/', $response )) {
				$this->response->data($loginattempt);
				throw new Exception('Enter captcha correctly');
			}
		}
		try {
			$user = $this->loginUserGet();

			// check for 2-factor auth
			if ($user) {
				if (
					($user->getAccountId() && $user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_2FA) || !$user->getAccountId()) &&
					($user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == 1)
				) {
					$this->response->data(array(
						'tfa' => '#/guest/loginTfaGgl'
					));
					return;
				}
			} else
				throw new Exception('Incorrect login or password (4)');

			$this->loginUserCreate($user);
		} catch (Exception $e) {
			$loginattempt = $this->db->GetRow("SELECT loginattempts FROM account_users WHERE `email` = ?",
				array($this->getParam('scalrLogin'))
			);
			$this->response->data($loginattempt);
			$this->response->failure($e->getMessage());
		}
	}

	public function loginTfaGglAction()
	{
		$user = $this->loginUserGet();

		$this->response->page('ui/guest/loginTfaGgl.js', array(
			'valid' => (
				($user->getAccountId() && $user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_2FA) || !$user->getAccountId()) &&
				($user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == 1)
			),
			'authenticated' => is_object($this->user)
		));
	}

	public function xLoginTfaGglAction()
	{
		$user = $this->loginUserGet();

		if (
			($user->getAccountId() && $user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_2FA) || !$user->getAccountId()) &&
			($user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == 1)
		) {
			$key = $this->getCrypto()->decrypt($user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY), $this->cryptoKey);

			if ($this->getParam('tfaCode') && Scalr_Util_Google2FA::verifyKey($key, $this->getParam('tfaCode'))) {
				$this->loginUserCreate($user);
			} else {
				$this->response->failure('Invalid code');
			}
		} else {
			$this->response->failure('Two-factor authentication not enabled for this user');
		}
	}

	public function recoverPasswordAction()
	{
		$this->response->page('ui/guest/recoverPassword.js');
	}

	public function xResetPasswordAction()
	{
		global $Mailer; //FIXME: [postponed] this needs to be removed as part of LibWebta

		$user = Scalr_Account_User::init()->loadByEmail($this->getParam('email'));

		if ($user) {
			$hash = $this->getCrypto()->sault(10);

			$user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash);

			$clientinfo = array(
				'email' => $user->getEmail(),
				'fullname'	=> $user->fullname
			);

			// Send welcome E-mail
			$Mailer->ClearAddresses();
			$res = $Mailer->Send("emails/password_change_confirm.eml",
				array("client" => $clientinfo, "pwd_link" => "https://{$_SERVER['HTTP_HOST']}/#/guest/updatePassword/?hash={$hash}"),
				$clientinfo['email'],
				$clientinfo['fullname']
			);

			$this->response->success("Confirmation email has been sent to you");
		}
		else
			$this->response->failure("Specified e-mail not found in our database");
	}

	public function updatePasswordAction()
	{
		$user = Scalr_Account_User::init()->loadBySetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $this->getParam('hash'));
		$this->response->page('ui/guest/updatePassword.js', array('valid' => is_object($user), 'authenticated' => is_object($this->user)));
	}

	public function xUpdatePasswordAction()
	{
		$user = Scalr_Account_User::init()->loadBySetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $this->getParam('hash'));

		if ($user) {
			$password = $this->getParam('password');
			$user->updatePassword($password);
			$user->save();

			$user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, "");

			//Scalr_Session::create($user->getAccountId(), $user->getId(), $user->getType());

			$this->response->success("Password has been reset. Please log in.");
		} else {
			$this->response->failure("Incorrect confirmation link");
		}
	}

	public function xPerpetuumMobileAction()
	{
		$result = array();

		if ($this->user) {
			if ($this->getParam('updateDashboard'))
				$result['updateDashboard'] = Scalr_UI_Controller::loadController('dashboard')->checkLifeCycle($this->getParam('updateDashboard'));
		}

		$equal = $this->user && ($this->user->getId() == $this->getParam('userId')) &&
			(($this->getEnvironment() ? $this->getEnvironmentId() : 0) == $this->getParam('envId'));

		$result['equal'] = $equal;
		$result['isAuthenticated'] = $this->user ? true : false;

		$this->response->data($result);
	}

	public function xPostErrorAction()
	{
		$this->request->defineParams(array(
			'url', 'file', 'lineno', 'message'
		));

		$message = explode("\n", $this->getParam('message'));

		$this->db->Execute('INSERT INTO ui_errors (tm, file, lineno, url, short, message, browser, account_id) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE cnt = cnt + 1, tm = NOW()', array(
			$this->getParam('file'),
			$this->getParam('lineno'),
			$this->getParam('url'),
			$message[0],
			$this->getParam('message'),
			$_SERVER['HTTP_USER_AGENT'],
			$this->user ? $this->user->getAccountId() : ''
		));

		$this->response->success();
	}
}
