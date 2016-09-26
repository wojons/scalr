<?php
namespace Scalr\Stats\CostAnalytics\Entity;

/**
 * PriceEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (05.02.2014)
 * @Entity
 * @Table(name="prices",service="cadb")
 */
class PriceEntity extends \Scalr\Model\AbstractEntity
{
    const OS_LINUX = 0;

    const OS_WINDOWS = 1;

    /**
     * Price identifier (UUID)
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $priceId;

    /**
     * The type of the instance
     *
     * @Id
     * @var string
     */
    public $instanceType;

    /**
     * operating system platform
     *
     * Possible values: (PriceEntity::OS_LINUX | PriceEntity::OS_WINDOWS)
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $os;

    /**
     * The display name of the instance type
     *
     * @var string
     */
    public $name;

    /**
     * The USD cost of the instance per hour
     *
     * @Column(type="decimal", precision=9, scale=6)
     * @var float
     */
    public $cost;

    /**
     * Price History item associated with the price
     *
     * @var PriceHistoryEntity
     */
    private $_priceHistory;

    /**
     * Sets price history item associated with the price
     *
     * @param   PriceHistoryEntity $priceHistory
     * @return  \Scalr\Stats\CostAnalytics\Entity\PriceEntity
     */
    public function setPriceHistory(PriceHistoryEntity $priceHistory = null)
    {
        $this->_priceHistory = $priceHistory;
        return $this;
    }

    /**
     * Gets price history item associated with the price
     *
     * @return \Scalr\Stats\CostAnalytics\Entity\PriceHistoryEntity Returns price History item
     */
    public function getPriceHistory()
    {
        //Lazy getter
        if ($this->_priceHistory === null) {
            $this->loadPriceHistory();
        }
        return $this->_priceHistory;
    }

    /**
     * Loads price history item associated with the price
     *
     * @return \Scalr\Stats\CostAnalytics\Entity\PriceEntity
     */
    public function loadPriceHistory()
    {
        $this->_priceHistory = \Scalr::getContainer()->analytics->prices->get($this->priceId);
        return $this;
    }
}