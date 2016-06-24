<?php

use Scalr\Acl\Acl;
use Scalr\UI\Request\RawData;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;
use Scalr\Util\CryptoTool;
use Scalr\Model\Entity\Account\User\ApiKeyEntity;
use Scalr\DataType\ScopeInterface;
use Scalr\System\Http\Client;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Core extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user ? true : false;
    }

    public function aboutAction()
    {
        $key = "short";
        if (!Scalr::isHostedScalr() || $this->user->isScalrAdmin() || $this->request->getHeaderVar('Interface-Beta')) {
            $key = $this->user->isScalrAdmin() || $this->request->getHeaderVar('Interface-Beta') ? "beta" : "full";
        }

        $this->response->page('ui/core/about.js', Scalr::getContainer()->version($key));
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

            $token = CryptoTool::generateTenderMultipassToken(json_encode($args));

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
        $params[Scalr_Account_User::SETTING_API_ACCESS_KEY] = $this->user->getSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY);
        $params[Scalr_Account_User::SETTING_API_SECRET_KEY] = $this->user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY);
        $params['api.endpoint'] = \Scalr::config('scalr.endpoint.scheme').'://'.\Scalr::config('scalr.endpoint.host').'/api/api.php';
        $params[Scalr_Account_User::VAR_API_IP_WHITELIST] = (string)$this->user->getVar(Scalr_Account_User::VAR_API_IP_WHITELIST);

        $this->response->page('ui/core/api.js', $params);
    }

    public function api2Action()
    {
        if ($this->user->isAdmin() || !\Scalr::config('scalr.system.api.enabled')) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->response->page('ui/core/api2.js');
    }

    public function xListApiKeysAction()
    {
        if ($this->user->isAdmin() || !\Scalr::config('scalr.system.api.enabled')) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $keys = [];

        foreach (ApiKeyEntity::findByUserId($this->user->getId()) as $apiKeyEntity) {
            /* @var $apiKeyEntity ApiKeyEntity */
            $row = get_object_vars($apiKeyEntity);
            $row['secretKey'] = '******';
            $row['created'] = Scalr_Util_DateTime::convertTz($apiKeyEntity->created);
            $row['lastUsed'] = $apiKeyEntity->lastUsed ? Scalr_Util_DateTime::convertTz($apiKeyEntity->lastUsed) : 'Never';
            $row['createdHr'] = $apiKeyEntity->created  ? Scalr_Util_DateTime::getFuzzyTimeString($apiKeyEntity->created) : '';
            $row['lastUsedHr'] = $apiKeyEntity->lastUsed ? Scalr_Util_DateTime::getFuzzyTimeString($apiKeyEntity->lastUsed) : 'Never';

            $keys[] = $row;
        }

        $this->response->data(['data' => $keys]);
    }


    public function xGenerateApiKeyAction()
    {
        if ($this->user->isAdmin() || !\Scalr::config('scalr.system.api.enabled')) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $apiKeyEntity = new ApiKeyEntity($this->user->getId());

        //It should prevents wiping out existing keys
        $attempts = 3;
        do {
            //Generates API KEY explicitly before saving
            $apiKeyEntity->keyId = $apiKeyEntity->getIterator()->getField('keyId')->getType()->generateValue($apiKeyEntity);

            if ($attempts-- == 0) {
                throw new RuntimeException("Could not generate uniquie API Key");
            }
        } while (ApiKeyEntity::findPk($apiKeyEntity->keyId) !== null);
        // Saving a new API key
        $apiKeyEntity->save();

        $row = get_object_vars($apiKeyEntity);
        $row['created'] = Scalr_Util_DateTime::convertTz($apiKeyEntity->created);
        $row['lastUsed'] = $apiKeyEntity->lastUsed ? Scalr_Util_DateTime::convertTz($apiKeyEntity->lastUsed) : 0;
        $row['createdHr'] = $apiKeyEntity->created ? Scalr_Util_DateTime::getFuzzyTimeString($apiKeyEntity->created) : '';
        $row['lastUsedHr'] = $apiKeyEntity->lastUsed ? Scalr_Util_DateTime::getFuzzyTimeString($apiKeyEntity->lastUsed) : 'Never';

        $this->response->data(['key' => $row]);
    }

    /**
     * Saves api key name
     *
     * @param string    $keyId     Api key id
     * @param string    $name      Api key name to save
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xSaveApiKeyNameAction($keyId, $name)
    {
        if ($this->user->isAdmin() || !\Scalr::config('scalr.system.api.enabled')) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        if (!preg_match('/^[a-z0-9 _-]*$/i', $name)) {
            throw new Scalr_Exception_Core("Name should contain only letters, numbers, spaces and dashes");
        }

        try {
            $apiKeyEntity = ApiKeyEntity::findPk($keyId);
            /* @var $apiKeyEntity ApiKeyEntity */
            if ($apiKeyEntity->userId != $this->user->getId()) {
                throw new Scalr_Exception_Core('Insufficient permissions to modify API key');
            }

            $apiKeyEntity->name = $name;
            $apiKeyEntity->save();
            $this->response->data(['name' => $name]);
        } catch (Exception $e) {
            $this->response->failure($e->getMessage());
            return;
        }

        $this->response->success();
    }

    /**
     * @param string    $action Action
     * @param JsonData  $keyIds JSON encoded structure
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xApiKeysActionHandlerAction($action, JsonData $keyIds)
    {
        if ($this->user->isAdmin() || !\Scalr::config('scalr.system.api.enabled')) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $processed = [];
        $errors = [];

        foreach($keyIds as $keyId) {
            try {
                $apiKeyEntity = ApiKeyEntity::findPk($keyId);
                /* @var $apiKeyEntity ApiKeyEntity */
                if ($apiKeyEntity->userId != $this->user->getId()) {
                    throw new Scalr_Exception_Core('Insufficient permissions to modify API key');
                }

                switch($action) {
                    case 'delete':
                        $apiKeyEntity->delete();
                        $processed[] = $keyId;
                        break;

                    case 'activate':
                    case 'deactivate':
                        $apiKeyEntity->active = $action == 'activate';
                        $apiKeyEntity->save();
                        $processed[] = $keyId;
                        break;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $num = count($keyIds);

        if (count($processed) == $num) {
            $this->response->success('All API keys processed');
        } else {
            array_walk($errors, function (&$item) {
                $item = '- ' . $item;
            });
            $this->response->warning(sprintf("Successfully processed only %d from %d API KEYS. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(['processed' => $processed]);
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
        $ipWhitelist = $this->getParam(str_replace(".", "_", Scalr_Account_User::VAR_API_IP_WHITELIST));

        $this->user->setSetting(Scalr_Account_User::SETTING_API_ENABLED, $apiEnabled);
        $this->user->setVar(Scalr_Account_User::VAR_API_IP_WHITELIST, $ipWhitelist);

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
            'security2fa' => true,
            'security2faGgl' => $this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) ? '1' : '',
            'security2faCode' => Scalr_Util_Google2FA::generateSecretKey(),
            'securityIpWhitelist' => join(', ', $whitelist),
            'currentIp' => $this->request->getRemoteAddr(),
            'isAdmin' => $this->user->isAccountOwner() || $this->user->isAccountAdmin() || $this->user->isAdmin()
        );

        $this->response->page('ui/core/security.js', $params, ['ux-qrext.js']);
    }

    /**
     * @param RawData $password
     * @param RawData $cpassword
     * @param string  $securityIpWhitelist
     * @param RawData $currentPassword optional
     */
    public function xSecuritySaveAction(RawData $password, RawData $cpassword, $securityIpWhitelist, RawData $currentPassword = null)
    {
        $validator = new Validator();
        $password = (string) $password;

        if ($password) {
            $validator->validate($password, 'password', Validator::PASSWORD, $this->user->isAccountOwner() || $this->user->isAccountAdmin() || $this->user->isAdmin() ? ['admin'] : []);
            $validator->addErrorIf(!$this->user->checkPassword($currentPassword, false), ['currentPassword'], 'Invalid password');
        }

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

            if ($password) {
                $this->user->updatePassword($password);
                $updateSession = true;

                // Send notification E-mail
                $this->getContainer()->mailer->sendTemplate(
                    SCALR_TEMPLATES_PATH . '/emails/password_change_notification.eml',
                    array(
                        '{{fullname}}' => $this->user->fullname ? $this->user->fullname : $this->user->getEmail()
                    ),
                    $this->user->getEmail(), $this->user->fullname
                );
            }

            $this->user->setVar(Scalr_Account_User::VAR_SECURITY_IP_WHITELIST, count($subnets) ? serialize($subnets) : '');
            $this->user->save();

            if ($updateSession) {
                Scalr_Session::create($this->user->getId());
                $this->response->data(['specialToken' => Scalr_Session::getInstance()->getToken()]);
            }

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

            if (Scalr_Util_Google2FA::verifyKey($qr, $code) || CryptoTool::hash($code) == $resetCode) {
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
     * @param string $qr
     * @param string $code
     * @throws Exception
     */
    public function xSettingsEnable2FaGglAction($qr, $code)
    {
        if ($this->user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == 1) {
            throw new Exception('Two-factor authentication has been already enabled for this user');
        }

        if ($qr && $code) {
            if (Scalr_Util_Google2FA::verifyKey($qr, $code)) {
                $resetCode = CryptoTool::sault(12);
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL, 1);
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_KEY,
                    $this->getCrypto()->encrypt($qr)
                );
                $this->user->setSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL_RESET_CODE,
                    CryptoTool::hash($resetCode)
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
        if ($this->user->isAdmin()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $panel = $this->user->getDashboard($this->getEnvironmentId(true));
        $params = [
            'scalrId' => SCALR_ID,
            'userEmail' => $this->user->getEmail(),
            'userFullname' => $this->user->fullname,
            'dashboardColumns' => count($panel['configuration']),
            'timezonesList' => Scalr_Util_DateTime::getTimezones(),
            'gravatarHash' => $this->user->getGravatarHash(),
        ];
        $params = array_merge($params, $this->user->getSshConsoleSettings());

        foreach ([Scalr_Account_User::SETTING_GRAVATAR_EMAIL, Scalr_Account_User::SETTING_UI_TIMEZONE] as $setting) {
            $v = $this->user->getSetting($setting);
            $params[$setting] = $v ? $v : '';
        }

        $this->response->page('ui/core/settings.js', $params);
    }

    /**
     * @param   int     $dashboardColumns   Number of dashboard columns
     * @param   string  $userFullname       Full name of user
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xSaveSettingsAction($dashboardColumns, $userFullname)
    {
        if ($this->user->isAdmin()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $sshSettings = [
            Scalr_Account_User::VAR_SSH_CONSOLE_USERNAME,
            Scalr_Account_User::VAR_SSH_CONSOLE_IP,
            Scalr_Account_User::VAR_SSH_CONSOLE_PORT,
            Scalr_Account_User::VAR_SSH_CONSOLE_KEY_NAME,
            Scalr_Account_User::VAR_SSH_CONSOLE_DISABLE_KEY_AUTH,
            Scalr_Account_User::VAR_SSH_CONSOLE_LOG_LEVEL,
            Scalr_Account_User::VAR_SSH_CONSOLE_PREFERRED_PROVIDER,
            Scalr_Account_User::VAR_SSH_CONSOLE_ENABLE_AGENT_FORWARDING
        ];
        $sshParams = [];
        foreach ($sshSettings as $s) {
            $sshParams[$s] = $this->request->getParam($s);
        }
        $this->user->setSshConsoleSettings($sshParams);

        $panel = $this->user->getDashboard($this->getEnvironmentId(true));
        $currentColumns = count($panel['configuration']);
        if ($dashboardColumns != $currentColumns) {
            if ($dashboardColumns > $currentColumns) {
                while ($dashboardColumns > count($panel['configuration'])) {
                    $panel['configuration'][] = array();
                }
            } else {
                for (; $currentColumns > $dashboardColumns; $currentColumns--) {
                    foreach($panel['configuration'][$currentColumns - 1] as $widg) {
                        $panel['configuration'][0][] = $widg;
                    }
                    unset($panel['configuration'][$currentColumns - 1]);
                }
            }

            $this->user->setDashboard($this->getEnvironmentId(true), $panel);
            $this->response->data(['dashboard' => $panel = Scalr_UI_Controller_Dashboard::controller()->fillDash($panel)]);
        }


        $uiSettings = [ Scalr_Account_User::SETTING_GRAVATAR_EMAIL, Scalr_Account_User::SETTING_UI_TIMEZONE ];
        foreach ($uiSettings as $s) {
            $this->user->setSetting($s, $this->request->getParam($s));
        }

        $this->user->fullname = $userFullname;
        $this->user->save();

        $this->response->success('Settings successfully updated');
        $this->response->data([ 'gravatarHash' => $this->user->getGravatarHash() ]);
    }

    public function variablesAction()
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->request->restrictAccess(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT);
        $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_ENVIRONMENT);
        $this->response->page('ui/core/variables.js', array('variables' => json_encode($vars->getValues())), array('ui/core/variablefield.js'));
    }

    /**
     * @param JsonData $variables JSON encoded structure
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws \Scalr\Exception\ValidationErrorException
     */
    public function xSaveVariablesAction(JsonData $variables)
    {
        if ($this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->request->restrictAccess(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT, Acl::PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE);

        $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_ENVIRONMENT);
        $result = $vars->setValues($variables, 0, 0, 0, '', false);
        if ($result === true) {
            $this->response->success('Variables saved');
            $this->response->data([
                'defaults' => $vars->getUiDefaults()
            ]);
        } else {
            $this->response->failure();
            $this->response->data(array(
                'errors' => array(
                    'variables' => $result
                )
            ));
        }
    }

    /**
     * @param   bool    $enabled
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xSaveDebugAction($enabled = false)
    {
        $session = Scalr_Session::getInstance();
        if ($session->isVirtual() || $this->user->isScalrAdmin()) {
            Scalr_Session::getInstance()->setDebugMode($enabled);

            if ($enabled) {
                $this->response->data(['js' => $this->response->getModuleName('ui-debug.js')]);
            }

            $this->response->success();
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    /**
     * @param   string  $query
     * @throws  Scalr_Exception_Core
     */
    public function xSearchResourcesAction($query)
    {
        if (trim($query) == '') {
            $this->response->data(['data' => []]);
            return;
        }

        $environments = $this->request->getScope() == ScopeInterface::SCOPE_ACCOUNT ?
            array_map(function ($r) {
                return $r['id'];
            }, $this->user->getEnvironments()) :
            [$this->getEnvironmentId()];

        $f = new Entity\Farm();
        $s = new Entity\Server();
        $fr = new Entity\FarmRole();
        $ft = new Entity\FarmTeam();
        $e = new Entity\Account\Environment();
        $at = new Entity\Account\Team();
        $sp = new Entity\Server\Property();

        $farmSql = [];
        $serverSql = [];
        $queryEnc = "%{$query}%";

        foreach ($environments as $envId) {
            $acl = $this->user->getAclRolesByEnvironment($envId);
            $isTermporaryServerPerm = $acl->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_BUILD) ||
                                      $acl->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_IMPORT);

            if ($acl->isAllowed(Acl::RESOURCE_FARMS)) {
                $farmSql[] = "{$f->columnEnvId('f')} = $envId";
                if ($isTermporaryServerPerm) {
                    $serverSql[] = "{$s->columnEnvId} = {$envId}";
                } else {
                    $serverSql[] = "{$s->columnEnvId} = {$envId} AND {$s->columnFarmId} IS NOT NULL";
                }
            } else {
                $q = [];
                if ($acl->isAllowed(Acl::RESOURCE_TEAM_FARMS)) {
                    $q[] = Entity\Farm::getUserTeamOwnershipSql($this->getUser()->id);
                }

                if ($acl->isAllowed(Acl::RESOURCE_OWN_FARMS)) {
                    $q[] = "{$f->columnOwnerId('f')} = '" . intval($this->getUser()->id) . "'";
                }

                if (count($q)) {
                    $farmSql[] = "{$f->columnEnvId('f')} = {$envId} AND (" . join(" OR ", $q) . ")";
                }

                if ($isTermporaryServerPerm) {
                    $q[] = "{$s->columnStatus} IN ('" . Entity\Server::STATUS_IMPORTING . "', '" . Entity\Server::STATUS_TEMPORARY . "') AND {$s->columnFarmId} IS NULL";
                }

                if (count($q)) {
                    $serverSql[] = "{$s->columnEnvId} = {$envId} AND (" . join(" OR ", $q) . ")";
                }
            }
        }

        $farms = [];

        if (count($farmSql)) {
            $farmStmt = $this->db->Execute("
                SELECT {$f->columnId('f')} AS id, {$f->columnName('f')} AS name, {$f->columnEnvId('f')} AS envId, {$f->columnStatus('f')} AS status,
                {$f->columnAdded('f')} AS added, {$f->columnCreatedByEmail('f')} AS createdByEmail, {$e->columnName} AS `envName`,
                GROUP_CONCAT({$at->columnName} SEPARATOR ', ') AS teamName
                FROM {$f->table('f')}
                LEFT JOIN {$e->table()} ON {$f->columnEnvId('f')} = {$e->columnId}
                LEFT JOIN {$ft->table()} ON {$ft->columnFarmId} = {$f->columnId('f')}
                LEFT JOIN {$at->table()} ON {$at->columnId} = {$ft->columnTeamId}
                WHERE ({$f->columnName('f')} LIKE ? OR {$f->columnId('f')} = ?) AND (" . join(" OR ", $farmSql) . ")
                GROUP BY {$f->columnId('f')}",
                [$queryEnc, $query]
            );

            $names = [
                'id'    => 'ID',
                'name'  => 'Name'
            ];

            while (($farm = $farmStmt->FetchRow())) {
                $farm['status'] = Entity\Farm::getStatusName($farm['status']);
                $farm['added'] = Scalr_Util_DateTime::convertTz($farm['added'], 'M j, Y H:i');

                if (stristr($farm['name'], $query)) {
                    $m = 'name';
                } else {
                    $m = 'id';
                }

                $farms[] = [
                    'entityName' => 'farm',
                    'envId'      => $farm['envId'],
                    'envName'    => $farm['envName'],
                    'matchField' => $names[$m],
                    'matchValue' => $farm[$m],
                    'data'       => $farm
                ];
            }
        }

        $servers = [];

        if (count($serverSql)) {
            $serverStmt = $this->db->Execute("
                SELECT {$s->columnServerId} AS serverId, {$s->columnFarmId} AS farmId, {$s->columnFarmRoleId} AS farmRoleId,
                {$s->columnEnvId} AS envId, {$s->columnPlatform} AS platform, {$s->columnInstanceTypeName} AS instanceTypeName,
                {$s->columnStatus} AS status, {$s->columnCloudLocation} AS cloudLocation, {$s->columnRemoteIp} AS publicIp,
                {$s->columnLocalIp} AS privateIp, {$s->columnAdded} AS added, {$f->columnName('f')} AS farmName,
                {$fr->columnAlias} AS farmRoleName, {$e->columnName} AS `envName`, {$fr->columnRoleId} AS roleId,
                {$sp->columnValue('sp1', 'hostname')}
                FROM {$s->table()}
                LEFT JOIN {$f->table('f')} ON {$f->columnId('f')} = {$s->columnFarmId}
                LEFT JOIN {$fr->table()} ON {$fr->columnId} = {$s->columnFarmRoleId}
                LEFT JOIN {$e->table()} ON {$e->columnId} = {$s->columnEnvId}
                LEFT JOIN {$sp->table('sp1')} ON {$sp->columnServerId('sp1')} = {$s->columnServerId} AND {$sp->columnName('sp1')} = ?
                WHERE ({$s->columnRemoteIp} LIKE ? OR {$s->columnLocalIp} LIKE ? OR {$sp->columnValue('sp1')} LIKE ?) AND (" . join(" OR ", $serverSql) . ")
                GROUP BY {$s->columnServerId}",
                [Scalr_Role_Behavior::SERVER_BASE_HOSTNAME, $queryEnc, $queryEnc, $queryEnc]
            );

            $names = [
                'publicIp'  => 'Public IP',
                'privateIp' => 'Private IP',
                'hostname'  => 'Hostname'
            ];

            while (($server = $serverStmt->FetchRow())) {
                $server['added'] = Scalr_Util_DateTime::convertTz($server['added'], 'M j, Y H:i');
                if (strstr($server['publicIp'], $query)) {
                    $m = 'publicIp';
                } else if (strstr($server['privateIp'], $query)) {
                    $m = 'privateIp';
                } else {
                    $m = 'hostname';
                }

                $servers[] = [
                    'entityName'    => 'server',
                    'envId'         => $server['envId'],
                    'envName'       => $server['envName'],
                    'matchField'    => $names[$m],
                    'matchValue'    => $server[$m],
                    'data'          => $server
                ];
            }
        }

        $this->response->data([
            'data' => array_merge($farms, $servers)
        ]);
    }
}
