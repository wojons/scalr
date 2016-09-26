<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Acl;
use Scalr\Model\Entity\FarmSetting;

class Update20151207161318 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '8ea66037-843f-4195-ae5e-342fe9c2e845';

    protected $depends = [];

    protected $description = 'Convert Farms permissions to CRUD; make farm owner as foreign key to users';

    protected $dbservice = 'adodb';

    const PERM_FARMS_MANAGE = 'manage';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function run1($stage)
    {
        $this->console->out('Converting Farms manage permission to create, update, delete');

        $this->db->BeginTrans();

        try {
            foreach ([Acl::RESOURCE_FARMS, Acl::RESOURCE_OWN_FARMS, Acl::RESOURCE_TEAM_FARMS] as $resourceId) {
                foreach ([Acl::PERM_FARMS_CREATE, Acl::PERM_FARMS_UPDATE, Acl::PERM_FARMS_DELETE] as $permission) {
                    if ($permission == Acl::PERM_FARMS_CREATE && $resourceId != Acl::RESOURCE_OWN_FARMS) {
                        // leave CREATE only for OWN_FARMS
                        continue;
                    }

                    $this->db->Execute("INSERT IGNORE `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`) " .
                        "SELECT `account_role_id`, ? AS `resource_id`, ? AS `perm_id`, `granted` ".
                        "FROM `acl_account_role_resource_permissions` WHERE resource_id = ? AND perm_id = ?", [
                        $resourceId,
                        $permission,
                        $resourceId,
                        self::PERM_FARMS_MANAGE
                    ]);

                    $this->db->Execute("
                        INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
                        VALUES (?, ?, ?, 1)
                    ", array(
                        Acl::ROLE_ID_FULL_ACCESS,
                        $resourceId,
                        $permission
                    ));
                }
            }

            $this->console->out('Deleting "manage" permission from Farms resources');
            foreach ([Acl::RESOURCE_FARMS, Acl::RESOURCE_OWN_FARMS, Acl::RESOURCE_TEAM_FARMS] as $resourceId) {
                foreach ([self::PERM_FARMS_MANAGE, Acl::PERM_FARMS_CHANGE_OWNERSHIP] as $permission) {
                    if ($permission == Acl::PERM_FARMS_CHANGE_OWNERSHIP && $resourceId != Acl::RESOURCE_OWN_FARMS) {
                        // remove CHANGE_OWNERSHIP only for OWN_FARMS
                        continue;
                    }

                    $this->db->Execute("DELETE FROM `acl_role_resource_permissions` WHERE `resource_id` = ? AND `perm_id` = ?", [
                        $resourceId,
                        $permission
                    ]);

                    $this->db->Execute("DELETE FROM `acl_account_role_resource_permissions` WHERE `resource_id` = ? AND `perm_id` = ?", [
                        $resourceId,
                        $permission
                    ]);
                }
            }

            $this->db->CommitTrans();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            $this->console->out("Transaction rolled back");
            throw $e;
        }
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableForeignKey("fk_farms_account_users_id", "farms");
    }

    protected function run2()
    {
        $this->db->BeginTrans();
        try {
            $this->console->out("Copying properties from farms to farm settings");
            $this->db->Execute("
                INSERT IGNORE INTO farm_settings (`farmid`, `name`, `value`)
                SELECT id, ? AS `name`, created_by_id AS `value` FROM farms WHERE created_by_id IS NOT NULL
                UNION
                SELECT id, ? AS `name`, created_by_email AS `value` FROM farms WHERE created_by_email IS NOT NULL
            ", [FarmSetting::CREATED_BY_ID, FarmSetting::CREATED_BY_EMAIL]);

            $this->console->out("Sanitizing farms");
            $this->db->Execute("
                UPDATE farms f
                LEFT JOIN account_users au ON au.id = f.created_by_id
                SET f.created_by_id = NULL
                WHERE au.id IS NULL
            ");

            $this->console->out("Adding foreign key 'fk_farms_account_users_id'");
            $this->db->Execute("
                ALTER TABLE farms
                ADD CONSTRAINT `fk_farms_account_users_id`
                FOREIGN KEY (`created_by_id`)
                REFERENCES `account_users` (`id`)
                ON DELETE SET NULL ON UPDATE NO ACTION
            ");

            $this->db->CommitTrans();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }
}