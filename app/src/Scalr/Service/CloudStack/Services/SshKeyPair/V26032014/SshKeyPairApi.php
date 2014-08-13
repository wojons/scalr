<?php
namespace Scalr\Service\CloudStack\Services\SshKeyPair\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\ListSshKeyPairsData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\RegisterSshKeyPairData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshKeyResponseData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshKeyResponseList;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshPrivateKeyResponseData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\UpdateSshKeyData;
use Scalr\Service\CloudStack\Services\SshKeyPairService;
use Scalr\Service\CloudStack\Services\UpdateTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class SshKeyPairApi extends AbstractApi
{
    use UpdateTrait;
    /**
     * @var SshKeyPairService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   SshKeyPairService $sshKeyPair
     */
    public function __construct(SshKeyPairService $sshKeyPair)
    {
        $this->service = $sshKeyPair;
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
     * Register a public key in a keypair under a certain name
     *
     * @param RegisterSshKeyPairData $requestData Register ssh key data object
     * @return SshKeyResponseData
     */
    public function registerSSHKeyPair(RegisterSshKeyPairData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'registerSSHKeyPair', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadSshKeyPairData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Create a new keypair and returns the private key
     *
     * @param UpdateSshKeyData $requestData Data object
     * @return SshPrivateKeyResponseData
     */
    public function createSSHKeyPair(UpdateSshKeyData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createSSHKeyPair', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadSshPrivateKeyData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes a keypair by name
     *
     * @param UpdateSshKeyData $requestData Requesy data object
     * @return ResponseDeleteData
     */
    public function deleteSSHKeyPair(UpdateSshKeyData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteSSHKeyPair', $requestData->toArray()
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
     * List registered keypairs
     *
     * @param ListSshKeyPairsData $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return SshKeyResponseList|null
     */
    public function listSSHKeyPairs(ListSshKeyPairsData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listSSHKeyPairs', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadSshKeyPairList($resultObject->sshkeypair);
            }
        }

        return $result;
    }

    /**
     * Loads SshKeyResponseList from json object
     *
     * @param   object $keysList
     * @return  SshKeyResponseList Returns SshKeyResponseList
     */
    protected function _loadSshKeyPairList($keysList)
    {
        $result = new SshKeyResponseList();

        if (!empty($keysList)) {
            foreach ($keysList as $key) {
                $item = $this->_loadSshKeyPairData($key);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads SshKeyResponseData from json object
     *
     * @param   object $resultObject
     * @return  SshKeyResponseData Returns SshKeyResponseData
     */
    protected function _loadSshKeyPairData($resultObject)
    {
        $item = new SshKeyResponseData();
        $item->fingerprint = (property_exists($resultObject, 'fingerprint') ? (string) $resultObject->fingerprint : null);
        $item->name = (property_exists($resultObject, 'name') ? (string) $resultObject->name : null);

        return $item;
    }

    /**
     * Loads SshPrivateKeyResponseData from json object
     *
     * @param   object $resultObject
     * @return  SshPrivateKeyResponseData Returns SshPrivateKeyResponseData
     */
    protected function _loadSshPrivateKeyData($resultObject)
    {
        $item = new SshPrivateKeyResponseData();
        $item->name = !empty($resultObject->keypair->name) ? (string) $resultObject->keypair->name : null;
        $item->fingerprint = !empty($resultObject->keypair->fingerprint) ? (string) $resultObject->keypair->fingerprint : null;
        $item->privatekey = !empty($resultObject->keypair->privatekey) ? (string) $resultObject->keypair->privatekey : null;

        return $item;
    }

}