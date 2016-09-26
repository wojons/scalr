<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

/**
 * Environment
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.20 (18.04.2016)
 */
class Farm extends ApiFixture
{
    /**
     * Projects created for specifics request
     */
    const TEST_DATA_PROJECTS = 'Projects';

    /**
     * Projects properties  created for specifics request
     */
    const TEST_DATA_PROJECT_PROPERTIES = 'ProjectProperties';

    /**
     * Farm created for specifics request
     */
    const TEST_DATA_FARM = 'Farm';

    /**
     * Farm settings created for specifics farm
     */
    const TEST_DATA_FARM_SETTINGS = 'FarmSettings';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'FarmsData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'farm';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_PROJECTS])) {
            $this->prepareProjects(static::TEST_DATA_PROJECTS);
        }

        if (!empty($this->sets[static::TEST_DATA_FARM])) {
            if (!empty($this->sets[static::TEST_DATA_FARM_SETTINGS])) {
                $this->prepareData(static::TEST_DATA_FARM_SETTINGS);
            }
            $this->prepareFarm(static::TEST_DATA_FARM);
        }

        $this->prepareData(static::TEST_DATA);
    }
}