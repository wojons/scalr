<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Model\AbstractEntity;
use DateTime;

/**
 * UsageHourlyEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (06.03.2014)
 * @Entity
 * @Table(name="usage_h",service="cadb")
 */
class UsageHourlyEntity extends AbstractEntity
{

    /**
     * The unique identifier of the record
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="uuid")
     * @var string
     */
    public $usageId;

    /**
     * The identifier of the account
     *
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * The date and time Y-m-d H:00:00
     *
     * @Column(type="UTCDatetime")
     * @var DateTime
     */
    public $dtime;

    /**
     * The name of the cloud platform
     *
     * @var string
     */
    public $platform;

    /**
     * The keystone endpoint url for the private cloud
     *
     * @var string
     */
    public $url;

    /**
     * The cloud location where node is being run
     *
     * @var string
     */
    public $cloudLocation;

    /**
     * Identifier of the Usage Item
     *
     * @Column(type="bin4")
     * @var string
     */
    public $usageItem;

    /**
     * The identifier of the operating system platform (0-linux, 1-windows)
     *
     * @Column(type="integer")
     * @var int
     */
    public $os;

    /**
     * The identifier of the cost centre associated with the environment
     *
     * @Column(type="uuid")
     * @var string
     */
    public $ccId;

    /**
     * The identifier of the project associated with the farm
     *
     * @Column(type="uuid")
     * @var string
     */
    public $projectId;

    /**
     * The identifier of the environment associated with the node
     *
     * @Column(type="integer")
     * @var int
     */
    public $envId;

    /**
     * The identifier of the farm associated with the node
     *
     * @Column(type="integer")
     * @var int
     */
    public $farmId;

    /**
     * The identifier of the farm role associeated with the node
     *
     * @Column(type="integer")
     * @var int
     */
    public $farmRoleId;

    /**
     * The identifier of the role
     *
     * @Column(type="integer")
     * @var int
     */
    public $roleId;

    /**
     * The number of the nodes of the same type
     *
     * @Column(type="integer")
     * @var int
     */
    public $num;

    /**
     * The total cost of the usage of the node
     *
     * @Column(type="decimal", precision=12, scale=6)
     * @var float
     */
    public $cost;

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::load()
     */
    public function load($obj, $tableAlias = null)
    {
        parent::load($obj);

        if (empty($this->farmId)) {
            $this->farmId = null;
        }

        if (empty($this->envId)) {
            $this->envId = null;
        }

        if (empty($this->farmRoleId)) {
            $this->farmRoleId = null;
        }

        if (empty($this->ccId) || $this->ccId === '00000000-0000-0000-0000-000000000000') {
            $this->ccId = null;
        }

        if (empty($this->projectId) || $this->projectId === '00000000-0000-0000-0000-000000000000') {
            $this->projectId = null;
        }
    }
}