<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\Entity\Server;
use Scalr\Model\Entity;
use Scalr_Role_Behavior;

/**
 * ServerAdapter v1beta0
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.11 (23.02.2016)
 */
class ServerAdapter extends ApiEntityAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA => [
            'serverId' => 'id', 'cloudLocation', 'platform' => 'cloudPlatform',
            'type', 'added' => 'launched', 'index',
            '_status'       => 'status',
            '_farm'         => 'farm',
            '_farmRole'     => 'farmRole',
            '_publicIp'     => 'publicIp',
            '_privateIp'    => 'privateIp',
            '_launchedBy'   => 'launchedBy'
        ],

        self::RULE_TYPE_SETTINGS_PROPERTY => 'properties',
        self::RULE_TYPE_SETTINGS    => [
            Scalr_Role_Behavior::SERVER_BASE_HOSTNAME   => 'hostname',
            Server::LAUNCH_REASON                       => 'launchReason',
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE => [],

        self::RULE_TYPE_FILTERABLE => ['id', 'status', 'cloudPlatform', 'cloudLocation', 'index', 'publicIp', 'privateIp', 'hostname', 'launchReason', 'launchedBy', 'farmRole', 'farm', 'type'],
        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['added' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\Server';

    protected function _status($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Server */
                $to->status = lcfirst(str_replace(' ', '_', $from->status));
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Server */
                $to->status = ucfirst(str_replace('_', ' ', $from->status));
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['status' => ucfirst(str_replace('_', ' ', $from->status))]];
        }
    }

    protected function _farm($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Server */
                $to->farm = [ 'id' => $from->farmId ];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Server */
                $to->farmId = ApiController::getBareId($from, 'farm');
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ 'farmId' => ApiController::getBareId($from, 'farm') ]];
        }
    }

    protected function _farmRole($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Server */
                $to->farmRole = [ 'id' => $from->farmRoleId ];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Server */
                $to->farmRoleId = ApiController::getBareId($from, 'farmRole');
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ 'farmRoleId' => ApiController::getBareId($from, 'farmRole') ]];
        }
    }

    protected function _publicIp($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Server */
                $to->publicIp = empty($from->remoteIp) ? [] : [$from->remoteIp];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Server */
                $to->remoteIp = empty($from->publicIp) ? null : reset($from->publicIp);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['remoteIp' => reset($from->publicIp)]];
        }
    }

    protected function _privateIp($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Server */
                $to->privateIp = empty($from->localIp) ? [] : [$from->localIp];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Server */
                $to->localIp = empty($from->privateIp) ? null : reset($from->privateIp);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['localIp' => reset($from->privateIp)]];
        }
    }

    protected function _launchedBy($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Server */
                $to->launchedBy = ['id' => $from->properties[Server::LAUNCHED_BY_ID]];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Server */
                $launchedBy = ApiController::getBareId($from, 'launchedBy');

                if (!isset($launchedBy)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed launchedBy.id property");
                }

                $to->properties[Server::LAUNCHED_BY_ID] = $launchedBy;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $entity = new Server();

                return $entity->getSettingCriteria(Server::LAUNCHED_BY_ID, ApiController::getBareId($from, 'launchedBy'));
        }
    }

}