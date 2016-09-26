<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use InvalidArgumentException;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\AbstractEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;

/**
 * Project Adapter v1beta0
 *
 * @author N.V.
 *
 * @method  ProjectEntity toEntity($data) Converts data to entity
 */
class ProjectAdapter extends ApiEntityAdapter
{

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA         => [
            'projectId' => 'id', 'name',

            '_costCenter' => 'costCenter',
            '_billingCode' => 'billingCode',
            '_leadEmail' => 'leadEmail',
            '_description' => 'description'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE       => ['name', 'description'],

        self::RULE_TYPE_FILTERABLE      => ['id', 'name', 'costCenter', 'billingCode'],
        self::RULE_TYPE_SORTING         => [self::RULE_TYPE_PROP_DEFAULT => ['created' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Stats\CostAnalytics\Entity\ProjectEntity';

    public function _costCenter($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from ProjectEntity */
                $to->costCenter = [
                    'id' => $from->ccId
                ];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to ProjectEntity */
                $to->ccId = ApiController::getBareId($from, 'costCenter');
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ 'ccId' => ApiController::getBareId($from, 'costCenter') ]];
        }
    }

    public function _billingCode($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from ProjectEntity */
                $to->billingCode = $from->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to ProjectEntity */
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $project = new ProjectEntity();
                $property = new ProjectPropertyEntity();

                return [
                    AbstractEntity::STMT_FROM => "
                        JOIN  {$property->table()}  ON {$property->columnProjectId} = {$project->columnProjectId}
                            AND  {$property->columnName} = " . $property->qstr('name', ProjectPropertyEntity::NAME_BILLING_CODE) . "
                    ",
                    AbstractEntity::STMT_WHERE => " {$property->columnValue} = " . $property->qstr('value', $from->billingCode)
                ];
        }
    }

    public function _leadEmail($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from ProjectEntity */
                $to->leadEmail = $from->getProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to ProjectEntity */
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[  ]];
        }
    }

    public function _description($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from ProjectEntity */
                $to->description = $from->getProperty(ProjectPropertyEntity::NAME_DESCRIPTION);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to ProjectEntity */
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[  ]];
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\DataType\ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof ProjectEntity) {
            throw new InvalidArgumentException(
                "First argument must be instance of Scalr\\Stats\\CostAnalytics\\Entity\\ProjectEntity class"
            );
        }

        $cc = \Scalr::getContainer()->analytics->ccs->get($entity->ccId);

        if (empty($cc)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Cost center with ID '{$entity->ccId}' not found");
        }

        if (empty($entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Project name can't be empty");
        }
    }
}