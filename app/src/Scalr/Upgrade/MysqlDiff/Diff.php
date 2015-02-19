<?php
namespace Scalr\Upgrade\MysqlDiff;

use Scalr\Upgrade\MysqlDiff\Mapping\Scheme;
use Scalr\Upgrade\MysqlDiff\Mapping\Table;
use SplFileObject;

/**
 * Class Diff
 * MySQL DDL comparator
 *
 * @package Scalr\Upgrade\MysqlDiff
 */
class Diff
{

    /**
     * Actual scheme
     *
     * @var Scheme
     */
    private $source;

    /**
     * Current scheme
     *
     * @var Scheme
     */
    private $target;

    /**
     * MySQL DDL comparator
     *
     * @param   SplFileObject $source actual scheme
     * @param   SplFileObject $target current scheme
     */
    public function __construct(SplFileObject $source, SplFileObject $target)
    {
        $parser = new DdlParser($source);
        $parser->parse();
        $this->source = $parser->getScheme();

        $parser = new DdlParser($target);
        $parser->parse();
        $this->target = $parser->getScheme();
    }

    /**
     * Compares two schemes and finds difference
     *
     * @return  string[] array of MySQL statements, execution of which will bring the current scheme up to date.
     */
    public function diff()
    {
        $diffsInit   = [];
        $diffsFollow = [];

        /* @var $source Table */
        foreach ($this->source as $source) {
            /* @var $target Table */
            if ($target = $this->target[$source->name]) {
                foreach ($source->columns as $name => $column) {
                    if (isset($target->columns[$name])) {
                        if ("{$column}" != "{$target->columns[$name]}") {
                            array_unshift($diffsFollow, "ALTER TABLE `{$target->name}` CHANGE COLUMN `{$column->name}` {$column};");
                        }
                    } else {
                        array_unshift($diffsFollow, "ALTER TABLE `{$target->name}` ADD COLUMN {$column};");
                    }
                }

                $missed = array_diff_key($target->columns, $source->columns);
                foreach ($missed as $name => $column) {
                    $diffsFollow[] = "ALTER TABLE `{$target->name}` DROP COLUMN `{$name}`;";
                }

                foreach ($source->indexes as $ix) {
                    if (isset($target->indexes[$ix->name])) {
                        $targetIndex = $target->indexes[$ix->name];
                        if ("{$ix}" != "{$targetIndex}") {
                            $diffsInit[]   = "ALTER TABLE `{$target->name}` DROP " . $targetIndex->getType() . ($targetIndex->name ? " `{$targetIndex->name}`;" : ';');
                            $diffsFollow[] = "ALTER TABLE `{$target->name}` ADD {$ix};";
                        }
                    } else {
                        $diffsFollow[] = "ALTER TABLE `{$target->name}` ADD {$ix};";
                    }
                }

                $missed = array_diff_key($target->indexes, $source->indexes);
                foreach ($missed as $name => $index) {
                    $type        = $index->getType();
                    $diffsInit[] = "ALTER TABLE `{$target->name}` DROP {$type} `{$name}`;";
                }

                foreach ($source->options as $name => $value) {
                    if (!in_array($name, Table::$excludedOptions) &&
                        !(isset($target->options[$name]) && $value == $target->options[$name])) {
                        $diffsInit[] = "ALTER TABLE `{$target->name}` {$name}={$value};";
                    }
                }
            } else {
                array_unshift($diffsInit, "{$source}");
            }

            unset($this->target[$source->name]);
        }

        foreach ($this->target as $table) {
            if ($references = $this->target->referencedTables[$table->name]) {
                foreach ($references as $fk) {
                    if ($fk) {
                        array_unshift($diffsInit, "ALTER TABLE `{$fk->table->name}` DROP FOREIGN KEY `{$fk->name}`;");
                    }
                }
            }

            $diffsFollow[] = "DROP TABLE `{$table->name}`;";
        }

        return array_merge($diffsInit, $diffsFollow);
    }
}
