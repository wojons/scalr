<?php

namespace Scalr\Util;

/**
 * Callback trait
 * It allows to set callback
 *
 * @author N.V.
 */
trait CallbackTrait
{

    /**
     * Callback
     *
     * @var callable
     */
    protected $callback;

    /**
     * Sets callback
     *
     * @param callable $callback optional A new callback
     *
     * @return callable Returns previous callback
     */
    public function setCallback(callable $callback = null)
    {
        $previous = $this->callback;

        $this->callback = $callback;

        return $previous;
    }
}