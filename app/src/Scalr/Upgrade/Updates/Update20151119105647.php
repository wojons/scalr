<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151119105647 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'a598076a-477f-4d2e-be02-68f6dec227fb';

    protected $depends = [];

    protected $description = 'Increase max length of name to 128 symbols in global variables';

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
        $tables = [
            'variables',
            'account_variables',
            'client_environment_variables',
            'role_variables',
            'farm_variables',
            'farm_role_variables',
            'server_variables'
        ];

        foreach ($tables as $table) {
            $this->db->Execute("ALTER TABLE {$table} MODIFY `name` varchar(128) NOT NULL");
        }
    }
}
