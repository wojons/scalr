<?php

namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\Elb\AbstractElbDataType;

/**
 * ModifyLoadBalancerAttributes
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 *
 * @property \Scalr\Service\Aws\Elb\DataType\AttributesData $loadBalancerAttributes
 *
 */
class ModifyLoadBalancerAttributes extends AbstractElbDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['loadBalancerAttributes'];

    /**
     * Load balancer name
     *
     * @var string
     */
    public $loadBalancerName;

    /**
     * Constructor
     *
     * @param string         $loadBalancerName      Load balancer name
     * @param AttributesData $attributesData        The attributes of the load balancer.
     */
    public function __construct($loadBalancerName, AttributesData $attributesData)
    {
        $this->loadBalancerName = $loadBalancerName;
        $this->setLoadBalancerAttributes($attributesData);
    }

    /**
     * Sets Attributes Data
     *
     * @param   AttributesData $data Attributes Data
     * @return  ModifyLoadBalancerAttributes
     */
    public function setLoadBalancerAttributes(AttributesData $data)
    {
        $this->__call(__FUNCTION__, [$data]);
    }

}