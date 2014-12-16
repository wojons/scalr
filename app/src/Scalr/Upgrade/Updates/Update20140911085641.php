<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140911085641 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '9c58ce48-f883-4d0b-8a00-44ea0ab73279';

    protected $depends = [];

    protected $description = 'Webhook history handle_attempts update';

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
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute('
            UPDATE webhook_history
            SET handle_attempts = 1
            WHERE handle_attempts = 0
            AND status IN (1, 2)'
        );
        $this->console->notice('%d webhooks history records has been updated', $this->db->Affected_Rows());
    }
}