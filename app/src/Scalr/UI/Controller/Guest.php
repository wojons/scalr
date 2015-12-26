<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity\Tag;
use Scalr\Model\Entity\Os;
use Scalr\UI\Request\RawData;
use Scalr\UI\Request\JsonData;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Util\CryptoTool;
use \Scalr\AuditLogger;
use Scalr\DataType\ScopeInterface;
use Scalr\UI\Request\Validator;
use Scalr\Model\Entity\CloudCredentialsProperty;
use Scalr\Model\Entity\CloudCredentials;
use Scalr\Model\Entity\Account\User;

class Scalr_UI_Controller_Guest extends Scalr_UI_Controller
{
    protected $ldapGroups = null;

    public function logoutAction()
    {
        $this->auditLog("user.auth.logout", ['result' => 'success']);
        Scalr_Session::destroy();
        $this->response->setRedirect('/');
    }

    public function hasAccess()
    {
        return true;
    }

    /**
     * @param int $uiStorageTime optional
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xInitAction($uiStorageTime = 0)
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
            $this->response->getModuleName("ui.js"),
            $this->response->getModuleName("resourcesearch.js")
        );

        if (Scalr_Session::getInstance()->getDebugMode()) {
            $initParams['extjs'][] = $this->response->getModuleName('ui-debug.js');
        }

        $initParams['css'] = array(
            $this->response->getModuleName("ui.css")
        );

        $initParams['uiHash'] = $this->response->pageUiHash();
        $initParams['context'] = $this->getContext($uiStorageTime);

        $this->response->data(array('initParams' => $initParams));
    }

    /**
     * @param  int $uiStorageTime optional
     * @return array
     */
    public function getContext($uiStorageTime = 0)
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
                'envVars' => '',
                'type' => $this->user->getType(),
                'settings' => [
                    Scalr_Account_User::SETTING_UI_TIMEZONE => $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE)
                ]
            );

            if ($this->getEnvironment()) {
                $data['user']['envVars'] = $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_UI_VARS);
            } else if ($this->user->getAccountId()) {
                $data['user']['envVars'] = $this->user->getAccount()->getSetting(Scalr_Account::SETTING_UI_VARS);
            }

            if (($uiStorageTime > 0) && ($uiStorageTime < $this->user->getSetting(Scalr_Account_User::SETTING_UI_STORAGE_TIME)) && !Scalr_Session::getInstance()->isVirtual()) {
                $data['user']['uiStorage'] = $this->user->getVar(Scalr_Account_User::VAR_UI_STORAGE);
            }

            $envVars = json_decode($data['user']['envVars'], true);
            $betaMode = ($envVars && $envVars['beta'] == 1);

            if (! $this->user->isAdmin()) {
                $data['flags'] = [];
                if ($this->user->getAccountId() != 0) {
                    $data['user']['userIsTrial'] = $this->user->getAccount()->getSetting(Scalr_Account::SETTING_IS_TRIAL) == '1' ? true : false;
                }

                $data['flags']['billingExists'] = \Scalr::config('scalr.billing.enabled');
                $data['flags']['showDeprecatedFeatures'] = \Scalr::config('scalr.ui.show_deprecated_features');

                $data['flags']['wikiUrl'] = \Scalr::config('scalr.ui.wiki_url');
                $data['flags']['supportUrl'] = \Scalr::config('scalr.ui.support_url');
                if ($data['flags']['supportUrl'] == '/core/support') {
                    $data['flags']['supportUrl'] .= '?X-Requested-Token=' . Scalr_Session::getInstance()->getToken();
                }

                $data['acl'] = $this->request->getAclRoles()->getAllowedArray(true);

                if (! $this->user->isAccountOwner()) {
                    $data['user']['accountOwnerName'] = $this->user->getAccount()->getOwner()->getEmail();
                }

                $data['environments'] = $this->user->getEnvironments();

                if ($this->user->isAccountOwner()) {
                    if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED)) {
                        $data['flags']['needEnvConfig'] = true;
                    }
                }

                if ($this->request->getScope() == 'environment') {
                    $sql = 'SELECT id, name FROM farms WHERE env_id = ?';
                    $args = [$this->getEnvironmentId()];

                    list($sql, $args) = $this->request->prepareFarmSqlQuery($sql, $args);
                    $sql .= ' ORDER BY name';

                    $data['farms'] = $this->db->getAll($sql, $args);

                    if ($this->getEnvironment() && $this->user->isTeamOwner()) {
                        $data['user']['isTeamOwner'] = true;
                    }
                }
            }

            //OS
            $data['os'] = [];
            foreach (Os::find() as $os) {
                /* @var $os Os */
                $data['os'][] = [
                    'id' => $os->id,
                    'family' => $os->family,
                    'name' => $os->name,
                    'generation' => $os->generation,
                    'version' => $os->version,
                    'status' => $os->status
                ];
            }

            $data['defaults'] = (new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(true), ScopeInterface::SCOPE_ENVIRONMENT))->getUiDefaults();

            $data['platforms'] = [];
            $allowedClouds = (array) \Scalr::config('scalr.allowed_clouds');

            if ($this->user->getAccountId() == 263)
                array_push($allowedClouds, SERVER_PLATFORMS::VERIZON);

            $platforms = SERVER_PLATFORMS::getList();
            if (!($this->request->getHeaderVar('Interface-Beta') || $betaMode)) {
                $platforms = array_intersect_key($platforms, array_flip($allowedClouds));
            }

            $environment = $this->getEnvironment();
            if (!empty($environment)) {
                $cloudsCredentials = $environment->cloudCredentialsList(array_keys($platforms));
            }

            foreach ($platforms as $platform => $platformName) {
                if (!in_array($platform, $allowedClouds) && !$this->request->getHeaderVar('Interface-Beta') && !$betaMode) {
                    continue;
                }

                $data['platforms'][$platform] = array(
                    'public'  => PlatformFactory::isPublic($platform),
                    'enabled' => ($this->user->isAdmin() || $this->request->getScope() != 'environment') ? true : (isset($cloudsCredentials[$platform]) && $cloudsCredentials[$platform]->isEnabled()),
                    'name'    => $platformName,
                );

                if (! ($this->user->isAdmin() || $this->request->getScope() != 'environment')) {
                    if ($platform == SERVER_PLATFORMS::EC2 && $this->environment->status == Scalr_Environment::STATUS_INACTIVE && $this->environment->getPlatformConfigValue('system.auto-disable-reason')) {
                        $data['platforms'][$platform]['config'] = array('autoDisabled' => true);
                    }

                    if (PlatformFactory::isOpenstack($platform) && $data['platforms'][$platform]['enabled']) {
                        $ccProps = $cloudsCredentials[$platform]->properties;
                        $data['platforms'][$platform]['config'] = [
                            CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED  => $ccProps[CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED],
                            CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED           => $ccProps[CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED],
                            CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED    => $ccProps[CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED],
                            CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED          => $ccProps[CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED],
                            CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED           => $ccProps[CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED]
                        ];
                    }
                }
            }

            $data['flags']['uiStorageTime'] = $this->user->getSetting(Scalr_Account_User::SETTING_UI_STORAGE_TIME);
            $data['flags']['uiStorage'] = $this->user->getVar(Scalr_Account_User::VAR_UI_STORAGE);
            $data['flags']['allowManageAnalytics'] = $this->user->getAccountId() && Scalr::isAllowedAnalyticsOnHostedScalrAccount($this->user->getAccountId());

            $data['scope'] = $this->request->getScope();
            if ($this->request->getScope() == 'environment') {
                $governance = new Scalr_Governance($this->getEnvironmentId());
                $data['governance'] = $governance->getValues(true);
            }
        }

        if ($this->user) {
            $data['tags'] = Tag::getAll($this->user->getAccountId());
        }

        $data['flags']['authMode'] = $this->getContainer()->config->get('scalr.auth_mode');
        $data['flags']['recaptchaPublicKey'] = $this->getContainer()->config->get('scalr.ui.recaptcha.public_key');
        $data['flags']['specialToken'] = Scalr_Session::getInstance()->getToken();
        $data['flags']['hostedScalr'] = (bool) Scalr::isHostedScalr();
        $data['flags']['analyticsEnabled'] = $this->getContainer()->analytics->enabled;
        $data['flags']['apiEnabled'] = (bool) \Scalr::config('scalr.system.api.enabled');

        return $data;
    }

    /**
     * @param int $uiStorageTime optional
     */
    public function xGetContextAction($uiStorageTime = 0)
    {
        $this->response->data($this->getContext($uiStorageTime));
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
     * @param string  $name
     * @param string  $org
     * @param string  $email
     * @param RawData $password
     * @param string  $agreeTerms
     * @param string  $newBilling
     * @param string  $country
     * @param string  $phone
     * @param string  $lastname
     * @param string  $firstname
     * @param string  $v
     * @param string  $numServers
     */
    public function xCreateAccountAction($name = '', $org = '', $email = '', RawData $password = null, $agreeTerms = '', $newBilling = '', $country = '', $phone = '', $lastname = '', $firstname = '', $v = '', $numServers = '', $beta = 0)
    {
        if (!\Scalr::config('scalr.billing.enabled')) {
            header("HTTP/1.0 403 Forbidden");
            exit();
        }

        $validator = new Validator();

        if ($v == 2) {
            $validator->validate($firstname, "firstname", Validator::NOEMPTY, [], "First name is required");
            $validator->validate($lastname, "lastname", Validator::NOEMPTY, [], "Last name is required");
            $name = $firstname . " " . $lastname;
        } else {
            $validator->validate($name, "name", Validator::NOEMPTY, [], "Account name is required");
        }

        if ($password == '') {
            $password = \Scalr::GenerateSecurePassword(User::PASSWORD_ADMIN_LENGTH);
        }

        $validator->validate($email, "email", Validator::EMAIL);
        $validator->validate($password, "password", Validator::PASSWORD, ['admin']);
        $validator->addErrorIf(
            $this->db->GetOne("SELECT EXISTS(SELECT * FROM account_users WHERE email = ?)", [$email]),
            "email",
            "E-mail already exists in the database"
        );
        $validator->validate($agreeTerms, "agreeTerms", Validator::NOEMPTY, [], "You haven't accepted terms and conditions");

        $errors = $validator->getErrors(true);

        if (empty($errors)) {
            $account = Scalr_Account::init();
            $account->name = $org ? $org : $name;
            $account->status = Scalr_Account::STATUS_ACTIVE;
            $account->save();

            $user = $account->createUser($email, $password, Scalr_Account_User::TYPE_ACCOUNT_OWNER);
            $user->fullname = $name;
            $user->save();

            if ($this->getContainer()->analytics->enabled) {
                $analytics = $this->getContainer()->analytics;

                
                    //Default Cost Center should be assigned
                    $cc = $analytics->ccs->get($analytics->usage->autoCostCentre());
                

                //Assigns account with Cost Center
                $accountCcEntity = new AccountCostCenterEntity($account->id, $cc->ccId);
                $accountCcEntity->save();
            }

            //Creates Environment. It will be associated with the Cost Center itself.
            $account->createEnvironment("Environment 1");

            $account->initializeAcl();

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

            if (!empty($clientSettings)) {
                foreach ($clientSettings as $k => $v) {
                    $account->setSetting($k, $v);
                }
            }

            try {
                $this->db->Execute("
                    INSERT INTO default_records
                    SELECT null, '{$account->id}', rtype, ttl, rpriority, rvalue, rkey
                    FROM default_records
                    WHERE clientid='0'
                ");
            } catch (Exception $e) {
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

            if ($beta != 1) {
                $this->response->setRedirect("{$url}/thanks.html");
            } else {
                $this->response->data(array('accountId' => $user->getAccountId()));
            }
        } else {
            if ($beta == 1) {
                header("HTTP/1.0 400 Bad request");
                print json_encode($errors);
                exit();
            } else {
                $error = array_values($errors)[0];
                $this->response->setRedirect("{$url}/order/?error={$error}");
            }
        }
    }

    /**
     * @param   string          $captcha
     * @return  bool|string     Return true or string if error
     * @throws  \Scalr\System\Config\Exception\YamlException
     */
    public function validateReCaptcha($captcha)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($curl, CURLOPT_POST, true);
        $post = 'secret=' . urlencode($this->getContainer()->config->get('scalr.ui.recaptcha.private_key')) .
            '&remoteip=' . urlencode($this->request->getRemoteAddr()) .
            '&response=' . urlencode($captcha);

        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($curl);
        curl_close($curl);
        $responseObject = json_decode($response, true);

        if ($responseObject) {
            if ($responseObject['success'] == true) {
                return true;
            } else {
                return is_array($responseObject['error-codes']) ? join(', ', $responseObject['error-codes']) : 'Error response';
            }
        } else {
            return 'No response';
        }
    }

    /**
     * @param string $login
     * @param string $password
     * @param int    $accountId
     * @param string $scalrCaptcha
     * @return Scalr_Account_User
     * @throws Exception
     * @throws Scalr_Exception_Core
     * @throws \Scalr\System\Config\Exception\YamlException
     */
    private function loginUserGet($login, $password, $accountId, $scalrCaptcha)
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

                    if (empty($userAvailableAccounts)) {
                        throw new Scalr_Exception_Core(
                            'You don\'t have access to any account. '
                          . $ldap->getLog()
                        );
                    } elseif (count($userAvailableAccounts) == 1) {
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
                        $user->setSetting(Scalr_Account_User::SETTING_LDAP_USERNAME, $ldap->getUsername());
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

                } elseif (count($userAvailableAccounts) > 1) {
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
                    if ($user->status != User::STATUS_ACTIVE) {
                        throw new Exception('User account has been deactivated. Please contact your account owner.');
                    }

                    // kaptcha
                    if (($user->loginattempts > 3) && $this->getContainer()->config->get('scalr.ui.recaptcha.private_key')) {
                        if (!$scalrCaptcha || ($r = $this->validateReCaptcha($scalrCaptcha)) !== true) {
                            $this->response->data(array(
                                'loginattempts' => $user->loginattempts,
                                'scalrCaptchaError' => isset($r) ? $r : 'empty-value'
                            ));
                            throw new Exception();
                        }
                    }

                    if (! $user->checkPassword($password)) {
                        $attempts = (int) $this->getContainer()->config->get('scalr.security.user.suspension.failed_login_attempts');
                        if ($attempts > 0 && $user->loginattempts >= $attempts && $user->getEmail() != 'admin') {
                            $user->status = User::STATUS_INACTIVE;
                            $user->loginattempts = 0;
                            $user->save();
                            throw new Exception('User account has been deactivated. Please contact your account owner.');
                        }

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
     * @param bool               $keepSession
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

    /**
     * @param string  $scalrLogin
     * @param RawData $scalrPass
     * @param bool    $scalrKeepSession
     * @param int     $accountId
     * @param string  $tfaGglCode
     * @param bool    $tfaGglReset
     * @param string  $scalrCaptcha
     * @param string  $scalrCaptchaChallenge
     */
    public function xLoginAction($scalrLogin, RawData $scalrPass, $scalrKeepSession = false, $accountId = 0, $tfaGglCode = '', $tfaGglReset = false, $scalrCaptcha = '', $scalrCaptchaChallenge = '')
    {
        $user = $this->loginUserGet($scalrLogin, $scalrPass, $accountId, $scalrCaptcha, $scalrCaptchaChallenge);

        $msg = [];

        // check for 2-factor auth
        if ($user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == 1) {
            if ($tfaGglCode) {
                if ($tfaGglReset) {
                    $resetCode = $user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_RESET_CODE);

                    if ($resetCode != CryptoTool::hash($tfaGglCode)) {
                        $this->response->data(["errors" => ["tfaGglCode" => "Invalid reset code"]]);

                        $this->auditLog("user.auth.login", [
                            'result'        => 'error',
                            'error_message' => 'Invalid reset code'
                        ]);

                        $this->response->failure();
                        return;
                    } else {
                        $user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL, '');
                        $user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY, '');
                        $user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_RESET_CODE, '');

                        $msg = ["info" => "Two-factor authentication has been disabled."];

                        $this->response->success($msg["info"]);
                    }
                } else {
                    $key = $this->getCrypto()->decrypt($user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY));

                    if (! Scalr_Util_Google2FA::verifyKey($key, $tfaGglCode)) {
                        $this->response->data(["errors" => ["tfaGglCode" => "Invalid code"]]);

                        $this->auditLog("user.auth.login", [
                            'result'        => 'error',
                            'error_message' => 'Invalid code'
                        ]);

                        $this->response->failure();
                        return;
                    }
                }
            } else {
                $this->response->data(["tfaGgl" => true]);
                $this->response->failure();
                return;
            }
        }

        $this->loginUserCreate($user, $scalrKeepSession);

        try {
            $envId = $this->getEnvironmentId(true) ?: $user->getDefaultEnvironment()->id;
        } catch (Exception $e) {
            $envId = null;
        }

        $this->auditLog(
            "user.auth.login",
            $user,
            $envId,
            $this->request->getRemoteAddr(),
            Scalr_Session::getInstance()->getRealUserId()
        );
    }

    /**
     * @param   string  $email
     * @param   string  $scalrCaptcha
     */
    public function xResetPasswordAction($email, $scalrCaptcha = '')
    {
        $user = Scalr_Account_User::init()->loadByEmail($email);

        if ($this->getContainer()->config->get('scalr.ui.recaptcha.private_key')) {
            $r = $this->validateReCaptcha($scalrCaptcha);
            if ($r !== true) {
                throw new Scalr_Exception_Core(sprintf('ReCaptcha error: %s', $r));
            }
        }

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
                    '{{link}}'     => Scalr::config('scalr.endpoint.scheme') . "://" . Scalr::config('scalr.endpoint.host') . "/?resetPasswordHash={$hash}",
                    '{{hash}}'     => $hash
                ),
                $clientinfo['email'], $clientinfo['fullname']
            );
        }

        $this->response->success("Confirmation email has been sent to you");
    }

    /**
     * @param string $hash
     */
    public function xUpdatePasswordValidateAction($hash)
    {
        if ($hash && ($user = Scalr_Account_User::init()->loadBySetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash))) {
            $this->response->data([
                'email' => $user->getEmail(),
                'isAdmin' => $user->isAccountOwner() || $user->isAccountAdmin(),
                'hash' => $hash
            ]);
        } else {
            $this->response->failure("Incorrect confirmation link");
        }
    }

    /**
     * @param string  $hash
     * @param RawData $password
     */
    public function xUpdatePasswordAction($hash, RawData $password)
    {
        if ($hash && ($user = Scalr_Account_User::init()->loadBySetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash)) && $password) {
            $validator = new Validator();
            $validator->validate($password, "password", Validator::PASSWORD, $user->isAccountAdmin() || $user->isAccountOwner() ? ['admin'] : []);
            if ($validator->isValid($this->response)) {
                $user->updatePassword($password);
                $user->loginattempts = 0;
                $user->save();

                $user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, "");

                $this->response->data(['email' => $user->getEmail(), 'message' => 'Password has been reset. Please log in.']);
            }
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

            if (!Scalr_Session::getInstance()->isVirtual() && $uiStorage->count()) {
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
     * @param   string  $url
     * @param   string  $file
     * @param   int     $lineno
     * @param   RawData $message
     * @param   string  $plugins
     */
    public function xPostErrorAction($url, $file = '', $lineno = 0, RawData $message, $plugins = '')
    {
        $this->response->success();

        if ($this->user && $message) {
            $this->db->Execute('INSERT INTO ui_errors (`tm`, `file`, `lineno`, `url`, `plugins`, `message`, `browser`, `account_id`, `user_id`) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)', [
                $file,
                $lineno,
                $url,
                $plugins,
                $message,
                filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'),
                $this->user->getAccountId(),
                $this->user->id
            ]);
        }
    }
}
