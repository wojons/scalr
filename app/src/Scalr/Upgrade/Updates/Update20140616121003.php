<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140616121003 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '4c03d6a6-b7f3-45c9-a3cb-77d4f13979c1';

    protected $depends = ['e38a31ae-dd51-4117-9abc-153db3ae173f'];

    protected $description = 'Add script_path to script_shortcuts';

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
        return $this->hasTableColumn('script_shortcuts', 'script_path');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('script_shortcuts');
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE script_shortcuts MODIFY `script_id` int(11) NULL, ADD script_path VARCHAR(255) NOT NULL AFTER script_id');
    }
}