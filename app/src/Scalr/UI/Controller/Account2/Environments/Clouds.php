<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Azure;
use Scalr\Service\Azure\DataType\ProviderData;
use Scalr\Service\CloudStack\DataType\AccountList;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\ListAccountsData;
use Scalr\System\Config\Yaml;
use Scalr\DataType\CloudPlatformSuspensionInfo;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Account2_Environments_Clouds extends Scalr_UI_Controller
{
    /**
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
        return parent::hasAccess() && ($this->user->isAccountSuperAdmin() || $this->request->isAllowed(Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT));
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $platforms = $this->env->getEnabledPlatforms();
        $suspendedPlatforms = [];
        foreach ($platforms as $platform) {
            $suspensionInfo = new CloudPlatformSuspensionInfo($this->env->id, $platform);
            if ($suspensionInfo->isPendingSuspend() || $suspensionInfo->isSuspended()) {
                $suspendedPlatforms[$platform] = $suspensionInfo->getLastErrorMessage();
            }
        }

        $this->response->page('ui/account2/environments/clouds.js', array(
            'env' => array(
                'id'   => $this->env->id,
                'name' => $this->env->name
            ),
            'enabledPlatforms' => $platforms,
            'suspendedPlatforms' => $suspendedPlatforms
        ), array(
            'ui/account2/environments/clouds/ec2.js',
            'ui/account2/environments/clouds/gce.js',
            'ui/account2/environments/clouds/cloudstack.js',
            'ui/account2/environments/clouds/openstack.js',
            'ui/account2/environments/clouds/azure.js'
        ), array('ui/account2/environments/clouds.css'));
    }

    private function checkVar($name, $type, $requiredError = '', $cloud = '', $noFileTrim = false, $namePrefix = '', $base64encode = false)
    {
        $varName = str_replace('.', '_', $name);
        $errorName = $name;
        $name = (!empty($namePrefix) ? $namePrefix . '.' : '') . $name;

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
                    $value = ($noFileTrim) ? $value : trim($value);

                    if ($value !== '0' && empty($value)) {
                        return false;
                    }

                    return $base64encode ? base64_encode($value) : $value;
                } else {
                    $value = $this->env->keychain($cloud)->properties[$name];
                    if ($value == '' && $requiredError)
                        $this->checkVarError[$errorName] = $requiredError;

                    if ($value !== '0' && empty($value)) {
                        return false;
                    }

                    return $value;
                }
                break;
        }
    }

    private function checkVar2($name, $type, $requiredError = '', $cloud = '', $trim = true, $base64encode = false, $fetchIfEmpty = false)
    {
        $varName = str_replace('.', '_', $name);

        switch ($type) {
            case 'string':
                $value = $this->getParam($varName);
                $value = $trim ? trim($value) : $value;
                if (empty($value) && $fetchIfEmpty) {
                    $value = $this->env->keychain($cloud)->properties[$name];
                }
                if (empty($value) && $requiredError) {
                    $this->checkVarError[$name] = $requiredError;
                }
                return $value;
            case 'password':
                $value = $this->getParam($varName);
                if ($value === '******' || empty($value) && $fetchIfEmpty) {
                    $value = $this->env->keychain($cloud)->properties[$name];
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
                    $value = $this->env->keychain($cloud)->properties[$name];
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
        $params = [];

        if (in_array($platform, $this->env->getEnabledPlatforms()) || $platform == SERVER_PLATFORMS::AZURE) {
            $cloudCredentials = $this->env->keychain($platform);
            $ccProps = $cloudCredentials->properties;

            switch ($platform) {
                case SERVER_PLATFORMS::EC2:
                    $params[SERVER_PLATFORMS::EC2 . '.is_enabled'] = true;
                    $params[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] = $ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID];
                    $params[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE] = $ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE];
                    $params[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY] = $ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY];
                    $params[Entity\CloudCredentialsProperty::AWS_SECRET_KEY] = $ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY] != '' ? '******' : '';
                    $params[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] = $ccProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] != '' ? 'Uploaded' : '';
                    $params[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] = $ccProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] != '' ? 'Uploaded' : '';
                    $params[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET] = $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET];
                    $params[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED] = $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED];
                    $params[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT] = $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT];
                    $params[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION] = $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION];

                    try {
                        if ($params[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE] == Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_CN_CLOUD)
                            $params['arn'] = $this->env->aws('cn-north-1')->getUserArn();
                        elseif ($params[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE] == Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD)
                            $params['arn'] = $this->env->aws('us-gov-west-1')->getUserArn();
                        else
                            $params['arn'] = $this->env->aws('us-east-1')->getUserArn();
                        //$params['username'] = $this->env->aws('us-east-1')->getUsername();
                    } catch (Exception $e) {}

                    break;
                case SERVER_PLATFORMS::GCE:
                    $params[SERVER_PLATFORMS::GCE . '.is_enabled'] = true;
                    $params[Entity\CloudCredentialsProperty::GCE_PROJECT_ID] = $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];
                    $jsonKey = $ccProps[Entity\CloudCredentialsProperty::GCE_JSON_KEY];
                    if (!empty($jsonKey)) {
                        $params[Entity\CloudCredentialsProperty::GCE_JSON_KEY] = 'Uploaded';
                    } else {
                        $params[Entity\CloudCredentialsProperty::GCE_CLIENT_ID] = $ccProps[Entity\CloudCredentialsProperty::GCE_CLIENT_ID];
                        $params[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME] = $ccProps[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME];
                        $params[Entity\CloudCredentialsProperty::GCE_KEY] = $ccProps[Entity\CloudCredentialsProperty::GCE_KEY] != '' ? 'Uploaded' : '';
                    }
                    break;
                case SERVER_PLATFORMS::CLOUDSTACK:
                case SERVER_PLATFORMS::IDCF:
                    $params = $this->getCloudStackDetails($platform);
                    break;
                case SERVER_PLATFORMS::OPENSTACK:
                case SERVER_PLATFORMS::RACKSPACENG_UK:
                case SERVER_PLATFORMS::RACKSPACENG_US:
                case SERVER_PLATFORMS::OCS:
                case SERVER_PLATFORMS::NEBULA:
                case SERVER_PLATFORMS::MIRANTIS:
                case SERVER_PLATFORMS::VIO:
                case SERVER_PLATFORMS::VERIZON:
                case SERVER_PLATFORMS::CISCO:
                case SERVER_PLATFORMS::HPCLOUD:
                    $params = $this->getOpenStackDetails($platform);
                    break;
                case SERVER_PLATFORMS::AZURE:
                    $params[SERVER_PLATFORMS::AZURE . '.is_enabled'] = $cloudCredentials->isEnabled();
                    $params[Entity\CloudCredentialsProperty::AZURE_TENANT_NAME] = $ccProps[Entity\CloudCredentialsProperty::AZURE_TENANT_NAME];
                    $params[Entity\CloudCredentialsProperty::AZURE_AUTH_STEP] = $ccProps[Entity\CloudCredentialsProperty::AZURE_AUTH_STEP] ?: 0;
                    $params[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID] = $ccProps[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID];

                    $params['subscriptions'] = [];

                    if ($params[Entity\CloudCredentialsProperty::AZURE_AUTH_STEP] > 1) {
                        $subscriptionList = [];

                        try {
                            $subscriptions = $this->env->azure()->getSubscriptionsList();

                            foreach ($subscriptions as $subscription) {
                                if ($subscription->state == 'Enabled') {
                                    $subscriptionList[] = [
                                        'displayName'    => $subscription->displayName,
                                        'subscriptionId' => $subscription->subscriptionId,
                                    ];
                                }
                            }
                        } catch (Exception $e) {
                            if (strpos($e->getMessage(), 'Error validating credentials') !== false ||
                                strpos($e->getMessage(), 'Refresh token is expired or not exists') !== false) {

                                $cloudCredentials->delete();
                                $cloudCredentials->release();
                                $params[Entity\CloudCredentialsProperty::AZURE_AUTH_STEP] = 0;
                                $params[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID] = null;
                            }

                            $params['errorMessage'] = $e->getMessage();
                            break;
                        }

                        if (empty($subscriptionList)) {
                            $params['errorMessage'] = sprintf("There are no active subscriptions available for the '%s' tenant", $params[Entity\CloudCredentialsProperty::AZURE_TENANT_NAME]);
                        }

                        $params['subscriptions'] = $subscriptionList;
                    }

                    break;
            }
        }

        if ($platform == SERVER_PLATFORMS::EC2) {
            $platformModule = PlatformFactory::NewPlatform($platform);
            /* @var $platformModule Ec2PlatformModule */
            $params['cloudLocations'] = [
                Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_REGULAR    => array_keys($platformModule->getLocationsByAccountType(Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_REGULAR)),
                Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD  => array_keys($platformModule->getLocationsByAccountType(Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD)),
                Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_CN_CLOUD   => array_keys($platformModule->getLocationsByAccountType(Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_CN_CLOUD))
            ];
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

            $suspensionInfo = new CloudPlatformSuspensionInfo($this->env->id, $platform);
            $suspensionInfo->resume();

            $this->response->data(array('params' => $this->getCloudParams($platform)));
        } else {
            $this->response->failure('Under construction ...');
        }
    }

    private function saveAzure()
    {
        if (Scalr::isHostedScalr() && !$this->request->getHeaderVar('Interface-Beta')) {
            $this->response->failure('Azure support available only for Scalr Enterprise Edition.');
            return;
        }

        $enabled = false;

        $currentCloudCredentials = $this->env->keychain(SERVER_PLATFORMS::AZURE);

        if (empty($currentCloudCredentials->id)) {
            $currentCloudCredentials = $this->makeCloudCredentials(SERVER_PLATFORMS::AZURE, [], Entity\CloudCredentials::STATUS_DISABLED);
        }

        /* @var $ccProps Scalr\Model\Collections\SettingsCollection */
        $ccProps = $currentCloudCredentials->properties;

        if ($this->getParam('azure.is_enabled')) {
            $enabled = true;

            $tenantName = $this->checkVar(Entity\CloudCredentialsProperty::AZURE_TENANT_NAME, 'string', "Azure Tenant name is required", SERVER_PLATFORMS::AZURE);

            $ccProps->saveSettings([Entity\CloudCredentialsProperty::AZURE_AUTH_STEP => 0]);

            if (!count($this->checkVarError)) {
                $oldTenantName = $ccProps[Entity\CloudCredentialsProperty::AZURE_TENANT_NAME];
                $ccProps->saveSettings([Entity\CloudCredentialsProperty::AZURE_TENANT_NAME => $tenantName]);

                $azure = $this->env->azure();
                $ccProps->saveSettings([Entity\CloudCredentialsProperty::AZURE_AUTH_STEP => 1]);

                $authorizationCode = $ccProps[Entity\CloudCredentialsProperty::AZURE_AUTH_CODE];

                $accessToken = $ccProps[Entity\CloudCredentialsProperty::AZURE_ACCESS_TOKEN];

                if ((empty($authorizationCode) && empty($accessToken))
                    || $oldTenantName != $ccProps[Entity\CloudCredentialsProperty::AZURE_TENANT_NAME]) {

                    $ccProps->saveSettings([
                        Entity\CloudCredentialsProperty::AZURE_AUTH_CODE => false,
                        Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID => false
                    ]);

                    $location = $azure->getAuthorizationCodeLocation();
                    $this->response->data(['authLocation' => $location]);
                    return;
                }

                $ccProps->saveSettings([Entity\CloudCredentialsProperty::AZURE_AUTH_STEP => 0]);

                $subscriptionId = trim(
                    $this->checkVar(Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID, 'string', "Azure Subscription id is required", SERVER_PLATFORMS::AZURE)
                );

                $params[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID] = $subscriptionId;

                if (!count($this->checkVarError)) {
                    $oldSubscriptionId = $ccProps[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID];

                    if ($subscriptionId != $oldSubscriptionId) {
                        $azure->getClientToken();

                        $objectId = $azure->getAppObjectId();
                        $params[Entity\CloudCredentialsProperty::AZURE_CLIENT_OBJECT_ID] = $objectId;

                        $contributorRoleId = $azure->getContributorRoleId($subscriptionId);
                        $params[Entity\CloudCredentialsProperty::AZURE_CONTRIBUTOR_ID] = $contributorRoleId;

                        $roleAssignment = $azure->getContributorRoleAssignmentInfo($subscriptionId, $objectId, $contributorRoleId);

                        if (empty($roleAssignment)) {
                            $roleAssignmentId = \Scalr::GenerateUID();
                            $azure->assignContributorRoleToApp($subscriptionId, $roleAssignmentId, $objectId, $contributorRoleId);
                        } else {
                            $roleAssignmentId = $roleAssignment->name;
                        }

                        $params[Entity\CloudCredentialsProperty::AZURE_ROLE_ASSIGNMENT_ID] = $roleAssignmentId;

                        $ccProps->saveSettings([
                            Entity\CloudCredentialsProperty::AZURE_CLIENT_TOKEN => false,
                            Entity\CloudCredentialsProperty::AZURE_CLIENT_TOKEN_EXPIRE => false
                        ]);

                        $azure->getClientToken(Azure::URL_CORE_WINDOWS);

                        $ccProps->saveSettings($params);

                        $providersList = $azure->getProvidersList($subscriptionId);
                        $requiredProviders = ProviderData::getRequiredProviders();

                        foreach ($providersList as $providerData) {
                            /* @var $providerData ProviderData */
                            if (in_array($providerData->namespace, $requiredProviders) && $providerData->registrationState == ProviderData::REGISTRATION_STATE_NOT_REGISTERED) {
                                $registerResponse = $azure->registerSubscription($subscriptionId, $providerData->namespace);
                            }
                        }

                        if (!empty($registerResponse)) {
                            do {
                                sleep(5);
                                $provider = $azure->getLocationsList($registerResponse->namespace);
                            } while ($provider->registrationState != ProviderData::REGISTRATION_STATE_REGISTERED);
                        }
                    }

                    $ccProps[Entity\CloudCredentialsProperty::AZURE_AUTH_STEP] = 3;
                } else {
                    $this->response->failure();
                    $this->response->data(['errors' => $this->checkVarError]);
                    return;
                }
            } else {
                $this->response->failure();
                $this->response->data(['errors' => $this->checkVarError]);
                return;
            }
        }

        $this->db->BeginTrans();

        try {
            $this->env->enablePlatform(SERVER_PLATFORMS::AZURE, $enabled);

            if ($enabled) {
                $currentCloudCredentials->status = Entity\CloudCredentials::STATUS_ENABLED;
                $currentCloudCredentials->save();
            }

            if (!$this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED)) {
                $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());
            }

            $this->response->success('Environment saved');
            $this->response->data(['enabled' => $enabled]);
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw new Exception(_("Failed to save Azure settings: {$e->getMessage()}"));
        }

        $this->db->CommitTrans();
    }

    private function saveAzureSettings($pars, $encrypt = true)
    {
        $this->db->BeginTrans();

        try {
            $this->env->setPlatformConfig($pars, $encrypt);
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw new Exception(_("Failed to save Azure settings: {$e->getMessage()}"));
        }

        $this->db->CommitTrans();
    }

    private function saveEc2()
    {
        $pars = [];
        $enabled = false;
        $envAutoEnabled = false;

        $bNew = !$this->env->isPlatformEnabled(SERVER_PLATFORMS::EC2);

        $currentCloudCredentials = $this->env->keychain(SERVER_PLATFORMS::EC2);
        $ccProps = $currentCloudCredentials->properties;

        if ($this->getParam('ec2_is_enabled')) {
            $enabled = true;

            $pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE] = trim($this->checkVar(Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE, 'string', "AWS Account Type required", SERVER_PLATFORMS::EC2));
            $pars[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY]   = trim($this->checkVar(Entity\CloudCredentialsProperty::AWS_ACCESS_KEY, 'string', "AWS Access Key required", SERVER_PLATFORMS::EC2));
            $pars[Entity\CloudCredentialsProperty::AWS_SECRET_KEY]   = trim($this->checkVar(Entity\CloudCredentialsProperty::AWS_SECRET_KEY, 'password', "AWS Access Key required", SERVER_PLATFORMS::EC2));
            $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY]  = $this->checkVar(Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY, 'file', '', SERVER_PLATFORMS::EC2);
            $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]  = $this->checkVar(Entity\CloudCredentialsProperty::AWS_CERTIFICATE, 'file', '', SERVER_PLATFORMS::EC2);

            if ($this->getContainer()->analytics->enabled) {
                $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED] = $this->checkVar2(Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED, 'bool', '', SERVER_PLATFORMS::EC2);

                if (!empty($pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED])) {
                    $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET]        = $this->checkVar(Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET, 'string', "Detailed billing bucket name is required", SERVER_PLATFORMS::EC2);
                    $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT] = $this->checkVar2(Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT, 'string', '', SERVER_PLATFORMS::EC2);
                    $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION]        = $this->checkVar(Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION, 'string', "Aws region is required", SERVER_PLATFORMS::EC2);
                } else {
                    $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET]        = false;
                    $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT] = false;
                    $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION]        = false;
                }
            }

            // user can mull certificate and private key, check it
            if (strpos($pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY], 'BEGIN CERTIFICATE') !== FALSE &&
                strpos($pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE], 'BEGIN PRIVATE KEY') !== FALSE) {
                // swap it
                $key = $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY];
                $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] = $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE];
                $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] = $key;
            }

            if ($pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE] == Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD) {
                $region = \Scalr\Service\Aws::REGION_US_GOV_WEST_1;
            } else if ($pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE] == Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_CN_CLOUD) {
                $region = \Scalr\Service\Aws::REGION_CN_NORTH_1;
            } else {
                $region = \Scalr\Service\Aws::REGION_US_EAST_1;
            }

            if (!count($this->checkVarError)) {
                if (
                    //$pars[Ec2PlatformModule::ACCOUNT_ID] != $this->env->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID) or
                    $pars[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY] != $ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY] or
                    $pars[Entity\CloudCredentialsProperty::AWS_SECRET_KEY] != $ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY] or
                    $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] != $ccProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] or
                    $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] != $ccProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]
                ) {
                    $aws = $this->env->aws(
                        $region,
                        $pars[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY],
                        $pars[Entity\CloudCredentialsProperty::AWS_SECRET_KEY],
                        !empty($pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]) ? $pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] : null,
                        !empty($pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY]) ? $pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] : null
                    );

                    //Validates private key and certificate if they are provided
                    if (!empty($pars[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]) || !empty($pars[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY])) {
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
                    $pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] = $aws->getAccountNumber();

                    try {
                        if ($ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] != $pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID]) {
                            $this->db->Execute("DELETE FROM client_environment_properties WHERE name LIKE 'ec2.vpc.default%' AND env_id = ?", [
                                $this->env->id
                            ]);
                        }
                    } catch (Exception $e) {}
                } else {
                    $pars[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] = $ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID];
                }
            } else {
                $this->response->failure();
                $this->response->data(['errors' => $this->checkVarError]);
                return;
            }
        }

        if ($enabled && $this->getContainer()->analytics->enabled && !empty($pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET])) {
            try {
                $region = $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION];

                $aws = $this->env->aws($region, $pars[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY], $pars[Entity\CloudCredentialsProperty::AWS_SECRET_KEY]);

                if (!empty($pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]) && $aws->getAccountNumber() != $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]) {
                    $payerCredentials = $this->getUser()->getAccount()->cloudCredentialsList(
                        [SERVER_PLATFORMS::EC2],
                        [],
                        [Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID => [['value' => $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]]]]
                    );

                    if (count($payerCredentials) == 0) {
                        throw new Exception("Payer account not found!");
                    }

                    $payerCredentials = $payerCredentials->current();


                    $aws = $this->env->aws(
                        $region,
                        $payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY],
                        $payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_SECRET_KEY],
                        !empty($payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]) ? $payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] : null,
                        !empty($payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY]) ? $payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] : null
                    );
                }

                try {
                    $bucketObjects = $aws->s3->bucket->listObjects($pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET]);
                } catch (ClientException $e) {
                    if ($e->getErrorData() && $e->getErrorData()->getCode() == ErrorData::ERR_AUTHORIZATION_HEADER_MALFORMED &&
                        preg_match("/expecting\s+'(.+?)'/", $e->getMessage(), $matches) &&
                        in_array($matches[1], Aws::getCloudLocations())) {
                        $expectingRegion = $matches[1];

                        if (isset($payerCredentials)) {
                            $aws = $this->env->aws(
                                $expectingRegion,
                                $payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY],
                                $payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_SECRET_KEY],
                                !empty($payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]) ? $payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_CERTIFICATE] : null,
                                !empty($payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY]) ? $payerCredentials->properties[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY] : null
                            );
                        } else {
                            $aws = $this->env->aws($expectingRegion, $pars[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY], $pars[Entity\CloudCredentialsProperty::AWS_SECRET_KEY]);
                        }

                        $bucketObjects = $aws->s3->bucket->listObjects($pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET]);
                        $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION] = $expectingRegion;
                    } else {
                        throw $e;
                    }
                }

                $objectName = (empty($pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]) ? '' : "{$pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]}-") . 'aws-billing-detailed-line-items-with-resources-and-tags';

                $objectExists = false;
                $bucketObjectName = null;

                foreach ($bucketObjects as $bucketObject) {
                    /* @var $bucketObject Scalr\Service\Aws\S3\DataType\ObjectData */
                    if (strpos($bucketObject->objectName, $objectName) !== false) {
                        $bucketObjectName = $bucketObject->objectName;
                        $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED] = 1;
                        $objectExists = true;
                        break;
                    }
                }

                if (!$objectExists) {
                    $this->response->failure();
                    $this->response->data(['errors' => [Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT => "Object with name '{$objectName}' does not exist."]]);
                    return;
                }

                $aws->s3->object->getMetadata($pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET], $bucketObjectName);
            } catch (Exception $e) {
                $this->response->failure();
                $this->response->data(['errors' => [Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET => sprintf("Cannot access billing bucket with name %s. Error: %s", $pars[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET], $e->getMessage())]]);
                return;
            }

        }

        $this->db->BeginTrans();
        try {
            $this->env->enablePlatform(SERVER_PLATFORMS::EC2, $enabled);

            if ($enabled) {
                $this->makeCloudCredentials(SERVER_PLATFORMS::EC2, $pars);

                if ($this->getContainer()->analytics->enabled && $bNew) {
                    $this->getContainer()->analytics->notifications->onCloudAdd('ec2', $this->env, $this->user);
                }
            }

            if (! $this->user->getAccount()->getSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED))
                $this->user->getAccount()->setSetting(Scalr_Account::SETTING_DATE_ENV_CONFIGURED, time());

            //TODO: cloud suspension info must work with cloud credentials
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
        $this->response->data(['enabled' => $enabled, 'demoFarm' => $demoFarm, 'envAutoEnabled' => $envAutoEnabled]);
    }

    private function saveGce()
    {
        $pars = array();
        $enabled = false;

        $currentCloudCredentials = $this->env->keychain(SERVER_PLATFORMS::GCE);
        $cccProps = $currentCloudCredentials->properties;

        if ($this->getParam('gce_is_enabled')) {
            $enabled = true;

            $pars[Entity\CloudCredentialsProperty::GCE_PROJECT_ID] = $this->checkVar2(Entity\CloudCredentialsProperty::GCE_PROJECT_ID, 'string', 'GCE Project ID required', SERVER_PLATFORMS::GCE);
            $isJsonKeySaved = $currentCloudCredentials->properties[Entity\CloudCredentialsProperty::GCE_JSON_KEY];
            if (!empty($_FILES[str_replace('.', '_', Entity\CloudCredentialsProperty::GCE_JSON_KEY)])) {
                //json key
                $pars[Entity\CloudCredentialsProperty::GCE_JSON_KEY] = $this->checkVar2(Entity\CloudCredentialsProperty::GCE_JSON_KEY, 'file', 'JSON key required', SERVER_PLATFORMS::GCE, false, false, $isJsonKeySaved);
                if (!count($this->checkVarError)) {
                    $jsonKey = json_decode($pars[Entity\CloudCredentialsProperty::GCE_JSON_KEY], true);
                    //see vendor/google/apiclient/src/Google/Signer/P12.php line 46
                    $jsonKey['private_key'] = str_replace(' PRIVATE KEY-----', ' RSA PRIVATE KEY-----', $jsonKey['private_key']);
                    $pars[Entity\CloudCredentialsProperty::GCE_CLIENT_ID] = $jsonKey['client_id'];
                    $pars[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME] = $jsonKey['client_email'];
                    $pars[Entity\CloudCredentialsProperty::GCE_KEY] = base64_encode($jsonKey['private_key']);

                    // We need to reset access token when changing credentials
                    $pars[Entity\CloudCredentialsProperty::GCE_ACCESS_TOKEN] = "";
                }
            } else {
                //p12 key
                $pars[Entity\CloudCredentialsProperty::GCE_CLIENT_ID] = $this->checkVar2(Entity\CloudCredentialsProperty::GCE_CLIENT_ID, 'string', 'GCE Cient ID required', SERVER_PLATFORMS::GCE);
                $pars[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME] = $this->checkVar2(Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME, 'string', 'GCE email (service account name) required');
                $pars[Entity\CloudCredentialsProperty::GCE_KEY] = $this->checkVar2(Entity\CloudCredentialsProperty::GCE_KEY, 'file', 'GCE Private Key required', SERVER_PLATFORMS::GCE, false, true, !$isJsonKeySaved);
                $pars[Entity\CloudCredentialsProperty::GCE_JSON_KEY] = false;

                // We need to reset access token when changing credentials
                $pars[Entity\CloudCredentialsProperty::GCE_ACCESS_TOKEN] = "";
            }
            if (! count($this->checkVarError)) {
                if (
                    $pars[Entity\CloudCredentialsProperty::GCE_CLIENT_ID] != $cccProps[Entity\CloudCredentialsProperty::GCE_CLIENT_ID] or
                    $pars[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME] != $cccProps[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME] or
                    $pars[Entity\CloudCredentialsProperty::GCE_PROJECT_ID] != $cccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID] or
                    $pars[Entity\CloudCredentialsProperty::GCE_KEY] != $cccProps[Entity\CloudCredentialsProperty::GCE_KEY]
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

            if ($enabled) {
                $this->makeCloudCredentials(SERVER_PLATFORMS::GCE, $pars);
            }

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
        $ccProps = $this->env->keychain($platform)->properties;

        $params = array();
        $params["{$platform}.is_enabled"] = true;
        $params[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL];
        $params[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY];
        $params[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY]  != '' ? '******' : '';

        try {
            $cs = new CloudStack(
                $params[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL],
                $params[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY],
                $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY],
                $platform
            );

            /* @var $config Yaml */
            $config = $this->env->getContainer()->config;

            if ($config->defined("scalr.{$platform}.use_proxy") &&
                $config("scalr.{$platform}.use_proxy") &&
                in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
                $proxySettings = $config('scalr.connections.proxy');

                $cs->setProxy(
                    $proxySettings['host'], $proxySettings['port'], $proxySettings['user'],
                    $proxySettings['pass'], $proxySettings['type'], $proxySettings['authtype']
                );
            }

            $params['_info'] = $cs->listCapabilities();

        } catch (Exception $e) {}

        return $params;
    }

    /**
     * Searches a Cloudstack user name from the accounts list by api key and sets properties
     *
     * @param   AccountList $accounts   Accounts list
     * @param   array       $pars       Cloudstack properties
     *
     * @return  bool    Returns true if api key is found, false - otherwise
     */
    private function searchCloudstackUser(AccountList $accounts = null, array $pars = [])
    {
        if (!empty($accounts)) {
            foreach ($accounts as $account) {
                foreach ($account->user as $user) {
                    if ($user->apikey == $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY]) {
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_ACCOUNT_NAME] = $user->account;
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_NAME] = $user->domain;
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_ID] = $user->domainid;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function saveCloudstack()
    {
        $pars = array();
        $enabled = false;
        $platform = $this->getParam('platform');

        $currentCloudCredentials = $this->env->keychain($platform);
        $ccProps = $currentCloudCredentials->properties;

        $bNew = !$currentCloudCredentials->isEnabled();
        if (!$bNew) {
            $oldUrl = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL];
        }

        if ($this->getParam("{$platform}_is_enabled")) {
            $enabled = true;

            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL] = $this->checkVar(Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL, 'string', 'API URL required', $platform);
            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY] = $this->checkVar(Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY, 'string', 'API key required', $platform);
            $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY] = $this->checkVar(Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY, 'password', 'Secret key required', $platform);
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

                $listAccountsData = new ListAccountsData();
                $listAccountsData->listall = true;
                //$listAccountsData->accounttype = 0;

                /* @var $config Yaml */
                $config = $this->env->getContainer()->config;

                if ($config->defined("scalr.{$platform}.use_proxy") &&
                    $config("scalr.{$platform}.use_proxy") &&
                    in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
                    $proxySettings = $config('scalr.connections.proxy');

                    $cs->setProxy(
                        $proxySettings['host'], $proxySettings['port'], $proxySettings['user'],
                        $proxySettings['pass'], $proxySettings['type'], $proxySettings['authtype']
                    );
                }

                if (!$this->searchCloudstackUser($cs->listAccounts($listAccountsData), $pars)) {
                    throw new Exception("Cannot determine account name for provided keys");
                }
            }

            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform($platform, $enabled);

                if ($enabled) {
                    $this->makeCloudCredentials($platform, $pars);
                    if ($this->getContainer()->analytics->enabled &&
                        ($bNew || $oldUrl !== $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL])) {
                        $this->getContainer()->analytics->notifications->onCloudAdd($platform, $this->env, $this->user);
                    }
                } else {
                    $currentCloudCredentials->environments[$this->env->id]->delete();
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
        $params = [];

        $cloudCredentials = $this->env->keychain($platform);
        $ccProps = $cloudCredentials->properties;

        $params["{$platform}.is_enabled"] = true;
        $params[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL]    = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL];
        $params[Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER]  = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER];
        $params[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME]        = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME];
        $params[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD]        = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD] != '' ? '******' : '';
        $params[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY]         = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY];
        $params[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME]     = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME];
        $params[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME]     = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME];

        $params['features'] = [];

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

        if ($params[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL]) {
            try {
                $openstackConfig = new OpenStackConfig(
                    $params[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME],
                    $params[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL],
                    'fake-region',
                    $params[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME],
                    null,
                    $params['proxySettings'],
                    5
                );

                $os = new OpenStack($openstackConfig);

                $params['regions'] = $os->listZones();

                foreach ($params['regions'] as $region) {
                    $cloudLocation = $region->name;

                    $osConfig = clone $openstackConfig;
                    $osConfig->setRegion($cloudLocation);

                    try {
                        $os = new OpenStack($osConfig);

                        $params['features'][$cloudLocation] = [
                            'Volumes (Cinder)'               => $os->hasService('volume'),
                            'Security groups (Nova)'         => $os->servers->isExtensionSupported(ServersExtension::securityGroups()),
                            'Networking (Neutron)'           => $os->hasService('network'),
                            'Load balancing (Neutron LBaaS)' => (!in_array($platform, [SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK]) && $os->hasService('network')) ?
                                                                $os->network->isExtensionSupported('lbaas') : false,
                            'Floating IPs (Nova)'            => $os->servers->isExtensionSupported(ServersExtension::floatingIps()),
                            'Objects store (Swift)'          => $os->hasService('object-store')
                        ];

                        $params['info'][$cloudLocation] = [
                            'services'        => $os->listServices(),
                            'nova_extensions' => $os->servers->listExtensions()
                        ];

                        if ($os->hasService('network')) {
                            $params['info'][$cloudLocation]['neutron_url'] = $os->network->getEndpointUrl();
                            $params['info'][$cloudLocation]['neutron_extensions'] = $os->network->listExtensions();
                        }
                    } catch (Exception $e) {
                        $params['features'][$cloudLocation] = $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $params['info']['exception'] = $e->getMessage();
            }
        }

        return $params;
    }

    private function saveOpenstack()
    {
        $pars = array();
        $enabled = false;
        $platform = $this->getParam('platform');
        $currentCloudCredentials = $this->env->keychain($platform);

        $bNew = !$currentCloudCredentials->isEnabled();
        if (!$bNew) {
            $oldUrl = $currentCloudCredentials->properties[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL];
        }

        if ($this->getParam("{$platform}_is_enabled")) {
            $enabled = true;

            $pars[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL] = trim($this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL, 'string', 'KeyStone URL required', $platform));
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER] = trim($this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER, 'bool', '', $platform));
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_USERNAME, 'string', 'Username required', $platform);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD, 'password', '', $platform, false);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_API_KEY, 'string', '', $platform);

            $pars[Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION] = OpenStackConfig::parseIdentityVersion($pars[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL]);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME, 'string', '', $platform);
            $pars[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME] = $this->checkVar(Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME, 'string', '', $platform);

            if (empty($this->checkVarError) &&
                empty($pars[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD]) &&
                empty($pars[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY])) {
                $this->checkVarError['api_key'] = $this->checkVarError['password'] = 'Either API Key or password must be provided.';
            }
        }

        /* @var $config Yaml */
        $config = $this->env->getContainer()->config;

        if (isset($platform) &&
            $config->defined("scalr.{$platform}.use_proxy") &&
            $config("scalr.{$platform}.use_proxy") &&
            in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
            $proxySettings = $config('scalr.connections.proxy');
        } else {
            $proxySettings = null;
        }

        if (count($this->checkVarError)) {
            $this->response->failure();
            $this->response->data(array('errors' => $this->checkVarError));
        } else {
            if ($this->getParam($platform . "_is_enabled")) {
                $os = new OpenStack(new OpenStackConfig(
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL],
                    'fake-region',
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION],
                    $proxySettings
                ));

                //It throws an exception on failure
                $zones = $os->listZones();
                $zone = array_shift($zones);

                $os = new OpenStack(new OpenStackConfig(
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL],
                    $zone->name,
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME],
                    $pars[Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION],
                    $proxySettings
                ));

                // Check SG Extension
                $pars[Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED] = (int)$os->servers->isExtensionSupported(ServersExtension::securityGroups());

                // Check Floating Ips Extension
                $pars[Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED] = (int)$os->servers->isExtensionSupported(ServersExtension::floatingIps());

                // Check Cinder Extension
                $pars[Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED] = (int)$os->hasService('volume');

                // Check Swift Extension
                $pars[Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED] = (int)$os->hasService('object-store');

                // Check LBaas Extension
                $pars[Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED] = (!in_array($platform, array(SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK)) && $os->hasService('network')) ? (int)$os->network->isExtensionSupported('lbaas') : 0;
            }

            $this->db->BeginTrans();
            try {
                $this->env->enablePlatform($platform, $enabled);

                if ($enabled) {
                    $this->makeCloudCredentials($platform, $pars);

                    if ($this->getContainer()->analytics->enabled &&
                        ($bNew || $oldUrl !== $pars[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL])) {
                        $this->getContainer()->analytics->notifications->onCloudAdd($platform, $this->env, $this->user);
                    }
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
