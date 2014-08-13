<?php
use Scalr\Acl\Acl;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;

class Scalr_UI_Controller_Core extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user ? true : false;
    }

    /**
     * @param bool $resetCounter
     */
    public function xGetChangeLogAction($resetCounter = false)
    {
        if ($resetCounter) {
            $this->user->setSetting(Scalr_Account_User::SETTING_UI_CHANGELOG_TIME, time());
            $this->response->success();
        } else {
            $rssCachePath = CACHEPATH."/rss.changelog.cxml";
            $data = array();
            if (file_exists($rssCachePath) && (time() - filemtime($rssCachePath) < 3600)) {
                clearstatcache();
                $data = json_decode(file_get_contents($rssCachePath), true);
            } else {
                $feedUrl = $this->getContainer()->config->get('scalr.ui.changelog_rss_url');
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $feedUrl);
                curl_setopt($curl, CURLOPT_TIMEOUT, 10);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

                $feedContent = curl_exec($curl);
                curl_close($curl);

                if ($feedContent && !empty($feedContent)) {
                    $feedXml = simplexml_load_string($feedContent);

                    if($feedXml) {
                        foreach ($feedXml->entry as $key=>$item) {
                            $data[] = array(
                                'text' =>  (string)$item->title,
                                'url'  =>  (string)$item->link->attributes()->href,
                                'time' =>  date('M d Y',strtotime((string)$item->published)),
                                'timestamp'  => strtotime((string)$item->published)
                            );
                        }
                    }
                }
                file_put_contents($rssCachePath, json_encode($data));
            }

            $tm = $this->user->getSetting(Scalr_Account_User::SETTING_UI_CHANGELOG_TIME);
            $countNew = 0;

            if (count($data) > 100) {
                $data = array_slice($data, 0, 100);
            }

            foreach ($data as &$v) {
                $v['new'] = $v['timestamp'] > $tm ? true : false;
                if ($v['new'])
                    $countNew++;
            }

            $this->response->data(array('data' => $data, 'countNew' => $countNew));
        }
    }

    public function supportAction()
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        if ($this->user) {
            $args = array(
                "name"		=> $this->user->fullname,
                "AccountID" => $this->user->getAccountId(),
                "email"		=> $this->user->getEmail(),
                "expires" => date("D M d H:i:s O Y", time()+120)
            );

            $token = Scalr_Util_CryptoTool::generateTenderMultipassToken(json_encode($args));

            $this->response->setRedirect("http://support.scalr.net/?sso={$token}");
        } else {
            $this->response->setRedirect("/");
        }
    }

    public function apiAction()
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

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
        $params['api.endpoint'] = \Scalr::config('scalr.endpoint.scheme').'://'.\Scalr::config('scalr.endpoint.host').'/api/api.php';

        $this->response->page('ui/core/api.js', $params);
    }

    public function disasterAction()
    {
        $this->response->page('ui/core/disaster.js');
    }

    public function troubleshootAction()
    {
        $this->response->page('ui/core/troubleshoot.js');
    }

    public function xSaveApiSettingsAction()
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $apiEnabled = $this->getParam(str_replace(".", "_", Scalr_Account_User::SETTING_API_ENABLED)) == 'on' ? true : false;
        $ipWhitelist = $this->getParam(str_replace(".", "_", Scalr_Account_User::SETTING_API_IP_WHITELIST));

        $this->user->setSetting(Scalr_Account_User::SETTING_API_ENABLED, $apiEnabled);
        $this->user->setSetting(Scalr_Account_User::SETTING_API_IP_WHITELIST, $ipWhitelist);

        $this->response->success('API settings successfully saved');
    }

    public function xRegenerateApiKeysAction()
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $keys = Scalr::GenerateAPIKeys();

        $this->user->setSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY, $keys['id']);
        $this->user->setSetting(Scalr_Account_User::SETTING_API_SECRET_KEY, $keys['key']);

        $this->response->success('Keys have been regenerated');
        $this->response->data(array('keys' => $keys));
    }

    public function securityAction()
    {
        $subnets = $this->user->getVar(Scalr_Account_User::VAR_SECURITY_IP_WHITELIST);
        $whitelist = array();
        if ($subnets) {
            $subnets = unserialize($subnets);
            foreach ($subnets as $subnet)
                $whitelist[] = Scalr_Util_Network::convertSubnetToMask($subnet);
        }

        $params = array(
            'security2fa' => $this->user->getAccountId() ? $this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_2FA) : true,
            'security2faGgl' => $this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) ? '1' : '',
            'security2faCode' => Scalr_Util_Google2FA::generateSecretKey(),
            'securityIpWhitelist' => join(', ', $whitelist)
        );

        $this->response->page('ui/core/security.js', $params, ['ux-qrext.js']);
    }

    /**
     * @param $password
     * @param $cpassword
     * @param $securityIpWhitelist
     */
    public function xSecuritySaveAction($password, $cpassword, $securityIpWhitelist)
    {
        $validator = new Validator();
        $validator->validate($password, 'password', Validator::NOEMPTY);
        $validator->validate($cpassword, 'cpassword', Validator::NOEMPTY);
        $validator->addErrorIf(($password && $cpassword && ($password != $cpassword)), ['password','cpassword'], 'Two passwords are not equal');

        $subnets = array();
        $securityIpWhitelist = trim($securityIpWhitelist);
        if ($securityIpWhitelist) {
            $whitelist = explode(',', $securityIpWhitelist);

            foreach ($whitelist as $mask) {
                $sub = Scalr_Util_Network::convertMaskToSubnet($mask);
                if ($sub)
                    $subnets[] = $sub;
                else
                    $validator->addError('securityIpWhitelist', sprintf('Not valid mask: %s', $mask));
            }
        }

        if (count($subnets) && !Scalr_Util_Network::isIpInSubnets($this->request->getRemoteAddr(), $subnets))
            $validator->addError('securityIpWhitelist', 'New IP access whitelist doesn\'t correspond your current IP address');

        if ($validator->isValid($this->response)) {
            $updateSession = false;

            if ($password != '******') {
                $this->user->updatePassword($password);
                $updateSession = true;
            }

            $this->user->setVar(Scalr_Account_User::VAR_SECURITY_IP_WHITELIST, count($subnets) ? serialize($subnets) : '');
            $this->user->save();

            if ($updateSession)
                Scalr_Session::create($this->user->getId());

            $this->response->success('Security settings successfully updated');
        }
    }

    /**
     * @param string $code
     * @throws Exception
     */
    public function xSettingsDisable2FaGglAction($code)
    {
        if (! $this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL)) {
            throw new Exception('Two-factor authentication has been already disabled for this user.');
        }

        $error = NULL;
        if ($code) {
            $qr = $this->getCrypto()->decrypt($this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY));
            $resetCode = $this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_RESET_CODE);

            if (Scalr_Util_Google2FA::verifyKey($qr, $code) || Scalr_Util_CryptoTool::hash($code) == $resetCode) {
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL, '');
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY, '');
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_RESET_CODE, '');
                $this->response->success('Two-factor authentication has been disabled.');
                return;
            } else {
                $error = 'Invalid code';
            }
        } else {
            $error = 'Code is required field';
        }

        $this->response->failure();
        $this->response->data(['errors' => ['code' => $error]]);
    }

    /**
     * @param $qr
     * @param $code
     * @throws Exception
     */
    public function xSettingsEnable2FaGglAction($qr, $code)
    {
        if ($this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == 1) {
            throw new Exception('Two-factor authentication has been already enabled for this user');
        }

        if ($qr && $code) {
            if (Scalr_Util_Google2FA::verifyKey($qr, $code)) {
                $resetCode = Scalr_Util_CryptoTool::sault(12);
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL, 1);
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY,
                    $this->getCrypto()->encrypt($qr)
                );
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_RESET_CODE,
                    Scalr_Util_CryptoTool::hash($resetCode)
                );

                $this->response->data(['resetCode' => $resetCode]);
            } else {
                $this->response->data(array('errors' => array(
                    'code' => 'Invalid code'
                )));
                $this->response->failure();
            }
        } else {
            $this->response->failure('Invalid data');
        }
    }


    public function settingsAction()
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $panel = $this->user->getDashboard($this->getEnvironmentId());

        $params = array_merge(
            $this->user->getSshConsoleSettings(),
            array(
                'gravatar_email' => $this->user->getSetting(Scalr_Account_User::SETTING_GRAVATAR_EMAIL) ? $this->user->getSetting(Scalr_Account_User::SETTING_GRAVATAR_EMAIL) : '',
                'gravatar_hash' => $this->user->getGravatarHash(),
                'rss_login' => $this->user->getSetting(Scalr_Account_User::SETTING_RSS_LOGIN),
                'rss_pass' => $this->user->getSetting(Scalr_Account_User::SETTING_RSS_PASSWORD),
                'timezone' => $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE),
                'timezones_list' => Scalr_Util_DateTime::getTimezones(),
                'user_email' => $this->user->getEmail(),
                'user_fullname' => $this->user->fullname,
                'dashboard_columns' => count($panel['configuration']),
                'scalr.id' => SCALR_ID
            )
        );

        $this->response->page('ui/core/settings.js', $params);
    }

    public function xSaveSettingsAction()
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->request->defineParams(array(
            'rss_login', 'rss_pass', 'default_environment',
            Scalr_Account_User::VAR_SSH_CONSOLE_USERNAME,
            Scalr_Account_User::VAR_SSH_CONSOLE_PORT,
            Scalr_Account_User::VAR_SSH_CONSOLE_KEY_NAME,
            Scalr_Account_User::VAR_SSH_CONSOLE_DISABLE_KEY_AUTH,
            Scalr_Account_User::VAR_SSH_CONSOLE_LOG_LEVEL,
            Scalr_Account_User::VAR_SSH_CONSOLE_PREFERRED_PROVIDER

        ));

        $rssLogin = $this->getParam('rss_login');
        $rssPass = $this->getParam('rss_pass');

        if ($rssLogin != '' || $rssPass != '') {
            if (strlen($rssLogin) < 6)
                $err['rss_login'] = "RSS feed login must be 6 chars or more";

            if (strlen($rssPass) < 6)
                $err['rss_pass'] = "RSS feed password must be 6 chars or more";
        }

        if (count($err)) {
            $this->response->failure();
            $this->response->data(array('errors' => $err));
            return;
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

        $this->user->setSetting(Scalr_Account_User::SETTING_RSS_LOGIN, $rssLogin);
        $this->user->setSetting(Scalr_Account_User::SETTING_RSS_PASSWORD, $rssPass);
        $this->user->setSetting(Scalr_Account_User::SETTING_UI_TIMEZONE, $this->getParam('timezone'));

        $gravatarEmail = $this->getParam('gravatar_email');
        $this->user->setSetting(Scalr_Account_User::SETTING_GRAVATAR_EMAIL, $gravatarEmail);

        if ($this->request->isAllowed(Acl::RESOURCE_FARMS_SERVERS, Acl::PERM_FARMS_SERVERS_SSH_CONSOLE)) {
            $this->user->setSshConsoleSettings($this->request->getParams());
        }

        $this->user->fullname = $this->getParam('user_fullname');
        $this->user->save();

        $this->response->success('Settings successfully updated');
        $this->response->data(array('panel' => $panel, 'gravatarHash' => $this->user->getGravatarHash()));
    }

    public function variablesAction()
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->request->restrictAccess(Acl::RESOURCE_ENVADMINISTRATION_GLOBAL_VARIABLES);
        $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT);
        $this->response->page('ui/core/variables.js', array('variables' => json_encode($vars->getValues())), array('ui/core/variablefield.js'), array('ui/core/variablefield.css'));
    }

    /**
     * @param JsonData $variables JSON encoded structure
     */
    public function xSaveVariablesAction(JsonData $variables)
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->request->restrictAccess(Acl::RESOURCE_ENVADMINISTRATION_GLOBAL_VARIABLES);

        $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT);
        $result = $vars->setValues($variables, 0, 0, 0, '', false);
        if ($result === true)
            $this->response->success('Variables saved');
        else {
            $this->response->failure();
            $this->response->data(array(
                'errors' => array(
                    'variables' => $result
                )
            ));
        }
    }

    /**
     * @param int $envId
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xChangeEnvironmentAction($envId)
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $env = Scalr_Environment::init()->loadById($envId);

        foreach ($this->user->getEnvironments() as $e) {
            if ($env->id == $e['id']) {
                Scalr_Session::getInstance()->setEnvironmentId($e['id']);

                if (! Scalr_Session::getInstance()->isVirtual())
                    $this->user->setSetting(Scalr_Account_User::SETTING_UI_ENVIRONMENT, $e['id']);

                $this->response->success();
                return;
            }
        }

        throw new Scalr_Exception_InsufficientPermissions();
    }
}
