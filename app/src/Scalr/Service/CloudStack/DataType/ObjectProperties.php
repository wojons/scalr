<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ObjectProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.0.0
 *
 */
class ObjectProperties
{
    /**
     * Gets public properties of an object
     *
     * @param object $object
     * @return array
     */
    public static function get($object)
    {
        return get_object_vars($object);
    }

}