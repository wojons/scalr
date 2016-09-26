<?php

namespace Scalr\Api\DataType;


/**
 * Rest API AbstractDataType object
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (02.03.2015)
 */
abstract class AbstractDataType
{
    public function __set($name, $value)
    {
        throw new \BadMethodCallException(sprintf(
            "Unable to set the value of the property '%s'. It does not exist in the %s class",
            strip_tags($name), get_class($this)
        ));
    }
}