<?php
namespace Scalr\Service\CloudStack\Services\Snapshot\V26032014;

use DateTime;
use DateTimeZone;
use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\CreateSnapshotData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\CreateSnapshotPolicyData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\ListSnapshotData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotPolicyResponseData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotPolicyResponseList;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotResponseData;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotResponseList;
use Scalr\Service\CloudStack\Services\SnapshotService;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class SnapshotApi extends AbstractApi
{
    use TagsTrait, UpdateTrait;

    /**
     * @var SnapshotService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   SnapshotService $snapshot
     */
    public function __construct(SnapshotService $snapshot)
    {
        $this->service = $snapshot;
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
     * Creates an instant snapshot of a volume.
     *
     * @param CreateSnapshotData $requestData Create snapshot request data object
     * @return SnapshotResponseData
     */
    public function createSnapshot(CreateSnapshotData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createSnapshot', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadSnapshotResponseData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Lists all available snapshots for the account.
     *
     * @param ListSnapshotData   $requestData List snapshot request data object
     * @param PaginationType     $pagination  Pagination
     * @return SnapshotResponseList|null
     */
    public function listSnapshots(ListSnapshotData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listSnapshots', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadSnapshotResponseList($resultObject->snapshot);
            }
        }

        return $result;
    }

    /**
     * Deletes a snapshot of a disk volume.
     *
     * @param string $id The ID of the snapshot
     * @return ResponseDeleteData
     */
    public function deleteSnapshot($id)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteSnapshot',
             array('id' => $this->escape($id))
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
     * Creates a snapshot policy for the account.
     *
     * @param CreateSnapshotPolicyData $requestData Create policy data object
     * @return SnapshotPolicyResponseData
     */
    public function createSnapshotPolicy(CreateSnapshotPolicyData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createSnapshotPolicy', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadSnapshotPolicyData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes snapshot policies for the account.
     *
     * @param string $id the Id of the snapshot
     * @param string $ids list of snapshots IDs separated by comma
     * @return ResponseDeleteData
     */
    public function deleteSnapshotPolicies($id = null, $ids = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteSnapshotPolicies',
             array(
                 'id' => $this->escape($id),
                 'ids' => $this->escape($ids)
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
     * Lists snapshot policies.
     *
     * @param string $volumeId the ID of the disk volume
     * @param PaginationType $pagination Pagination
     * @return SnapshotPolicyResponseList|null
     */
    public function listSnapshotPolicies($volumeId, $keyword = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array(
            'volumeid' => $this->escape($volumeId),
            'keyword' => $this->escape($keyword)
        );

        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listSnapshotPolicies', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadSnapshotPolicyList($resultObject->snapshotpolicy);
            }
        }

        return $result;
    }

    /**
     * Loads SnapshotResponseList from json object
     *
     * @param   object $snapshotList
     * @return  SnapshotResponseList Returns SnapshotResponseList
     */
    protected function _loadSnapshotResponseList($snapshotList)
    {
        $result = new SnapshotResponseList();

        if (!empty($snapshotList)) {
            foreach ($snapshotList as $snapshot) {
                $item = $this->_loadSnapshotResponseData($snapshot);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads SnapshotResponseData from json object
     *
     * @param   object $resultObject
     * @return  SnapshotResponseData Returns SnapshotResponseData
     */
    protected function _loadSnapshotResponseData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new SnapshotResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('created' == $property) {
                        $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                    } else if (is_object($resultObject->{$property})) {
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
        }

        return $item;
    }

    /**
     * Loads SnapshotPolicyResponseList from json object
     *
     * @param   object $policyList
     * @return  SnapshotPolicyResponseList Returns SnapshotPolicyResponseList
     */
    protected function _loadSnapshotPolicyList($policyList)
    {
        $result = new SnapshotPolicyResponseList();

        if (!empty($policyList)) {
            foreach ($policyList as $policy) {
                $item = $this->_loadSnapshotPolicyData($policy);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads SnapshotPolicyResponseData from json object
     *
     * @param   object $resultObject
     * @return  SnapshotPolicyResponseData Returns SnapshotPolicyResponseData
     */
    protected function _loadSnapshotPolicyData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new SnapshotPolicyResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('schedule' == $property) {
                        $item->{$property} = new DateTime((string)$resultObject->{$property}, new DateTimeZone('UTC'));
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

}