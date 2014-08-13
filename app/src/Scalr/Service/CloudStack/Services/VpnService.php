<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\AddVpnUserData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\CreateRemoteAccessData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\ListRemoteAccessData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\ListVpnUsersData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\RemoteAccessResponseData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\RemoteAccessResponseList;
use Scalr\Service\CloudStack\Services\Vpn\DataType\RemoveVpnUserData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\VpnUserResponseData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\VpnUserResponseList;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Vpn\V26032014\VpnApi getApiHandler()
 *           getApiHandler()
 *           Gets an Vpn API handler for the specific version
 */
class VpnService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_VPN;
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
     * Creates a l2tp/ipsec remote access vpn
     *
     * @param CreateRemoteAccessData|array $request Remote access request data object
     * @return RemoteAccessResponseData
     */
    public function create($request)
    {
        if ($request !== null && !($request instanceof CreateRemoteAccessData)) {
            $request = CreateRemoteAccessData::initArray($request);
        }
        return $this->getApiHandler()->createRemoteAccessVpn($request);
    }

    /**
     * Destroys a l2tp/ipsec remote access vpn
     *
     * @param string $publicIpId public ip address id of the vpn server
     * @return ResponseDeleteData
     */
    public function delete($publicIpId)
    {
        return $this->getApiHandler()->deleteRemoteAccessVpn($publicIpId);
    }

    /**
     * Lists remote access vpns
     *
     * @param ListRemoteAccessData|array   $filter List remote access request data object
     * @param PaginationType               $pagination  Pagination
     * @return RemoteAccessResponseList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListRemoteAccessData)) {
            $filter = ListRemoteAccessData::initArray($filter);
        }
        return $this->getApiHandler()->listRemoteAccessVpns($filter, $pagination);
    }

    /**
     * Adds vpn users
     *
     * @param AddVpnUserData|array $request Add vpn user request data object
     * @return VpnUserResponseData
     */
    public function addUser($request)
    {
        if ($request !== null && !($request instanceof AddVpnUserData)) {
            $request = AddVpnUserData::initArray($request);
        }
        return $this->getApiHandler()->addVpnUser($request);
    }

    /**
     * Removes vpn user
     *
     * @param RemoveVpnUserData|array $request Remove request data object
     * @return ResponseDeleteData
     */
    public function removeUser($request)
    {
        if ($request !== null && !($request instanceof RemoveVpnUserData)) {
            $request = RemoveVpnUserData::initArray($request);
        }
        return $this->getApiHandler()->removeVpnUser($request);
    }

    /**
     * Lists vpn users
     *
     * @param ListVpnUsersData|array   $filter List vpn users request data object
     * @param PaginationType           $pagination  Pagination
     * @return VpnUserResponseList|null
     */
    public function listUsers($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListVpnUsersData)) {
            $filter = ListVpnUsersData::initArray($filter);
        }
        return $this->getApiHandler()->listVpnUsers($filter, $pagination);
    }

}