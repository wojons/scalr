<?php
namespace Scalr\Tests\Api\Rest;

use Scalr\Tests\TestCase;
use Scalr\Api\Rest\Environment;

/**
 * EnvironmentTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4 (18.02.2015)
 */
class EnvironmentTest extends TestCase
{
    public function testConstructor()
    {
        $env = new Environment();

        //Checks interator
        $this->assertInstanceOf('ArrayIterator', $env->getIterator());

        foreach ($env as $k => $v) {
            $this->assertNotEmpty($k);
        }

        //Checks array access
        foreach(['request.headers', 'PATH_INFO', 'SCHEME', 'QUERY_STRING', 'SCRIPT_NAME', 'raw.body'] as $key) {
            $this->assertArrayHasKey($key, $env);
        }


    }
}