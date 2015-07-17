<?php

use Scalr\Acl\Acl;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Modules\Platforms\Eucalyptus\EucalyptusPlatformModule;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;
use Scalr\Modules\Platforms\Rackspace\RackspacePlatformModule;
use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\ListAccountsData;
use Scalr\System\Config\Yaml;

class Scalr_UI_Controller_Account2_Environments_Clouds extends Scalr_UI_Controller
{
    /**
     *
     * @var Scalr_Environment
     */
    private $env;
    private $checkVarError;

    public function init()
    {
        $this->env = Scalr_Environment::init()->loadById($this->getParam(Scalr_UI_Controller_Environments::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($this->env);
    }

    public function hasAccess()
    {
        return parent::hasAccess() && ($this->user->isAccountSuperAdmin() || $this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_ENV_CLOUDS));
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/account2/environments/clouds.js', array(
            'env' => array(
                'id'   => $this->env->id,
                'name' => $this->env->name
            ),
            'enabledPlatforms' => $this->env->getEnabledPlatforms()
        ), array(
            'ui/account2/environments/clouds/ec2.js',
            'ui/account2/environments/clouds/gce.js',
            'ui/account2/environments/clouds/cloudstack.js',
            'ui/account2/environments/clouds/openstack.js',
            'ui/account2/environments/clouds/rackspace.js',
            'ui/account2/environments/clouds/eucalyptus.js',
        ));
    }

    private function checkVar($name, $type, $requiredError = '', $group = '', $noFileTrim = false, $namePrefix = '', $base64encode = false)
    {
        $varName = str_replace('.', '_', ($group != '' ? $name . '.' . $group : $name));
        $errorName = $group != '' ? $name . '.' . $group : $name;
        $name = (!empty($namePrefix) ? $namePrefix . '.' : '') . $name;

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
                    $value = ($noFileTrim) ? $value : trim($value);
                    return $base64encode ? base64_encode($value) : $value;
                } else {
                    $value = $this->env->getPlatformConfigValue($name, true, $group);
                    if ($value == '' && $requiredError)
                        $this->checkVarError[$errorName] = $requiredError;

                    return $value;
                }
                break;
        }
    }

    private function checkVar2($name, $type, $requiredError = '', $trim = true, $base64encode = false, $fetchIfEmpty = false)
    {
        $varName = str_replace('.', '_', $name);

        switch ($type) {
            case 'string':
                $value = $this->getParam($varName);
                $value = $trim ? trim($value) : $value;
                if (empty($value) && $fetchIfEmpty) {
                    $value = $this->env->getPlatformConfigValue($name, true);
                }
                if (empty($value) && $requiredError) {
                    $this->checkVarError[$name] = $requiredError;
                }
                return $value;
            case 'password':
                $value = $this->getParam($varName);
                if ($value === '******' || empty($value) && $fetchIfEmpty) {
                    $value = $this->env->getPlatformConfigValue($name, true);
                }
                if (empty($value) && $requiredError) {
                    $this->checkVarError[$name] = $requiredError;
                }
                return $value;
            case 'bool':
                return $this->getParam($varName) ? 1 : 0;

            case 'file':
                if (!empty($_FILES[$varName]) && !empty($_FILES[$varName]['tmp_name']) && ($value = @file_get_contents($_FILES[$varName]['tmp_name'])) != '') {
                    $value = $trim ? trim($value) : $value;
                }

                if (!empty($value)) {
                    $value = $base64encode ? base64_encode($value) : $value;
                } else if ($fetchIfEmpty){
                    $value = $this->env->getPlatformConfigValue($name, true);
                }
                if (empty($value) && $requiredError) {
                    $this->checkVarError[$name] = $requiredError;
                }
                return $value;
        }
    }

    public function xGetCloudParamsAction()
    {
        $this->response->data(array(
            'params' => $this->getCloudParams($this->getParam('platform'))
        ));
    }

    private function getCloudParams($platform)
    {
        $params = array();
        if (in_array($platform, $this->env->getEnabledPlatforms())) {
            switch ($platform) {
                case SERVER_PLATFORMS::EC2:
                    $params[SERVER_PLATFORMS::EC2 . '.is_enabled'] = true;
                    $params[Ec2PlatformModule::ACCOUNT_ID] = $this->env->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID);
                    $params[Ec2PlatformModule::ACCOUNT_TYPE] = $this->env->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_TYPE);
                    $params[Ec2PlatformModule::ACCESS_KEY] = $this->env->getPlatformConfigValue(Ec2PlatformModule::ACCESS_KEY);
                    $params[Ec2PlatformModule::SECRET_KEY] = $this->env->getPlatformConfigValue(Ec2PlatformModule::SECRET_KEY) != '' ? '******' : '';
                    $params[Ec2PlatformModule::PRIVATE_KEY] = $this->env->getPlatformConfigValue(Ec2PlatformModule::PRIVATE_KEY) != '' ? 'Uploaded' : '';
                    $params[Ec2PlatformModule::CERTIFICATE] = $this->env->getPlatformConfigValue(Ec2PlatformModule::CERTIFICATE) != '' ? 'Uploaded' : '';
                    $params[Ec2PlatformModule::DETAILED_BILLING_BUCKET] = $this->env->getPlatformConfigValue(Ec2PlatformModule::DETAILED_BILLING_BUCKET);
                    $params[Ec2PlatformModule::DETAILED_BILLING_ENABLED] = $this->env->getPlatformConfigValue(Ec2PlatformModule::DETAILED_BILLING_ENABLED);

                    try {
                        if ($params[Ec2PlatformModule::ACCOUNT_TYPE] == Ec2PlatformModule::ACCOUNT_TYPE_CN_CLOUD)
                            $params['arn'] = $this->env->aws('cn-north-1')->getUserArn();
                        elseif ($params[Ec2PlatformModule::ACCOUNT_TYPE] == Ec2PlatformModule::ACCOUNT_TYPE_GOV_CLOUD)
                            $params['arn'] = $this->env->aws('us-gov-west-1')->getUserArn();
                        else
                            $params['arn'] = $this->env->aws('us-east-1')->getUserArn();
                        //$params['username'] = $this->env->aws('us-east-1')->getUsername();
                    } catch (Exception $e) {}

                    break;
                case SERVER_PLATFORMS::GCE:
                    $params[SERVER_PLATFORMS::GCE . '.is_enabled'] = true;
                    $params[GoogleCEPlatformModule::PROJECT_ID] = $this->env->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID);
                    $jsonKey = $this->env->getPlatformConfigValue(GoogleCEPlatformModule::JSON_KEY);
                    if (!empty($jsonKey)) {
                        $params[GoogleCEPlatformModule::JSON_KEY] = 'Uploaded';
                    } else {
                        $params[GoogleCEPlatformModule::CLIENT_ID] = $this->env->getPlatformConfigValue(GoogleCEPlatformModule::CLIENT_ID);
                        $params[GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME] = $this->env->getPlatformConfigValue(GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME);
                        $params[GoogleCEPlatformModule::KEY] = $this->env->getPlatformConfigValue(GoogleCEPlatformModule::KEY) != '' ? 'Uploaded' : '';
                    }
                    break;
                case SERVER_PLATFORMS::CLOUDSTACK:
                case SERVER_PLATFORMS::IDCF:
                    $params = $this->getCloudStackDetails($platform);
                    break;
                case SERVER_PLATFORMS::OPENSTACK:
                case SERVER_PLATFORMS::RACKSPACENG_UK:
                case SERVER_PLATFORMS::RACKSPACENG_US:
                case SERVER_PLATFORMS::ECS:
                case SERVER_PLATFORMS::OCS:
                case SERVER_PLATFORMS::NEBULA:
                case SERVER_PLATFORMS::MIRANTIS:
                case SERVER_PLATFORMS::VIO:
                case SERVER_PLATFORMS::VERIZON:
                case SERVER_PLATFORMS::CISCO:
                case SERVER_PLATFORMS::HPCLOUD:
                    $params = $this->getOpenStackDetails($platform);
                    break;
                case SERVER_PLATFORMS::RACKSPACE:
                    $rows = $this->db->GetAll('SELECT * FROM client_environment_properties WHERE env_id = ? AND name LIKE "rackspace.%" AND `group` != "" GROUP BY `group`', $this->env->id);
                    $params[SERVER_PLATFORMS::RACKSPACE . '.is_enabled'] = true;
                    foreach ($rows as $value) {
                        $cloud = $value['group'];
                        $params[$cloud] = array(
                            RackspacePlatformModule::USERNAME => $this->env->getPlatformConfigValue(RackspacePlatformModule::USERNAME, true, $cloud),
                            RackspacePlatformModule::API_KEY => $this->env->getPlatformConfigValue(RackspacePlatformModule::API_KEY, true, $cloud),
                            RackspacePlatformModule::IS_MANAGED => $this->env->getPlatformConfigValue(RackspacePlatformModule::IS_MANAGED, true, $cloud),
                        );
                    }
                    break;
                case SERVER_PLATFORMS::EUCALYPTUS:
                    $rows = $this->db->GetAll('SELECT * FROM client_environment_properties WHERE env_id = ? AND name LIKE "eucalyptus.%" AND `group` != "" GROUP BY `group`', $this->env->id);
                    $params[SERVER_PLATFORMS::EUCALYPTUS . '.is_enabled'] = true;
                    $params['locations'] = array();
                    foreach ($rows as $value) {
                        $cloud = $value['group'];
                        $params['locations'][$cloud] = array(
                            EucalyptusPlatformModule::ACCOUNT_ID => $this->env->getPlatformConfigValue(EucalyptusPlatformModule::ACCOUNT_ID, true, $cloud),
                            EucalyptusPlatformModule::ACCESS_KEY => $this->env->getPlatformConfigValue(EucalyptusPlatformModule::ACCESS_KEY, true, $cloud),
                            EucalyptusPlatformModule::EC2_URL => $this->env->getPlatformConfigValue(EucalyptusPlatformModule::EC2_URL, true, $cloud),
                            EucalyptusPlatformModule::S3_URL => $this->env->getPlatformConfigValue(EucalyptusPlatformModule::S3_URL, true, $cloud),
                            EucalyptusPlatformModule::SECRET_KEY => $this->env->getPlatformConfigValue(EucalyptusPlatformModule::SECRET_KEY, true, $cloud) != '' ? '******' : false,
                            EucalyptusPlatformModule::PRIVATE_KEY => $this->env->getPlatformConfigValue(EucalyptusPlatformModule::PRIVATE_KEY, true, $cloud) != '' ? 'Uploaded' : '',
                            EucalyptusPlatformModule::CLOUD_CERTIFICATE => $this->env->getPlatformConfigValue(EucalyptusPlatformModule::CLOUD_CERTIFICATE, true, $cloud) != '' ? 'Uploaded' : '',
                            EucalyptusPlatformModule::CERTIFICATE => $this->env->getPlatformConfigValue(EucalyptusPlatformModule::CERTIFICATE, true, $cloud) != '' ? 'Uploaded' : ''
                        );
                    }
                    break;

            }
        }
        return $params;
    }

    public function xSaveCloudParamsAction()
    {
        $platform = $this->getParam('platform');
        if (PlatformFactory::isCloudstack($platform)) {
            $method = SERVER_PLATFORMS::CLOUDSTACK;
        } elseif (PlatformFactory::isOpenstack($platform)) {
            $method = SERVER_PLATFORMS::OPENSTACK;
        } else {
            $method = $platform;
        }

        $method = 'save' . ucfirst($method);
        if (method_exists($this, $method)) {
            $this->$method();
            $this->response->data(array('params' => $this->getCloudParams($platform)));
        } else {
            $this->response->failure('Under construction ...');
        }
    }

    private function saveEc2()
    {
        $pars = array();
        $enabled = false;
        $envAutoEnabled = false;

        $bNew = !$this->env->isPlatformEnabled(SERVER_PLATFORMS::EC2);

        if ($this->getParam('ec2_is_enabled')) {
            $enabled = true;

            $pars[Ec2PlatformModule::ACCOUNT_TYPE] = trim($this->checkVar(Ec2PlatformModule::ACCOUNT_TYPE, 'string', "AWS Account Type required"));
            $pars[Ec2PlatformModule::ACCESS_KEY] = trim($this->checkVar(Ec2PlatformModule::ACCESS_KEY, 'string', "AWS Access Key required"));
            $pars[Ec2PlatformModule::SECRET_KEY] = trim($this->checkVar(Ec2PlatformModule::SECRET_KEY, 'password', "AWS Access Key required"));
            $pars[Ec2PlatformModule::PRIVATE_KEY] = trim($this->checkVar(Ec2PlatformModule::PRIVATE_KEY, 'file'));
            $pars[Ec2PlatformModule::CERTIFICATE] = trim($this->checkVar(Ec2PlatformModule::CERTIFICATE, 'file'));

            if ($this->getContainer()->analytics->enabled) {
                $pars[Ec2PlatformModule::DETAILED_BILLING_BUCKET]  = $this->getParam(Ec2PlatformModule::DETAILED_BILLING_BUCKET) ?: null;
                $pars[Ec2PlatformModule::DETAILED_BILLING_ENABLED] = 0;
            }

            // user can mull certificate and private key, check it
            if (strpos($pars[Ec2PlatformModule::PRIVATE_KEY], 'BEGIN CERTIFICATE') !== FALSE &&
                strpos($pars[Ec2PlatformModule::CERTIFICATE], 'BEGIN PRIVATE KEY') !== FALSE) {
                // swap it
                $key = $pars[Ec2PlatformModule::PRIVATE_KEY];
                $pars[Ec2PlatformModule::PRIVATE_KEY] = $pars[Ec2PlatformModule::CERTIFICATE];
                $pars[Ec2PlatformModule::CERTIFICATE] = $key;
            }

            if ($pars[Ec2PlatformModule::ACCOUNT_TYPE] == Ec2PlatformModule::ACCOUNT_TYPE_GOV_CLOUD) {
                $region = \Scalr\Service\Aws::REGION_US_GOV_WEST_1;
            } else if ($pars[Ec2PlatformModule::ACCOUNT_TYPE] == Ec2PlatformModule::ACCOUNT_TYPE_CN_CLOUD) {
                $region = \Scalr\Service\Aws::REGION_CN_NORTH_1;
            } else {
                $region = \Scalr\Service\Aws::REGION_US_EAST_1;
            }

            if (!count($this->checkVarError)) {
                if (
                    //$pars[Ec2PlatformModule::ACCOUNT_ID] != $this->env->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID) or
                    $pars[Ec2PlatformModule::ACCESS_KEY] != $this->env->getPlatformConfigValue(Ec2PlatformModule::ACCESS_KEY) or
                    $pars[Ec2PlatformModule::SECRET_KEY] != $this->env->getPlatformConfigValue(Ec2PlatformModule::SECRET_KEY) or
                    $pars[Ec2PlatformModule::PRIVATE_KEY] != $this->env->getPlatformConfigValue(Ec2PlatformModule::PRIVATE_KEY) or
                    $pars[Ec2PlatformModule::CERTIFICATE] != $this->env->getPlatformConfigValue(Ec2PlatformModule::CERTIFICATE)
                ) {
                    $aws = $this->env->aws(
                        $region,
                        $pars[Ec2PlatformModule::ACCESS_KEY],
                        $pars[Ec2PlatformModule::SECRET_KEY],
                        !empty($pars[Ec2PlatformModule::CERTIFICATE]) ? $pars[Ec2PlatformModule::CERTIFICATE] : null,
                        !empty($pars[Ec2PlatformModule::PRIVATE_KEY]) ? $pars[Ec2PlatformModule::PRIVATE_KEY] : null
                    );

                    //Validates private key and certificate if they are provided
                    if (!empty($pars[Ec2PlatformModule::CERTIFICATE]) || !empty($pars[Ec2PlatformModule::PRIVATE_KEY])) {
                        try {
                            //SOAP is not supported anymore
                            //$aws->validateCertificateAndPrivateKey();
                        } catch (Exception $e) {
                            throw new Exception(_("Incorrect format of X.509 certificate or private key. Make sure that you are using files downloaded from AWS profile. ({$e->getMessage()})"));
                        }
                    }

                    //Validates both access and secret keys
                    try {
                        $buckets = $aws->s3->bucket->getList();
                    } catch (Exception $e) {
                        throw new Exception(sprintf(_("Failed to verify your EC2 access key and secret key: %s"), $e->getMessage()));
                    }

                    //Extract AWS Account ID
                    $pars[Ec2PlatformModule::ACCOUNT_ID] = $aws->getAccountNumber();

                    try {
                        if ($this->env->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID) != $pars[Ec2PlatformModule::ACCOUNT_ID]) {
                            $this->db->Execute("DELETE FROM client_environment_properties WHERE name LIKE 'ec2.vpc.default%' AND env_id = ?", array(
                                $this->env->id
                            ));
                        }
                    } catch (Exception $e) {}
                }
            } else {
                $this->response->failure();
                $this->response->data(array('errors' => $this->checkVarError));
                return;
            }
        }

        if ($enabled && $this->getContainer()->analytics->enabled && !empty($pars[Ec2PlatformModule::DETAILED_BILLING_BUCKET])) {
            try {
                $aws = $this->env->aws($region, $pars[Ec2PlatformModule::ACCESS_KEY], $pars[Ec2PlatformModule::SECRET_KEY]);

                $bucketObjects = $aws->s3->bucket->listObjects($pars[Ec2PlatformModule::DETAILED_BILLING_BUCKET]);

                $objectName = 'aws-billing-detailed-line-items-with-resources-and-tags';

                $objectExists = false;

                foreach ($bucketObjects as $bucketObject) {
                    /* @var $bucketObject Scalr\Service\Aws\S3\DataType\ObjectData */
                    if (strpos($bucketObject->objectName, $objectName) !== false) {
                        $pars[Ec2PlatformModule::DETAILED_BILLING_ENABLED] = 1;
                        $objectExists = true;
                        break;
                    }
                }

                if (!$objectExists) {
                    $this->response->failure();
                    $this->response->data(['errors' => [Ec2PlatformModule::DETAILED_BILLING_BUCKET => "Object with name 'aws-billing-detailed-line-items-with-resources-and-tags' does not exist."]]);
                    return;
                }
            } catch (Exception $e) {
                $this->response->failure();
                $this->response->data(['errors' => [Ec2PlatformModule::DETAILED_BILLING_BUCKET => sprintf("Cannot access billing bucket with name %s.", $pars[Ec2PlatformModule::DETAILED_BILLING_BUCKET])]]);
                return;
            }

        }

        $this->db->BeginTrans();
        try {
            $this->env->enablePlatform(SERVER_PLATFORMS::EC2, $enabled);

            if ($enabled) {
                $this->env->setPlatformConfig($pars);
                if ($this->getContainer()->analytics->enabled && $bNew) {
                    $this->getContainer()->analytics->notifications->onCloudAdd('ec2', $this->env, $this->user);
                }
            }

            if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

            if ($enabled && $this->env->status == Scalr_Environment::STATUS_INACTIVE && $this->env->getPlatformConfigValue('system.auto-disable-reason')) {
                // env was inactive due invalid keys for amazon, activate it
                $this->env->status = Scalr_Environment::STATUS_ACTIVE;
                $this->env->save();
                $this->env->setPlatformConfig(['system.auto-disable-reason' => NULL]);
                $envAutoEnabled = true;
            }

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw new Exception(_("Failed to save AWS settings: {$e->getMessage()}"));
        }

        

        $this->response->success('Cloud credentials have been ' . ($enabled ? 'saved' : 'removed from Scalr'));
        $this->response->data(array('enabled' => $enabled, 'demoFarm' => $demoFarm, 'envAutoEnabled' => $envAutoEnabled));
    }

    private function saveGce()
    {
        $pars = array();
        $enabled = false;

        if ($this->getParam('gce_is_enabled')) {
            $enabled = true;

            $pars[GoogleCEPlatformModule::PROJECT_ID] = $this->checkVar2(GoogleCEPlatformModule::PROJECT_ID, 'string', 'GCE Project ID required');
            $isJsonKeySaved = $this->env->getPlatformConfigValue(GoogleCEPlatformModule::JSON_KEY);
            if (!empty($_FILES[str_replace('.', '_', GoogleCEPlatformModule::JSON_KEY)])) {
                //json key
                $pars[GoogleCEPlatformModule::JSON_KEY] = $this->checkVar2(GoogleCEPlatformModule::JSON_KEY, 'file', 'JSON key required', false, false, $isJsonKeySaved);
                if (!count($this->checkVarError)) {
                    $jsonKey = json_decode($pars[GoogleCEPlatformModule::JSON_KEY], true);
                    //see google-api-php-client-git-03162015/src/Google/Signer/P12.php line 46
                    $jsonKey['private_key'] = str_replace(' PRIVATE KEY-----', ' RSA PRIVATE KEY-----', $jsonKey['private_key']);
                    $pars[GoogleCEPlatformModule::CLIENT_ID] = $jsonKey['client_id'];
                    $pars[GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME] = $jsonKey['client_email'];
                    $pars[GoogleCEPlatformModule::KEY] = base64_encode($jsonKey['private_key']);

                    // We need to reset access token when changing credentials
                    $pars[GoogleCEPlatformModule::ACCESS_TOKEN] = "";
                }
            } else {
                //p12 key
                $pars[GoogleCEPlatformModule::CLIENT_ID] = $this->checkVar2(GoogleCEPlatformModule::CLIENT_ID, 'string', 'GCE Cient ID required');
                $pars[GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME] = $this->checkVar2(GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME, 'string', 'GCE email (service account name) required');
                $pars[GoogleCEPlatformModule::KEY] = $this->checkVar2(GoogleCEPlatformModule::KEY, 'file', 'GCE Private Key required', false, true, !$isJsonKeySaved);
                $pars[GoogleCEPlatformModule::JSON_KEY] = false;

                // We need to reset access token when changing credentials
                $pars[GoogleCEPlatformModule::ACCESS_TOKEN] = "";
            }
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
                            $key,
                            $pars[GoogleCEPlatformModule::JSON_KEY] ? null : 'notasecret'
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

            $this->response->success('Cloud credentials have been ' . ($enabled ? 'saved' : 'removed from Scalr'));
            $this->response->data(array('enabled' => $enabled));
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw new Exception(_("Failed to save GCE settings: {$e->getMessage()}"));
        }
        $this->db->CommitTrans();
    }

    private function getCloudStackDetails($platform)
    {
        $params = array();
        $params["{$platform}.is_enabled"] = true;
        $params[CloudstackPlatformModule::API_URL] = $this->env->getPlatformConfigValue("{$platform}." . CloudstackPlatformModule::API_URL);
        $params[CloudstackPlatformModule::API_KEY] = $this->env->getPlatformConfigValue("{$platform}." . CloudstackPlatformModule::API_KEY);
        $params[CloudstackPlatformModule::SECRET_KEY] = $this->env->getPlatformConfigValue("{$platform}." . CloudstackPlatformModule::SECRET_KEY)  != '' ? '******' : '';

        try {
            $cs = new CloudStack(
                $params[CloudstackPlatformModule::API_URL],
                $params[CloudstackPlatformModule::API_KEY],
                $this->env->getPlatformConfigValue("{$platform}." . CloudstackPlatformModule::SECRET_KEY),
                $platform
            );

            $params['_info'] = $cs->listCapabilities();

        } catch (Exception $e) {}

        return $params;
    }

    private function saveCloudstack()
    {
        $pars = array();
        $enabled = false;
        $platform = $this->getParam('platform');

        $bNew = !$this->env->isPlatformEnabled($platform);
        if (!$bNew) {
            $oldUrl = $this->env->getPlatformConfigValue("{$platform}." . CloudstackPlatformModule::API_URL);
        }

        if ($this->getParam("{$platform}_is_enabled")) {
            $enabled = true;

            $pars["{$platform}." . CloudstackPlatformModule::API_URL] = $this->checkVar(CloudstackPlatformModule::API_URL, 'string', 'API URL required');
            $pars["{$platform}." . CloudstackPlatformModule::API_KEY] = $this->checkVar(CloudstackPlatformModule::API_KEY, 'string', 'API key required');
            $pars["{$platform}." . CloudstackPlatformModule::SECRET_KEY] = $this->checkVar(CloudstackPlatformModule::SECRET_KEY, 'password', 'Secret key required', '', false, $platform);
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

                $listAccountsData = new ListAccountsData();
                $listAccountsData->listall = true;
                //$listAccountsData->accounttype = 0;

                $accounts = $cs->listAccounts($listAccountsData);
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
                    if ($this->getContainer()->analytics->enabled &&
                        ($bNew || $oldUrl !== $pars["{$platform}." . CloudstackPlatformModule::API_URL])) {
                        $this->getContainer()->analytics->notifications->onCloudAdd($platform, $this->env, $this->user);
                    }
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

                $this->response->success('Cloud credentials have been ' . ($enabled ? 'saved' : 'removed from Scalr'));
                $this->response->data(array('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(_('Failed to save '.ucfirst($platform).' settings'));
            }
            $this->db->CommitTrans();
        }
    }

    private function getOpenStackDetails($platform)
    {
        $params = array();
        $params["{$platform}.is_enabled"] = true;
        $params[OpenstackPlatformModule::KEYSTONE_URL] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::KEYSTONE_URL);
        $params[OpenstackPlatformModule::SSL_VERIFYPEER] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::SSL_VERIFYPEER);
        $params[OpenstackPlatformModule::USERNAME] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::USERNAME);
        $params[OpenstackPlatformModule::PASSWORD] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::PASSWORD) != '' ? '******' : '';
        $params[OpenstackPlatformModule::API_KEY] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::API_KEY);
        if ($platform == SERVER_PLATFORMS::ECS) {
            $params[OpenstackPlatformModule::TENANT_NAME] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::TENANT_NAME) != '' ? '******' : '';
        } else {
            $params[OpenstackPlatformModule::TENANT_NAME] = $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::TENANT_NAME);
        }

        $params['features'] = array();

        /* @var $config Yaml */
        $config = $this->env->getContainer()->config;

        if (isset($platform) &&
            $config->defined("scalr.{$platform}.use_proxy") &&
            $config("scalr.{$platform}.use_proxy") &&
            in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
            $params['proxySettings'] = $config('scalr.connections.proxy');
        } else {
            $params['proxySettings'] = null;
        }

        if ($params[OpenstackPlatformModule::KEYSTONE_URL]) {

            try {
                $os = new OpenStack(new OpenStackConfig(
                        $params[OpenstackPlatformModule::USERNAME],
                        $params[OpenstackPlatformModule::KEYSTONE_URL],
                        'fake-region',
                        $params[OpenstackPlatformModule::API_KEY],
                        null, // Closure callback for token
                        null, // Auth token. We should be assured about it right now
                        $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::PASSWORD),
                        $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::TENANT_NAME),
                        null,
                        $params['proxySettings']
                ));

                //$os->setDebug(true);

                $params['regions'] = $os->listZones();
                foreach ($params['regions'] as $region) {
                    try{
                        $cloudLocation = $region->name;
                        $os = new OpenStack(new OpenStackConfig(
                                $params[OpenstackPlatformModule::USERNAME],
                                $params[OpenstackPlatformModule::KEYSTONE_URL],
                                $cloudLocation,
                                $params[OpenstackPlatformModule::API_KEY],
                                null, // Closure callback for token
                                null, // Auth token. We should be assured about it right now
                                $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::PASSWORD),
                                $this->env->getPlatformConfigValue("{$platform}." . OpenstackPlatformModule::TENANT_NAME),
                                null,
                                $params['proxySettings']
                        ));

                        $params['features'][$cloudLocation] = array(
                            'Volumes (Cinder)' => $os->hasService('volume'),
                            'Security groups (Nova)' => $os->servers->isExtensionSupported(ServersExtension::securityGroups()),
                            'Networking (Neutron)' => $os->hasService('network'),
                            'Load balancing (Neutron LBaaS)' => (!in_array($platform, array(SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK)) && $os->hasService('network')) ? $os->network->isExtensionSupported('lbaas') : false,
                            'Floating IPs (Nova)' => $os->servers->isExtensionSupported(ServersExtension::floatingIps()),
                            'Objects store (Swift)' => $os->hasService('object-store')
                        );

                        $params['info'][$cloudLocation] = array(
                            'services' => $os->listServices(),
                            'nova_extensions' => $os->servers->listExtensions()
                        );

                        if ($os->hasService('network')) {
                            $params['info'][$cloudLocation]['neutron_url'] = $os->network->getEndpointUrl();
                            $params['info'][$cloudLocation]['neutron_extensions'] = $os->network->listExtensions();
                        }
                    } catch (Exception $e) {
                        $params['info'][$cloudLocation]['exception'] = $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                //TODO: Show in UI
                $params['info']['exception'] = $e->getMessage();
            }
        }

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
        return $this->getParam('platform') . "." . constant('Scalr\\Modules\\Platforms\\Openstack\\OpenstackPlatformModule::' . $name);
    }

    private function saveOpenstack()
    {
        $pars = array();
        $enabled = false;
        $platform = $this->getParam('platform');

        $bNew = !$this->env->isPlatformEnabled($platform);
        if (!$bNew) {
            $oldUrl = $this->env->getPlatformConfigValue($this->getOpenStackOption('KEYSTONE_URL'));
        }

        if ($this->getParam("{$platform}_is_enabled")) {
            $enabled = true;

            $pars[$this->getOpenStackOption('KEYSTONE_URL')] = trim($this->checkVar(OpenstackPlatformModule::KEYSTONE_URL, 'string', 'KeyStone URL required'));
            $pars[$this->getOpenStackOption('SSL_VERIFYPEER')] = trim($this->checkVar(OpenstackPlatformModule::SSL_VERIFYPEER, 'int'));
            $pars[$this->getOpenStackOption('USERNAME')] = $this->checkVar(OpenstackPlatformModule::USERNAME, 'string', 'Username required');
            $pars[$this->getOpenStackOption('PASSWORD')] = $this->checkVar(OpenstackPlatformModule::PASSWORD, 'password', '', '', false, $platform);
            $pars[$this->getOpenStackOption('API_KEY')] = $this->checkVar(OpenstackPlatformModule::API_KEY, 'string');

            $pars[$this->getOpenStackOption('IDENTITY_VERSION')] = OpenStackConfig::parseIdentityVersion($pars[$this->getOpenStackOption('KEYSTONE_URL')]);

            if ($platform == SERVER_PLATFORMS::ECS) {
                $pars[$this->getOpenStackOption('TENANT_NAME')] = $this->checkVar(OpenstackPlatformModule::TENANT_NAME, 'password', '', '', false, $platform);
            } else {
                $pars[$this->getOpenStackOption('TENANT_NAME')] = $this->checkVar(OpenstackPlatformModule::TENANT_NAME, 'string');
            }

            if (empty($this->checkVarError) &&
                empty($pars[$this->getOpenStackOption('PASSWORD')]) &&
                empty($pars[$this->getOpenStackOption('API_KEY')])) {
                $this->checkVarError['api_key'] = $this->checkVarError['password'] = 'Either API Key or password must be provided.';
            }
        }

        /* @var $config Yaml */
        $config = $this->env->getContainer()->config;

        if (isset($platform) &&
            $config->defined("scalr.{$platform}.use_proxy") &&
            $config("scalr.{$platform}.use_proxy") &&
            in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
            $pars['proxySettings'] = $config('scalr.connections.proxy');
        } else {
            $pars['proxySettings'] = null;
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {
            if ($this->getParam($platform . "_is_enabled")) {
                $os = new OpenStack(new OpenStackConfig(
                    $pars[$this->getOpenStackOption('USERNAME')],
                    $pars[$this->getOpenStackOption('KEYSTONE_URL')],
                    'fake-region',
                    $pars[$this->getOpenStackOption('API_KEY')],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $pars[$this->getOpenStackOption('PASSWORD')],
                    $pars[$this->getOpenStackOption('TENANT_NAME')],
                    $pars[$this->getOpenStackOption('IDENTITY_VERSION')],
                    $pars['proxySettings']
                ));

                //It throws an exception on failure
                $zones = $os->listZones();
                $zone = array_shift($zones);

                $os = new OpenStack(new OpenStackConfig(
                    $pars[$this->getOpenStackOption('USERNAME')],
                    $pars[$this->getOpenStackOption('KEYSTONE_URL')],
                    $zone->name,
                    $pars[$this->getOpenStackOption('API_KEY')],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $pars[$this->getOpenStackOption('PASSWORD')],
                    $pars[$this->getOpenStackOption('TENANT_NAME')],
                    $pars[$this->getOpenStackOption('IDENTITY_VERSION')],
                    $pars['proxySettings']
                ));

                // Check SG Extension
                $pars[$this->getOpenStackOption('EXT_SECURITYGROUPS_ENABLED')] = (int)$os->servers->isExtensionSupported(ServersExtension::securityGroups());

                // Check Floating Ips Extension
                $pars[$this->getOpenStackOption('EXT_FLOATING_IPS_ENABLED')] = (int)$os->servers->isExtensionSupported(ServersExtension::floatingIps());

                // Check Cinder Extension
                $pars[$this->getOpenStackOption('EXT_CINDER_ENABLED')] = (int)$os->hasService('volume');

                // Check Swift Extension
                $pars[$this->getOpenStackOption('EXT_SWIFT_ENABLED')] = (int)$os->hasService('object-store');

                // Check LBaas Extension
                $pars[$this->getOpenStackOption('EXT_LBAAS_ENABLED')] = (!in_array($platform, array(SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK)) && $os->hasService('network')) ? (int)$os->network->isExtensionSupported('lbaas') : 0;
            }

            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform($platform, $enabled);

                if ($enabled) {
                    $this->env->setPlatformConfig($pars);

                    if ($this->getContainer()->analytics->enabled &&
                        ($bNew || $oldUrl !== $pars[$this->getOpenStackOption('KEYSTONE_URL')])) {
                        $this->getContainer()->analytics->notifications->onCloudAdd($platform, $this->env, $this->user);
                    }
                } else {
                    $this->env->setPlatformConfig(array(
                        "{$platform}." . OpenstackPlatformModule::AUTH_TOKEN => false
                    ));
                }

                if (!$this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                    $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

                $this->response->success('Cloud credentials have been ' . ($enabled ? 'saved' : 'removed from Scalr'));
                $this->response->data(array('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(_('Failed to save '.ucfirst($platform).' settings'));
            }
            $this->db->CommitTrans();
        }
    }

    private function saveRackspace()
    {
        $pars = array();
        $enabled = false;
        $locations = array('rs-ORD1', 'rs-LONx');

        if (! $this->env->isPlatformEnabled(SERVER_PLATFORMS::RACKSPACE))
            throw new Scalr_Exception_Core('Rackspace cloud has been deprecated. Please use Rackspace Open Cloud instead.');

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

                $this->response->success('Cloud credentials have been ' . ($enabled ? 'saved' : 'removed from Scalr'));
                $this->response->data(array('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(_('Failed to save Rackspace settings'));
            }
            $this->db->CommitTrans();
        }
    }

    private function saveEucalyptus()
    {
        $this->request->defineParams(array(
            'locations' => array('type' => 'json')
        ));

        $pars = array();
        $enabled = false;

        $locations = $this->getParam('locations');
        $locationsDeleted = array();
        if (count($locations)) {
            $enabled = true;
            foreach ($locations as $location) {
                $pars[$location][EucalyptusPlatformModule::ACCOUNT_ID] = $this->checkVar(EucalyptusPlatformModule::ACCOUNT_ID, 'string', "Account ID required", $location);
                $pars[$location][EucalyptusPlatformModule::ACCESS_KEY] = $this->checkVar(EucalyptusPlatformModule::ACCESS_KEY, 'string', "Access Key required", $location);
                $pars[$location][EucalyptusPlatformModule::EC2_URL] = $this->checkVar(EucalyptusPlatformModule::EC2_URL, 'string', "EC2 URL required", $location);
                $pars[$location][EucalyptusPlatformModule::S3_URL] = $this->checkVar(EucalyptusPlatformModule::S3_URL, 'string', "S3 URL required", $location);
                $pars[$location][EucalyptusPlatformModule::SECRET_KEY] = $this->checkVar(EucalyptusPlatformModule::SECRET_KEY, 'password', "Secret Key required", $location);
                $pars[$location][EucalyptusPlatformModule::PRIVATE_KEY] = $this->checkVar(EucalyptusPlatformModule::PRIVATE_KEY, 'file', "x.509 Private Key required", $location);
                $pars[$location][EucalyptusPlatformModule::CERTIFICATE] = $this->checkVar(EucalyptusPlatformModule::CERTIFICATE, 'file', "x.509 Certificate required", $location);
                $pars[$location][EucalyptusPlatformModule::CLOUD_CERTIFICATE] = $this->checkVar(EucalyptusPlatformModule::CLOUD_CERTIFICATE, 'file', "x.509 Cloud Certificate required", $location);
            }
        }

        // clear old cloud locations
        foreach ($this->db->GetAll("
                SELECT * FROM client_environment_properties
                WHERE env_id = ? AND name LIKE 'eucalyptus.%' AND `group` != ''
                GROUP BY `group`
            ", $this->env->id
        ) as $key => $value) {
            if (!in_array($value['group'], $locations))
                $locationsDeleted[] = $value['group'];
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {
            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform(SERVER_PLATFORMS::EUCALYPTUS, $enabled);

                foreach ($locationsDeleted as $key => $location) {
                    $this->db->Execute('
                        DELETE FROM client_environment_properties
                        WHERE env_id = ? AND `group` = ? AND name LIKE "eucalyptus.%"
                    ', array($this->env->id, $location));
                }

                foreach ($pars as $location => $prs) {
                    //Saves options to database
                    $this->env->setPlatformConfig($prs, true, $location);

                    //Verifies cloud credentials
                    $client = $this->env->eucalyptus($location);

                    try {
                        //Checks ec2url
                        $client->ec2->availabilityZone->describe();
                    } catch (ClientException $e) {
                        throw new Exception(sprintf(
                            "Failed to verify your access key and secret key against ec2 service for location %s: (%s)",
                            $location, $e->getMessage()
                        ));
                    }

                    try {
                        //Verifies s3url
                        $client->s3->bucket->getList();
                    } catch (ClientException $e) {
                        throw new Exception(sprintf(
                            "Failed to verify your access key and secret key against s3 service for location %s: (%s)",
                            $location, $e->getMessage()
                        ));
                    }
                }

                if (!$this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                    $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

                $this->response->success(_('Environment saved'));
                $this->response->data(array('enabled' => $enabled));
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw new Exception(sprintf("Failed to save Eucalyptus settings. %s", $e->getMessage()));
            }
            $this->db->CommitTrans();
        }
    }

}
