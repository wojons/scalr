<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Script;

class Update20140708115408 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '4aa60136-130b-4855-b508-4f15718250c3';

    protected $depends = ['c908b8da-0481-49a0-9fe7-6fc65e408f6b'];

    protected $description = 'Add type field for scripts';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('scripts') && $this->hasTableColumn('scripts', 'os');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('scripts');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE scripts ADD `os` VARCHAR(16) NOT NULL AFTER dt_changed");

        foreach ($this->db->GetAll("SELECT script_id, SUBSTRING(content, 1, 64) AS content FROM script_versions GROUP BY script_id ORDER BY MAX(version) DESC") as $script) {
            $this->db->Execute('UPDATE scripts SET `os` = ? WHERE id = ?', [
                (!strncmp($script['content'], '#!cmd', strlen('#!cmd')) || !strncmp($script['content'], '#!powershell', strlen('#!powershell'))) ? Script::OS_WINDOWS : Script::OS_LINUX,
                $script['script_id']
            ]);
        }
    }

    protected function isApplied2()
    {
        return !$this->hasTableColumn('scripts', 'type');
    }

    protected function validateBefore2()
    {
        return $this->hasTable('scripts');
    }

    protected function run2()
    {
        $this->db->Execute('ALTER TABLE scripts DROP `type`');
    }
}
