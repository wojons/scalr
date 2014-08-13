<?php
namespace Scalr\Service\CloudStack\Services\Template\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\ExtractTemplateResponseData;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\DataType\TemplatePermissionsList;
use Scalr\Service\CloudStack\DataType\TemplateResponseData;
use Scalr\Service\CloudStack\DataType\TemplateResponseList;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\Template\DataType\CreateTemplateData;
use Scalr\Service\CloudStack\Services\Template\DataType\ExtractTemplateData;
use Scalr\Service\CloudStack\Services\Template\DataType\ListTemplatesData;
use Scalr\Service\CloudStack\Services\Template\DataType\RegisterTemplateData;
use Scalr\Service\CloudStack\Services\Template\DataType\UpdateTemplateData;
use Scalr\Service\CloudStack\Services\Template\DataType\UpdateTemplatePermissionsData;
use Scalr\Service\CloudStack\Services\TemplateService;
use Scalr\Service\CloudStack\Services\TemplateTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;
use Scalr\Service\CloudStack\Services\VirtualTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class TemplateApi extends AbstractApi
{
    use VirtualTrait, TagsTrait, UpdateTrait, TemplateTrait;

    /**
     * @var TemplateService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   TemplateService $template
     */
    public function __construct(TemplateService $template)
    {
        $this->service = $template;
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
     * Creates a template of a virtual machine.
     * The virtual machine must be in a STOPPED state.
     * A template created from this command is automatically designated as a private template visible to the account that created it.
     *
     * @param CreateTemplateData $requestData Create Template request data
     * @return TemplateResponseData
     */
    public function createTemplate(CreateTemplateData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('createTemplate', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadTemplateResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Registers an existing template into the Cloud.com cloud.
     *
     * @param RegisterTemplateData $requestData Register template request data
     * @return TemplateResponseData
     */
    public function registerTemplate(RegisterTemplateData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('registerTemplate', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadTemplateResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Updates attributes of a template.
     *
     * @param UpdateTemplateData $requestData Update template request data
     * @return TemplateResponseData
     */
    public function updateTemplate(UpdateTemplateData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('updateTemplate', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadTemplateResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Copies a template from one zone to another.
     *
     * @param string $id             Template ID.
     * @param string $destzoneId     ID of the zone the template is being copied to.
     * @param string $sourceZoneId   ID of the zone the template is currently hosted on.
     *                               If not specified and template is cross-zone, then we will sync this template to region wide image store
     * @return TemplateResponseData
     */
    public function copyTemplate($id, $destzoneId, $sourceZoneId = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'copyTemplate', array(
                'id' => $this->escape($id),
                'destzoneid' => $this->escape($destzoneId),
                'sourcezoneid' => $this->escape($sourceZoneId),
             )
        );
        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadTemplateResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes a template from the system.
     * All virtual machines using the deleted template will not be affected.
     *
     * @param string $id the ID of the template
     * @param string $zoneId the ID of zone of the template
     * @return ResponseDeleteData
     */
    public function deleteTemplate($id, $zoneId = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteTemplate', array(
                'id' => $this->escape($id),
                'zoneid' => $this->escape($zoneId)
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
     * List all public, private, and privileged templates.
     *
     * @param ListTemplatesData $requestData List template request data
     * @param PaginationType $pagination Pagination
     * @return TemplateResponseList|null
     */
    public function listTemplates(ListTemplatesData $requestData, PaginationType $pagination = null)
    {
        $result = null;
        $args = $requestData->toArray();
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }

        $response = $this->getClient()->call('listTemplates', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadTemplateResponseList($resultObject->template);
            }
        }

        return $result;
    }

    /**
     * Updates a template visibility permissions.
     * A public template is visible to all accounts within the same domain.
     * A private template is visible only to the owner of the template.
     * A priviledged template is a private template with account permissions added.
     * Only accounts specified under the template permissions are visible to them.
     *
     * @param UpdateTemplatePermissionsData $requestData Update template permissions request data
     * @return ResponseDeleteData
     */
    public function updateTemplatePermissions(UpdateTemplatePermissionsData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('updateTemplatePermissions', $requestData->toArray());

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
     * @param string $id         The template ID
     * @param string $account    List template visibility and permissions for the specified account.
     *                           Must be used with the domainId parameter.
     * @param string $domainId   List template visibility and permissions by domain.
     *                           If used with the account parameter, specifies in which domain the specified account exists.
     * @param PaginationType $pagination Pagination
     * @return TemplatePermissionsList|null
     */
    public function listTemplatePermissions($id, $account = null, $domainId = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array(
           'id'        => $this->escape($id),
           'account'   => $this->escape($account),
           'domainId'  => $this->escape($domainId)
        );
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }

        $response = $this->getClient()->call(
            'listTemplatePermissions', $args
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadTemplatePermissionsList($resultObject->templatepermission);
            }
        }

        return $result;
    }

    /**
     * Extracts a template
     *
     * @param ExtractTemplateData $requestData Extract template request data
     * @return ExtractTemplateResponseData
     */
    public function extractTemplate(ExtractTemplateData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('extractTemplate', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadExtractTemplateData($resultObject);
            }
        }

        return $result;
    }

}