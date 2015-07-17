<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * AbstractSettingEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (30.05.2014)
 */
abstract class AbstractSettingEntity extends AbstractEntity
{
    /**
     * The field name for the value property
     */
    const ENTITY_VALUE_FIELD_NAME = 'value';

    /**
     * Gets a value from setting
     *
     * @param   string|array $pk        The primary key
     * @return  string       Returns a value from setting
     */
    public static function getValue($pk)
    {
        $pk = is_array($pk) ? $pk : [$pk];

        $entity = call_user_func_array("static::findPk", $pk);

        return $entity !== null ? $entity->{static::ENTITY_VALUE_FIELD_NAME} : null;
    }

    /**
     * Sets a value to setting
     *
     * @param   string|array $pk     The primary key of the setting
     * @param   string       $value  The value
     * @return  AbstractSettingEntity|null Returns the entity object or NULL if it has been removed
     */
    public static function setValue($pk, $value)
    {
        $pk = is_array($pk) ? $pk : [$pk];

        $entity = new static;

        $iterator = $entity->getIterator();

        foreach ($iterator->getPrimaryKey() as $name) {
            $entity->$name = array_shift($pk);
        }

        $entity->{static::ENTITY_VALUE_FIELD_NAME} = $value;
        $entity->save();

        return $entity->{static::ENTITY_VALUE_FIELD_NAME} === null ? null : $entity;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::save()
     */
    public function save()
    {
        if ($this->{static::ENTITY_VALUE_FIELD_NAME} === null) {
            $this->delete();
        } else {
            parent::save();
        }
    }
}