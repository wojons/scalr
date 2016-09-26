<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Model\Collections\EntityIterator;

/**
 * UsageTypeEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.5 (19.05.2015)
 * @Entity
 * @Table(name="usage_types",service="cadb")
 */
class UsageTypeEntity extends \Scalr\Model\AbstractEntity
{

    /**
     * Compute cost distribution type
     */
    const COST_DISTR_TYPE_COMPUTE = 1;

    /**
     * Storage cost distribution type
     */
    const COST_DISTR_TYPE_STORAGE = 2;

    /**
     * Bandwidth cost distribution type
     */
    const COST_DISTR_TYPE_BANDWIDTH = 3;

    /**
     * Others cost distribution type
     */
    const COST_DISTR_TYPE_OTHERS = 4;

    /**
     * The Usage Type name for cloud computing box usage
     */
    const NAME_COMPUTE_BOX_USAGE = 'BoxUsage';

    /**
     * The Usage Type name for Other - Software
     */
    const NAME_OTHER_SOFTWARE = 'Software';

    /**
     * The Usage Type name for cloud storage ebs
     */
    const NAME_STORAGE_EBS = 'EBS';

    /**
     * The Usage Type name for cloud storage ebs io
     */
    const NAME_STORAGE_EBS_IO = 'EBS IO';

    /**
     * The Usage Type name for cloud storage ebs iops
     */
    const NAME_STORAGE_EBS_IOPS = 'EBS IOPS';

    /**
     * The Usage Type name for cloud bandwidth regional
     */
    const NAME_BANDWIDTH_REGIONAL = 'Regional';

    /**
     * The Usage Type name for cloud bandwidth in
     */
    const NAME_BANDWIDTH_IN = 'In';

    /**
     * The Usage Type name for cloud bandwidth out
     */
    const NAME_BANDWIDTH_OUT = 'Out';

    /**
     * Unique identifier of the Usage Type
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="bin4")
     * @var string
     */
    public $id;

    /**
     * Cost distribution type
     *
     * @Column(type="integer")
     * @var integer
     */
    public $costDistrType;

    /**
     * The name of the Usage Type
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Display name of the Usage Type
     *
     * It is used to override the name for displaying purposes
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $displayName;

    /**
     * The Usage Items which correspond to the Usage Type
     *
     * @var EntityIterator
     */
    private $usageItems;

    /**
     * Gets display name of the Usage Type
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName ?: $this->name;
    }

    /**
     * Gets Usage Items which correspond to the Usage Type
     *
     * @param    string         $ignoreCache  optional Whether it should ignore cache
     * @return   EntityIterator Returns entity iterator of the Usage Items which corresponds to the Usate Type
     */
    public function getUsageItems($ignoreCache = false)
    {
        if ($ignoreCache || $this->usageItems === null) {
            $this->usageItems = UsageItemEntity::findByUsageType($this->id);
        }

        return $this->usageItems;
    }
}