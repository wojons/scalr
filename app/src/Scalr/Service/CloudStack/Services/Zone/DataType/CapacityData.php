<?php
namespace Scalr\Service\CloudStack\Services\Zone\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CapacityData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class CapacityData extends AbstractDataType
{

    /**
     * The total capacity available
     *
     * @var string
     */
    public $capacitytotal;

    /**
     * The capacity currently in use
     *
     * @var string
     */
    public $capacityused;

    /**
     * The Cluster ID
     *
     * @var string
     */
    public $clusterid;

    /**
     * The Cluster name
     *
     * @var string
     */
    public $clustername;

    /**
     * The percentage of capacity currently in use
     *
     * @var string
     */
    public $percentused;

    /**
     * The Pod ID
     *
     * @var string
     */
    public $podid;

    /**
     * The Pod name
     *
     * @var string
     */
    public $podname;

    /**
     * The capacity type
     *
     * @var string
     */
    public $type;

    /**
     * The Zone ID
     *
     * @var string
     */
    public $zoneid;

    /**
     * The Zone name
     *
     * @var string
     */
    public $zonename;

}