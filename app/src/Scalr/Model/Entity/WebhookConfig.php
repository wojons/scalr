<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\DataType\ScopeInterface;

/**
 * WebhookConfig entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (11.03.2014)
 *
 * @Entity
 * @Table(name="webhook_configs")
 */
class WebhookConfig extends AbstractEntity implements ScopeInterface
{

    const LEVEL_SCALR = 1;
    const LEVEL_ACCOUNT = 2;
    const LEVEL_ENVIRONMENT = 4;
    const LEVEL_FARM = 8;


    /**
     * The identifier of the webhook config
     *
     * @Id
     * @GeneratedValue("CUSTOM")
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
     * @var ArrayCollection
     */
    private $_endpoints;

    /**
     * @var ArrayCollection
     */
    private $_events;

    /**
     * @var ArrayCollection
     */
    private $_farms;

    /**
     * Gets the list of the endpoints associated with the config
     *
     * @return ArrayCollection Returns the list of WebhookConfigEndpoint objects
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
     * @return ArrayCollection Returns the list of WebhookConfigEndpoint objects
     */
    public function fetchEndpoints()
    {
        $this->_endpoints = WebhookConfigEndpoint::result(self::RESULT_ENTITY_COLLECTION)->findByWebhookId($this->webhookId);
        return $this->_endpoints;
    }

    /**
     * Sets the list of the endpoints
     *
     * @param   ArrayCollection   $endpoints
     * @return  \Scalr\Model\Entity\WebhookConfig
     */
    public function setEndpoints($endpoints = null)
    {
        $this->_endpoints = $endpoints === null ? new ArrayCollection([]) : $endpoints;
        return $this;
    }

    /**
     * Gets the list of the events associated with the config
     *
     * @return ArrayCollection Returns the list of WebhookConfigEvent objects
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
     * @return ArrayCollection Returns the list of WebhookConfigEvents objects
     */
    public function fetchEvents()
    {
        $this->_events = WebhookConfigEvent::result(self::RESULT_ENTITY_COLLECTION)->findByWebhookId($this->webhookId);
        return $this->_events;
    }

    /**
     * Sets the list of the events
     *
     * @param   ArrayCollection   $events
     * @return  \Scalr\Model\Entity\WebhookConfig
     */
    public function setEvents($events = null)
    {
        $this->_events = $events === null ? new ArrayCollection([]) : $events;
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
     * @return ArrayCollection Returns the list of WebhookConfigFarm objects
     */
    public function fetchFarms()
    {
        $this->_farms = WebhookConfigFarm::result(self::RESULT_ENTITY_COLLECTION)->findByWebhookId($this->webhookId);
        return $this->_farms;
    }

    /**
     * Sets the list of the farms
     *
     * @param   ArrayCollection    $farms
     * @return  \Scalr\Model\Entity\WebhookConfig
     */
    public function setFarms($farms = null)
    {
        $this->_farms = $farms === null ? new ArrayCollection([]) : $farms;
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

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        switch ($this->level) {
            case self::LEVEL_FARM:
                return self::SCOPE_FARM;
            case self::LEVEL_ENVIRONMENT:
                return self::SCOPE_ENVIRONMENT;
            case self::LEVEL_ACCOUNT:
                return self::SCOPE_ACCOUNT;
            case self::LEVEL_SCALR:
                return self::SCOPE_SCALR;
            default:
                throw new \UnexpectedValueException(sprintf(
                    "Unknown level type: %d in %s::%s",
                    $this->level, get_class($this), __FUNCTION__
                ));
        }
    }

    public function setScope($scope, $accountId, $envId)
    {
        switch ($scope) {
            case self::SCOPE_ENVIRONMENT:
                $this->level = self::LEVEL_ENVIRONMENT;
                $this->accountId = $accountId;
                $this->envId = $envId;
                break;
            case self::SCOPE_ACCOUNT:
                $this->level = self::LEVEL_ACCOUNT;
                $this->accountId = $accountId;
                break;
            case self::SCOPE_SCALR:
                $this->level = self::LEVEL_SCALR;
                break;
            default:
                throw new \UnexpectedValueException(sprintf(
                    "Unknown scope: %d in %s::%s",
                    $scope, get_class($this), __FUNCTION__
                ));
        }
    }

}