<?php
use Scalr\Acl\Acl;
use Scalr\Farm\Role\FarmRoleStorage;
use Scalr\Farm\Role\FarmRoleStorageConfig;
use Scalr\Farm\Role\FarmRoleStorageException;
use Scalr\Logger\AuditLog\Documents\FarmRoleSettingsDocument;
use Scalr\Logger\AuditLog\AuditLogTags;

class Scalr_UI_Controller_Farms_Builder extends Scalr_UI_Controller
{

    var $errors = array('farm' => array(), 'roles' => array(), 'error_count' => 0, 'first_error' => '');

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_FARMS);
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

    private function setBuildError($setting, $message, $roleId = null, $isFinal = false)
    {
        $this->errors['error_count']++;
        if ($roleId == null)
            $this->errors['farm'][$setting] = $message;
        else
            $this->errors['roles'][$roleId][$setting] = $message;
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

        $farmVariables = new Scalr_Scripting_GlobalVariables($this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARM);
        $farmRoleVariables = new Scalr_Scripting_GlobalVariables($this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);

        if ($farmSettings['variables']) {
            $result = $farmVariables->validateValues($farmSettings['variables'], 0, $farmId);
            if ($result !== TRUE)
                $this->setBuildError('variables', $result);
        }

        if (!empty($roles)) {
            $cloudFoundryStack = array();
            $hasVpcRouter = false;
            $nginxFound = 0;

            foreach ($roles as $role) {
                $dbRole = DBRole::loadById($role['role_id']);

                if (!$dbRole->getImageId($role['platform'], $role['cloud_location'])) {
                    $this->setBuildError(
                        $dbRole->name,
                        sprintf(_("Role '%s' is not available in %s on %s"),
                            $dbRole->name, $role['platform'], $role['cloud_location']
                        ),
                        $role['farm_role_id']
                    );
                }

                if ($role['alias']) {
                    if (!preg_match("/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/si", $role['alias']))
                        $this->setBuildError(
                            'alias',
                            sprintf(_("Alias for role '%s' should start and end with letter or number and contain only letters, numbers and dashes."),
                                $dbRole->name, $role['platform'], $role['cloud_location']
                            ),
                            $role['farm_role_id']
                        );
                }

                // Validate deployments
                $appId = $role[Scalr_Role_Behavior::ROLE_DM_APPLICATION_ID];
                if ($appId) {
                    $application = Scalr_Dm_Application::init()->loadById($appId);
                    $this->user->getPermissions()->validate($application);

                    if (!$role[Scalr_Role_Behavior::ROLE_DM_REMOTE_PATH]) {
                        $this->setBuildError(
                            Scalr_Role_Behavior::ROLE_DM_REMOTE_PATH,
                            sprintf("Remote path required for deployment on role '%s'", $dbRole->name),
                            $role['farm_role_id']
                        );
                    }
                }

                //-- CloudFoundryStuff
                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER))
                    $cloudFoundryStack[ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER] = true;
                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_DEA))
                    $cloudFoundryStack[ROLE_BEHAVIORS::CF_DEA] = true;
                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_HEALTH_MANAGER))
                    $cloudFoundryStack[ROLE_BEHAVIORS::CF_HEALTH_MANAGER] = true;
                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_ROUTER))
                    $cloudFoundryStack[ROLE_BEHAVIORS::CF_ROUTER] = true;
                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::CF_SERVICE))
                    $cloudFoundryStack[ROLE_BEHAVIORS::CF_SERVICE] = true;

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER))
                    $hasVpcRouter = true;


                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::NGINX))
                    $nginxFound++;
                 //-- End CloudFoundry stuff

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::RABBITMQ)) {
                    $role['settings'][DBFarmRole::SETTING_SCALING_MAX_INSTANCES] = $role['settings'][DBFarmRole::SETTING_SCALING_MIN_INSTANCES];

                    $role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] = (int)$role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO];
                    if ($role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] < 1 || $role['settings'][Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO] > 100) {
                        $this->setBuildError(
                            Scalr_Role_Behavior_RabbitMQ::ROLE_NODES_RATIO,
                            sprintf("Nodes ratio for RabbitMq role '%s' should be between 1 and 100", $dbRole->name),
                            $role['farm_role_id']
                        );
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

                /* Validate scaling */
                $minCount = (int)$role['settings'][DBFarmRole::SETTING_SCALING_MIN_INSTANCES];
                if (!$minCount && $minCount != 0)
                    $minCount = 1;

                if ($minCount < 0 || $minCount > 400) {
                    $this->setBuildError(
                        DBFarmRole::SETTING_SCALING_MIN_INSTANCES,
                        sprintf(_("Min instances for '%s' must be a number between 1 and 400"), $dbRole->name),
                        $role['farm_role_id']
                    );
                }

                $maxCount = (int)$role['settings'][DBFarmRole::SETTING_SCALING_MAX_INSTANCES];
                if (!$maxCount)
                    $maxCount = 1;

                if ($maxCount < 1 || $maxCount > 400) {
                    $this->setBuildError(
                        DBFarmRole::SETTING_SCALING_MAX_INSTANCES,
                        sprintf(_("Max instances for '%s' must be a number between 1 and 400"), $dbRole->name),
                        $role['farm_role_id']
                    );
                }

                if ($maxCount < $minCount) {
                    $this->setBuildError(
                        DBFarmRole::SETTING_SCALING_MAX_INSTANCES,
                        sprintf(_("Max instances should be greater or equal than Min instances for role '%s'"), $dbRole->name),
                        $role['farm_role_id']
                    );
                }

                if (isset($role['settings'][DBFarmRole::SETTING_SCALING_POLLING_INTERVAL]) && $role['settings'][DBFarmRole::SETTING_SCALING_POLLING_INTERVAL] > 0)
                    $polling_interval = (int)$role['settings'][DBFarmRole::SETTING_SCALING_POLLING_INTERVAL];
                else
                    $polling_interval = 2;


                if ($polling_interval < 1 || $polling_interval > 50) {
                    $this->setBuildError(
                        DBFarmRole::SETTING_SCALING_POLLING_INTERVAL,
                        sprintf(_("Polling interval for role '%s' must be a number between 1 and 50"), $dbRole->name),
                        $role['farm_role_id']
                    );
                }

                /** Validate platform specified settings **/
                switch($role['platform']) {
                    case SERVER_PLATFORMS::EC2:
                	    if ($dbRole->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
                	        if ($role['settings'][DBFarmRole::SETTING_MYSQL_DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::EBS) {

                	            if ($dbRole->generation != 2) {
                	                if ($role['settings'][DBFarmRole::SETTING_AWS_AVAIL_ZONE] == "" ||
                	                $role['settings'][DBFarmRole::SETTING_AWS_AVAIL_ZONE] == "x-scalr-diff" ||
                	                stristr($role['settings'][DBFarmRole::SETTING_AWS_AVAIL_ZONE], 'x-scalr-custom')
                	                ) {
                	                    $this->setBuildError(
                	                        DBFarmRole::SETTING_AWS_AVAIL_ZONE,
                	                        sprintf(_("Requirement for EBS MySQL data storage is specific 'Placement' parameter for role '%s'"), $dbRole->name),
                	                        $role['farm_role_id']
                	                    );
                	                }
                	            }
                	        }
                	    }

                        if ($dbRole->getDbMsrBehavior())
                        {
                            if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::EPH) {
                                if (!$role['settings'][Scalr_Db_Msr::DATA_STORAGE_EPH_DISK] && !$role['settings'][Scalr_Db_Msr::DATA_STORAGE_EPH_DISKS]) {
                                    $this->setBuildError(
                                        Scalr_Db_Msr::DATA_STORAGE_EPH_DISK,
                                        sprintf(_("Ephemeral disk settings is required for role '%s'"), $dbRole->name),
                                        $role['farm_role_id']
                                    );
                                }
                            }

                	        if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::LVM) {
                	            if (!$role['settings'][Scalr_Role_DbMsrBehavior::ROLE_DATA_STORAGE_LVM_VOLUMES]) {
                	                $this->setBuildError(
                	                    Scalr_Role_DbMsrBehavior::ROLE_DATA_STORAGE_LVM_VOLUMES,
                	                    sprintf(_("Ephemeral disks settings is required for role '%s'"), $dbRole->name),
                	                    $role['farm_role_id']
                	                );
                	            }
                	        }

                	        if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::RAID_EBS) {
                	            if (!$this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_RAID)) {
                	                $this->setBuildError(
                	                    Scalr_Db_Msr::DATA_STORAGE_ENGINE,
                	                    'RAID arrays are not available for your pricing plan. <a href="#/billing">Please upgrade your account to be able to use this feature.</a>',
                	                    $role['farm_role_id']
                	                );
                	            }
                	        }

                	        if ($role['settings'][Scalr_Db_Msr::DATA_STORAGE_FSTYPE] && $role['settings'][Scalr_Db_Msr::DATA_STORAGE_FSTYPE] != 'ext3') {
                	            if (!$this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_MFS)) {
                	                $this->setBuildError(
                	                    Scalr_Db_Msr::DATA_STORAGE_ENGINE,
                	                    'Only ext3 filesystem available for your pricing plan. <a href="#/billing">Please upgrade your account to be able to use other filesystems.</a>',
                	                    $role['farm_role_id']
                	                );
                	            }
                	        }
                        }

                        if ($dbRole->hasBehavior(ROLE_BEHAVIORS::MONGODB)) {
                            if ($role['settings'][Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::RAID_EBS) {
                                if (!$this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_RAID)) {
                                    $this->setBuildError(
                                        Scalr_Role_Behavior_MongoDB::ROLE_DATA_STORAGE_ENGINE,
                                        'RAID arrays are not available for your pricing plan. <a href="#/billing">Please upgrade your account to be able to use this feature.</a>',
                                        $role['farm_role_id']
                                    );
                                }
                            }
                        }

                	    if ($role['settings'][DBFarmRole::SETTING_AWS_AVAIL_ZONE] == 'x-scalr-custom=') {
                	        $this->setBuildError(
                	            DBFarmRole::SETTING_AWS_AVAIL_ZONE,
                	            sprintf(_("Availability zone for role \"%s\" should be selected"), $dbRole->name),
                	            $role['farm_role_id']
                	        );
                	    }

                        break;

                	case SERVER_PLATFORMS::CLOUDSTACK:
                	    if (!$role['settings'][DBFarmRole::SETTING_CLOUDSTACK_SERVICE_OFFERING_ID]) {
                	        $this->setBuildError(
                	            DBFarmRole::SETTING_CLOUDSTACK_SERVICE_OFFERING_ID,
                	            sprintf(_("Service offering for '%s' cloudstack role should be selected on 'Cloudstack settings' tab"), $dbRole->name),
                	            $role['farm_role_id']
                	        );
                	    }
                	    break;

                	case SERVER_PLATFORMS::RACKSPACE:
                	    if (!$role['settings'][DBFarmRole::SETTING_RS_FLAVOR_ID]) {
                	        $this->setBuildError(
                	            DBFarmRole::SETTING_CLOUDSTACK_SERVICE_OFFERING_ID,
                	            sprintf(_("Flavor for '%s' rackspace role should be selected on 'Placement and type' tab"), $dbRole->name),
                	            $role['farm_role_id']
                	        );
                	    }
                	    break;
                }

                if ($role['settings'][DBFarmRole::SETTING_DNS_CREATE_RECORDS]) {
                    if ($role['settings'][DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS]) {
                        if (!preg_match("/^[A-Za-z0-9-%_]+$/si", $role['settings'][DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS])) {
                            $this->setBuildError(
                                DBFarmRole::SETTING_DNS_EXT_RECORD_ALIAS,
                                "ext-%rolename% record alias for role '{$dbRole->name}' should contain only [A-Za-z0-9-] chars. First and last char should not by hypen.",
                                $role['farm_role_id']
                            );
                        }
                    }

                    if ($role['settings'][DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS]) {
                        if (!preg_match("/^[A-Za-z0-9-%_]+$/si", $role['settings'][DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS])) {
                            $this->setBuildError(
                                DBFarmRole::SETTING_DNS_INT_RECORD_ALIAS,
                                "int-%rolename% record alias for role '{$dbRole->name}' should contain only [A-Za-z0-9-] chars. First and last char should not by hypen.",
                                $role['farm_role_id']
                            );
                        }
                    }
                }

                //DEPRECATED
                $rParams = $dbRole->getParameters();
                if (count($rParams) > 0 && strpos($role['farm_role_id'], 'virtual_') === false) {
                    if (empty($role['params'])) {
                        try {
                            $dbFarmRole = DBFarmRole::LoadByID($role['farm_role_id']);
                            foreach ($rParams as $param) {
                                $farmRoleOption = $this->db->GetRow("SELECT id, value FROM farm_role_options WHERE farm_roleid=? AND `hash`=? LIMIT 1", array($dbFarmRole->ID, $param['hash']));
                                if ($farmRoleOption['id'])
                                    $value = $farmRoleOption['value'];

                                $role['params'][$param['hash']] = $value;
                            }
                        } catch (Exception $e) {}
                    }
                }

                //Validate role parameters
                foreach ($rParams as $p) {
                    if ($p['required'] && $role['params'][$p['hash']] == "" && !$p['defval']) {
                        $this->setBuildError(
                            $p['name'],
                            "Missed required parameter '{$p['name']}' for role '{$dbRole->name}'",
                            $role['farm_role_id']
                        );
                    }
                }

                // Validate Global variables
                if (! strstr($role['farm_role_id'], 'virtual_')) {
                    $farmRole = DBFarmRole::LoadByID($role['farm_role_id']);
                } else {
                    $farmRole = null;
                }

                $result = $farmRoleVariables->validateValues($role['variables'], $dbRole->id, $farmId, $farmRole ? $farmRole->ID : 0);
                if ($result !== TRUE)
                    $this->setBuildError('variables', $result, $role['farm_role_id']);
            }
        }

        try {
            if (!empty($cloudFoundryStack)) {
                if (!$cloudFoundryStack[ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER])
                    throw new Exception("CF CloudContoller role required for CloudFoundry stack. Please add All-in-one CF or separate CCHM role to farm");

                if (!$cloudFoundryStack[ROLE_BEHAVIORS::CF_HEALTH_MANAGER])
                    throw new Exception("CF HealthManager role required for CloudFoundry stack. Please add All-in-one CF or separate CCHM role to farm");

                if (!$cloudFoundryStack[ROLE_BEHAVIORS::CF_ROUTER])
                    throw new Exception("CF Router role required for CloudFoundry stack. Please add All-in-one CF or separate CF Router role to farm");

                if (!$cloudFoundryStack[ROLE_BEHAVIORS::CF_DEA])
                    throw new Exception("CF DEA role required for CloudFoundry stack. Please add All-in-one CF or separate CF DEA role to farm");

                if (!$nginxFound)
                    throw new Exception("Nginx load balancer role required for CloudFoundry stack. Please add it to the farm");

                if ($cloudFoundryStack[ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER] > 1)
                    throw new Exception("CloudFoundry stack can work only with ONE CF CloudController role. Please leave only one CloudController role in farm");

                if ($cloudFoundryStack[ROLE_BEHAVIORS::CF_HEALTH_MANAGER] > 1)
                    throw new Exception("CloudFoundry stack can work only with ONE CF HealthManager role. Please leave only one HealthManager role in farm");

                if ($nginxFound > 1)
                    throw new Exception("CloudFoundry stack can work only with ONE nginx role. Please leave only one nginx role in farm");
            }
        } catch (Exception $e) {
            $this->setBuildError(
                'general',
                $e->getMessage(),
                null
            );
        }

        if ($farmSettings['vpc_id']) {
            $vpcRouterRequired = false;
            if (\Scalr::config('scalr.instances_connection_policy') != 'local' && !$hasVpcRouter) {
                foreach ($this->getParam('roles') as $role) {
                    if ($role['settings'][DBFarmRole::SETTING_AWS_VPC_INTERNET_ACCESS] == 'outbound-only') {
                        $vpcRouterRequired = true;
                        break;
                    }
                }

                if ($vpcRouterRequired) {
                    $this->setBuildError(
                        'general',
                        "VPC Router role required for farm that running inside VPC with roles configured for outbound-only internet access",
                        null
                    );
                }
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
            'roleUpdate' => array('type' => 'int')
        ));

        $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

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
        } else {
            $this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_FARMS, 1);

            $dbFarm = new DBFarm();
            $dbFarm->Status = FARM_STATUS::TERMINATED;

            $dbFarm->createdByUserId = $this->user->getId();
            $dbFarm->createdByUserEmail = $this->user->getEmail();
            $dbFarm->changedByUserId = $this->user->getId();
            $dbFarm->changedTime = microtime();
        }

        if ($this->getParam('farm')) {
            $dbFarm->Name = strip_tags($farm['name']);
            $dbFarm->RolesLaunchOrder = $farm['rolesLaunchOrder'];
            $dbFarm->Comments = trim(strip_tags($farm['description']));
        }

        if (empty($dbFarm->Name))
            throw new Exception(_("Farm name required"));

        $dbFarm->save();

        $governance = new Scalr_Governance($this->getEnvironmentId());
        if ($governance->isEnabled(Scalr_Governance::GENERAL_LEASE)) {
            $dbFarm->SetSetting(DBFarm::SETTING_LEASE_STATUS, 'Active');
        }

        if (isset($farm['variables'])) {
            $variables = new Scalr_Scripting_GlobalVariables($this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARM);
            $variables->setValues($farm['variables'], 0, $dbFarm->ID, 0, '', false);
        }

        if (!$farm['timezone'])
            $farm['timezone'] = date_default_timezone_get();

        $dbFarm->SetSetting(DBFarm::SETTING_TIMEZONE, $farm['timezone']);
        $dbFarm->SetSetting(DBFarm::SETTING_EC2_VPC_ID, $farm['vpc_id']);
        $dbFarm->SetSetting(DBFarm::SETTING_EC2_VPC_REGION, $farm['vpc_region']);

        if (!$dbFarm->GetSetting(DBFarm::SETTING_CRYPTO_KEY))
            $dbFarm->SetSetting(DBFarm::SETTING_CRYPTO_KEY, Scalr::GenerateRandomKey(40));

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
        $farmRoleVariables = new Scalr_Scripting_GlobalVariables($this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);

        if (!empty($roles)) {
            foreach ($roles as $role) {
                if ($role['farm_role_id']) {

                    if ($virtualFarmRoles[$role['farm_role_id']])
                        $role['farm_role_id'] = $virtualFarmRoles[$role['farm_role_id']];

                    $update = true;
                    $dbFarmRole = DBFarmRole::LoadByID($role['farm_role_id']);
                    $dbRole = DBRole::loadById($dbFarmRole->RoleID);
                    $role['role_id'] = $dbFarmRole->RoleID;

                    if ($dbFarmRole->Platform == SERVER_PLATFORMS::GCE)
                        $dbFarmRole->CloudLocation = $role['cloud_location'];

                }

                /** TODO:  Remove because will be handled with virtual_ **/
                 else {
                    $update = false;
                    $dbRole = DBRole::loadById($role['role_id']);
                    $dbFarmRole = $dbFarm->AddRole($dbRole, $role['platform'], $role['cloud_location'], (int)$role['launch_index']);
                }

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::RABBITMQ))
                    $role['settings'][DBFarmRole::SETTING_SCALING_MAX_INSTANCES] = $role['settings'][DBFarmRole::SETTING_SCALING_MIN_INSTANCES];

                if ($dbFarmRole->NewRoleID) {
                    continue;
                }

                if ($update) {
                    $dbFarmRole->LaunchIndex = (int)$role['launch_index'];
                    $dbFarmRole->Alias = $role['alias'];
                    $dbFarmRole->Save();
                }

                $usedPlatforms[$role['platform']] = 1;

                $oldRoleSettings = $dbFarmRole->GetAllSettings();

                // Update virtual farm_role_id with actual value
                $scripts = (array)$role['scripting'];
                if (count($virtualFarmRoles) > 0) {
                    array_walk_recursive($scripts, function(&$v, $k) use ($virtualFarmRoles) {
                        if (is_string($v))
                            $v = str_replace(array_keys($virtualFarmRoles), array_values($virtualFarmRoles), $v);
                    });

                    array_walk_recursive($role['settings'], function(&$v, $k) use ($virtualFarmRoles) {
                        if (is_string($v))
                            $v = str_replace(array_keys($virtualFarmRoles), array_values($virtualFarmRoles), $v);
                    });
                }

                //Audit log start
                //!TODO Enable Audit log for Farm Builder
    //             $auditLog = $this->getEnvironment()->auditLog;
    //             $docRoleSettingsBefore = new FarmRoleSettingsDocument($oldRoleSettings);
    //             $docRoleSettingsBefore['farmroleid'] = $dbFarmRole->ID;
    //             $docRoleSettings = new FarmRoleSettingsDocument(array_merge((array)$role['scaling_settings'], (array)$role['settings']));
    //             $docRoleSettings['farmroleid'] = $dbFarmRole->ID;

                $dbFarmRole->ClearSettings("chef.");

                if (!empty($role['scaling_settings']) && is_array($role['scaling_settings']))
                    foreach ($role['scaling_settings'] as $k => $v) {
                        $dbFarmRole->SetSetting($k, $v, DBFarmRole::TYPE_CFG);
                    }


                foreach ($role['settings'] as $k => $v)
                    $dbFarmRole->SetSetting($k, $v, DBFarmRole::TYPE_CFG);

    //             $auditLog->log('Farm has been saved', array(AuditLogTags::TAG_UPDATE), $docRoleSettings, $docRoleSettingsBefore);
    //             unset($docRoleSettings);
    //             unset($docRoleSettingsBefore);
                //Audit log finish

                /****** Scaling settings ******/
                $scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
                $scalingManager->setFarmRoleMetrics(is_array($role['scaling']) ? $role['scaling'] : array());

                //TODO: optimize this code...
                $this->db->Execute("DELETE FROM farm_role_scaling_times WHERE farm_roleid=?",
                    array($dbFarmRole->ID)
                );

                // 5 = Time based scaling -> move to constants
                if ($role['scaling'][5]) {
                    foreach ($role['scaling'][5] as $scal_period) {
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

                /* Update role params */
                $dbFarmRole->SetParameters((array)$role['params']);
                /* End of role params management */

                /* Add script options to databse */
                $dbFarmRole->SetScripts($scripts, (array)$role['scripting_params']);
                /* End of scripting section */

                /* Add services configuration */
                $dbFarmRole->SetServiceConfigPresets((array)$role['config_presets']);
                /* End of scripting section */

                /* Add storage configuration */
                //try {
                    $dbFarmRole->getStorage()->setConfigs((array)$role['storages']['configs']);
                //} catch (FarmRoleStorageException $e) {
                //    $errors[] = array('farm_role_id' => 1, 'tab' => 'storage', 'error' => $e->getMessage());
                //}

                $farmRoleVariables->setValues($role['variables'], $dbFarmRole->GetRoleID(), $dbFarm->ID, $dbFarmRole->ID, '', false);

                Scalr_Helpers_Dns::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);

                foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
                    $behavior->onFarmSave($dbFarm, $dbFarmRole);

                /**
                 * Platfrom specified updates
                 */
                if ($dbFarmRole->Platform == SERVER_PLATFORMS::EC2) {
                    Modules_Platforms_Ec2_Helpers_Ebs::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
                    Modules_Platforms_Ec2_Helpers_Eip::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
                    Modules_Platforms_Ec2_Helpers_Elb::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
                }

                if (in_array($dbFarmRole->Platform, array(SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::CLOUDSTACK))) {
                    Modules_Platforms_Cloudstack_Helpers_Cloudstack::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $role['settings']);
                }

                $dbFarmRolesList[] = $dbFarmRole;
                $newFarmRolesList[] = $dbFarmRole->ID;
            }
        }

        if (!$this->getParam('roleUpdate')) {
            foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {
                if (!$dbFarmRole->NewRoleID && !in_array($dbFarmRole->ID, $newFarmRolesList))
                    $dbFarmRole->Delete();
            }
        }

        if ($usedPlatforms[SERVER_PLATFORMS::CLOUDSTACK])
            Modules_Platforms_Cloudstack_Helpers_Cloudstack::farmSave($dbFarm, $dbFarmRolesList);

        if ($usedPlatforms[SERVER_PLATFORMS::EC2])
            Modules_Platforms_Ec2_Helpers_Ec2::farmSave($dbFarm, $dbFarmRolesList);

        if ($usedPlatforms[SERVER_PLATFORMS::EUCALYPTUS])
            Modules_Platforms_Eucalyptus_Helpers_Eucalyptus::farmSave($dbFarm, $dbFarmRolesList);

        $dbFarm->save();

        if (!$client->GetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED))
            $client->SetSettingValue(CLIENT_SETTINGS::DATE_FARM_CREATED, time());

        $this->response->success('Farm successfully saved');
        $this->response->data(array('farmId' => $dbFarm->ID));
    }

    public function getFarm($farmId)
    {
        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);

        $farmRoleId = $this->getParam('farmRoleId');
        $farmRoles = array();

        $variables = new Scalr_Scripting_GlobalVariables($this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARM);
        $farmRoleVariables = new Scalr_Scripting_GlobalVariables($this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);

        foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {
            if ($farmRoleId && $farmRoleId != $dbFarmRole->ID)
                continue;

            $scripts = $this->db->GetAll("
                SELECT farm_role_scripts.*, scripts.name
                FROM farm_role_scripts
                INNER JOIN scripts ON scripts.id = farm_role_scripts.scriptid
                WHERE farm_roleid=? AND issystem='1'
            ", array(
                $dbFarmRole->ID
            ));
            $scriptsObject = array();
            foreach ($scripts as $script) {
                $s = array(
                    'script_id'		=> $script['scriptid'],
                    'script_path'	=> $script['script_path'],
                    'script'		=> $script['name'],
                    'params'		=> unserialize($script['params']),
                    'target'		=> $script['target'],
                    'version'		=> $script['version'],
                    'timeout'		=> $script['timeout'],
                    'issync'		=> $script['issync'],
                    'order_index'	=> $script['order_index'],
                    'event' 		=> $script['event_name']
                );

                if ($script['target'] == Scalr_Script::TARGET_BEHAVIORS || $script['target'] == Scalr_Script::TARGET_ROLES) {
                    $varName = ($script['target'] == Scalr_Script::TARGET_ROLES) ? 'target_roles' : 'target_behaviors';
                    $s[$varName] = array();
                    $r = $this->db->GetAll("SELECT `target` FROM farm_role_scripting_targets WHERE farm_role_script_id = ?", array($script['id']));
                    foreach ($r as $v)
                        array_push($s[$varName], $v['target']);
                }

                $scriptsObject[] = $s;
            }

            //Scripting params
            $scriptingParams = $this->db->Execute("SELECT * FROM farm_role_scripting_params WHERE farm_role_id = ? AND farm_role_script_id = '0'", array($dbFarmRole->ID));
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

            if ($dbFarmRole->NewRoleID) {
                $roleName = DBRole::loadById($dbFarmRole->NewRoleID)->name;
                $isBundling = true;
            } else {
                $roleName = $dbFarmRole->GetRoleObject()->name;
                $isBundling = false;
            }

            $imageDetails = $dbFarmRole->GetRoleObject()->getImageDetails($dbFarmRole->Platform, $dbFarmRole->CloudLocation);

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
                        if ($server->status != SERVER_STATUS::TERMINATED && $server->status != SERVER_STATUS::TROUBLESHOOTING) {
                            $info['serverId'] = $server->serverId;
                            $info['serverInstanceId'] = $server->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
                        }
                    } catch (Exception $e) {
                        $this->response->varDump($e->getMessage());
                        $this->response->varDump($e->getTraceAsString());
                    }

                    $storages['devices'][$configKey][] = $info;
                }
            }

            $imageInfo = $dbFarmRole->GetRoleObject()->getImageDetails($dbFarmRole->Platform, $dbFarmRole->CloudLocation);
            $architecture = $imageInfo['architecture'];

            $farmRoles[] = array(
                'farm_role_id'	=> $dbFarmRole->ID,
                'alias'         => $dbFarmRole->Alias ? $dbFarmRole->Alias : $dbFarmRole->GetRoleObject()->name,
                'role_id'		=> $dbFarmRole->RoleID,
                'platform'		=> $dbFarmRole->Platform,
                'os'			=> $dbFarmRole->GetRoleObject()->os,
                'os_family'     => $dbFarmRole->GetRoleObject()->osFamily,
                'os_generation' => $dbFarmRole->GetRoleObject()->osGeneration,
                'os_version'    => $dbFarmRole->GetRoleObject()->osVersion,
                'generation'	=> $dbFarmRole->GetRoleObject()->generation,
                'group'			=> $dbFarmRole->GetRoleObject()->getCategoryName(),
                'cat_id'        => $dbFarmRole->GetRoleObject()->catId,
                'arch'          => $architecture,
                'name'			=> $roleName,
                'is_bundle_running'	=> $isBundling,
                'behaviors'		=> implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
                'scripting'		=> $scriptsObject,
                'scripting_params' => $sParams,
                'settings'		=> $dbFarmRole->GetAllSettings(),
                'cloud_location'=> $dbFarmRole->CloudLocation,
                'launch_index'	=> (int)$dbFarmRole->LaunchIndex,
                'scaling'		=> $scaling,
                'config_presets'=> $presets,
                'tags'			=> $dbFarmRole->GetRoleObject()->getTags(),
                'storages'      => $storages,
                'variables'     => $farmRoleVariables->getValues($dbFarmRole->GetRoleID(), $dbFarm->ID, $dbFarmRole->ID)
            );
        }

        return array(
            'farm' => array(
                'name' => $dbFarm->Name,
                'description' => $dbFarm->Comments,
                'rolesLaunchOrder' => $dbFarm->RolesLaunchOrder,
                'variables' => $variables->getValues(0, $dbFarm->ID),
                'vpc_region' => '',
                'vpc_id' => '',
                'vpc_region_list' => array(),
                'services' => array()
            ),
            'roles' => $farmRoles,
            'lock' => $dbFarm->isLocked(false),
            'changed' => $dbFarm->changedTime
        );
    }

    public function getFarm2($farmId)
    {
        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);

        $farmRoleId = $this->getParam('farmRoleId');
        $farmRoles = array();

        $variables = new Scalr_Scripting_GlobalVariables($this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARM);
        $farmRoleVariables = new Scalr_Scripting_GlobalVariables($this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);

        foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {
            if ($farmRoleId && $farmRoleId != $dbFarmRole->ID)
                continue;

            $scripts = $this->db->GetAll("
                SELECT farm_role_scripts.*, scripts.name
                FROM farm_role_scripts
                LEFT JOIN scripts ON scripts.id = farm_role_scripts.scriptid
                WHERE farm_roleid=? AND issystem='1'
            ", array(
                $dbFarmRole->ID
            ));
            $scriptsObject = array();
            foreach ($scripts as $script) {
                if (!empty($script['scriptid']) || !empty($script['script_path']))
                $s = array(
                    'script_id'		=> $script['scriptid'],
                    'script'		=> $script['name'],
                    'params'		=> unserialize($script['params']),
                    'target'		=> $script['target'],
                    'version'		=> $script['version'],
                    'timeout'		=> $script['timeout'],
                    'issync'		=> $script['issync'],
                    'order_index'	=> $script['order_index'],
                    'event' 		=> $script['event_name'],
                    'script_path' 	=> $script['script_path'],
                    'run_as' 	    => $script['run_as'],
                );

                if ($script['target'] == Scalr_Script::TARGET_BEHAVIORS || $script['target'] == Scalr_Script::TARGET_ROLES) {
                    $varName = ($script['target'] == Scalr_Script::TARGET_ROLES) ? 'target_roles' : 'target_behaviors';
                    $s[$varName] = array();
                    $r = $this->db->GetAll("SELECT `target` FROM farm_role_scripting_targets WHERE farm_role_script_id = ?", array($script['id']));
                    foreach ($r as $v)
                        array_push($s[$varName], $v['target']);
                }

                $scriptsObject[] = $s;
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

            if ($dbFarmRole->NewRoleID) {
                $roleName = DBRole::loadById($dbFarmRole->NewRoleID)->name;
                $isBundling = true;
            } else {
                $roleName = $dbFarmRole->GetRoleObject()->name;
                $isBundling = false;
            }

            $imageDetails = $dbFarmRole->GetRoleObject()->getImageDetails($dbFarmRole->Platform, $dbFarmRole->CloudLocation);

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
                        if ($server->status != SERVER_STATUS::TERMINATED && $server->status != SERVER_STATUS::TROUBLESHOOTING) {
                            $info['serverId'] = $server->serverId;
                            $info['serverInstanceId'] = $server->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
                        }
                    } catch (Exception $e) {
                        $this->response->varDump($e->getMessage());
                        $this->response->varDump($e->getTraceAsString());
                    }

                    $storages['devices'][$configKey][] = $info;
                }
            }

            $imageInfo = $dbFarmRole->GetRoleObject()->getImageDetails($dbFarmRole->Platform, $dbFarmRole->CloudLocation);
            $architecture = $imageInfo['architecture'];

            $securityGroups = $this->getInitialSecurityGroupsList($dbFarmRole);

            $farmRoles[] = array(
                'farm_role_id'	=> $dbFarmRole->ID,
                'alias'         => $dbFarmRole->Alias ? $dbFarmRole->Alias : $dbFarmRole->GetRoleObject()->name,
                'role_id'		=> $dbFarmRole->RoleID,
                'platform'		=> $dbFarmRole->Platform,
                'os'			=> $dbFarmRole->GetRoleObject()->os,
                'os_family'     => $dbFarmRole->GetRoleObject()->osFamily,
                'os_generation' => $dbFarmRole->GetRoleObject()->osGeneration,
                'os_version'    => $dbFarmRole->GetRoleObject()->osVersion,
                'generation'	=> $dbFarmRole->GetRoleObject()->generation,
                'group'			=> $dbFarmRole->GetRoleObject()->getCategoryName(),
                'cat_id'        => $dbFarmRole->GetRoleObject()->catId,
                'arch'          => $architecture,
                'name'			=> $roleName,
                'is_bundle_running'	=> $isBundling,
                'behaviors'		=> implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
                'scripting'		=> $scriptsObject,
                'scripting_params' => $sParams,
                'settings'		=> $dbFarmRole->GetAllSettings(),
                'cloud_location'=> $dbFarmRole->CloudLocation,
                'launch_index'	=> (int)$dbFarmRole->LaunchIndex,
                'scaling'		=> $scaling,
                'config_presets'=> $presets,
                'tags'			=> $dbFarmRole->GetRoleObject()->getTags(),
                'storages'      => $storages,
                'variables'     => $farmRoleVariables->getValues($dbFarmRole->GetRoleID(), $dbFarm->ID, $dbFarmRole->ID),
                'running_servers' => $dbFarmRole->GetRunningInstancesCount(),
                'security_groups' => $securityGroups
            );
        }

        $vpc = array();
        if ($dbFarm->GetSetting(DBFarm::SETTING_EC2_VPC_ID)) {
            $vpc = array(
                'id'        => $dbFarm->GetSetting(DBFarm::SETTING_EC2_VPC_ID),
                'region'    => $dbFarm->GetSetting(DBFarm::SETTING_EC2_VPC_REGION)
            );
        }

        return array(
            'farm' => array(
                'name' => $dbFarm->Name,
                'description' => $dbFarm->Comments,
                'rolesLaunchOrder' => $dbFarm->RolesLaunchOrder,
                'timezone' => $dbFarm->GetSetting(DBFarm::SETTING_TIMEZONE),
                'variables' => $variables->getValues(0, $dbFarm->ID),
                'vpc' => $vpc,
                'status' => $dbFarm->Status
            ),
            'roles' => $farmRoles,
            'lock' => $dbFarm->isLocked(false),
            'changed' => $dbFarm->changedTime
        );
    }

    private function getInitialSecurityGroupsList(DBFarmRole $dbFarmRole)
    {

        $additionalSecurityGroups = $dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_SG_LIST);
        $append = $dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_SG_LIST_APPEND);
        if ($append === null || $append == 1 || ($append == 0 && $additionalSecurityGroups === null)) {
            $retval = array('default', \Scalr::config('scalr.aws.security_group_name'));
            if (!$dbFarmRole->GetFarmObject()->GetSetting(DBFarm::SETTING_EC2_VPC_ID)) {
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

    public function xGetFarmAction()
    {
        $res = $this->getFarm($this->getParam('farmId'));
        $this->response->data($res);
    }

    public function xGetScriptsAction()
    {
        $dbRole = DBRole::loadById($this->getParam('roleId'));
        if ($dbRole->origin == ROLE_TYPE::CUSTOM)
            $this->user->getPermissions()->validate($dbRole);


        $data = self::loadController('Scripts')->getScriptingData();
        $data['roleScripts'] = $dbRole->getScripts();

        $this->response->data($data);
    }
}
