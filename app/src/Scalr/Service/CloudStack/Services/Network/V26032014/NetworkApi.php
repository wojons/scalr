<?php
namespace Scalr\Service\CloudStack\Services\Network\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\Network\DataType\CreateNetwork;
use Scalr\Service\CloudStack\Services\Network\DataType\ListNetworkData;
use Scalr\Service\CloudStack\Services\Network\DataType\ListNetworkOfferingsData;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkOfferingsResponseData;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkOfferingsResponseList;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseCapabilityData;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseCapabilityList;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseData;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseList;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseProviderData;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseProviderList;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseServiceData;
use Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseServiceList;
use Scalr\Service\CloudStack\Services\NetworkService;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class NetworkApi extends AbstractApi
{
    use TagsTrait, UpdateTrait;
    /**
     * @var NetworkService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   NetworkService $network
     */
    public function __construct(NetworkService $network)
    {
        $this->service = $network;
    }

    /**
     * Gets HTTP Client
     *
     * @return  ClientInterface Returns HTTP Client
     */
    public function getClient()
    {
        return $this->service->getCloudStack()->getClient();
    }

    /**
     * Creates a network
     *
     * @param CreateNetwork $requestData Request data object
     * @return NetworkResponseData
     */
    public function createNetwork(CreateNetwork $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createNetwork', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadNetworkResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes a network
     *
     * @param string $id the ID of the network
     * @param bool $forced the ID of the network
     * @return ResponseDeleteData
     */
    public function deleteNetwork($id, $forced = false)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteNetwork',
                array(
                    'id' => $this->escape($id),
                    'forced' => $this->escape($forced)
                )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadUpdateData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Lists all available networks.
     *
     * @param ListNetworkData $requestData Request data object
     * @param PaginationType  $pagination  Pagination data
     * @return NetworkResponseList|null
     */
    public function listNetworks(ListNetworkData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listNetworks', $args);
        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadNetworkResponseList($resultObject->network);
            }
        }

        return $result;
    }

    /**
     * Reapplies all ip addresses for the particular network
     *
     * @param string $id The network this ip address should be associated to.
     * @param string $cleanup If cleanup old network elements.
     * @return NetworkResponseData
     */
    public function restartNetwork($id, $cleanup = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'restartNetwork',
                array(
                    'id' => $this->escape($id),
                    'cleanup' => $this->escape($cleanup)
                )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadNetworkResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Updates a network
     *
     * @param string $id the ID of the network
     * @param string $displayText the new display text for the network
     * @param string $name the new name for the network
     * @return NetworkResponseData
     */
    public function updateNetwork($id, $displayText = null, $name = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'updateNetwork',
                array(
                    'id' => $this->escape($id),
                    'displaytext' => $this->escape($displayText),
                    'name' => $this->escape($name)
                )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadNetworkResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Lists all available network offerings.
     *
     * @param ListNetworkOfferingsData $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return NetworkOfferingsResponseList|null
     */
    public function listNetworkOfferings(ListNetworkOfferingsData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listNetworkOfferings', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadNetworkOfferingsList($resultObject->networkoffering);
            }
        }

        return $result;
    }

    /**
     * Loads NetworkResponseList from json object
     *
     * @param   object $networkList
     * @return  NetworkResponseList Returns NetworkResponseList
     */
    protected function _loadNetworkResponseList($networkList)
    {
        $result = new NetworkResponseList();

        if (!empty($networkList)) {
            foreach ($networkList as $network) {
                $item = $this->_loadNetworkResponseData($network);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads NetworkResponseData from json object
     *
     * @param   object $resultObject
     * @return  NetworkResponseData Returns NetworkResponseData
     */
    protected function _loadNetworkResponseData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new NetworkResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    }
                    else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
            if (property_exists($resultObject, 'service')) {
                $item->setService($this->_loadNetworkServiceList($resultObject->service));
            }
            if (property_exists($resultObject, 'tags')) {
                $item->setTags($this->_loadTagsList($resultObject->tags));
            }
        }

        return $item;
    }

    /**
     * Loads NetworkResponseServiceList from json object
     *
     * @param   object $serviceList
     * @return  NetworkResponseServiceList Returns NetworkResponseServiceList
     */
    protected function _loadNetworkServiceList($serviceList)
    {
        $result = new NetworkResponseServiceList();

        if (!empty($serviceList)) {
            foreach ($serviceList as $service) {
                $item = $this->_loadNetworkServiceData($service);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads NetworkResponseServiceData from json object
     *
     * @param   object $service
     * @return  NetworkResponseServiceData Returns NetworkResponseServiceData
     */
    protected function _loadNetworkServiceData($service)
    {
        $item = new NetworkResponseServiceData();
        $item->name = (property_exists($service, 'name') ? (string) $service->name : null);
        if (property_exists($service, 'capability')) {
            $item->setCapability($this->_loadNetworkCapabilitiesList($service->capability));
        }
        if (property_exists($service, 'provider')) {
            $item->setProvider($this->_loadNetworkProviderList($service->provider));
        }

        return $item;
    }

    /**
     * Loads NetworkResponseCapabilityList from json object
     *
     * @param   object $capabilityList
     * @return  NetworkResponseCapabilityList Returns NetworkResponseCapabilityList
     */
    protected function _loadNetworkCapabilitiesList($capabilityList)
    {
        $result = new NetworkResponseCapabilityList();

        if (!empty($capabilityList)) {
            foreach ($capabilityList as $capability) {
                $item = $this->_loadNetworkCapabilityData($capability);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads NetworkResponseCapabilityData from json object
     *
     * @param   object $capability
     * @return  NetworkResponseCapabilityData Returns NetworkResponseCapabilityData
     */
    protected function _loadNetworkCapabilityData($capability)
    {
        $item = new NetworkResponseCapabilityData();
        $item->canchooseservicecapability = (property_exists($capability, 'canchooseservicecapability')
                ? (string) $capability->canchooseservicecapability : null);
        $item->name = (property_exists($capability, 'name') ? (string) $capability->name : null);
        $item->value = (property_exists($capability, 'value') ? (string) $capability->value : null);

        return $item;
    }

    /**
     * Loads NetworkResponseProviderList from json object
     *
     * @param   object $providerList
     * @return  NetworkResponseProviderList Returns NetworkResponseProviderList
     */
    protected function _loadNetworkProviderList($providerList)
    {
        $result = new NetworkResponseProviderList();

        if (!empty($providerList)) {
            foreach ($providerList as $provider) {
                $item = $this->_loadNetworkProviderData($provider);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads NetworkResponseProviderData from json object
     *
     * @param   object $provider
     * @return  NetworkResponseProviderData Returns NetworkResponseProviderData
     */
    protected function _loadNetworkProviderData($provider)
    {
        $item = new NetworkResponseProviderData();
        $item->id = (property_exists($provider, 'id') ? (string) $provider->id : null);
        $item->canenableindividualservice = (property_exists($provider, 'canenableindividualservice')
                ? (string) $provider->canenableindividualservice : null);
        $item->destinationphysicalnetworkid = (property_exists($provider, 'destinationphysicalnetworkid')
                ? (string) $provider->destinationphysicalnetworkid : null);
        $item->name = (property_exists($provider, 'name') ? (string) $provider->name : null);
        $item->physicalnetworkid = (property_exists($provider, 'physicalnetworkid')
                ? (string) $provider->physicalnetworkid : null);
        $item->servicelist = (property_exists($provider, 'servicelist') ? (string) $provider->servicelist : null);
        $item->state = (property_exists($provider, 'state') ? (string) $provider->state : null);

        return $item;
    }

    /**
     * Loads NetworkOfferingsResponseList from json object
     *
     * @param   object $resultObject
     * @return  NetworkOfferingsResponseList Returns NetworkOfferingsResponseList
     */
    protected function _loadNetworkOfferingsList($resultObject)
    {
        $result = new NetworkOfferingsResponseList();

        if (!empty($resultObject)) {
            foreach ($resultObject as $network) {
                $item = $this->_loadNetworkOfferingsData($network);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads NetworkOfferingsResponseData from json object
     *
     * @param   object $resultObject
     * @return  NetworkOfferingsResponseData Returns NetworkOfferingsResponseData
     */
    protected function _loadNetworkOfferingsData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new NetworkOfferingsResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property ' . $property, E_USER_WARNING);
                    }
                    else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
            if (property_exists($resultObject, 'service')) {
                $item->setService($this->_loadNetworkServiceList($resultObject->service));
            }
        }

        return $item;
    }

}