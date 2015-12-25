<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151029142459 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '65caf15f-993a-4b17-9e0d-4e5fb46b8423';

    protected $depends = [];

    protected $description = 'Synchronise behaviors field in roles';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function run1($stage)
    {
        $srs = $this->db->Execute("
            SELECT r.`id`, r.`name`, r.`behaviors`, GROUP_CONCAT(rb.`behavior` ORDER BY rb.`behavior` ASC SEPARATOR ',') AS behaviors_table
            FROM `roles` r
            LEFT JOIN `role_behaviors` rb ON rb.`role_id` = r.id
            GROUP BY r.`id`
            HAVING r.`behaviors` != behaviors_table OR behaviors_table IS NULL
        ");

        while (($role = $srs->FetchRow())) {
            $this->db->BeginTrans();
            try {
                $current = array_unique(explode(",", $role['behaviors']));
                sort($current);
                $currentString = join(",", $current);
                if ($currentString != $role['behaviors']) {
                    $this->db->Execute("UPDATE `roles` SET `behaviors` = ? WHERE `id` = ?", [$currentString, $role['id']]);
                }

                $this->db->Execute("DELETE FROM `role_behaviors` WHERE `role_id` = ?", [$role['id']]);
                $sql = $args = [];
                foreach ($current as $behavior) {
                    $sql[] = '(?, ?)';
                    $args = array_merge($args, [$role['id'], $behavior]);
                }

                if (count($sql)) {
                    $this->db->Execute("INSERT INTO `role_behaviors` (`role_id`, `behavior`) VALUES " . join(', ', $sql), $args);
                }
                $this->db->CommitTrans();
            } catch (\Exception $e) {
                $this->db->RollbackTrans();
                $this->console->warning($e->getMessage());
            }
        }
    }
}
