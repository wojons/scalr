<?php
namespace Scalr\Service\Aws\Route53;

use Scalr\Service\Aws;
use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\AbstractDataType;

/**
 * AbstractRoute53DataType
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 * @method   \Scalr\Service\Aws\Route53                            getRoute53()
 *           getRoute53() Gets an Amazon Route53 instance.
 * @method   \Scalr\Service\Aws\Route53\AbstractRoute53DataType    setRoute53()
 *           setRoute53(\Scalr\Service\Aws\Route53 $route53) Sets an Amazon Route53 instance.
 */
abstract class AbstractRoute53DataType extends AbstractDataType
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractDataType::getServiceNames()
     */
    public function getServiceNames()
    {
        return array(Aws::SERVICE_INTERFACE_ROUTE53);
    }

    /**
     * Throws an exception if this object was not initialized.
     *
     * @throws Route53Exception
     */
    protected function throwExceptionIfNotInitialized()
    {
        if (!($this->getRoute53() instanceof \Scalr\Service\Aws\Route53)) {
            throw new Route53Exception(get_class($this) . ' has not been initialized with Route53 yet.');
        }
    }
}