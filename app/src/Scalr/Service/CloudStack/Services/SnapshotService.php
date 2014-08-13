<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\CreateSnapshotData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\CreateSnapshotPolicyData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\ListSnapshotData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotPolicyResponseData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotPolicyResponseList;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotResponseData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotResponseList;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Snapshot\V26032014\SnapshotApi getApiHandler()
 *           getApiHandler()
 *           Gets an Snapshot API handler for the specific version
 */
class SnapshotService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_SNAPSHOT;
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
     * Creates an instant snapshot of a volume.
     *
     * @param CreateSnapshotData|array $request Create snapshot request data object
     * @return SnapshotResponseData
     */
    public function create($request)
    {
        if ($request !== null && !($request instanceof CreateSnapshotData)) {
            $request = CreateSnapshotData::initArray($request);
        }
        return $this->getApiHandler()->createSnapshot($request);
    }

    /**
     * Lists all available snapshots for the account.
     *
     * @param ListSnapshotData|array   $filter List snapshot request data object
     * @param PaginationType           $pagination  Pagination
     * @return SnapshotResponseList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListSnapshotData)) {
            $filter = ListSnapshotData::initArray($filter);
        }
        return $this->getApiHandler()->listSnapshots($filter, $pagination);
    }

    /**
     * Deletes a snapshot of a disk volume.
     *
     * @param string $id The ID of the snapshot
     * @return ResponseDeleteData
     */
    public function delete($id)
    {
        return $this->getApiHandler()->deleteSnapshot($id);
    }

    /**
     * Creates a snapshot policy for the account.
     *
     * @param CreateSnapshotPolicyData|array $request Create policy data object
     * @return SnapshotPolicyResponseData
     */
    public function createPolicy($request)
    {
        if ($request !== null && !($request instanceof CreateSnapshotPolicyData)) {
            $request = CreateSnapshotPolicyData::initArray($request);
        }
        return $this->getApiHandler()->createSnapshotPolicy($request);
    }

    /**
     * Deletes snapshot policies for the account.
     *
     * @param string $id the Id of the snapshot
     * @param string $ids list of snapshots IDs separated by comma
     * @return ResponseDeleteData
     */
    public function deletePolicies($id = null, $ids = null)
    {
        return $this->getApiHandler()->deleteSnapshotPolicies($id, $ids);
    }

    /**
     * Lists snapshot policies.
     *
     * @param string $volumeId the ID of the disk volume
     * @param string $keyword
     * @param PaginationType $pagination Pagination
     * @return SnapshotPolicyResponseList|null
     */
    public function listPolicies($volumeId, $keyword = null, PaginationType $pagination = null)
    {
        return $this->getApiHandler()->listSnapshotPolicies($volumeId, $keyword, $pagination);
    }

}