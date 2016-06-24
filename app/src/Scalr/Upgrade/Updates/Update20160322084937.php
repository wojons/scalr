<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160322084937 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '91e06524-a19a-4111-8362-cb7a7e0bae9b';

    protected $depends = [];

    protected $description = 'Add default_priority to client_environments';

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
        return $this->hasTableColumn('client_environments', 'default_priority');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('client_environments');
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            ALTER TABLE `client_environments`
            ADD COLUMN `default_priority` int NOT NULL DEFAULT 0 COMMENT 'Default priority' AFTER `status`
        ");
    }
}