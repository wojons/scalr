<?php

namespace Scalr\Upgrade;

/**
 * SequenceInterface
 *
 * When update needs to be performed by stages this interface should be
 * implemented for Update class.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (15.10.2013)
 */
interface SequenceInterface
{
    /**
     * Gets a number of the stages
     *
     * @return  int    Returns number stages
     */
    public function getNumberStages();
}