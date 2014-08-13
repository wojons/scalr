<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity\Image;
use Scalr\UI\Request\JsonData;

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

        $e_platforms = $this->getEnvironment()->getEnabledPlatforms();
        $platforms = array();
        $l_platforms = SERVER_PLATFORMS::GetList();
        foreach ($e_platforms as $platform)
            $platforms[$platform] = $l_platforms[$platform];

        if ($filterPlatform)
            if (!$platforms[$filterPlatform])
                throw new Exception("Selected cloud not enabled in current environment");

        if ($filterCatId === 'shared') {
            $roles_sql = "SELECT DISTINCT(roles.id), platform FROM roles INNER JOIN role_images ON role_images.role_id = roles.id WHERE is_deprecated='0' AND generation = '2' AND env_id=?";
            $args[] = 0;
            if (!$filterPlatform)
                $roles_sql .= " AND role_images.platform IN ('".implode("','", array_keys($platforms))."')";
            else {
                $roles_sql .= " AND role_images.platform = ?";
                $args[] = $filterPlatform;
            }

            if ($filterOsFamily) {
                $roles_sql .= ' AND os_family = ?';
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

                // Set hvm flag
                $hvm = stristr($dbRole->name, '-hvm-') ? 1 : 0;

                // Set arch flag
                $architecture = (stristr($dbRole->name, '64-')) ? 'x86_64' : 'i386';

                $images = $dbRole->getImages(true);
                foreach ($images as $cloud => $locations) {
                    if (!$platforms[$cloud]) {
                        unset($images[$cloud]);
                    } else {
                        foreach ($locations as $location => $image) {
                            if (!$image['architecture']) {
                                $images[$cloud][$location]['architecture'] = $architecture;
                            }
                        }
                    }
                }

                $item = array(
                    'role_id'       => $dbRole->id,
                    'name'		    => $dbRole->name,
                    'behaviors'     => $dbRole->getBehaviors(),
                    'origin'        => $dbRole->origin,
                    'cat_name'	    => $dbRole->getCategoryName(),
                    'cat_id'	    => $dbRole->catId,
                    'os_name'       => $dbRole->os,
                    'os_family'     => $dbRole->osFamily,
                    'os_generation' => $dbRole->osGeneration,
                    'os_version'    => $dbRole->osVersion,
                    'images'        => $images,
                    'hvm'           => $hvm,
                    'ebs'           => 1,
                    'description'   => $dbRole->description
                );

                $software[$type]['roles'][] = $item;

                $software[$type]['name'] = $type;
                $software[$type]['ordering'] = isset($softwareOrdering[$type]) ? $softwareOrdering[$type] : 1000;
                $total++;
            }
            $software = array_values($software);
        } else {
            $args[] = $this->getEnvironmentId();
            $roles_sql = "
                SELECT DISTINCT(r.id), r.env_id
                FROM roles r
                LEFT JOIN roles_queue q ON r.id = q.role_id
                INNER JOIN role_images as i ON i.role_id = r.id
                WHERE generation = '2' AND env_id IN(0, ?)
                AND q.id IS NULL
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
                $roles_sql .= ' AND r.os_family = ?';
                $args[] = $filterOsFamily;
            }

            $roles_sql .= ' GROUP BY r.id';

            if ($filterCatId === 'recent') {
                $roles_sql .= ' ORDER BY r.id DESC LIMIT 10';
            }

            $dbRoles = $this->db->Execute($roles_sql, $args);

            $globalVars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);

            foreach ($dbRoles as $role) {
                $dbRole = DBRole::loadById($role['id']);

                $architecture = (stristr($dbRole->name, '64-')) ? 'x86_64' : 'i386';
                $images = $dbRole->getImages(true);
                foreach ($images as $cloud => $locations) {
                    if (!$platforms[$cloud]) {
                        unset($images[$cloud]);
                    } else if ($dbRole->envId == 0) {
                        foreach ($locations as $location => $image) {
                            if (!$image['architecture']) {
                                $images[$cloud][$location]['architecture'] = $architecture;
                            }
                        }
                    }
                }

                $roles[] = array(
                    'role_id'       => $dbRole->id,
                    'name'		    => $dbRole->name,
                    'behaviors'     => $dbRole->getBehaviors(),
                    'origin'        => $dbRole->origin,
                    'cat_name'	    => $dbRole->getCategoryName(),
                    'cat_id'	    => $dbRole->catId,
                    'os_name'       => $dbRole->os,
                    'os_family'     => $dbRole->osFamily,
                    'os_generation' => $dbRole->osGeneration,
                    'os_version'    => $dbRole->osVersion,
                    'images'        => $images,
                    'tags'			=> $dbRole->getTags(),
                    'variables'     => $globalVars->getValues($dbRole->id, 0, 0),
                    'shared'        => $role['env_id'] == 0,
                    'description'   => $dbRole->description,
                );
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

    public function xGetMigrateDetailsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);

        $role = DBRole::loadById($this->getParam('roleId'));
        if ($role->envId != 0)
            $this->user->getPermissions()->validate($role);
        else
            throw new Exception("You cannot migrate shared role");

        $images = $role->getImages();

        if (!$this->request->getEnvironment()->isPlatformEnabled(SERVER_PLATFORMS::EC2))
            throw new Exception('You can migrate image between regions only on EC2 cloud');

        if (!$images[SERVER_PLATFORMS::EC2])
            throw new Exception('You can migrate image between regions only on EC2 cloud');

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
        $locationsList = $platform->getLocations($this->environment);

        $availableSources = array_keys($images[SERVER_PLATFORMS::EC2]);
        $availableSourcesL = array();
        foreach ($availableSources as $sourceLocation)
            $availableSourcesL[] = array('cloudLocation' => $sourceLocation, 'name' => $locationsList[$sourceLocation]);

        foreach ($locationsList as $location => $name) {
            if (!in_array($location, $availableSources))
                $availableDestinations[] = array('cloudLocation' => $location, 'name' => $name);
        }

        $this->response->data(array(
            'availableSources' => $availableSourcesL,
            'availableDestinations' => $availableDestinations,
            'roleName' => $role->name,
            'roleId' => $role->id
        ));
    }

    public function xMigrateAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);

        $role = DBRole::loadById($this->getParam('roleId'));
        if ($role->envId != 0)
            $this->user->getPermissions()->validate($role);
        else
            throw new Exception("You cannot migrate shared role");

        $images = $role->getImages(true);

        $aws = $this->request->getEnvironment()->aws($this->getParam('sourceRegion'));
        $newImageId = $aws->ec2->image->copy(
            $this->getParam('sourceRegion'),
            $images[SERVER_PLATFORMS::EC2][$this->getParam('sourceRegion')]['image_id'],
            $role->name,
            "Scalr role: {$role->name}",
            null,
            $this->getParam('destinationRegion')
        );

        $role->setImage(
            $newImageId,
            SERVER_PLATFORMS::EC2,
            $this->getParam('destinationRegion'),
            $images[SERVER_PLATFORMS::EC2][$this->getParam('sourceRegion')]['szr_version'],
            $images[SERVER_PLATFORMS::EC2][$this->getParam('sourceRegion')]['architecture']
        );

        $this->response->success('Role successfully migrated');
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
        if ($dbRole->envId != 0)
            $this->user->getPermissions()->validate($dbRole);

        if (! preg_match("/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/si", $newRoleName))
            throw new Exception(_("Role name is incorrect"));

        $chkRoleId = $this->db->GetOne("SELECT id FROM roles WHERE name=? AND (env_id = '0' OR env_id = ?) LIMIT 1",
            array($newRoleName, $this->getEnvironmentId())
        );

        if ($chkRoleId) {
            if (!$this->db->GetOne("SELECT id FROM roles_queue WHERE role_id=? LIMIT 1", array($chkRoleId)))
                throw new Exception('Selected role name is already used. Please select another one.');
        }

        $dbRole->cloneRole($newRoleName, $this->user->getAccountId(), $this->environment->id);

        $this->response->success('Role successfully cloned');
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);
        $this->request->defineParams(array(
            'roles' => array('type' => 'json'),
            'removeFromCloud'
        ));

        if (is_array($this->getParam('roles'))) {
            foreach ($this->getParam('roles') as $id) {
                $dbRole = DBRole::loadById($id);

                if ($this->user->getType() != Scalr_Account_User::TYPE_SCALR_ADMIN)
                    $this->user->getPermissions()->validate($dbRole);

                if ($this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE role_id=? AND farmid IN (SELECT id FROM farms WHERE clientid=?)", array($dbRole->id, $this->user->getAccountId())) == 0) {

                    if ($this->getParam('removeFromCloud')) {
                        $images = $dbRole->getImages();
                        $doNotDeleteImage = false;

                        foreach ($images as $platform => $cloudLocations) {
                            foreach ($cloudLocations as $cloudLocation => $imageId) {
                                $usedBy = $this->db->GetOne("SELECT COUNT(*) FROM role_images WHERE image_id = ?", array($imageId));
                                if ($usedBy > 1)
                                    $doNotDeleteImage = true;
                            }
                        }

                        if (!$doNotDeleteImage)
                            $this->db->Execute("INSERT INTO roles_queue SET `role_id`=?, `action`=?, dtadded=NOW()", array($dbRole->id, 'remove'));
                        else
                            $dbRole->remove();

                    } else {
                        $dbRole->remove();
                    }
                }
                else
                    throw new Exception(sprintf(_("Role '%s' is used by at least one farm, and cannot be removed."), $dbRole->name));
            }
        }

        $this->response->success('Selected roles successfully removed');
    }

    public function builderAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE);

        $platforms = array();
        foreach (self::loadController('Platforms')->getEnabledPlatforms(false) as $k => $v) {
            if (in_array($k, array(SERVER_PLATFORMS::ECS, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::EC2, SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK))) {
                $platforms[$k] = array('name' => $v);
            }
        }

        $images = json_decode(file_get_contents(APPPATH . '/www/storage/images.json'), true);
        foreach ($platforms as $k => $v) {
            $p = PlatformFactory::NewPlatform($k);
            $platforms[$k]['images'] = $images[$k];
        }

        $server = null;
        if ($this->getParam('serverId')) {
            $dbServer = DBServer::LoadByID($this->getParam('serverId'));
            $this->user->getPermissions()->validate($dbServer);

            if ($dbServer->status != SERVER_STATUS::TEMPORARY) {
                throw new Exception('Server is not in role building state');
            }

            $bundleTaskId = $this->db->GetOne(
                "SELECT id FROM bundle_tasks WHERE server_id = ? ORDER BY dtadded DESC LIMIT 1",
                array($dbServer->serverId)
            );

            $server = array(
                'serverId'      => $dbServer->serverId,
                'platform'      => $dbServer->platform,
                'bundleTaskId'  => $bundleTaskId,
                'imageId'       => $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_IMAGE_ID)
            );
        }

        $this->response->page('ui/roles/builder.js', array(
            'platforms'     => $platforms,
            'environment'   => '#/account/environments/view?envId=' . $this->getEnvironmentId(),
            'server'        => $server
        ), array(), array('ui/roles/builder.css'));
    }

    /**
     * @param string $platform
     * @param string $architecture
     * @param JsonData $behaviors
     * @param string $roleName
     * @param bool $roleImage
     * @param string $imageId
     * @param string $cloudLocation
     * @param string $osfamily
     * @param string $hvm
     * @param JsonData $advanced
     * @param JsonData $chef
     * @throws Exception
     */
    public function xBuildAction($platform, $architecture, JsonData $behaviors, $roleName, $roleImage, $imageId, $cloudLocation, $osfamily, $hvm, JsonData $advanced, JsonData $chef)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE);

        if ($roleImage) {
            $roleName = '';
        } else {
            if (strlen($roleName) < 3)
                throw new Exception(_("Role name should be greater than 3 chars"));

            if (! preg_match("/^[A-Za-z0-9-]+$/si", $roleName))
                throw new Exception(_("Role name is incorrect"));

            $chkRoleId = $this->db->GetOne("SELECT id FROM roles WHERE name=? AND (env_id = '0' OR env_id = ?) LIMIT 1",
                array($roleName, $this->getEnvironmentId())
            );

            if ($chkRoleId) {
                if (!$this->db->GetOne("SELECT id FROM roles_queue WHERE role_id=? LIMIT 1", array($chkRoleId)))
                    throw new Exception('Selected role name is already used. Please select another one.');
            }
        }

        $behaviours = implode(",", array_values($behaviors));

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
                if ($osfamily == 'ubuntu')
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
                    if ($osfamily == 'oel') {
                        $launchOptions->serverType = 'm1.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    }
                    elseif ($osfamily == 'rhel') {
                        $launchOptions->serverType = 'm1.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    }
                    elseif ($osfamily == 'scientific') {
                        $launchOptions->serverType = 'm1.large';
                        $bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    }
                    else
                        $launchOptions->serverType = 'm1.small';
                }

                $launchOptions->userData = "#cloud-config\ndisable_root: false";
                break;
            case SERVER_PLATFORMS::GCE:
                $launchOptions->serverType = 'n1-standard-1';
                $locations = array_keys($platformObj->getLocations($this->environment));

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
            $roleName,
            SERVER_REPLACEMENT_TYPE::NO_REPLACE
        );

        $bundleTask = BundleTask::Create($creInfo, true);

        if ($bundleType)
            $bundleTask->bundleType = $bundleType;

        $bundleTask->createdById = $this->user->id;
        $bundleTask->createdByEmail = $this->user->getEmail();

        $bundleTask->osFamily = $osfamily;

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

    /**
    * Role manager
    */
    public function managerAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES);

        $this->response->page('ui/roles/manager.js', array(
            'categories' => $this->db->GetAll("SELECT * FROM `role_categories` WHERE env_id IN (0, ?)", array($this->user->isScalrAdmin() ? 0 : $this->getEnvironmentId())),
            'os' => $this->db->GetCol("SELECT DISTINCT os_family FROM `roles` WHERE env_id IN (0, ?) AND os_family <> '' ORDER BY os_family", array($this->user->isScalrAdmin() ? 0 : $this->getEnvironmentId()))
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
            'platform', 'cloudLocation', 'origin', 'query', 'catId', 'osFamily',
            'sort' => array('type' => 'json')
        ));

        if ($this->user->isScalrAdmin()) {
            $sql = 'SELECT DISTINCT(roles.id)
                    FROM roles
                    LEFT JOIN role_images ON role_images.role_id = roles.id
                    WHERE env_id = 0
                    AND :FILTER:';
            $args = array();
        } else {
            $sql = 'SELECT DISTINCT(roles.id)
                    FROM roles
                    LEFT JOIN role_images ON role_images.role_id = roles.id
                    WHERE (env_id = ? OR env_id = 0 AND role_images.role_id IS NOT NULL)
                    AND generation=?
                    AND :FILTER:';
            $args = array($this->getEnvironmentId(), 2);
        }

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

        if ($this->getParam('roleId')) {
            $sql .= ' AND roles.id = ?';
            $args[] = $this->getParam('roleId');
        }

        if ($this->getParam('catId')) {
            $sql .= ' AND roles.cat_id = ?';
            $args[] = $this->getParam('catId');
        }

        if ($this->getParam('osFamily')) {
            $sql .= ' AND roles.os_family = ?';
            $args[] = $this->getParam('osFamily');
        }

        if ($this->getParam('origin')) {
            $sql .= ' AND origin = ?';
            $args[] = $this->getParam('origin');
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

        $response = $this->buildResponseFromSql2($sql, array('name', 'os'), array('name'), $args);

        foreach ($response['data'] as &$row) {
            $row = $this->getInfo($row['id']);
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

    private function getInfo($roleId, $extended = false)
    {
        $dbRole = DBRole::loadById($roleId);

        if ($dbRole->envId != 0) {
            $this->user->getPermissions()->validate($dbRole);
        }

        if ($this->user->getAccountId() != 0) {
            $usedBy =$this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE (role_id=? OR new_role_id=?) AND farmid IN (SELECT id FROM farms WHERE env_id=?)", array($dbRole->id, $dbRole->id, $this->getEnvironmentId()));
        } else {
            $usedBy =$this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE role_id=? OR new_role_id=?", array($dbRole->id, $dbRole->id));
        }

        $status = 'Not used';
        if ($this->db->GetOne("SELECT id FROM roles_queue WHERE role_id=? LIMIT 1", array($dbRole->id))) {
            $status = 'Deleting';
        } elseif ($usedBy > 0) {
            $status = 'In use';
        }

        $role = array(
            'name'			=> $dbRole->name,
            'behaviors'		=> $dbRole->getBehaviors(),
            'id'			=> $dbRole->id,
            'client_id'		=> $dbRole->clientId,
            'env_id'		=> $dbRole->envId,
            'status'		=> $status,
            'origin'		=> $dbRole->origin,
            'os'			=> $dbRole->os,
            'osFamily'      => $dbRole->osFamily,
            'platforms'		=> $dbRole->getPlatforms(),
            'client_name'   => $dbRole->clientId == 0 ? 'Scalr' : 'Private'
        );

        try {
            $envId = $this->getEnvironmentId();
            $role['used_servers'] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE role_id=? AND env_id=?",
                array($dbRole->id, $envId)
            );
        }
        catch(Exception $e) {
            if ($this->user->getAccountId() == 0) {
                $role['used_servers'] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE role_id=?",
                    array($dbRole->id)
                );

                if ($this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE role_id=?", array($dbRole->id)) > 0) {
                    $role['status'] = 'In use';
                }
            }
        }

        if ($extended) {
            $role['software'] = $dbRole->getSoftwareList();
            $role['description'] = $dbRole->description;
            $role['images'] = array();
            $imDetails = $dbRole->getImages(true);
            if (!empty($imDetails) && (is_array($imDetails) || $imDetails instanceof \Traversable)) {
                foreach ($imDetails as $platform => $locations) {
                    foreach ($locations as $location => $imageInfo) {
                        $role['images'][] = array(
                            'image_id' 		=> $imageInfo['image_id'],
                            'platform' 		=> $platform,
                            'location' 		=> $location,
                            'architecture'	=> $imageInfo['architecture']
                        );
                    }
                }
            }

            if ($role['status'] == 'In use' && $this->user->getAccountId() != 0) {
                $usedBy = $this->db->GetCol("SELECT DISTINCT farmid FROM farm_roles WHERE (role_id=? OR new_role_id=?) AND farmid IN (SELECT id FROM farms WHERE env_id=?)", array($dbRole->id, $dbRole->id, $this->getEnvironmentId()));
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

        $params['scriptData'] = \Scalr\Model\Entity\Script::getScriptingData($this->user->getAccountId(), $this->getEnvironment() ? $this->getEnvironmentId() : NULL);
        $params['categories'] = $this->db->GetAll("SELECT * FROM role_categories WHERE env_id IN (0, ?)", array($this->user->isScalrAdmin() ? 0 : $this->getEnvironmentId()));

        if ($this->getParam('roleId')) {
            $dbRole = DBRole::loadById($this->getParam('roleId'));

            if (!$this->user->isScalrAdmin()) {
                $this->user->getPermissions()->validate($dbRole);
            }

            $images = array();
            $imDetails = $dbRole->getImages(true);
            if (!empty($imDetails) && (is_array($imDetails) || $imDetails instanceof \Traversable)) {
                foreach ($imDetails as $platform => $locations) {
                    foreach ($locations as $location => $imageInfo) {
                        $images[] = array(
                            'image_id' 		=> $imageInfo['image_id'],
                            'platform' 		=> $platform,
                            'location' 		=> $location,
                            'architecture'	=> $imageInfo['architecture']
                        );
                    }
                }
            }

            $params['role'] = array(
                'roleId'		=> $dbRole->id,
                'name'			=> $dbRole->name,
                'catId'         => $dbRole->catId,
                'os'			=> $dbRole->os,
                'osFamily'		=> $dbRole->osFamily,
                'osGeneration'	=> $dbRole->osGeneration,
                'osVersion'     => $dbRole->osVersion,
                'description'	=> $dbRole->description,
                'behaviors'		=> $dbRole->getBehaviors(),
                'images'		=> $images,
                'scripts'       => $dbRole->getScripts(),
                'dtadded'       => Scalr_Util_DateTime::convertTz($dbRole->dateAdded),
                'addedByEmail'  => $dbRole->addedByEmail,
                'software'      => $dbRole->getSoftwareList(),
                'tags'          => array_fill_keys($dbRole->getTags(), 1),
                'chef'          => $dbRole->getProperties('chef.')
            );

            $variables = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->user->isScalrAdmin() ? 0 : $this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_ROLE);
            $params['role']['variables'] = $variables->getValues($dbRole->id);

            if ($this->user->isScalrAdmin()) {
                $params['roleUsage'] = array (
                    'farms'     => $this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE role_id=? OR new_role_id=?", array($dbRole->id, $dbRole->id)),
                    'instances' => $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE role_id=?", array($dbRole->id))
                );
            } else {
                $params['roleUsage'] = array (
                    'farms'     => $this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE (role_id=? OR new_role_id=?) AND farmid IN (SELECT id FROM farms WHERE env_id=?)", array($dbRole->id, $dbRole->id, $this->getEnvironmentId())),
                    'instances' => $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE role_id=? AND env_id=?", array($dbRole->id, $this->getEnvironmentId()))
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
                'tags'          => array()
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
            'ux-boxselect.js',
            'ui/services/chef/chefsettings.js'
        ), array(
            'ui/roles/edit.css',
            'ui/scripts/scriptfield.css',
            'ui/core/variablefield.css'
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
            'description', 'name', 'os',
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
                $envId = 0;
                $clientId = 0;
            } else {
                $origin = ROLE_TYPE::CUSTOM;
                $envId = $this->environment->id;
                $clientId = $this->user->getAccountId();
            }

            $dbRole = new DBRole(0);

            $dbRole->generation = 2;
            $dbRole->origin = $origin;
            $dbRole->envId = $envId;
            $dbRole->clientId = $clientId;
            $dbRole->catId = $this->getParam('catId');
            $dbRole->name = $this->getParam('name');
            $dbRole->os = $this->getParam('os');
            $dbRole->osGeneration = $this->getParam('osGeneration');
            $dbRole->osFamily = $this->getParam('osFamily');
            $dbRole->osVersion = $this->getParam('osVersion');

            $dbRole->addedByEmail = $this->user->getEmail();
            $dbRole->addedByUserId = $this->user->getId();

            $dbRole->save();

            $dbRole->setBehaviors(array_values($this->getParam('behaviors')));
            if ($this->user->isScalrAdmin()) {
                $dbRole->setTags($this->getParam('tags'));
            }

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

        if ($dbRole->origin == ROLE_TYPE::CUSTOM) {
            //chef
            $dbRole->clearProperties('chef.');
            $dbRole->setProperties($this->getParam('chef'));
        }

        $dbRole->description = $this->getParam('description');

        $removedImages = $this->getParam('removedImages');
        if (!empty($removedImages)) {
            foreach ($removedImages as $imageId) {
                $dbRole->removeImage($imageId);
            }
        }

        foreach ($this->getParam('images') as $image) {
            $image = (array)$image;
            $dbRole->setImage(
                $image['image_id'],
                $image['platform'],
                $image['location'] ? $image['location'] : '',
                $image['szr_version'],
                $image['architecture']
            );
        }

        $dbRole->setScripts($this->getParam('scripts'));

        $dbRole->save();
        $this->response->data(array(
            'role' => $this->getInfo($dbRole->id, true)
        ));
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
