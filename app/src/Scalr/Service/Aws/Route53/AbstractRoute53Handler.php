<?php
namespace Scalr\Service\Aws\Route53;

use Scalr\Service\Aws;
use Scalr\Service\Aws\AbstractHandler;

/**
 * AbstractRoute53Handler
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
abstract class AbstractRoute53Handler extends AbstractHandler
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractHandler::getServiceNames()
     */
    public function getServiceNames()
    {
        return array(Aws::SERVICE_INTERFACE_ROUTE53);
    }

    /**
     * Sets Amazon Route53 service interface instance
     *
     * @param   \Scalr\Service\Aws\Route53 $route53 Route53 service instance
     * @return  AbstractRoute53Handler
     */
    public function setRoute53(\Scalr\Service\Aws\Route53 $route53 = null)
    {
        $this->_services[Aws::SERVICE_INTERFACE_ROUTE53] = $route53;
        return $this;
    }

    /**
     * Gets Route53 service interface instance
     *
     * @return  \Scalr\Service\Aws\Route53 Returns Route53 service interface instance
     */
    public function getRoute53()
    {
        return isset($this->_services[Aws::SERVICE_INTERFACE_ROUTE53]) ? $this->_services[Aws::SERVICE_INTERFACE_ROUTE53] : null;
    }
}