<?php

use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Acl\Acl;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Modules\Platforms\Eucalyptus\EucalyptusPlatformModule;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;
use Scalr\Modules\Platforms\Nimbula\NimbulaPlatformModule;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;
use Scalr\Modules\Platforms\Rackspace\RackspacePlatformModule;
use Scalr\Service\CloudStack\CloudStack;

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
            $this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_ENV_CLOUDS)))
            throw new Scalr_Exception_InsufficientPermissions();
    }

    public static function getApiDefinitions()
    {
        return array('xSaveEc2', 'xSaveRackspace', 'xSaveNimbula', 'xSaveCloudstack', 'xSaveOpenstack', 'xSaveEucalyptus');
    }

    private function checkVar($name, $type, $requiredError = '', $group = '', $noFileTrim = false)
    {
        $varName = str_replace('.', '_', ($group != '' ? $name . '.' . $group : $name));
        $errorName = $group != '' ? $name . '.' . $group : $name;

        switch ($type) {
            case 'int':
                if ($this->getParam($varName)) {
                    return intval($this->getParam($varName));
                } else {
                    $value = $this->env->getPlatformConfigValue($name, true, $group);
                    if (!$value && $requiredError)
                        $this->checkVarError[$errorName] = $requiredError;

                    return $value;
                }
                break;

            case 'string':
                if ($this->getParam($varName)) {
                    return $this->getParam($varName);
                } else {
                    $value = $this->env->getPlatformConfigValue($name, true, $group);
                    if ($value == '' && $requiredError)
                        $this->checkVarError[$errorName] = $requiredError;

                    return $value;
                }
                break;

            case 'password':
                if ($this->getParam($varName) && $this->getParam($varName) != '******') {
                    return $this->getParam($varName);
                } else {
                    $value = $this->env->getPlatformConfigValue($name, true, $group);
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
                    $value = $this->env->getPlatformConfigValue($name, true, $group);
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

            $pars[GoogleCEPlatformModule::CLIENT_ID] = trim($this->checkVar(GoogleCEPlatformModule::CLIENT_ID, 'string', "GCE Cient ID required"));
            $pars[GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME] = trim($this->checkVar(GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME, 'string', "GCE email (service account name) required"));
            $pars[GoogleCEPlatformModule::PROJECT_ID] = trim($this->checkVar(GoogleCEPlatformModule::PROJECT_ID, 'password', "GCE Project ID required"));
            $pars[GoogleCEPlatformModule::KEY] = base64_encode($this->checkVar(GoogleCEPlatformModule::KEY, 'file', "GCE Private Key required", null, true));

            if (! count($this->checkVarError)) {
                if (
                    $pars[GoogleCEPlatformModule::CLIENT_ID] != $this->env->getPlatformConfigValue(GoogleCEPlatformModule::CLIENT_ID) or
                    $pars[GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME] != $this->env->getPlatformConfigValue(GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME) or
                    $pars[GoogleCEPlatformModule::PROJECT_ID] != $this->env->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID) or
                    $pars[GoogleCEPlatformModule::KEY] != $this->env->getPlatformConfigValue(GoogleCEPlatformModule::KEY)
                ) {
                    try {
                        $client = new Google_Client();
                        $client->setApplicationName("Scalr GCE");
                        $client->setScopes(array('https://www.googleapis.com/auth/compute'));

                        $key = base64_decode($pars[GoogleCEPlatformModule::KEY]);
                        $client->setAssertionCredentials(new Google_Auth_AssertionCredentials(
                            $pars[GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME],
                            array('https://www.googleapis.com/auth/compute'),
                            $key
                        ));

                        //$client->setUseObjects(true);
                        $client->setClientId($pars[GoogleCEPlatformModule::CLIENT_ID]);

                        $gce = new Google_Service_Compute($client);

                        $gce->zones->listZones($pars[GoogleCEPlatformModule::PROJECT_ID]);
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
                $this->env->setPlatformConfig($pars);

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

        if ($this->getParam('ec2_is_enabled')) {
            $enabled = true;

            $pars[Ec2PlatformModule::ACCOUNT_ID] = $this->checkVar(Ec2PlatformModule::ACCOUNT_ID, 'string', "AWS Account Number required");

            if (! is_numeric($pars[Ec2PlatformModule::ACCOUNT_ID]) || strlen($pars[Ec2PlatformModule::ACCOUNT_ID]) != 12)
                //$err[Ec2PlatformModule::ACCOUNT_ID] = _("AWS numeric account ID required (See <a href='/faq.html'>FAQ</a> for info on where to get it).");
                $this->checkVarError[Ec2PlatformModule::ACCOUNT_ID] = _("AWS Account Number should be numeric");
            else
                $pars[Ec2PlatformModule::ACCOUNT_ID] = preg_replace("/[^0-9]+/", "", $pars[Ec2PlatformModule::ACCOUNT_ID]);

            $pars[Ec2PlatformModule::ACCESS_KEY] = $this->checkVar(Ec2PlatformModule::ACCESS_KEY, 'string', "AWS Access Key required");
            $pars[Ec2PlatformModule::SECRET_KEY] = $this->checkVar(Ec2PlatformModule::SECRET_KEY, 'password', "AWS Access Key required");
            $pars[Ec2PlatformModule::PRIVATE_KEY] = trim($this->checkVar(Ec2PlatformModule::PRIVATE_KEY, 'file', "AWS x.509 Private Key required"));
            $pars[Ec2PlatformModule::CERTIFICATE] = trim($this->checkVar(Ec2PlatformModule::CERTIFICATE, 'file', "AWS x.509 Certificate required"));

            // user can mull certificate and private key, check it
            if (strpos($pars[Ec2PlatformModule::PRIVATE_KEY], 'BEGIN CERTIFICATE') !== FALSE &&
                strpos($pars[Ec2PlatformModule::CERTIFICATE], 'BEGIN PRIVATE KEY') !== FALSE) {
                // swap it
                $key = $pars[Ec2PlatformModule::PRIVATE_KEY];
                $pars[Ec2PlatformModule::PRIVATE_KEY] = $pars[Ec2PlatformModule::CERTIFICATE];
                $pars[Ec2PlatformModule::CERTIFICATE] = $key;
            }

            if (!count($this->checkVarError)) {
                if (
                    $pars[Ec2PlatformModule::ACCOUNT_ID] != $this->env->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID) or
                    $pars[Ec2PlatformModule::ACCESS_KEY] != $this->env->getPlatformConfigValue(Ec2PlatformModule::ACCESS_KEY) or
                    $pars[Ec2PlatformModule::SECRET_KEY] != $this->env->getPlatformConfigValue(Ec2PlatformModule::SECRET_KEY) or
                    $pars[Ec2PlatformModule::PRIVATE_KEY] != $this->env->getPlatformConfigValue(Ec2PlatformModule::PRIVATE_KEY) or
                    $pars[Ec2PlatformModule::CERTIFICATE] != $this->env->getPlatformConfigValue(Ec2PlatformModule::CERTIFICATE)
                ) {
                    try {
                        $aws = $this->env->aws(
                            \Scalr\Service\Aws::REGION_US_EAST_1,
                            $pars[Ec2PlatformModule::ACCESS_KEY],
                            $pars[Ec2PlatformModule::SECRET_KEY],
                            $pars[Ec2PlatformModule::CERTIFICATE],
                            $pars[Ec2PlatformModule::PRIVATE_KEY]
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

            if ($enabled)
                $this->env->setPlatformConfig($pars);

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

    public function xSaveRackspaceAction()
    {
        $pars = array();
        $enabled = false;
        $locations = array('rs-ORD1', 'rs-LONx');

        foreach ($locations as $location) {
            if ($this->getParam("rackspace_is_enabled_{$location}")) {
                $enabled = true;

                $pars[$location][RackspacePlatformModule::USERNAME] = $this->checkVar(RackspacePlatformModule::USERNAME, 'string', "Username required", $location);
                $pars[$location][RackspacePlatformModule::API_KEY] = $this->checkVar(RackspacePlatformModule::API_KEY, 'string', "API Key required", $location);
                $pars[$location][RackspacePlatformModule::IS_MANAGED] = $this->checkVar(RackspacePlatformModule::IS_MANAGED, 'bool', "", $location);
            }
            else {
                $pars[$location][RackspacePlatformModule::USERNAME] = false;
                $pars[$location][RackspacePlatformModule::API_KEY] = false;
                $pars[$location][RackspacePlatformModule::IS_MANAGED] = false;
            }
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {
            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform(SERVER_PLATFORMS::RACKSPACE, $enabled);

                foreach ($pars as $cloud => $prs)
                    $this->env->setPlatformConfig($prs, true, $cloud);

                if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                    $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

                $this->response->success('Environment saved');
                $this->response->data(array('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(_('Failed to save Rackspace settings'));
            }
            $this->db->CommitTrans();
        }
    }

    public function xSaveNimbulaAction()
    {
        $pars = array();
        $enabled = false;

        if ($this->getParam('nimbula_is_enabled')) {
            $enabled = true;

            $pars[NimbulaPlatformModule::API_URL] = $this->checkVar(NimbulaPlatformModule::API_URL, 'string', 'API URL required');
            $pars[NimbulaPlatformModule::USERNAME] = $this->checkVar(NimbulaPlatformModule::USERNAME, 'string', 'Username required');
            $pars[NimbulaPlatformModule::PASSWORD] = $this->checkVar(NimbulaPlatformModule::PASSWORD, 'string', 'Password required');
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {
            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform(SERVER_PLATFORMS::NIMBULA, $enabled);

                if ($enabled)
                    $this->env->setPlatformConfig($pars);

                if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                    $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

                $this->response->success('Environment saved');
                $this->response->data(arraY('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(_('Failed to save Nimbula settings'));
            }
            $this->db->CommitTrans();
        }
    }

    private function getOpenStackDetails($platform)
    {
        $params["{$platform}.is_enabled"] = true;
        $params[OpenstackPlatformModule::KEYSTONE_URL] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::KEYSTONE_URL);
        $params[OpenstackPlatformModule::USERNAME] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::USERNAME);
        $params[OpenstackPlatformModule::PASSWORD] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::PASSWORD);
        $params[OpenstackPlatformModule::API_KEY] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::API_KEY);
        $params[OpenstackPlatformModule::TENANT_NAME] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::TENANT_NAME);
        $params[OpenstackPlatformModule::SSL_VERIFYPEER] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::SSL_VERIFYPEER);

        return $params;
    }

    /**
     * Gets unified platform variable name
     *
     * @param   string  $name  mnemonic name
     * @return  string  Returns full platform variable name
     */
    private function getOpenStackOption($name)
    {
        return $this->getParam('platform') . "." . constant("Scalr\\Modules\\Platforms\\Openstack\\OpenstackPlatformModule::" . $name);
    }

    public function xSaveOpenstackAction()
    {
        $pars = array();
        $enabled = false;
        $platform = $this->getParam('platform');

        if ($this->getParam("{$platform}_is_enabled")) {
            $enabled = true;

            $pars[$this->getOpenStackOption('KEYSTONE_URL')] = $this->checkVar(OpenstackPlatformModule::KEYSTONE_URL, 'string', 'KeyStone URL required');
            $pars[$this->getOpenStackOption('USERNAME')] = $this->checkVar(OpenstackPlatformModule::USERNAME, 'string', 'Username required');
            $pars[$this->getOpenStackOption('PASSWORD')] = $this->checkVar(OpenstackPlatformModule::PASSWORD, 'string');
            $pars[$this->getOpenStackOption('API_KEY')] = $this->checkVar(OpenstackPlatformModule::API_KEY, 'string');
            $pars[$this->getOpenStackOption('TENANT_NAME')] = $this->checkVar(OpenstackPlatformModule::TENANT_NAME, 'string');
            $pars[$this->getOpenStackOption('SSL_VERIFYPEER')] = $this->checkVar(OpenstackPlatformModule::SSL_VERIFYPEER, 'string');
            if (empty($this->checkVarError) &&
                empty($pars[$this->getOpenStackOption('PASSWORD')]) &&
                empty($pars[$this->getOpenStackOption('API_KEY')])) {
                $this->checkVarError['API_KEY'] = 'Either API Key or password must be provided.';
            }
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {

            $this->env->setPlatformConfig(array(
                "{$platform}." . OpenstackPlatformModule::AUTH_TOKEN => false
            ));

            if ($this->getParam($platform . "_is_enabled")) {
                $os = new OpenStack(new OpenStackConfig(
                    $pars[$this->getOpenStackOption('USERNAME')],
                    $pars[$this->getOpenStackOption('KEYSTONE_URL')],
                    'fake-region',
                    $pars[$this->getOpenStackOption('API_KEY')],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $pars[$this->getOpenStackOption('PASSWORD')],
                    $pars[$this->getOpenStackOption('TENANT_NAME')]
                ));
                //It throws an exception on failure
                $os->listZones();
            }


            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform($platform, $enabled);

                if ($enabled) {
                    $this->env->setPlatformConfig($pars);
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
        $params["{$platform}.is_enabled"] = true;
        $params[CloudstackPlatformModule::API_URL] = $this->env->getPlatformConfigValue("{$platform}." . CloudstackPlatformModule::API_URL);
        $params[CloudstackPlatformModule::API_KEY] = $this->env->getPlatformConfigValue("{$platform}." . CloudstackPlatformModule::API_KEY);
        $params[CloudstackPlatformModule::SECRET_KEY] = $this->env->getPlatformConfigValue("{$platform}." . CloudstackPlatformModule::SECRET_KEY);

        return $params;
    }

    public function xSaveCloudstackAction()
    {
        $pars = array();
        $enabled = false;
        $platform = $this->getParam('platform');

        if ($this->getParam("{$platform}_is_enabled")) {
            $enabled = true;

            $pars["{$platform}." . CloudstackPlatformModule::API_URL] = $this->checkVar(CloudstackPlatformModule::API_URL, 'string', 'API URL required');
            $pars["{$platform}." . CloudstackPlatformModule::API_KEY] = $this->checkVar(CloudstackPlatformModule::API_KEY, 'string', 'API key required');
            $pars["{$platform}." . CloudstackPlatformModule::SECRET_KEY] = $this->checkVar(CloudstackPlatformModule::SECRET_KEY, 'string', 'Secret key required');
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {

            if ($this->getParam("{$platform}_is_enabled")) {
                $cs = new CloudStack(
                        $pars["{$platform}." . CloudstackPlatformModule::API_URL],
                        $pars["{$platform}." . CloudstackPlatformModule::API_KEY],
                        $pars["{$platform}." . CloudstackPlatformModule::SECRET_KEY],
                        $platform
                    );

                $accounts = $cs->listAccounts();
                foreach ($accounts as $account) {
                    foreach ($account->user as $user) {
                        if ($user->apikey == $pars["{$platform}." . CloudstackPlatformModule::API_KEY]) {
                            $dPars["{$platform}." . CloudstackPlatformModule::ACCOUNT_NAME] = $user->account;
                            $dPars["{$platform}." . CloudstackPlatformModule::DOMAIN_NAME] = $user->domain;
                            $dPars["{$platform}." . CloudstackPlatformModule::DOMAIN_ID] = $user->domainid;
                        }
                    }
                }

                if (!$dPars["{$platform}." . CloudstackPlatformModule::ACCOUNT_NAME]) {
                    throw new Exception("Cannot determine account name for provided keys");
                }
            }

            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform($platform, $enabled);

                if ($enabled) {
                    $this->env->setPlatformConfig($pars);
                    $this->env->setPlatformConfig($dPars, false);
                } else {
                    $this->env->setPlatformConfig(array(
                        "{$platform}." . CloudstackPlatformModule::ACCOUNT_NAME => false,
                        "{$platform}." . CloudstackPlatformModule::API_KEY => false,
                        "{$platform}." . CloudstackPlatformModule::API_URL => false,
                        "{$platform}." . CloudstackPlatformModule::DOMAIN_ID => false,
                        "{$platform}." . CloudstackPlatformModule::DOMAIN_NAME => false,
                        "{$platform}." . CloudstackPlatformModule::SECRET_KEY => false,
                        "{$platform}." . CloudstackPlatformModule::SHARED_IP => false,
                        "{$platform}." . CloudstackPlatformModule::SHARED_IP_ID => false,
                        "{$platform}." . CloudstackPlatformModule::SHARED_IP_INFO => false,
                        "{$platform}." . CloudstackPlatformModule::SZR_PORT_COUNTER => false
                    ));
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

    public function xSaveEucalyptusAction()
    {
        $this->request->defineParams(array(
            'clouds' => array('type' => 'json')
        ));

        $pars = array();
        $enabled = false;

        $clouds = $this->getParam('clouds');
        $cloudsDeleted = array();
        if (count($clouds)) {
            $enabled = true;

            foreach ($clouds as $cloud) {
                $pars[$cloud][EucalyptusPlatformModule::ACCOUNT_ID] = $this->checkVar(EucalyptusPlatformModule::ACCOUNT_ID, 'string', "Account ID required", $cloud);
                $pars[$cloud][EucalyptusPlatformModule::ACCESS_KEY] = $this->checkVar(EucalyptusPlatformModule::ACCESS_KEY, 'string', "Access Key required", $cloud);
                $pars[$cloud][EucalyptusPlatformModule::EC2_URL] = $this->checkVar(EucalyptusPlatformModule::EC2_URL, 'string', "EC2 URL required", $cloud);
                $pars[$cloud][EucalyptusPlatformModule::S3_URL] = $this->checkVar(EucalyptusPlatformModule::S3_URL, 'string', "S3 URL required", $cloud);
                $pars[$cloud][EucalyptusPlatformModule::SECRET_KEY] = $this->checkVar(EucalyptusPlatformModule::SECRET_KEY, 'password', "Secret Key required", $cloud);
                $pars[$cloud][EucalyptusPlatformModule::PRIVATE_KEY] = $this->checkVar(EucalyptusPlatformModule::PRIVATE_KEY, 'file', "x.509 Private Key required", $cloud);
                $pars[$cloud][EucalyptusPlatformModule::CERTIFICATE] = $this->checkVar(EucalyptusPlatformModule::CERTIFICATE, 'file', "x.509 Certificate required", $cloud);
                $pars[$cloud][EucalyptusPlatformModule::CLOUD_CERTIFICATE] = $this->checkVar(EucalyptusPlatformModule::CLOUD_CERTIFICATE, 'file', "x.509 Cloud Certificate required", $cloud);
            }
        }

        // clear old cloud locations
        foreach ($this->db->GetAll('SELECT * FROM client_environment_properties WHERE env_id = ? AND name LIKE "eucalyptus.%" AND `group` != "" GROUP BY `group', $this->env->id) as $key => $value) {
            if (! in_array($value['group'], $clouds))
                $cloudsDeleted[] = $value['group'];
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {
            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform(SERVER_PLATFORMS::EUCALYPTUS, $enabled);

                foreach ($cloudsDeleted as $key => $cloud)
                    $this->db->Execute('DELETE FROM client_environment_properties WHERE env_id = ? AND `group` = ? AND name LIKE "eucalyptus.%"', array($this->env->id, $cloud));

                foreach ($pars as $cloud => $prs)
                    $this->env->setPlatformConfig($prs, true, $cloud);

                if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                    $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

                $this->response->success(_('Environment saved'));
                $this->response->data(array('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(_('Failed to save Eucalyptus settings'));
            }
            $this->db->CommitTrans();
        }
    }
}
