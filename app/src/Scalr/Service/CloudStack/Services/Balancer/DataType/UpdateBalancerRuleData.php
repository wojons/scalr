<?php
namespace Scalr\Service\CloudStack\Services\Balancer\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * UpdateBalancerRuleData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class UpdateBalancerRuleData extends AbstractDataType
{

    /**
     * Required
     * The id of the load balancer rule to update
     *
     * @var string
     */
    public $id;

    /**
     * Load balancer algorithm (source, roundrobin, leastconn)
     *
     * @var string
     */
    public $algorithm;

    /**
     * Name of the load balancer rule
     *
     * @var string
     */
    public $name;

    /**
     * The description of the load balancer rule
     *
     * @var string
     */
    public $description;

    /**
     * Constructor
     *
     * @param   string  $id   The id of the load balancer rule to update
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

}
