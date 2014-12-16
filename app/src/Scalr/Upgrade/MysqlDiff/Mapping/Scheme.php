<?php

namespace Scalr\Upgrade\MysqlDiff\Mapping;

use Scalr\Util\ObjectAccess;

/**
 * Class Scheme
 * @package Scalr\Upgrade\MysqlDiff\Mapping
 */
class Scheme extends ObjectAccess
{

    /**
     * @var Table[]
     */
    public $referencedTables = [ ];

    /**
     * @var Column[]
     */
    public $fks = [ ];

    /**
     * Append table to scheme
     *
     * @param   Table $table
     */
    public function addTable(Table $table)
    {
        $this[ $table->name ] = $table;
    }

    /**
     * Rename table in scheme
     *
     * @param   $old
     * @param   $new
     */
    public function renameTable($old, $new)
    {
        $this[ $new ] = $this[ $old ];
        unset($this[ $old ]);
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return implode("\n", $this->data);
    }
}
