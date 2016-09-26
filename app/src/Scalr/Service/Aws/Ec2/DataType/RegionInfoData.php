<?php

namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2\AbstractEc2DataType;

/**
 * RegionInfoData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.0 (28.01.2014)
 */
class RegionInfoData extends AbstractEc2DataType
{

    /**
     * The name of the region
     *
     * @var string
     */
    public $regionName;

    /**
     * The endpoint for the region
     *
     * @var string
     */
    public $regionEndpoint;

    /**
     * Constructor
     *
     * @param   string     $regionName       optional The name of the region.
     * @param   string     $regionEndpoint   optional The endpoint for the region.
     */
    public function __construct($regionName = null, $regionEndpoint = null)
    {
        parent::__construct();
        $this->regionName = $regionName;
        $this->regionEndpoint = $regionEndpoint;
    }
}