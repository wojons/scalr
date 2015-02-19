<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

class Update20141203134531 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'a5393964-d0da-42ba-a3a4-f64420571b63';

    protected $depends = [];

    protected $description = 'Update projects with shared=SHARED_TO_OWNER to SHARED_WITHIN_ENV';

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
        return $this->db->GetOne("SELECT 1 FROM `projects` WHERE shared = ? LIMIT 1", [ProjectEntity::SHARED_TO_OWNER]) ? false : true;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('projects');
    }

    protected function run1($stage)
    {
        $this->db->Execute('UPDATE `projects` SET shared = ? WHERE shared = ?', [ProjectEntity::SHARED_WITHIN_ENV, ProjectEntity::SHARED_TO_OWNER]);
        $affectedRows = $this->db->Affected_Rows();

        $this->console->out('%d record(s) have been updated', $affectedRows);
    }
}