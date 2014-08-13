<?php
namespace Scalr\Service\CloudStack\Services\Network\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * NetworkResponseServiceData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  \Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseCapabilityList    $capability
 * The list of capabilities
 *
 * @property  \Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseProviderList    $provider
 * The service provider name
 *
 */
class NetworkResponseServiceData extends AbstractDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('capability', 'provider');

    /**
     * The service name
     *
     * @var string
     */
    public $name;

    /**
     * Sets capability list
     *
     * @param   NetworkResponseCapabilityList $capability
     * @return  NetworkResponseServiceData
     */
    public function setCapability(NetworkResponseCapabilityList $capability = null)
    {
        return $this->__call(__FUNCTION__, array($capability));
    }

    /**
     * Sets provider list
     *
     * @param   NetworkResponseProviderData $provider
     * @return  NetworkResponseServiceList
     */
    public function setProvider(NetworkResponseProviderList $provider = null)
    {
        return $this->__call(__FUNCTION__, array($provider));
    }

}