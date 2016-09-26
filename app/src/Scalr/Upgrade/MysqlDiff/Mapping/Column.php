<?php

namespace Scalr\Upgrade\MysqlDiff\Mapping;

/**
 * Class Column
 * @package Scalr\Upgrade\MysqlDiff\Mapping
 */
class Column
{

    /**
     * @var bool TRUE to include comments comparison, FALSE otherwise
     */
    public static $compareMode = false;

    /**
     * @var string
     */
    public $name;

    /**
     * @var Type
     */
    public $type;

    /**
     * @var bool
     */
    public $null;

    /**
     * @var string
     */
    public $default;

    /**
     * @var bool
     */
    public $ai;

    /**
     * @var string index type
     */
    public $key;

    /**
     * @var string
     */
    public $comment;

    /**
     * @var string
     */
    public $format;

    /**
     * @var string foreign key constraint
     */
    public $references;

    /**
     * Column
     *
     * @param   string $name
     * @param   Type   $type
     * @param   bool   $null
     * @param   string $default
     * @param   bool   $ai
     * @param   string $key
     * @param   string $comment
     * @param   string $format
     * @param   string $references
     */
    public function __construct($name, Type $type, $null = true, $default = null, $ai = null, $key = null, $comment = null, $format = null, $references = null)
    {
        $this->name       = $name;
        $this->type       = $type;
        $this->null       = !$null || ($null == 'NULL');
        $this->default    = $default;
        $this->ai         = $ai;
        $this->key        = $key;
        $this->comment    = $comment;
        $this->format     = $format;
        $this->references = $references;
    }

    /**
     * Used for simple comparisons as strings
     * @return  string
     */
    public function __toString()
    {
        return "`{$this->name}` {$this->type}"
               . ($this->null ? " NULL" : ' NOT NULL')
               . ($this->default ? " DEFAULT {$this->default}" : '')
               . ($this->ai ? " AUTO_INCREMENT" : '')
               . ($this->key ? " {$this->key}" : '')
               . ((static::$compareMode && $this->comment) ? " COMMENT '{$this->comment}'" : '')
               . ($this->format ? " COLUMN_FORMAT {$this->format}" : '')
               . ($this->references ? " {$this->references}" : '');
    }
}
