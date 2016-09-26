<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160121182000 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '812632f2-2ce4-42d2-a505-d67573e3c7fa';

    protected $depends = [];

    protected $description = 'Change image status from delete to pending_delete';

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
        $found = $this->db->GetOne("SELECT * FROM images WHERE status = ? LIMIT 1", array('delete'));
        return $found === null;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out('Renaming image status to pending_delete');
        $this->db->Execute("UPDATE images SET status = ? WHERE status = ?", array('pending_delete', 'delete'));
    }
}
