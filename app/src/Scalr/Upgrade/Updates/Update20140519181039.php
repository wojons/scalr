<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140519181039 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'e40ca96b-9582-4e24-8d40-2c6a166ede0e';

    protected $depends = array();

    protected $description = 'Adding skip_private_gv column to webhook_configs table';

    protected $ignoreChanges = true;

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
        return $this->hasTableColumn('webhook_configs', 'skip_private_gv');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `webhook_configs` ADD `skip_private_gv` TINYINT(1) NULL DEFAULT '0'");
    }
}