<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Tests\Functional\Api\V2\ApiV2Test;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;

/**
 * Class CostCenters
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (07.12.2015)
 */
class CostCenters extends TestDataFixtures
{

    const TEST_DATA_COST_CENTERS = 'CostCenters';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'CostCentersData';

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Stats\CostAnalytics\Entity\CostCentreEntity';

    /**
     * {@inheritdoc}
     * @see TestDataFixtures::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_COST_CENTERS])) {
            foreach ($this->sets[static::TEST_DATA_COST_CENTERS] as &$ccData) {
                $ccData['accountId'] = self::$user->getAccountId();
                $ccData['envId'] = self::$env->id;
                /* @var $cc CostCentreEntity */
                $cc = ApiV2Test::createEntity(new CostCentreEntity(), $ccData, 2);
                $cc->saveProperty(CostCentrePropertyEntity::NAME_BILLING_CODE, $ccData['billingCode']);
                $cc->save();
                $ccData['id'] = $cc->ccId;

                ApiV2Test::createEntity(new AccountCostCenterEntity(), [
                    'ccId'      => $ccData['id'],
                    'accountId' => $ccData['accountId']
                ], 1);

                // to delete Cost Center properties
                ApiV2Test::toDelete(
                    CostCentrePropertyEntity::class,
                    [$cc->ccId, $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)]
                );
            }
        }
    }

}