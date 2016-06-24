<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

/**
 * Class Projects
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (08.12.2015)
 */
class Project extends ApiFixture
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
     * {@inheritdoc}
     */
    const TEST_DATA = 'ProjectsData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'project';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_PROJECTS])) {
            $this->prepareProjects(static::TEST_DATA_PROJECTS);
        }
        $this->prepareData(static::TEST_DATA);
    }
}