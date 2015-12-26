<?php

namespace Scalr\Model\Entity;

use Exception;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\AbstractEntity;
use Scalr_Scaling_Algorithms_DateTime;
use Scalr_Scaling_Algorithms_Sensor;

/**
 * Scaling Metrics entity.
 *
 * @author   Roman Kolodnitskyi  <r.kolodnitskyi@scalr.com>
 *
 * @since    5.9 (10.06.2015)
 *
 * @Entity
 * @Table(name="scaling_metrics")
 */
class ScalingMetric extends AbstractEntity implements ScopeInterface
{
    const METRIC_LOAD_AVERAGES_ID     = 1;
    const METRIC_FREE_RAM_ID          = 2;
    const METRIC_URL_RESPONSE_TIME_ID = 3;
    const METRIC_SQS_QUEUE_SIZE_ID    = 4;
    const METRIC_DATE_AND_TIME_ID     = 5;
    const METRIC_BANDWIDTH_ID         = 6;

    /**
     * File-Read retrieve method
     */
    const RETRIEVE_METHOD_READ = 'read';

    /**
     * File-Execute retrieve method
     */
    const RETRIEVE_METHOD_EXECUTE = 'execute';

    /**
     * Calculation function "Average"
     */
    const CALC_FUNCTION_AVERAGE = 'avg';

    /**
     * Calculation function "Sum"
     */
    const CALC_FUNCTION_SUM = 'sum';

    /**
     * Calculation function "Max"
     */
    const CALC_FUNCTION_MAXIMUM = 'max';

    /**
     * Calculation function "Min"
     */
    const CALC_FUNCTION_MINIMUM = 'min';

    /**
     * Sensor scaling algorithm
     */
    const ALGORITHM_SENSOR = 'Sensor';

    /**
     * DateTime scaling algorithm
     */
    const ALGORITHM_DATETIME = 'DateTime';

    /**
     * ID.
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * The identifier of the client's account.
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * The identifier of the client's environment.
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

    /**
     * Metric's Name.
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * File path.
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $filePath;

    /**
     * Retrieve method.
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $retrieveMethod;

    /**
     * Calculating function.
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $calcFunction;

    /**
     * Algorithm.
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $algorithm;

    /**
     * List with initialized algorithm objects
     *
     * @var array
     */
    private static $algos = [];

    /**
     * Alias.
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $alias;

    /**
     * Should it follow inverted logic
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $isInvert = false;

    /**
     * Get list with basic info about metrics.
     *
     * @param int $envId Identifier of environment
     * @return array
     */
    public static function getList($envId)
    {
        $result = [];
        $criteria = [['$or' => [['envId' => $envId],['envId' => null]]]];
        $metrics = self::find($criteria);

        foreach ($metrics as $metric) {
            /* @var $metric \Scalr\Model\Entity\ScalingMetric */
            $result[$metric->id] = [
                'id'       => $metric->id,
                'name'     => $metric->name,
                'alias'    => $metric->alias,
                'scope'    => $metric->getScope(),
                'isInvert' => $metric->isInvert,
            ];
        }

        return $result;
    }

    /**
     * Get specified algorithm object
     *
     * @param string $algoName Name of algorithm
     * @return Scalr_Scaling_Algorithms_DateTime|Scalr_Scaling_Algorithms_Sensor Algorithm object
     * @throws Exception
     */
    public static function getAlgorithm($algoName)
    {
        if (!self::$algos[$algoName]) {
            $className = "Scalr_Scaling_Algorithms_{$algoName}";

            if (class_exists($className)) {
                self::$algos[$algoName] = new $className();
            } else {
                throw new \Exception(sprintf(_("Scaling algorithm '%s' is not found"), $algoName));
            }
        }

        return self::$algos[$algoName];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->envId) ? self::SCOPE_ENVIRONMENT : self::SCOPE_SCALR;
    }
}
