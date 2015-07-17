<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150424104821 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'c407fb6c-9736-41d9-9e2c-472991a0a6af';

    protected $depends = [];

    protected $description = 'Add allow_script_parameters field to scripts table';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('scripts', 'allow_script_parameters');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Adding 'allow_script_parameters' field to 'scripts'");
        $this->db->Execute("ALTER TABLE `scripts` ADD COLUMN `allow_script_parameters` TINYINT(1) NOT NULL DEFAULT '0'");
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('scripts', 'allow_script_parameters');
    }

    protected function run2($stage)
    {
        $this->console->out('Fill allow_script_parameters field');
        $rows = $this->db->GetAll('SELECT s.id, v.variables FROM `script_versions` v INNER JOIN `scripts` s ON v.script_id = s.id;');
        foreach ($rows as $row) {
            $variables = unserialize($row['variables']);
            if (count($variables)) {
                $this->db->Execute("UPDATE `scripts` SET allow_script_parameters = 1 WHERE id = ?", [$row['id']]);
            }
        }

    }

}