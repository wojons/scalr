<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

/**
 * Class Farms
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (04.12.2015)
 */
class Farms extends TestDataFixtures
{
    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'FarmsData';

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\Farm';

    /**
     * {@inheritdoc}
     * @see TestDataFixtures::prepareTestData()
     */
    public function prepareTestData()
    {
        // TODO: Implement prepareTestData() method.
    }
}