<?php
namespace Scalr\Service\CloudStack\Services\Volume\V26032014;

use DateTime;
use DateTimeZone;
use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\ExtractTemplateResponseData;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\TemplateTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;
use Scalr\Service\CloudStack\Services\Volume\DataType\CreateVolumeData;
use Scalr\Service\CloudStack\Services\Volume\DataType\ExtractVolumeData;
use Scalr\Service\CloudStack\Services\Volume\DataType\ListVolumesData;
use Scalr\Service\CloudStack\Services\Volume\DataType\VolumeResponseData;
use Scalr\Service\CloudStack\Services\Volume\DataType\VolumeResponseList;
use Scalr\Service\CloudStack\Services\VolumeService;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class VolumeApi extends AbstractApi
{
    use TagsTrait, TemplateTrait, UpdateTrait;

    /**
     * @var VolumeService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   VolumeService $volume
     */
    public function __construct(VolumeService $volume)
    {
        $this->service = $volume;
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
     * Attaches a disk volume to a virtual machine.
     *
     * @param string $id the ID of the disk volume
     * @param string $virtualMachineId the ID of the virtual machine
     * @param string $deviceId the ID of the device to map the volume to within the guest OS.
     *        If no deviceId is passed in, the next available deviceId will be chosen.
     *        Possible values for a Linux OS are:* 1 - /dev/xvdb* 2 - /dev/xvdc* 4 - /dev/xvde
     *        * 5 - /dev/xvdf* 6 - /dev/xvdg* 7 - /dev/xvdh* 8 - /dev/xvdi* 9 - /dev/xvdj
     * @return VolumeResponseData
     */
    public function attachVolume($id, $virtualMachineId, $deviceId = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'attachVolume',
             array(
                 'id' => $this->escape($id),
                 'virtualmachineids' => $this->escape($virtualMachineId),
                 'deviceid' => $this->escape($deviceId)
             )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVolumeResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Detaches a disk volume from a virtual machine.
     *
     * @param string $deviceId the device ID on the virtual machine where volume is detached from
     * @param string $id the ID of the disk volume
     * @param string $virtualMachineId the ID of the virtual machine where the volume is detached from
     * @return VolumeResponseData
     */
    public function detachVolume($deviceId = null, $id = null, $virtualMachineId = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'detachVolume',
             array(
                 'id' => $this->escape($id),
                 'virtualmachineids' => $this->escape($virtualMachineId),
                 'deviceid' => $this->escape($deviceId)
             )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVolumeResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Creates a disk volume from a disk offering.
     * This disk volume must still be attached to a virtual machine to make use of it.
     *
     * @param CreateVolumeData $requestData Create volume request data object
     * @return VolumeResponseData
     */
    public function createVolume(CreateVolumeData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('createVolume', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVolumeResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes a detached disk volume.
     *
     * @param string $id The ID of the disk volume
     * @return ResponseDeleteData
     */
    public function deleteVolume($id)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteVolume',
             array(
                 'id' => $this->escape($id)
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
     * Lists all volumes.
     *
     * @param ListVolumesData $requestData   List volumes request object
     * @param PaginationType $pagination Pagination
     * @return VolumeResponseList|null
     */
    public function listVolumes(ListVolumesData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_push($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listVolumes', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadVolumeResponseList($resultObject->volume);
            }
        }

        return $result;
    }

    /**
     * Extracts volume
     *
     * @param ExtractVolumeData $requestData Extract volume request data object
     * @return ExtractTemplateResponseData
     */
    public function extractVolume(ExtractVolumeData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('extractVolume', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadExtractTemplateData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Loads VolumeResponseList from json object
     *
     * @param   object $volumesList
     * @return  VolumeResponseList Returns VolumeResponseList
     */
    protected function _loadVolumeResponseList($volumesList)
    {
        $result = new VolumeResponseList();

        if (!empty($volumesList)) {
            foreach ($volumesList as $volume) {
                $item = $this->_loadVolumeResponseData($volume);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads VolumeResponseData from json object
     *
     * @param   object $resultObject
     * @return  VolumeResponseData Returns VolumeResponseData
     */
    protected function _loadVolumeResponseData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new VolumeResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('created' == $property || 'attached' == $property) {
                        $item->{$property} = new DateTime((string)$resultObject->{$property}, new DateTimeZone('UTC'));
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
            if (property_exists($resultObject, 'tags')) {
                $item->setTags($this->_loadTagsList($resultObject->tags));
            }
        }

        return $item;
    }

}