<?php

namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;

/**
 * Orchestration Log
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 *
 * @Entity
 * @Table(name="orchestration_log")
 */
class OrchestrationLog extends AbstractEntity
{
    /**
     * Manual script execution
     */
    const TYPE_MANUAL = 'manually';

    /**
     * Scheduler script execution
     */
    const TYPE_SCHEDULER = 'scheduler';

    /**
     * Event script execution
     */
    const TYPE_EVENT = 'event';

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
     * Farm id
     *
     * @Column(name="farmid",type="integer",nullable=true)
     *
     * @var int
     */
    public $farmId;

    /**
     * Log type
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $type;

    /**
     * Server id
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $serverId;

    /**
     * Date added
     *
     * @Column(type="datetime",nullable=true,name="dtadded")
     * @var DateTime
     */
    public $added;

    /**
     * Message
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $message;

    /**
     * Name of the script
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $scriptName;

    /**
     * Execution time
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $execTime;

    /**
     * Execution exit code
     *
     * @Column(name="exec_exitcode",type="integer",nullable=true)
     * @var int
     */
    public $execExitCode;

    /**
     * Run as
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $runAs;

    /**
     * Event id
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $eventId;

    /**
     * Event server id
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $eventServerId;

    /**
     * Execution id
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $executionId;

    /**
     * Scheduler task id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $taskId;
}