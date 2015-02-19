<?php
namespace Scalr\Stats\CostAnalytics\Entity;

/**
 * AccountCostCenterEntity
 *
 * @Entity
 * @Table(name="account_ccs")
 */
class AccountCostCenterEntity extends \Scalr\Model\AbstractEntity
{
    /**
     * The identifier of the client's account
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * Cost centre identifier (UUID)
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $ccId;

    /**
     * Constructor
     *
     * @param    int     $accountId optional The identifier of the client's Account
     * @param    string  $ccId      optional The identifier of the Cost Center
     */
    public function __construct($accountId = null, $ccId = null)
    {
        $this->accountId = $accountId;
        $this->ccId = $ccId;
    }
}