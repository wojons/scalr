<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150602134011 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '722ec49f-9e39-468e-aee5-cc32d9d1e84f';

    protected $depends = ['1a6723e8-0173-4f74-bd9c-c21dc97365aa'];

    protected $description = "Removes deprecated auditlog tables";

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
        return !$this->hasTable('auditlog') || !$this->hasTableColumn('auditlog', 'datatype');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('auditlog') && $this->hasTable('auditlog_data') && $this->hasTable('auditlog_tags');
    }

    protected function run1($stage)
    {
        $this->dropTables(['auditlog_data', 'auditlog_tags', 'auditlog']);
    }
}