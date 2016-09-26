<?php

namespace Scalr\Api\DataType;

use DomainException;
use Exception;
use Scalr\Model\Collections\EntityIterator;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Objects\BaseAdapter;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Model\Loader\Field;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\AbstractEntity;
use UnexpectedValueException;

/**
 * ApiEntityAdapter
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (02.03.2015)
 *
 * @method  EntityIterator getInnerIterator()
 *          getInnerIterator()
 *          Gets results of the query
 */
class ApiEntityAdapter extends BaseAdapter
{
    /**
     * Defines the list of the filterable fields
     *
     * IMPORTANT: The property name is not actually the name of the Entity's property
     * but visible field name which API will return in the response. These fields are specified
     * in the [self::RULE_TYPE_TO_DATA] offset of the array
     */
    const RULE_TYPE_FILTERABLE = 'filterable';

    /**
     * Defines the list of the properties which are changeable
     *
     * IMPORTANT: The property name is not actially the Entity's property but visible
     * field name that API returns in the response. These rules are specified
     * in the [self::RULE_TYPE_TO_DATA] offset of the array
     */
    const RULE_TYPE_ALTERABLE = 'alterable';

    /**
     * Defines sorting rules
     *
     * Real Entity fields here
     */
    const RULE_TYPE_SORTING = 'sorting';

    /**
     * Default rule set
     */
    const RULE_TYPE_PROP_DEFAULT = 'default';

    /**
     * Rules of conversion entity settings into data fields
     */
    const RULE_TYPE_SETTINGS = 'settings';

    /**
     * Entity property provides settings
     */
    const RULE_TYPE_SETTINGS_PROPERTY = 'settings.property';

    /**
     * Controller instance
     *
     * @var ApiController
     */
    protected $controller;

    /**
     * Constructor
     *
     * @param  ApiController $controller The controller instance
     */
    public function __construct(ApiController $controller)
    {
        $this->controller = $controller;
    }

    /**
     * Copies all alterable properties from the request object to Entity
     *
     * It does not validate the values. It only checks whether the request
     * contains only alterable properties. If not it will raise ApiErrorExceptions
     *
     * @param    object         $object An object (source)
     * @param    AbstractEntity $entity An Entity (destination)
     * @throws ApiErrorException
     * @throws Exception
     */
    public function copyAlterableProperties($object, AbstractEntity $entity)
    {
        $rules = $this->getRules();

        if (!isset($rules[static::RULE_TYPE_ALTERABLE])) {
            //Nothing to copy
            throw new \Exception(sprintf(
                "ApiEntityAdapter::RULE_TYPE_ALTERABLE offset of rules has not been defined for the %s class.",
                get_class($this)
            ));
        }

        $it = $entity->getIterator();

        $notAlterable = array_diff(array_keys(get_object_vars($object)), $rules[static::RULE_TYPE_ALTERABLE]);

        if (!empty($notAlterable)) {
            if (count($notAlterable) > 1) {
                $message = "You are trying to set properties %s that either are not alterable or do not exist";
            } else {
                $message = "You are trying to set the property %s which either is not alterable or does not exist";
            }

            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, sprintf($message, implode(', ', $notAlterable)));
        }

        $settingsRules = null;
        $collection = null;

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
                            $this->{$property}($object, $entity, self::ACT_CONVERT_TO_ENTITY);
                            continue;
                        }
                    } else {
                        $property = $key;
                    }
                }
            }

            //As the name of the property that goes into response may be different from the
            //real setting name in the Entity object it should be mapped at first
            if (empty($property) && !empty($rules[static::RULE_TYPE_SETTINGS])) {
                if (!isset($settingsRules)) {
                    $settingsRules = $this->getSettingsRules();
                }

                if (($property = array_search($key, $settingsRules)) !== false) {
                    if (!isset($collection)) {
                        $collection = $this->getSettingsCollection($entity);
                    }

                    if (!is_string($property)) {
                        $property = $key;
                    }

                    $collection[$property] = $object->$key;
                    continue;
                }
            }

            $property = isset($property) ? $property : $key;

            $entity->$property = $object->$key === null ? null : static::convertInputValue($it->getField($property)->column->type, $object->$key, $key);

        }
    }

    /**
     * Adjusts search criteria according to RULE_TYPE_FILTERABLE rules and Request
     *
     * @param    array $criteria optional Default search criteria
     * @return   array|null  Returns adjusted criteria
     * @throws ApiErrorException
     */
    public function getCriteria($criteria = null)
    {
        $rules = $this->getRules();

        if (!empty($rules[static::RULE_TYPE_FILTERABLE])) {
            if (!is_array($rules[static::RULE_TYPE_FILTERABLE]) && !($rules[static::RULE_TYPE_FILTERABLE] instanceof \ArrayAccess)) {
                throw new \InvalidArgumentException(sprintf(
                    "[%s::RULE_TYPE_FILTERABLE] offset of the rules is expected to be an Array",
                    get_class($this)
                ));
            }

            /* @var $entity AbstractEntity */
            $entityClass = $this->getEntityClass();
            $entity = new $entityClass;
            $it = $entity->getIterator();

            //Search criteria
            $criteria = $criteria ?: [];

            $settingsRules = null;

            foreach ($rules[static::RULE_TYPE_FILTERABLE] as $property) {
                $key = null;
                //Gets value from the request
                $filterValue = $this->controller->params($property);
                if ($filterValue === null) {
                    continue;
                }

                //As the name of the property that goes into response may be different from the
                //real property name in the Entity object it should be mapped at first
                if (!empty($rules[static::RULE_TYPE_TO_DATA])) {
                    //if toData rule is null it means all properties are allowed
                    if (($key = array_search($property, $rules[static::RULE_TYPE_TO_DATA])) !== false) {
                        if (is_string($key)) {
                            //In this case the real name of the property is the key of the array
                            if ($key[0] === '_' && method_exists($this, $key)) {
                                //It is callable
                                $from = (object)[$property => $filterValue];
                                $addCriteria = $this->$key($from, null, self::ACT_GET_FILTER_CRITERIA);
                                if (!empty($addCriteria)) {
                                    if (isset($addCriteria[AbstractEntity::STMT_FROM])) {
                                        if (!isset($criteria[AbstractEntity::STMT_FROM])) {
                                            $criteria[AbstractEntity::STMT_FROM] = $entity->table();
                                        }
                                        $criteria[AbstractEntity::STMT_FROM] .= " " . $addCriteria[AbstractEntity::STMT_FROM];
                                    }

                                    if (isset($addCriteria[AbstractEntity::STMT_WHERE])) {
                                        if (!empty($criteria[AbstractEntity::STMT_WHERE])) {
                                            $criteria[AbstractEntity::STMT_WHERE] .= " AND (" . $addCriteria[AbstractEntity::STMT_WHERE] . ")";
                                        } else {
                                            $criteria[AbstractEntity::STMT_WHERE] = $addCriteria[AbstractEntity::STMT_WHERE];
                                        }
                                    }
                                    //Latter value should not overwrite the previous
                                    $criteria = array_merge($addCriteria, $criteria);
                                }
                                continue;
                            }

                            $property = $key;
                        }
                    }
                }

                if (empty($key) && !empty($rules[static::RULE_TYPE_SETTINGS]) && method_exists($entity, 'getSettingCriteria')) {
                    if (!isset($settingsRules)) {
                        $settingsRules = $this->getSettingsRules();
                    }

                    if (($key = array_search($property, $settingsRules)) !== false) {
                        if (is_object($filterValue) || is_array($filterValue)) {
                            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Filter value must be a string");
                        }

                        if (empty($criteria[AbstractEntity::STMT_FROM])) {
                            $criteria[AbstractEntity::STMT_FROM] = " {$entity->table()} ";
                        }

                        $criteria = $entity->getSettingCriteria($key, $filterValue, $criteria);
                        continue;
                    }
                }

                //Fetches the definition of the field from the Entity model
                $field = $it->getField($property);

                if (!($field instanceof Field)) {
                    throw new \InvalidArgumentException(sprintf(
                        "Invalid value is in the [%s::RULE_TYPE_FILTERABLE] offset of the rules. "
                      . "Property '%s' is not defined in the %s entity.",
                        get_class($this), $property, get_class($entity)
                    ));
                }

                //Different column type values should be converted
                $criteria[] = [$field->name => static::convertInputValue($field->column->type, $filterValue, $property)];
            }
        }

        //We should make sure users do not send requests with unavailable filters.
        $notProcessed = array_diff(
            array_keys($this->controller->request->get()),
            array_keys($this->controller->getCommonQueryParams()),
            !empty($rules[static::RULE_TYPE_FILTERABLE]) ? $rules[static::RULE_TYPE_FILTERABLE] : array_values($rules[static::RULE_TYPE_TO_DATA])
        );

        if (!empty($notProcessed)) {
            //It means user sent request to filter on not filterable property.
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, sprintf("Unsupported filter. Fields which are available for filtering: [%s]", join(', ', $rules[static::RULE_TYPE_FILTERABLE])));
        }

        return $criteria;
    }

    /**
     * {@inheritdoc}
     * @see BaseAdapter::convertInputValue()
     *
     * @throws   ApiErrorException
     */
    public static function convertInputValue($fieldType, $value, $fieldName = '')
    {
        try {
            return parent::convertInputValue($fieldType, $value, $fieldName);
        } catch (UnexpectedValueException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }
    }

    /**
     * Converts output value
     *
     * @param    string    $fieldType  Field type
     * @param    string    $value      A value taken from input
     * @throws   ApiErrorException
     * @return   mixed     Returns value which can be used in the response
     */
    public static function convertOutputValue($fieldType, $value)
    {
        switch ($fieldType) {
            case 'boolean':
                $result = (bool)$value;
                break;

            case 'UTCDatetime':
            case 'UTCDate':
                $result = is_null($value) ? null : $value->format('Y-m-d\TH:i:s\Z');
                break;

            case 'date':
            case 'datetime':
                if (is_null($value)) {
                    $result = null;
                } else {
                    if (date_default_timezone_get() !== 'UTC') {
                        $value->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                    }
                    $result = $value->format('Y-m-d\TH:i:s\Z');
                }
                break;

            default:
                $result = $value;
        }

        return $result;
    }

    /**
     * Gets sorting option for the find method of the Entity
     *
     * It processes HTTP Request and takes RULE_TYPE_SORTING of rules into account
     *
     * @return array|null Returns sorting option for the find method of the Entity
     * @throws \InvalidArgumentException
     */
    public function getSorting()
    {
        $sorting = null;

        $rules = $this->getRules();

        if (!empty($rules[static::RULE_TYPE_SORTING][static::RULE_TYPE_PROP_DEFAULT])) {
            if (!is_array($rules[static::RULE_TYPE_SORTING][static::RULE_TYPE_PROP_DEFAULT])) {
                throw new \InvalidArgumentException(sprintf(
                    "[%s::RULE_TYPE_SORTING]['%s'] offset of the rules is expected to be an array",
                    get_class($this), static::RULE_TYPE_PROP_DEFAULT
                ));
            }

            $sorting = $rules[static::RULE_TYPE_SORTING][static::RULE_TYPE_PROP_DEFAULT];
        }

        return $sorting;
    }

    /**
     * Fetches records according to rules set
     *
     * @param  array    $criteria     optional Default search criteria
     * @param  callable $findCallback optional Find method. Default value: find
     * @return ApiEntityAdapter Returns current instance that actually is iterator of the found records
     */
    public function find($criteria = null, $findCallback = null)
    {
        if ($findCallback !== null) {
            if (!is_callable($findCallback)) {
                throw new \InvalidArgumentException(sprintf("Second argument is expected to be Callable"));
            }
        } else {
            $findCallback = [$this->getEntityClass(), 'find'];
        }

        $criteria = $this->getCriteria($criteria);

        $this->setInnerIterator($findCallback(empty($criteria) ? null : $criteria, null, $this->getSorting(), $this->controller->getMaxResults(), $this->controller->getPageOffset(), true));

        return $this;
    }

    /**
     * Gets describe result
     *
     * @param   array    $criteria      Default search criteria
     * @param   callable $findCallback  optional Find method. Default value: find
     * @return  ListResultEnvelope      Returns describe result
     */
    public function getDescribeResult($criteria = null, $findCallback = null)
    {
        $data = [];

        foreach ($this->find($criteria, $findCallback) as $item) {
            $data[] = $item;
        }

        return $this->controller->resultList($data, $this->getInnerIterator()->totalNumber);
    }

    /**
     * Validates specified string
     *
     * @param   string $string   A string
     * @param   string $message  optional A error message
     * @throws  ApiErrorException
     */
    public function validateString($string, $message = 'Invalid string')
    {
        if (!is_string($string) || $string != strip_tags($string)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $message);
        }
    }

    /**
     * Validates entity
     *
     * It is applicable only to either POST or PATCH methods.
     * It should be used on GET methods.
     *
     * @param   AbstractEntity   $entity  An Entity
     * @throws  ApiErrorException
     */
    public function validateEntity($entity)
    {
        //This method is expected to be overriden
    }

    /**
     * Validates object
     *
     * It is applicable only to either POST or PATCH methods.
     * It should be used on GET methods.
     *
     * @param   object   $object  An object provided with the request
     * @param   string   $method  optional HTTP METHOD
     * @throws  ApiErrorException
     */
    public function validateObject($object, $method = null)
    {
        if (!is_object($object)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');
        }

        $rules = $this->getRules();

        if (!empty($rules[static::RULE_TYPE_TO_DATA])) {
            $validFields = $rules[static::RULE_TYPE_TO_DATA];
        } else {
            //All fields from the Entity are allowed to be in the data object
            $entityClass = $this->getEntityClass();
            $entity = new $entityClass;
            $validFields = [];
            foreach ($entity->getIterator()->fields() as $field) {
                /* @var $field \Scalr\Model\Loader\Field */
                $validFields[$field->name] = $field->name;
            }
        }

        $objectVars = get_object_vars($object);
        $doesNotExist = array_diff(array_keys($objectVars), $validFields);

        if (!empty($rules[static::RULE_TYPE_SETTINGS])) {
            $doesNotExist = array_diff($doesNotExist, array_values($this->getSettingsRules()));
        }

        if (!empty($doesNotExist)) {
            if (count($doesNotExist) > 1) {
                $message = "You are trying to set properties %s that do not exist.";
            } else {
                $message = "You are trying to set the property %s which does not exist.";
            }

            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, sprintf($message, implode(', ', $doesNotExist)));
        }

        foreach ($objectVars as $property => $val) {
            if (is_string($val)) {
                if (($key = array_search($property, $validFields)) && $key[0] === '_' && method_exists($this, $key)) {
                    //It is callable
                    continue;
                } elseif ($val != strip_tags($val)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf("Property %s contains invalid characters.", $property));
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see BaseAdapter::toEntity()
     */
    public function toEntity($data)
    {
        $entity = parent::toEntity($data);

        $converterRules = $this->getRules();

        if (!is_object($data)) {
            $data = (object) $data;
        }

        if (!empty($converterRules[static::RULE_TYPE_SETTINGS])) {
            $collection = $this->getSettingsCollection($entity);

            foreach ($this->getSettingsRules() as $key => $property) {
                if (isset($data->$property)) {
                    $collection[is_int($key) ? $property : $key] = $data->$property;
                }
            }
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     * @see BaseAdapter::toData()
     */
    public function toData($entity)
    {
        $result = parent::toData($entity);

        $converterRules = $this->getRules();

        if (!empty($converterRules[static::RULE_TYPE_SETTINGS])) {
            $collection = $this->getSettingsCollection($entity);

            foreach ($converterRules[static::RULE_TYPE_SETTINGS] as $key => $property) {
                //Some properties only for writing we can't include them in the response
                if ($property[0] == '!') {
                    continue;
                }

                //This is necessary when result data key does not match the property name of the entity
                $key = is_int($key) ? $property : $key;

                $result->$property = $collection[$key];
            }
        }

        return $result;
    }

    /**
     * Gets settings collection from entity.
     *
     * @param   AbstractEntity  $entity Entity containing collection of settings
     *
     * @return  SettingsCollection
     *
     * @throws DomainException if RULE_TYPE_SETTINGS_PROPERTY not defined in rules
     */
    public function getSettingsCollection(AbstractEntity $entity)
    {
        if (empty($this->rules[static::RULE_TYPE_SETTINGS_PROPERTY])) {
            throw new DomainException('Rule RULE_TYPE_SETTINGS_PROPERTY must be defined!');
        }

        $collectionProperty = $this->rules[static::RULE_TYPE_SETTINGS_PROPERTY];

        return $entity->$collectionProperty;
    }

    /**
     * Gets settings to data rules
     *
     * @return array
     */
    public function getSettingsRules()
    {
        return array_map(function ($field) {
            return $field[0] == '!' ? substr($field, 1) : $field;
        }, $this->rules[static::RULE_TYPE_SETTINGS]);
    }
}
