<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\EventDefinition;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use DateTime;

class Update20150518125920 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '05f0f437-793c-49f6-9b28-5a2270be663b';

    protected $depends = [];

    protected $description = "Add 'created' column to scalr.event_definitions table.";

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
        return $this->hasTableColumn('event_definitions', 'created') &&
        $this->hasTableIndex('event_definitions', 'idx_created');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('event_definitions');
    }

    protected function run1($stage)
    {
        $table = 'event_definitions';

        $sql = [];

        if (!$this->hasTableColumn($table, 'created')) {
            $bCreated = true;
            $this->console->out("Adding scalr.event_definitions.created column...");
            $sql[] = "ADD COLUMN `created` DATETIME NOT NULL COMMENT 'Created at timestamp' AFTER `description`";
        }

        if (!$this->hasTableIndex($table, 'idx_created')) {
            $this->console->out('Adding index by `created` to `event_definitions`');
            $sql[] = 'ADD INDEX `idx_created` (created ASC)';
        }

        if (!empty($sql)) {
            $this->applyChanges($table, $sql);
        }

        if (!empty($bCreated)) {
            $date = new DateTime();
            $date->modify('-1 hour');

            $list = EventDefinition::find([['$or' => [['created' => null], ['created' => new \DateTime('0000-00-00 00:00:00')]]]]);

            foreach ($list as $event) {
                /* @var $event EventDefinition */
                $event->created = $date;
                $event->save();
                $date->modify('+1 second');
            }
        }
    }

}