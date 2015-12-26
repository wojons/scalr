<?php

namespace Scalr\Tests\Functional\Api\Rest;

use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Rest\Http\Response;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Util\Api\Describer;
use Scalr\Api\DataType\ApiMessage;

/**
 * Class DeprecatedPathTest
 * @author A.P.
 * @package Scalr\Tests\Functional\Api\Rest
 */
class DeprecatedPathTest extends ApiTestCase
{

    /**
     * @test
     */
    public function testUserApiLists()
    {
        $userSpec = $this->getSpecFile('user');
        $this->assertApiListResponse($userSpec['basePath'], $this->getGroupPath($userSpec['paths'], '#^/{envId}/[\w-]+/$#'), true);
    }

    /**
     * @test
     */
    public function testAccountApiLists()
    {
        $accountSpec = $this->getSpecFile('account');
        $this->assertApiListResponse($accountSpec['basePath'], $this->getGroupPath($accountSpec['paths'], '#^/[\w-]+/$#'));
    }

    /**
     * Asserts describe response has all properties and deprecated paths response are identical current response
     *
     * @param string $basePath
     * @param array $listPaths
     * @param bool $environment optional if variable is true replace envId for test environment
     */
    public function assertApiListResponse($basePath, $listPaths, $environment = false)
    {
        $this->assertNotEmpty($listPaths);
        foreach ($listPaths as $path => $property) {
            $pathInfo = $basePath . $path;
            if ($environment) {
                $pathInfo = str_replace('{envId}', static::$testEnvId, $pathInfo);
            }

            $resp = $this->request($pathInfo, Request::METHOD_GET);
            $envelope = $resp->getBody();
            $this->assertDescribeResponseNotEmpty($resp);

            $pathInfoDeprecated = preg_replace("#/(v\d.*?)/(user|admin|account)/#", '/$2/$1/', $pathInfo);
            $resDepPath = $this->request($pathInfoDeprecated, Request::METHOD_GET);
            $this->assertDescribeResponseNotEmpty($resDepPath);
            $envelopeDep = $resDepPath->getBody();

            $this->assertObjectHasAttribute('warnings', $envelopeDep);
            $this->assertNotEmpty($envelopeDep->warnings);
            $code = []; $message = [];
            /* @var $warning ApiMessage */
            foreach($envelopeDep->warnings as $warning) {
                $code[] = $warning->code;
                $message[] = $warning->message;
            }
            $this->assertContains(Response::getCodeMessage(301),$code);
            $this->assertContains(sprintf('Location %s', $pathInfo), $message);
            $this->assertEquals($envelope->data, $envelopeDep->data);
        }
    }

    /**
     * Get spec array for current service
     *
     * @param $service string available api service (user|account|admin)
     * @return array
     */
    protected function getSpecFile($service)
    {

        $describer = new Describer(self::$apiVersion, $service, \Scalr::getContainer()->config());
        $reflectionSpecProperties = (new \ReflectionClass('Scalr\Util\Api\Describer'))->getProperty('specFile');
        $reflectionSpecProperties->setAccessible(true);
        return yaml_parse_file($reflectionSpecProperties->getValue($describer));
    }

    /**
     * Get filtered paths for pattern
     *
     * @param $specDataPaths array  spec paths
     * @param $pattern       string The pattern to search path
     *
     * @return array filtered paths
     */
    protected function getGroupPath($specDataPaths, $pattern)
    {
        return array_filter($specDataPaths, function ($k) use ($pattern) {
            return preg_match($pattern, $k);
        }, ARRAY_FILTER_USE_KEY);
    }

}