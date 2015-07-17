<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20150505143635 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'a3d36bdc-9365-4189-869a-ef81962e213f';

    protected $depends = [];

    protected $description = 'Add team owner of farm';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    const RESOURCE_FARMS_SERVERS = 0x105;
    const RESOURCE_FARMS_ALERTS = 0x101;
    const RESOURCE_FARMS_STATISTICS = 0x103;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 9;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('farms', 'team_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('farms');
    }

    protected function run1($stage)
    {
        $this->console->notice('Adding field to table farms');
        $this->db->Execute("ALTER TABLE farms ADD `team_id` int(11) NULL DEFAULT NULL");

        $this->console->notice('Adding foreign key');
        $this->db->Execute("
            ALTER TABLE farms ADD CONSTRAINT `farms_account_teams_id`
                FOREIGN KEY (`team_id`) REFERENCES `account_teams` (`id`)
                ON DELETE SET NULL
                ON UPDATE NO ACTION
        ");
    }

    protected function run2()
    {
        $this->console->out('Removing old resources from base acl roles');

        $this->db->Execute("DELETE FROM `acl_role_resources` WHERE `resource_id` IN (?,?,?)",
            [self::RESOURCE_FARMS_SERVERS, self::RESOURCE_FARMS_ALERTS, self::RESOURCE_FARMS_STATISTICS]);

        $this->db->Execute("DELETE FROM `acl_role_resource_permissions` WHERE `resource_id` = ? AND perm_id IN (?,?,?)",
            [Acl::RESOURCE_FARMS, 'not-owned-farms', 'launch', 'terminate']);

        $this->db->Execute("DELETE FROM `acl_role_resource_permissions` WHERE `resource_id` = ? AND perm_id = ?",
            [self::RESOURCE_FARMS_SERVERS, 'ssh-console']);

        $this->db->Execute("DELETE FROM `acl_account_role_resource_permissions` WHERE `resource_id` = ? AND perm_id = ?",
            [self::RESOURCE_FARMS_SERVERS, 'ssh-console']);
    }

    /**
     * Check if stage is applied for the specified resource and permission
     *
     * @param    string    $resourceName   The name of the ACL resource (Example:"RESOURCE_FARMS")
     * @param    string    $permissionName The name of the ACL permission (Example:"PERM_FARMS_SERVERS")
     * @return   boolean
     */
    private function checkAppliedForPermission($resourceName, $permissionName)
    {
        return  defined('Scalr\\Acl\\Acl::' . $resourceName) &&
                defined('Scalr\\Acl\\Acl::' . $permissionName) &&
                Definition::has(constant('Scalr\\Acl\\Acl::' . $resourceName)) &&
                $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resource_permissions`
                    WHERE `resource_id` = ? AND `role_id` = ? AND `perm_id` = ?
                    LIMIT 1
                ", [
                    constant('Scalr\\Acl\\Acl::' . $resourceName),
                    Acl::ROLE_ID_FULL_ACCESS,
                    constant('Scalr\\Acl\\Acl::' . $permissionName)
                ]) == 1;
    }

    /**
     * Adds a new Full Access Role Permission
     *
     * @param    string    $resourceName   The name of the ACL resource (Example:"RESOURCE_FARMS")
     * @param    string    $permissionName The name of the ACL permission (Example:"PERM_FARMS_SERVERS")
     */
    private function createNewFullAccessPermission($resourceName, $permissionName)
    {
        $this->console->out('Creating %s ACL permission', $permissionName);

        $this->db->Execute("
            INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", [
            Acl::ROLE_ID_FULL_ACCESS,
            constant('Scalr\\Acl\\Acl::' . $resourceName),
            constant('Scalr\\Acl\\Acl::' . $permissionName)
        ]);
    }

    protected function isApplied3($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_FARMS', 'PERM_FARMS_SERVERS');
    }

    protected function run3($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_FARMS', 'PERM_FARMS_SERVERS');
    }

    protected function isApplied4($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_FARMS', 'PERM_FARMS_LAUNCH_TERMINATE');
    }

    protected function run4($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_FARMS', 'PERM_FARMS_LAUNCH_TERMINATE');
    }

    protected function isApplied5($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_FARMS', 'PERM_FARMS_STATISTICS');
    }

    protected function run5($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_FARMS', 'PERM_FARMS_STATISTICS');
    }

    protected function isApplied6($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_FARMS', 'PERM_FARMS_CHANGE_OWNERSHIP');
    }

    protected function run6($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_FARMS', 'PERM_FARMS_CHANGE_OWNERSHIP');
    }

    protected function isApplied7($stage)
    {
        return false;
    }

    protected function run7()
    {
        $this->console->out('Creating new own farms and team farms resources');

        foreach ([Acl::RESOURCE_OWN_FARMS, Acl::RESOURCE_TEAM_FARMS] as $resource) {
            $this->db->Execute("INSERT IGNORE acl_role_resources (`role_id`, `resource_id`, `granted`) VALUES(?, ?, 1)", [
                Acl::ROLE_ID_FULL_ACCESS, $resource
            ]);

            foreach ([Acl::PERM_FARMS_MANAGE, Acl::PERM_FARMS_CLONE, Acl::PERM_FARMS_LAUNCH_TERMINATE, Acl::PERM_FARMS_SERVERS, Acl::PERM_FARMS_CHANGE_OWNERSHIP, Acl::PERM_FARMS_STATISTICS] as $permission) {
                $this->db->Execute("
                    INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
                    VALUES (?, ?, ?, 1)
                ", array(
                    Acl::ROLE_ID_FULL_ACCESS,
                    $resource,
                    $permission
                ));
            }
        }
    }

    /**
     * Insert account level permission value
     *
     * Create allow permission if base acl role is forbidden and permFlag is allowed,
     * and deny permission if base acl role is allowed and permFlag is denied.
     * If permFlag is null, do nothing
     *
     * @param string    $accountRoleId  The identifier of the ACL role on account
     * @param string    $permFlag       The permission value
     * @param int       $resource       The identifier of the ACL resource
     * @param string    $permName       The identifier of the ACL permission
     * @param bool      $isDenyRole     Whether the account level role derived from full access role
     */
    protected function createAclPermissionRule($accountRoleId, $permFlag, $resource, $permName, $isDenyRole)
    {
        if ($permFlag === '1' && $isDenyRole || $permFlag === '0' && !$isDenyRole) {
            $this->db->Execute("
                INSERT IGNORE acl_account_role_resource_permissions (`account_role_id`, `resource_id`, `perm_id`, `granted`)
                VALUES (?, ?, ?, ?)
            ", [$accountRoleId, $resource, $permName, $permFlag]);
        }
    }

    /**
     * Gets granted value for the specified resource of account level ACL role
     *
     * @param   string  $accountRoleId The identifier of the account role
     * @param   string  $resourceId    The identifier of the ACL resource
     * @return  string  Returns granted value
     */
    private function isGrantedAccountResource($accountRoleId, $resourceId)
    {
        return $this->db->GetOne("
            SELECT granted FROM acl_account_role_resources
            WHERE account_role_id = ? AND resource_id = ?
        ", [$accountRoleId, $resourceId]);
    }

    /**
     * Gets granted value for the specified permission of account level ACL role
     *
     * @param   string  $accountRoleId The identifier of the account role
     * @param   string  $resourceId    The identifier of the ACL resource
     * @param   string  $permissionId  The identifier of the ACL permission
     * @return  string  Returns granted value
     */
    private function isGrantedAccountPermission($accountRoleId, $resourceId, $permissionId)
    {
        return $this->db->GetOne("
            SELECT granted FROM acl_account_role_resource_permissions
            WHERE account_role_id = ? AND resource_id = ? AND perm_id = ?
        ", [$accountRoleId, $resourceId, $permissionId]);
    }

    /**
     * Sets granted value for the specified account level ACL resource
     *
     * @param    string   $accountRoleId The identifier of the account role
     * @param    int      $resourceId    The identifier of the ACL resource
     * @param    string   $granted       The granted value
     */
    private function setGrantedAccountResource($accountRoleId, $resourceId, $granted)
    {
        $this->db->Execute("
            REPLACE acl_account_role_resources (`account_role_id`, `resource_id`, `granted`)
            VALUES (?, ?, ?)
        ", [$accountRoleId, $resourceId, $granted]);
    }

    /**
     * Sets granted value for the specified account level ACL permission
     *
     * @param    string   $accountRoleId The identifier of the account role
     * @param    int      $resourceId    The identifier of the ACL resource
     * @param    int      $permissionId  The identifier of the ACL permission
     * @param    string   $granted       The granted value
     */
    private function setGrantedAccountPermission($accountRoleId, $resourceId, $permissionId, $granted)
    {
        $this->db->Execute("
            REPLACE acl_account_role_resource_permissions (`account_role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, ?)
        ", [$accountRoleId, $resourceId, $permissionId, $granted]);
    }

    protected function run8()
    {
        $this->console->out('Converting acl rules to a new schema');

        $permissionsArray = [
            Acl::PERM_FARMS_MANAGE, Acl::PERM_FARMS_LAUNCH_TERMINATE, Acl::PERM_FARMS_CLONE,
            Acl::PERM_FARMS_SERVERS, Acl::PERM_FARMS_CHANGE_OWNERSHIP, Acl::PERM_FARMS_STATISTICS
        ];

        foreach($this->db->GetAll('SELECT account_role_id, role_id FROM acl_account_roles') as $accountRole) {
            $accountRoleId = $accountRole['account_role_id'];
            $isDenyRole = $accountRole['role_id'] == Acl::ROLE_ID_EVERYTHING_FORBIDDEN;

            $resourceFarmServers = $this->isGrantedAccountResource($accountRoleId, self::RESOURCE_FARMS_SERVERS);

            $resourceFarms = $this->isGrantedAccountResource($accountRoleId, Acl::RESOURCE_FARMS);

            $resourceStatistics = $this->isGrantedAccountResource($accountRoleId, self::RESOURCE_FARMS_STATISTICS);

            $permFarmsNotOwner = $this->isGrantedAccountPermission($accountRoleId, Acl::RESOURCE_FARMS, 'not-owned-farms');

            $permFarmsLaunch = $this->isGrantedAccountPermission($accountRoleId, Acl::RESOURCE_FARMS, 'launch');

            $permFarmsClone = $this->isGrantedAccountPermission($accountRoleId, Acl::RESOURCE_FARMS, 'clone');

            $permFarmsManage = $this->isGrantedAccountPermission($accountRoleId, Acl::RESOURCE_FARMS, 'manage');

            // Clear items. Because they could be re-added later depending on permission "not-owned-farms"
            $this->db->Execute("DELETE FROM `acl_account_role_resources` WHERE account_role_id = ? AND `resource_id` = ?",
                [$accountRoleId, Acl::RESOURCE_FARMS]);

            $this->db->Execute("DELETE FROM `acl_account_role_resource_permissions` WHERE account_role_id = ? AND `resource_id` = ?",
                [$accountRoleId, Acl::RESOURCE_FARMS]);

            if ($resourceFarms == 1 || $resourceFarms == NULL && !$isDenyRole) {
                // Allows to view farms
                if ($permFarmsNotOwner == 1 || $permFarmsNotOwner == NULL && !$isDenyRole) {
                    // Access to all farms
                    foreach ([Acl::RESOURCE_FARMS, Acl::RESOURCE_OWN_FARMS, Acl::RESOURCE_TEAM_FARMS] as $r) {
                        $this->setGrantedAccountResource($accountRoleId, $r, '1');
                    }

                    $this->createAclPermissionRule($accountRoleId, $permFarmsManage, Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE, $isDenyRole);
                    // special requirement for upgrade script, permission is disabled for existing roles (base roles have this permission enabled)
                    $this->createAclPermissionRule($accountRoleId, '0', Acl::RESOURCE_FARMS, Acl::PERM_FARMS_CHANGE_OWNERSHIP, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsLaunch, Acl::RESOURCE_FARMS, Acl::PERM_FARMS_LAUNCH_TERMINATE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsClone, Acl::RESOURCE_FARMS, Acl::PERM_FARMS_CLONE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $resourceFarmServers, Acl::RESOURCE_FARMS, Acl::PERM_FARMS_SERVERS, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $resourceStatistics, Acl::RESOURCE_FARMS, Acl::PERM_FARMS_STATISTICS, $isDenyRole);

                    $this->createAclPermissionRule($accountRoleId, $permFarmsManage, Acl::RESOURCE_TEAM_FARMS, Acl::PERM_FARMS_MANAGE, $isDenyRole);
                    // special requirement for upgrade script, permission is disabled for existing roles (base roles have this permission enabled)
                    $this->createAclPermissionRule($accountRoleId, '0', Acl::RESOURCE_TEAM_FARMS, Acl::PERM_FARMS_CHANGE_OWNERSHIP, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsLaunch, Acl::RESOURCE_TEAM_FARMS, Acl::PERM_FARMS_LAUNCH_TERMINATE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsClone, Acl::RESOURCE_TEAM_FARMS, Acl::PERM_FARMS_CLONE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $resourceFarmServers, Acl::RESOURCE_TEAM_FARMS, Acl::PERM_FARMS_SERVERS, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $resourceStatistics, Acl::RESOURCE_TEAM_FARMS, Acl::PERM_FARMS_STATISTICS, $isDenyRole);

                    $this->createAclPermissionRule($accountRoleId, $permFarmsManage, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_MANAGE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsManage, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_CHANGE_OWNERSHIP, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsLaunch, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_LAUNCH_TERMINATE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsClone, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_CLONE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $resourceFarmServers, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_SERVERS, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $resourceStatistics, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_STATISTICS, $isDenyRole);
                } else {
                    // your own farms access only
                    $this->setGrantedAccountResource($accountRoleId, Acl::RESOURCE_OWN_FARMS, '1');

                    if (!$isDenyRole) {
                        // block access to ALL and teams farms if default acl role == all access
                        $this->setGrantedAccountResource($accountRoleId, Acl::RESOURCE_FARMS, '0');
                        $this->setGrantedAccountResource($accountRoleId, Acl::RESOURCE_TEAM_FARMS, '0');

                        // also block permissions for ALL farms and TEAM farms
                        foreach ($permissionsArray as $perm) {
                            $this->setGrantedAccountPermission($accountRoleId, Acl::RESOURCE_FARMS, $perm, '0');
                            $this->setGrantedAccountPermission($accountRoleId, Acl::RESOURCE_TEAM_FARMS, $perm, '0');
                        }
                    }

                    $this->createAclPermissionRule($accountRoleId, $permFarmsManage, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_MANAGE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsManage, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_CHANGE_OWNERSHIP, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsLaunch, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_LAUNCH_TERMINATE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $permFarmsClone, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_CLONE, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $resourceFarmServers, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_SERVERS, $isDenyRole);
                    $this->createAclPermissionRule($accountRoleId, $resourceStatistics, Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_STATISTICS, $isDenyRole);
                }
            } else if ($resourceFarms === '0' && !$isDenyRole) {
                foreach ([Acl::RESOURCE_FARMS, Acl::RESOURCE_OWN_FARMS, Acl::RESOURCE_TEAM_FARMS] as $resource) {
                    $this->setGrantedAccountResource($accountRoleId, $resource, '0');

                    foreach ($permissionsArray as $perm) {
                        $this->setGrantedAccountPermission($accountRoleId, $resource, $perm, '0');
                    }
                }
            }
        }

        //Removes deprecated resources
        $this->db->Execute("DELETE FROM `acl_account_role_resources` WHERE `resource_id` IN (?, ?)", [self::RESOURCE_FARMS_SERVERS, self::RESOURCE_FARMS_STATISTICS]);
        $this->db->Execute("DELETE FROM `acl_account_role_resource_permissions` WHERE `resource_id` = ?", [self::RESOURCE_FARMS_SERVERS]);
    }

    protected function run9()
    {
        $this->db->Execute("UPDATE farm_settings SET value = ? WHERE name = ? AND value = ?", ['owner', \DBFarm::SETTING_LOCK_RESTRICT, 1]);
    }
}
