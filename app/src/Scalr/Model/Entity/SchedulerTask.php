<?php

namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;

/**
 * Scheduler task
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 *
 * @Entity
 * @Table(name="scheduler")
 */
class SchedulerTask extends AbstractEntity
{
    /**
     * Identifier
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     *
     * @var int
     */
    public $id;

    /**
     * Name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $name;

    /**
     * Log type
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $type;

    /**
     * Comments
     *
     * @Column(type="string")
     * @var string
     */
    public $comments;

    /**
     * Target id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $targetId;

    /**
     * Target server index
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $targetServerIndex;

    /**
     * Target type
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $targetType;

    /**
     * Script id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $scriptId;

    /**
     * Start time
     *
     * @Column(type="datetime",nullable=true)
     * @var DateTime
     */
    public $startTime;

    /**
     * Last start time
     *
     * @Column(type="datetime",nullable=true)
     * @var DateTime
     */
    public $lastStartTime;

    /**
     * Restart every
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $restartEvery;

    /**
     * Config
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $config;

    /**
     * Timezone
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $timezone;

    /**
     * Status
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $status;

    /**
     * Account id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * Env id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

}