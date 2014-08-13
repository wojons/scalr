<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Model\Collections\ArrayCollection;

/**
 * PriceHistoryEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (05.02.2014)
 * @Entity
 * @Table(name="price_history",service="cadb")
 */
class PriceHistoryEntity extends \Scalr\Model\AbstractEntity
{
    /**
     * The unique identifier (UUID)
     *
     * @Id
     * @GeneratedValue
     * @Column(type="uuid")
     * @var uuid
     */
    public $priceId;

    /**
     * Cloud platform
     *
     * @var string
     */
    public $platform;

    /**
     * The keystone url of the private cloud.
     *
     * Empty string for the public clouds.
     *
     * @var string
     */
    public $url;

    /**
     * The cloud location
     *
     * @var string
     */
    public $cloudLocation;

    /**
     * The identifier of the client's account.
     *
     * Zero for the global price level.
     *
     * @var int
     */
    public $accountId;

    /**
     * The start date this price is applied from.
     *
     * @Column(type="UTCDate")
     * @var \DateTime
     */
    public $applied;

    /**
     * May this price be overridden on account level
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $denyOverride;

    /**
     * Prices which are associated with it
     *
     * @var ArrayCollection
     */
    private $_details;

    /**
     * Gets prices for an each instance type
     *
     * @return  ArrayCollection Returns prices collection
     */
    public function getDetails()
    {
        //Lazy load prices
        if ($this->_details === null) {
            $this->loadDetails();
        }
        return $this->_details;
    }

    /**
     * Loads prices from database
     *
     * @return PriceHistoryEntity
     */
    public function loadDetails()
    {
        $this->_details = \Scalr::getContainer()->analytics->prices->getDetails($this);
        return $this;
    }

    /**
     * Sets prices for an each instance type
     *
     * @param   ArrayCollection $details
     * @return  PriceHistoryEntity
     */
    public function setDetails($details = null)
    {
        $this->_details = $details;
        return $this;
    }
}