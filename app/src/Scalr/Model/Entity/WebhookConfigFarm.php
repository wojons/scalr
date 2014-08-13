<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * WebhookConfigFarm entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (11.03.2014)
 *
 * @Entity
 * @Table(name="webhook_config_farms")
 */
class WebhookConfigFarm extends AbstractEntity
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
     * The identifier of the farm (farms.id reference)
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $farmId;
}