<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;
use Scalr\Model\Entity\Tag;
use Scalr\UI\Request\RawData;
use Scalr\UI\Request\JsonData;

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

        // always sync list of files (js, css) to Scalr_UI_Response->pageUiHash
        $initParams['extjs'] = array(
            $this->response->getModuleName("override.js"),
            $this->response->getModuleName("init.js"),
            $this->response->getModuleName("utils.js"),
            $this->response->getModuleName("ui-form.js"),
            $this->response->getModuleName("ui-grid.js"),
            $this->response->getModuleName("ui-plugins.js"),
            $this->response->getModuleName("ui.js")
        );

        $mode = Scalr_Session::getInstance()->getDebugMode();
        if (isset($mode['enabled']) && $mode['enabled'])
            $initParams['extjs'][] = $this->response->getModuleName('ui-debug.js');

        $initParams['css'] = array(
            $this->response->getModuleName("ui.css")
        );

        $initParams['uiHash'] = $this->response->pageUiHash();
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
                'gravatarHash' => $this->user->getGravatarHash(),
                'envId' => $this->getEnvironment() ? $this->getEnvironmentId() : 0,
                'envName'  => $this->getEnvironment() ? $this->getEnvironment()->name : '',
                'envVars' => $this->getEnvironment() ? $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_UI_VARS) : '',
                'type' => $this->user->getType()
            );

            $envVars = json_decode($data['user']['envVars'], true);
            $betaMode = ($envVars && $envVars['beta'] == 1);

            if (! $this->user->isAdmin()) {
                $data['farms'] = $this->db->getAll('SELECT id, name FROM farms WHERE env_id = ? ORDER BY name', array($this->getEnvironmentId()));

                if ($this->user->getAccountId() != 0) {
                    $data['flags'] = $this->user->getAccount()->getFeaturesList();
                    $data['user']['userIsTrial'] = $this->user->getAccount()->getSetting(Scalr_Account::SETTING_IS_TRIAL) == '1' ? true : false;
                } else {
                    $data['flags'] = array();
                }

                $data['flags']['billingExists'] = \Scalr::config('scalr.billing.enabled');
                $data['flags']['featureUsersPermissions'] = $this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_USERS_PERMISSIONS);

                $data['flags']['wikiUrl'] = \Scalr::config('scalr.ui.wiki_url');
                $data['flags']['supportUrl'] = \Scalr::config('scalr.ui.support_url');
                if ($data['flags']['supportUrl'] == '/core/support') {
                    $data['flags']['supportUrl'] .= '?X-Requested-Token=' . Scalr_Session::getInstance()->getToken();
                }

                $data['acl'] = $this->request->getAclRoles()->getAllowedArray(true);

                if ($this->user->isAccountOwner()) {
                    if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED)) {
                        if (count($this->environment->getEnabledPlatforms()) == 0) {
                            $data['flags']['needEnvConfig'] = Scalr_Environment::init()->loadDefault($this->user->getAccountId())->id;
                        }
                    }
                }

                $data['environments'] = $this->user->getEnvironments();

                if ($this->getEnvironment() && $this->user->isTeamOwner()) {
                    $data['user']['isTeamOwner'] = true;
                }
            }

            $data['platforms'] = array();
            $allowedClouds = (array) \Scalr::config('scalr.allowed_clouds');

            foreach (SERVER_PLATFORMS::getList() as $platform => $platformName) {
                if (!in_array($platform, $allowedClouds) && !$this->request->getHeaderVar('Interface-Beta')) {
                    continue;
                }

                $data['platforms'][$platform] = array(
                    'public'  => PlatformFactory::isPublic($platform),
                    'enabled' => $this->user->isAdmin() ? true : !!$this->environment->isPlatformEnabled($platform),
                    'name'    => $platformName,
                );

                if (!$this->user->isAdmin()) {
                    if ($platform == SERVER_PLATFORMS::EC2 && $this->environment->status == Scalr_Environment::STATUS_INACTIVE && $this->environment->getPlatformConfigValue('system.auto-disable-reason')) {
                        $data['platforms'][$platform]['config'] = array('autoDisabled' => true);
                    }

                    if (PlatformFactory::isOpenstack($platform) && $data['platforms'][$platform]['enabled']) {
                        $data['platforms'][$platform]['config'] = array(
                            OpenstackPlatformModule::EXT_SECURITYGROUPS_ENABLED => PlatformFactory::NewPlatform($platform)->getConfigVariable(OpenstackPlatformModule::EXT_SECURITYGROUPS_ENABLED, $this->getEnvironment(), false),
                            OpenstackPlatformModule::EXT_LBAAS_ENABLED => PlatformFactory::NewPlatform($platform)->getConfigVariable(OpenstackPlatformModule::EXT_LBAAS_ENABLED, $this->getEnvironment(), false),
                            OpenstackPlatformModule::EXT_FLOATING_IPS_ENABLED => PlatformFactory::NewPlatform($platform)->getConfigVariable(OpenstackPlatformModule::EXT_FLOATING_IPS_ENABLED, $this->getEnvironment(), false),
                            OpenstackPlatformModule::EXT_CINDER_ENABLED => PlatformFactory::NewPlatform($platform)->getConfigVariable(OpenstackPlatformModule::EXT_CINDER_ENABLED, $this->getEnvironment(), false),
                            OpenstackPlatformModule::EXT_SWIFT_ENABLED => PlatformFactory::NewPlatform($platform)->getConfigVariable(OpenstackPlatformModule::EXT_SWIFT_ENABLED, $this->getEnvironment(), false)
                        );
                    }
                }
            }

            $data['flags']['uiStorageTime'] = $this->user->getSetting(Scalr_Account_User::SETTING_UI_STORAGE_TIME);
            $data['flags']['uiStorage'] = $this->user->getVar(Scalr_Account_User::VAR_UI_STORAGE);
            $data['flags']['allowManageAnalytics'] = (bool) Scalr::isAllowedAnalyticsOnHostedScalrAccount($this->environment->clientId);
        }

        if ($this->user)
            $data['tags'] = Tag::getAll($this->user->getAccountId());

        $data['flags']['authMode'] = $this->getContainer()->config->get('scalr.auth_mode');
        $data['flags']['specialToken'] = Scalr_Session::getInstance()->getToken();
        $data['flags']['hostedScalr'] = (bool) Scalr::isHostedScalr();
        $data['flags']['analyticsEnabled'] = $this->getContainer()->analytics->enabled;

        return $data;
    }

    public function xGetContextAction()
    {
        $this->response->data($this->getContext());
    }

    /**
     * Accumulates emails in app/cache/.remind-me-later-emails file.
     * Registration from is in the http://scalr.net/l/re-invent-2012/
     *
     * @param string $email
     */
    public function xRemindMeLaterAction($email)
    {
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
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

    /**
     * @param string $name
     * @param string $org
     * @param $email
     * @param $password
     * @param string $agreeTerms
     * @param string $newBilling
     * @param string $country
     * @param string $phone
     * @param string $lastname
     * @param string $firstname
     * @param string $v
     * @param string $numServers
     */
    public function xCreateAccountAction($name = '', $org = '', $email, $password = '', $agreeTerms = '', $newBilling = '', $country = '', $phone = '', $lastname = '', $firstname = '', $v = '', $numServers = '')
    {
        if (!\Scalr::config('scalr.billing.enabled'))
            exit();

        $Validator = new Scalr_Validator();

        if ($v == 2) {
            if (!$firstname)
                $err['firstname'] = _("First name required");

            if (!$lastname)
                $err['lastname'] = _("Last name required");

            //if (!$org)
            //    $err['org'] = _("Organization required");

            $name = $firstname . " " . $lastname;

        } else {
            if (!$name)
                $err['name'] = _("Account name required");
        }

        if (!$password)
            $password = $this->getCrypto()->sault(10);

        if ($Validator->validateEmail($email, null, true) !== true)
            $err['email'] = _("Invalid E-mail address");

        if (strlen($password) < 6)
            $err['password'] = _("Password should be longer than 6 chars");

        // Check email
        $DBEmailCheck = $this->db->GetOne("SELECT COUNT(*) FROM account_users WHERE email=?", array($email));

        if ($DBEmailCheck > 0)
            $err['email'] = _("E-mail already exists in database");

        if (!$agreeTerms)
            $err['agreeTerms'] = _("You need to agree with terms and conditions");

        if (count($err) == 0) {
            $account = Scalr_Account::init();
            $account->name = $org ? $org : $name;
            $account->status = Scalr_Account::STATUS_ACTIVE;
            $account->save();

            $account->createEnvironment("Environment 1");

            $account->initializeAcl();

            $user = $account->createUser($email, $password, Scalr_Account_User::TYPE_ACCOUNT_OWNER);
            $user->fullname = $name;
            $user->save();

            if ($v == 2) {
                $user->setSetting('website.phone', $phone);
                $user->setSetting('website.country', $country);
                $user->setSetting('website.num_servers', $numServers);
            }

            /**
             * Limits
             */

            $url = Scalr::config('scalr.endpoint.scheme') . "://" . Scalr::config('scalr.endpoint.host');

            try {
                $billing = new Scalr_Billing();
                $billing->loadByAccount($account);
                $billing->createSubscription(Scalr_Billing::PAY_AS_YOU_GO, "", "", "", "");
                /*******************/
            } catch (Exception $e) {
                $account->delete();
                header("Location: {$url}/order/?error={$e->getMessage()}");
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

            $clientSettings[CLIENT_SETTINGS::RSS_LOGIN] = $email;
            $clientSettings[CLIENT_SETTINGS::RSS_PASSWORD] = $this->getCrypto()->sault(10);

            foreach ($clientSettings as $k=>$v)
                $account->setSetting($k, $v);

            try {
                $this->db->Execute("INSERT INTO default_records SELECT null, '{$account->id}', rtype, ttl, rpriority, rvalue, rkey FROM default_records WHERE clientid='0'");
            } catch(Exception $e) {
            }

            $clientinfo = array(
                'fullname'	=> $name,
                'firstname'	=> ($firstname) ? $firstname : $name,
                'email'		=> $email,
                'password'	=> $password
            );

            //Sends welcome email
            $this->getContainer()->mailer
                 ->setFrom('sales@scalr.com', 'Scalr')
                 ->setHtml()
                 ->sendTemplate(
                     SCALR_TEMPLATES_PATH . '/emails/welcome.html.php',
                     array(
                         'firstName'  => htmlspecialchars($clientinfo['firstname']),
                         'password'   => htmlspecialchars($clientinfo['password']),
                         "siteUrl"    => htmlspecialchars($url),
                         "wikiUrl"    => htmlspecialchars(\Scalr::config('scalr.ui.wiki_url')),
                         "supportUrl" => htmlspecialchars(\Scalr::config('scalr.ui.support_url')),
                         "isUrl"      => (preg_match('/^http(s?):\/\//i', \Scalr::config('scalr.ui.support_url'))),
                     ),
                     $email
                 )
            ;

            $user->getAccount()->setSetting(Scalr_Account::SETTING_IS_TRIAL, 1);

            //AutoLogin
            $user->updateLastLogin();
            Scalr_Session::create($user->getId());
            Scalr_Session::keepSession();
            $this->response->setRedirect("{$url}/thanks.html");
        } else {
            $errors = array_values($err);
            $error = $errors[0];
            $this->response->setRedirect("{$url}/order/?error={$error}");
        }
    }

    public function loginAction()
    {
        $this->response->page('ui/guest/login.js', array('loginAttempts' => 0, 'recaptchaPublicKey' => $this->getContainer()->config->get('scalr.ui.recaptcha.public_key')));
    }

    protected $ldapGroups = null;

    private function loginUserGet($login, $password, $accountId, $scalrCaptcha, $scalrCaptchaChallenge)
    {
        if ($login != '' && $password != '') {
            $isAdminLogin = $this->db->GetOne('SELECT * FROM account_users WHERE email = ? AND account_id = 0', array($login));

            if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap' && !$isAdminLogin) {
                $ldap = $this->getContainer()->ldap($login, $password);

                $this->response->setHeader('X-Scalr-LDAP-Login', $login);

                $tldap = 0;
                $start = microtime(true);
                $result = $ldap->isValidUser();
                $tldap = microtime(true) - $start;

                if ($result) {
                    try {
                        //Tries to retrieve user's email address from LDAP or provides that login is always with domain suffix
                        if (($pos = strpos($login, '@')) === false)
                            $login = $ldap->getEmail();

                        $start = microtime(true);
                        $groups = $ldap->getUserGroups();
                        $gtime = microtime(true) - $start;
                        $tldap += $gtime;
                        $this->response->setHeader('X-Scalr-LDAP-G-Query-Time', sprintf('%0.4f sec', $gtime));
                        $this->response->setHeader('X-Scalr-LDAP-Query-Time', sprintf('%0.4f sec', $tldap));
                        $this->response->setHeader('X-Scalr-LDAP-CLogin', $login);

                        $this->ldapGroups = $groups;
                    } catch (Exception $e) {
                        throw new Exception(
                            $e->getMessage()
                            . $ldap->getLog()
                        );
                    }

                    foreach ($groups as $key => $name)
                        $groups[$key] = $this->db->qstr($name);

                    $userAvailableAccounts = array();

                    if ($ldap->getConfig()->debug) {
                        $this->response->varDump($groups);
                        $this->response->setHeader('X-Scalr-LDAP-Debug', json_encode($ldap->getLog()));
                    }

                    // System users are not members of any group so if there is no groups then skip this.
                    if (count($groups) > 0) {
                        foreach ($this->db->GetAll('
                            SELECT clients.id, clients.name
                            FROM clients
                            JOIN client_environments ON client_environments.client_id = clients.id
                            JOIN account_team_envs ON account_team_envs.env_id = client_environments.id
                            JOIN account_teams ON account_teams.id = account_team_envs.team_id
                            WHERE account_teams.name IN(' . join(',', $groups) . ')') as $value) {
                                $userAvailableAccounts[$value['id']] = $value;
                        }
                    }

                    foreach ($this->db->GetAll("
                        SELECT clients.id, clients.name, clients.org, clients.dtadded
                        FROM clients
                        JOIN account_users ON account_users.account_id = clients.id
                        WHERE account_users.email = ? AND account_users.type = ?",
                        array($login, Scalr_Account_User::TYPE_ACCOUNT_OWNER)) as $value) {
                            $value['dtadded'] = Scalr_Util_DateTime::convertTz($value['dtadded'], 'M j, Y');
                            $userAvailableAccounts[$value['id']] = $value;
                    }
                    $userAvailableAccounts = array_values($userAvailableAccounts);

                    if (count($userAvailableAccounts) == 0) {
                        throw new Scalr_Exception_Core(
                            'You don\'t have access to any account. '
                          . $ldap->getLog()
                        );
                    }

                    if (count($userAvailableAccounts) == 1) {
                        $accountId = $userAvailableAccounts[0]['id'];
                    } else {
                        $ids = array();
                        foreach ($userAvailableAccounts as $value)
                            $ids[] = $value['id'];

                        if (!$accountId && !in_array($accountId, $ids)) {
                            $this->response->data(array(
                                'accounts' => $userAvailableAccounts
                            ));
                            throw new Exception();
                        }
                    }

                    $user = new Scalr_Account_User();
                    $user = $user->loadByEmail($login, $accountId);

                    if (!$user) {
                        $user = new Scalr_Account_User();
                        $user->type = Scalr_Account_User::TYPE_TEAM_USER;
                        $user->status = Scalr_Account_User::STATUS_ACTIVE;
                        $user->create($login, $accountId);
                    }

                    if (! $user->fullname) {
                        $user->fullname = $ldap->getFullName();
                        $user->save();
                    }

                    if ($ldap->getUsername() != $ldap->getEmail()) {
                        $user->setSetting(Scalr_Account_User::SETTING_LDAP_EMAIL, $ldap->getEmail());
                    } else {
                        $user->setSetting(Scalr_Account_User::SETTING_LDAP_EMAIL, '');
                    }
                } else {
                    throw new Exception(
                        "Incorrect login or password (1) "
                      . $ldap->getLog()
                    );
                }
            } else {
                $userAvailableAccounts = $this->db->GetAll('
                    SELECT account_users.id AS userId, clients.id, clients.name, clients.org, clients.dtadded, au.email AS `owner`
                    FROM account_users
                    LEFT JOIN clients ON clients.id = account_users.account_id
                    LEFT JOIN account_users au ON account_users.account_id = au.account_id
                    WHERE account_users.email = ? AND (au.type = ? OR account_users.type = ? OR account_users.type = ?)
                    GROUP BY userId
                ', array($login, Scalr_Account_User::TYPE_ACCOUNT_OWNER, Scalr_Account_User::TYPE_SCALR_ADMIN, Scalr_Account_User::TYPE_FIN_ADMIN));

                foreach ($userAvailableAccounts as &$ac) {
                    $ac['dtadded'] = Scalr_Util_DateTime::convertTz($ac['dtadded'], 'M j, Y');
                }

                if (count($userAvailableAccounts) == 1) {
                    $user = new Scalr_Account_User();
                    $user->loadById($userAvailableAccounts[0]['userId']);

                } else if (count($userAvailableAccounts) > 1) {
                    if ($accountId) {
                        foreach($userAvailableAccounts as $acc) {
                            if ($acc['id'] == $accountId) {
                                $user = new Scalr_Account_User();
                                $user->loadById($acc['userId']);
                                break;
                            }
                        }
                    } else {
                        $this->response->data(array(
                            'accounts' => $userAvailableAccounts
                        ));
                        throw new Exception();
                    }

                } else {
                    throw new Exception("Incorrect login or password (3)");
                }

                if ($user) {
                    // kaptcha
                    if (($user->loginattempts > 2) && $this->getContainer()->config->get('scalr.ui.recaptcha.private_key')) {
                        $curl = curl_init();
                        curl_setopt($curl, CURLOPT_URL, 'http://www.google.com/recaptcha/api/verify');
                        curl_setopt($curl, CURLOPT_POST, true);
                        $post = 'privatekey=' . urlencode($this->getContainer()->config->get('scalr.ui.recaptcha.private_key')) .
                            '&remoteip=' . urlencode($this->request->getRemoteAddr()) .
                            '&challenge=' . urlencode($scalrCaptchaChallenge) .
                            '&response=' . urlencode($scalrCaptcha);

                        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
                        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
                        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl, CURLINFO_HEADER_OUT, true);

                        $response = curl_exec($curl);
                        curl_close($curl);
                        $responseStrings = explode("\n", $response);

                        if ($responseStrings[0] !== 'true') {
                            $this->response->data(array(
                                'loginattempts' => $user->loginattempts,
                                'kaptchaError' => $response
                            ));
                            throw new Exception();
                        }
                    }

                    if (! $user->checkPassword($password)) {
                        if ($this->getContainer()->config->get('scalr.ui.recaptcha.private_key')) {
                            $this->response->data(array(
                                'loginattempts' => $user->loginattempts
                            ));
                        }
                        throw new Exception("Incorrect login or password (1)");
                    }
                } else {
                    throw new Exception("Incorrect login or password (2)");
                }
            }

            // valid user, other checks
            $whitelist = $user->getVar(Scalr_Account_User::VAR_SECURITY_IP_WHITELIST);
            if ($whitelist) {
                $subnets = unserialize($whitelist);
                if (! Scalr_Util_Network::isIpInSubnets($this->request->getRemoteAddr(), $subnets))
                    throw new Exception('The IP address you are attempting to log in from isn\'t authorized');
            }

            return $user;
        } else {
            throw new Exception('Incorrect login or password (0)');
        }
    }

    /**
     * @param Scalr_Account_User $user
     * @param bool $keepSession
     */
    private function loginUserCreate($user, $keepSession)
    {
        $user->updateLastLogin();
        Scalr_Session::create($user->getId());

        if (Scalr::config('scalr.auth_mode') == 'ldap') {
            $user->applyLdapGroups($this->ldapGroups);
        } else {
            if ($keepSession)
                Scalr_Session::keepSession();
        }

        $this->response->data(array('userId' => $user->getId(), 'specialToken' => Scalr_Session::getInstance()->getToken()));
    }

    public function xLoginFakeAction()
    {
        $this->response->setResponse(file_get_contents(APPPATH . '/www/login.html'));
    }

    /**
     * @param string $scalrLogin
     * @param RawData $scalrPass
     * @param bool $scalrKeepSession
     * @param int $accountId
     * @param string $tfaGglCode
     * @param bool $tfaGglReset
     * @param string $scalrCaptcha
     * @param string $scalrCaptchaChallenge
     */
    public function xLoginAction($scalrLogin, RawData $scalrPass, $scalrKeepSession = false, $accountId = 0, $tfaGglCode = '', $tfaGglReset = false, $scalrCaptcha = '', $scalrCaptchaChallenge = '')
    {
        $user = $this->loginUserGet($scalrLogin, $scalrPass, $accountId, $scalrCaptcha, $scalrCaptchaChallenge);

        // check for 2-factor auth
        if (
            ($user->getAccountId() && $user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_2FA) || !$user->getAccountId()) &&
            ($user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == 1)
        ) {
            if ($tfaGglCode) {
                if ($tfaGglReset) {
                    $resetCode = $user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_RESET_CODE);
                    if ($resetCode != Scalr_Util_CryptoTool::hash($tfaGglCode)) {
                        $this->response->data(array('errors' => array('tfaGglCode' => 'Invalid reset code')));
                        $this->response->failure();
                        return;
                    } else {
                        $user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL, '');
                        $user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY, '');
                        $user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_RESET_CODE, '');
                        $this->response->success('Two-factor authentication has been disabled.');
                    }
                } else {
                    $key = $this->getCrypto()->decrypt($user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY));
                    if (! Scalr_Util_Google2FA::verifyKey($key, $tfaGglCode)) {
                        $this->response->data(array('errors' => array('tfaGglCode' => 'Invalid code')));
                        $this->response->failure();
                        return;
                    }
                }
            } else {
                $this->response->data(array('tfaGgl' => true));
                $this->response->failure();
                return;
            }
        }

        $this->loginUserCreate($user, $scalrKeepSession);
    }

    public function recoverPasswordAction()
    {
        $this->response->page('ui/guest/recoverPassword.js');
    }

    /**
     * @param $email
     */
    public function xResetPasswordAction($email)
    {
        $user = Scalr_Account_User::init()->loadByEmail($email);

        if ($user) {
            $hash = $this->getCrypto()->sault(10);
            $user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash);
            $clientinfo = array(
                'email'    => $user->getEmail(),
                'fullname' => $user->fullname,
            );

            // Send welcome E-mail
            $this->getContainer()->mailer->sendTemplate(
                SCALR_TEMPLATES_PATH . '/emails/password_reset_confirmation.eml',
                array(
                    '{{fullname}}' => $clientinfo['fullname'],
                    '{{link}}'     => Scalr::config('scalr.endpoint.scheme') . "://" . Scalr::config('scalr.endpoint.host') . "/#/guest/updatePassword/?hash={$hash}",
                ),
                $clientinfo['email'], $clientinfo['fullname']
            );

            $this->response->success("Confirmation email has been sent to you");
        } else {
            $this->response->failure("Specified e-mail not found in our database");
        }
    }

    /**
     * @param $hash
     */
    public function updatePasswordAction($hash)
    {
        $user = Scalr_Account_User::init()->loadBySetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash);
        $this->response->page('ui/guest/updatePassword.js', array('valid' => is_object($user), 'authenticated' => is_object($this->user)));
    }

    /**
     * @param $hash
     * @param $password
     */
    public function xUpdatePasswordAction($hash, $password)
    {
        $user = Scalr_Account_User::init()->loadBySetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash);

        if ($user && $password) {
            $user->updatePassword($password);
            $user->loginattempts = 0;
            $user->save();

            $user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, "");

            //Scalr_Session::create($user->getAccountId(), $user->getId(), $user->getType());

            $this->response->success("Password has been reset. Please log in.");
        } else {
            $this->response->failure("Incorrect confirmation link");
        }
    }

    /**
     * @param int $userId
     * @param int $envId
     * @param JsonData $uiStorage
     * @param JsonData $updateDashboard
     */
    public function xPerpetuumMobileAction($userId, $envId, JsonData $uiStorage, JsonData $updateDashboard)
    {
        $result = array();

        if ($this->user) {
            if ($updateDashboard)
                $result['updateDashboard'] = Scalr_UI_Controller::loadController('dashboard')->checkLifeCycle($updateDashboard);

            if (!Scalr_Session::getInstance()->isVirtual() && $uiStorage) {
                $this->user->setSetting(Scalr_Account_User::SETTING_UI_STORAGE_TIME, $uiStorage['time']);
                $this->user->setVar(Scalr_Account_User::VAR_UI_STORAGE, $uiStorage['dump']);
            }
        }

        $equal = $this->user && ($this->user->getId() == $userId) &&
            (($this->getEnvironment() ? $this->getEnvironmentId() : 0) == $envId);

        $result['equal'] = $equal;
        $result['isAuthenticated'] = $this->user ? true : false;

        $this->response->data($result);
    }

    /**
     * @param $url
     * @param $file
     * @param $lineno
     * @param RawData $message
     */
    public function xPostErrorAction($url, $file, $lineno, RawData $message)
    {
        $this->response->success();

        if ($this->user) {
            $messageArr = explode("\n", $message);
            if (empty($messageArr[0]))
                return;

            $this->db->Execute('INSERT INTO ui_errors (tm, file, lineno, url, short, message, browser, account_id, user_id) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE cnt = cnt + 1, tm = NOW()', array(
                $file,
                $lineno,
                $url,
                $messageArr[0],
                $message,
                $_SERVER['HTTP_USER_AGENT'],
                $this->user ? $this->user->getAccountId() : '',
                $this->user ? $this->user->id : ''
            ));
        }
    }
}
