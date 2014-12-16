<?php

namespace Scalr\Upgrade\MysqlDiff\Mapping;

/**
 * Class IndexColumn
 * @package Scalr\Upgrade\MysqlDiff\Mapping
 */
class IndexColumn
{

    /**
     * @var Column
     */
    private $column;

    /**
     * @var int
     */
    private $length;

    /**
     * @var string
     */
    private $direction;

    /**
     * IndexColumn
     *
     * @param   Column $column
     * @param   int    $length
     * @param   string $direction
     */
    public function __construct(Column &$column = null, $length = null, $direction = null)
    {
        $this->column    = &$column;
        $this->length    = $length;
        $this->direction = $direction;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return "`{$this->column->name}`"
               . ($this->length ? "({$this->length})" : '')
               . ($this->direction ? " {$this->direction}" : '');
    }
}
