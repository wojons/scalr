<?php

namespace Scalr\Api\Service\Account\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Account\Team;
use Scalr\Model\Entity\Account\TeamEnvs;
use InvalidArgumentException;

/**
 * EnvironmentTeamAdapter v1beta0
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.18 (07.03.2016)
 *
 * @method  TeamEnvs  toEntity($data) Converts data to entity
 */
class EnvironmentTeamAdapter extends ApiEntityAdapter
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
            '_team' => 'team',
            '_defaultAclRole' => 'defaultAclRole'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE => ['team'],

        self::RULE_TYPE_FILTERABLE => ['team', 'defaultAclRole'],
        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['teamId' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\Account\TeamEnvs';

    public function _team($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from TeamEnvs */
                $to->team = ['id' => $from->teamId];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to TeamEnvs */
                $to->teamId = ApiController::getBareId($from, 'team');
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['teamId' => ApiController::getBareId($from, 'team')]];

        }
    }

    public function _defaultAclRole($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from TeamEnvs */
                $to->defaultAclRole = ['id' => $from->getTeam()->accountRoleId];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to TeamEnvs */
                // now its only Team property
                throw new ApiNotImplementedErrorException(
                    'Adjustment the default ACL Role for the Environment has not been implemented yet.'
                );

            case static::ACT_GET_FILTER_CRITERIA:
                $aclRoleId = ApiController::getBareId($from, 'defaultAclRole');
                $team = new Team();
                $envTeam = new TeamEnvs();
                return [
                    AbstractEntity::STMT_FROM => "
                        JOIN {$team->table('t')} ON {$team->columnId('t')} = {$envTeam->columnTeamId()}
                            AND {$team->columnAccountRoleId('t')} = " . $team->qstr('accountRoleId', $aclRoleId) . "
                    "
                ];
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof TeamEnvs) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of %s", Team::class
            ));
        }

        if (empty($entity->teamId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property team.id");
        }

        $teamId = $entity->teamId;

        if (!is_numeric($teamId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid team identifier");
        }

        /* @var $team Team */
        $team = Team::findOne([['id' => $teamId], ['accountId' => $this->controller->getUser()->accountId]]);
        if (empty($team)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf("Requested team %s do not exist in this account", $teamId));
        }

        if (!empty(TeamEnvs::findOne([['envId' => $entity->envId], ['teamId' => $teamId]]))) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf("Team %s already exists in this environment", $teamId));
        }

        /* @var  $teamAdapter TeamAdapter */
        $teamAdapter = $this->controller->adapter('team');
        $teamAdapter->validateTeamName($team->name);
    }
}