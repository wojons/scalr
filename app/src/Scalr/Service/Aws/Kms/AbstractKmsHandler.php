<?php
namespace Scalr\Service\Aws\Kms;

use Scalr\Service\Aws;

/**
 * AbstractKmsHandler
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.9  (19.06.2015)
 *
 * @property  \Scalr\Service\Aws\Kms $kms An Amazon Kms instance
 * @method    void __constructor() __constructor(\Scalr\Service\Aws\Kms $kms)
 */
abstract class AbstractKmsHandler extends Aws\AbstractHandler
{

    /**
     * {@inheritdoc}
     * @see Aws\AbstractHandler::getServiceNames()
     */
    public function getServiceNames()
    {
        return array(Aws::SERVICE_INTERFACE_KMS);
    }

    /**
     * Sets KMS service interface instance
     *
     * @param   \Scalr\Service\Aws\Kms $kms Kms service instance
     * @return  AbstractKmsHandler
     */
    public function setKms(\Scalr\Service\Aws\Kms $kms = null)
    {
        $this->_services[Aws::SERVICE_INTERFACE_KMS] = $kms;
        return $this;
    }

    /**
     * Gets KMS service interface instance
     *
     * @return  \Scalr\Service\Aws\Kms Returns Kms service interface instance
     */
    public function getKms()
    {
        return isset($this->_services[Aws::SERVICE_INTERFACE_KMS]) ? $this->_services[Aws::SERVICE_INTERFACE_KMS] : null;
    }
}