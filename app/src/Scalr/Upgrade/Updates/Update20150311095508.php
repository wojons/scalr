<?php
namespace Scalr\Upgrade\Updates;

use DateTime;
use Exception;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Upgrade\SequenceInterface;

class Update20150311095508 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '95a92c46-b523-4975-be4f-4f8110acd25b';

    protected $depends = [];

    protected $description = 'Optimize indexes in `images` table';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private $origin = 'images';

    private $sql = [];

    private static $index = [
        1 => 'id',
        2 => 'platform',
        3 => 'cloud_location',
        4 => 'env_id'
    ];

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 4;
    }

    protected function isApplied1($stage)
    {
        $expected = static::$index;
        $index = $this->getTableIndex($this->origin, 'idx_id');

        foreach ($index as $column) {
            if ($column['Column_name'] == $expected[$column['Seq_in_index']]) {
                unset($expected[$column['Seq_in_index']]);
            }
        }

        return empty($expected);
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->sql[] = "DROP INDEX `idx_id`";
    }

    protected function isApplied2($stage)
    {
        return (bool) $this->hasTableCompatibleIndex($this->origin, static::$index, true);
    }

    protected function validateBefore2($stage)
    {
        return array_search("DROP INDEX `idx_id`", $this->sql) !== false || !$this->hasTableIndex($this->origin, 'idx_id');
    }

    protected function run2($stage)
    {
        $this->sql[] = "ADD UNIQUE INDEX `idx_id` (`id` ASC, `platform` ASC, `cloud_location` ASC, `env_id` ASC)";
    }

    protected function isApplied3($stage)
    {
        $indexes = $this->hasTableCompatibleIndex($this->origin, [1 => 'env_id']);
        $dropped = [];

        foreach ($indexes as $index) {
            if (array_search("DROP INDEX `{$index}`", $this->sql) !== false) {
                $dropped[] = $index;
            }
        }

        $indexes = array_diff($indexes, $dropped);

        return !empty($indexes);
    }

    protected function validateBefore3($stage)
    {
        return !$this->hasTableIndex($this->origin, 'env_id_idx');
    }

    protected function run3($stage)
    {
        $this->sql[] = "ADD INDEX `env_id_idx` (`env_id` ASC)";
    }

    protected function isApplied4($stage)
    {
        return empty($this->sql);
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable($this->origin);
    }

    protected function run4($stage)
    {
        $this->applyChanges($this->origin, $this->sql);
    }
}