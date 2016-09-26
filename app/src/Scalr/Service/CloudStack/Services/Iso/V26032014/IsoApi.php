<?php
namespace Scalr\Service\CloudStack\Services\Iso\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\ExtractTemplateResponseData;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\DataType\TemplatePermissionsList;
use Scalr\Service\CloudStack\DataType\TemplateResponseData;
use Scalr\Service\CloudStack\DataType\TemplateResponseList;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\Iso\DataType\ExtractIsoData;
use Scalr\Service\CloudStack\Services\Iso\DataType\ListIsosData;
use Scalr\Service\CloudStack\Services\Iso\DataType\RegisterIsoData;
use Scalr\Service\CloudStack\Services\Iso\DataType\UpdateIsoData;
use Scalr\Service\CloudStack\Services\Iso\DataType\UpdateIsoPermissions;
use Scalr\Service\CloudStack\Services\IsoService;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\TemplateTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;
use Scalr\Service\CloudStack\Services\VirtualTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class IsoApi extends AbstractApi
{
    use VirtualTrait, TagsTrait, UpdateTrait, TemplateTrait;

    /**
     * @var IsoService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   IsoService $iso
     */
    public function __construct(IsoService $iso)
    {
        $this->service = $iso;
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
     * Attaches an ISO to a virtual machine.
     *
     * @param string $id the ID of the ISO file
     * @param string $virtualMachineId the ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function attachIso($id, $virtualMachineId)
    {
        $result = null;

        $response = $this->getClient()->call('attachIso', array(
            'id' => $this->escape($id),
            'virtualmachineid' => $this->escape($virtualMachineId),
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Detaches any ISO file (if any) currently attached to a virtual machine.
     *
     * @param string $virtualMachineId The ID of the virtual machine
     * @return VirtualMachineInstancesData
     */
    public function detachIso($virtualMachineId)
    {
        $result = null;

        $response = $this->getClient()->call('detachIso', array(
            'virtualmachineid' => $this->escape($virtualMachineId)
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVirtualMachineInstanceData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Lists all available ISO files.
     *
     * @param ListIsosData $requestData  Iso request data
     * @param PaginationType $pagination Pagination
     * @return TemplateResponseList|null
     */
    public function listIsos(ListIsosData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = array_merge($args, $requestData->toArray());
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }

        $response = $this->getClient()->call('listIsos', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadTemplateResponseList($resultObject->iso);
            }
        }

        return $result;
    }

    /**
     * Registers an existing ISO into the Cloud.com Cloud.
     *
     * @param RegisterIsoData $requestData Register Iso request data
     * @return TemplateResponseData
     */
    public function registerIso(RegisterIsoData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('registerIso', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadTemplateResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Updates an ISO file.
     *
     * @param UpdateIsoData $requestData Update Iso request data
     * @return TemplateResponseData
     */
    public function updateIso(UpdateIsoData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('updateIso', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadTemplateResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes an ISO file.
     *
     * @param string $id     The ID of the ISO file
     * @param string $zoneId The ID of the zone of the ISO file.
     *                       If not specified, the ISO will be deleted from all the zones
     * @return ResponseDeleteData
     */
    public function deleteIso($id, $zoneId = null)
    {
        $result = null;

        $response = $this->getClient()->call('deleteIso', array(
            'id' => $this->escape($id),
            'zoneid' => $this->escape($zoneId)
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadUpdateData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Copies an ISO file.
     *
     * @param string $id the ID of the ISO file
     * @param string $destzoneId the ID of the destination zone to which the ISO file will be copied
     * @param string $sourceZoneId the ID of the source zone from which the ISO file will be copied
     * @return TemplateResponseData
     */
    public function copyIso($id, $destzoneId, $sourceZoneId = null)
    {
        $result = null;

        $response = $this->getClient()->call('copyIso', array(
            'id' => $this->escape($id),
            'destzoneid' => $this->escape($destzoneId),
            'sourcezoneid' => $this->escape($sourceZoneId),
        ));

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadTemplateResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Updates iso permissions
     *
     * @param UpdateIsoPermissions $requestData request data object
     * @return ResponseDeleteData
     */
    public function updateIsoPermissions(UpdateIsoPermissions $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('updateIsoPermissions', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadUpdateData($resultObject);
            }
        }

        return $result;
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
    public function listIsoPermissions($id, $account = null, $domainId = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array(
            'id' => $this->escape($id),
            'destzoneid' => $this->escape($account),
            'sourcezoneid' => $this->escape($domainId),
        );
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listIsoPermissions', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadTemplatePermissionsList($resultObject->isopermission);
            }
        }

        return $result;
    }

    /**
     * Extracts an ISO
     *
     * @param ExtractIsoData $requestData request data object
     * @return ExtractTemplateResponseData
     */
    public function extractIso(ExtractIsoData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('extractIso', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadExtractTemplateData($resultObject);
            }
        }

        return $result;
    }

}