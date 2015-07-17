<?php

namespace Scalr\Model\Entity;

use Scalr\DataType\ScopeInterface;
use Scalr\Model\AbstractEntity;

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

        /* @var \Scalr\Model\Entity\ScalingMetric $metric */
        foreach ($metrics as $metric) {
            $result[$metric->id] = [
                'id'    => $metric->id,
                'name'  => $metric->name,
                'alias' => $metric->alias,
                'scope' => $metric->getScope(),
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
                throw new \Exception(sprintf(_("Scaling algorithm '%s' not found"), $algoName));
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
