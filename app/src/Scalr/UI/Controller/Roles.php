<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\Aws;
use Scalr\UI\Request\JsonData;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\RoleImage;
use Scalr\Model\Entity\Os;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\RoleProperty;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\CloudCredentialsProperty;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Model\Entity\RoleEnvironment;
use Scalr\Model\Entity\RoleCategory;

class Scalr_UI_Controller_Roles extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'roleId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return true;
    }

    /**
     * @param  int  $roleId
     * @param  string  $catId
     * @param  string  $osFamily
     * @param  string  $keyword
     * @param  string  $platform
     *
     * Get list of roles for roles library
     */
    public function xGetListAction($roleId = null, $catId = null, $osFamily = null, $keyword = null, $platform = null)
    {
        $this->request->restrictFarmDesignerAccess();

        $total = 0;
        $roles = array();

        $ec2Locations = array_keys(PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2)->getLocations($this->environment));
        $enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();
        $enabledServerPlatforms = array();
        $serverPlatforms = SERVER_PLATFORMS::GetList();

        foreach ($enabledPlatforms as $enabledPlatform) {
            $enabledServerPlatforms[$enabledPlatform] = $serverPlatforms[$enabledPlatform];
        }

        if ($platform && empty($enabledServerPlatforms[$platform])) {
            throw new Exception("Selected cloud not enabled in current environment");
        }

        $globalVars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_FARMROLE);

        $rolesSql = "
            SELECT DISTINCT r.*, rc.name cat_name
            FROM roles r
            INNER JOIN role_images ri ON ri.role_id = r.id
            INNER JOIN os ON os.id = r.os_id
            LEFT JOIN role_environments re ON re.role_id = r.id
            LEFT JOIN role_categories rc ON rc.id = r.cat_id
            WHERE r.is_deprecated = '0'
            AND os.status = ?
            AND (r.client_id IS NULL OR r.env_id = ? OR r.client_id = ? AND r.env_id IS NULL AND (re.env_id IS NULL OR re.env_id = ?))
        ";

        $args = [Os::STATUS_ACTIVE, $this->getEnvironmentId(), $this->user->getAccountId(), $this->getEnvironmentId()];

        if ($platform) {
            $rolesSql .= " AND ri.platform = ?";
            $args[] = $platform;
        } else {
            $rolesSql .= " AND ri.platform IN ('".implode("','", array_keys($enabledServerPlatforms))."')";
        }

        if ($osFamily) {
            $rolesSql .= " AND os.family = ?";
            $args[] = $osFamily;
        }

        if (in_array(SERVER_PLATFORMS::EC2, $enabledPlatforms)) {
            if (empty($ec2Locations)) {
                throw new Exception('EC2 locations list is empty');
            }
            $rolesSql .= " AND (ri.platform != ? OR ri.cloud_location IN (" . rtrim(str_repeat("?,", count($ec2Locations)), ',') . "))";
            $args[] = SERVER_PLATFORMS::EC2;
            $args = array_merge($args, $ec2Locations);
        }

        if ($catId === 'shared') {
            $rolesSql .= " AND r.is_quick_start = '1'";

            $software = [];
            $softwareOrdering = [
                'base' => 0,
                'mysql' => 10,
                'percona' => 20,
                'mariadb' => 10,
                'postgresql' => 30,
                'mongodb' => 40,
                'redis' => 50,
                'apache' => 60,
                'lamp' => 70,
                'tomcat' => 80,
                'haproxy' => 90,
                'nginx' => 100,
                'memcached' => 110,
                'rabbitmq' => 120,
                'vpcrouter' => 130
            ];
        } else {
            $rolesSql .= " AND r.generation = '2'";

            if ($catId === 'search'){
                $rolesSql .= ' AND r.name LIKE ' . $this->db->qstr('%' . trim($keyword) . '%');
                if ($roleId) {
                    $rolesSql .= ' AND r.id = ?';
                    $args[] = $roleId;
                }
            } elseif ($catId === 'recent') {
            } elseif ($catId) {
                $rolesSql .= ' AND r.cat_id = ?';
                $args[] = $catId;
            }

            $rolesSql .= ' GROUP BY r.id';

            if ($catId === 'recent') {
                $rolesSql .= ' ORDER BY r.id DESC LIMIT 10';
            }
        }

        $rolesData = $this->db->Execute($rolesSql, $args);

        foreach ($rolesData as $roleData) {
            $role = new Role();
            $role->load($roleData);
            $images = [];

            foreach ($role->fetchImagesArray() as $imagePlatform => $image) {
                if ($this->getEnvironment()->isPlatformEnabled($imagePlatform)) {
                    if ($imagePlatform == SERVER_PLATFORMS::EC2) {
                        foreach ($image as $cloudlocation => $img) {
                            if (in_array($cloudlocation, $ec2Locations)) {
                                $images[$imagePlatform][$cloudlocation] = $img;
                            }
                        }
                    } else {
                        $images[$imagePlatform] = $image;
                    }
                }
            }

            if (!empty($images)) {
                $item = [
                    'role_id'       => $role->id,
                    'name'          => $role->name,
                    'behaviors'     => $role->getBehaviors(),
                    'origin'        => $role->origin,
                    'cat_id'        => $role->catId,
                    'osId'          => $role->osId,
                    'description'   => $role->description,
                    'images'        => $images,
                    'scope'         => $role->accountId ? ($role->envId ? ScopeInterface::SCOPE_ENVIRONMENT : ScopeInterface::SCOPE_ACCOUNT) : ScopeInterface::SCOPE_SCALR,
                    'variables'     => $globalVars->getValues($role->id),
                    'cat_name'      => $roleData['cat_name'],
                ];

                if ($catId === 'shared') {
                    // Get type
                    if ($role->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER))
                        $type = 'vpcrouter';
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::APACHE) && ($role->hasBehavior(ROLE_BEHAVIORS::MYSQL2) || $role->hasBehavior(ROLE_BEHAVIORS::MYSQL) || $role->hasBehavior(ROLE_BEHAVIORS::PERCONA) || $role->hasBehavior(ROLE_BEHAVIORS::MARIADB)))
                        $type = 'lamp';
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::MYSQL2))
                        $type = 'mysql';
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::APACHE))
                        $type = 'apache';
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::TOMCAT))
                        $type = ROLE_BEHAVIORS::TOMCAT;
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::NGINX))
                        $type = 'nginx';
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::HAPROXY))
                        $type = 'haproxy';
                    elseif ($role->getDbMsrBehavior())
                        $type = $role->getDbMsrBehavior();
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::MONGODB))
                        $type = ROLE_BEHAVIORS::MONGODB;
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::RABBITMQ))
                        $type = ROLE_BEHAVIORS::RABBITMQ;
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::MEMCACHED))
                        $type = ROLE_BEHAVIORS::MEMCACHED;
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::BASE))
                        $type = ROLE_BEHAVIORS::BASE;
                    elseif ($role->hasBehavior(ROLE_BEHAVIORS::MYSQL))
                        continue;

                    $item['isScalarized'] = 1;

                    $software[$type]['roles'][] = $item;

                    $software[$type]['name'] = $type;
                    $software[$type]['ordering'] = isset($softwareOrdering[$type]) ? $softwareOrdering[$type] : 1000;
                } else {
                    $item['isQuickStart'] = $role->isQuickStart ? '1' : '0';
                    $item['isDeprecated'] = $role->isDeprecated ? '1' : '0';
                    $item['isScalarized'] = $role->isScalarized ? '1' : '0';

                    $roles[] = $item;
                }

                $total++;
            }
        }

        $this->response->data([
            'roles'     => $roles,
            'software'  => isset($software) ? array_values($software) : [],
            'total'     => $total
        ]);
    }

    /**
     * @param int $roleId
     * @param string $newRoleName
     * @throws Exception
     */
    public function xCloneAction($roleId, $newRoleName)
    {
        $this->request->restrictAccess('ROLES', 'CLONE');

        $dbRole = DBRole::loadById($roleId);
        if (!empty($dbRole->envId)) {
            $this->user->getPermissions()->validate($dbRole);
        }

        if (! Role::isValidName($newRoleName)) {
            throw new Exception(_("Role name is incorrect"));
        }

        if (Role::isNameUsed($newRoleName, $this->user->getAccountId(), $this->getEnvironmentId(true))) {
            throw new Exception('Selected role name is already used. Please select another one.');
        }

        $newRoleId = $dbRole->cloneRole($newRoleName, $this->user, $this->getEnvironmentId(true));
        $this->response->data(['role' => $this->getInfo($newRoleId, true)]);
        $this->response->success('Role successfully cloned');
    }

    /**
     * @param   JsonData    $roles
     * @throws  Exception
     * @throws  Scalr_Exception_InsufficientPermissions
     * @throws  \Scalr\Exception\ModelException
     */
    public function xRemoveAction(JsonData $roles)
    {
        $this->request->restrictAccess('ROLES', 'MANAGE');

        $errors = [];
        $processed = [];

        foreach ($roles as $id) {
            try {
                /* @var $role Role */
                $role = Role::findPk($id);
                if ($role) {
                    $this->request->checkPermissions($role, true);

                    if ($role->isUsed()) {
                        throw new Exception(sprintf(_("Role '%s' is used by at least one farm, and cannot be removed."), $role->name));
                    } else {
                        $role->delete();
                    }
                }
                $processed[] = $id;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->response->data(['processed' => $processed]);

        if (count($errors)) {
            $this->response->warning("Roles(s) successfully removed, but some errors occurred:\n" . implode("\n", $errors));
        } else {
            $this->response->success('Roles(s) successfully removed');
        }
    }

    /**
     * @param   bool        $devel
     * @param   string      $serverId
     * @throws  Exception
     */
    public function builderAction($devel = false, $serverId = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_BUILD);

        // check for environment
        $this->getEnvironmentId();

        $enabledPlatforms = self::loadController('Platforms')->getEnabledPlatforms(false);
        if (empty($enabledPlatforms)) {
            $this->response->failure('Please <a href="#/account/environments?envId='.$this->getEnvironmentId().'">configure cloud credentials</a> before using Role Builder.', true);
            return;
        }

        $platforms = array();
        foreach ($enabledPlatforms as $k => $v) {
            if (in_array($k, array(SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::EC2, SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK))) {
                $platforms[$k] = array('name' => $v);
            }
        }

        if (empty($platforms)) {
            $this->response->failure('The Role Builder does not support your enabled clouds. <br/>Please <a href="#/roles/import">Create a role from Non-Scalr server</a> instead.', true);
            return;
        }

        $images = json_decode(file_get_contents(APPPATH . '/www/storage/images' . ($devel ? '-dev' : '') . '.json'), true);
        foreach ($platforms as $k => $v) {
            if ($k == SERVER_PLATFORMS::EC2) {
                $locations = PlatformFactory::NewPlatform($k)->getLocations($this->environment);
                $ccProps = $this->getEnvironment()->keychain(SERVER_PLATFORMS::EC2)->properties;

                $enabledInstanceStore = $ccProps[CloudCredentialsProperty::AWS_CERTIFICATE] && $ccProps[CloudCredentialsProperty::AWS_PRIVATE_KEY];
                $platforms[$k]['images'] = array();
                foreach ($images[$k] as $image) {
                    if (isset($locations[$image['cloud_location']]) && ($enabledInstanceStore || $image['root_device_type'] != 'instance-store')) {
                        $platforms[$k]['images'][] = $image;
                    }
                }
            } else {
                $platforms[$k]['images'] = !empty($images[$k]) ? $images[$k] : [];
            }
        }

        $server = null;
        if ($serverId) {
            $dbServer = DBServer::LoadByID($serverId);
            $this->user->getPermissions()->validate($dbServer);

            if ($dbServer->status != SERVER_STATUS::TEMPORARY) {
                throw new Exception('Server is not in role building state');
            }

            $bundleTaskId = $this->db->GetOne(
                "SELECT id FROM bundle_tasks WHERE server_id = ? ORDER BY dtadded DESC LIMIT 1",
                array($dbServer->serverId)
            );

            $bundleTask = BundleTask::LoadById($bundleTaskId);

            $server = array(
                'serverId'      => $dbServer->serverId,
                'platform'      => $dbServer->platform,
                'bundleTaskId'  => $bundleTaskId,
                'object'        => $bundleTask->object,
                'imageId'       => $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_IMAGE_ID)
            );
        }

        $this->response->page('ui/roles/builder.js', array(
            'platforms'     => $platforms,
            'environment'   => '#/account/environments/view?envId=' . $this->getEnvironmentId(),
            'server'        => $server,
        ), array('ui/services/chef/chefsettings.js', 'ui/bundletasks/view.js'), array('ui/roles/builder.css'));
    }

    /**
     * Check whether specified OS should have EBS HVM bundle type
     *
     * @param   Os   $os Operating System object
     * @return  bool Returns true if the specified OS should have EBS HVM bundle type
     */
    private function isHvmBundleTypeOs($os)
    {
        return $os->family == 'oel' ||
               $os->family == 'rhel' ||
               $os->family == 'scientific' ||
               ($os->family == 'debian' && $os->generation == '8') ||
               ($os->family == 'centos' && $os->generation == '7');
    }

    /**
     * @param   string      $platform
     * @param   string      $architecture
     * @param   JsonData    $behaviors
     * @param   string      $name
     * @param   bool        $createImage
     * @param   string      $imageId
     * @param   string      $cloudLocation
     * @param   string      $osId
     * @param   integer     $hvm
     * @param   JsonData    $advanced
     * @param   JsonData    $chef
     * @throws  Exception
     */
    public function xBuildAction($platform, $architecture, JsonData $behaviors, $name = '', $createImage = false, $imageId, $cloudLocation, $osId, $hvm = 0, JsonData $advanced, JsonData $chef)
    {
        $this->request->restrictAccess(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_BUILD);

        if (!Role::isValidName($name)) {
            throw new Exception(_("Name is incorrect"));
        }

        if (!$createImage) {
            $this->request->restrictAccess(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);
        }

        if (!$createImage && Role::isNameUsed($name, $this->user->getAccountId(), $this->getEnvironmentId())) {
            throw new Exception('Selected role name is already used. Please select another one.');
        }

        $behaviours = implode(",", array_values($behaviors->getArrayCopy()));

        $os = Os::findPk($osId);
        if (!$os)
            throw new Exception('Operating system not found.');

        // Create server
        $creInfo = new ServerCreateInfo($platform, null, 0, 0);
        $creInfo->clientId = $this->user->getAccountId();
        $creInfo->envId = $this->getEnvironmentId();
        $creInfo->farmId = 0;
        $creInfo->SetProperties(array(
            SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR => $behaviours,
            SERVER_PROPERTIES::SZR_IMPORTING_IMAGE_ID => $imageId,
            SERVER_PROPERTIES::SZR_KEY => Scalr::GenerateRandomKey(40),
            SERVER_PROPERTIES::SZR_KEY_TYPE => SZR_KEY_TYPE::PERMANENT,
            SERVER_PROPERTIES::SZR_VESION => "0.13.0",
            SERVER_PROPERTIES::SZR_IMPORTING_MYSQL_SERVER_TYPE => "mysql",
            SERVER_PROPERTIES::SZR_DEV_SCALARIZR_BRANCH => $advanced['scalrbranch'],
            SERVER_PROPERTIES::ARCHITECTURE => $architecture,
            SERVER_PROPERTIES::SZR_IMPORTING_LEAVE_ON_FAIL => $advanced['dontterminatefailed'] == 'on' ? 1 : 0,

            SERVER_PROPERTIES::SZR_IMPORTING_CHEF_SERVER_ID => $chef['chef.server'],
            SERVER_PROPERTIES::SZR_IMPORTING_CHEF_ENVIRONMENT => $chef['chef.environment'],
            SERVER_PROPERTIES::SZR_IMPORTING_CHEF_ROLE_NAME => $chef['chef.role']
        ));

        $dbServer = DBServer::Create($creInfo, true);
        $dbServer->status = SERVER_STATUS::TEMPORARY;
        $dbServer->imageId = $imageId;
        $dbServer->save();

        //Launch server
        $launchOptions = new Scalr_Server_LaunchOptions();
        $launchOptions->imageId = $imageId;
        $launchOptions->cloudLocation = $cloudLocation;
        $launchOptions->architecture = $architecture;

        $platformObj = PlatformFactory::NewPlatform($platform);

        switch($platform) {
            case SERVER_PLATFORMS::IDCF:
                    $launchOptions->serverType = 24;
                break;

            case SERVER_PLATFORMS::RACKSPACENG_US:
                    $launchOptions->serverType = 3;
                break;

            case SERVER_PLATFORMS::RACKSPACENG_UK:
                    $launchOptions->serverType = 3;
                break;

            case SERVER_PLATFORMS::EC2:
                if ($cloudLocation == Aws::REGION_AP_NORTHEAST_2) {
                    if ($hvm == 1 || $this->isHvmBundleTypeOs($os)) {
                        $launchOptions->serverType = 't2.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    }
                } else if ($hvm == 1) {
                    $launchOptions->serverType = "m3.xlarge";
                    $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                } else {
                    $launchOptions->serverType = "m3.large";

                    if ($this->isHvmBundleTypeOs($os)) {
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    }

                    if ($os->family == 'oel' && $os->generation == '5') {
                        $launchOptions->serverType = "m1.large";
                    }
                }

                $launchOptions->userData = "#cloud-config\ndisable_root: false";
                break;

            case SERVER_PLATFORMS::GCE:
                $launchOptions->serverType = 'n1-standard-1';
                $location = null;
                $locations = array_keys($platformObj->getLocations($this->environment));

                while (count($locations) != 0) {
                    $location = array_shift($locations);

                    if (strstr($location, "us-")) {
                        break;
                    }
                }

                $launchOptions->cloudLocation = $locations[0];
                $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::GCE_STORAGE;
                break;
        }

        if ($advanced['servertype']) {
            $launchOptions->serverType = $advanced['servertype'];
        }

        if ($advanced['availzone']) {
            $launchOptions->availZone = $advanced['availzone'];
        }

        if ($advanced['region']) {
            $launchOptions->cloudLocation = $advanced['region'];
        }

        //Add Bundle task
        $creInfo = new ServerSnapshotCreateInfo(
            $dbServer,
            $name,
            SERVER_REPLACEMENT_TYPE::NO_REPLACE
        );

        $bundleTask = BundleTask::Create($creInfo, true);

        if ($bundleType)
            $bundleTask->bundleType = $bundleType;

        $bundleTask->createdById = $this->user->id;
        $bundleTask->createdByEmail = $this->user->getEmail();

        $bundleTask->osFamily = $os->family;
        $bundleTask->object = $createImage ? BundleTask::BUNDLETASK_OBJECT_IMAGE : BundleTask::BUNDLETASK_OBJECT_ROLE;

        $bundleTask->cloudLocation = $launchOptions->cloudLocation;
        $bundleTask->save();

        $bundleTask->Log(sprintf("Launching temporary server (%s)", serialize($launchOptions)));

        $dbServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BUNDLE_TASK_ID, $bundleTask->id);

        try {
            $platformObj->LaunchServer($dbServer, $launchOptions);
            $dbServer->Save();
            $bundleTask->Log(_("Temporary server launched. Waiting for running state..."));
        }
        catch(Exception $e) {
            $bundleTask->SnapshotCreationFailed(sprintf(_("Unable to launch temporary server: %s"), $e->getMessage()));
        }

        $this->response->data(array(
            'serverId'     => $dbServer->serverId,
            'bundleTaskId' => $bundleTask->id
        ));
    }

    public function defaultAction()
    {
        $this->managerAction();
    }

    /**
    * Role manager
    */
    public function managerAction()
    {
        $this->request->restrictAccess('ROLES');

        $this->response->page('ui/roles/manager.js', [
            'categories' => $this->listRoleCategories(true)
        ]);
    }

    /**
     * Get list of roles for listView
     *
     * @param   int     $roleId         optional
     * @param   string  $platform       optional
     * @param   string  $cloudLocation  optional
     * @param   string  $imageId        optional
     * @param   string  $scope          optional
     * @param   int     $chefServerId   optional
     * @param   int     $catId          optional
     * @param   string  $osFamily       optional
     * @param   bool    $isQuickStart   optional
     * @param   bool    $isDeprecated   optional
     * @param   string  $status         optional
     * @param   JsonData $addImage      optional
     */
    public function xListRolesAction($roleId = null, $platform = null, $cloudLocation = null, $imageId = null, $scope = null,
                                     $chefServerId = null, $catId = null, $osFamily = null, $isQuickStart = false, $isDeprecated = false,
                                     $status = null, JsonData $addImage = null)
    {
        $this->request->restrictAccess('ROLES');

        $args = [];
        $inUseJoin = '';
        $envId = $this->getEnvironmentId(true);
        $accountId = $this->user->getAccountId() ?: NULL;

        if ($accountId) {
            $inUseJoin = " JOIN farms ON farm_roles.farmid = farms.id AND farms.clientid = ? ";
            $args[] = $accountId;
            if ($envId) {
                $inUseJoin .= " AND farms.env_id = ?";
                $args[] = $envId;
            }
        }

        $role = new Role();
        $sql = "
            SELECT DISTINCT ". $role->fields('r') .", os.name as osName, os.family as osFamily,
            (SELECT EXISTS(SELECT 1 FROM farm_roles " . $inUseJoin . "
                WHERE farm_roles.role_id = r.id)) AS inUse,
            (SELECT GROUP_CONCAT(name SEPARATOR ',') FROM `client_environments` ce LEFT JOIN role_environments re ON ce.id = re.env_id
                WHERE re.role_id = r.id) AS environments
            FROM " . $role->table('r') . "
            LEFT JOIN role_images ON r.id = role_images.role_id
            LEFT JOIN os ON r.os_id = os.id
            LEFT JOIN role_environments re ON re.role_id = r.id
            WHERE
        ";

        if ($this->request->getScope() == ScopeInterface::SCOPE_SCALR) {
            $sql .= " r.client_id IS NULL";
        } else if ($this->request->getScope() == ScopeInterface::SCOPE_ACCOUNT) {
            $sql .= " (r.client_id IS NULL AND role_images.role_id IS NOT NULL OR r.client_id = ? AND r.env_id IS NULL) AND r.generation = ?";
            $args = array_merge($args, [$accountId, 2]);
        } else {
            $sql .= " (r.client_id IS NULL AND role_images.role_id IS NOT NULL OR r.client_id = ? AND r.env_id IS NULL AND (re.env_id IS NULL OR re.env_id = ?) OR r.env_id = ?) AND r.generation = ?";
            $args = array_merge($args, [$accountId, $envId,
                $envId, 2]);
        }

        if ($roleId) {
            $sql .= " AND r.id = ?";
            $args[] = $roleId;
        } else {
            $sql .= " AND :FILTER: ";

            if ($scope == ScopeInterface::SCOPE_SCALR) {
                $sql .= " AND r.client_id IS NULL";
            } else if ($scope == ScopeInterface::SCOPE_ACCOUNT) {
                $sql .= " AND r.client_id = ? AND r.env_id IS NULL";
                $args[] = $accountId;
            } else if ($scope == ScopeInterface::SCOPE_ENVIRONMENT) {
                $sql .= " AND r.env_id = ?";
                $args[] = $envId;
            }

            if ($platform) {
                $sql .= " AND role_images.platform = ?";
                $args[] = $platform;
            }

            if ($cloudLocation) {
                $sql .= " AND role_images.cloud_location = ?";
                $args[] = $cloudLocation;
            }

            if ($imageId) {
                $sql .= " AND role_images.image_id = ?";
                $args[] = $imageId;
            }

            if ($catId) {
                $sql .= " AND r.cat_id = ?";
                $args[] = $catId;
            }

            if ($osFamily) {
                $sql .= " AND os.family = ?";
                $args[] = $osFamily;
            }

            if ($scope) {
                $sql .= " AND r.origin = ?";
                $args[] = $scope == 'scalr' ? 'Shared' : 'Custom';
            }

            if ($status) {
                $sql .= " AND (";
                $used = $status == 'inUse' ? true : false;

                if ($this->user->getAccountId() != 0) {
                    $sql .= "r.id " . ($used ? '' : "NOT") . " IN (SELECT role_id FROM farm_roles fr " .
                        "JOIN farms f ON f.id = fr.farmid WHERE f." . ($envId ? "env_id" : "clientid") . " = ?)";
                    $args[] = $envId ?: $this->user->getAccountId();
                } else {
                    $sql .= "r.id " . ($used ? '' : "NOT") . " IN (SELECT role_id FROM farm_roles)";
                }

                $sql .= ')';
            }

            if ($chefServerId) {
                $sql .= " AND r.id  IN (SELECT role_id FROM role_properties WHERE name = ? AND value = ?)";
                $sql .= " AND r.id  IN (SELECT role_id FROM role_properties WHERE name = ? AND value = ?)";
                $args[] = \Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID;
                $args[] = $chefServerId;
                $args[] = \Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP;
                $args[] = 1;
            }

            if ($addImage) {
                if (isset($addImage['osId'])) {
                    $sql .= " AND r.os_id = ?";
                    $args[] = $addImage['osId'];
                }
                if (isset($addImage['isScalarized']) && isset($addImage['hasCloudInit']) && $addImage['isScalarized'] == 0  && $addImage['hasCloudInit'] == 0) {
                    $sql .= " AND r.is_scalarized = 0";
                }
            }

            if ($isQuickStart) {
                $sql .= " AND r.is_quick_start = 1";
            }

            if ($isDeprecated) {
                $sql .= " AND r.is_deprecated = 1";
            }
        }

        $response = $this->buildResponseFromSql2($sql, ['id', 'name', 'os_id'], ['r.name'], $args);
        $data = [];

        $allPlatforms = array_flip(array_keys(SERVER_PLATFORMS::GetList()));

        foreach ($response['data'] as $r) {
            $role = new Role();
            $role->load($r);

            $row = new stdClass;

            $row->name = $role->name;
            $row->behaviors = $role->getBehaviors();
            $row->id = $role->id;
            $row->accountId = $role->accountId;
            $row->envId = $role->envId;
            $row->status = $r['inUse'] ? 'In use' : 'Not used';
            $row->scope = $role->getScope();
            $row->os = $r['osName'];
            $row->osId = $role->osId;
            $row->osFamily = $r['osFamily'];
            $row->dtAdded = $role->added ? Scalr_Util_DateTime::convertTz($role->added) : null;
            $row->dtLastUsed = $role->lastUsed ? Scalr_Util_DateTime::convertTz($role->lastUsed) : null;
            $row->isQuickStart = $role->isQuickStart ? "1" : "0";
            $row->isDeprecated = $role->isDeprecated ? "1" : "0";
            $row->isScalarized = $role->isScalarized ? 1 : 0;
            $row->client_name = $role->accountId == 0 ? 'Scalr' : 'Private';
            $row->environments = $r['environments'] ? explode(',' , $r['environments']) : [];

            $platforms = array_keys($role->fetchImagesArray());
            usort($platforms, function($a, $b) use($allPlatforms) {
                return $allPlatforms[$a] > $allPlatforms[$b] ? 1 : -1;
            });
            $row->platforms = $platforms;

            if ($addImage->count()) {
                try {
                    $role->getImage($addImage['platform'], $addImage['cloudLocation']);
                    $row->canAddImage = false;
                } catch (Exception $e) {
                    $row->canAddImage = true;
                }
            }

            $data[] = $row;
        }

        $this->response->data([
            'total' => $response['total'],
            'data'  => $data
        ]);
    }

    /**
     * @param   int     $roleId
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xGetInfoAction($roleId)
    {
        $this->request->restrictAccess('ROLES');

        $role = $this->getInfo($roleId, true);

        $this->response->data(array(
            'role' => $role
        ));
    }

    /**
     * Get information about role
     *
     * @param   int     $roleId      Identifier of role
     * @param   bool    $extended    Get extended information about role
     * @param   array   $canAddImage Array of platform, cloudLocation to check if role has image in that location
     * @return  array
     * @throws  Exception
     * @throws  Scalr_Exception_Core
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    private function getInfo($roleId, $extended = false, $canAddImage = null)
    {
        /* @var $role Role */
        $role = Role::findPk($roleId);

        if (!$role) {
            throw new Scalr_Exception_Core(sprintf(_("Role ID#%s not found in database"), $roleId));
        }

        $this->request->checkPermissions($role);
        $usedBy = $role->getFarmsCount($this->user->getAccountId(), $this->getEnvironmentId(true));

        $platforms = array_keys($role->fetchImagesArray());
        $allPlatforms = array_flip(array_keys(SERVER_PLATFORMS::GetList()));
        usort($platforms, function($a, $b) use($allPlatforms) {
            return $allPlatforms[$a] > $allPlatforms[$b] ? 1 : -1;
        });

        $result = array(
            'name'          => $role->name,
            'behaviors'     => $role->getBehaviors(),
            'id'            => $role->id,
            'accountId'     => $role->accountId,
            'envId'         => $role->envId,
            'catId'         => $role->catId,
            'status'        => $usedBy > 0 ? 'In use' : 'Not used',
            'scope'         => $role->getScope(),
            'os'            => $role->getOs()->name,
            'osId'          => $role->osId,
            'osFamily'      => $role->getOs()->family,
            'dtAdded'       => $role->added ? Scalr_Util_DateTime::convertTz($role->added) : NULL,
            'dtLastUsed'    => $role->lastUsed ? Scalr_Util_DateTime::convertTz($role->lastUsed): NULL,
            'isQuickStart'  => $role->isQuickStart,
            'isDeprecated'  => $role->isDeprecated,
            'isScalarized'  => $role->isScalarized,
            'platforms'     => $platforms,
            'environments'  => !empty($envs = $role->getAllowedEnvironments()) ?
                $this->db->GetCol("SELECT name FROM client_environments WHERE id IN(" . join(',', $envs) . ")") :
                []
        );

        if ($canAddImage) {
            try {
                $role->getImage($canAddImage['platform'], $canAddImage['cloudLocation']);
                $result['canAddImage'] = false;
            } catch (Exception $e) {
                $result['canAddImage'] = true;
            }
        }

        if ($extended) {
            $result['description'] = $role->description;
            $result['images'] = [];
            foreach (RoleImage::find([['roleId' => $role->id]]) as $image) {
                /* @var $image RoleImage */
                $im = $image->getImage();
                $ext = [];
                if ($im) {
                    $ext = get_object_vars($im);
                    $ext['software'] = $im->getSoftwareAsString();
                }

                $result['images'][] = [
                    'imageId' => $image->imageId,
                    'platform' => $image->platform,
                    'cloudLocation' => $image->cloudLocation,
                    'extended' => $ext
                ];
            }

            if ($result['status'] == 'In use' && $this->getEnvironmentId(true)) {
                $farms = [];
                $f = [];
                foreach (FarmRole::find([['roleId' => $role->id]]) as $farmRole) {
                    /* @var $farmRole FarmRole */
                    $f[] = $farmRole->farmId;
                }
                $f = array_unique($f);

                if (count($f)) {
                    foreach (Farm::find([['id' => ['$in' => $f]], ['envId' => $this->getEnvironmentId()]]) as $fm) {
                        /* @var $fm Farm */
                        $farms[] = ['id' => $fm->id, 'name' => $fm->name];
                    }
                }

                $result['usedBy'] = [ 'farms' => $farms, 'cnt' => count($farms)];
            }
        }

        return $result;
    }

    /**
     * @param   int   $roleId
     * @throws  Exception
     * @throws  Scalr_Exception_Core
     * @throws  Scalr_Exception_InsufficientPermissions
     * @throws  Scalr_UI_Exception_NotFound
     */
    public function editAction($roleId = 0)
    {
        $this->request->restrictAccess('ROLES', 'MANAGE');

        $params = array();

        $params['scriptData'] = \Scalr\Model\Entity\Script::getScriptingData($this->user->getAccountId(), $this->getEnvironmentId(true));
        $params['categories'] = array_values($this->listRoleCategories());
        $params['accountScripts'] = [];

        if (!$this->user->isScalrAdmin()) {
            foreach (Scalr_UI_Controller_Account2_Orchestration::controller()->getOrchestrationRules() as $script) {
                $script['system'] = 'account';
                $params['accountScripts'][] = $script;
            }
        }

        $envs = [];
        if ($this->request->getScope() == ScopeInterface::SCOPE_ACCOUNT) {
            foreach (Environment::find([['accountId' => $this->user->getAccountId()]], null, ['name' => true]) as $env) {
                /* @var $env Environment */
                $envs[] = ['id' => $env->id, 'name' => $env->name, 'enabled' => 1];
            }
        }

        $variables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(true), ScopeInterface::SCOPE_ROLE);

        if ($roleId) {
            /* @var $role Role */
            $role = Role::findPk($roleId);

            if (!$role) {
                throw new Scalr_Exception_Core(sprintf(_("Role ID#%s not found in database"), $roleId));
            }

            $this->request->checkPermissions($role, true);

            $images = array();
            foreach (RoleImage::find([['roleId' => $role->id]]) as $image) {
                /* @var $image RoleImage */
                $im = $image->getImage();
                $a = get_object_vars($image);
                if ($im) {
                    $b = get_object_vars($im);
                    $b['scope'] = $im->getScope();
                    $b['dtAdded'] = Scalr_Util_DateTime::convertTz($b['dtAdded']);
                    $b['software'] = $im->getSoftwareAsString();
                    $a['name'] = $im->name;
                    $a['hash'] = $im->hash;
                    $a['extended'] = $b;
                }
                $images[] = $a;
            }

            $properties = [];
            foreach (RoleProperty::find([['roleId' => $role->id], ['name' => ['$like' => 'chef.%']]]) as $prop) {
                /* @var $prop RoleProperty */
                $properties[$prop->name] = $prop->value;
            }

            $params['role'] = array(
                'roleId'        => $role->id,
                'name'          => $role->name,
                'catId'         => $role->catId,
                'os'            => $role->getOs()->name,
                'osId'          => $role->osId,
                'osFamily'      => $role->getOs()->family,
                'osGeneration'  => $role->getOs()->generation,
                'osVersion'     => $role->getOs()->version,
                'description'   => $role->description,
                'behaviors'     => $role->getBehaviors(),
                'images'        => $images,
                'scripts'       => $role->getScripts(),
                'dtadded'       => Scalr_Util_DateTime::convertTz($role->added),
                'addedByEmail'  => $role->addedByEmail,
                'chef'          => $properties,
                'isQuickStart'  => $role->isQuickStart,
                'isDeprecated'  => $role->isDeprecated,
                'isScalarized'  => $role->isScalarized,
                'environments'  => []
            );

            if ($this->request->getScope() == ScopeInterface::SCOPE_ACCOUNT) {
                $allowedEnvs = $role->getAllowedEnvironments();
                if (!empty($allowedEnvs)) {
                    foreach ($envs as &$env) {
                        $env['enabled'] = in_array($env['id'], $allowedEnvs) ? 1 : 0;
                    }
                }

                $params['role']['environments'] = $envs;
            }

            $params['role']['variables'] = $variables->getValues($role->id);

            $params['roleUsage'] = [
                'farms' => $role->getFarmsCount($this->user->getAccountId(), $this->getEnvironmentId(true)),
                'instances' => $role->getServersCount($this->user->getAccountId(), $this->getEnvironmentId(true))
            ];
        } else {
            $params['role'] = array(
                'roleId'        => 0,
                'name'          => '',
                'arch'          => 'x86_64',
                'agent'         => 2,
                'description'   => '',
                'behaviors'     => array(),
                'images'        => array(),
                'scripts'       => array(),
                'tags'          => array(),
                'environments'  => [],
                'variables'     => $variables->getValues(),
                'isScalarized'  => 1
            );

            if ($this->request->getScope() == ScopeInterface::SCOPE_ACCOUNT) {
                $params['role']['environments'] = $envs;
            }
        }

        $this->response->page('ui/roles/edit.js', $params, [
            'ui/roles/edit/overview.js',
            'ui/roles/edit/images.js',
            'ui/roles/edit/scripting.js',
            'ui/roles/edit/variables.js',
            'ui/roles/edit/chef.js',
            'ui/roles/edit/environments.js',
            'ui/scripts/scriptfield.js',
            'ui/core/variablefield.js',
            'ui/services/chef/chefsettings.js'
        ], [
            'ui/roles/edit.css',
            'ui/scripts/scriptfield.css'
        ]);
    }

    /**
     * @param   int         $roleId
     * @param   string      $name
     * @param   string      $description
     * @param   string      $osId
     * @param   int         $catId
     * @param   bool        $isQuickStart
     * @param   bool        $isDeprecated
     * @param   bool        $isScalarized
     * @param   JsonData    $behaviors
     * @param   JsonData    $images
     * @param   JsonData    $scripts
     * @param   JsonData    $variables
     * @param   JsonData    $chef
     * @param   JsonData    $environments
     * @throws  Exception
     * @throws  Scalr_Exception_Core
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xSaveAction($roleId = 0, $name, $description, $osId, $catId, $isQuickStart = false, $isDeprecated = false, $isScalarized = true,
                                JsonData $behaviors, JsonData $images, JsonData $scripts, JsonData $variables, JsonData $chef, JsonData $environments)
    {
        $this->request->restrictAccess('ROLES', 'MANAGE');

        $accountId = $this->user->getAccountId() ?: NULL;

        if (! Role::isValidName($name)) {
            throw new Exception(_("Role name is incorrect"));
        }

        if (Role::isNameUsed($name, $accountId, $this->getEnvironmentId(true), $roleId)) {
            throw new Exception('Selected role name is already used. Please select another one.');
        }

        if (!$catId) {
            throw new Exception('Role category is required');
        }


        if ($roleId == 0) {
            if (! Os::findPk($osId)) {
                throw new Exception(sprintf('%s is not valid osId', $osId));
            }

            $role = new Role();
            $role->generation = 2;
            $role->origin = $this->user->isScalrAdmin() ? ROLE_TYPE::SHARED : ROLE_TYPE::CUSTOM;
            $role->accountId = $accountId;
            $role->envId = $this->getEnvironmentId(true);
            $role->name = $name;
            $role->catId = $catId;
            $role->osId = $osId;
            $role->isScalarized = $isScalarized ? 1 : 0;

            $role->addedByUserId = $this->user->getId();
            $role->addedByEmail = $this->user->getEmail();

            $role->setBehaviors((array) $behaviors);
            $role->save();
        } else {
            $role = Role::findPk($roleId);

            if (!$role) {
                throw new Scalr_Exception_Core(sprintf(_("Role ID#%s not found in database"), $roleId));
            }

            $this->request->checkPermissions($role, true);

            $role->name = $name;
            $role->catId = $catId;
        }

        $globalVariables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(true), ScopeInterface::SCOPE_ROLE);
        $globalVariables->setValues($variables, $role->id);

        foreach (RoleProperty::find([['roleId' => $role->id], ['name' => ['$like' => ['chef.%']]]]) as $prop) {
            $prop->delete();
        }

        foreach ($chef as $name => $value) {
            $prop = new RoleProperty();
            $prop->roleId = $role->id;
            $prop->name = $name;
            $prop->value = $value;
            $prop->save();
        }

        $role->description = $description;
        $role->isQuickStart = $isQuickStart;
        $role->isDeprecated = $isDeprecated;

        foreach ($images as $i) {
            if (isset($i['platform']) && isset($i['cloudLocation']) && isset($i['imageId'])) {
                $role->setImage(
                    $i['platform'],
                    $i['cloudLocation'],
                    $i['imageId'],
                    $this->user->getId(),
                    $this->user->getEmail()
                );
            }
        }

        $role->setScripts((array) $scripts);
        $role->save();

        if ($this->request->getScope() == ScopeInterface::SCOPE_ACCOUNT) {
            foreach (RoleEnvironment::find([['roleId' => $roleId]]) as $re) {
                $re->delete();
            }
            $accountEnvironments = [];
            $allowedEnvironments = [];
            foreach (Environment::find([['accountId' => $this->user->getAccountId()]]) as $env) {
                $accountEnvironments[] = $env->id;
            }

            foreach ($environments as $e) {
                if ($e['enabled'] == 1 && in_array($e['id'], $accountEnvironments)) {
                    $allowedEnvironments[] = $e['id'];
                }
            }

            if (count($allowedEnvironments) < count($accountEnvironments)) {
                foreach ($allowedEnvironments as $id) {
                    $re = new RoleEnvironment();
                    $re->roleId = $role->id;
                    $re->envId = $id;
                    $re->save();
                }
            }
        }

        $this->response->data(['role' => $this->getInfo($role->id, true), 'categories' => $this->listRoleCategories(true)]);
        $this->response->success('Role saved');
    }

    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);
        $this->response->page('ui/roles/create.js');
    }

    /**
     * Move role to account scope
     *
     * @param   int     $id     ID of Role
     * @throws  Exception
     */
    public function xPromoteAction($id)
    {
        throw new \Scalr\Exception\NotYetImplementedException('Not implemented yet');
        $this->request->restrictAccess(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);
        $this->request->restrictAccess(Acl::RESOURCE_ROLES_ACCOUNT, Acl::PERM_ROLES_ACCOUNT_MANAGE);

        /* @var $role Role */
        if (!$id || !($role = Role::findPk($id))) {
            throw new Exception('Role not found');
        }

        $role->envId = null;
        $role->save();

        // Invar: move all images to account scope

        $this->response->data(['role' => $this->getInfo($role->id, true)]);
    }

    /**
     * Returns list Role Categories
     * @param bool $notEmpty        Not empty categories only
     * @param bool $checkRoleImages Count roles with no images
     * @return  array [id => name]
     */
    public function listRoleCategories($notEmpty = false, $checkRoleImages = false)
    {
        $query = "SELECT c.*
                  FROM role_categories c ";
        $where = "WHERE (c.account_id IS NULL AND c.env_id IS NULL) OR (c.account_id = ? AND (c.env_id IS NULL OR c.env_id = ?)) ";
        $args = [$this->user->getAccountId(), $this->environment->id];

        if ($notEmpty) {
            $query .= "INNER JOIN roles r ON c.id = r.cat_id ";
            $where .= "AND (r.client_id IS NULL AND r.env_id IS NULL) OR (r.client_id = ? AND (r.env_id IS NULL OR r.env_id = ?)) ";
            $args[] = $this->user->getAccountId();
            $args[] = $this->environment->id;

            if ($this->request->getScope() == ScopeInterface::SCOPE_ENVIRONMENT && $checkRoleImages) {
                $query .= "INNER JOIN role_images ri ON ri.role_id = r.id ";
                $where .= "AND ri.platform IN ('".implode("','", array_keys(self::loadController('Platforms')->getEnabledPlatforms()))."') ";
            }
        }

        $query .= $where . "
             GROUP BY c.id
             ORDER BY IF(c.account_id OR c.env_id, 1, 0), IF(c.account_id OR c.env_id, c.name, c.id)
            ";
        $result = [];
        foreach ($this->db->GetAll($query, $args) as $c) {
            $category = new RoleCategory();
            $category->load($c);
            $result[$category->id] = [
                'id'    => $category->id,
                'name'  => $category->name,
                'scope' => $category->getScope()
            ];
        }

        return $result;
    }

}
