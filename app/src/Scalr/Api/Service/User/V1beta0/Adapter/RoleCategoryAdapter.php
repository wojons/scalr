<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Model\Entity\RoleCategory;

/**
 * RoleCategoryAdapter V1
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (18.03.2015)
 */
class RoleCategoryAdapter extends ApiEntityAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data restul object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'id', 'name', '_scope' => 'scope'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name'],

        self::RULE_TYPE_FILTERABLE  => ['id', 'name', 'scope'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Model\Entity\RoleCategory';

    public function _scope($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from RoleCategory */
                $to->scope = $from->getScope();
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                switch ($from->scope) {
                    case ScopeInterface::SCOPE_SCALR:
                        break;

                    case ScopeInterface::SCOPE_ENVIRONMENT:
                        $to->envId = $this->controller->getEnvironment()->id;
                        $to->accountId = $this->controller->getUser()->getAccountId();
                        break;

                    case ScopeInterface::SCOPE_ACCOUNT:
                        $to->accountId = $this->controller->getUser()->getAccountId();
                        break;

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected scope value');
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                if (empty($from->scope)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope value");
                }

                if ($from->scope === ScopeInterface::SCOPE_ENVIRONMENT && $this->controller->getEnvironment() === null) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected scope value');
                }

                return $this->controller->getScopeCriteria($from->scope, true);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\DataType\ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!($entity instanceof RoleCategory)) {
            throw new \InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\RoleCategory class"
            ));
        }

        if (!preg_match('/^' . RoleCategory::NAME_REGEXP . '$/', $entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE,
                'Invalid name of the Role Category. Name should start and end with letter or number and contain only letters, numbers, spaces and dashes.'
            );
        }

        if (strlen($entity->name) > RoleCategory::NAME_LENGTH) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Name should be less than 18 characters');
        }

        if (is_null($entity->id)) {
            $criteria = $this->controller->getScopeCriteria($entity->getScope());
            $criteria[] = ['name' => $entity->name];
            if (!empty(RoleCategory::findOne($criteria))) {
                throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf(
                    'Role Category with name %s already exists', $entity->name
                ));
            }
        } else if (empty(RoleCategory::findPk($entity->id))) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                "Could not find out the Role Category with id: %d", $entity->id
            ));
        }
    }
}