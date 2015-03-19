<?php
namespace Scalr\Upgrade\Updates;

use DateTime;
use MESSAGE_STATUS;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Upgrade\SequenceInterface;

class Update20150228144346 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '16105e26-2b55-42a4-be54-0903fe6f6c29';

    protected $depends = [];

    protected $description = 'Normalize `messages`, replace surrogate primary key by compound natural key';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private static $index = [
        1 => 'messageid',
        2 => 'server_id'
    ];

    private $origin = 'messages';

    private $temporary;

    private $backup;

    private $changes = 0;

    /**
     * {@inheritdoc}
     * @see AbstractUpdate::__construct()
     */
    public function __construct(\SplFileInfo $fileInfo, \ArrayObject $collection)
    {
        parent::__construct($fileInfo, $collection);

        $timestamp = (new DateTime())->getTimestamp();

        $this->temporary = "{$this->origin}_temporary_{$timestamp}";

        $this->backup = "{$this->origin}_backup_{$timestamp}";
    }

    public function __destruct()
    {
        $this->db->Execute("DROP TABLE IF EXISTS `{$this->temporary}`");
    }

    public function prepare($origin, $tmp, $dropIfExist = false)
    {
        if ($dropIfExist && $this->hasTable($tmp)) {
            $this->db->Execute("DROP TABLE IF EXISTS `{$tmp}`");
        }

        $this->db->Execute("CREATE TABLE IF NOT EXISTS `{$tmp}` LIKE `{$origin}`");
    }

    public function swapTables($origin, $tmp, $bkup)
    {
        $this->db->Execute("RENAME TABLE `{$origin}` TO `{$bkup}`, `{$tmp}` TO `{$origin}`");
    }

    public function copyActualData($target, $source, $fields, $where = '', array $params = [])
    {
        $fields = '`' . implode('`,`', $fields) . '`';

        $query = "
            INSERT INTO `{$target}`
            ($fields)
              SELECT
                $fields
              FROM `{$source}`
              {$where}";

        $this->db->Execute($query, $params);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 7;
    }

    protected function isApplied1($stage)
    {
        $this->prepare($this->origin, $this->temporary);

        $column = $this->getTableColumnDefinition($this->temporary, 'messageid');
        return $column && $column->isNullable == 'NO';
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable($this->temporary) && $this->hasTableColumn($this->temporary, 'messageid');
    }

    protected function run1($stage)
    {
        $column = $this->getTableColumnDefinition($this->temporary, 'messageid');
        $this->db->Execute("ALTER TABLE `{$this->temporary}` CHANGE COLUMN `messageid` `messageid` {$column->columnType} NOT NULL");
        $this->changes++;
    }

    protected function isApplied2($stage)
    {
        $this->prepare($this->origin, $this->temporary);

        $column = $this->getTableColumnDefinition($this->temporary, 'server_id');
        return $column && $column->isNullable == 'NO';
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable($this->temporary) && $this->hasTableColumn($this->temporary, 'server_id');
    }

    protected function run2($stage)
    {
        $column = $this->getTableColumnDefinition($this->temporary, 'server_id');
        $this->db->Execute("ALTER TABLE `{$this->temporary}` CHANGE COLUMN `server_id` `server_id` {$column->columnType} NOT NULL");
        $this->changes++;
    }

    protected function isApplied3($stage)
    {
        $this->prepare($this->origin, $this->temporary);

        $expected = static::$index;
        $index = $this->getTableIndex($this->temporary, 'PRIMARY');

        foreach ($index as $column) {
            if ($column['Column_name'] == $expected[$column['Seq_in_index']]) {
                unset($expected[$column['Seq_in_index']]);
            }
        }

        return empty($expected);
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable($this->temporary) && $this->hasTableIndex($this->temporary, 'PRIMARY');
    }

    protected function run3($stage)
    {
        $sql = "ALTER TABLE `{$this->temporary}` ";

        if ($this->hasTableColumn($this->temporary, 'id') &&
            $this->getTableColumnDefinition($this->temporary, 'id')->extra == 'auto_increment') {
            $sql .= "DROP COLUMN `id`, ";
        }

        $sql .= "DROP PRIMARY KEY";

        $this->db->Execute($sql);
        $this->changes++;
    }

    protected function isApplied4($stage)
    {
        return $this->isApplied3($stage);
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable($this->temporary) &&
               $this->getTableColumnDefinition($this->temporary, 'messageid')->isNullable == 'NO' &&
               $this->getTableColumnDefinition($this->temporary, 'server_id')->isNullable == 'NO' &&
              !$this->hasTableIndex($this->temporary, 'PRIMARY');
    }

    protected function run4($stage)
    {
        $this->db->Execute("ALTER TABLE `{$this->temporary}` ADD PRIMARY KEY (`messageid`, `server_id`)");
        $this->changes++;
    }

    protected function isApplied5($stage)
    {
        $this->prepare($this->origin, $this->temporary);

        return !in_array(
            'server_message',
            (array) $this->hasTableCompatibleIndex($this->temporary, static::$index, true)
        );
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable($this->temporary) && $this->hasTableIndex($this->temporary, 'server_message');
    }

    protected function run5($stage)
    {
        $this->db->Execute("ALTER TABLE `{$this->temporary}` DROP INDEX `server_message`");
        $this->changes++;
    }

    protected function isApplied6($stage)
    {
        $this->prepare($this->origin, $this->temporary);

        return $this->hasTableCompatibleIndex($this->temporary, [1 => 'dtadded']);
    }

    protected function validateBefore6($stage)
    {
        return $this->hasTable($this->temporary) && !$this->hasTableIndex($this->temporary, 'dtadded');
    }

    protected function run6($stage)
    {
        $this->db->Execute("ALTER TABLE `{$this->temporary}` ADD INDEX `dtadded_idx` (`dtadded` ASC);");
        $this->changes++;
    }

    protected function isApplied7($stage)
    {
        return !(bool) $this->changes;
    }

    protected function validateBefore7($stage)
    {
        return $this->hasTable($this->origin) &&
              !$this->hasTable($this->backup) &&
               $this->hasTable($this->temporary);
    }

    protected function run7($stage)
    {
        $fields = [
            'messageid',
            'processing_time',
            'status',
            'handle_attempts',
            'dtlasthandleattempt',
            'dtadded',
            'message',
            'server_id',
            'event_server_id',
            'type',
            'message_name',
            'message_version',
            'message_format',
            'ipaddress',
            'event_id'
        ];

        $this->swapTables($this->origin, $this->temporary, $this->backup);

        $this->console->notice("You need to restart Python and Scalr services!");

        $this->copyActualData($this->origin, $this->backup, $fields, "WHERE `status` = ?", [MESSAGE_STATUS::PENDING]);

        $this->db->Execute("DROP TABLE IF EXISTS `{$this->backup}`");
    }
}