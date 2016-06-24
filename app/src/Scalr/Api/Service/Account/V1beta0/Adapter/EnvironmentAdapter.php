<?php

namespace Scalr\Api\Service\Account\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\Account\V1beta0\Controller\Environments;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Model\Entity\Account\EnvironmentProperty;
use Scalr\Model\Entity\Account;

/**
 * Environment Adapter v1beta0
 *
 * @author N.V.
 *
 * @property    Environments    $controller Environments controller
 *
 * @method  Environment toEntity($data) Converts data to entity
 */
class EnvironmentAdapter extends ApiEntityAdapter
{

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'id', 'name', 'added',
            '_status' => 'status', '_costCenter' => 'costCenter'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'status'],

        self::RULE_TYPE_FILTERABLE  => ['id', 'name', 'status', 'added', 'costCenter'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\Account\Environment';

    public function _status($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Environment */
                $to->status = lcfirst($from->status);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Environment */
                static::validateStatus($from->status);

                $to->status = ucfirst($from->status);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                static::validateStatus($from->status);

                return [['status' => ucfirst($from->status)]];
        }
    }

    public function _costCenter($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Environment */
                $to->costCenter = $from->getProperty(EnvironmentProperty::SETTING_CC_ID);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Environment */
                $ccId = ApiController::getBareId($from, 'costCenter');

                if (empty($ccId)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Missed property costCenter");
                }

                $this->controller->getCostCenter($ccId);

                $to->setProperty(EnvironmentProperty::SETTING_CC_ID, $ccId);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $env = new Environment();
                $envProperty = new EnvironmentProperty();

                return [
                    AbstractEntity::STMT_FROM  => "
                         JOIN {$envProperty->table('cep')} ON {$env->columnId()} = {$envProperty->columnEnvId('cep')}
                            AND {$envProperty->columnName('cep')} = " . $envProperty->qstr('name', EnvironmentProperty::SETTING_CC_ID) . "
                    ",
                    AbstractEntity::STMT_WHERE => "{$envProperty->columnValue('cep')} = " . $envProperty->qstr('value', $from->costCenter)
                ];

        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        /* @var $entity Environment */
        if (empty($entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property name");
        }

        if (Environment::findOne([['accountId' => $entity->accountId], ['name' => $entity->name], ['id' => ['$ne' => $entity->id]]])) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, "Environment with name '{$entity->name}' already exists in this account");
        }

        if (empty($entity->getProperty(EnvironmentProperty::SETTING_CC_ID))) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property costCenter");
        }
    }

    /**
     * Validates environment status
     *
     * @param   string  $status The status name
     *
     * @throws ApiErrorException
     */
    public static function validateStatus($status)
    {
        if (!in_array(ucfirst($status), [Environment::STATUS_ACTIVE, Environment::STATUS_INACTIVE])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected environment status");
        }
    }
}
