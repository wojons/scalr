<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Exception;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Controller\GlobalVariableTrait;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\GlobalVariable;

/**
 * Global Variable Adapter
 *
 * @author N.V.
 */
class GlobalVariableAdapter extends ApiEntityAdapter
{
    use GlobalVariableTrait;

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => ['name', 'value', 'category', 'description',
            'format'     => 'outputFormat',
            'validator'  => 'validationPattern',
            'required'   => 'requiredIn',
            'hidden'     => 'hidden',
            'final'      => 'locked',
            'scope'      => 'declaredIn'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['value', 'category', 'outputFormat', 'validationPattern', 'hidden', 'requiredIn', 'locked', 'description'],

        self::RULE_TYPE_FILTERABLE  => [],
        self::RULE_TYPE_SORTING     => [],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = GlobalVariable::class;

    /**
     * Scopes priority
     *
     * @var int[]
     */
    protected $scopesPriority = [
        ScopeInterface::SCOPE_SCALR => 0,
        ScopeInterface::SCOPE_ACCOUNT => 1,
        ScopeInterface::SCOPE_ENVIRONMENT => 2,
        ScopeInterface::SCOPE_ROLE => 3,
        ScopeInterface::SCOPE_FARM => 3,
        ScopeInterface::SCOPE_FARMROLE => 4,
        ScopeInterface::SCOPE_SERVER => 5
    ];

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::copyAlterableProperties()
     */
    public function copyAlterableProperties($object, AbstractEntity $entity, $scope = ScopeInterface::SCOPE_SCALR)
    {
        /* @var $entity GlobalVariable */
        $rules = $this->getRules();

        if (!isset($rules[static::RULE_TYPE_ALTERABLE])) {
            //Nothing to copy
            throw new Exception(sprintf(
                "ApiEntityAdapter::RULE_TYPE_ALTERABLE offset of rules has not been defined for the %s class.",
                get_class($this)
            ));
        }

        $notAlterable = array_diff(array_keys(get_object_vars($object)), $rules[static::RULE_TYPE_ALTERABLE]);

        if (!empty($notAlterable)) {
            if (count($notAlterable) > 1) {
                $message = "You are trying to set properties %s that either are not alterable or do not exist";
            } else {
                $message = "You are trying to set the property %s which either is not alterable or does not exist";
            }

            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, sprintf($message, implode(', ', $notAlterable)));
        }

        $allowOnlyValue = $this->scopesPriority[$entity->getScope()] < $this->scopesPriority[$scope];

        if ($entity->final && $allowOnlyValue) {
            throw new ApiErrorException(
                403,
                ErrorMessage::ERR_SCOPE_VIOLATION,
                "You can't change final variable locked on {$entity->getScope()} level"
            );
        }

        foreach ($rules[static::RULE_TYPE_ALTERABLE] as $key) {
            if (!property_exists($object, $key)) {
                continue;
            }

            //As the name of the property that goes into response may be different from the
            //real property name in the Entity object it should be mapped at first
            if (!empty($rules[static::RULE_TYPE_TO_DATA])) {
                //if toData rule is null it means all properties are allowed
                if (($property = array_search($key, $rules[static::RULE_TYPE_TO_DATA])) !== false) {
                    if (is_string($property)) {
                        //In this case the real name of the property is the key of the array
                        if ($property[0] === '_' && method_exists($this, $property)) {
                            //It is callable
                            continue;
                        }
                    } else {
                        $property = $key;
                    }

                    if ($allowOnlyValue && $property != 'value' && isset($object->{$key}) && $object->{$key} != $entity->{$property}) {
                        throw new ApiErrorException(
                            403,
                            ErrorMessage::ERR_SCOPE_VIOLATION,
                            sprintf("This variable was declared in the %s Scope, you can only modify its 'value' field in the Farm Role Scope", ucfirst($entity->getScope()))
                        );
                    }
                }
            }
        }
    }

    /**
     * Convert variable model data to api response format
     *
     * @param array $variable
     * @return array
     */
    public function convertData(array $variable)
    {
        $variable = $this->getCleanVarDefinition($variable);

        return [
            'declaredIn'        => $this->getActualVariableScope($variable),
            'hidden'            => $this->getActualValue('flagHidden', $variable, null, null, false),
            'locked'            => $this->getActualValue('flagFinal', $variable, null, null, false),
            'requiredIn'        => $this->getActualValue('flagRequired', $variable, null, null, false),
            'name'              => $variable['name'],
            'value'             => !empty($variable['current']['value']) ? $variable['current']['value'] : null,
            'category'          => !empty($variable['current']['category']) ? $variable['current']['category'] : null,
            'outputFormat'      => $this->getActualValue('format', $variable),
            'validationPattern' => $this->getActualValue('validator', $variable),
            'description'       => $this->getActualValue('description', $variable),
            'computedValue'     => $this->getActualValue('value', $variable),
            'computedCategory'  => $this->getActualValue('category', $variable),
        ];
    }
}