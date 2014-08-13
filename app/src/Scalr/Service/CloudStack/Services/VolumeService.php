<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\ExtractTemplateResponseData;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\Volume\DataType\CreateVolumeData;
use Scalr\Service\CloudStack\Services\Volume\DataType\ExtractVolumeData;
use Scalr\Service\CloudStack\Services\Volume\DataType\ListVolumesData;
use Scalr\Service\CloudStack\Services\Volume\DataType\VolumeResponseData;
use Scalr\Service\CloudStack\Services\Volume\DataType\VolumeResponseList;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Volume\V26032014\VolumeApi getApiHandler()
 *           getApiHandler()
 *           Gets an Volume API handler for the specific version
 */
class VolumeService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_VOLUME;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getVersion()
     */
    public function getVersion()
    {
        return self::VERSION_DEFAULT;
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
    public function attach($id, $virtualMachineId, $deviceId = null)
    {
        return $this->getApiHandler()->attachVolume($id, $virtualMachineId, $deviceId);
    }

    /**
     * Detaches a disk volume from a virtual machine.
     *
     * @param string $deviceId the device ID on the virtual machine where volume is detached from
     * @param string $id the ID of the disk volume
     * @param string $virtualMachineId the ID of the virtual machine where the volume is detached from
     * @return VolumeResponseData
     */
    public function detach($deviceId = null, $id = null, $virtualMachineId = null)
    {
        return $this->getApiHandler()->detachVolume($deviceId, $id, $virtualMachineId);
    }

    /**
     * Creates a disk volume from a disk offering.
     * This disk volume must still be attached to a virtual machine to make use of it.
     *
     * @param CreateVolumeData|array $request Create volume request data object
     * @return VolumeResponseData
     */
    public function create($request)
    {
        if ($request !== null && !($request instanceof CreateVolumeData)) {
            $request = CreateVolumeData::initArray($request);
        }
        return $this->getApiHandler()->createVolume($request);
    }

    /**
     * Deletes a detached disk volume.
     *
     * @param string $id The ID of the disk volume
     * @return ResponseDeleteData
     */
    public function delete($id)
    {
        return $this->getApiHandler()->deleteVolume($id);
    }

    /**
     * Lists all volumes.
     *
     * @param ListVolumesData|array $filter   List volumes request object
     * @param PaginationType $pagination Pagination
     * @return VolumeResponseList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListVolumesData)) {
            $filter = ListVolumesData::initArray($filter);
        }
        return $this->getApiHandler()->listVolumes($filter, $pagination);
    }

    /**
     * Extracts volume
     *
     * @param ExtractVolumeData|array $request Extract volume request data object
     * @return ExtractTemplateResponseData
     */
    public function extract($request)
    {
        if ($request !== null && !($request instanceof ExtractVolumeData)) {
            $request = ExtractVolumeData::initArray($request);
        }
        return $this->getApiHandler()->extractVolume($request);
    }

}