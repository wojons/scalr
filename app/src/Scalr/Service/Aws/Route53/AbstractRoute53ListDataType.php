<?php
namespace Scalr\Service\Aws\Route53;

use Scalr\Service\Aws;
use Scalr\Service\Aws\DataType\ListDataType;

/**
 * AbstractRoute53ListDataType
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @method    \Scalr\Service\Aws\Route53                              getRoute53()
 *            getRoute53() Gets an Amazon Route53 instance.
 * @method    \Scalr\Service\Aws\Route53\AbstractRoute53ListDataType  setRoute53()
 *            setRoute53(\Scalr\Service\Aws\Route53 $route53) Sets an Amazon Route53 instance.
 */
abstract class AbstractRoute53ListDataType extends ListDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::getServiceNames()
     */
    public function getServiceNames()
    {
        return array(Aws::SERVICE_INTERFACE_ROUTE53);
    }
}