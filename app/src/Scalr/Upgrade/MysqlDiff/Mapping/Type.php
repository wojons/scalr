<?php

namespace Scalr\Upgrade\MysqlDiff\Mapping;

/**
 * Class Type
 * @package Scalr\Upgrade\MysqlDiff\Mapping
 */
class Type
{

    /**
     * @var string
     */
    public $type;

    /**
     * @var string type range
     */
    public $values;

    /**
     * @var bool
     */
    public $unsigned;

    /**
     * @var bool
     */
    public $zerofill;

    /**
     * @var bool
     */
    public $binary;

    /**
     * @var string text-field character set
     */
    public $charset;

    /**
     * @var string text-field collate
     */
    public $collate;

    /**
     * Type
     *
     * @param   string $type
     * @param   string $values
     * @param   bool   $unsigned
     * @param   bool   $zerofill
     * @param   bool   $binary
     * @param   string $charset
     * @param   string $collate
     */
    public function __construct($type, $values = null, $unsigned = null, $zerofill = null, $binary = null, $charset = null, $collate = null)
    {
        $this->type     = $type;
        $this->values   = $values;
        $this->unsigned = $unsigned;
        $this->zerofill = $zerofill;
        $this->binary   = $binary;
        $this->charset  = $charset;
        $this->collate  = $collate;
    }

    /**
     * Used for simple comparisons as strings
     *
     * @return  string
     */
    public function __toString()
    {
        return $this->type
               . ($this->values ? "({$this->values})" : '')
               . ($this->unsigned ? " UNSIGNED" : '')
               . ($this->zerofill ? " ZEROFILL" : '')
               . ($this->binary ? " BINARY" : '')
               . ($this->charset ? " CHARACTER SET {$this->charset}" : '')
               . ($this->collate ? " COLLATE {$this->collate}" : '');
    }
}
