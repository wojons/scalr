<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * WebhookHistory entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (11.03.2014)
 *
 * @Entity
 * @Table(name="webhook_history")
 */
class WebhookHistory extends AbstractEntity
{

    const STATUS_PENDING = 0;
    const STATUS_COMPLETE = 1;
    const STATUS_FAILED = 2;

    /**
     * The identifier of the webhook config
     *
     * @Id
     * @GeneratedValue("UUID")
     * @Column(type="uuid")
     * @var string
     */
    public $historyId;

    /**
     * The UUID of the webhook config
     *
     * @Column(type="uuid")
     * @var string
     */
    public $webhookId;

    /**
     * The date when the record is created
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $created;

    /**
     * The identifier of the endpoint
     *
     * @Column(type="uuid")
     * @var string
     */
    public $endpointId;

    /**
     * The UUID of the event
     *
     * @Column(type="string")
     * @var string
     */
    public $eventId;

    /**
     * The identifier of the farm associated with webhook
     *
     * @Column(type="integer")
     * @var   int
     */
    public $farmId;

    /**
     * The type of the event
     *
     * @var string
     */
    public $eventType;

    /**
     * Status
     *
     * @Column(type="integer")
     * @var int
     */
    public $status;

    /**
     * Response code
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $responseCode;

    /**
     * Payload
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $payload;

    /**
     * Error message
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $errorMsg;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->created = new \DateTime('now');
        $this->status = self::STATUS_PENDING;
    }
}