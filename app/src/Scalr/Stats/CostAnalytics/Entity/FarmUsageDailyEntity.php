<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Model\AbstractEntity;
use \DateTime;

/**
 * FarmUsageDailyEntity
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 * @Entity
 * @Table(name="farm_usage_d",service="cadb")
 */
class FarmUsageDailyEntity extends AbstractEntity
{

    /**
     * The identifier of the account
     *
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * The date
     *
     * @Column(type="UTCDate")
     * @var DateTime
     */
    public $date;

    /**
     * The name of the cloud platform
     *
     * @var string
     */
    public $platform;

    /**
     * The cloud location where node is being run
     *
     * @var string
     */
    public $cloudLocation;

    /**
     * The type of the instance
     *
     * @var string
     */
    public $instanceType;

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
     * The total cost of the usage of the node
     *
     * @Column(type="decimal", precision=12, scale=6)
     * @var float
     */
    public $cost;

    /**
     * Min instances count
     *
     * @Column(type="integer")
     * @var int
     */
    public $minInstances;

    /**
     * Max instances count
     *
     * @Column(type="integer")
     * @var int
     */
    public $maxInstances;

    /**
     * Total instance hours
     *
     * @Column(type="integer")
     * @var int
     */
    public $instanceHours;

    /**
     * Hours when farm is running
     *
     * @Column(type="integer")
     * @var int
     */
    public $workingHours;

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
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::load()
     */
    public function load($obj, $tableAlias = null)
    {
        parent::load($obj);

        if (empty($this->ccId) || $this->ccId === '00000000-0000-0000-0000-000000000000') {
            $this->ccId = null;
        }

        if (empty($this->projectId) || $this->projectId === '00000000-0000-0000-0000-000000000000') {
            $this->projectId = null;
        }
    }

}