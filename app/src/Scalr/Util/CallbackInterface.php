<?php

namespace Scalr\Util;

/**
 * Callback Interface
 * Class that implements this interface allows to set some callback
 *
 * @AUTHOR N.V.
 */
interface CallbackInterface
{

    /**
     * Sets callback
     *
     * @param callable $callback optional A new callback
     *
     * @return callable Returns previous callback
     */
    public function setCallback(callable $callback = null);
}