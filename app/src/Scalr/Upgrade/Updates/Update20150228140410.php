<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Os;

class Update20150228140410 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '895fafd2-f068-47f6-b055-abb5fb7505c0';

    protected $depends = [];

    protected $description = "Add 'created' column to scalr.os table.";

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
        return $this->hasTableColumn('os', 'created') &&
               $this->hasTableIndex('os', 'idx_created');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('os');
    }

    protected function run1($stage)
    {
        $stmt = '';

        if (!$this->hasTableColumn('os', 'created')) {
            $bCreated = true;
            $this->console->out("Adding scalr.os.created column...");
            $stmt .= ", ADD COLUMN `created` DATETIME NOT NULL COMMENT 'Created at timestamp' AFTER `is_system`";
        }

        if (!$this->hasTableIndex('os', 'idx_created')) {
            $this->console->out("Adding idx_created index for scalr.os.created column...");
            $stmt .= ", ADD INDEX `idx_created` (`created` ASC)";
        }

        if (!empty($stmt)) {
            $this->db->Execute("ALTER TABLE `os` " . ltrim($stmt, ','));
        }

        if (!empty($bCreated)) {
            $date = new \DateTime();
            $date->modify('-1 hour');

            $list = Os::find([['$or' => [['created' => null], ['created' => new \DateTime('0000-00-00 00:00:00')]]]]);
            foreach ($list as $os) {
                /* @var $os Os */
                $os->created = $date;
                $os->save();
                $date->modify('+1 second');
            }
        }
    }
}