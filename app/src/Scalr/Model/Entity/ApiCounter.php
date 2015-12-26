<?php

namespace Scalr\Model\Entity;

use DateTime;
use DateTimeZone;
use Scalr\Exception\LimitExceededException;
use Scalr\Model\AbstractEntity;

/**
 * ApiCounter entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="api_counters")
 */
class ApiCounter extends AbstractEntity
{

    /**
     * Calculation date and time
     *
     * @Id
     * @Column(type="UTCDatetimeNearMinute")
     * @var int
     */
    public $date;

    /**
     * API key identifier
     *
     * @Id
     * @Column(type="string")
     *
     * @var string
     */
    public $apiKeyId;

    /**
     * Requests count
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $requests = 0;
}