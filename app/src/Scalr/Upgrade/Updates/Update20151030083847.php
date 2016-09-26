<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151030083847 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '23fd3a0f-992c-4ffd-b0ec-e6ba218f7e84';

    protected $depends = [];

    protected $description = 'Convert validation pattern in global variables to the single format';

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
        $updated = 0;
        $tableKeys = [
            'variables' => '',
            'account_variables' => 'account_id',
            'client_environment_variables' => 'env_id',
            'role_variables' => 'role_id',
            'farm_variables' => 'farm_id',
            'farm_role_variables' => 'farm_role_id',
            'server_variables' => 'server_id'
        ];

        $this->db->BeginTrans();
        try {
            foreach ($tableKeys as $table => $key) {
                $srs = $this->db->Execute("SELECT * FROM `{$table}` WHERE `validator` != ''");
                while (($variable = $srs->FetchRow())) {
                    if ($variable['validator'][0] != '/') {
                        $validator = "/{$variable['validator']}/";
                        $sql = "UPDATE `{$table}` SET `validator` = ? WHERE `name` = ?";
                        $args = [$validator, $variable['name']];
                        if ($key) {
                            $sql .= " AND `{$key}` = ?";
                            $args[] = $variable[$key];
                        }

                        $this->db->Execute($sql, $args);
                        $updated++;
                    }
                }
            }
            $this->db->CommitTrans();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }

        $this->console->out("Updated variables: %d", $updated);
    }
}
