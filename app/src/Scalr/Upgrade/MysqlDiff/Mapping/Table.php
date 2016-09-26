<?php

namespace Scalr\Upgrade\MysqlDiff\Mapping;

/**
 * Class Table
 * @package Scalr\Upgrade\MysqlDiff\Mapping
 */
class Table
{

    /**
     * @var string[]
     */
    public static $excludedOptions = [ 'COMMENT', 'AUTO_INCREMENT' ];

    /**
     * @var string table name
     */
    public $name;

    /**
     * @var bool
     */
    public $temporary;

    /**
     * @var string[] table options
     */
    public $options = [ ];

    /**
     * @var Column[] columns
     */
    public $columns = [ ];

    /**
     * @var Index[] indexes
     */
    public $indexes = [ ];

    /**
     * Table
     *
     * @param   string $name
     * @param   bool   $temporary
     */
    public function __construct($name, $temporary = null)
    {
        $this->name      = $name;
        $this->temporary = (bool) $temporary;
    }

    /**
     * Sets table option
     *
     * @param $name
     * @param $value
     */
    public function setOption($name, $value)
    {
        $this->options[ $name ] = $value;
    }

    /**
     * Gets table option
     *
     * @param   $name
     *
     * @return  string
     */
    public function getOption($name)
    {
        return $this->options[ $name ];
    }

    /**
     * Append table columns
     *
     * @param   Column $column new column
     */
    public function addColumn(Column $column)
    {
        $this->columns[ $column->name ] = $column;
    }

    /**
     * Add index
     *
     * @param   Index $index
     */
    public function addIndex(Index $index)
    {
        $this->indexes[ $index->name ] = $index;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        $options = [ ];
        foreach ($this->options as $option => $value) {
            if (!in_array($option, static::$excludedOptions)) {
                $options[] = "{$option}=" . (preg_match('/\s/', $value) ? "'{$value}'" : $value);
            }
        }
        $options = implode(' ', $options);

        $columns = implode(",\n", $this->columns);

        $constraints = implode(",\n", $this->indexes);

        return "CREATE"
               . ($this->temporary ? ' TEMPORARY' : '')
               . " TABLE `{$this->name}` (\n"
               . $columns
               . ($constraints ? ",\n{$constraints}\n" : '\n')
               . ") {$options};";
    }
}
