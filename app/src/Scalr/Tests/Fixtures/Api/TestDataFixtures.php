<?php
namespace Scalr\Tests\Fixtures\Api;

use DirectoryIterator;
use Scalr\System\Config\Yaml;
use Scalr\Api\Rest\Http\Request;
use Scalr\Tests\TestCase;

/**
 * Class TestData
 * @package Scalr\Tests\Functional\Api\V2
 */
class TestDataFixtures extends TestCase
{

    /**
     * @return array
     */
    public function getPaths()
    {
        return $this->getDataFixtures();
    }

    /**
     * @return array
     * @throws \Scalr\System\Config\Exception\YamlException
     */
    protected function getDataFixtures()
    {
        $fixtures = $this->getFixturesDirectory() . '/Api/V2';
        $paths = [];
        foreach (new DirectoryIterator($fixtures) as $fileInfo) {
            if ($fileInfo->isFile()) {
                $pathInfo = Yaml::load($fileInfo->getPathname());
                $paths[$fileInfo->getBasename('.yaml')] = $this->resolvePathInfo($pathInfo->get('paths'));
            }
        }

        return $paths;
    }

    /**
     * @param array $info
     * @return array
     */
    protected function resolvePathInfo(array $info)
    {
        $pathInfo = [];
        foreach ($info as $paths) {
            $defaultOptions = [
                'uri' => $paths['uri'],
                'method' => Request::METHOD_GET,
                'response' => 200
            ];
            foreach ($paths['operations'] as $operation) {
                $pathInfo[] = array_merge($defaultOptions, $operation);
            }
        }

        return $pathInfo;
    }
}