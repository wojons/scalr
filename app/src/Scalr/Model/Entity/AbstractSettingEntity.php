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
     * Misc cache
     *
     * @var array
     */
    private static $cache = [];

    /**
     * Gets a value from setting
     *
     * @param   string    $id        The identifier of the setting
     * @param   string    $useCache  optional Whether it should use cache
     * @return  string    Returns a value from setting
     */
    public static function getValue($id, $useCache = true)
    {
        $class = get_called_class();

        if (empty(self::$cache[$class]) || !array_key_exists($id, self::$cache[$class]) || !$useCache) {
            $entity = static::findPk($id);

            if ($entity === null) {
                self::$cache[$class][$id] = null;
            } else {
                self::$cache[$class][$id] = $entity->value;
            }
        }

        return self::$cache[$class][$id];
    }

    /**
     * Sets a value to setting
     *
     * @param   string    $id     The identifier of the setting
     * @param   string    $value  The value
     */
    public static function setValue($id, $value)
    {
        $class = get_called_class();

        self::$cache[$class][$id] = $value;

        $entity = new $class;
        $entity->id = $id;

        if ($value === null) {
            $entity->delete();
        } else {
            $entity->value = $value;
            $entity->save();
        }
    }
}