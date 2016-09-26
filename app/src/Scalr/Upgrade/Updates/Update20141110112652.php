<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141110112652 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '45848307-fec3-4deb-a982-06cb99e1f48a';

    protected $depends = [];

    protected $description = "Modifying payload column size of the report_payloads table.";

    protected $ignoreChanges = true;

    protected $dbservice = 'cadb';

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        return !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
    }

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
        return $this->hasTableColumn('report_payloads', 'payload') &&
               $this->hasTableColumnType('report_payloads', 'payload', 'mediumtext');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Replacing report_payloads.payload from TEXT to MEDIUMTEXT...");
        $this->db->Execute("ALTER TABLE `report_payloads` MODIFY `payload` MEDIUMTEXT NOT NULL COMMENT 'Payload'");
    }
}