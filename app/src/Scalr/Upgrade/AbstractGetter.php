<?php

namespace Scalr\Upgrade;

use Scalr\Exception;

/**
 * AbstractGetter
 *
 * Handles access to protected properties of the class.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (10.10.2013)
 */
abstract class AbstractGetter
{
    public function __get($prop)
    {
        if (property_exists($this, $prop)) {
            return $this->$prop;
        }
        throw new Exception\UpgradeException(sprintf(
            'Property "%s" does not exist for the class "%s".',
            $prop, get_class($this)
        ));
    }

    public function __isset($prop)
    {
        return isset($this->$prop);
    }
}