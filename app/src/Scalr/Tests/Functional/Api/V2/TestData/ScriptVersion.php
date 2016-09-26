<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

/**
 * ScriptVersion
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.15 (23.03.2016)
 */
class ScriptVersion extends ApiFixture
{
    /**
     * Scripts created for specifics request
     */
    const TEST_DATA_SCRIPTS = 'Scripts';

    /**
     * Script version request params
     */
    const TEST_DATA_SCRIPT_VERSION_PARAMS = 'ScriptVersionParams';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'ScriptVersionData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'scriptVersion';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_SCRIPTS])) {
            $this->prepareScript(static::TEST_DATA_SCRIPTS);
        }
        $this->prepareData(static::TEST_DATA);
        $this->prepareData(static::TEST_DATA_SCRIPT_VERSION_PARAMS);
    }
}