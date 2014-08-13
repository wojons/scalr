<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Model\AbstractEntity;

/**
 * UsageHourlyNotManagedEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (06.03.2014)
 * @Entity
 * @Table(name="nm_usage_h",service="cadb")
 */
class UsageHourlyNotManagedEntity extends AbstractEntity
{
    /**
     * The unique identifier of the record
     *
     * @Id
     * @GeneratedValue("UUID")
     * @Column(type="uuid")
     * @var string
     */
    public $usageId;

    /**
     * The date and time Y-m-d H:00:00
     *
     * @Column(type="UTCDatetime")
     * @var DateTime
     */
    public $dtime;

    /**
     * The name of the cloud platform
     *
     * @var string
     */
    public $platform;

    /**
     * The cloud location where node is being run
     *
     * @var string
     */
    public $cloudLocation;

    /**
     * The type of the instance (flavor)
     *
     * @var string
     */
    public $instanceType;

    /**
     * The identifier of the operating system platform (0-linux, 1-windows)
     *
     * @Column(type="integer")
     * @var int
     */
    public $os;

    /**
     * The number of the nodes of the same type
     *
     * @Column(type="integer")
     * @var int
     */
    public $num;

    /**
     * The total cost of the usage of the node
     *
     * @Column(type="decimal", precision=12, scale=6)
     * @var float
     */
    public $cost;
}