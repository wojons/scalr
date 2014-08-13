<?php
namespace Scalr\Service\CloudStack\Services\Balancer\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListBalancerRuleInstancesData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListBalancerRuleInstancesData extends AbstractDataType
{

    /**
     * The ID of the load balancer rule
     *
     * @var string
     */
    public $id;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * True if listing all virtual machines currently applied to the load balancer rule; default is true
     *
     * @var string
     */
    public $applied;

}