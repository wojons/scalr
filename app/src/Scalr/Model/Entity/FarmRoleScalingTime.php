<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * FarmRoleScalingTime entity
 *
 * @author  Andrii Penchuk  <a.penchuk@scalr.com>
 * @since   5.11.9 (05.02.2016)
 *
 * @Entity
 * @Table(name="farm_role_scaling_times")
 */
class FarmRoleScalingTime extends AbstractEntity
{
    /**
     * Identifier
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * FarmRole Id
     *
     * @Column(name="farm_roleid",type="integer",nullable=true)
     * @var int
     */
    public $farmRoleId;

    /**
     * Start time
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $startTime;

    /**
     * End of the frame
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $endTime;

    /**
     * Days of Week is applied on
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $daysOfWeek;

    /**
     * Number of the instances which should be running
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $instancesCount;
}