<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

class Update20141219100745 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'beabb100-c04c-45b3-8ea7-f3fca944e096';

    protected $depends = [];

    protected $description = 'Update projects with shared=SHARED_WITHIN_ENV to SHARED_WITHIN_ACCOUNT';

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
        return $this->db->GetOne("SELECT 1 FROM `projects` WHERE shared = ? LIMIT 1", [ProjectEntity::SHARED_WITHIN_ENV]) ? false : true;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('projects');
    }

    protected function run1($stage)
    {
        $this->db->Execute('UPDATE `projects` SET shared = ? WHERE shared = ?', [ProjectEntity::SHARED_WITHIN_ACCOUNT, ProjectEntity::SHARED_WITHIN_ENV]);
        $affectedRows = $this->db->Affected_Rows();

        $this->console->out('%d record(s) have been updated', $affectedRows);
    }
}