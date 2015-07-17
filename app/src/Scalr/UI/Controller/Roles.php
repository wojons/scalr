<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\UI\Request\JsonData;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\RoleImage;
use Scalr\Model\Entity\Os;

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
    * Get list of roles for roles library
    */
    public function xGetListAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES);

        $total = 0;
        $roles = array();
        $filterRoleId = $this->getParam('roleId');
        $filterCatId = $this->getParam('catId');
        $filterOsFamily = $this->getParam('osFamily');
        $filterKeyword = $this->getParam('keyword');

        $filterPlatform = $this->getParam('platform');

        $ec2Locations = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2)->getLocations($this->environment);
        $e_platforms = $this->getEnvironment()->getEnabledPlatforms();
        $platforms = array();
        $l_platforms = SERVER_PLATFORMS::GetList();
        foreach ($e_platforms as $platform)
            $platforms[$platform] = $l_platforms[$platform];

        if ($filterPlatform)
            if (!$platforms[$filterPlatform])
                throw new Exception("Selected cloud not enabled in current environment");

        $globalVars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);

        if ($filterCatId === 'shared') {
            $args = [];

            $roles_sql = "SELECT DISTINCT(roles.id) FROM roles
                INNER JOIN role_images ON role_images.role_id = roles.id
                INNER JOIN os ON os.id = roles.os_id
                WHERE is_deprecated='0' AND roles.generation = '2' AND roles.env_id IS NULL";

            if (!$filterPlatform)
                $roles_sql .= " AND role_images.platform IN ('".implode("','", array_keys($platforms))."')";
            else {
                $roles_sql .= " AND role_images.platform = ?";
                $args[] = $filterPlatform;
            }

            if ($filterOsFamily) {
                $roles_sql .= ' AND family = ?';
                $args[] = $filterOsFamily;
            }

            $dbRoles = $this->db->Execute($roles_sql, $args);
            $software = array();
            $softwareOrdering = array(
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
            );
            foreach ($dbRoles as $role) {
                $dbRole = DBRole::loadById($role['id']);

                // Get type
                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER))
                    $type = 'vpcrouter';
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::APACHE) && ($dbRole->hasBehavior(ROLE_BEHAVIORS::MYSQL2) || $dbRole->hasBehavior(ROLE_BEHAVIORS::MYSQL) || $dbRole->hasBehavior(ROLE_BEHAVIORS::PERCONA) || $dbRole->hasBehavior(ROLE_BEHAVIORS::MARIADB)))
                    $type = 'lamp';
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::MYSQL2))
                    $type = 'mysql';
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::APACHE))
                    $type = 'apache';
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::TOMCAT))
                    $type = ROLE_BEHAVIORS::TOMCAT;
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::NGINX))
                    $type = 'nginx';
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::HAPROXY))
                    $type = 'haproxy';
                elseif ($dbRole->getDbMsrBehavior())
                    $type = $dbRole->getDbMsrBehavior();
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::MONGODB))
                    $type = ROLE_BEHAVIORS::MONGODB;
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::RABBITMQ))
                    $type = ROLE_BEHAVIORS::RABBITMQ;
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::MEMCACHED))
                    $type = ROLE_BEHAVIORS::MEMCACHED;
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::BASE))
                    $type = ROLE_BEHAVIORS::BASE;
                elseif ($dbRole->hasBehavior(ROLE_BEHAVIORS::MYSQL))
                    continue;

                $images = $dbRole->__getNewRoleObject()->fetchImagesArray();
                if (!empty($images)) {
                    $item = array(
                        'role_id'       => $dbRole->id,
                        'name'		    => $dbRole->name,
                        'behaviors'     => $dbRole->getBehaviors(),
                        'origin'        => $dbRole->origin,
                        'cat_name'	    => $dbRole->getCategoryName(),
                        'cat_id'	    => $dbRole->catId,
                        'osId'          => $dbRole->getOs()->id,
                        'images'        => $images,
                        'description'   => $dbRole->description,
                        'variables'     => $globalVars->getValues($dbRole->id),
                    );

                    $software[$type]['roles'][] = $item;

                    $software[$type]['name'] = $type;
                    $software[$type]['ordering'] = isset($softwareOrdering[$type]) ? $softwareOrdering[$type] : 1000;
                    $total++;
                }
            }
            $software = array_values($software);
        } else {
            $args[] = $this->getEnvironmentId();
            $roles_sql = "
                SELECT DISTINCT(r.id), r.env_id
                FROM roles r
                INNER JOIN role_images as i ON i.role_id = r.id
                INNER JOIN os as o ON o.id = r.os_id
                WHERE r.generation = '2' AND (r.env_id IS NULL OR r.env_id = ?)
                AND i.platform
            ";
            if (!$filterPlatform)
                $roles_sql .= " IN ('".implode("','", array_keys($platforms))."')";
            else {
                $roles_sql .= " = ?";
                $args[] = $filterPlatform;
            }

            if ($filterCatId === 'search'){
                $roles_sql .= ' AND r.name LIKE ' . $this->db->qstr('%' . trim($filterKeyword) . '%');
                if ($filterRoleId) {
                    $roles_sql .= ' AND r.id = ?';
                    $args[] = $filterRoleId;
                }
            } elseif ($filterCatId === 'recent') {
            } elseif ($filterCatId) {
                $roles_sql .= ' AND r.cat_id = ?';
                $args[] = $filterCatId;
            }

            if ($filterOsFamily) {
                $roles_sql .= ' AND o.family = ?';
                $args[] = $filterOsFamily;
            }

            $roles_sql .= ' GROUP BY r.id';

            if ($filterCatId === 'recent') {
                $roles_sql .= ' ORDER BY r.id DESC LIMIT 10';
            }

            $dbRoles = $this->db->Execute($roles_sql, $args);

            foreach ($dbRoles as $role) {
                $dbRole = DBRole::loadById($role['id']);

                $images = $dbRole->__getNewRoleObject()->fetchImagesArray();
                if (!empty($images)) {
                    $roles[] = array(
                        'role_id'       => $dbRole->id,
                        'name'		    => $dbRole->name,
                        'behaviors'     => $dbRole->getBehaviors(),
                        'origin'        => $dbRole->origin,
                        'cat_name'	    => $dbRole->getCategoryName(),
                        'cat_id'	    => $dbRole->catId,
                        'osId'          => $dbRole->getOs()->id,
                        'images'        => $images,
                        'variables'     => $globalVars->getValues($dbRole->id, 0, 0),
                        'shared'        => $role['env_id'] == 0,
                        'description'   => $dbRole->description,
                    );
                }
            }
            $total = count($roles);
        }

        $moduleParams = array(
            'roles' => $roles,
            'software' => $software,
            'total' => $total
        );
        $this->response->data($moduleParams);
    }

    /**
     * @param int $roleId
     * @param string $newRoleName
     * @throws Exception
     */
    public function xCloneAction($roleId, $newRoleName)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CLONE);

        $dbRole = DBRole::loadById($roleId);
        if (!empty($dbRole->envId)) {
            $this->user->getPermissions()->validate($dbRole);
        }

        if (! preg_match("/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/si", $newRoleName))
            throw new Exception(_("Role name is incorrect"));

        $chkRoleId = $this->db->GetOne("SELECT id FROM roles WHERE name=? AND (env_id IS NULL OR env_id = ?) LIMIT 1",
            array($newRoleName, $this->getEnvironmentId(true))
        );

        if ($chkRoleId) {
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
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);
        $errors = [];
        $processed = [];

        foreach ($roles as $id) {
            try {
                /* @var $role Role */
                $role = Role::findPk($id);
                if ($role) {
                    if ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN)
                        $this->user->getPermissions()->validate($role);

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
        if (count($errors))
            $this->response->warning("Roles(s) successfully removed, but some errors occurred:\n" . implode("\n", $errors));
        else
            $this->response->success('Roles(s) successfully removed');
    }

    /**
     * @param   string      $devel
     * @param   string      $serverId
     * @throws  Exception
     */
    public function builderAction($devel = false, $serverId = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE);
        $enabledPlatforms = self::loadController('Platforms')->getEnabledPlatforms(false);
        if (empty($enabledPlatforms)) {
            $this->response->failure('Please <a href="#/account/environments?envId='.$this->getEnvironmentId().'">configure cloud credentials</a> before using Role Builder.', true);
            return;
        }

        $platforms = array();
        foreach ($enabledPlatforms as $k => $v) {
            if (in_array($k, array(SERVER_PLATFORMS::ECS, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::EC2, SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK))) {
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
                $platforms[$k]['images'] = array();
                foreach ($images[$k] as $image) {
                    if (isset($locations[$image['cloud_location']])) {
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
        ), array('ui/services/chef/chefsettings.js'), array('ui/roles/builder.css'));
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
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE);

        if (! \Scalr\Model\Entity\Role::validateName($name))
            throw new Exception(_("Name is incorrect"));

        if (! $createImage && $this->db->GetOne("SELECT id FROM roles WHERE name=? AND (env_id IS NULL OR env_id = ?) LIMIT 1",
                array($name, $this->getEnvironmentId()))
        )
            throw new Exception('Selected role name is already used. Please select another one.');

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
            case SERVER_PLATFORMS::ECS:
                    $launchOptions->serverType = 10;
                    if ($cloudLocation == 'all') {
                        $locations = array_keys($platformObj->getLocations($this->environment));
                        $launchOptions->cloudLocation = $locations[0];
                    }

                    //Network here:
                    $osClient = $platformObj->getOsClient($this->environment, $launchOptions->cloudLocation);
                    $networks = $osClient->network->listNetworks();
                    $tenantId = $osClient->getConfig()->getAuthToken()->getTenantId();
                    foreach ($networks as $network) {
                        if ($network->status == 'ACTIVE') {
                            if ($network->{"router:external"} != true) {
                                if ($tenantId == $network->tenant_id) {
                                    $launchOptions->networks = array($network->id);
                                    break;
                                }
                            }
                        }
                    }

                break;
            case SERVER_PLATFORMS::IDCF:
                    $launchOptions->serverType = 24;
                break;
            case SERVER_PLATFORMS::RACKSPACE:
                if ($os->family == 'ubuntu')
                    $launchOptions->serverType = 1;
                else
                    $launchOptions->serverType = 3;
                break;
            case SERVER_PLATFORMS::RACKSPACENG_US:
                    $launchOptions->serverType = 3;
                break;
            case SERVER_PLATFORMS::RACKSPACENG_UK:
                    $launchOptions->serverType = 3;
                break;
            case SERVER_PLATFORMS::EC2:

                if ($hvm == 1) {
                    $launchOptions->serverType = 'm3.xlarge';
                    $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                } else {
                    if ($os->family == 'oel') {
                        $launchOptions->serverType = 'm3.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    } elseif ($os->family == 'rhel') {
                        $launchOptions->serverType = 'm3.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    } elseif ($os->family == 'scientific') {
                        $launchOptions->serverType = 'm3.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    } elseif ($os->family == 'debian' && $os->generation == '8') {
                        $launchOptions->serverType = 'm3.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    } elseif ($os->family == 'centos' && $os->generation == '7') {
                        $launchOptions->serverType = 'm3.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    }

                    //TODO: Add CentOS 7 and Amazon Linux 2014.09 to use HVM

                    else
                        $launchOptions->serverType = 'm3.large';
                }

                $launchOptions->userData = "#cloud-config\ndisable_root: false";
                break;
            case SERVER_PLATFORMS::GCE:
                $launchOptions->serverType = 'n1-standard-1';
                $location = null;
                $locations = array_keys($platformObj->getLocations($this->environment));
                while (count($locations) != 0) {
                    $location = array_shift($locations);
                    if (strstr($location, "us-"))
                        break;
                }

                $launchOptions->cloudLocation = $locations[0];

                $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::GCE_STORAGE;
                break;
        }

        if ($advanced['servertype'])
            $launchOptions->serverType = $advanced['servertype'];

        if ($advanced['availzone'])
            $launchOptions->availZone = $advanced['availzone'];

        if ($advanced['region'])
            $launchOptions->cloudLocation = $advanced['region'];

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
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES);

        $this->response->page('ui/roles/manager.js', array(
            'categories' => $this->db->GetAll("SELECT * FROM `role_categories` WHERE env_id IS NULL OR env_id = ?", array($this->user->isScalrAdmin() ? null : $this->getEnvironmentId()))
        ));
    }

    //todo: restrictAccess check?
    public function xGetRoleParamsAction()
    {
        $this->request->defineParams(array(
            'roleId' => array('type' => 'int'),
            'farmId' => array('type' => 'int'),
            'cloudLocation'
        ));

        try {
            $dbRole = DBRole::loadById($this->getParam('roleId'));
            if ($dbRole->envId != 0)
                $this->user->getPermissions()->validate($dbRole);
        }
        catch (Exception $e) {
            $this->response->data(array('params' => array()));
            return;
        }

        $params = $this->db->GetAll("SELECT * FROM role_parameters WHERE role_id=? AND hash NOT IN('apache_http_vhost_template','apache_https_vhost_template')",
            array($dbRole->id)
        );

        foreach ($params as $key => $param) {
            $value = false;

            try {
                if($this->getParam('farmId')) {
                    $dbFarmRole = DBFarmRole::Load($this->getParam('farmId'), $this->getParam('roleId'), $this->getParam('cloudLocation'));

                    $value = $this->db->GetOne("SELECT value FROM farm_role_options WHERE farm_roleid=? AND hash=? LIMIT 1",
                        array($dbFarmRole->ID, $param['hash'])
                    );
                }
            }
            catch(Exception $e) {}

            // Get field value
            if ($value === false || $value === null)
                $value = $param['defval'];

            $params[$key]['value'] = str_replace("\r", "", $value);
        }

        $this->response->data(array('params' => $params));
    }

    /**
    * Get list of roles for listView
    */
    public function xListRolesAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES);

        $this->request->defineParams(array(
            'client_id' => array('type' => 'int'),
            'roleId' => array('type' => 'int'),
            'platform', 'cloudLocation', 'scope', 'query', 'catId', 'osFamily',
            'sort' => array('type' => 'json'),
            'addImage' => array('type' => 'json')
        ));

        if ($this->user->isScalrAdmin()) {
            $sql = 'SELECT DISTINCT(roles.id), roles.name as name, os.name as os_name
                    FROM roles
                    LEFT JOIN role_images ON role_images.role_id = roles.id
                    LEFT JOIN os ON os.id = roles.os_id
                    WHERE roles.env_id IS NULL
                    AND :FILTER:';
            $args = array();
        } else {
            $sql = 'SELECT DISTINCT(roles.id), roles.name as name, os.name as os_name
                    FROM roles
                    LEFT JOIN role_images ON role_images.role_id = roles.id
                    LEFT JOIN os ON os.id = roles.os_id
                    WHERE (roles.env_id = ? OR roles.env_id IS NULL AND role_images.role_id IS NOT NULL)
                    AND roles.generation=?
                    AND :FILTER:';
            $args = array($this->getEnvironmentId(), 2);
        }

        if ($this->getParam('roleId')) {
            $sql .= ' AND roles.id = ?';
            $args[] = $this->getParam('roleId');
        } else {
            if ($this->getParam('platform')) {
                $sql .= ' AND role_images.platform = ?';
                $args[] = $this->getParam('platform');
            }

            if ($this->getParam('cloudLocation')) {
                $sql .= ' AND role_images.cloud_location = ?';
                $args[] = $this->getParam('cloudLocation');
            }

            if ($this->getParam('imageId')) {
                $sql .= ' AND role_images.image_id = ?';
                $args[] = $this->getParam('imageId');
            }

            if ($this->getParam('catId')) {
                $sql .= ' AND roles.cat_id = ?';
                $args[] = $this->getParam('catId');
            }

            if ($this->getParam('osFamily')) {
                $sql .= ' AND os.family = ?';
                $args[] = $this->getParam('osFamily');
            }

            if ($this->getParam('scope')) {
                $sql .= ' AND origin = ?';
                $args[] = $this->getParam('scope') == 'scalr' ? 'Shared' : 'Custom';
            }

            if ($this->getParam('status')) {
                $sql .= ' AND (';
                $used = $this->getParam('status') == 'Used' ? true : false;
                if ($this->user->getAccountId() != 0) {
                    $sql .= 'roles.id ' . ($used ? '' : 'NOT') . ' IN(SELECT role_id FROM farm_roles WHERE farmid IN(SELECT id from farms WHERE env_id = ?))';
                    $sql .= ' OR roles.id ' . ($used ? '' : 'NOT') . ' IN(SELECT new_role_id FROM farm_roles WHERE farmid IN(SELECT id from farms WHERE env_id = ?))';
                    $args[] = $this->getEnvironmentId();
                    $args[] = $this->getEnvironmentId();
                } else {
                    $sql .= 'roles.id ' . $used ? '' : 'NOT' . ' IN(SELECT role_id FROM farm_roles)';
                    $sql .= ' OR ' . $used ? '' : 'NOT' . ' roles.id IN(SELECT new_role_id FROM farm_roles)';
                }
                $sql .= ')';
            }

            if ($this->getParam('chefServerId')) {
                $sql .= ' AND roles.id  IN(SELECT role_id FROM role_properties WHERE name = ? AND value = ?)';
                $sql .= ' AND roles.id  IN(SELECT role_id FROM role_properties WHERE name = ? AND value = ?)';
                $args[] = \Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID;
                $args[] = (int)$this->getParam('chefServerId');
                $args[] = \Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP;
                $args[] = 1;
            }

            $addImage = $this->getParam('addImage');
            if ($addImage) {
                $sql .= ' AND os_id = ?';
                $args[] = $addImage['osId'];
            }
        }

        $response = $this->buildResponseFromSql2($sql, array('name', 'os_id'), array('roles.name'), $args);

        foreach ($response['data'] as &$row) {
            $row = $this->getInfo($row['id'], false, $addImage);
        }

        $this->response->data($response);
    }

    public function xGetInfoAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES);

        $this->request->defineParams(array(
            'roleId' => array('type' => 'int')
        ));

        $role = $this->getInfo($this->getParam('roleId'), true);

        $this->response->data(array(
            'role' => $role
        ));
    }

    private function getInfo($roleId, $extended = false, $canAddImage = false)
    {
        $dbRole = DBRole::loadById($roleId);

        if ($dbRole->envId != 0) {
            $this->user->getPermissions()->validate($dbRole);
        }

        if ($this->user->getAccountId() != 0) {
            $usedBy = $dbRole->getFarmRolesCount($this->getEnvironmentId());
        } else {
            $usedBy = $dbRole->getFarmRolesCount();
        }

        $status = 'Not used';
        if ($usedBy > 0) {
            $status = 'In use';
        }

        $role = array(
            'name'			=> $dbRole->name,
            'behaviors'		=> $dbRole->getBehaviors(),
            'id'			=> (int) $dbRole->id,
            'client_id'		=> $dbRole->clientId,
            'env_id'		=> $dbRole->envId,
            'status'		=> $status,
            'origin'		=> $dbRole->origin,
            'os'			=> $dbRole->getOs()->name,
            'osId'          => $dbRole->osId,
            'osFamily'      => $dbRole->getOs()->family,
            'dtAdded'       => $dbRole->dateAdded ? Scalr_Util_DateTime::convertTz($dbRole->dateAdded) : NULL,
            'dtLastUsed'    => $dbRole->dtLastUsed ? Scalr_Util_DateTime::convertTz($dbRole->dtLastUsed): NULL,
            'platforms'		=> array_keys($dbRole->__getNewRoleObject()->fetchImagesArray()),
            'client_name'   => $dbRole->clientId == 0 ? 'Scalr' : 'Private'
        );

        try {
            $envId = $this->getEnvironmentId();
            $role['used_servers'] = $this->db->GetOne("SELECT COUNT(*) FROM servers LEFT JOIN farm_roles ON servers.farm_roleid = farm_roles.id WHERE farm_roles.role_id=? AND env_id=?",
                array($dbRole->id, $envId)
            );
        }
        catch(Exception $e) {
            if ($this->user->getAccountId() == 0) {
                $role['used_servers'] = $this->db->GetOne("SELECT COUNT(*) FROM servers LEFT JOIN farm_roles ON servers.farm_roleid = farm_roles.id WHERE farm_roles.role_id=?",
                    array($dbRole->id)
                );

                if ($this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE role_id=?", array($dbRole->id)) > 0) {
                    $role['status'] = 'In use';
                }
            }
        }

        if ($canAddImage) {
            try {
                $dbRole->__getNewRoleObject()->getImage($canAddImage['platform'], $canAddImage['cloudLocation']);
                $role['canAddImage'] = false;
            } catch (Exception $e) {
                $role['canAddImage'] = true;
            }
        }

        if ($extended) {
            $role['description'] = $dbRole->description;
            $role['images'] = [];
            foreach (RoleImage::find([['roleId' => $dbRole->id]]) as $image) {
                /* @var $image RoleImage */
                $ext = get_object_vars($image->getImage());
                $ext['software'] = $image->getImage()->getSoftwareAsString();

                $role['images'][] = [
                    'imageId' => $image->imageId,
                    'platform' => $image->platform,
                    'cloudLocation' => $image->cloudLocation,
                    'extended' => $ext
                ];
            }

            if ($role['status'] == 'In use' && $this->user->getAccountId() != 0) {
                $usedBy = $dbRole->getFarms($this->getEnvironmentId());
                $farms = $this->db->GetAll('SELECT id, name FROM farms WHERE env_id = ? AND id IN(' . implode(',', $usedBy) . ')', [$this->getEnvironmentId()]);
                $role['usedBy'] = [ 'farms' => $farms, 'cnt' => count($usedBy)];
            }
        }

        return $role;

    }

    public function editAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);

        $this->request->defineParams(array(
            'roleId' => array('type' => 'int')
        ));

        $params = array();

        $params['scriptData'] = \Scalr\Model\Entity\Script::getScriptingData($this->user->getAccountId(), $this->getEnvironmentId(true));
        $params['categories'] = $this->db->GetAll("SELECT * FROM role_categories WHERE env_id IS NULL OR env_id = ?", [$this->user->isScalrAdmin() ? null : $this->getEnvironmentId()]);
        $params['accountScripts'] = [];

        if (!$this->user->isScalrAdmin()) {
            foreach (self::loadController('Orchestration', 'Scalr_UI_Controller_Account2')->getOrchestrationRules() as $script) {
                $script['system'] = 'account';
                $params['accountScripts'][] = $script;
            }
        }

        $variables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->user->isScalrAdmin() ? 0 : $this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_ROLE);

        if ($this->getParam('roleId')) {
            $dbRole = DBRole::loadById($this->getParam('roleId'));

            if (!$this->user->isScalrAdmin()) {
                $this->user->getPermissions()->validate($dbRole);
            }

            $images = array();
            foreach (RoleImage::find([['roleId' => $dbRole->id]]) as $image) {
                /* @var $image RoleImage */
                $im = $image->getImage();
                $a = get_object_vars($image);
                if ($im) {
                    $b = get_object_vars($im);
                    $b['dtAdded'] = Scalr_Util_DateTime::convertTz($b['dtAdded']);
                    $b['software'] = $im->getSoftwareAsString();
                    $a['name'] = $im->name;
                    $a['hash'] = $im->hash;
                    $a['extended'] = $b;
                }
                $images[] = $a;
            }

            $params['role'] = array(
                'roleId'		=> $dbRole->id,
                'name'			=> $dbRole->name,
                'catId'         => $dbRole->catId,
                'os'			=> $dbRole->getOs()->name,
                'osId'          => $dbRole->osId,
                'osFamily'		=> $dbRole->getOs()->family,
                'osGeneration'	=> $dbRole->getOs()->generation,
                'osVersion'     => $dbRole->getOs()->version,
                'description'	=> $dbRole->description,
                'behaviors'		=> $dbRole->getBehaviors(),
                'images'		=> $images,
                'scripts'       => $dbRole->getScripts(),
                'dtadded'       => Scalr_Util_DateTime::convertTz($dbRole->dateAdded),
                'addedByEmail'  => $dbRole->addedByEmail,
                'chef'          => $dbRole->getProperties('chef.')
            );

            $params['role']['variables'] = $variables->getValues($dbRole->id);

            if ($this->user->isScalrAdmin()) {
                $params['roleUsage'] = array (
                    'farms'     => $dbRole->getFarmRolesCount(),
                    'instances' => $this->db->GetOne("SELECT COUNT(*) FROM servers LEFT JOIN farm_roles ON servers.farm_roleid = farm_roles.id WHERE farm_roles.role_id=?", array($dbRole->id))
                );
            } else {
                $params['roleUsage'] = array (
                    'farms'     => $dbRole->getFarmRolesCount($this->getEnvironmentId()),
                    'instances' => $this->db->GetOne("SELECT COUNT(*) FROM servers LEFT JOIN farm_roles ON servers.farm_roleid = farm_roles.id WHERE farm_roles.role_id=? AND env_id=?", array($dbRole->id, $this->getEnvironmentId()))
                );

            }

        } else {
            $params['role'] = array(
                'roleId'	    => 0,
                'name'			=> '',
                'arch'			=> 'x86_64',
                'agent'			=> 2,
                'description'	=> '',
                'behaviors'		=> array(),
                'images'		=> array(),
                'scripts'       => array(),
                'tags'          => array(),
                'variables'     => $variables->getValues()
            );
        }
        $this->response->page('ui/roles/edit.js', $params, array(
            'ui/roles/edit/overview.js',
            'ui/roles/edit/images.js',
            'ui/roles/edit/scripting.js',
            'ui/roles/edit/variables.js',
            'ui/roles/edit/chef.js',
            'ui/scripts/scriptfield.js',
            'ui/core/variablefield.js',
            'ui/services/chef/chefsettings.js'
        ), array(
            'ui/roles/edit.css',
            'ui/scripts/scriptfield.css'
        ));
    }

    //todo: remove
    public function edit2Action()
    {
        $this->editAction();
    }

    public function xSaveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);

        $this->request->defineParams(array(
            'roleId' => array('type' => 'int'),
            'behaviors' => array('type' => 'json'),
            'tags' => array('type' => 'json'),
            'description', 'name',
            'parameters' => array('type' => 'json'),
            'removedImages' => array('type' => 'json'),
            'images' => array('type' => 'json'),
            'properties' => array('type' => 'json'),
            'scripts' => array('type' => 'json'),
            'variables' => array('type' => 'json'),
            'chef' => array('type' => 'json')
        ));

        $id = $this->getParam('roleId');

        if ($id == 0) {
            if ($this->user->isScalrAdmin()) {
                $origin = ROLE_TYPE::SHARED;
                $envId = null;
                $clientId = null;
            } else {
                $origin = ROLE_TYPE::CUSTOM;
                $envId = $this->environment->id;
                $clientId = $this->user->getAccountId();
            }

            // TODO: validate role name via Scalr\Model\Entity\Role::validateName(), validate other fields

            $dbRole = new DBRole(0);

            $dbRole->generation = 2;
            $dbRole->origin = $origin;
            $dbRole->envId = $envId;
            $dbRole->clientId = $clientId;
            $dbRole->catId = $this->getParam('catId');
            $dbRole->name = $this->getParam('name');

            //TODO: VALIDATE osId

            $dbRole->osId = $this->getParam('osId');

            $dbRole->addedByEmail = $this->user->getEmail();
            $dbRole->addedByUserId = $this->user->getId();

            $dbRole->save();

            $dbRole->setBehaviors(array_values($this->getParam('behaviors')));

        } else {
            $dbRole = DBRole::loadById($id);

            if (!$this->user->isScalrAdmin()) {
                $this->user->getPermissions()->validate($dbRole);
            }
        }

        if ($dbRole->origin == ROLE_TYPE::CUSTOM && $this->user->getAccountId()) {
            $variables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_ROLE);
            $variables->setValues(is_array($this->getParam('variables')) ? $this->getParam('variables') : [], $dbRole->id);
        } else if ($this->user->isScalrAdmin()) {
            $variables = new Scalr_Scripting_GlobalVariables(0, 0, Scalr_Scripting_GlobalVariables::SCOPE_ROLE);
            $variables->setValues(is_array($this->getParam('variables')) ? $this->getParam('variables') : [], $dbRole->id);
        }

        $dbRole->clearProperties('chef.');
        if (!is_null($this->getParam('chef'))) {
            $dbRole->setProperties($this->getParam('chef'));
        }

        $dbRole->description = $this->getParam('description');

        $images = $this->getParam('images');
        if (!empty($images)) {
            foreach($images as $i) {
                $dbRole->__getNewRoleObject()->setImage($i['platform'], $i['cloudLocation'], $i['imageId'], $this->user->getId(), $this->user->getEmail());
            }
        }

        $scripts = $this->getParam('scripts');
        if (is_null($scripts))
            $scripts = [];
        $dbRole->setScripts($scripts);

        $dbRole->save();
        $this->response->data(['role' => $this->getInfo($dbRole->id, true)]);
        $this->response->success('Role saved');
    }

    //todo: remove
    public function xSave2Action()
    {
        $this->xSaveAction();
    }

    public function createAction()
    {
        $this->response->page('ui/roles/create.js');
    }
}
