<?php

use Scalr\Service\Azure;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Modules\PlatformFactory;
use Scalr\Acl\Acl;
use Scalr\Service\CloudStack\CloudStack;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Environments_Platform extends Scalr_UI_Controller
{
    /**
     *
     * @var Scalr_Environment
     */
    private $env;
    private $checkVarError;

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::init()
     */
    public function init()
    {
        $this->env = Scalr_Environment::init()->loadById($this->getParam(Scalr_UI_Controller_Environments::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($this->env);

        if (!($this->user->isAccountOwner() || $this->user->isTeamOwnerInEnvironment($this->env->id) ||
            $this->request->isAllowed(Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT)))
            throw new Scalr_Exception_InsufficientPermissions();
    }

    public static function getApiDefinitions()
    {
        return array('xSaveEc2', 'xSaveCloudstack', 'xSaveOpenstack');
    }

    private function checkVar($name, $type, $requiredError = '', $cloud = '', $noFileTrim = false)
    {
        $varName = str_replace('.', '_', "{$cloud}.{$name}");
        $errorName = $cloud != '' ? $name . '.' . $cloud : $name;

        switch ($type) {
            case 'int':
                if ($this->getParam($varName)) {
                    return intval($this->getParam($varName));
                } else {
                    $value = $this->env->keychain($cloud)->properties[$name];
                    if (!$value && $requiredError)
                        $this->checkVarError[$errorName] = $requiredError;

                    return $value;
                }
                break;

            case 'string':
                if ($this->getParam($varName)) {
                    return $this->getParam($varName);
                } else {
                    $value = $this->env->keychain($cloud)->properties[$name];
                    if ($value == '' && $requiredError)
                        $this->checkVarError[$errorName] = $requiredError;

                    return $value;
                }
                break;

            case 'password':
                if ($this->getParam($varName) && $this->getParam($varName) != '******') {
                    return $this->getParam($varName);
                } else {
                    $value = $this->env->keychain($cloud)->properties[$name];
                    if ($value == '' && $requiredError)
                        $this->checkVarError[$errorName] = $requiredError;

                    return $value;
                }
                break;

            case 'bool':
                return $this->getParam($varName) ? 1 : 0;

            case 'file':
                if (!empty($_FILES[$varName]['tmp_name']) && ($value = @file_get_contents($_FILES[$varName]['tmp_name'])) != '') {
                    return ($noFileTrim) ? $value : trim($value);
                } else {
                    $value = $this->env->keychain($cloud)->properties[$name];
                    if ($value == '' && $requiredError)
                        $this->checkVarError[$errorName] = $requiredError;

                    return $value;
                }
                break;
        }
    }

    public function xSaveGceAction()
    {
        $pars = array();
        $enabled = false;

        if ($this->getParam('gce_is_enabled')) {
            $enabled = true;

            $pars[Entity\CloudCredentialsProperty::GCE_CLIENT_ID] = trim($this->checkVar(Entity\CloudCredentialsProperty::GCE_CLIENT_ID, 'string', "GCE Cient ID required", SERVER_PLATFORMS::GCE));
            $pars[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME] = trim($this->checkVar(Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME, 'string', "GCE email (service account name) required", SERVER_PLATFORMS::GCE));
            $pars[Entity\CloudCredentialsProperty::GCE_PROJECT_ID] = trim($this->checkVar(Entity\CloudCredentialsProperty::GCE_PROJECT_ID, 'password', "GCE Project ID required", SERVER_PLATFORMS::GCE));
            $pars[Entity\CloudCredentialsProperty::GCE_KEY] = base64_encode($this->checkVar(Entity\CloudCredentialsProperty::GCE_KEY, 'file', "GCE Private Key required", SERVER_PLATFORMS::GCE, true));

            if (! count($this->checkVarError)) {
                $ccProps = $this->env->keychain(SERVER_PLATFORMS::GCE)->properties;
                if (
                    $pars[Entity\CloudCredentialsProperty::GCE_CLIENT_ID] != $ccProps[Entity\CloudCredentialsProperty::GCE_CLIENT_ID] or
                    $pars[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME] != $ccProps[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME] or
                    $pars[Entity\CloudCredentialsProperty::GCE_PROJECT_ID] != $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID] or
                    $pars[Entity\CloudCredentialsProperty::GCE_KEY] != $ccProps[Entity\CloudCredentialsProperty::GCE_KEY]
                ) {
                    try {
                        $googlePlatform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
                        $gce = $googlePlatform->getClient(null, $pars);
                        
                        $gce->zones->listZones($pars[Entity\CloudCredentialsProperty::GCE_PROJECT_ID]);
                    } catch (Exception $e) {
                        throw new Exception(_("Provided GCE credentials are incorrect: ({$e->getMessage()})"));
                    }
                }
            } else {
                $this->response->failure();
                $this->response->data(array('errors' => $this->checkVarError));
                return;
            }
        }

        $this->db->BeginTrans();
        try {
            $this->env->enablePlatform(SERVER_PLATFORMS::GCE, $enabled);

            if ($enabled)
                $this->makeCloudCredentials(SERVER_PLATFORMS::GCE, $pars);

            if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

            $this->response->success('Environment saved');
            $this->response->data(array('enabled' => $enabled));
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw new Exception(_("Failed to save GCE settings: {$e->getMessage()}"));
        }
        $this->db->CommitTrans();
    }

    public function xSaveEc2Action()
    {
        $pars = array();
        $enabled = false;

        $ccProps = $this->env->keychain(SERVER_PLATFORMS::EC2)->properties;

        if ($this->getParam('ec2_is_enabled')) {
            $enabled = true;

            $pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] = $this->checkVar(Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID, 'string', "AWS Account Number required", SERVER_PLATFORMS::EC2);

            if (! is_numeric($pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID]) || strlen($pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID]) != 12)
                //$err[Ec2PlatformModule::ACCOUNT_ID] = _("AWS numeric account ID required (See <a href='/faq.html'>FAQ</a> for info on where to get it).");
                $this->checkVarError[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] = _("AWS Account Number should be numeric");
            else
                $pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] = preg_replace("/[^0-9]+/", "", $pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID]);

            $pars[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY] = $this->checkVar(Entity\CloudCredentialsProperty::AWS_ACCESS_KEY, 'string', "AWS Access Key required", SERVER_PLATFORMS::EC2);
            $pars[Entity\CloudCredentialsProperty::AWS_SECRET_KEY] = $this->checkVar(Entity\CloudCredentialsProperty::AWS_SECRET_KEY, 'password', "AWS Access Key required", SERVER_PLATFORMS::EC2);
            $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] = trim($this->checkVar(Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY, 'file', "AWS x.509 Private Key required", SERVER_PLATFORMS::EC2));
            $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] = trim($this->checkVar(Entity\CloudCredentialsProperty::AWS_CERTIFICATE, 'file', "AWS x.509 Certificate required", SERVER_PLATFORMS::EC2));

            // user can mull certificate and private key, check it
            if (strpos($pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY], 'BEGIN CERTIFICATE') !== FALSE &&
                strpos($pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE], 'BEGIN PRIVATE KEY') !== FALSE) {
                // swap it
                $key = $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY];
                $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] = $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE];
                $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] = $key;
            }

            if (!count($this->checkVarError)) {
                if (
                    $pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] != $ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] or
                    $pars[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY] != $ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY] or
                    $pars[Entity\CloudCredentialsProperty::AWS_SECRET_KEY] != $ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY] or
                    $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] != $ccProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] or
                    $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] != $ccProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]
                ) {
                    try {
                        $aws = $this->env->aws(
                            \Scalr\Service\Aws::REGION_US_EAST_1,
                            $pars[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY],
                            $pars[Entity\CloudCredentialsProperty::AWS_SECRET_KEY],
                            $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE],
                            $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY]
                        );
                        $aws->validateCertificateAndPrivateKey();
                    } catch (Exception $e) {
                        throw new Exception(_("Incorrect format of X.509 certificate or private key. Make sure that you are using files downloaded from AWS profile. ({$e->getMessage()})"));
                    }

                    try {
                        $buckets = $aws->s3->bucket->getList();
                    } catch (Exception $e) {
                        throw new Exception(sprintf(_("Failed to verify your EC2 access key and secret key: %s"), $e->getMessage()));
                    }
                }
            } else {
                $this->response->failure();
                $this->response->data(array('errors' => $this->checkVarError));
                return;
            }
        }

        $this->db->BeginTrans();
        try {
            $this->env->enablePlatform(SERVER_PLATFORMS::EC2, $enabled);

            if ($enabled) {
                $this->makeCloudCredentials(SERVER_PLATFORMS::EC2, $pars);
            }

            if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

            if ($this->env->status == Scalr_Environment::STATUS_INACTIVE) {
                $this->env->status = Scalr_Environment::STATUS_ACTIVE;
                $this->env->save();
            }

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw new Exception(_("Failed to save AWS settings: {$e->getMessage()}"));
        }

        $demoFarm = false;

        

        $this->response->success('Environment saved');
        $this->response->data(array('enabled' => $enabled, 'demoFarm' => $demoFarm));
    }

    private function getOpenStackDetails($platform)
    {
        $ccProps = $this->env->keychain($platform)->properties;

        $params["{$platform}.is_enabled"] = true;
        $params["{$platform}." . Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL];
        $params["{$platform}." . Entity\CloudCredentialsProperty::OPENSTACK_USERNAME] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME];
        $params["{$platform}." . Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD];
        $params["{$platform}." . Entity\CloudCredentialsProperty::OPENSTACK_API_KEY] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY];
        $params["{$platform}." . Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME];
        $params["{$platform}." . Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME];
        $params["{$platform}." . Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER];

        return $params;
    }

    public function xSaveOpenstackAction()
    {
        $pars = array();
        $enabled = false;
        $platform = $this->getParam('platform');

        $currentCloudCredentials = $this->env->keychain($platform);
        $ccProps = $currentCloudCredentials->properties;

        if ($this->getParam("{$platform}_is_enabled")) {
            $enabled = true;

            $pars[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL, 'string', 'KeyStone URL required', $platform);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_USERNAME, 'string', 'Username required', $platform);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD, 'string', '', $platform);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_API_KEY, 'string', '', $platform);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME, 'string', '', $platform);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME, 'string', '', $platform);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER, 'string', '', $platform);
            if (empty($this->checkVarError) &&
                empty($pars[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD]) &&
                empty($pars[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY])) {
                $this->checkVarError['API_KEY'] = 'Either API Key or password must be provided.';
            }
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {
            $ccProps->saveSettings([Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN => false]);

            if ($this->getParam("{$platform}_is_enabled")) {
                $os = new OpenStack(new OpenStackConfig(
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL],
                    'fake-region',
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME]
                ));
                //It throws an exception on failure
                $os->listZones();
            }


            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform($platform, $enabled);

                if ($enabled) {
                    $this->makeCloudCredentials($platform, $pars);
                }

                if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                    $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

                $this->response->success('Environment saved');
                $this->response->data(array('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(_('Failed to save '.ucfirst($platform).' settings'));
            }
            $this->db->CommitTrans();
        }
    }

    private function getCloudStackDetails($platform)
    {
        $ccProps = $this->env->keychain($platform)->properties;

        $params["{$platform}.is_enabled"] = true;
        $params["{$platform}." . Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL];
        $params["{$platform}." . Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY];
        $params["{$platform}." . Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY];

        return $params;
    }

    public function xSaveCloudstackAction()
    {
        $pars = array();
        $enabled = false;
        $platform = $this->getParam('platform');

        if ($this->getParam("{$platform}_is_enabled")) {
            $enabled = true;

            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL] = $this->checkVar(Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL, 'string', 'API URL required', $platform);
            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY] = $this->checkVar(Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY, 'string', 'API key required', $platform);
            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY] = $this->checkVar(Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY, 'string', 'Secret key required', $platform);
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {

            if ($this->getParam("{$platform}_is_enabled")) {
                $cs = new CloudStack(
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL],
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY],
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY],
                        $platform
                    );

                $accounts = $cs->listAccounts();
                foreach ($accounts as $account) {
                    foreach ($account->user as $user) {
                        if ($user->apikey == $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY]) {
                            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_ACCOUNT_NAME] = $user->account;
                            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_NAME] = $user->domain;
                            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_ID] = $user->domainid;
                        }
                    }
                }

                if (empty($pars[Entity\CloudCredentialsProperty::CLOUDSTACK_ACCOUNT_NAME])) {
                    throw new Exception("Cannot determine account name for provided keys");
                }
            }

            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform($platform, $enabled);

                if ($enabled) {
                    $this->makeCloudCredentials($platform, $pars);
                } else {
                    $this->env->keychain($platform)->properties->saveSettings([
                        Entity\CloudCredentialsProperty::CLOUDSTACK_ACCOUNT_NAME => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_ID => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_NAME => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_ID => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_INFO => false,
                        Entity\CloudCredentialsProperty::CLOUDSTACK_SZR_PORT_COUNTER => false
                    ]);
                }

                if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                    $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

                $this->response->success('Environment saved');
                $this->response->data(array('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(_('Failed to save '.ucfirst($platform).' settings'));
            }
            $this->db->CommitTrans();
        }
    }

    /**
     * Makes clod credentials entity for specified platform
     *
     * @param   string $platform             Cloud credentials platform
     * @param   array  $parameters           Array of cloud credentials parameters
     * @param   int    $status      optional Cloud credentials status
     *
     * @return Entity\CloudCredentials Returns new cloud credentials entity
     *
     * @throws Exception
     */
    public function makeCloudCredentials($platform, $parameters, $status = Entity\CloudCredentials::STATUS_ENABLED)
    {
        $cloudCredentials = new Entity\CloudCredentials();
        $cloudCredentials->envId = $this->env->id;
        $cloudCredentials->accountId = $this->env->getAccountId();
        $cloudCredentials->cloud = $platform;
        $cloudCredentials->name = "{$this->env->id}-{$this->env->getAccountId()}-{$platform}-" . \Scalr::GenerateUID(true);
        $cloudCredentials->status = $status;

        try {
            $this->db->BeginTrans();

            $cloudCredentials->save();
            $cloudCredentials->properties->saveSettings($parameters);
            $cloudCredentials->bindToEnvironment($this->env);

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();

            throw $e;
        }

        $cloudCredentials->cache();

        return $cloudCredentials;
    }
}
