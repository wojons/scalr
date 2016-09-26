<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use InvalidArgumentException;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity;
use stdClass;

/**
 * ScalingMetricAdapter v1beta0
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.9 (05.02.2016)
 */
class ScalingMetricAdapter extends ApiEntityAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data restful object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'isInvert' => 'inverted',
            '_filePath' => 'filePath',
            '_name' => 'name',
            '_scope' => 'scope',
            '_function' => 'function',
            '_retrieveMethod' => 'retrieveMethod'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE       => ['name', 'filePath', 'retrieveMethod', 'function', 'inverted'],
        self::RULE_TYPE_FILTERABLE      => ['name', 'retrieveMethod', 'function', 'inverted', 'scope'],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['name' => true]]
    ];

    /**
     * Scaling rule names
     */
    const FREE_RAM_NAME          = 'FreeRam';
    const BANDWIDTH_NAME         = 'BandWidth';
    const LOAD_AVERAGES_NAME     = 'LoadAverages';
    const DATE_AND_TIME_NAME     = 'DateAndTime';
    const SQS_QUEUE_SIZE_NAME    = 'SQSQueueSize';
    const URL_RESPONSE_TIME_NAME = 'URLResponseTime';

    /**
     * The list of allowed functions
     *
     * @var array
     */
    protected static $functionMap = [
        Entity\ScalingMetric::CALC_FUNCTION_AVERAGE => 'average',
        Entity\ScalingMetric::CALC_FUNCTION_MAXIMUM => Entity\ScalingMetric::CALC_FUNCTION_MAXIMUM,
        Entity\ScalingMetric::CALC_FUNCTION_MINIMUM => Entity\ScalingMetric::CALC_FUNCTION_MINIMUM,
        Entity\ScalingMetric::CALC_FUNCTION_SUM => Entity\ScalingMetric::CALC_FUNCTION_SUM
    ];

    /**
     * Scaling metric name mapping
     *
     * @var array
     */
    public static $nameMap = [
         self::FREE_RAM_NAME      => self::FREE_RAM_NAME,
         self::BANDWIDTH_NAME     => self::BANDWIDTH_NAME,
         self::LOAD_AVERAGES_NAME => self::LOAD_AVERAGES_NAME,
         self::DATE_AND_TIME_NAME => self::DATE_AND_TIME_NAME,
         'SqsQueueSize'           => self::SQS_QUEUE_SIZE_NAME,
         'UrlResponseTime'        => self::URL_RESPONSE_TIME_NAME
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\ScalingMetric';

    public function _filePath($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\ScalingMetric */
                if (!empty($from->filePath)) {
                    $to->filePath = $from->filePath;
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\ScalingMetric */
                $this->validateString($from->filePath, 'Property filePath contains invalid characters');
                $to->filePath = $from->filePath;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[]];
        }
    }

    public function _name($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\ScalingMetric */
                $to->name = static::metricNameToData($from->name);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\ScalingMetric */
                $to->name = static::metricNameToEntity($from->name);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['name' => static::metricNameToEntity($from->name)]];
        }
    }

    public function _scope($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\ScalingMetric */
                $to->scope = $from->getScope();
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\ScalingMetric */
                switch ($from->scope) {
                    case ScopeInterface::SCOPE_SCALR:
                        break;

                    case ScopeInterface::SCOPE_ENVIRONMENT:
                        $to->envId = $this->controller->getEnvironment()->id;
                        $to->accountId = $this->controller->getUser()->getAccountId();
                        break;

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected scope value');
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                if (empty($from->scope)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed scope value');
                }

                return $this->controller->getScopeCriteria($from->scope, true);
        }
    }

    public function _function($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\ScalingMetric */
                if (!empty($from->calcFunction)) {
                    $to->function = static::$functionMap[$from->calcFunction];
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\ScalingMetric */
                $to->calcFunction = $this->functionToEntity($from);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['calcFunction' => $this->functionToEntity($from)]];
        }
    }

    public function _retrieveMethod($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\ScalingMetric */
                if (!empty($from->retrieveMethod)) {
                    $to->retrieveMethod = $from->retrieveMethod;
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\ScalingMetric */
                $to->retrieveMethod = $from->retrieveMethod;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['retrieveMethod' => $from->retrieveMethod]];
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof Entity\ScalingMetric) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\ScalingMetric class"
            ));
        }

        if (empty($entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property name');
        }
        if (!preg_match('/^' . Entity\ScalingMetric::NAME_REGEXP . '$/', $entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Scaling metric name should be both alphanumeric and greater than 5 chars');
        }
        $criteria = $this->controller->getScopeCriteria();
        $criteria[] = ['name' => $entity->name];
        $criteria[] = ['id' => ['$ne' => $entity->id]];
        if (!empty(Entity\ScalingMetric::findOne($criteria))) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf(
                'Scaling metric with name %s already exists', $entity->name
            ));
        }

        if (empty($entity->retrieveMethod)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property retrieveMethod');
        }
        if (!in_array($entity->retrieveMethod, [Entity\ScalingMetric::RETRIEVE_METHOD_EXECUTE, Entity\ScalingMetric::RETRIEVE_METHOD_READ, Entity\ScalingMetric::RETRIEVE_METHOD_URL_REQUEST])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected retrieveMethod value');
        }

        $uriParts = parse_url($entity->filePath);
        if (!$uriParts) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Property filePath is invalid');
        }

        if ($entity->retrieveMethod == Entity\ScalingMetric::RETRIEVE_METHOD_URL_REQUEST &&
            (empty($uriParts['scheme']) || !in_array($uriParts['scheme'], ['http', 'https']))) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid path for request.');
        }

        if (empty($entity->calcFunction)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property function');
        }

        if (empty($entity->filePath)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property filePath');
        }
    }

    /**
     * Get function name from map
     *
     * @param stdClass $object
     * @return string
     * @throws ApiErrorException
     */
    public function functionToEntity($object)
    {
        $functions = array_flip(static::$functionMap);
        if (!isset($functions[$object->function])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected function value');
        }
        return $functions[$object->function];
    }

    /**
     * Get metrics name used in API to internal representation
     * If metric name exist in nameMapping function will return changed name
     *
     * @param string $name  Metric's name
     * @return string
     */
    public static function metricNameToEntity($name)
    {
        return isset(static::$nameMap[$name]) ? static::$nameMap[$name] : $name;
    }

    /**
     * Change metrics name used in api responses
     *
     * @param string|string[] $name  Metric's name or list of names
     * @return string|string[]
     */
    public static function metricNameToData($name)
    {
        if (is_array($name)) {
            return array_merge(array_keys(array_intersect(static::$nameMap, $name)), array_diff($name, static::$nameMap));
        }

        $nameMap = array_flip(static::$nameMap);
        return isset($nameMap[$name]) ? $nameMap[$name] : $name;
    }
}