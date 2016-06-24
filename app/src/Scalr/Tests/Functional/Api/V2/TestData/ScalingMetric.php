<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;
use Scalr\Model\Entity;
use Scalr\Tests\Functional\Api\V2\ApiTest;

/**
 * Class ScalingMetric
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11.10 (12.02.2016)
 */
class ScalingMetric extends ScalingRule
{

    const FARM_ROLE_SCALING_METRIC_DATA = 'FarmRoleScalingMetrics';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'ScalingMetricData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'scalingMetric';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        $this->prepareData(static::TEST_DATA_CUSTOM_METRIC);
        $this->createMetricsEntity();
        if (!empty($this->sets[static::TEST_DATA_FARM_ROLE])) {
            $this->prepareFarmRole(static::TEST_DATA_FARM_ROLE);
        }
        if (isset($this->sets[static::FARM_ROLE_SCALING_METRIC_DATA])) {
            $this->prepareData(static::FARM_ROLE_SCALING_METRIC_DATA);
            foreach ($this->sets[static::FARM_ROLE_SCALING_METRIC_DATA] as $farmRoleScaling) {
                ApiTest::createEntity(new Entity\FarmRoleScalingMetric(), $farmRoleScaling);
            }
        }
        $this->prepareData(static::TEST_DATA);
    }
}