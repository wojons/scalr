<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Platform Usage Statistics
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 *
 * @Entity
 * @Table(name="platform_usage")
 */
class PlatformUsage extends AbstractEntity
{
    /**
     * Time the sensor collects info
     *
     * @Id
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $time;

    /**
     * Platform name
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $platform;

    /**
     * A value
     *
     * @Column(type="integer")
     * @var int
     */
    public $value;
}
