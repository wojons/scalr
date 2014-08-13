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
}
