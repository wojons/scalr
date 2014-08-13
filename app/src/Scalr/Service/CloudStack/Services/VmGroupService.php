<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\VmGroup\DataType\CreateInstanceGroupData;
use Scalr\Service\CloudStack\Services\VmGroup\DataType\InstanceGroupList;
use Scalr\Service\CloudStack\Services\VmGroup\DataType\ListInstanceGroupsData;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\VmGroup\V26032014\VmGroupApi getApiHandler()
 *           getApiHandler()
 *           Gets an VmGroup API handler for the specific version
 */
class VmGroupService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_VM_GROUP;
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
     * Creates a vm group
     *
     * @param CreateInstanceGroupData|array $request Request data object
     * @return InstanceGroupData
     */
    public function create($request)
    {
        if ($request !== null && !($request instanceof CreateInstanceGroupData)) {
            $request = CreateInstanceGroupData::initArray($request);
        }
        return $this->getApiHandler()->createInstanceGroup($request);
    }

    /**
     * Deletes a vm group
     *
     * @param string $id the ID of the instance group
     * @return ResponseDeleteData
     */
    public function delete($id)
    {
        return $this->getApiHandler()->deleteInstanceGroup($id);
    }

    /**
     * Updates a vm group
     *
     * @param string $id Instance group ID
     * @param string $name new instance group name
     * @return InstanceGroupData
     */
    public function update($id, $name = null)
    {
        return $this->getApiHandler()->updateInstanceGroup($id, $name);
    }

    /**
     * Lists vm groups
     *
     * @param ListInstanceGroupsData|array $filter Request data object
     * @param PaginationType               $pagination Pagination
     * @return InstanceGroupList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListInstanceGroupsData)) {
            $filter = ListInstanceGroupsData::initArray($filter);
        }
        return $this->getApiHandler()->listInstanceGroups($filter, $pagination);
    }

}