<?php


namespace Scalr\Api\Service\User\V1beta0\Adapter;

use InvalidArgumentException;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Script;

/**
 * ScriptAdapter V1beta0
 *
 * @author N.V.
 *
 * @method  Script toEntity($data) Converts data to entity
 */
class ScriptAdapter extends ApiEntityAdapter
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
            'id', 'name', 'description',
            'os' => 'osType', 'isSync' => 'blockingDefault', 'timeout' => 'timeoutDefault',
            'dtCreated' => 'added', 'dtChanged' => 'lastChanged', '_scope' => 'scope'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'timeoutDefault', 'blockingDefault', 'scope'],

        self::RULE_TYPE_FILTERABLE  => ['id', 'name', 'osType', 'blockingDefault', 'scope'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\Script';

    public function _scope($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Script */
                $to->scope = $from->getScope();
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Script */
                switch ($from->scope) {
                    case ScopeInterface::SCOPE_SCALR:
                        break;

                    case ScopeInterface::SCOPE_ENVIRONMENT:
                        $to->envId = $this->controller->getEnvironment()->id;
                        break;

                    case ScopeInterface::SCOPE_ACCOUNT:
                        $to->accountId = $this->controller->getUser()->getAccountId();
                        break;

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                if (empty($from->scope)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed scope value");
                }

                switch ($from->scope) {
                    case ScopeInterface::SCOPE_SCALR:
                        return [['accountId' => null], ['envId' => null]];

                    case ScopeInterface::SCOPE_ENVIRONMENT:
                        return [['envId' => $this->controller->getEnvironment()->id]];

                    case ScopeInterface::SCOPE_ACCOUNT:
                        return [['accountId' => $this->controller->getUser()->getAccountId()], ['envId' => null]];

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
                }
                break;
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\DataType\ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof Script) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\Script class"
            ));
        }

        if ($entity->id !== null) {
            if (!Script::findPk($entity->id)) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                    "Could not find out the Script with ID: %d", $entity->id
                ));
            }
        }

        if ($entity->getScope() == ScopeInterface::SCOPE_SCALR ) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, sprintf(
                "Scalr scope is not allowed"
            ));
        }

        if (!$this->controller->hasPermissions($entity, true)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }
    }
}