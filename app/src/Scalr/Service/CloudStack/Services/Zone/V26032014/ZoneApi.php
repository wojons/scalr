<?php
namespace Scalr\Service\CloudStack\Services\Zone\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\Services\ZoneService;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\Services\Zone\DataType\ZoneList;
use Scalr\Service\CloudStack\Services\Zone\DataType\ListZonesData;
use Scalr\Service\CloudStack\Services\Zone\DataType\ZoneData;
use Scalr\Service\CloudStack\Services\Zone\DataType\CapacityList;
use Scalr\Service\CloudStack\Services\Zone\DataType\CapacityData;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ZoneApi extends AbstractApi
{
    use TagsTrait;

    /**
     * @var ZoneService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   ZoneService $zone
     */
    public function __construct(ZoneService $zone)
    {
        $this->service = $zone;
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
     * Lists zones
     *
     * @param ListZonesData $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return ZoneList|null
     */
    public function listZones(ListZonesData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listZones', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadZonesList($resultObject->zone);
            }
        }

        return $result;
    }

    /**
     * Loads ZoneList from json object
     *
     * @param   object $zonesList
     * @return  ZoneList Returns ZoneList
     */
    protected function _loadZonesList($zonesList)
    {
        $result = new ZoneList();

        if (!empty($zonesList)) {
            foreach ($zonesList as $zone) {
                $item = $this->_loadZonesData($zone);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads ZoneData from json object
     *
     * @param   object $resultObject
     * @return  ZoneData Returns ZoneData
     */
    protected function _loadZonesData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new ZoneData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
            if (property_exists($resultObject, 'tags')) {
                $item->setTags($this->_loadTagsList($resultObject->tags));
            }
            if (property_exists($resultObject, 'capacity')) {
                $item->setCapacity($this->_loadCapacityList($resultObject->capacity));
            }
            if (property_exists($resultObject, 'resourcedetails')) {
                $item->setResourcedetails($resultObject->resourcedetails);
            }
        }

        return $item;
    }

    /**
     * Loads CapacityList from json object
     *
     * @param   object $capacityList
     * @return  CapacityList Returns CapacityList
     */
    protected function _loadCapacityList($capacityList)
    {
        $result = new CapacityList();

        if (!empty($capacityList)) {
            foreach ($capacityList as $capacity) {
                $item = $this->_loadCapacityData($capacity);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads CapacityData from json object
     *
     * @param   object $resultObject
     * @return  CapacityData Returns CapacityData
     */
    protected function _loadCapacityData($resultObject)
    {
        $item = new CapacityData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                $item->{$property} = (string) $resultObject->{$property};
            }
        }

        return $item;
    }

}