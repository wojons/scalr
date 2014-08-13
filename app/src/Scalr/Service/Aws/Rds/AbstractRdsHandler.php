<?php
namespace Scalr\Service\Aws\Rds;

use Scalr\Service\Aws;
use Scalr\Service\Aws\AbstractHandler;

/**
 * AbstractRdsHandler
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     07.03.2013
 *
 * @property  \Scalr\Service\Aws\Rds   $rds      An Amazon Rds instance
 * @method    void                __constructor()  __constructor(\Scalr\Service\Aws\Rds $rds)
 */
abstract class AbstractRdsHandler extends AbstractHandler
{

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractHandler::getServiceNames()
     */
    public function getServiceNames()
    {
        return array(Aws::SERVICE_INTERFACE_RDS);
    }

    /**
     * Sets Amazon Rds service interface instance
     *
     * @param   \Scalr\Service\Aws\Rds $rds Rds service instance
     * @return  AbstractRdsHandler
     */
    public function setRds(\Scalr\Service\Aws\Rds $rds = null)
    {
        $this->_services[Aws::SERVICE_INTERFACE_RDS] = $rds;
        return $this;
    }

    /**
     * Gets Rds service interface instance
     *
     * @return  \Scalr\Service\Aws\Rds Returns Rds service interface instance
     */
    public function getRds()
    {
        return isset($this->_services[Aws::SERVICE_INTERFACE_RDS]) ? $this->_services[Aws::SERVICE_INTERFACE_RDS] : null;
    }
}