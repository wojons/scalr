<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150221005402 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'ca4a7d53-7aed-4d18-ab86-10543087fdc9';

    protected $depends = [];

    protected $description = 'Create new scheduler task type';

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

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable("scheduler");
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `scheduler` CHANGE `type` `type` ENUM('script_exec','terminate_farm','launch_farm','fire_event') NULL DEFAULT NULL;");
    }
}