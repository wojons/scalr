<?php
namespace Scalr\Model\Loader;

use Scalr\Model\Mapping\Column;
use Scalr\Model\Mapping\GeneratedValue;
use Scalr\Model\Mapping\Id;
use Scalr\Model\Type\AbstractType;

/**
 * Field Annotation
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (06.03.2014)
 */
class Field
{
    /**
     * Field name
     *
     * @var string
     */
    public $name;

    /**
     * @var Column
     */
    public $column;

    /**
     * @var GeneratedValue
     */
    public $generatedValue;

    /**
     * @var Id
     */
    public $id;

    /**
     * @var AbstractType
     */
    public $type;

    /**
     * The entity this field corresponds to
     *
     * @var Entity
     */
    private $_entity;

    /**
     * Sets type
     *
     * @param   AbstractType $type  The type object
     * @return  Field
     */
    public function setType(AbstractType $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Gets type
     *
     * @return  AbstractType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the entity this field corresponds to
     *
     * @param   Entity $entity
     * @return  Field
     */
    public function setEntityAnnotation(Entity $entity)
    {
        $this->_entity = $entity;
        return $this;
    }

    /**
     * Gets the entity annotation this field corresponds to
     *
     * @return   Entity
     */
    public function getEntityAnnotation()
    {
        return $this->_entity;
    }

    /**
     * Get the name of the column prefixed either with the name of the table
     * or with the specified alias
     *
     * @param   string $tableAlias  optional An table alias
     * @param   string $alias       optional An column alias
     * @return  string Returns the name of the column with table prefix
     */
    public function getColumnName($tableAlias = null, $alias = null)
    {
        return '`' . ($tableAlias ?: str_replace('.', '`.`', $this->getEntityAnnotation()->table->name)) . '`.`' . $this->column->name . '`' .
               (!empty($alias) ? ' AS `' . $alias . '`' : '');
    }
}