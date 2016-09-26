<?php

namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;

/**
 * Message Entity
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 *
 * @Entity
 * @Table(name="messages")
 */
class Message extends AbstractEntity
{
    /**
     * Message identifier (UUID)
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(name="messageid",type="uuid")
     * @var string
     */
    public $messageId;

    /**
     * Processing time
     *
     * @Column(type="decimal",nullable=true)
     * @var float
     */
    public $processingTime;

    /**
     * Message status
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $status;

    /**
     * Handle Attempts number
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $handleAttempts;

    /**
     * Date added
     *
     * @Column(type="datetime",nullable=true,name="dtlasthandleattempt")
     * @var DateTime
     */
    public $lastHandleAttemptDate;

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
     * Server id
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $serverId;

    /**
     * Event Server id
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $eventServerId;

    /**
     * Type
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $type;

    /**
     * Message name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $messageName;

    /**
     * Message version
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $messageVersion;

    /**
     * Message format
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $messageFormat;

    /**
     * Ip Address
     *
     * @Column(type="string",nullable=true, name="ipaddress")
     * @var string
     */
    public $ipAddress;

    /**
     * Event id
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $eventId;

}