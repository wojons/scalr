<?php
namespace Scalr\Service\CloudStack\Services\Vpn\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\UpdateTrait;
use Scalr\Service\CloudStack\Services\Vpn\DataType\AddVpnUserData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\CreateRemoteAccessData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\ListRemoteAccessData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\ListVpnUsersData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\RemoteAccessResponseData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\RemoteAccessResponseList;
use Scalr\Service\CloudStack\Services\Vpn\DataType\RemoveVpnUserData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\VpnUserResponseData;
use Scalr\Service\CloudStack\Services\Vpn\DataType\VpnUserResponseList;
use Scalr\Service\CloudStack\Services\VpnService;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class VpnApi extends AbstractApi
{
    use UpdateTrait;

    /**
     * @var VpnService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   VpnService $vpn
     */
    public function __construct(VpnService $vpn)
    {
        $this->service = $vpn;
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
     * Creates a l2tp/ipsec remote access vpn
     *
     * @param CreateRemoteAccessData $requestData Remote access request data object
     * @return RemoteAccessResponseData
     */
    public function createRemoteAccessVpn(CreateRemoteAccessData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createRemoteAccessVpn', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadRemoteAccessData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Destroys a l2tp/ipsec remote access vpn
     *
     * @param string $publicIpId public ip address id of the vpn server
     * @return ResponseDeleteData
     */
    public function deleteRemoteAccessVpn($publicIpId)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteRemoteAccessVpn', array('publicipid' => $publicIpId)
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
     * Lists remote access vpns
     *
     * @param ListRemoteAccessData   $requestData List remote access request data object
     * @param PaginationType         $pagination  Pagination
     * @return RemoteAccessResponseList|null
     */
    public function listRemoteAccessVpns(ListRemoteAccessData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_push($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listRemoteAccessVpns', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadRemoteAccessList($resultObject->remoteaccessvpn);
            }
        }

        return $result;
    }

    /**
     * Adds vpn users
     *
     * @param AddVpnUserData $requestData Add vpn user request data object
     * @return VpnUserResponseData
     */
    public function addVpnUser(AddVpnUserData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'addVpnUser', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadVpnUserData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Removes vpn user
     *
     * @param RemoveVpnUserData $requestData Remove request data object
     * @return ResponseDeleteData
     */
    public function removeVpnUser(RemoveVpnUserData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'removeVpnUser', $requestData->toArray()
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
     * Lists vpn users
     *
     * @param ListVpnUsersData   $requestData List vpn users request data object
     * @param PaginationType         $pagination  Pagination
     * @return VpnUserResponseList|null
     */
    public function listVpnUsers(ListVpnUsersData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_push($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listVpnUsers', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadVpnUserList($resultObject->vpnuser);
            }
        }

        return $result;
    }

    /**
     * Loads RemoteAccessResponseList from json object
     *
     * @param   object $remoteAccessList
     * @return  RemoteAccessResponseList Returns RemoteAccessResponseList
     */
    protected function _loadRemoteAccessList($remoteAccessList)
    {
        $result = new RemoteAccessResponseList();

        if (!empty($remoteAccessList)) {
            foreach ($remoteAccessList as $remoteAccess) {
                $item = $this->_loadRemoteAccessData($remoteAccess);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads RemoteAccessResponseData from json object
     *
     * @param   object $resultObject
     * @return  RemoteAccessResponseData Returns RemoteAccessResponseData
     */
    protected function _loadRemoteAccessData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new RemoteAccessResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        // Fix me. Temporary fix.
                        trigger_error('Cloudstack error. Unexpected sdt object class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Loads VpnUserResponseList from json object
     *
     * @param   object $vpnUsersList
     * @return  VpnUserResponseList Returns VpnUserResponseList
     */
    protected function _loadVpnUserList($vpnUsersList)
    {
        $result = new VpnUserResponseList();

        if (!empty($vpnUsersList)) {
            foreach ($vpnUsersList as $vpnUser) {
                $item = $this->_loadVpnUserData($vpnUser);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads VpnUserResponseData from json object
     *
     * @param   object $resultObject
     * @return  VpnUserResponseData Returns VpnUserResponseData
     */
    protected function _loadVpnUserData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new VpnUserResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        // Fix me. Temporary fix.
                        trigger_error('Cloudstack error. Unexpected sdt object class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

}