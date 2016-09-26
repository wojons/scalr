<?php

namespace Scalr\Tests\Functional\Api\V2\Iterator;

use FilterIterator;
use DirectoryIterator;

/**
 * Class ApiFixtureIterator
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (15.12.2015)
 */
class ApiFixtureIterator extends FilterIterator
{
    /**
     * Fixtures auto generated test data path
     */
    const FIXTURES_TEST_DATA = SRCPATH . '/Scalr/Tests/Functional/Api/V2/TestData/';

    /**
     * File which describes the test data will not be included to the test
     */
    const FIXTURES_TEST_DATA_FILTER_FILE = SRCPATH . '/Scalr/Tests/Fixtures/Api/V2/ignorePath.yaml';

    /**
     * List of files that are won't be included in the api test
     *
     * @var array
     */
    protected $ignoreFiles = [];

    /**
     * ApiFixtureIterator constructor.
     *
     * @param DirectoryIterator $iterator   Fixtures directory iterator
     * @param string            $type       Type api fixtures
     */
    public function __construct(DirectoryIterator $iterator, $type)
    {
        if (file_exists(static::FIXTURES_TEST_DATA_FILTER_FILE)) {
            $ignorePaths = yaml_parse_file(static::FIXTURES_TEST_DATA_FILTER_FILE);
            if (!empty($ignorePaths['files'][$type])) {
                $this->ignoreFiles = $ignorePaths['files'][$type];
            }
        }
        parent::__construct($iterator);
    }

    /**
     * {@inheritdoc}
     * @see FilterIterator::accept()
     */
    public function accept()
    {
        /* @var  $fileInfo DirectoryIterator */
        $fileInfo = $this->getInnerIterator()->current();
        $name = $fileInfo->getBasename('.yaml');
        return $fileInfo->isFile() && file_exists(static::FIXTURES_TEST_DATA . ucfirst($name) . '.php') && !in_array($name, $this->ignoreFiles);
    }
}