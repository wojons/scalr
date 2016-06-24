<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Model\Entity;
use Scalr\Tests\Functional\Api\V2\ApiTest;

/**
 * Class ScalingRule
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11.10 (11.02.2016)
 */
class ScalingRule extends ApiFixture
{

    /**
     * Farm Role Image created for specifics request
     */
    const TEST_DATA_ROLE_IMAGE = 'RoleImage';

    /**
     * Role categories created for specifics request
     */
    const TEST_DATA_ROLE_CATEGORY = 'RoleCategory';

    /**
     * Role created for specifics request
     */
    const TEST_DATA_ROLE = 'Role';

    /**
     * Farm role scaling metrics
     */
    const FARM_ROLE_SCALING_METRIC_DATA = 'FarmRoleScalingMetrics';

    /**
     * Custom metrics categories created for specifics request
     */
    const TEST_DATA_CUSTOM_METRIC = 'CustomMetrics';

    /**
     * Farm created for specifics request
     */
    const TEST_DATA_FARM = 'Farm';

    /**
     * Farm role created for specifics request
     */
    const TEST_DATA_FARM_ROLE = 'FarmRole';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'ScalingRuleData';

    /**
     * Test data for patch params
     */
    const TEST_DATA_PARAMS = 'ScalingRuleDataParams';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'scalingRule';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {

        if (!empty($this->sets[static::TEST_DATA_ROLE_CATEGORY])) {
            $this->prepareRoleCategory(static::TEST_DATA_ROLE_CATEGORY, 2);
        }

        if (!empty($this->sets[static::TEST_DATA_ROLE])) {
            $this->prepareData(static::TEST_DATA_ROLE);
            $this->prepareRole(static::TEST_DATA_ROLE, 1);
        }

        if (!empty($this->sets[static::TEST_DATA_ROLE_IMAGE])) {
            $this->prepareData(static::TEST_DATA_ROLE_IMAGE);
            $this->prepareRoleImage(static::TEST_DATA_ROLE_IMAGE);
        }

        if (!empty($this->sets[static::TEST_DATA_FARM])) {
            $this->prepareFarm(static::TEST_DATA_FARM);
        }

        $this->createMetricsEntity();
        if (!empty($this->sets[static::TEST_DATA_FARM_ROLE])) {
            $this->prepareData(static::TEST_DATA_FARM_ROLE);
            $this->prepareFarmRole(static::TEST_DATA_FARM_ROLE);
        }
        $this->prepareData(static::TEST_DATA);
        $this->prepareData(static::TEST_DATA_PARAMS);
    }

    /**
     * Creates and save scaling metrics entity to DB
     */
    protected function createMetricsEntity()
    {
        if (!empty($this->sets[static::TEST_DATA_CUSTOM_METRIC])) {
            foreach ($this->sets[static::TEST_DATA_CUSTOM_METRIC] as &$cmData) {
                $cmData['envId'] = static::$testEnvId;
                $cmData['accountId'] = static::$user->getAccountId();
                /* @var  $cm Entity\ScalingMetric */
                $cm = ApiTest::createEntity(new Entity\ScalingMetric(), $cmData);
                $cmData['id'] = $cm->id;
            }
        }
    }
}