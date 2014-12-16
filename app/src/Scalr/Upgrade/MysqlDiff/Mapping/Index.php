<?php

namespace Scalr\Upgrade\MysqlDiff\Mapping;

/**
 * Class Index
 * @package Scalr\Upgrade\MysqlDiff\Mapping
 */
class Index
{

    protected static $keys = [ 'PRIMARY', 'UNIQUE' ];

    /**
     * @var string
     */
    public $name;

    /**
     * @var IndexColumn[]
     */
    protected $columns;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    public $storage;

    /**
     * Index
     *
     * @param   string        $name
     * @param   IndexColumn[] $columns
     * @param   string        $type
     * @param   string        $storage
     */
    public function __construct($name, $columns, $type = 'INDEX', $storage = 'BTREE')
    {
        $this->name    = $name;
        $this->columns = $columns;
        $this->type    = $type;
    }

    /**
     * @return  string
     */
    public function getType()
    {
        return in_array($this->type, static::$keys) ? "{$this->type} KEY" : $this->type;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        $type = $this->getType();

        return "{$type}"
               . ($this->name ? " `{$this->name}`" : '')
               . ($this->storage ? " USING {$this->storage}" : '')
               . ' (' . implode(',', $this->columns) . ')';
    }
}
