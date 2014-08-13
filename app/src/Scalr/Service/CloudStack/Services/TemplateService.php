<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\ExtractTemplateResponseData;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\DataType\TemplatePermissionsList;
use Scalr\Service\CloudStack\DataType\TemplateResponseData;
use Scalr\Service\CloudStack\DataType\TemplateResponseList;
use Scalr\Service\CloudStack\Services\Template\DataType\CreateTemplateData;
use Scalr\Service\CloudStack\Services\Template\DataType\ExtractTemplateData;
use Scalr\Service\CloudStack\Services\Template\DataType\ListTemplatesData;
use Scalr\Service\CloudStack\Services\Template\DataType\RegisterTemplateData;
use Scalr\Service\CloudStack\Services\Template\DataType\UpdateTemplateData;
use Scalr\Service\CloudStack\Services\Template\DataType\UpdateTemplatePermissionsData;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\TemplateService\V26032014\TemplateApi getApiHandler()
 *           getApiHandler()
 *           Gets an Network API handler for the specific version
 */
class TemplateService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_TEMPLATE;
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
     * Creates a template of a virtual machine.
     * The virtual machine must be in a STOPPED state.
     * A template created from this command is automatically designated as a private template visible to the account that created it.
     *
     * @param CreateTemplateData|array $request Create Template request data
     * @return TemplateResponseData
     */
    public function create($request)
    {
        if ($request !== null && !($request instanceof CreateTemplateData)) {
            $request = CreateTemplateData::initArray($request);
        }
        return $this->getApiHandler()->createTemplate($request);
    }

    /**
     * Registers an existing template into the Cloud.com cloud.
     *
     * @param RegisterTemplateData|array $request Register template request data
     * @return TemplateResponseData
     */
    public function register($request)
    {
        if ($request !== null && !($request instanceof RegisterTemplateData)) {
            $request = RegisterTemplateData::initArray($request);
        }
        return $this->getApiHandler()->registerTemplate($request);
    }

    /**
     * Updates attributes of a template.
     *
     * @param UpdateTemplateData|array $request Update template request data
     * @return TemplateResponseData
     */
    public function update($request)
    {
        if ($request !== null && !($request instanceof UpdateTemplateData)) {
            $request = UpdateTemplateData::initArray($request);
        }
        return $this->getApiHandler()->updateTemplate($request);
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
    public function copy($id, $destzoneId, $sourceZoneId = null)
    {
        return $this->getApiHandler()->copyTemplate($id, $destzoneId, $sourceZoneId);
    }

    /**
     * Deletes a template from the system.
     * All virtual machines using the deleted template will not be affected.
     *
     * @param string $id the ID of the template
     * @param string $zoneId the ID of zone of the template
     * @return ResponseDeleteData
     */
    public function delete($id, $zoneId = null)
    {
        return $this->getApiHandler()->deleteTemplate($id, $zoneId);
    }

    /**
     * List all public, private, and privileged templates.
     *
     * @param ListTemplatesData|array $filter List template request data
     * @param PaginationType $pagination Pagination
     * @return TemplateResponseList|null
     */
    public function describe($filter, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListTemplatesData)) {
            $filter = ListTemplatesData::initArray($filter);
        }
        return $this->getApiHandler()->listTemplates($filter, $pagination);
    }

    /**
     * Updates a template visibility permissions.
     * A public template is visible to all accounts within the same domain.
     * A private template is visible only to the owner of the template.
     * A priviledged template is a private template with account permissions added.
     * Only accounts specified under the template permissions are visible to them.
     *
     * @param UpdateTemplatePermissionsData|array $request Update template permissions request data
     * @return ResponseDeleteData
     */
    public function updatePermissions($request)
    {
        if ($request !== null && !($request instanceof UpdateTemplatePermissionsData)) {
            $request = UpdateTemplatePermissionsData::initArray($request);
        }
        return $this->getApiHandler()->updateTemplatePermissions($request);
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
    public function listPermissions($id, $account = null, $domainId = null, PaginationType $pagination = null)
    {
        return $this->getApiHandler()->listTemplatePermissions($id, $account, $domainId, $pagination);
    }

    /**
     * Extracts a template
     *
     * @param ExtractTemplateData|array $request Extract template request data
     * @return ExtractTemplateResponseData
     */
    public function extract($request)
    {
        if ($request !== null && !($request instanceof ExtractTemplateData)) {
            $request = ExtractTemplateData::initArray($request);
        }
        return $this->getApiHandler()->extractTemplate($request);
    }

}