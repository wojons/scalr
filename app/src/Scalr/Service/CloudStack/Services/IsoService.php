<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\ExtractTemplateResponseData;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\DataType\TemplatePermissionsList;
use Scalr\Service\CloudStack\DataType\TemplateResponseData;
use Scalr\Service\CloudStack\DataType\TemplateResponseList;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData;
use Scalr\Service\CloudStack\Services\Iso\DataType\ExtractIsoData;
use Scalr\Service\CloudStack\Services\Iso\DataType\ListIsosData;
use Scalr\Service\CloudStack\Services\Iso\DataType\RegisterIsoData;
use Scalr\Service\CloudStack\Services\Iso\DataType\UpdateIsoData;
use Scalr\Service\CloudStack\Services\Iso\DataType\UpdateIsoPermissions;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Iso\V26032014\IsoApi getApiHandler()
 *           getApiHandler()
 *           Gets an Iso API handler for the specific version
 */
class IsoService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_ISO;
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
     * Attaches an ISO to a virtual machine.
     *
     * @param string $id the ID of the ISO file
     * @param string $virtualMachineId the ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function attach($id, $virtualMachineId)
    {
        return $this->getApiHandler()->attachIso($id, $virtualMachineId);
    }

    /**
     * Detaches any ISO file (if any) currently attached to a virtual machine.
     *
     * @param string $virtualMachineId The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function detach($virtualMachineId)
    {
        return $this->getApiHandler()->detachIso($virtualMachineId);
    }

    /**
     * Lists all available ISO files.
     *
     * @param ListIsosData|array $filter  Iso request data
     * @param PaginationType $pagination Pagination
     * @return TemplateResponseList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListIsosData)) {
            $filter = ListIsosData::initArray($filter);
        }
        return $this->getApiHandler()->listIsos($filter, $pagination);
    }

    /**
     * Registers an existing ISO into the Cloud.com Cloud.
     *
     * @param RegisterIsoData|array $request Register Iso request data
     * @return TemplateResponseData
     */
    public function register($request)
    {
        if ($request !== null && !($request instanceof RegisterIsoData)) {
            $request = RegisterIsoData::initArray($request);
        }
        return $this->getApiHandler()->registerIso($request);
    }

    /**
     * Updates an ISO file.
     *
     * @param UpdateIsoData|array $request Update Iso request data
     * @return TemplateResponseData
     */
    public function update($request)
    {
        if ($request !== null && !($request instanceof UpdateIsoData)) {
            $request = UpdateIsoData::initArray($request);
        }
        return $this->getApiHandler()->updateIso($request);
    }

    /**
     * Deletes an ISO file.
     *
     * @param string $id     The ID of the ISO file
     * @param string $zoneId The ID of the zone of the ISO file.
     *                       If not specified, the ISO will be deleted from all the zones
     * @return ResponseDeleteData
     */
    public function delete($id, $zoneId = null)
    {
        return $this->getApiHandler()->deleteIso($id, $zoneId);
    }

    /**
     * Copies an ISO file.
     *
     * @param string $id the ID of the ISO file
     * @param string $destzoneId the ID of the destination zone to which the ISO file will be copied
     * @param string $sourceZoneId the ID of the source zone from which the ISO file will be copied
     * @return TemplateResponseData
     */
    public function copy($id, $destzoneId, $sourceZoneId = null)
    {
        return $this->getApiHandler()->copyIso($id, $destzoneId, $sourceZoneId);
    }

    /**
     * Updates iso permissions
     *
     * @param UpdateIsoPermissions|array $request request data object
     * @return ResponseDeleteData
     */
    public function updatePermissions($request)
    {
        if ($request !== null && !($request instanceof UpdateIsoPermissions)) {
            $request = UpdateIsoPermissions::initArray($request);
        }
        return $this->getApiHandler()->updateIsoPermissions($request);
    }

    /**
     * List template visibility and all accounts that have permissions to view this template.
     *
     * @param string $id        The template ID
     * @param string $account   List template visibility and permissions for the specified account. Must be used with the domainId parameter.
     * @param string $domainId  List template visibility and permissions by domain.
     *                          If used with the account parameter, specifies in which domain the specified account exists.
     * @param PaginationType $pagination Pagination
     * @return TemplatePermissionsList|null
     */
    public function listPermissions($id, $account = null, $domainId = null, PaginationType $pagination = null)
    {
        return $this->getApiHandler()->listIsoPermissions($id, $account, $domainId, $pagination);
    }

    /**
     * Extracts an ISO
     *
     * @param ExtractIsoData|array $request request data object
     * @return ExtractTemplateResponseData
     */
    public function extract($request)
    {
        if ($request !== null && !($request instanceof ExtractIsoData)) {
            $request = ExtractIsoData::initArray($request);
        }
        return $this->getApiHandler()->extractIso($request);
    }

}