<?php

namespace Scalr\UI\Request;

/**
 * Interface ObjectInitializingInterface
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0.0 (19.06.2014)
 */
interface ObjectInitializingInterface
{
    /**
     * Create object from request var
     *
     * @param mixed $value
     * @return mixed
     */
    public static function initFromRequest($value);
}
