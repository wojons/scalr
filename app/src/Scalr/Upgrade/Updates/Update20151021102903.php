<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Acl;

class Update20151021102903 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'ad10ec95-00e6-46b6-a8db-b37eb61bfd6c';

    protected $depends = [];

    protected $description = 'Refactor Roles and Images acl';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    const PERM_IMAGES_ENVIRONMENT_CREATE = 'create';
    const PERM_ROLES_ENVIRONMENT_BUNDLETASKS = 'bundletasks';
    const PERM_ROLES_ENVIRONMENT_CREATE = 'create';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function run1()
    {
        $this->console->out('Copying existing access permissions (create) from Roles to Images');

        $this->db->BeginTrans();

        try {
            // add missed entries in table acl_account_role_resources
            $rows = $this->db->GetCol("
                SELECT DISTINCT k.account_role_id
                FROM `acl_account_role_resource_permissions` k
                LEFT JOIN acl_account_role_resources f ON f.account_role_id = k.account_role_id AND f.resource_id = ?
                WHERE k.resource_id = ? AND k.perm_id IN(?, ?) AND f.resource_id IS NULL;
            ", [Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::RESOURCE_ROLES_ENVIRONMENT, self::PERM_ROLES_ENVIRONMENT_CREATE, self::PERM_ROLES_ENVIRONMENT_BUNDLETASKS]);

            foreach ($rows as $accountRoleId) {
                $this->db->Execute("INSERT IGNORE `acl_account_role_resources` (`account_role_id`, `resource_id`, `granted`) VALUES(?,?,?)", [
                    $accountRoleId,
                    Acl::RESOURCE_IMAGES_ENVIRONMENT,
                    1
                ]);
            }

            // import
            $this->db->Execute("INSERT IGNORE `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`) " .
                "SELECT `account_role_id`, ? AS `resource_id`, ? AS `perm_id`, `granted` FROM `acl_account_role_resource_permissions` WHERE resource_id = ? AND perm_id = ?", [
                Acl::RESOURCE_IMAGES_ENVIRONMENT,
                Acl::PERM_IMAGES_ENVIRONMENT_IMPORT,
                Acl::RESOURCE_ROLES_ENVIRONMENT,
                self::PERM_ROLES_ENVIRONMENT_CREATE
            ]);

            // build
            $this->db->Execute("INSERT IGNORE `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`) " .
                "SELECT `account_role_id`, ? AS `resource_id`, ? AS `perm_id`, `granted` FROM `acl_account_role_resource_permissions` WHERE resource_id = ? AND perm_id = ?", [
                Acl::RESOURCE_IMAGES_ENVIRONMENT,
                Acl::PERM_IMAGES_ENVIRONMENT_BUILD,
                Acl::RESOURCE_ROLES_ENVIRONMENT,
                self::PERM_ROLES_ENVIRONMENT_CREATE
            ]);

            // bundletasks
            $this->db->Execute("INSERT IGNORE `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`) " .
                "SELECT `account_role_id`, ? AS `resource_id`, ? AS `perm_id`, `granted` FROM `acl_account_role_resource_permissions` WHERE resource_id = ? AND perm_id = ?", [
                Acl::RESOURCE_IMAGES_ENVIRONMENT,
                Acl::PERM_IMAGES_ENVIRONMENT_BUNDLETASKS,
                Acl::RESOURCE_ROLES_ENVIRONMENT,
                self::PERM_ROLES_ENVIRONMENT_BUNDLETASKS
            ]);

            $this->console->out('Adding permissions build, import, bundletasks to Images resource');
            foreach ([Acl::PERM_IMAGES_ENVIRONMENT_BUILD, Acl::PERM_IMAGES_ENVIRONMENT_IMPORT, Acl::PERM_IMAGES_ENVIRONMENT_BUNDLETASKS] as $permission) {
                $this->db->Execute("
                INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
                VALUES (?, ?, ?, 1)
            ", array(
                    Acl::ROLE_ID_FULL_ACCESS,
                    Acl::RESOURCE_IMAGES_ENVIRONMENT,
                    $permission
                ));
            }

            $this->console->out('Deleting permission create from Images resource');
            foreach ([self::PERM_IMAGES_ENVIRONMENT_CREATE] as $permission) {
                $this->db->Execute("DELETE FROM `acl_role_resource_permissions` WHERE `resource_id` = ? AND `perm_id` = ?", [
                    Acl::RESOURCE_IMAGES_ENVIRONMENT,
                    $permission
                ]);

                $this->db->Execute("DELETE FROM `acl_account_role_resource_permissions` WHERE `resource_id` = ? AND `perm_id` = ?", [
                    Acl::RESOURCE_IMAGES_ENVIRONMENT,
                    $permission
                ]);
            }

            $this->console->out('Deleting create and bundletasks permissions from Images resource');
            foreach ([self::PERM_ROLES_ENVIRONMENT_BUNDLETASKS, self::PERM_ROLES_ENVIRONMENT_CREATE] as $permission) {
                $this->db->Execute("DELETE FROM `acl_role_resource_permissions` WHERE `resource_id` = ? AND `perm_id` = ?", [
                    Acl::RESOURCE_ROLES_ENVIRONMENT,
                    $permission
                ]);

                $this->db->Execute("DELETE FROM `acl_account_role_resource_permissions` WHERE `resource_id` = ? AND `perm_id` = ?", [
                    Acl::RESOURCE_ROLES_ENVIRONMENT,
                    $permission
                ]);
            }

            $this->db->CommitTrans();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            $this->console->out("Transaction rolled back");
            throw $e;
        }
    }
}
