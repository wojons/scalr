<?php

namespace Scalr\Upgrade\MysqlDiff\Mapping;

/**
 * Class ForeignKey
 * @package Scalr\Upgrade\MysqlDiff\Mapping
 */
class ForeignKey extends Index
{

    protected static $keys = [ 'FOREIGN' ];

    /**
     * @var Table
     */
    public $table;

    /**
     * @var Table
     */
    public $refTable;

    /**
     * @var IndexColumn[]
     */
    private $refColumns;

    /**
     * @var string
     */
    private $match;

    /**
     * @var string[]
     */
    private $actions;

    /**
     * ForeignKey
     *
     * @param   string        $name
     * @param   Table         $table
     * @param   IndexColumn[] $columns
     * @param   Table         $refTable
     * @param   IndexColumn[] $refColumns
     * @param   string        $match
     * @param   string[]      $actions
     */
    public function __construct($name, Table $table, $columns, Table $refTable, $refColumns, $match, $actions)
    {
        parent::__construct($name, $columns, 'FOREIGN');

        $this->table      = $table;
        $this->refTable   = $refTable;
        $this->refColumns = $refColumns;
        $this->match      = $match;
        $this->actions    = $actions;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        $columns = implode(',', $this->columns);

        $refColumns = implode(',', $this->refColumns);

        $actions = [ ];

        foreach ($this->actions as $event => $action) {
            $actions[] = "ON {$event} {$action}";
        }

        return "CONSTRAINT `{$this->name}` FOREIGN KEY ({$columns}) REFERENCES `{$this->refTable->name}` ({$refColumns})"
               . ($this->match ? " MATCH {$this->match}" : '')
               . ($actions ? ' ' . implode(' ', $actions) : '');
    }
}
