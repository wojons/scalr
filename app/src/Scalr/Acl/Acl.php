<?php

namespace Scalr\Acl;

use Scalr\Acl\Exception;
use Scalr\Acl\Resource;
use Scalr\Acl\Role;
use Scalr\Modules\PlatformFactory;

/**
 * Scalr ACL class
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    30.07.2013
 */
class Acl
{

    // Associative groups of the ACL resources.
    // This group is needed just to visually separate resources by belonging to group.
    const GROUP_ADMINISTRATION = 'Account management';
    const GROUP_ENVADMINISTRATION = 'Environment management';
    const GROUP_GENERAL = 'General';
    const GROUP_SECURITY = 'Security';
    const GROUP_FARMS = 'Cloud management';
    const GROUP_SERVICES = 'Services';
    const GROUP_LOGS = 'Logs';
    const GROUP_DNS = 'Dns';
    const GROUP_DEPLOYMENTS = 'Deployments';
    const GROUP_DATABASES = 'Databases';
    const GROUP_AWS = 'AWS';
    const GROUP_CLOUDSTACK = 'CloudStack';
    const GROUP_OPENSTACK = 'OpenStack';
    const GROUP_GCE = 'GCE';
    const GROUP_ANALYTICS = 'Analytics';
    // .. add more associative groups here
    // const GROUP_FOOGROUP = 'Fooname';

    // Once determined the value of resource_id must remain unchanged and be unique.
    // These values have to be synchronyzed with mysql data
    // (acl_role_resources, acl_account_role_resources and acl_account_role_resource_permissions tables).
    // All defined constants must be referenced in the \Scalr\Acl\Resource\Definition class.
    const RESOURCE_FARMS = 0x100;
    const RESOURCE_FARMS_ALERTS = 0x101;
    const RESOURCE_FARMS_STATISTICS = 0x103;
    const RESOURCE_FARMS_ROLES = 0x104;
    const RESOURCE_FARMS_SERVERS = 0x105;
    const RESOURCE_FARMS_IMAGES = 0x107;

    const RESOURCE_CLOUDSTACK_VOLUMES = 0x110;
    const RESOURCE_CLOUDSTACK_SNAPSHOTS = 0x111;
    const RESOURCE_CLOUDSTACK_PUBLIC_IPS = 0x112;

    const RESOURCE_OPENSTACK_VOLUMES = 0x210;
    const RESOURCE_OPENSTACK_SNAPSHOTS = 0x211;
    const RESOURCE_OPENSTACK_PUBLIC_IPS = 0x212;
    const RESOURCE_OPENSTACK_ELB = 0x213;

    const RESOURCE_GCE_STATIC_IPS = 0x230;
    const RESOURCE_GCE_PERSISTENT_DISKS = 0x231;
    const RESOURCE_GCE_SNAPSHOTS = 0x232;

    const RESOURCE_AWS_VOLUMES = 0x120;
    const RESOURCE_AWS_SNAPSHOTS = 0x121;
    const RESOURCE_AWS_ELB = 0x122;
    const RESOURCE_AWS_CLOUDWATCH = 0x123;
    const RESOURCE_AWS_IAM = 0x124;
    const RESOURCE_AWS_RDS = 0x125;
    const RESOURCE_AWS_ELASTIC_IPS = 0x126;
    const RESOURCE_AWS_S3 = 0x127;
    const RESOURCE_AWS_ROUTE53 = 0x128;

    const RESOURCE_SECURITY_SSH_KEYS = 0x130;
    const RESOURCE_SECURITY_RETRIEVE_WINDOWS_PASSWORDS = 0x131;
    const RESOURCE_SECURITY_SECURITY_GROUPS = 0x132;

    const RESOURCE_LOGS_API_LOGS = 0x140;
    const RESOURCE_LOGS_SCRIPTING_LOGS = 0x141;
    const RESOURCE_LOGS_SYSTEM_LOGS = 0x142;
    const RESOURCE_LOGS_EVENT_LOGS = 0x143;

    const RESOURCE_SERVICES_APACHE = 0x150;
    const RESOURCE_SERVICES_ENVADMINISTRATION_CHEF = 0x151;
    const RESOURCE_SERVICES_SSL = 0x152;
    const RESOURCE_SERVICES_RABBITMQ = 0x153;
    const RESOURCE_SERVICES_ADMINISTRATION_CHEF = 0x154;

    const RESOURCE_ENVADMINISTRATION_GLOBAL_VARIABLES = 0x160;
    const RESOURCE_GENERAL_CUSTOM_SCALING_METRICS = 0x163;
    const RESOURCE_GENERAL_CUSTOM_EVENTS = 0x164;
    const RESOURCE_GENERAL_SCHEDULERTASKS = 0x165;
    const RESOURCE_ENVADMINISTRATION_WEBHOOKS = 0x166;
    const RESOURCE_ADMINISTRATION_GLOBAL_VARIABLES = 0x167;
    const RESOURCE_ADMINISTRATION_WEBHOOKS = 0x168;

    const RESOURCE_DB_BACKUPS = 0x170;
    const RESOURCE_DB_DATABASE_STATUS = 0x171;
    const RESOURCE_DB_SERVICE_CONFIGURATION = 0x172;

    const RESOURCE_DEPLOYMENTS_APPLICATIONS = 0x180;
    const RESOURCE_DEPLOYMENTS_SOURCES = 0x181;
    const RESOURCE_DEPLOYMENTS_TASKS = 0x182;

    const RESOURCE_DNS_ZONES = 0x190;

    const RESOURCE_ENVADMINISTRATION_GOVERNANCE = 0x161;
    const RESOURCE_ADMINISTRATION_BILLING = 0x162;
    const RESOURCE_ENVADMINISTRATION_ENV_CLOUDS = 0x202;

    const RESOURCE_ANALYTICS_PROJECTS = 0x240;
    const RESOURCE_ADMINISTRATION_ANALYTICS = 0x241;
    const RESOURCE_ENVADMINISTRATION_ANALYTICS = 0x242;

    const RESOURCE_ADMINISTRATION_ORCHESTRATION = 0x250;
    const RESOURCE_ADMINISTRATION_SCRIPTS = 0x106;

    // ... add more resource_id here
    // const RESOURCE_FOO = 0x101;

    // ID of the unique permissions of resources.
    // Values need to be defined in the lowercase less than 64 characters.
    // These values have to be cynchronyzed with mysql data
    // (acl_role_resource_permissions, acl_account_role_resource_permissions tables)
    const PERM_FARMS_MANAGE = 'manage';
    const PERM_FARMS_CLONE = 'clone';
    const PERM_FARMS_LAUNCH = 'launch';
    const PERM_FARMS_TERMINATE = 'terminate';
    const PERM_FARMS_NOT_OWNED_FARMS = 'not-owned-farms';

    const PERM_FARMS_ROLES_MANAGE = 'manage';
    const PERM_FARMS_ROLES_CLONE = 'clone';
    const PERM_FARMS_ROLES_BUNDLETASKS = 'bundletasks';
    const PERM_FARMS_ROLES_CREATE = 'create';

    const PERM_FARMS_IMAGES_MANAGE = 'manage';
    const PERM_FARMS_IMAGES_CREATE = 'create';

    const PERM_FARMS_SERVERS_SSH_CONSOLE = 'ssh-console';

    const PERM_ADMINISTRATION_SCRIPTS_MANAGE = 'manage';
    const PERM_ADMINISTRATION_SCRIPTS_EXECUTE = 'execute';
    const PERM_ADMINISTRATION_SCRIPTS_FORK = 'fork';

    const PERM_DB_BACKUPS_REMOVE = 'remove';

    const PERM_DB_DATABASE_STATUS_PMA = 'phpmyadmin';

    const PERM_GENERAL_CUSTOM_EVENTS_FIRE = 'fire';

    const PERM_ADMINISTRATION_ANALYTICS_MANAGE_PROJECTS = 'manage-projects';

    const PERM_ADMINISTRATION_ANALYTICS_ALLOCATE_BUDGET = 'allocate-budget';

    // ... add more permission_id for existing resource here
    // const PERM_FOORESOURCE_FOOPERMISSIONNAME

    const ROLE_ID_FULL_ACCESS = 10;
    const ROLE_ID_EVERYTHING_FORBIDDEN = 1;

    /**
     * The list of the disabled resources
     *
     * @var array
     */
    private static $disabledResources;

    /**
     * ADODB instance
     *
     * @var \ADODB_mysqli
     */
    private $db;

    /**
     * Sets database instance to object
     *
     * @param   \ADODB_mysqli $db The Database instance
     * @return  \Scalr\Acl\Acl
     */
    public function setDb($db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Gets database instance
     *
     * @return ADODB_mysqli
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Gets mnemonic names for all resources using constants
     *
     * This method excludes disabled resources.
     *
     * @return  array Returns all resources looks like array(resourceId => mnemonicIndex)
     */
    public static function getResourcesMnemonic()
    {
        $res = array();
        $refl = new \ReflectionClass(get_called_class());
        foreach ($refl->getConstants() as $name => $resourceId) {
            if (strpos($name, 'RESOURCE_') === 0 && Resource\Definition::has($resourceId)) {
                $res[$resourceId] = substr($name, 9);
            }
        }

        //Removes disabled resources
        foreach (self::getDisabledResources() as $resourceId) {
            if (isset($res[$resourceId])) {
                unset($res[$resourceId]);
            }
        }

        return $res;
    }

    /**
     * Gets all ACl groups
     *
     * @return  array Returns the list of the Groups looks like array(name => sortOrder)
     */
    public static function getGroups()
    {
        $res = array();
        $cnt = 1;
        $refl = new \ReflectionClass(get_called_class());
        foreach ($refl->getConstants() as $name => $group) {
            if (strpos($name, "GROUP_") === 0) {
                $res[$group] = $cnt;
                $cnt++;
            }
        }
        return $res;
    }

    /**
     * Returns the list of the disabled resources for current installation
     *
     * It looks in the config.
     *
     * @return  array Returns array of the disabled ACL resources
     */
    public static function getDisabledResources()
    {
        if (!isset(self::$disabledResources)) {
            self::$disabledResources = array();

            $allowedClouds = \Scalr::config('scalr.allowed_clouds');

            if (!\Scalr::config('scalr.dns.global.enabled')) {
                //If DNS is disabled in the config we should not use it in the ACL
                self::$disabledResources[] = self::RESOURCE_DNS_ZONES;
            }

            if (!\Scalr::config('scalr.billing.enabled')) {
                //If Billing is disabled in the config we should not use it in the ACL
                self::$disabledResources[] = self::RESOURCE_ADMINISTRATION_BILLING;
            }

            if (array_intersect(PlatformFactory::getCloudstackBasedPlatforms(), $allowedClouds) === array()) {
                //If any cloudstack based cloud is not allowed we should not use these permissions
                self::$disabledResources[] = self::RESOURCE_CLOUDSTACK_VOLUMES;
                self::$disabledResources[] = self::RESOURCE_CLOUDSTACK_SNAPSHOTS;
                self::$disabledResources[] = self::RESOURCE_CLOUDSTACK_PUBLIC_IPS;
            }

            if (array_intersect(PlatformFactory::getOpenstackBasedPlatforms(), $allowedClouds) === array()) {
                //If any openstack base cloud is not allowed we should not use these permissions
                self::$disabledResources[] = self::RESOURCE_OPENSTACK_VOLUMES;
                self::$disabledResources[] = self::RESOURCE_OPENSTACK_SNAPSHOTS;
                self::$disabledResources[] = self::RESOURCE_OPENSTACK_PUBLIC_IPS;
            }
        }

        return self::$disabledResources;
    }

    /**
     * Gets auto-generated ID for account role usage.
     *
     * @return string Returns 20 characters length unique string
     */
    public static function generateAccountRoleId()
    {
        return substr(sha1(uniqid()), 0, 20);
    }

    /**
     * Gets role object for the specified ID
     *
     * @param   int                  $roleId The ID of the ACL role.
     * @return  Role\RoleObject|null Returns role object for the specified ID
     */
    public function getRole($roleId)
    {
        $rec = $this->db->GetRow("SELECT `role_id`, `name` FROM `acl_roles` WHERE `role_id` = ?", array($roleId));
        if ($rec !== false) {
            $role = new Role\RoleObject($rec['role_id'], $rec['name']);
            $this->loadRolePermissions($role);
        }
        return isset($role) ? $role : null;
    }

    /**
     * Gets all global predefined roles
     *
     * @return  \ArrayObject Returns all global predefined roles
     * @throws  Exception\RoleObjectException
     */
    public function getRoles()
    {
        $roles = new \ArrayObject(array());
        $res = $this->db->Execute("SELECT `role_id` as `id` FROM `acl_roles`");
        while ($rec = $res->FetchRow()) {
            $roles[$rec['id']] = $this->getRole($rec['id']);
        }

        return $roles;
    }

    /**
     * Gets all base roles
     *
     * This method guarantees that all resources with unique permissions will be returned.
     *
     * @return array Returns array of all base roles
     */
    public function getRolesComputed()
    {
        $groups = self::getGroups();
        $baseRoles = array();
        foreach ($this->getRoles() as $role) {
            $baseRole = array(
                'id'        => $role->getRoleId(),
                'name'      => $role->getName(),
                'resources' => null
            );
            foreach ($role->getIteratorResources() as $resource) {
                $r = array(
                    'id'          => $resource->getResourceId(),
                    'granted'     => $role->isAllowed($resource->getResourceId()) ? 1 : 0,
                    'name'        => $resource->getName(),
                    'group'       => $resource->getGroup(),
                    'groupOrder'  => (isset($groups[$resource->getGroup()]) ? $groups[$resource->getGroup()] : 0),
                    'permissions' => null
                );
                foreach ($resource->getPermissions() as $permissionId => $permissionDescription) {
                    $r['permissions'][$permissionId] = $role->isAllowed($resource->getResourceId(), $permissionId) ? 1 : 0;
                }
                $baseRole['resources'][] = $r;
            }
            $baseRoles[] = $baseRole;
        }
        return $baseRoles;
    }

    /**
     * Gets all account level roles
     *
     * This method guarantees that all resources and unique permissions will be returned.
     *
     * @param   int    $accountId  The ID of the account
     * @return  array  Returns all account level roles
     */
    public function getAccountRolesComputed($accountId)
    {
        $accountRoles = array();
        foreach($this->getAccountRoles($accountId) as $role) {
            $accountRoles[] = $this->getAccountRoleComputed($role);
        }
        return $accountRoles;
    }

    /**
     * Gets account role computed object
     *
     * @param  string|\Scalr\Acl\Role\AccountRoleObject $role
     *         The Id of the account role or object that represetns account role
     *
     * @return array  Returns account role with all resources
     */
    public function getAccountRoleComputed($role)
    {
        if (is_string($role)) {
            $role = $this->getAccountRole($role);
        } else if (!($role instanceof Role\AccountRoleObject)) {
            throw new \InvalidArgumentException(
                'ID of account role or \Scalr\Acl\Role\AccountRoleObject is expected.'
            );
        }

        $groups = self::getGroups();

        if ($role instanceof Role\AccountRoleObject) {
            $accountRole = array(
                'id'         => $role->getRoleId(),
                'name'       => $role->getName(),
                'baseRoleId' => $role->getBaseRole()->getRoleId(),
                'color'      => $role->getColorHex(),
                'automatic'  => $role->isAutomatic(),
                'resources'  => null
            );
            foreach ($role->getIteratorResources() as $resource) {
                $accountRoleResource = array(
                    'id'          => $resource->getResourceId(),
                    'granted'     => $role->isAllowed($resource->getResourceId()) ? 1 : 0,
                    'name'        => $resource->getName(),
                    'group'       => $resource->getGroup(),
                    'groupOrder'  => (isset($groups[$resource->getGroup()]) ? $groups[$resource->getGroup()] : 0),
                    'permissions' => null
                );
                foreach ($resource->getPermissions() as $permissionId => $permissionDescription) {
                    $accountRoleResource['permissions'][$permissionId] = $role->isAllowed($resource->getResourceId(), $permissionId) ? 1 : 0;
                }
                $accountRole['resources'][] = $accountRoleResource;
            }
        }

        return isset($accountRole) ? $accountRole : null;
    }


    /**
     * Gets all global predefined resources
     *
     * @return  \ArrayObject Returns the list of all global predefined resources
     */
    public static function getResources($raw = false)
    {
        return Resource\Definition::getAll($raw);
    }

    /**
     * Loads permissions into role object
     *
     * @param   Role\RoleObject $role  A role object
     */
    protected function loadRolePermissions(Role\RoleObject $role)
    {
        $sAcc = $role instanceof Role\AccountRoleObject ? 'account_' : '';

        $res = $this->db->Execute("
            SELECT
                rr.`" . $sAcc . "role_id` as `role_id`,
                rr.`resource_id`, rr.`granted`, rp.`perm_id`,
                rp.`granted` AS `perm_granted`
            FROM `acl_" . $sAcc . "role_resources` rr
            LEFT JOIN `acl_" . $sAcc . "role_resource_permissions` rp
                ON rp.`" . $sAcc . "role_id` = rr.`" . $sAcc . "role_id`
                AND rp.`resource_id` = rr.`resource_id`
            WHERE rr.`" . $sAcc . "role_id` = ?
        ", array($role->getRoleId()));

        if ($res) {
            $resources = $role->getResources();
            while ($rec = $res->FetchRow()) {
                if (!isset($resources[$rec['resource_id']])) {
                    //Adds resource to role object
                    $resource = new Role\RoleResourceObject($rec['role_id'], $rec['resource_id'], $rec['granted']);
                    $role->appendResource($resource);
                } else {
                    $resource = $resources[$rec['resource_id']];
                }
                if ($rec['perm_id'] !== null) {
                    $permission = new Role\RoleResourcePermissionObject(
                        $rec['role_id'], $rec['resource_id'], $rec['perm_id'], $rec['perm_granted']
                    );
                    //We should append permission only if it's been declared in the definition.
                    $resourceDefinition = Resource\Definition::get($resource->getResourceId());
                    if ($resourceDefinition->hasPermission($permission->getPermissionId())) {
                        $resource->appendPermission($permission);
                    }
                    unset($permission);
                }
                unset($resource);
            }
        }
    }

    /**
     * Gets role of account level
     *
     * @param   string    $accountRoleId  The ID of the account role
     * @param   int       $accountId      optional Restricts result by identifier of the account
     * @return  Scalr\Acl\Role\AccountRoleObject    Returns AccountRoleObject for the specified ID of account role.
     *                                    It returns null if object does not exist.
     * @throws  Exception\AclException
     */
    public function getAccountRole($accountRoleId, $accountId = null)
    {
        $rec = $this->db->GetRow("
            SELECT `account_role_id`, `account_id`, `role_id`, `name`, `color`, `is_automatic`
            FROM `acl_account_roles`
            WHERE `account_role_id` = ?
            " . (!empty($accountId) ? " AND `account_id` = " . intval($accountId) : "") . "
            LIMIT 1
        ", array($accountRoleId));

        if ($rec !== false) {
            $role = $this->getAccountRoleByRow($rec);
        }

        return isset($role) ? $role : null;
    }

    /**
     * Gets Account role using record from acl_account_role
     *
     * @param   array     $rec  Record from acl_account_role
     * @throws  Exception\AclException
     * @return  Role\AccountRoleObject    Returns AccountRoleObject for the specified ID of account role.
     *                                    It returns null if object does not exist.
     */
    public function getAccountRoleByRow($rec)
    {
        if ($rec !== false) {
            $baseRole = $this->getRole($rec['role_id']);

            if (!$baseRole) {
                //Hardly probable case because of that foreign key is responsible for this
                throw new Exception\AclException(sprintf(
                    "Base ACL role (role_id:%d) does not exist.", $rec['role_id']
                ));
            }

            $role = new Role\AccountRoleObject(
                $baseRole, $rec['account_id'], $rec['account_role_id'], $rec['name'], $rec['color'], $rec['is_automatic']
            );
            $this->loadRolePermissions($role);
        }

        return isset($role) ? $role : null;
    }

    /**
     * Gets all account roles
     *
     * @param   int          $accountId  The ID of the account
     * @return  \ArrayObject Returns all account roles for the specified account.
     * @throws  Exception\RoleObjectException
     */
    public function getAccountRoles($accountId)
    {
        $roles = new \ArrayObject(array());
        $res = $this->db->Execute(
            "SELECT `account_role_id` as `id` FROM `acl_account_roles` WHERE `account_id` = ?",
            array($accountId)
        );
        while ($rec = $res->FetchRow()) {
            $roles[$rec['id']] = $this->getAccountRole($rec['id']);
        }

        return $roles;
    }

    /**
     * Gets full access account role
     *
     * @param   int       $accountId        The identifier of the client's account
     * @param   bool      $createIfNotExist optional If true it will create full access role when it does not exist.
     * @return  \Scalr\Acl\Role\AccountRoleObject  Returns AccountRoleObject
     */
    public function getFullAccessAccountRole($accountId, $createIfNotExist = false)
    {
        $accountRoleId = $this->db->GetOne("
            SELECT r.`account_role_id` `id`
            FROM `acl_account_roles` r
            WHERE r.`account_id` = ?
            AND r.`role_id` = ?
            AND r.`is_automatic` = 1
            LIMIT 1
        ", array(
            $accountId,
            Acl::ROLE_ID_FULL_ACCESS,
        ));

        return !empty($accountRoleId) ? $this->getAccountRole($accountRoleId) :
               ($createIfNotExist ? $this->createFullAccessAccountRole($accountId) : null);
    }

    /**
     * Creates full access account role
     *
     * @param   int     $accountId      The identifier of the client's account
     * @return  \Scalr\Acl\Role\AccountRoleObject  Returns AccountRoleObject
     */
    public function createFullAccessAccountRole($accountId)
    {
        $accountRoleId = self::generateAccountRoleId();
        //Marked with black colour
        $this->db->Execute("
            INSERT `acl_account_roles` (account_role_id, account_id, role_id, name, color, is_automatic)
            SELECT ?, ?, role_id, 'Full access (no admin)', 0, 1
            FROM `acl_roles`
            WHERE `role_id` = ?
        ", array(
            $accountRoleId,
            $accountId,
            Acl::ROLE_ID_FULL_ACCESS
        ));

        //Disables administration section in Full access ACL
        foreach (array(self::RESOURCE_ADMINISTRATION_BILLING, self::RESOURCE_ENVADMINISTRATION_ENV_CLOUDS, self::RESOURCE_ENVADMINISTRATION_GOVERNANCE, self::RESOURCE_ADMINISTRATION_ORCHESTRATION) as $resourceId) {
            $this->db->Execute("
                INSERT IGNORE `acl_account_role_resources` (account_role_id, resource_id, granted)
                VALUES (?, ?, 0)
            ", array(
                $accountRoleId,
                $resourceId,
            ));
        }

        return $this->getAccountRole($accountRoleId);
    }

    /**
     * Creates No access account role
     *
     * @param   int     $accountId      The identifier of the client's account
     * @return  \Scalr\Acl\Role\AccountRoleObject  Returns AccountRoleObject
     */
    public function createNoAccessAccountRole($accountId)
    {
        $accountRoleId = self::generateAccountRoleId();
        //Marked with red colour
        $this->db->Execute("
            INSERT `acl_account_roles` (account_role_id, account_id, role_id, name, color, is_automatic)
            SELECT ?, ?, role_id, name, 14623232, 1
            FROM `acl_roles`
            WHERE `role_id` = ?
        ", array(
            $accountRoleId,
            $accountId,
            Acl::ROLE_ID_EVERYTHING_FORBIDDEN
        ));

        return $this->getAccountRole($accountRoleId);
    }

    /**
     * Gets no access account role
     *
     * @param   int       $accountId        The identifier of the client's account
     * @param   bool      $createIfNotExist optional If true it will create no access role when it does not exist.
     * @return  \Scalr\Acl\Role\AccountRoleObject  Returns AccountRoleObject
     */
    public function getNoAccessAccountRole($accountId, $createIfNotExist = false)
    {
        $accountRoleId = $this->db->GetOne("
            SELECT r.`account_role_id` `id`
            FROM `acl_account_roles` r
            WHERE r.`account_id` = ?
            AND r.`role_id` = ?
            AND r.`is_automatic` = 1
            LIMIT 1
        ", array(
            $accountId,
            Acl::ROLE_ID_EVERYTHING_FORBIDDEN
        ));

        return !empty($accountRoleId) ? $this->getAccountRole($accountRoleId) :
               ($createIfNotExist ? $this->createNoAccessAccountRole($accountId) : null);
    }

    /**
     * Gets missing records for predefined global ACL roles: Full Access and Everything forbidden.
     *
     * @return string Returns sql script output that adds missing records
     */
    public function getMissingRecords()
    {
        $output = array();

        foreach (array(array(self::ROLE_ID_FULL_ACCESS, true), array(self::ROLE_ID_EVERYTHING_FORBIDDEN, false)) as $v) {
            $roleId = $v[0];
            $allowed = $v[1];

            $role = $this->getRole($roleId);
            $roleResources = $role->getResources();

            foreach (Resource\Definition::getAll() as $resourceId => $resourceDefinition) {
                // Absence of the record is considered as forbidden
                if (!$allowed && !isset($roleResources[$resourceId])) continue;

                if (!isset($roleResources[$resourceId])) {
                    $output .= sprintf(
                        "INSERT `acl_role_resources` "
                      . "SET `role_id` = %d, `resource_id` = 0x%x, `granted` = %d;\n",
                        $roleId, $resourceId, (int)$allowed
                    );
                    $roleResources[$resourceId] = new Role\RoleResourceObject($roleId, $resourceId, $allowed);
                }

                $resource = $roleResources[$resourceId];

                if (($resource->isGranted() != $allowed)) {
                    $output .= sprintf(
                        "UPDATE `acl_role_resources` "
                      . "SET `granted` = %d; WHERE `role_id` = %d AND `resource_id` = 0x%x;\n",
                        (int)$allowed, $roleId, $resourceId
                    );
                }

                $permissions = $resource->getPermissions();
                foreach ($resourceDefinition->getPermissions() as $permissionId => $description) {
                    // Absence of the record is considered as forbidden
                    if (!$allowed && !isset($permissions[$permissionId])) continue;

                    if (!isset($permissions[$permissionId])) {
                        $output .= sprintf(
                            "INSERT `acl_role_resource_permissions` "
                          . "SET `role_id` = %d, `resource_id` = 0x%x, `perm_id` = '%s', `granted` = %d;\n",
                            $roleId, $resourceId, $permissionId, (int)$allowed
                        );
                        $permissions[$permissionId] = new Role\RoleResourcePermissionObject($roleId, $resourceId, $permissionId, $allowed);
                    }

                    $permission = $permissions[$permissionId];

                    if (($permission->isGranted() != $allowed)) {
                        $output .= sprintf(
                            "UPDATE `acl_role_resource_permissions` SET `granted` = %d; "
                          . "WHERE `role_id` = %d AND `resource_id` = 0x%x AND `perm_id` = '%s';\n",
                            (int)$allowed, $roleId, $resourceId, $permissionId
                        );
                    }
                }
                unset($permissions);
            }

            unset($role);
            unset($roleResources);
        }
        return $output;
    }

    /**
     * Deletes account role
     *
     * @param   string     $accountRoleId The ID of account role
     * @param   string     $accountId     The ID of account
     * @return  bool       Returns true on success or throws an exception
     */
    public function deleteAccountRole($accountRoleId, $accountId)
    {
        try {
            $this->db->Execute('START TRANSACTION');
            $this->db->Execute("
                DELETE FROM `acl_account_roles` WHERE `account_role_id` = ? AND account_id = ?
            ", array(
                $accountRoleId,
                $accountId
            ));
            $this->db->Execute('COMMIT');
        } catch (\Exception $e) {
            //There are one or more users which are associated with this ACL
            $this->db->Execute('ROLLBACK');
            if ($e instanceof \ADODB_Exception && $e->getCode() == 1451) {
                try {
                    $cnt = 0;
                    $users = '';
                    $r = $this->db->Execute("
                        SELECT SQL_CALC_FOUND_ROWS COALESCE(IF(au.fullname != '', au.fullname, NULL), au.email) as name
                        FROM `account_team_user_acls` tua
                        JOIN `account_team_users` tu ON tu.`id` = tua.`account_team_user_id`
                        JOIN `account_users` au ON au.`id` = tu.`user_id`
                        WHERE tua.`account_role_id` = ?
                        GROUP BY au.`id`
                        LIMIT 3
                    ", array(
                        $accountRoleId
                    ));
                    $cnt = $this->db->GetOne('SELECT FOUND_ROWS()');
                    while ($rec = $r->FetchRow()) {
                        $users .= $rec['name'] . " ,";
                    }
                    $users = rtrim($users, " ,");

                    if ($cnt == 0) {
                        $cntTeams = 0;
                        $teams = '';
                        $r = $this->db->Execute("
                            SELECT SQL_CALC_FOUND_ROWS at.name
                            FROM `account_teams` at
                            WHERE at.`account_role_id` = ? AND at.`account_id` = ?
                            LIMIT 3
                        ", array(
                            $accountRoleId, $accountId
                        ));
                        $cntTeams = $this->db->GetOne('SELECT FOUND_ROWS()');
                        while ($rec = $r->FetchRow()) {
                            $teams .= $rec['name'] . " ,";
                        }
                        $teams = rtrim($teams, " ,");
                    }
                } catch (\Exception $sub) {
                }

                if ($cnt > 0)
                    throw new Exception\AclException(sprintf(
                        "This ACL cannot be removed because there %s %d user%s (%s) to whom it is applied.",
                        ($cnt > 1 ? 'are' : 'is'),
                        $cnt,
                        ($cnt > 1 ? 's' : ''),
                        $users . ($cnt > 3 ? ' ...' : '')
                    ));
                else
                    throw new Exception\AclException(sprintf(
                        "This ACL cannot be removed because there %s %d team%s (%s) to which it is applied.",
                        ($cntTeams > 1 ? 'are' : 'is'),
                        $cntTeams,
                        ($cntTeams > 1 ? 's' : ''),
                        $teams . ($cntTeams > 3 ? ' ...' : '')
                    ));
            } else throw $e;
        }

        return true;
    }

    /**
     * Gets account roles superposition by specified ID of team
     *
     * @param   \Scalr_Account_User|int  $userId       The user's object or ID of the user
     * @param   int                      $teamId       The ID of the team
     * @param   int                      $accountId    The ID of the client's account
     * @return  \Scalr\Acl\Role\AccountRoleSuperposition Returns the list of the roles of account level by specified team
     */
    public function getUserRolesByTeam($user, $teamId, $accountId)
    {
        $ret = new Role\AccountRoleSuperposition(array());

        if ($user instanceof \Scalr_Account_User) {
            $userId = $user->getId();
            $ret->setUser($user);
        } else {
            $userId = $user;
            $ret->setUser($userId);
        }

        $res = $this->db->Execute("
            SELECT ar.*
            FROM `acl_account_roles` ar
            JOIN `account_team_user_acls` ua ON ua.`account_role_id` = ar.`account_role_id`
            JOIN `account_team_users` atu ON atu.`id` = ua.`account_team_user_id`
            WHERE atu.`user_id` = ? AND atu.`team_id` = ? AND ar.`account_id` = ?
            GROUP BY ar.`account_role_id`
        ", array($userId, $teamId, $accountId));

        while ($rec = $res->FetchRow()) {
            $role = $this->getAccountRoleByRow($rec);
            $role->setTeamRole(false);
            $ret[$role->getRoleId()] = $role;
        }

        if (!$ret->count()) {
            //User has no roles assigned to this team so we need to use default ACL role for the team
            $res = $this->db->Execute("
                SELECT ar.*
                FROM `acl_account_roles` ar
                JOIN `account_teams` at ON at.account_role_id = ar.account_role_id
                JOIN `account_team_users` atu ON at.`id` = atu.`team_id`
                WHERE at.id = ? AND atu.user_id = ? AND at.account_id = ?
            ", array($teamId, $userId, $accountId));
            while ($rec = $res->FetchRow()) {
                $role = $this->getAccountRoleByRow($rec);
                $role->setTeamRole(true);
                $ret[$role->getRoleId()] = $role;
            }
        }

        return $ret;
    }

    /**
     * Returns account_role_id identifiers for specified user and team
     *
     * You cannot specify both userId and teamId with an array at the one call
     *
     * @param   int|array  $userId    The ID of the user
     * @param   int|array  $teamId    The ID of the team
     * @param   int        $accountId The ID of the client's account
     * @return  array      Returns the list of the identifiers
     */
    public function getUserRoleIdsByTeam($userId, $teamId, $accountId)
    {
        $ret = array();
        if (is_array($userId)) {
            $group = 'user_id';
            $subj = $userId;
        }
        if (is_array($teamId)) {
            if (isset($group)) {
                throw new \InvalidArgumentException(
                    "You cannot speciy both userId and teamId as array at the same time."
                );
            }
            $group = 'team_id';
            $subj = $teamId;
        }

        $lambda = function($v) {
            return (is_array($v) ? "IN ('" . join("', '", array_map('intval', $v)) . "')" : "=" . intval($v));
        };

        if (!empty($teamId) && !empty($userId)) {
            $rs = $this->db->Execute("
                SELECT " . (isset($group) ? 'atu.' . $group . ',' : '') . " ar.account_role_id
                FROM `acl_account_roles` ar
                JOIN `account_team_user_acls` ua ON ua.`account_role_id` = ar.`account_role_id`
                JOIN `account_team_users` atu ON atu.`id` = ua.`account_team_user_id`
                WHERE atu.`user_id` " . $lambda($userId) . "
                AND atu.`team_id` " . $lambda($teamId) . "
                AND ar.`account_id` = ?
                GROUP BY " . (isset($group) ? "atu." . $group . "," : "") . " ar.`account_role_id`
            ", array($accountId));
            while ($rec = $rs->FetchRow()) {
                if (isset($group)) {
                    $ret[$rec[$group]][] = $rec['account_role_id'];
                } else {
                    $ret[] = $rec['account_role_id'];
                }
            }
        }

        if (isset($group)) {
            //users with missing ACL must be presented in the result array with empty result
            foreach ($subj as $id) {
                if (!isset($ret[$id])) {
                    $ret[$id] = array();
                }
            }
        }

        return $ret;
    }

    /**
     * Gets account roles superposition by specified ID of environment
     *
     * @param   \Scalr_Account_User|int  $userId       The user's object or ID of the user
     * @param   int                      $envId        The ID of the client's environment
     * @param   int                      $accountId    The ID of the client's account
     * @return  \Scalr\Acl\Role\AccountRoleSuperposition Returns the list of the roles of account level by specified environment
     */
    public function getUserRolesByEnvironment($user, $envId, $accountId)
    {
        $ret = new \Scalr\Acl\Role\AccountRoleSuperposition(array());

        if ($user instanceof \Scalr_Account_User) {
            $userId = $user->getId();
            $ret->setUser($user);
        } else {
            $userId = $user;
            $ret->setUser($userId);
        }

        //The teams in which user has ACL role
        $teamsUserHasAcl = array();

        //Selects User's ACLs
        $res = $this->db->Execute("
            SELECT atu.`team_id`, ar.*
            FROM `acl_account_roles` ar
            JOIN `account_team_user_acls` ua ON ua.`account_role_id` = ar.`account_role_id`
            JOIN `account_team_users` atu ON atu.`id` = ua.`account_team_user_id`
            JOIN `account_team_envs` te ON te.`team_id` = atu.`team_id`
            JOIN `account_teams` at ON at.id = atu.`team_id`
            WHERE atu.`user_id` = ? AND te.`env_id` = ? AND ar.`account_id` = ?
            GROUP BY at.`id`, ar.`account_role_id`
        ", array($userId, $envId, $accountId));

        while ($rec = $res->FetchRow()) {
            $teamsUserHasAcl[$rec['team_id']] = $rec['team_id'];
            $role = $this->getAccountRoleByRow($rec);
            $role->setTeamRole(false);
            $ret[$role->getRoleId()] = $role;
        }

        //Selects Team's ACLs where user enters without defined ACL
        $rs = $this->db->Execute("
            SELECT ar.*
            FROM `account_teams` at
            JOIN `account_team_users` tu ON at.`id` = tu.`team_id`
            JOIN `acl_account_roles` ar ON ar.`account_role_id` = at.`account_role_id` AND ar.`account_id` = at.`account_id`
            JOIN `account_team_envs` te ON te.`team_id` = tu.`team_id`
            WHERE tu.user_id = ? AND te.`env_id` = ? AND at.account_id = ?
            AND at.`account_role_id` IS NOT NULL
            " . (!empty($teamsUserHasAcl) ?
            "AND at.id NOT IN('" . join("','", array_values($teamsUserHasAcl)) . "')" : "") . "
        ", array($userId, $envId, $accountId));

        while ($rec = $rs->FetchRow()) {
            if (!isset($ret[$rec['account_role_id']])) {
                $role = $this->getAccountRoleByRow($rec);
                $role->setTeamRole(true);
                $ret[$role->getRoleId()] = $role;
            }
        }

        return $ret;
    }

    /**
     * Sets ACL roles to this user
     *
     * This method modifies resords of two tables
     * `account_team_users` and `account_team_user_acls`.
     *
     * Attention! It expects full list of the ACL roles relations for user.
     * All missing relations will be removed.
     *
     * @param   int     $userId    The ID of the user
     * @param   array   $data      ACL roles array which looks like
     *                             array(teamId => array(accountRoleId1, accountRoleId2, ...))
     * @param   int     $accountId optional The ID of the account. Restricts queries to the
     *                             specified account.
     */
    public function setAllRolesForUser($userId, array $data = array(), $accountId = null)
    {
        $tu = array();
        $rs = $this->db->Execute("
            SELECT tu.`id`, tu.`team_id` FROM `account_team_users` tu WHERE tu.`user_id` = ?
        ", array($userId));
        while ($rec = $rs->FetchRow()) {
            $tu[$rec['team_id']] = $rec['id'];
        }

        //Useless relations between teems
        $toRemove = array_diff(array_keys($tu), array_keys($data));
        if (!empty($toRemove)) {
            $this->db->Execute("
                DELETE FROM `account_team_users`
                WHERE `user_id` = ?
                AND `team_id` IN (" . rtrim(str_repeat("?,", count($toRemove)), ',') . ")
            ", array_merge(array($userId), $toRemove));
        }

        foreach ($data as $teamId => $roles) {
            if (empty($roles)) {
                $roles = array();
            }
            if (!isset($tu[$teamId])) {
                //Relation between user and team has to be created
                $this->db->Execute("
                    INSERT IGNORE `account_team_users` (`user_id`, `team_id`) VALUES (?, ?)
                ", array($userId, $teamId));
                $tu[$teamId] = $this->db->Insert_ID();
                $tua = array();
            } else {
                $tua = array_map(function($value) {
                    return $value['account_role_id'];
                }, $this->db->GetAll("
                    SELECT account_role_id FROM `account_team_user_acls` WHERE `account_team_user_id` = ?
                ", array($tu[$teamId])));
            }

            //Unnecessary relations with roles
            $toRemove = array_diff($tua, array_values($roles));
            if (!empty($toRemove)) {
                $this->db->Execute("
                    DELETE FROM `account_team_user_acls`
                    WHERE `account_team_user_id` = ?
                    AND `account_role_id` IN (" . rtrim(str_repeat("?,", count($toRemove)), ',') . ")
                ", array_merge(array($tu[$teamId]), $toRemove));
            }

            if (($c = count($roles))) {
                //INSERT-SELECT approach avoids missing foreign keys assertions
                $this->db->Execute("
                    INSERT IGNORE `account_team_user_acls` (`account_team_user_id`, `account_role_id`)
                    SELECT '" . $tu[$teamId] . "', `account_role_id` FROM `acl_account_roles`
                    WHERE `account_role_id` IN (" . rtrim(str_repeat("?,", $c), ',') . ")
                    " . (!empty($accountId) ? " AND `account_id` = " . intval($accountId) : "") . "
                ", array_values($roles));
            }
        }
    }

    /**
     * Set roles for specified user for specified team.
     *
     * @param   int        $teamId       The identifier of the team
     * @param   int        $userId       The identifier of the user
     * @param   array      $accountRoles The list of the identifiers of the roles of account level
     * @param   int        $accountId    optional The identifier of the account
     */
    public function setUserRoles($teamId, $userId, $accountRoles, $accountId = null)
    {
        $accountId = intval($accountId);

        //Verify that team and user are from the same acount
        if (!empty($accountId)) {
            $check = $this->db->GetOne("
                SELECT 1 FROM account_users WHERE id = ? AND account_id = ? LIMIT 1
            ", array($userId, $accountId)) && $this->db->GetOne("
                SELECT 1 FROM account_teams WHERE id = ? AND account_id = ? LIMIT 1
            ", array($teamId, $accountId));
            if (!$check) {
                throw new Exception\AclException(sprintf(
                    'Cannot find the team "%d" or user "%d" in the account "%d"',
                    $teamId, $userId, $accountId
                ));
            }
        } else {
            //Retrieves identifier of the account
            $accountId = $this->db->GetOne("
                SELECT u.account_id
                FROM account_users u
                JOIN account_teams t ON t.account_id = u.account_id
                WHERE u.user_id = ? AND t.team_id = ?
                LIMIT 1
            ", array($userId, $accountId));
            if (!$accountId) {
                throw new Exception\AclException(sprintf(
                    'Cannot find the team "%d" or user "%d" in the account "%d"',
                    $teamId, $userId, $accountId
                ));
            }
        }

        $teamUserId = $this->db->GetOne("
           SELECT tu.id
           FROM `account_team_users` tu
           WHERE tu.`team_id` = ? AND tu.`user_id` = ?
           LIMIT 1
        ", array(
           $teamId,
           $userId
        ));

        if (empty($teamUserId)) {
            $this->db->Execute("
                INSERT IGNORE `account_team_users`
                SET team_id = ?,
                    user_id = ?
            ", array(
                $teamId,
                $userId
            ));

            $teamUserId = $this->db->Insert_ID();
        } else {
            //Removes previous relations
            $this->db->Execute("
                DELETE FROM `account_team_user_acls` WHERE account_team_user_id = ?
            ", array(
                $teamUserId
            ));
        }

        if (($c = count($accountRoles))) {
            //Creates new relations
            $this->db->Execute("
                INSERT IGNORE `account_team_user_acls` (account_team_user_id, account_role_id)
                SELECT ?, r.account_role_id
                FROM `acl_account_roles` r
                WHERE r.account_id = ?
                AND r.account_role_id IN (" . rtrim(str_repeat("?,", $c), ',') . ")
            ", array_merge(array($teamUserId, $accountId), array_values($accountRoles)));
        }
    }

    /**
     * Set all relations between all users of this team and ACL roles
     *
     * @param   int     $teamId    The ID of the team
     * @param   array   $data      Roles array should look like array(user_id => array(account_role_id, ...))
     * @param   int     $accountId optional Restricts queries to the specified account
     * @throws  \Scalr\Acl\Exception\AclException
     */
    public function setAllRolesForTeam($teamId, array $data = array(), $accountId = null)
    {
        if (($c = count($data))) {
            //Creates required relations between users and team
            //INSERT-SELECT approach helps to avoid foreign key assertion
            $this->db->Execute("
                INSERT IGNORE `account_team_users` (`team_id`, `user_id`)
                SELECT " . intval($teamId) . ", `id` FROM `account_users`
                WHERE `id` IN (" . rtrim(str_repeat('?,', $c), ',') . ")
            ", array_keys($data));
        }

        $cur = array();
        //Fetches current relations between users and roles of this team
        $rs = $this->db->Execute("
            SELECT atu.`id`, atu.`user_id`, tua.`account_role_id` as `role_id`
            FROM `account_team_users` atu
            LEFT JOIN `account_team_user_acls` tua ON tua.`account_team_user_id` = atu.`id`
            WHERE `team_id` = ?
        ", array($teamId));
        while ($rec = $rs->FetchRow()) {
            if (!isset($cur[$rec['user_id']])) {
                $cur[$rec['user_id']] = array(
                    'id'    => $rec['id'],
                    'roles' => isset($rec['role_id']) ? array($rec['role_id']) : array(),
                );
            } elseif (isset($rec['role_id'])) {
                $cur[$rec['user_id']]['roles'][] = $rec['role_id'];
            }
        }

        //Removes unnecessary relations
        $toRemove = array_diff(array_keys($cur), array_keys($data));
        if (!empty($toRemove)) {
            $this->db->Execute("
                DELETE FROM `account_team_users`
                WHERE `team_id` = ? AND `user_id` IN(" . rtrim(str_repeat('?,', count($toRemove)), ',') . ")
            ", array_merge(array($teamId), $toRemove));
        }

        foreach ($data as $userId => $roles) {
            //It's unbelievable
            if (!isset($cur[$userId])) continue;
            if (empty($roles)) {
                $roles = array();
            } elseif (($c = count($roles))) {
                //Creates all relations to ACL roles
                $this->db->Execute("
                    INSERT IGNORE `account_team_user_acls` (`account_team_user_id`, `account_role_id`)
                    SELECT " . intval($cur[$userId]['id']) . ", `account_role_id`
                    FROM `acl_account_roles`
                    WHERE `account_role_id` IN (" . rtrim(str_repeat('?,', $c), ',') . ")
                    " . (!empty($accountId) ? " AND `account_id` = " . intval($accountId) : "") . "
                ", $roles);
            }

            //Removes unnecessary relations to roles
            $toRemove = array_diff($cur[$userId]['roles'], $roles);
            if (!empty($toRemove)) {
                $this->db->Execute("
                    DELETE FROM `account_team_user_acls`
                    WHERE `account_team_user_id` = ?
                    AND `account_role_id` IN(" . rtrim(str_repeat('?,', count($toRemove)), ',') . ")
                ", array_merge(array($cur[$userId]['id']), $toRemove));
            }
        }
    }

    /**
     * Saves account role to database
     *
     * @param   int        $accountId     The ID of the account.
     * @param   int        $baseRoleId    The ID of the base role.
     * @param   string     $name          The name of the account role.
     * @param   int        $color         The color specified as integer value
     * @param   array      $resources     Array of the resources which looks like
     *                                    array(
     *                                        resource_id => array(
     *                                            'granted' => [0|1], #is granted
     *                                            'permissions' => array(
     *                                                permissionId => [0|1], #is granted
     *                                            )
     *                                    );
     * @param   string     $accountRoleId optional The ID of the ACL role of account level. NULL if the new role.
     * @return  string     Returns the ID of the created or modified account role on success
     * @throws  \Scalr\Acl\Exception\AclException
     */
    public function setAccountRole($accountId, $baseRoleId, $name, $color, $resources, $accountRoleId = null)
    {
         if (empty($accountRoleId)) {
            //Creates new account role
            $accountRoleId = self::generateAccountRoleId();
            $new = true;
        }

        $this->db->Execute("
            INSERT `acl_account_roles`
            SET `account_role_id` = ?,
                `account_id` = ?,
                `role_id` = ?,
                `name` = ?,
                `color` = ?,
                `is_automatic` = 0
            ON DUPLICATE KEY UPDATE
                `role_id` = ?,
                `name` = ?,
                `color` = ?
        ", array(
            $accountRoleId,
            $accountId,
            $baseRoleId,
            $name,
            $color,

            $baseRoleId,
            $name,
            $color
        ));

        $accountRole = $this->getAccountRole($accountRoleId);
        if ($accountRole === null) {
            throw new Exception\AclException(($new ? 'Database error' : 'Cannot find requested ACL role!'));
        }

        $baseRole = $accountRole->getBaseRole();
        foreach ($accountRole->getIteratorResources() as $resourceDefinition) {
            /* @var $resourceDefinition \Scalr\Acl\Resource\ResourceObject */
            $resourceId = $resourceDefinition->getResourceId();
            $accountResource = $accountRole->getResource($resourceId);
            $toUpdate = null;
            $toUpdatePerm = array();
            foreach ($resourceDefinition->getPermissions() as $permissionId => $permissionName) {
                $granted = isset($resources[$resourceId]['permissions'][$permissionId]) ?
                    $resources[$resourceId]['permissions'][$permissionId] == 1 : false;
                if ($granted != $baseRole->isAllowed($resourceId, $permissionId)) {
                    //Unique permission is overridden on account level and needs to be created
                    $toUpdatePerm[$permissionId] = array(
                        $accountRoleId,
                        $resourceId,
                        $permissionId,
                        $granted ? 1 : 0,
                        $granted ? 1 : 0
                    );
                } else if ($accountResource !== null) {
                    //Unique permission needs to be removed
                    $this->db->Execute("
                        DELETE FROM `acl_account_role_resource_permissions`
                        WHERE `account_role_id` = ?
                        AND `resource_id` = ?
                        AND `perm_id` = ?
                    ", array(
                        $accountRoleId,
                        $resourceId,
                        $permissionId
                    ));
                }
            }
            $granted = isset($resources[$resourceId]['granted']) ? $resources[$resourceId]['granted'] == 1 : false;
            if ($granted != $baseRole->isAllowed($resourceId)) {
                //Resource record is overridden on account level and needs to be created
                $toUpdate = array(
                    $accountRoleId,
                    $resourceId,
                    $granted ? 1 : 0,
                    $granted ? 1 : 0,
                );
            } elseif (!empty($toUpdatePerm) && $granted) {
                //Referenced resource must be created as foreign key requires.
                $toUpdate = array(
                    $accountRoleId,
                    $resourceId,
                    $granted ? 1 : null,
                    $granted ? 1 : null,
                );
            } else {
                //Resource record the same as in the base role and needs to be removed
                $this->db->Execute("
                    DELETE FROM `acl_account_role_resources`
                    WHERE `account_role_id` = ?
                    AND `resource_id` = ?
                ", array(
                    $accountRoleId,
                    $resourceId,
                ));
            }

            if ($toUpdate) {
                $this->db->Execute("
                    INSERT `acl_account_role_resources`
                    SET `account_role_id` = ?,
                        `resource_id` = ?,
                        `granted` = ?
                    ON DUPLICATE KEY UPDATE
                        `granted` = ?
                ", $toUpdate);
            }
            if ($toUpdatePerm) {
                foreach ($toUpdatePerm as $opt) {
                    $this->db->Execute("
                        INSERT `acl_account_role_resource_permissions`
                        SET `account_role_id` = ?,
                            `resource_id` = ?,
                            `perm_id` = ?,
                            `granted` = ?
                        ON DUPLICATE KEY UPDATE
                            `granted` = ?
                    ", $opt);
                }
            }
        }

        return $accountRoleId;
    }

    /**
     * Gets all users which belong to the specified account role.
     *
     * @param   string     $accountRoleId The identifier of the role of account level.
     * @param   int        $accountId     The identifier of the account
     * @return  array      Returns users array looks like
     *                     array(userid => array(
     *                        'id'    => identifier of the user,
     *                        'name'  => full name,
     *                        'type'  => user type,
     *                        'email' => user email,
     *                        'status'=> status,
     *                        'teams' => array(teamId => team name),
     *                     ))
     */
    public function getUsersHaveAccountRole($accountRoleId, $accountId)
    {
        $accountId = intval($accountId);
        $users = array();

        $select = "SELECT tu.`user_id`, tu.`team_id`, u.`type`, u.`email`, u.`status`, u.`fullname`, t.`name` AS `team_name`";

        //Users which belong to the team with defined ACL roles
        $rs = $this->db->Execute("
            " . $select . "
            FROM `acl_account_roles` ar
            JOIN `account_team_user_acls` tua ON tua.`account_role_id` = ar.`account_role_id`
            JOIN `account_team_users` tu ON tua.`account_team_user_id` = tu.id
            JOIN `account_teams` t ON t.`account_id` = " . $accountId . " AND t.id = tu.`team_id`
            JOIN `account_users` u ON u.`account_id` = " . $accountId . " AND u.id = tu.`user_id`
            WHERE ar.`account_role_id` = ?
            AND ar.`account_id` = " . $accountId . "
            GROUP by tu.`user_id`, tu.`team_id`
        ", array(
            $accountRoleId
        ));
        while ($rec = $rs->FetchRow()) {
            if (!isset($users[$rec['user_id']])) {
                $users[$rec['user_id']] = array(
                    'id'     => $rec['user_id'],
                    'name'   => $rec['fullname'],
                    'type'   => $rec['type'],
                    'email'  => $rec['email'],
                    'status' => $rec['status'],
                    'teams'  => array(),
                );
            }
            $users[$rec['user_id']]['teams'][$rec['team_id']] = $rec['team_name'];
        }

        //Users which belong to the teams with team's default ACL role
        $rs = $this->db->Execute("
            " . $select . "
            FROM acl_account_roles ar
            JOIN account_teams t ON t.account_id = " . $accountId . " AND t.account_role_id = ar.account_role_id
            JOIN account_team_users tu ON tu.team_id = t.id
            JOIN account_users u ON u.account_id = " . $accountId . " AND u.id = tu.user_id
            LEFT JOIN account_team_user_acls tua ON tua.account_team_user_id = tu.id
            WHERE ar.account_role_id = ?
            AND ar.account_id = " . $accountId . "
            AND tua.account_role_id IS NULL
            GROUP by tu.user_id, tu.team_id
        ", array(
            $accountRoleId
        ));

        while ($rec = $rs->FetchRow()) {
            if (!isset($users[$rec['user_id']])) {
                $users[$rec['user_id']] = array(
                    'id'     => $rec['user_id'],
                    'name'   => $rec['fullname'],
                    'type'   => $rec['type'],
                    'email'  => $rec['email'],
                    'status' => $rec['status'],
                    'teams'  => array(),
                );
            }
            if (!array_key_exists($rec['team_id'], $users[$rec['user_id']]['teams'])) {
                $users[$rec['user_id']]['teams'][$rec['team_id']] = $rec['team_name'];
            }
        }

        return $users;
    }

    /**
     * Checks wheter access to ACL resource or unique permission is allowed.
     *
     * @param   \Scalr_Account_User $user                  The user
     * @param   \Scalr_Environment  $environment           The client's environment
     * @param   int                 $resourceId            The ID of the ACL resource or its symbolic name without "RESOURCE_" prefix.
     * @param   string              $permissionId optional The ID of the uniqure permission which is
     *                                            related to specified resource.
     * @return  bool                Returns TRUE if access is allowed
     */
    public function isUserAllowedByEnvironment(\Scalr_Account_User $user, $environment, $resourceId, $permissionId = null)
    {
        //Checks wheter environment and user are from the same account.
        if (!($user instanceof \Scalr_Account_User)) {
            throw new \InvalidArgumentException(sprintf(
                'Argument 1 of the method %s should be Scalr_Account_User object, %s given.',
                __METHOD__, gettype($user)
            ));
        } elseif ($user->isScalrAdmin()) {
            return true;
        } else if (!($environment instanceof \Scalr_Environment)) {
            //If environment is not defined it will return false.
            return false;
        } else if ($environment->clientId != $user->getAccountId()) {
            return false;
        }

        //Scalr-Admin and Account-Owner is allowed for everything
        if ($user->isAccountOwner()) {
            return true;
        }

        if (is_string($resourceId)) {
            $sName = 'Scalr\\Acl\\Acl::RESOURCE_' . strtoupper($resourceId);
            if (defined($sName)) {
                $resourceId = constant($sName);
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot find ACL resource %s by specified symbolic name %s.',
                    $sName, $resourceId
                ));
            }
        }

        return (bool) $user->getAclRolesByEnvironment($environment->id)->isAllowed($resourceId, $permissionId);
    }
}
