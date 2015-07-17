<?php

namespace Scalr\Api\Rest\Routing;

/**
 * Routing PathPart
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (13.02.2015)
 */
class PathPart
{
    const TYPE_STRING = 1;
    const TYPE_REGEXP = 2;
    const TYPE_REGEXP_PATH = 3;

    /**
     * Part type
     *
     * @var int
     */
    public $type;

    /**
     * The string or regexp value
     *
     * @var string
     */
    public $value;

    /**
     * Constructor
     *
     * @param    string $value  optional The value
     * @param    int    $type   optional The type
     */
    public function __construct($value = '', $type = self::TYPE_STRING)
    {
        $this->type = $type ?: self::TYPE_STRING;
        $this->value = $value ?: '';
    }

    /**
     * Checks whether the part is of string type
     *
     * @return boolean Returns TRUE if the part is of string type or FALSE otherwise
     */
    public function isString()
    {
        return $this->type === self::TYPE_STRING;
    }

    /**
     * Checks whether the part is of regexp type
     *
     * @return boolean Returns TRUE if the part is of regexp type or FALSE otherwise
     */
    public function isRegexp()
    {
        return $this->type === self::TYPE_REGEXP;
    }

    /**
     * Checks whether the part is of regexp path type
     *
     * @return boolean Returns TRUE if the part is of regexp path type or FALSE otherwise
     */
    public function isRegexpPath()
    {
        return $this->type === self::TYPE_REGEXP_PATH;
    }
}