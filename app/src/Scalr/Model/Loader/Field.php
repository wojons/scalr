<?php
namespace Scalr\Model\Loader;

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
     * @var \Scalr\Model\Mapping\Column
     */
    public $column;

    /**
     * @var \Scalr\Model\Mapping\GeneratedValue
     */
    public $generatedValue;

    /**
     * @var \Scalr\Model\Mapping\Id
     */
    public $id;

    /**
     * @var AbstractType
     */
    public $type;

    /**
     * The entity this field corresponds to
     *
     * @var Scalr\Model\Loader\Entity
     */
    private $_entity;

    /**
     * Sets type
     *
     * @param   AbstractType $type  The type object
     * @return  \Scalr\Model\Loader\Field
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
     * @return  \Scalr\Model\Loader\Field
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
        return '`' . ($tableAlias ?: $this->getEntityAnnotation()->table->name) . '`.`' . $this->column->name . '`' .
               (!empty($alias) ? ' AS `' . $alias . '`' : '');
    }
}