<?php
namespace Scalr\Service\Aws\Kms;

use Scalr\Service\Aws;

/**
 * AbstractKmsDataType
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.9  (19.06.2015)
 */
abstract class AbstractKmsDataType extends Aws\AbstractDataType
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractDataType::getServiceNames()
     */
    public function getServiceNames()
    {
        return array(Aws::SERVICE_INTERFACE_KMS);
    }

    /**
     * Throws an exception if this object was not initialized.
     *
     * @throws Aws\KmsException
     */
    protected function throwExceptionIfNotInitialized()
    {
        if (!($this->getKms() instanceof Aws\Kms)) {
            throw new Aws\KmsException(get_class($this) . ' has not been initialized with Kms yet.');
        }
    }

    /**
     * Sets Amazon Kms service interface instance
     *
     * @param   Aws\Kms $kms Kms service instance
     * @return  AbstractKmsDataType
     */
    public function setKms(Aws\Kms $kms = null)
    {
        $this->_services[Aws::SERVICE_INTERFACE_KMS] = $kms;

        if ($kms !== null) {
            $this->_setServiceRelatedDatasetUpdated(true);
        }

        return $this;
    }

    /**
     * Gets Kms service interface instance
     *
     * @return Aws\Kms Returns Kms service interface instance
     */
    public function getKms()
    {
        return isset($this->_services[Aws::SERVICE_INTERFACE_KMS]) ? $this->_services[Aws::SERVICE_INTERFACE_KMS] : null;
    }
}