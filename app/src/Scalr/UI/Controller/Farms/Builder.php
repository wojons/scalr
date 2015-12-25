<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use Scalr\Stats\CostAnalytics\Entity\PriceEntity;
use Scalr\UI\Request\Validator;
use Scalr\Service\Aws\Ec2\DataType\CreateVolumeRequestData;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\RoleProperty;

class Scalr_UI_Controller_Farms_Builder extends Scalr_UI_Controller
{

    var $errors = array('farm' => array(), 'roles' => array(), 'error_count' => 0, 'first_error' => '');

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isFarmAllowed();
    }

    public function xGetStorageConfigAction()
    {
        $farmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
        $this->user->getPermissions()->validate($farmRole);

        $device = \Scalr\Farm\Role\FarmRoleStorageDevice::getByConfigIdAndIndex($this->getParam('configId'), $this->getParam('serverIndex'));
        if ($device) {
            $this->response->data(array(
                'config' => $device->config
            ));
        } else {
            $this->response->failure('Config not found');
        }
    }

    public function xRemoveStorageVolumeAction()
    {
        $farmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
        $this->user->getPermissions()->validate($farmRole);

        $storageId = $this->getParam('storageId');

        $device = \Scalr\Farm\Role\FarmRoleStorageDevice::loadById($storageId);

        //TODO:

        $this->response->success();
    }

    private function setBuildError($setting, $message, $roleId = null, $isFinal = false)
    {
        $this->errors['error_count']++;
        if ($roleId == null)
            $this->errors['farm'][$setting] = $message;
        else
            $this->errors['roles'][$roleId][$setting] = $message;
    }

    private function isFarmRoleConfigurationValid()
    {

    }

    /**
     *
     * @param array $farmSettings
     * @param array $roles
     * @return bool
     */
    private function isFarmConfigurationValid($farmId, $farmSettings, array $roles = array())
    {
        $this->errors = array('error_count' => 0);

        $farmVariables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_FARM);
        $farmRoleVariables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_FARMROLE);

        $name = $this->request->stripValue($farmSettings['name']);
        if (empty($name)) {
            $this->setBuildError('name', 'Farm name is invalid');
        }
        if ($farmSettings['variables']) {
            $result = $farmVariables->validateValues(is_array($farmSettings['variables']) ? $farmSettings['variables'] : [], 0, $farmId);
            if ($result !== TRUE)
                $this->setBuildError('variables', $result);
        }

        if (is_numeric($farmSettings['owner'])) {
            try {
                $u = (new Scalr_Account_User())->loadById($farmSettings['owner']);
                if ($u->getAccountId() != $this->user->getAccountId()) {
                    throw new Exception('User not found');
                }
            } catch (Exception $e) {
                $this->setBuildError('owner', $e->getMessage());
            }
        }

        if (is_numeric($farmSettings['teamOwner']) && $farmSettings['teamOwner'] > 0) {
            if ($this->user->canManageAcl()) {
                $teams = $this->db->getAll('SELECT id, name FROM account_teams WHERE account_id = ?', array($this->user->getAccountId()));
            } else {
                $teams = $this->user->getTeams();
            }

            if (!in_array($farmSettings['teamOwner'], array_map(function($t) { return $t['id']; }, $teams))) {
                if ($this->db->GetOne('SELECT team_id FROM farms WHERE id = ?', [$farmId]) != $farmSettings['teamOwner'])
                    $this->setBuildError('teamOwner', 'Team not found');
            }
        }

        if (!empty($roles)) {
            $hasVpcRouter = false;
            $vpcRouterRequired = false;
            $governance = new Scalr_Governance($this->getEnvironmentId());
            
            foreach ($roles as $role) {
                $dbRole = DBRole::loadById($role['role_id']);

                if (!$this->hasPermissions($dbRole->__getNewRoleObject())) {
                    $this->setBuildError(
                        $dbRole->name,
                        'You don\'t have access to this role'
                    );
                }

                try {
                    $dbRole->__getNewRoleObject()->getImage($role['platform'], $role['cloud_location']);
                } catch (Exception $e) {
                    $this->setBuildError(
                        $dbRole->name,
                        sprintf("Role '%s' is not available in %s on %s",
                            $dbRole->name, $role['platform'], $role['cloud_location']
                        )
                    );
                }

                if ($role['alias']) {
                    if (!preg_match("/^[[:alnum:]](?:-*[[:alnum:]])*$/", $role['alias']))
                        $this->setBuildError(
                            'alias',
                            'Alias should start and end with letter or number and contain only letters, numbers and dashes.',
                            $role['farm_role_id']
                        );
                }

                // Validate deployments
                if (isset($role[Scalr_Role_Behavior::ROLE_DM_APPLICATION_ID])) {
                    $application = Scalr_Dm_Application::init()->loadById($role[Scalr_Role_Behavior::ROLE_DM_APPLICATION_ID]);
                    $this->user->getPermissions()->validate($application);

                    if (!$role[Scalr_Role_Behavior::ROLE_DM_REMOTE_PATH]) {
                        $this->setBuildError(
                            Scalr_Role_Behavior::ROLE_DM_REMOTE_PATH,
                            'Remote path is required for deployment',
                            $role['farm_role_id']
                        );
                    }
                }

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER))
                    $hasVpcRouter = true;

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
                    $role['settings'][Entity\FarmRoleSetting::SCALING_MAX_INSTANCES] = $role['settings'][Entity\FarmRoleSetting::SCALING_MIN_INSTANCES];

                    $role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] = (int)$role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO];
                    if ($role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] < 1 || $role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] > 100) {
                        $this->setBuildError(
                            Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO,
                            'Nodes ratio should be an integer between 1 and 100',
                            $role['farm_role_id']
                        );
                    } else {
                        $this->checkInteger($role['farm_role_id'], Scalr_Role_Behavior_RabbitMQ::ROLE_DATA_STORAGE_EBS_SIZE, $role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_DATA_STORAGE_EBS_SIZE], 'Storage size', 1, 1000);
                    }
                }

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                    if ($role['settings'][Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_ENGINE] == 'ebs') {
                        if ($role['settings'][Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_EBS_SIZE] < 10 || $role['settings'][Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_EBS_SIZE] > 1000) {
                            $this->setBuildError(
                                Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_EBS_SIZE,
                                sprintf("EBS size for mongoDB role should be between 10 and 1000 GB", $dbRole->name),
                                $role['farm_role_id']
                            );
                        }
                    }
                }

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::NGINX)) {
                    $proxies = (array)@json_decode($role['settings'][Scalr_Role_Behavior_Nginx::ROLE_PROXIES], true);
                    foreach ($proxies as $proxyIndex => $proxy) {
                        if ($proxy['ssl'] == 1) {
                            if (empty($proxy['ssl_certificate_id'])) {
                                $this->setBuildError(
                                    Scalr_Role_Behavior_Nginx::ROLE_PROXIES,
                                    ['message' => 'SSL certificate is required', 'invalidIndex' => $proxyIndex],
                                    $role['farm_role_id']
                                );
                                break;
                            }
                            if ($proxy['port'] == $proxy['ssl_port']) {
                                $this->setBuildError(
                                    Scalr_Role_Behavior_Nginx::ROLE_PROXIES,
                                    ['message' => 'HTTP and HTTPS ports cannot be the same', 'invalidIndex' => $proxyIndex],
                                    $role['farm_role_id']
                                );
                            }
                        }
                        if (count($proxy['backends']) > 0) {
                            foreach ($proxy['backends'] as $backend) {
                                if (empty($backend['farm_role_id']) && empty($backend['farm_role_alias']) && empty($backend['host'])) {
                                    $this->setBuildError(
                                        Scalr_Role_Behavior_Nginx::ROLE_PROXIES,
                                        ['message' => 'Destination is required', 'invalidIndex' => $proxyIndex],
                                        $role['farm_role_id']
                                    );
                                    break;
                                }
                            }
                        }
                    }

               }

                /* Validate scaling */
                if (!$dbRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER) && !$dbRole->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                    $minCount = $this->checkInteger($role['farm_role_id'], Entity\FarmRoleSetting::SCALING_MIN_INSTANCES, $role['settings'][Entity\FarmRoleSetting::SCALING_MIN_INSTANCES], 'Min instances', 0, 400);
                    $maxCount = $this->checkInteger($role['farm_role_id'], Entity\FarmRoleSetting::SCALING_MAX_INSTANCES, $role['settings'][Entity\FarmRoleSetting::SCALING_MAX_INSTANCES], 'Max instances', 1, 400);
                    if ($minCount !== false && $maxCount !== false && $maxCount < $minCount) {
                        $this->setBuildError(
                            Entity\FarmRoleSetting::SCALING_MAX_INSTANCES,
                            'Max instances should be greater than or equal to Min instances',
                            $role['farm_role_id']
                        );
                    }

                    $this->checkInteger($role['farm_role_id'], Entity\FarmRoleSetting::SCALING_POLLING_INTERVAL, $role['settings'][Entity\FarmRoleSetting::SCALING_POLLING_INTERVAL], 'Polling interval', 1, 50);

                    if (array_key_exists(Entity\FarmRoleSetting::SCALING_UPSCALE_TIMEOUT_ENABLED, $role["settings"]) && $role['settings'][Entity\FarmRoleSetting::SCALING_UPSCALE_TIMEOUT_ENABLED] == 1) {
                        $this->checkInteger($role['farm_role_id'], Entity\FarmRoleSetting::SCALING_UPSCALE_TIMEOUT, $role['settings'][Entity\FarmRoleSetting::SCALING_UPSCALE_TIMEOUT], 'Upscale timeout', 1);
                    }

                    if (array_key_exists(Entity\FarmRoleSetting::SCALING_DOWNSCALE_TIMEOUT_ENABLED, $role["settings"]) && $role['settings'][Entity\FarmRoleSetting::SCALING_DOWNSCALE_TIMEOUT_ENABLED] == 1) {
                        $this->checkInteger($role['farm_role_id'], Entity\FarmRoleSetting::SCALING_DOWNSCALE_TIMEOUT, $role['settings'][Entity\FarmRoleSetting::SCALING_DOWNSCALE_TIMEOUT], 'Downscale timeout', 1);
                    }

                    if (is_array($role['scaling'])) {
                        foreach ($role['scaling'] as $metricId => $metricSettings) {
                            $hasError = false;
                            if ($metricId == Entity\ScalingMetric::METRIC_URL_RESPONSE_TIME_ID) {
                                $hasError = Validator::validateUrl($metricSettings['url']) !== true ||
                                            Validator::validateInteger($metricSettings['min']) !== true ||
                                            Validator::validateInteger($metricSettings['max']) !== true;
                            } elseif ($metricId == Entity\ScalingMetric::METRIC_SQS_QUEUE_SIZE_ID) {
                                $hasError = Validator::validateNotEmpty($metricSettings['queue_name']) !== true ||
                                            Validator::validateInteger($metricSettings['min']) !== true ||
                                            Validator::validateInteger($metricSettings['max']) !== true;
                            } elseif (in_array($metricId, [Entity\ScalingMetric::METRIC_LOAD_AVERAGES_ID, Entity\ScalingMetric::METRIC_FREE_RAM_ID, Entity\ScalingMetric::METRIC_BANDWIDTH_ID])) {
                                $hasError = Validator::validateFloat($metricSettings['min']) !== true ||
                                            Validator::validateFloat($metricSettings['max']) !== true;
                            }
                            if ($hasError) {
                                $this->setBuildError(
                                    'scaling',
                                    ['message' => 'Scaling metric settings are invalid', 'invalidIndex' => $metricId],
                                    $role['farm_role_id']
                                );
                                break;
                            }
                        }
                    }
                }

                /* Validate advanced settings */
                if (!$dbRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
                    if (isset($role['settings'][Scalr_Role_Behavior::ROLE_BASE_API_PORT])) {
                        $this->checkInteger($role['farm_role_id'], Scalr_Role_Behavior::ROLE_BASE_API_PORT, $role['settings'][Scalr_Role_Behavior::ROLE_BASE_API_PORT], 'Scalarizr API port', 1, 65535);
                    }
                    if (isset($role['settings'][Scalr_Role_Behavior::ROLE_BASE_MESSAGING_PORT])) {
                        $this->checkInteger($role['farm_role_id'], Scalr_Role_Behavior::ROLE_BASE_MESSAGING_PORT, $role['settings'][Scalr_Role_Behavior::ROLE_BASE_MESSAGING_PORT], 'Scalarizr control port', 1, 65535);
                    }

                    if (isset($role['settings'][Entity\FarmRoleSetting::SYSTEM_REBOOT_TIMEOUT])) {
                        $this->checkInteger($role['farm_role_id'], Entity\FarmRoleSetting::SYSTEM_REBOOT_TIMEOUT, $role['settings'][Entity\FarmRoleSetting::SYSTEM_REBOOT_TIMEOUT], 'Reboot timeout', 1);
                    }
                    if (isset($role['settings'][Entity\FarmRoleSetting::SYSTEM_LAUNCH_TIMEOUT])) {
                        $this->checkInteger($role['farm_role_id'], Entity\FarmRoleSetting::SYSTEM_LAUNCH_TIMEOUT, $role['settings'][Entity\FarmRoleSetting::SYSTEM_LAUNCH_TIMEOUT], 'Launch timeout', 1);
                    }
                }

                /* Validate chef settings */
                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CHEF)) {
                    if ($role['settings'][Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP] == 1) {
                        if (empty($role['settings'][Scalr_Role_Behavior_Chef::ROLE_CHEF_COOKBOOK_URL]) && empty($role['settings'][Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID])) {
                            $this->setBuildError(
                                Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID,
                                'Chef Server or Chef Solo must be setup if using Chef to bootstrap Role',
                                $role['farm_role_id']
                            );
                        } elseif ($role['settings'][Scalr_Role_Behavior_Chef::ROLE_CHEF_COOKBOOK_URL_TYPE] == 'http' &&
                                  !empty($role['settings'][Scalr_Role_Behavior_Chef::ROLE_CHEF_COOKBOOK_URL]) &&
                                  Validator::validateUrl($role['settings'][Scalr_Role_Behavior_Chef::ROLE_CHEF_COOKBOOK_URL]) !== true) {
                            $this->setBuildError(
                                Scalr_Role_Behavior_Chef::ROLE_CHEF_COOKBOOK_URL,
                                'Cookbook URL is invalid.',
                                $role['farm_role_id']
                            );
                        }
                    } elseif ($dbRole->getProperty(Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP) == 1 && $dbRole->getProperty(Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID)) {
                        if (strpos($role['farm_role_id'], "virtual_") !== false) {
                            $chefGovernance = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_CHEF, 'servers');
                            if ($chefGovernance !== null && !isset($chefGovernance[$dbRole->getProperty(Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID)])) {
                                $this->setBuildError(
                                    Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID,
                                    'Chef server is not allowed by Governance.',
                                    $role['farm_role_id']
                                );
                            }
                        }
                        if (empty($dbRole->getProperty(Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT)) && empty($role['settings'][Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT])) {
                            $this->setBuildError(
                                Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT,
                                'Chef Environment is required',
                                $role['farm_role_id']
                            );
                        }
                    }
                }
                /** Validate platform specified settings **/
                switch($role['platform']) {
                    case SERVER_PLATFORMS::EC2:
                        if (!empty($role['settings'][Entity\FarmRoleSetting::AWS_TAGS_LIST])) {
                            $reservedBaseCustomTags = ['scalr-meta', 'Name'];
                            $baseCustomTags = @explode("\n", $role['settings'][Entity\FarmRoleSetting::AWS_TAGS_LIST]);
                            foreach ((array)$baseCustomTags as $tag) {
                                $tag = trim($tag);
                                $tagChunks = explode("=", $tag);
                                if (in_array(trim($tagChunks[0]), $reservedBaseCustomTags)) {
                                    $this->setBuildError(
                                        Entity\FarmRoleSetting::AWS_TAGS_LIST,
                                        "Avoid using Scalr-reserved tag names.",
                                        $role['farm_role_id']
                                    );
                                }
                            }
                        }

                        if ($dbRole->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
                            if ($role['settings'][Entity\FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::EBS) {
                                if ($dbRole->generation != 2 && isset($role['settings'][Entity\FarmRoleSetting::AWS_AVAIL_ZONE])) {
                                    if ($role['settings'][Entity\FarmRoleSetting::AWS_AVAIL_ZONE] == "" ||
                                        $role['settings'][Entity\FarmRoleSetting::AWS_AVAIL_ZONE] == "x-scalr-diff" ||
                                        stristr($role['settings'][Entity\FarmRoleSetting::AWS_AVAIL_ZONE], 'x-scalr-custom')) {
                                        $this->setBuildError(
                                            Entity\FarmRoleSetting::AWS_AVAIL_ZONE,
                                            'Requirement for EBS MySQL data storage is specific \'Placement\' parameter',
                                            $role['farm_role_id']
                                        );
                                    }
                                }
                            }
                        }

                        if ($dbRole->getDbMsrBehavior()) {
                            if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::EPH) {
                                if (!$role['settings'][Scalr_Db_Msr::DATA_STORAGE_EPH_DISK] && !$role['settings'][Scalr_Db_Msr::DATA_STORAGE_EPH_DISKS]) {
                                    $this->setBuildError(
                                        Scalr_Db_Msr::DATA_STORAGE_EPH_DISK,
                                        'Ephemeral disk settings is required',
                                        $role['farm_role_id']
                                    );
                                }
                            } elseif ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::EBS) {
                                if (array_key_exists(Scalr_Db_Msr::DATA_STORAGE_EBS_TYPE, $role["settings"])) {
                                    if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_TYPE] == CreateVolumeRequestData::VOLUME_TYPE_STANDARD) {
                                        $this->checkInteger($role['farm_role_id'], Scalr_Db_Msr::DATA_STORAGE_EBS_SIZE, $role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_SIZE], 'Storage size', 1, 1024);
                                    } elseif ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_TYPE] == CreateVolumeRequestData::VOLUME_TYPE_GP2) {
                                        $this->checkInteger($role['farm_role_id'], Scalr_Db_Msr::DATA_STORAGE_EBS_SIZE, $role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_SIZE], 'Storage size', 1, 16384);
                                    } elseif ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_TYPE] == CreateVolumeRequestData::VOLUME_TYPE_IO1) {
                                        $this->checkInteger($role['farm_role_id'], Scalr_Db_Msr::DATA_STORAGE_EBS_SIZE, $role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_SIZE], 'Storage size', 4, 16384);
                                        $this->checkInteger($role['farm_role_id'], Scalr_Db_Msr::DATA_STORAGE_EBS_IOPS, $role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_IOPS], 'IOPS', 100, 20000);
                                    }
                                }
                            }

                            if (array_key_exists(Scalr_Db_Msr::DATA_STORAGE_EBS_ENABLE_ROTATION, $role["settings"]) && $role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_ENABLE_ROTATION] == 1) {
                                $this->checkInteger($role['farm_role_id'], Scalr_Db_Msr::DATA_STORAGE_EBS_ROTATE, $role['settings'][Scalr_Db_Msr::DATA_STORAGE_EBS_ROTATE], 'Snapshot rotation limit', 1);
                            }

                            if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::LVM) {
                                if (!$role['settings'][Scalr_Role_DbMsrBehavior::ROLE_DATA_STORAGE_LVM_VOLUMES]) {
                                    $this->setBuildError(
                                        Scalr_Role_DbMsrBehavior::ROLE_DATA_STORAGE_LVM_VOLUMES,
                                        'LVM storage settings is required',
                                        $role['farm_role_id']
                                    );
                                }
                            }
                        }

                        if ($role['settings'][Entity\FarmRoleSetting::AWS_AVAIL_ZONE] == 'x-scalr-custom=') {
                            $this->setBuildError(
                                Entity\FarmRoleSetting::AWS_AVAIL_ZONE,
                                'Availability zone should be selected',
                                $role['farm_role_id']
                            );
                        }

                        if (!empty($farmSettings['vpc_id'])) {
                            $sgs = @json_decode($role['settings'][Entity\FarmRoleSetting::AWS_SECURITY_GROUPS_LIST]);
                            if (!$governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_SECURITY_GROUPS) &&
                                !$dbRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER) && 
                                empty($sgs) && 
                                empty($role['settings'][Entity\FarmRoleSetting::AWS_SG_LIST])
                            ) {
                                $this->setBuildError(
                                    Entity\FarmRoleSetting::AWS_SECURITY_GROUPS_LIST,
                                    'Security group(s) should be selected',
                                    $role['farm_role_id']
                                );
                            }

                            $subnets = @json_decode($role['settings'][Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID]);
                            if (empty($subnets)) {
                                $this->setBuildError(
                                    Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID,
                                    'VPC Subnet(s) should be selected',
                                    $role['farm_role_id']
                                );
                            }

                            if (\Scalr::config('scalr.instances_connection_policy') != 'local' &&
                                empty($role['settings'][Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID])) {
                                try {
                                    if (!empty($subnets[0])) {
                                        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
                                        $info = $platform->listSubnets(
                                            $this->getEnvironment(),
                                            $role['cloud_location'],
                                            $farmSettings['vpc_id'],
                                            true,
                                            $subnets[0]
                                        );

                                        if (!empty($info["type"]) && $info['type'] == 'private') {
                                            $vpcRouterRequired = $role['farm_role_id'];
                                        }
                                    }
                                } catch (Exception $e) {}
                            }
                        }
                        break;
                    case SERVER_PLATFORMS::CLOUDSTACK:
                        if (!$role['settings'][Entity\FarmRoleSetting::CLOUDSTACK_SERVICE_OFFERING_ID]) {
                            $this->setBuildError(
                                Entity\FarmRoleSetting::CLOUDSTACK_SERVICE_OFFERING_ID,
                                'Service offering should be selected',
                                $role['farm_role_id']
                            );
                        }
                        break;
                    case SERVER_PLATFORMS::RACKSPACE:
                        if (!$role['settings'][Entity\FarmRoleSetting::RS_FLAVOR_ID]) {
                            $this->setBuildError(
                                Entity\FarmRoleSetting::CLOUDSTACK_SERVICE_OFFERING_ID,
                                'Flavor should be selected',
                                $role['farm_role_id']
                            );
                        }
                        break;
                    case SERVER_PLATFORMS::GCE:
                        if ($dbRole->getDbMsrBehavior()) {
                            if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::GCE_PERSISTENT) {
                                $this->checkInteger($role['farm_role_id'], Scalr_Db_Msr::DATA_STORAGE_GCED_SIZE, $role['settings'][Scalr_Db_Msr::DATA_STORAGE_GCED_SIZE], 'Storage size', 1);
                            }
                        }
                        break;
                }

                if ($dbRole->getDbMsrBehavior()) {
                    if (array_key_exists(Scalr_Db_Msr::DATA_BUNDLE_ENABLED, $role["settings"]) && $role['settings'][Scalr_Db_Msr::DATA_BUNDLE_ENABLED] == 1) {
                        $this->checkInteger($role['farm_role_id'], Scalr_Db_Msr::DATA_BUNDLE_EVERY, $role['settings'][Scalr_Db_Msr::DATA_BUNDLE_EVERY], 'Bundle period', 1);

                        $this->checkString($role['farm_role_id'], Scalr_Db_Msr::DATA_BUNDLE_TIMEFRAME_START_HH, $role['settings'][Scalr_Db_Msr::DATA_BUNDLE_TIMEFRAME_START_HH], 'Preferred bundle window start HH is invalid', '/^([0-1][0-9])|(2[0-4])$/');
                        $this->checkString($role['farm_role_id'], Scalr_Db_Msr::DATA_BUNDLE_TIMEFRAME_START_MM, $role['settings'][Scalr_Db_Msr::DATA_BUNDLE_TIMEFRAME_START_MM], 'Preferred bundle window start MM is invalid', '/^[0-5][0-9]$/');
                        $this->checkString($role['farm_role_id'], Scalr_Db_Msr::DATA_BUNDLE_TIMEFRAME_END_HH, $role['settings'][Scalr_Db_Msr::DATA_BUNDLE_TIMEFRAME_END_HH], 'Preferred bundle window end HH is invalid', '/^([0-1][0-9])|(2[0-4])$/');
                        $this->checkString($role['farm_role_id'], Scalr_Db_Msr::DATA_BUNDLE_TIMEFRAME_END_MM, $role['settings'][Scalr_Db_Msr::DATA_BUNDLE_TIMEFRAME_END_MM], 'Preferred bundle window end MM is invalid', '/^[0-5][0-9]$/');
                    }

                    if (array_key_exists(Scalr_Db_Msr::DATA_BACKUP_ENABLED, $role["settings"]) && $role['settings'][Scalr_Db_Msr::DATA_BACKUP_ENABLED] == 1) {
                        $this->checkInteger($role['farm_role_id'], Scalr_Db_Msr::DATA_BACKUP_EVERY, $role['settings'][Scalr_Db_Msr::DATA_BACKUP_EVERY], 'Backup period', 1);

                        $this->checkString($role['farm_role_id'], Scalr_Db_Msr::DATA_BACKUP_TIMEFRAME_START_HH, $role['settings'][Scalr_Db_Msr::DATA_BACKUP_TIMEFRAME_START_HH], 'Preferred backup window start HH is invalid', '/^([0-1][0-9])|(2[0-4])$/');
                        $this->checkString($role['farm_role_id'], Scalr_Db_Msr::DATA_BACKUP_TIMEFRAME_START_MM, $role['settings'][Scalr_Db_Msr::DATA_BACKUP_TIMEFRAME_START_MM], 'Preferred backup window start MM is invalid', '/^[0-5][0-9]$/');
                        $this->checkString($role['farm_role_id'], Scalr_Db_Msr::DATA_BACKUP_TIMEFRAME_END_HH, $role['settings'][Scalr_Db_Msr::DATA_BACKUP_TIMEFRAME_END_HH], 'Preferred backup window end HH is invalid', '/^([0-1][0-9])|(2[0-4])$/');
                        $this->checkString($role['farm_role_id'], Scalr_Db_Msr::DATA_BACKUP_TIMEFRAME_END_MM, $role['settings'][Scalr_Db_Msr::DATA_BACKUP_TIMEFRAME_END_MM], 'Preferred backup window end MM is invalid', '/^[0-5][0-9]$/');
                    }
                }

                if (!empty($role['settings'][Scalr_Role_Behavior::ROLE_BASE_CUSTOM_TAGS]) && PlatformFactory::isOpenstack($role['platform'])) {
                    $reservedBaseCustomTags = ['scalr-meta', 'farmid', 'role', 'httpproto', 'region', 'hash', 'realrolename', 'szr_key', 'serverid', 'p2p_producer_endpoint', 'queryenv_url', 'behaviors', 'farm_roleid', 'roleid', 'env_id', 'platform', 'server_index', 'cloud_server_id', 'cloud_location_zone', 'owner_email'];
                    $baseCustomTags = @explode("\n", $role['settings'][Scalr_Role_Behavior::ROLE_BASE_CUSTOM_TAGS]);
                    foreach ((array)$baseCustomTags as $tag) {
                        $tag = trim($tag);
                        $tagChunks = explode("=", $tag);
                        if (in_array(trim($tagChunks[0]), $reservedBaseCustomTags)) {
                            $this->setBuildError(
                                Scalr_Role_Behavior::ROLE_BASE_CUSTOM_TAGS,
                                "Avoid using Scalr-reserved metadata names.",
                                $role['farm_role_id']
                            );
                        }
                    }
                }

                if (!empty($role['settings'][Scalr_Role_Behavior::ROLE_BASE_HOSTNAME_FORMAT])) {
                    if (!preg_match('/^[\w\{\}\.-]+$/', $role['settings'][Scalr_Role_Behavior::ROLE_BASE_HOSTNAME_FORMAT])) {
                        $this->setBuildError(
                            Scalr_Role_Behavior::ROLE_BASE_HOSTNAME_FORMAT,
                            "server hostname format for role'{$dbRole->name}' should contain only [a-z0-9-] chars. First char should not be hypen.",
                            $role['farm_role_id']
                        );
                    }
                }

                if (!empty($role['settings'][Entity\FarmRoleSetting::DNS_CREATE_RECORDS])) {
                    if ($role['settings'][Entity\FarmRoleSetting::DNS_EXT_RECORD_ALIAS]) {
                        if (!preg_match('/^[\w\{\}\.-]+$/', $role['settings'][Entity\FarmRoleSetting::DNS_EXT_RECORD_ALIAS])) {
                            $this->setBuildError(
                                Entity\FarmRoleSetting::DNS_EXT_RECORD_ALIAS,
                                "ext- record alias for role '{$dbRole->name}' should contain only [A-Za-z0-9-] chars. First and last char should not be hypen.",
                                $role['farm_role_id']
                            );
                        }
                    }

                    if ($role['settings'][Entity\FarmRoleSetting::DNS_INT_RECORD_ALIAS]) {
                        if (!preg_match('/^[\w\{\}\.-]+$/', $role['settings'][Entity\FarmRoleSetting::DNS_INT_RECORD_ALIAS])) {
                            $this->setBuildError(
                                Entity\FarmRoleSetting::DNS_INT_RECORD_ALIAS,
                                "int- record alias for role '{$dbRole->name}' should contain only [A-Za-z0-9-] chars. First and last char should not by hypen.",
                                $role['farm_role_id']
                            );
                        }
                    }
                }

                // Validate Global variables
                if (! strstr($role['farm_role_id'], 'virtual_')) {
                    $farmRole = DBFarmRole::LoadByID($role['farm_role_id']);
                } else {
                    $farmRole = null;

                    if ($dbRole->isDeprecated == 1) {
                        $this->setBuildError('roleId', 'This role has been deprecated and cannot be added', $role['farm_role_id']);
                    }

                    if (!empty(($envs = $dbRole->__getNewRoleObject()->getAllowedEnvironments()))) {
                        if (!in_array($this->getEnvironmentId(), $envs)) {
                            $this->setBuildError('roleId', "You don't have access to this role", $role['farm_role_id']);
                        }
                    }
                }

                if (isset($role['storages']['configs'])) {
                    // TODO: refactor, get rid of using DBFarmRole in constructor
                    $fr = $farmRole ? $farmRole : new DBFarmRole(0);
                    foreach ($fr->getStorage()->validateConfigs($role['storages']['configs']) as $index => $message) {
                        $this->setBuildError('storages', ['message' => $message, 'invalidIndex' => $index], $role['farm_role_id']);
                        break;
                    }
                }

                $result = $farmRoleVariables->validateValues(is_array($role['variables']) ? $role['variables'] : [], $dbRole->id, $farmId, $farmRole ? $farmRole->ID : 0);
                if ($result !== TRUE)
                    $this->setBuildError('variables', $result, $role['farm_role_id']);
            }
        }

        if ($farmSettings['vpc_id']) {
            if (!$hasVpcRouter && $vpcRouterRequired) {
                $this->setBuildError(
                    Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID,
                    'You must select a VPC Router for Farm Roles launched in a Private VPC Subnet',
                    $vpcRouterRequired
                );
            }
        }

        if ($this->getContainer()->analytics->enabled) {
            if ($farmSettings['projectId']) {
                $project = $this->getContainer()->analytics->projects->get($farmSettings['projectId']);
                if (!$project) {
                    $this->setBuildError('projectId', 'Project not found', null);
                } else if ($project->ccId != $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)) {
                    $this->setBuildError('projectId', 'Invalid project identifier. Project should correspond to the Environment\'s cost center.', null);
                }
            } else {
                $this->setBuildError('projectId', 'Project field is required', null);
            }
        }


        return ($this->errors['error_count'] == 0) ? true : false;
    }

    public function xBuildAction()
    {
        $this->request->defineParams(array(
            'farmId' => array('type' => 'int'),
            'roles' => array('type' => 'json'),
            'farm' => array('type' => 'json'),
            'roleUpdate' => array('type' => 'int'),
            'launch' => array('type' => 'bool'),
        ));

        if (!$this->isFarmConfigurationValid($this->getParam('farmId'), $this->getParam('farm'), (array)$this->getParam('roles'))) {
            if ($this->errors['error_count'] != 0) {
                $this->response->failure();
                $this->response->data(array('errors' => $this->errors));
                return;
            }
        }

        $farm = $this->getParam('farm');
        $client = Client::Load($this->user->getAccountId());

        if ($this->getParam('farmId')) {
            $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
            $this->user->getPermissions()->validate($dbFarm);
            $this->request->restrictFarmAccess($dbFarm, Acl::PERM_FARMS_MANAGE);
            $dbFarm->isLocked();

            if ($this->getParam('changed') && $dbFarm->changedTime && $this->getParam('changed') != $dbFarm->changedTime) {
                $userName = 'Someone';
                $changed = explode(' ', $this->getParam('changed'));
                $changedTime = intval($changed[1]);
                try {
                    $user = new Scalr_Account_User();
                    $user->loadById($dbFarm->changedByUserId);
                    $userName = $user->getEmail();
                } catch (Exception $e) {}

                $this->response->failure();
                $this->response->data(array('changedFailure' => sprintf('%s changed this farm at %s', $userName, Scalr_Util_DateTime::convertTz($changedTime))));
                return;
            }

            $dbFarm->changedByUserId = $this->user->getId();
            $dbFarm->changedTime = microtime();
            $bNew = false;
        } else {
            $this->request->restrictFarmAccess(null, Acl::PERM_FARMS_MANAGE);
            $this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_FARMS, 1);

            $dbFarm = new DBFarm();
            $dbFarm->ClientID = $this->user->getAccountId();
            $dbFarm->EnvID = $this->getEnvironmentId();
            $dbFarm->Status = FARM_STATUS::TERMINATED;

            $dbFarm->createdByUserId = $this->user->getId();
            $dbFarm->createdByUserEmail = $this->user->getEmail();
            $dbFarm->changedByUserId = $this->user->getId();
            $dbFarm->changedTime = microtime();
            $bNew = true;
        }

        if ($this->getParam('farm')) {
            $dbFarm->Name = $this->request->stripValue($farm['name']);
            $dbFarm->RolesLaunchOrder = $farm['rolesLaunchOrder'];
            $dbFarm->Comments = $this->request->stripValue($farm['description']);
        }

        if (empty($dbFarm->Name))
            throw new Exception(_("Farm name required"));

        if ($bNew) {
            $dbFarm->teamId = is_numeric($farm['teamOwner']) && $farm['teamOwner'] > 0 ? $farm['teamOwner'] : NULL;
        } else {
            if ($dbFarm->createdByUserId == $this->user->getId() || $this->user->isAccountOwner() || $this->request->isFarmAllowed($dbFarm, Acl::PERM_FARMS_CHANGE_OWNERSHIP)) {
                if (is_numeric($farm['owner']) && $farm['owner'] != $dbFarm->createdByUserId) {
                    $user = (new Scalr_Account_User())->loadById($farm['owner']);
                    $dbFarm->createdByUserId = $user->getId();
                    $dbFarm->createdByUserEmail = $user->getEmail();
                    // TODO: move to subclass \Farm\Setting\OwnerHistory
                    $history = unserialize($dbFarm->GetSetting(Entity\FarmSetting::OWNER_HISTORY));
                    if (! is_array($history))
                        $history = [];
                    $history[] = [
                        'newId' => $user->getId(),
                        'newEmail' => $user->getEmail(),
                        'changedById' => $this->user->getId(),
                        'changedByEmail' => $this->user->getEmail(),
                        'dt' => date('Y-m-d H:i:s')
                    ];
                    $dbFarm->SetSetting(Entity\FarmSetting::OWNER_HISTORY, serialize($history));
                }

                $dbFarm->teamId = is_numeric($farm['teamOwner']) && $farm['teamOwner'] > 0 ? $farm['teamOwner'] : NULL;
            }
        }

        $dbFarm->save();

        $governance = new Scalr_Governance($this->getEnvironmentId());
        if (!$this->getParam('farmId') && $governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE)) {
            $dbFarm->SetSetting(Entity\FarmSetting::LEASE_STATUS, 'Active'); // for created farm
        }

        if (isset($farm['variables'])) {
            $variables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_FARM);
            $variables->setValues(is_array($farm['variables']) ? $farm['variables'] : [], 0, $dbFarm->ID, 0, '', false, true);
        }

        if (!$farm['timezone']) {
            $farm['timezone'] = date_default_timezone_get();
        }

        $dbFarm->SetSetting(Entity\FarmSetting::TIMEZONE, $farm['timezone']);
        $dbFarm->SetSetting(Entity\FarmSetting::EC2_VPC_ID, isset($farm["vpc_id"]) ? $farm['vpc_id'] : null);
        $dbFarm->SetSetting(Entity\FarmSetting::EC2_VPC_REGION, isset($farm["vpc_id"]) ? $farm['vpc_region'] : null);
        $dbFarm->SetSetting(Entity\FarmSetting::SZR_UPD_REPOSITORY, $farm[Entity\FarmSetting::SZR_UPD_REPOSITORY]);
        $dbFarm->SetSetting(Entity\FarmSetting::SZR_UPD_SCHEDULE, $farm[Entity\FarmSetting::SZR_UPD_SCHEDULE]);


        if (!$dbFarm->GetSetting(Entity\FarmSetting::CRYPTO_KEY)) {
            $dbFarm->SetSetting(Entity\FarmSetting::CRYPTO_KEY, Scalr::GenerateRandomKey(40));
        }

        if ($this->getContainer()->analytics->enabled) {
            //Cost analytics project must be set for the Farm object
            $dbFarm->setProject((!empty($farm['projectId']) ? $farm['projectId'] : null));
        }

        $virtualFarmRoles = array();
        $roles = $this->getParam('roles');

        if (!empty($roles)) {
            foreach ($roles as $role) {
                if (strpos($role['farm_role_id'], "virtual_") !== false) {
                    $dbRole = DBRole::loadById($role['role_id']);
                    $dbFarmRole = $dbFarm->AddRole($dbRole, $role['platform'], $role['cloud_location'], (int)$role['launch_index'], $role['alias']);

                    $virtualFarmRoles[$role['farm_role_id']] = $dbFarmRole->ID;
                }
            }
        }

        $usedPlatforms = array();
        $dbFarmRolesList = array();
        $newFarmRolesList = array();
        $farmRoleVariables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_FARMROLE);

        if (!empty($roles)) {
            foreach ($roles as $role) {
                if ($role['farm_role_id']) {
                    if (isset($virtualFarmRoles[$role['farm_role_id']])) {
                        $role['farm_role_id'] = $virtualFarmRoles[$role['farm_role_id']];
                    }

                    $update = true;
                    $dbFarmRole = DBFarmRole::LoadByID($role['farm_role_id']);
                    $dbRole = DBRole::loadById($dbFarmRole->RoleID);
                    $role['role_id'] = $dbFarmRole->RoleID;

                    if ($dbFarmRole->Platform == SERVER_PLATFORMS::GCE) {
                        $dbFarmRole->CloudLocation = $role['cloud_location'];
                    }
                } else { /** TODO:  Remove because will be handled with virtual_ **/
                    $update = false;
                    $dbRole = DBRole::loadById($role['role_id']);
                    $dbFarmRole = $dbFarm->AddRole($dbRole, $role['platform'], $role['cloud_location'], (int)$role['launch_index']);
                }

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::RABBITMQ))
                    $role['settings'][Entity\FarmRoleSetting::SCALING_MAX_INSTANCES] = $role['settings'][Entity\FarmRoleSetting::SCALING_MIN_INSTANCES];

                if ($update) {
                    $dbFarmRole->LaunchIndex = (int)$role['launch_index'];
                    $dbFarmRole->Alias = $role['alias'];
                    $dbFarmRole->Save();
                }

                $usedPlatforms[$role['platform']] = 1;

                $oldRoleSettings = $dbFarmRole->GetAllSettings();

                // Update virtual farm_role_id with actual value
                $scripts = (array)$role['scripting'];
                if (!empty($virtualFarmRoles)) {
                    array_walk_recursive($scripts, function(&$v, $k) use ($virtualFarmRoles) {
                        if (is_string($v))
                            $v = str_replace(array_keys($virtualFarmRoles), array_values($virtualFarmRoles), $v);
                    });

                    array_walk_recursive($role['settings'], function(&$v, $k) use ($virtualFarmRoles) {
                        if (is_string($v))
                            $v = str_replace(array_keys($virtualFarmRoles), array_values($virtualFarmRoles), $v);
                    });
                }

                $dbFarmRole->ClearSettings("chef.");

                if (!empty($role['scaling_settings']) && is_array($role['scaling_settings']))
                    foreach ($role['scaling_settings'] as $k => $v) {
                        $dbFarmRole->SetSetting($k, $v, Entity\FarmRoleSetting::TYPE_CFG);
                    }


                foreach ($role['settings'] as $k => $v)
                    $dbFarmRole->SetSetting($k, $v, Entity\FarmRoleSetting::TYPE_CFG);


                /****** Scaling settings ******/
                $scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
                $scalingManager->setFarmRoleMetrics(is_array($role['scaling']) ? $role['scaling'] : array());

                //TODO: optimize this code...
                $this->db->Execute("DELETE FROM farm_role_scaling_times WHERE farm_roleid=?",
                    array($dbFarmRole->ID)
                );

                // 5 = Time based scaling -> move to constants
                if (!empty($role['scaling'][Entity\ScalingMetric::METRIC_DATE_AND_TIME_ID])) {
                    foreach ($role['scaling'][Entity\ScalingMetric::METRIC_DATE_AND_TIME_ID] as $scal_period) {
                        $chunks = explode(":", $scal_period['id']);
                        $this->db->Execute("INSERT INTO farm_role_scaling_times SET
                            farm_roleid		= ?,
                            start_time		= ?,
                            end_time		= ?,
                            days_of_week	= ?,
                            instances_count	= ?
                        ", array(
                            $dbFarmRole->ID,
                            $chunks[0],
                            $chunks[1],
                            $chunks[2],
                            $chunks[3]
                        ));
                    }
                }
                /*****************/

                /* Add script options to databse */
                $dbFarmRole->SetScripts($scripts, (array)$role['scripting_params']);
                /* End of scripting section */

                /* Add services configuration */
                $dbFarmRole->SetServiceConfigPresets((array)$role['config_presets']);
                /* End of scripting section */

                /* Add storage configuration */
                if (isset($role['storages']['configs'])) {
                    $dbFarmRole->getStorage()->setConfigs($role['storages']['configs'], false);
                }

                $farmRoleVariables->setValues(is_array($role['variables']) ? $role['variables']: [], $dbFarmRole->GetRoleID(), $dbFarm->ID, $dbFarmRole->ID, '', false, true);

                foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
                    $behavior->onFarmSave($dbFarm, $dbFarmRole);

                /**
                 * Platform specified updates
                 */
                if ($dbFarmRole->Platform == SERVER_PLATFORMS::EC2) {
                    \Scalr\Modules\Platforms\Ec2\Helpers\EbsHelper::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
                    \Scalr\Modules\Platforms\Ec2\Helpers\EipHelper::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
                    \Scalr\Modules\Platforms\Ec2\Helpers\ElbHelper::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
                }

                if (in_array($dbFarmRole->Platform, array(SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::CLOUDSTACK))) {
                    Scalr\Modules\Platforms\Cloudstack\Helpers\CloudstackHelper::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
                }

                $dbFarmRolesList[] = $dbFarmRole;
                $newFarmRolesList[] = $dbFarmRole->ID;
            }
        }

        if (!$this->getParam('roleUpdate')) {
            /* @var $farmRole Entity\FarmRole */
            foreach (Entity\FarmRole::findByFarmId($dbFarm->ID) as $farmRole) {
                if (!in_array($farmRole->id, $newFarmRolesList)) {
                    $farmRole->delete();
                }
            }
        }

        $dbFarm->save();

        if (!$client->GetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED))
            $client->SetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED, time());

        if ($this->request->isFarmAllowed($dbFarm, Acl::PERM_FARMS_LAUNCH_TERMINATE) && $this->getParam('launch')) {
            $this->user->getPermissions()->validate($dbFarm);

            $dbFarm->isLocked();

            Scalr::FireEvent($dbFarm->ID, new FarmLaunchedEvent(true, $this->user->id));

            $this->response->success('Farm successfully saved and launched');
        } else {
            $this->response->success('Farm successfully saved');
        }
        $this->response->data(array('farmId' => $dbFarm->ID, 'isNewFarm' => $bNew));
    }

    public function getFarm2($farmId)
    {
        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);

        $farmRoles = array();

        $variables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_FARM);
        $farmRoleVariables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_FARMROLE);

        foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {

            $scripts = $this->db->GetAll("
                SELECT farm_role_scripts.*, scripts.name, scripts.os
                FROM farm_role_scripts
                LEFT JOIN scripts ON scripts.id = farm_role_scripts.scriptid
                WHERE farm_roleid=? AND issystem='1'
            ", array(
                $dbFarmRole->ID
            ));
            $scriptsObject = array();
            foreach ($scripts as $script) {
                if (!empty($script['scriptid']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_SCALR ||
                    !empty($script['script_path']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_LOCAL ||
                    !empty($script['params']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_CHEF)
                {
                    $s = array(
                        'script_type'   => $script['script_type'],
                        'script_id'		=> (int) $script['scriptid'],
                        'script'		=> $script['name'],
                        'os'		    => $script['os'],
                        'params'		=> unserialize($script['params']),
                        'target'		=> $script['target'],
                        'version'		=> (int) $script['version'],
                        'timeout'		=> $script['timeout'],
                        'isSync'		=> (int) $script['issync'],
                        'order_index'	=> $script['order_index'],
                        'event' 		=> $script['event_name'],
                        'script_path' 	=> $script['script_path'],
                        'run_as' 	    => $script['run_as'],
                    );

                    if ($script['target'] == Script::TARGET_BEHAVIORS || $script['target'] == Script::TARGET_ROLES || $script['target'] == Script::TARGET_FARMROLES) {
                        switch ($script['target']) {
                            case $script['target'] == Script::TARGET_ROLES:
                                $varName = 'target_roles';
                                break;
                            case $script['target'] == Script::TARGET_FARMROLES:
                                $varName = 'target_farmroles';
                                break;
                            case $script['target'] == Script::TARGET_BEHAVIORS:
                                $varName = 'target_behaviors';
                                break;
                        }
                        $s[$varName] = array();
                        $r = $this->db->GetAll("SELECT `target` FROM farm_role_scripting_targets WHERE farm_role_script_id = ?", array($script['id']));
                        foreach ($r as $v)
                            array_push($s[$varName], $v['target']);
                    }

                    $scriptsObject[] = $s;
                }
            }

            //Scripting params
            $scriptingParams = $this->db->Execute("
                SELECT * FROM farm_role_scripting_params
                WHERE farm_role_id = ? AND farm_role_script_id = '0'
            ", array($dbFarmRole->ID));
            $sParams = array();
            while ($p = $scriptingParams->FetchRow()){
                $sParams[] = array('hash' => $p['hash'], 'role_script_id' => $p['role_script_id'], 'params' => unserialize($p['params']));
            }


            $scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
            $scaling = array();
            foreach ($scalingManager->getFarmRoleMetrics() as $farmRoleMetric)
                $scaling[$farmRoleMetric->metricId] = $farmRoleMetric->getSettings();

            $dbPresets = $this->db->GetAll("SELECT * FROM farm_role_service_config_presets WHERE farm_roleid=?", array($dbFarmRole->ID));
            $presets = array();
            foreach ($dbPresets as $preset)
                $presets[$preset['behavior']] = $preset['preset_id'];

            $roleName = $dbFarmRole->GetRoleObject()->name;

            $storages = array(
                'configs' => $dbFarmRole->getStorage()->getConfigs()
            );

            foreach ($dbFarmRole->getStorage()->getVolumes() as $configKey => $config) {
                $storages['devices'][$configKey] = array();

                foreach ($config as $device) {
                    $info = array(
                        'farmRoleId' => $device->farmRoleId,
                        'placement' => $device->placement,
                        'serverIndex' => $device->serverIndex,
                        'storageId' => $device->storageId,
                        'storageConfigId' => $device->storageConfigId,
                        'status' => $device->status
                    );

                    try {
                        $server = DBServer::LoadByFarmRoleIDAndIndex($device->farmRoleId, $device->serverIndex);
                        if ($server->status != SERVER_STATUS::TERMINATED) {
                            $info['serverId'] = $server->serverId;
                            $info['serverInstanceId'] = $server->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
                        }
                    } catch (Exception $e) {
                        $this->response->debugException($e);
                    }

                    $storages['devices'][$configKey][] = $info;
                }
            }

            $image = $dbFarmRole->GetRoleObject()->__getNewRoleObject()->getImage($dbFarmRole->Platform, $dbFarmRole->CloudLocation);
            $securityGroups = $this->getInitialSecurityGroupsList($dbFarmRole);

            $farmRoles[] = array(
                'farm_role_id'	=> $dbFarmRole->ID,
                'alias'         => $dbFarmRole->Alias ? $dbFarmRole->Alias : $dbFarmRole->GetRoleObject()->name,
                'role_id'		=> $dbFarmRole->RoleID,
                'platform'		=> $dbFarmRole->Platform,
                //todo: check&remove 4 deprecated os fields below
                'os'			=> $dbFarmRole->GetRoleObject()->getOs()->name,
                'os_family'     => $dbFarmRole->GetRoleObject()->getOs()->family,
                'os_generation' => $dbFarmRole->GetRoleObject()->getOs()->generation,
                'os_version'    => $dbFarmRole->GetRoleObject()->getOs()->version,

                'osId'          => $dbFarmRole->GetRoleObject()->getOs()->id,
                'generation'	=> $dbFarmRole->GetRoleObject()->generation,
                'group'			=> $dbFarmRole->GetRoleObject()->getCategoryName(),
                'cat_id'        => $dbFarmRole->GetRoleObject()->catId,
                'name'			=> $roleName,
                'behaviors'		=> implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
                'scripting'		=> $scriptsObject,
                'scripting_params' => $sParams,
                'settings'		=> $dbFarmRole->GetAllSettings(),
                'cloud_location'=> $dbFarmRole->CloudLocation,
                'launch_index'	=> (int)$dbFarmRole->LaunchIndex,
                'scaling'		=> $scaling,
                'config_presets'=> $presets,
                'image'         => $image->getImage(),
                'storages'      => $storages,
                'variables'     => $farmRoleVariables->getValues($dbFarmRole->GetRoleID(), $dbFarm->ID, $dbFarmRole->ID),
                'running_servers' => $dbFarmRole->GetRunningInstancesCount(),
                'suspended_servers' => $dbFarmRole->GetSuspendedInstancesCount(),
                'security_groups' => $securityGroups,
                'hourly_rate'     => $this->getInstanceTypeHourlyRate($dbFarmRole->Platform, $dbFarmRole->CloudLocation, $dbFarmRole->getInstanceType(), $dbFarmRole->GetRoleObject()->getOs()->family)
            );
        }

        $vpc = array();
        if ($dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_ID)) {
            $vpc = array(
                'id'        => $dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_ID),
                'region'    => $dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_REGION)
            );
        }

        $farmOwnerEditable = $this->user->isAccountOwner() || $this->request->isFarmAllowed($dbFarm, Acl::PERM_FARMS_CHANGE_OWNERSHIP);

        return array(
            'farm' => array(
                'name' => $dbFarm->Name,
                'description' => $dbFarm->Comments,
                'rolesLaunchOrder' => $dbFarm->RolesLaunchOrder,
                'timezone' => $dbFarm->GetSetting(Entity\FarmSetting::TIMEZONE),
                'variables' => $variables->getValues(0, $dbFarm->ID),
                'vpc' => $vpc,
                'status' => $dbFarm->Status,
                'hash' => $dbFarm->Hash,
                'owner' => $farmOwnerEditable ? $dbFarm->createdByUserId : $dbFarm->createdByUserEmail,
                'ownerEditable' => $farmOwnerEditable,
                'teamOwner' => $farmOwnerEditable ? $dbFarm->teamId : ($dbFarm->teamId ? (new Scalr_Account_Team())->loadById($dbFarm->teamId)->name : ''),
                'teamOwnerEditable' => $farmOwnerEditable,
                'launchPermission' => $this->request->isFarmAllowed($dbFarm, Acl::PERM_FARMS_LAUNCH_TERMINATE),

                Entity\FarmSetting::SZR_UPD_REPOSITORY => $dbFarm->GetSetting(Entity\FarmSetting::SZR_UPD_REPOSITORY),
                Entity\FarmSetting::SZR_UPD_SCHEDULE => $dbFarm->GetSetting(Entity\FarmSetting::SZR_UPD_SCHEDULE)
            ),
            'roles' => $farmRoles,
            'lock' => $dbFarm->isLocked(false),
            'changed' => $dbFarm->changedTime
        );
    }

    private function getInitialSecurityGroupsList(DBFarmRole $dbFarmRole)
    {
        $additionalSecurityGroups = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_SG_LIST);
        $append = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_SG_LIST_APPEND);
        if ($append === null || $append == 1 || ($append == 0 && $additionalSecurityGroups === null)) {
            $retval = array('default', \Scalr::config('scalr.aws.security_group_name'));
            if (!$dbFarmRole->GetFarmObject()->GetSetting(Entity\FarmSetting::EC2_VPC_ID)) {
                $retval = array_merge($retval, array(
                    "scalr-farm.{$dbFarmRole->FarmID}",
                    "scalr-role.{$dbFarmRole->ID}"
                ));
            }
        } else {
            $retval = array();
        }

        if ($additionalSecurityGroups !== null) {
            $additionalSecurityGroups = explode(",", $additionalSecurityGroups);
            foreach ($additionalSecurityGroups as $sg) {
                $sg = trim($sg);
                if ($sg)
                    array_push($retval, $sg);
            }
        }

        return $retval;
    }

    /**
     * @param  int  $roleId
     */
    public function xGetScriptsAction($roleId)
    {
        $role = Role::findPk($roleId);
        if (!$role) {
            $this->response->failure('Role not found');
            return;
        }

        $this->checkPermissions($role);

        $data = \Scalr\Model\Entity\Script::getScriptingData($this->user->getAccountId(), $this->getEnvironmentId());
        $data['roleScripts'] = $role->getScripts();

        $this->response->data($data);
    }

    /**
     * @param  int  $roleId
     */
    public function xGetRoleChefSettingsAction($roleId)
    {
        /* @var $role Role */
        $role = Role::findPk($roleId);
        if (!$role) {
            $this->response->failure('Role not found');
            return;
        }

        $this->checkPermissions($role);

        $properties = [];
        foreach (RoleProperty::find([['roleId' => $role->id], ['name' => ['$like' => 'chef.%']]]) as $prop) {
            /* @var $prop RoleProperty */
            $properties[$prop->name] = $prop->value;
        }

        $this->response->data(['chef' => $properties]);
    }

    /**
     * @param string $platform
     * @param string $cloudLocation
     * @param string $instanceType
     * @param string $osFamily
     * @return float
     */
    public function xGetInstanceTypeHourlyRateAction($platform, $cloudLocation, $instanceType, $osFamily)
    {
        $this->response->data(['hourly_rate' => $this->getInstanceTypeHourlyRate($platform, $cloudLocation, $instanceType, $osFamily)]);
    }

    /**
     * Gets instance type houry rate
     *
     * @param string $platform
     * @param string $cloudLocation
     * @param string $instanceType
     * @param string $osFamily
     * @return float
     */
    private function getInstanceTypeHourlyRate($platform, $cloudLocation, $instanceType, $osFamily)
    {
        $rate = 0;
        if ($this->getContainer()->analytics->enabled) {
            $env = $this->getEnvironment();
            $applied = new DateTime('now', new DateTimeZone('UTC'));
            $platformModule = PlatformFactory::NewPlatform($platform);
            $osType = $osFamily === 'windows' ? PriceEntity::OS_WINDOWS : PriceEntity::OS_LINUX;

            $roleInstancePricing = $env->analytics->prices->getActualPrices($platform, $cloudLocation, $platformModule->getEndpointUrl($env), $applied, 0, $instanceType, $osType);
            $pricing = reset($roleInstancePricing);

            $rate = $pricing instanceof PriceEntity ? $pricing->cost : 0;
        }
        return $rate;
    }

    /**
     * Validate integer and set builder error if value is not valid
     *
     * @param string $farmRoleId
     * @param string $name
     * @param string $value
     * @param string $title optional
     * @param string $min optional
     * @param string $max optional
     * @return integer|false Value or false if value is invalid.
     */
    private function checkInteger($farmRoleId, $name, $value, $title = null, $min = null, $max = null)
    {
        $result = false;
        if (ctype_digit((string)$value)) {
            $result = (int)$value;
            if (!is_null($min) && $result < $min || !is_null($max) && $result > $max) {
                $result = false;
            }
        }

        if ($result === false) {
            $errorMessage = ' must be an integer';
            if (!is_null($min) && !is_null($max)) {
                $errorMessage .= ' between ' . $min . ' and ' . $max;
            } elseif (!is_null($min)) {
                $errorMessage .= ' greater than or equal to ' . $min;
            } elseif (!is_null($max)) {
                $errorMessage .= ' less than or equal to ' . $max;
            }
            if (is_array($title)) {
                $title['message'] = 'Value' . $errorMessage;
            } else {
                $title = (!is_null($title) ? $title : 'Value') . $errorMessage;
            }
            $this->setBuildError(
                $name,
                $title,
                $farmRoleId
            );
        }

        return $result;
    }

    /**
     * Validate string using regular expression
     *
     * @param string $farmRoleId
     * @param string $name
     * @param string $value
     * @param string $errorMessage
     * @param string $regexp
     * @return string|false Value or false if value is invalid.
     */
    private function checkString($farmRoleId, $name, $value, $errorMessage, $regexp)
    {
        if (!preg_match($regexp, $value)) {
            $result = false;
            $this->setBuildError(
                $name,
                $errorMessage ? $errorMessage : 'Invalid value',
                $farmRoleId
            );
        } else {
            $result = $value;
        }

        return $result;

    }

}
