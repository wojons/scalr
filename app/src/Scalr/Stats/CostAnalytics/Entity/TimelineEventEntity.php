<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use DateTime, DateTimeZone;

/**
 * TimelineEventEntity
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 * @Entity
 * @Table(name="timeline_events",service="cadb")
 */
class TimelineEventEntity extends \Scalr\Model\AbstractEntity
{

    const EVENT_TYPE_REPLACE_PROJECT = 1;

    const EVENT_TYPE_ASSIGN_PROJECT = 2;

    const EVENT_TYPE_REPLACE_COST_CENTER = 3;

    const EVENT_TYPE_ASSIGN_COST_CENTER = 4;

    const EVENT_TYPE_CHANGE_CLOUD_PRICING = 5;

    /**
     * Event identifier
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $uuid;

    /**
     * The event message
     *
     * @Column(type="string")
     * @var string
     */
    public $description;

    /**
     * User id
     *
     * @Column(type="integer")
     * @var int
     */
    public $userId;

    /**
     * Account id
     *
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * Environment id
     *
     * @Column(type="integer")
     * @var int
     */
    public $envId;

    /**
     * The event type
     *
     * @Column(type="integer")
     * @var int
     */
    public $eventType;

    /**
     * The date and time Y-m-d H:00:00
     *
     * @Column(type="UTCDatetime")
     * @var DateTime
     */
    public $dtime;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dtime = new DateTime('now', new DateTimeZone('UTC'));
    }
}