<?php

namespace Scalr\UI\Request;

/**
 * RawData
 *
 * Returns original string without escaping special or html characters
 *
 * @author   Igor Vodiasov <invar@scalr.com>
 * @since    5.0.0 (19.06.2014)
 */
class RawData implements ObjectInitializingInterface
{
    /**
     * @var string
     */
    protected $value;

    /**
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @param mixed $value
     * @return RawData
     */
    public static function initFromRequest($value)
    {
        $obj = new self($value);
        return $obj;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }
}
