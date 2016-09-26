<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * WebhookConfigEvent entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (11.03.2014)
 *
 * @Entity
 * @Table(name="webhook_config_events")
 */
class WebhookConfigEvent extends AbstractEntity
{

    /**
     * The identifier of the webhook config
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $webhookId;

    /**
     * Event type
     *
     * @Id
     * @var string
     */
    public $eventType;
}