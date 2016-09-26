<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\Server;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151125130645 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'b08a37e6-335e-475f-ba36-f11ba24bdf3c';

    protected $depends = [];

    protected $description = 'Move property system.date.initialized to table servers';

    protected $dbservice = 'adodb';

    const INITIALIZED_TIME = "system.date.initialized";

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('servers', 'dtinitialized');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `servers` ADD COLUMN `dtinitialized` DATETIME NULL DEFAULT NULL AFTER `dtadded`");
        $this->db->Execute("
            UPDATE `servers` s
            JOIN `server_properties` sp ON sp.`server_id` = s.`server_id`
            SET s.`dtinitialized` = FROM_UNIXTIME(sp.`value`)
            WHERE sp.`name` = ?
        ", self::INITIALIZED_TIME);

        $this->db->Execute("
            UPDATE `servers` s
            SET s.`dtinitialized` = s.`dtadded`
            WHERE s.`dtinitialized` IS NULL AND s.`status` = ?
        ", Server::STATUS_RUNNING);
    }
}
