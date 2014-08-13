<?php
namespace Scalr\Service\CloudStack\Services\Balancer\DataType;

use Scalr\Service\CloudStack\DataType\AbstractListDataType;

/**
 * BalancerResponseList
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class BalancerResponseList extends AbstractListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\DataType.AbstractListDataType::getClass()
     */
    public function getClass()
    {
        return __NAMESPACE__ . '\\BalancerResponseData';
    }

}