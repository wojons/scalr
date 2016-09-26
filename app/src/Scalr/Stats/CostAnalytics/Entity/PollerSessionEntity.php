<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Model\AbstractEntity;

/**
 * PollerSessionEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (09.04.2014)
 * @Entity
 * @Table(name="poller_sessions",service="cadb")
 */
class PollerSessionEntity extends AbstractEntity
{

    /**
     * The unique identifier of the record
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="uuid")
     * @var string
     */
    public $sid;

    /**
     * The identifier of the account
     *
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * The identifier of the environment
     *
     * @Column(type="integer")
     * @var int
     */
    public $envId;

    /**
     * The timestamp
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
     * The keystone endpoint url for the private cloud
     *
     * @var string
     */
    public $url;

    /**
     * The cloud location where node is being run
     *
     * @var string
     */
    public $cloudLocation;

    /**
     * The cloud account number
     *
     * @var string
     */
    public $cloudAccount;
}