<?php

namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\ElbException;
use Scalr\Service\Aws\Elb\AbstractElbDataType;

/**
 * AttributesData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 *
 * @property \Scalr\Service\Aws\Elb\DataType\AccessLogData $accessLog
 *
 * @property \Scalr\Service\Aws\Elb\DataType\AdditionalAttributesList $additionalAttributes
 *
 * @property \Scalr\Service\Aws\Elb\DataType\ConnectionDrainingData $connectionDraining
 *
 * @property \Scalr\Service\Aws\Elb\DataType\ConnectionSettingsData $connectionSettings
 *
 * @property \Scalr\Service\Aws\Elb\DataType\CrossZoneLoadBalancingData $crossZoneLoadBalancing
 *
 */
class AttributesData extends AbstractElbDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['accessLog', 'additionalAttributes', 'connectionDraining', 'connectionSettings', 'crossZoneLoadBalancing'];

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = ['loadBalancerName'];

    /**
     * Sets Attributes Data
     *
     * @param   AccessLogData $data Attributes Data
     * @return  AttributesData
     */
    public function setAccessLog(AccessLogData $data)
    {
        $this->__call(__FUNCTION__, [$data]);
    }

    /**
     * Sets a AdditionalAttributes list
     *
     * @param   AdditionalAttributesList|array $data The List of the Additional Attributes.
     * @return  AttributesData
     */
    public function setAdditionalAttributes($data = null)
    {
        if ($data !== null && !($data instanceof AdditionalAttributesList)) {
            $data = new AdditionalAttributesList($data);
        }

        $this->__call(__FUNCTION__, [$data]);
    }

    /**
     * Sets Connection Draining Data
     *
     * @param   ConnectionDrainingData $data Connection Draining Data
     * @return  AttributesData
     */
    public function setConnectionDraining(ConnectionDrainingData $data)
    {
        $this->__call(__FUNCTION__, [$data]);
    }

    /**
     * Sets Connection Settings Data
     *
     * @param   ConnectionSettingsData $data Connection Settings Data
     * @return  AttributesData
     */
    public function setConnectionSettings(ConnectionSettingsData $data)
    {
        $this->__call(__FUNCTION__, [$data]);
    }

    /**
     * Sets CrossZone LoadBalancing Data
     *
     * @param   CrossZoneLoadBalancingData $data CrossZone LoadBalancing Data
     * @return  AttributesData
     */
    public function setCrossZoneLoadBalancing(CrossZoneLoadBalancingData $data)
    {
        $this->__call(__FUNCTION__, [$data]);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Elb.AbstractElbDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        if ($this->accessLog === null && $this->additionalAttributes === null && $this->connectionDraining === null &&
            $this->connectionSettings === null && $this->crossZoneLoadBalancing === null) {
            throw new ElbException(get_class($this) . ' has not been initialized with properties values yet.');
        }
    }

}