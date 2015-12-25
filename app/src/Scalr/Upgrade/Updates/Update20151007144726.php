<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151007144726 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'bfeab147-b7cc-43c6-a32d-e7980f5eb905';

    protected $depends = [];

    protected $description = 'Optimize ui_errors table';

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
        $this->console->out('Re-creating table ui_errors');
        $this->db->Execute("DROP TABLE ui_errors");
        $this->db->Execute("
            CREATE TABLE `ui_errors` (
                `tm` datetime NULL,
                `file` varchar(255) NULL,
                `lineno` varchar(255) NULL,
                `url` varchar(255) NULL,
                `message` text NULL,
                `browser` varchar(255) NULL,
                `plugins` varchar(255) NULL,
                `account_id` int(11) NULL,
                `user_id` int(11) NULL
            ) ENGINE=InnoDB CHARSET=utf8
        ");
    }
}
