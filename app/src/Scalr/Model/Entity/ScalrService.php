<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * ScalrService entity
 *
 * @author   Roman Kondratuk  <r.kondratuk@scalr.com>
 * @since    5.11.9 (11.02.2016)
 *
 * @Entity
 * @Table(name="scalr_services")
 */
class ScalrService extends AbstractEntity
{
    /**
     * Service is scheduled
     */
    const STATE_SCHEDULED = 0;

    /**
     * Service is running
     */
    const STATE_RUNNING = 1;

    /**
     * Service is idling
     */
    const STATE_IDLE = 2;

    /**
     * Service was finished with an error
     */
    const STATE_FAILED = 3;

    /**
     * Service is disabled
     */
    const STATE_DISABLED = 4;

    /**
     * State display names
     */
    const STATE_NAMES = [
        self::STATE_RUNNING     => 'running',
        self::STATE_SCHEDULED   => 'scheduled',
        self::STATE_FAILED      => 'failed',
        self::STATE_IDLE        => 'idle',
        self::STATE_DISABLED    => 'disabled'
    ];

    /**
     * It should not report about some internal / demo services
     */
    const EXCLUDED_SERVICES = ['api_rate_limit_rotate', 'analytics_demo'];

    /**
     * ID
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * The number of workers dedicated to the service
     *
     * @Column(type="integer")
     * @var integer
     */
    public $numWorkers;

    /**
     * The number of processed tasks on last run
     *
     * @Column(type="integer")
     * @var integer
     */
    public $numTasks;

    /**
     * Timestamp of the most recent start
     *
     * @Column(type="datetime",nullable=true)
     * @var \DateTime
     */
    public $lastStart;

    /**
     * Timestamp of the most recent finish
     *
     * @Column(type="datetime",nullable=true)
     * @var \DateTime
     */
    public $lastFinish;

    /**
     * Current state of the service
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $state;

    /**
     * Gets an array of the state definitions
     *
     * @return array Returns an array of the state display names
     */
    public static function listStateNames()
    {
        return static::STATE_NAMES;
    }
}
