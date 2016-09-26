<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentials;

use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use SERVER_PLATFORMS;

/**
 * Rackspace Cloud Credentials Adapter v1beta0
 *
 * @author N.V.
 */
class RackspaceCloudCredentialsAdapter extends OpenstackCloudCredentialsAdapter
{

    const RACKSPACE_KEYSTONE_URL_US = 'https://identity.api.rackspacecloud.com/v2.0';

    const RACKSPACE_KEYSTONE_URL_UK = 'https://lon.identity.api.rackspacecloud.com/v2.0';

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'id', 'name', 'description',
            '_cloudCredentialsType' => 'cloudCredentialsType', '_isUk' => 'isUk', '_scope' => 'scope', '_status' => 'status'
        ],

        self::RULE_TYPE_SETTINGS_PROPERTY => 'properties',
        self::RULE_TYPE_SETTINGS    => [
            Entity\CloudCredentialsProperty::OPENSTACK_USERNAME    => 'userName',
            Entity\CloudCredentialsProperty::OPENSTACK_API_KEY     => 'apiKey',
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'keystoneUrl', 'userName', 'apiKey'],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    public function _cloudCredentialsType($from, $to, $action)
    {
        switch($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @cloudCredentialsType $from Entity\CloudCredentials */
                $to->cloudCredentialsType = static::CLOUD_CREDENTIALS_TYPE_RACKSPACE;
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                if (!isset($from->isUk)) {
                    $to->cloud = SERVER_PLATFORMS::RACKSPACENG_US;
                    $from->keystoneUrl = static::RACKSPACE_KEYSTONE_URL_US;
                    $this->_keystoneUrl($from, $to, static::ACT_CONVERT_TO_ENTITY);
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['cloud' => ['$in' => PlatformFactory::getRackspacePlatforms()]]];
        }
    }

    public function _isUk($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                $to->isUk = $from->cloud == SERVER_PLATFORMS::RACKSPACENG_UK;
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                if ($from->isUk) {
                    $to->cloud = SERVER_PLATFORMS::RACKSPACENG_UK;
                    $from->keystoneUrl = static::RACKSPACE_KEYSTONE_URL_UK;
                    $this->_keystoneUrl($from, $to, static::ACT_CONVERT_TO_ENTITY);
                } else {
                    $to->cloud = SERVER_PLATFORMS::RACKSPACENG_US;
                    $from->keystoneUrl = static::RACKSPACE_KEYSTONE_URL_US;
                    $this->_keystoneUrl($from, $to, static::ACT_CONVERT_TO_ENTITY);
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['cloud' => $from->isUk ? SERVER_PLATFORMS::RACKSPACENG_UK : SERVER_PLATFORMS::RACKSPACENG_US]];
        }
    }
}