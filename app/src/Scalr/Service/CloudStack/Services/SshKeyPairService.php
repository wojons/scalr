<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\ListSshKeyPairsData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\RegisterSshKeyPairData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshKeyResponseData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshKeyResponseList;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshPrivateKeyResponseData;
use Scalr\Service\CloudStack\Services\SshKeyPair\DataType\UpdateSshKeyData;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\SshKeyPair\V26032014\SshKeyPairApi getApiHandler()
 *           getApiHandler()
 *           Gets an SshKeyPair API handler for the specific version
 */
class SshKeyPairService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_SSH_KEY_PAIR;
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
     * Register a public key in a keypair under a certain name
     *
     * @param RegisterSshKeyPairData|array $request Register ssh key data object
     * @return SshKeyResponseData
     */
    public function register($request)
    {
        if ($request !== null && !($request instanceof RegisterSshKeyPairData)) {
            $request = RegisterSshKeyPairData::initArray($request);
        }
        return $this->getApiHandler()->registerSSHKeyPair($request);
    }

    /**
     * Create a new keypair and returns the private key
     *
     * @param UpdateSshKeyData|array $request Data object
     * @return SshPrivateKeyResponseData
     */
    public function create($request)
    {
        if ($request !== null && !($request instanceof UpdateSshKeyData)) {
            $request = UpdateSshKeyData::initArray($request);
        }
        return $this->getApiHandler()->createSSHKeyPair($request);
    }

    /**
     * Deletes a keypair by name
     *
     * @param UpdateSshKeyData|array $request Requesy data object
     * @return ResponseDeleteData
     */
    public function delete($request)
    {
        if ($request !== null && !($request instanceof UpdateSshKeyData)) {
            $request = UpdateSshKeyData::initArray($request);
        }
        return $this->getApiHandler()->deleteSSHKeyPair($request);
    }

    /**
     * List registered keypairs
     *
     * @param ListSshKeyPairsData|array $filter Request data object
     * @param PaginationType $pagination Pagination
     * @return SshKeyResponseList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListSshKeyPairsData)) {
            $filter = ListSshKeyPairsData::initArray($filter);
        }
        return $this->getApiHandler()->listSSHKeyPairs($filter, $pagination);
    }

}