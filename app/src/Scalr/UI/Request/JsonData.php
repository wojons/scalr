<?php

namespace Scalr\UI\Request;

use ArrayObject;

/**
 * JsonData
 *
 * Convert input json string to array object
 *
 * @author   Igor Vodiasov <invar@scalr.com>
 * @since    5.0.0 (19.06.2014)
 */
class JsonData extends ArrayObject implements ObjectInitializingInterface
{
    /**
     * @param mixed $value
     * @return JsonData
     */
    public static function initFromRequest($value)
    {
        $decoded = json_decode($value, true);
        return new self($decoded ? $decoded : []);
    }

    /**
     * Checks if a value exists in an array
     *
     * @param   mixed   $needle  A value
     * @param   boolean $strict optional If it is set to TRUE then function will also
     *                          check the types of the needle in the haystack.
     * @return  boolean Returns TRUE if needle is found in the array, FALSE otherwise.
     */
    public function has($needle, $strict = false)
    {
        return in_array($needle, (array)$this, $strict);
    }

    /**
     * Searches the array for a given value and returns the corresponding key if successful
     *
     * @param   mixed    $needle  The searched value
     * @param   bool     $strict  optional Will searchf for identical elements
     * @return  mixed    Returns the key for needle if it is found in the array, FALSE otherwise.
     */
    public function search($needle, $strict = false)
    {
        return array_search($needle, (array)$this, $strict);
    }
}
