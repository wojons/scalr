<?php
namespace Scalr\Service\Aws\Kms;

use Scalr\Service\Aws;

/**
 * KmsListDataType
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.9  (19.06.2015)
 */
class KmsListDataType extends Aws\DataType\ListDataType
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractDataType::getServiceNames()
     */
    public function getServiceNames()
    {
        return [Aws::SERVICE_INTERFACE_KMS];
    }

    /**
     * Sets Amazon Kms service interface instance
     *
     * @param   Aws\Kms $kms Kms service instance
     * @return  KmsListDataType
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