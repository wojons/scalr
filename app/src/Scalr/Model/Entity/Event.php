<?php

namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;

/**
 * Events
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 *
 * @Entity
 * @Table(name="events")
 */
class Event extends AbstractEntity
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
     * Is handled
     *
     * @Column(name="ishandled",type="integer",nullable=true)
     * @var int
     */
    public $isHandled;

    /**
     * Message
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $shortMessage;

    /**
     * Event object
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $eventObject;

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
     * Message expected
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $msgExpected;

    /**
     * Message created
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $msgCreated;

    /**
     * Message sent
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $msgSent;

    /**
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $whTotal;

    /**
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $whCompleted;

    /**
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $whFailed;

    /**
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $scriptsTotal;

    /**
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $scriptsCompleted;

    /**
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $scriptsFailed;

    /**
     * @Column(name="scripts_timedout",type="integer",nullable=true)
     * @var int
     */
    public $scriptsTimedOut;

    /**
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $isSuspend;
}