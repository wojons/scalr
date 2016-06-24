<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

/**
 * Class CostCenter
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (07.12.2015)
 */
class CostCenter extends ApiFixture
{
    /**
     * CostCenters created for specifics request
     */
    const TEST_DATA_COST_CENTERS = 'CostCenters';

    /**
     * Cost Center Properties created for specifics request
     */
    const TEST_DATA_CC_PROPERTIES = 'CostCenterProperties';

    /**
     * Account Cost Center created for specifics request
     */
    const TEST_DATA_ACCOUNT_COST_CENTER = 'AccountCostCenter';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'CostCentersData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'costCenter';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (isset($this->sets[static::TEST_DATA_COST_CENTERS])) {
            $this->prepareCostCenter(static::TEST_DATA_COST_CENTERS);
        }

        if (isset($this->sets[static::TEST_DATA_ACCOUNT_COST_CENTER])) {
            $this->prepareData(static::TEST_DATA_ACCOUNT_COST_CENTER);
            $this->prepareAccountCostCenter(static::TEST_DATA_ACCOUNT_COST_CENTER);
        }
        $this->prepareData(static::TEST_DATA);
    }
}