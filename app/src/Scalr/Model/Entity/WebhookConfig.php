<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\ArrayCollection;

/**
 * WebhookConfig entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (11.03.2014)
 *
 * @Entity
 * @Table(name="webhook_configs")
 */
class WebhookConfig extends AbstractEntity
{

    const LEVEL_SCALR = 1;
    const LEVEL_ACCOUNT = 2;
    const LEVEL_ENVIRONMENT = 4;
    const LEVEL_FARM = 8;


    /**
     * The identifier of the webhook config
     *
     * @Id
     * @GeneratedValue("UUID")
     * @Column(type="uuid")
     * @var string
     */
    public $webhookId;

    /**
     * The level
     *
     * @Column(type="integer")
     * @var int
     */
    public $level;

    /**
     * The name of the config
     *
     * @var string
     */
    public $name;

    /**
     * The identifier of the client's account
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * The identifier of the client's environment
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

    /**
     * Posted data
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $postData;

    /**
     * Skip private global variables from payload
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $skipPrivateGv;

    /**
     * Timeout
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $timeout;

    /**
     * Attempts limit
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $attempts;

    /**
     * @var \ArrayObject
     */
    private $_endpoints;

    /**
     * @var \ArrayObject
     */
    private $_events;

    /**
     * @var \ArrayObject
     */
    private $_farms;

    /**
     * Gets the list of the endpoints associated with the config
     *
     * @return \ArrayObject Returns the list of WebhookConfigEndpoint objects
     */
    public function getEndpoints()
    {
        if ($this->_endpoints === null) {
            $this->fetchEndpoints();
        }
        return $this->_endpoints;
    }

    /**
     * Fetches list of the endpoints associated with the config (refreshes)
     *
     * @return \ArrayObject Returns the list of WebhookConfigEndpoint objects
     */
    public function fetchEndpoints()
    {
        $this->_endpoints = WebhookConfigEndpoint::findByWebhookId($this->webhookId);
        return $this->_endpoints;
    }

    /**
     * Sets the list of the endpoints
     *
     * @param   \ArrayObject   $endpoints
     * @return  \Scalr\Model\Entity\WebhookConfig
     */
    public function setEndpoints(\ArrayObject $endpoints = null)
    {
        $this->_endpoints = $endpoints === null ? new \ArrayObject(array()) : $endpoints;
        return $this;
    }

    /**
     * Gets the list of the events associated with the config
     *
     * @return \ArrayObject Returns the list of WebhookConfigEvent objects
     */
    public function getEvents()
    {
        if ($this->_events === null) {
            $this->fetchEvents();
        }
        return $this->_events;
    }

    /**
     * Fetches list of the events associated with the config (refreshes)
     *
     * @return \ArrayObject Returns the list of WebhookConfigEvents objects
     */
    public function fetchEvents()
    {
        $this->_events = WebhookConfigEvent::findByWebhookId($this->webhookId);
        return $this->_events;
    }

    /**
     * Sets the list of the events
     *
     * @param   \ArrayObject   $events
     * @return  \Scalr\Model\Entity\WebhookConfig
     */
    public function setEvents(\ArrayObject $events = null)
    {
        $this->_events = $events === null ? new \ArrayObject(array()) : $events;
        return $this;
    }

    /**
     * Gets the list of the farms associated with the config
     *
     * @return \ArrayObject Returns the list of WebhookConfigFarm objects
     */
    public function getFarms()
    {
        if ($this->_farms === null) {
            $this->fetchFarms();
        }
        return $this->_farms;
    }

    /**
     * Fetches list of the farms associated with the config (refreshes)
     *
     * @return \ArrayObject Returns the list of WebhookConfigFarm objects
     */
    public function fetchFarms()
    {
        $this->_farms = WebhookConfigFarm::findByWebhookId($this->webhookId);
        return $this->_farms;
    }

    /**
     * Sets the list of the farms
     *
     * @param   \ArrayObject   $farms
     * @return  \Scalr\Model\Entity\WebhookConfig
     */
    public function setFarms(\ArrayObject $farms = null)
    {
        $this->_farms = $farms === null ? new \ArrayObject(array()) : $farms;
        return $this;
    }

    /**
     * Finds webhook configs by even
     *
     * @param   string   $eventName     Event type
     * @param   int      $farmId    The identifier of the farm
     * @param   int      $accountId The identifier of the client's account
     * @param   int      $envId     The identifier of the environment
     * @return  ArrayCollection Gets collection of the WebhookConfig objects
     */
    public static function findByEvent($eventName, $farmId, $accountId, $envId)
    {
        $ret = new ArrayCollection();
        $cfg = new self();

        $res = $cfg->db()->Execute("
            SELECT " . $cfg->fields('c') . "
            FROM " . $cfg->table() . " c
            JOIN `webhook_config_events` ce ON ce.webhook_id = c.webhook_id
            WHERE ce.event_type = ?
            AND (
                (c.account_id = ? AND c.env_id = ? AND `level` = 4) OR
                (c.account_id = ? AND c.env_id IS NULL AND `level` = 2) OR
                (c.account_id IS NULL AND c.env_id IS NULL AND `level` = 1)
            )
            AND EXISTS (
                SELECT 1 FROM `webhook_config_farms` cf
                WHERE cf.webhook_id = c.webhook_id
                AND (cf.farm_id = 0 OR cf.farm_id = ?)
            )
        ", array(
            $eventName,
            $accountId,
            $envId,
            $accountId,
            $farmId
        ));

        while ($item = $res->FetchRow()) {
            $cfg = new self();
            $cfg->load($item);
            $ret->append($cfg);
            unset($cfg);
        }

        return $ret;
    }

}