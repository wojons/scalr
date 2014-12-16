<?php
namespace Scalr\Service\CloudStack\Services\VmGroup\V26032014;

use DateTime;
use DateTimeZone;
use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\UpdateTrait;
use Scalr\Service\CloudStack\Services\VmGroup\DataType\CreateInstanceGroupData;
use Scalr\Service\CloudStack\Services\VmGroup\DataType\InstanceGroupData;
use Scalr\Service\CloudStack\Services\VmGroup\DataType\InstanceGroupList;
use Scalr\Service\CloudStack\Services\VmGroup\DataType\ListInstanceGroupsData;
use Scalr\Service\CloudStack\Services\VmGroupService;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class VmGroupApi extends AbstractApi
{
    use UpdateTrait;

    /**
     * @var VmGroupService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   VmGroupService $vmGroup
     */
    public function __construct(VmGroupService $vmGroup)
    {
        $this->service = $vmGroup;
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
     * Creates a vm group
     *
     * @param CreateInstanceGroupData $requestData Request data object
     * @return InstanceGroupData
     */
    public function createInstanceGroup(CreateInstanceGroupData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createInstanceGroup', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadInstanceGroupData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes a vm group
     *
     * @param string $id the ID of the instance group
     * @return ResponseDeleteData
     */
    public function deleteInstanceGroup($id)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteInstanceGroup',
                array(
                    'id' => $this->escape($id),
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
     * Updates a vm group
     *
     * @param string $id Instance group ID
     * @param string $name new instance group name
     * @return InstanceGroupData
     */
    public function updateInstanceGroup($id, $name = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'updateInstanceGroup',
                array(
                    'id' => $this->escape($id),
                    'name' => $this->escape($name)
                )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadInstanceGroupData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Lists vm groups
     *
     * @param ListInstanceGroupsData $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return InstanceGroupList|null
     */
    public function listInstanceGroups(ListInstanceGroupsData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listInstanceGroups', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadInstanceGroupList($resultObject->instancegroup);
            }
        }

        return $result;
    }

    /**
     * Loads InstanceGroupList from json object
     *
     * @param   object $groupList
     * @return  InstanceGroupList Returns InstanceGroupList
     */
    protected function _loadInstanceGroupList($groupList)
    {
        $result = new InstanceGroupList();

        if (!empty($groupList)) {
            foreach ($groupList as $group) {
                $item = $this->_loadInstanceGroupData($group);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads InstanceGroupData from json object
     *
     * @param   object $resultObject
     * @return  InstanceGroupData Returns InstanceGroupData
     */
    protected function _loadInstanceGroupData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new InstanceGroupData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('created' == $property) {
                        $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                    }
                    else if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    }
                    else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

}