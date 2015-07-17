<?php
namespace Scalr\Upgrade\Updates;

use DateTime;
use Exception;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150403103623 extends AbstractUpdate implements SequenceInterface
{

    const INIT_DATETIME = '1971-01-01 12:00:00';

    protected $uuid = 'f2319feb-d79c-436f-addd-a5a8b8ff0132';

    protected $depends = [];

    protected $description = 'Init `dt_added` for `images` table';

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
        return $this->hasTable('images') &&
               $this->hasTableColumn('images', 'hash') &&
               $this->hasTableColumn('images', 'dt_added');
    }

    protected function run1($stage)
    {
        $images = $this->db->Execute("SELECT `hash` FROM `images` WHERE `dt_added` IS NULL");

        if ($count = $images->NumRows()) {
            $this->console->out("Found {$count} records with non-initialized `dt_added` in `images` table");

            $stmt = $this->db->Prepare("UPDATE `images` SET `dt_added` = ? WHERE `hash` = ?");

            $date = new DateTime(self::INIT_DATETIME);

            $affected = 0;

            $this->db->BeginTrans();

            try {
                foreach ($images as $image) {
                    $this->db->Execute($stmt, [$date->format('Y-m-d H:i:s'), $image['hash']]);

                    $affected += $this->db->Affected_Rows();

                    $date->modify('+1 second');
                }
            } catch (Exception $e) {
                $this->db->RollbackTrans();

                $this->console->error("Update rolled back!");

                throw $e;
            }

            $this->db->CommitTrans();

            $this->console->out("Updated {$count} records in `images` table");
        }
    }
}