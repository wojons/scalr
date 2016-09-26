<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * WebhookConfigEndpoint entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (11.03.2014)
 *
 * @Entity
 * @Table(name="webhook_config_endpoints")
 */
class WebhookConfigEndpoint extends AbstractEntity
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
     * @Column(type="uuid")
     * @var string
     */
    public $endpointId;

    /**
     * @var WebhookEndpoint
     */
    private $_endpoint;

    /**
     * Gets endpoint object
     *
     * @return  \Scalr\Model\Entity\WebhookEndpoint
     */
    public function getEndpoint()
    {
        if ($this->_endpoint === null) {
            $this->fetchEndpoint();
        }
        return $this->_endpoint;
    }

    /**
     * Fetches endpoint from database
     *
     * @return WebhookEndpoint|null Returns endpoint object or null
     */
    public function fetchEndpoint()
    {
        $this->_endpoint = WebhookEndpoint::findPk($this->endpointId);
        return $this->_endpoint;
    }

    /**
     * Sets endpoint object
     *
     * @param   WebhookEndpoint $endpoint The endpoint object
     * @return \Scalr\Model\Entity\WebhookConfigEndpoint
     */
    public function setEndpoint(WebhookEndpoint $endpoint)
    {
        $this->endpointId = $endpoint->endpointId;
        $this->_endpoint = $endpoint;
        return $this;
    }
}