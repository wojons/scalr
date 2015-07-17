<?php

namespace Scalr\Tests\Functional\Api\Rest;

use Scalr\Api\Rest\Http\Request;
use Scalr\Tests\Functional\Api\ApiTestCase;

class ApiApplicationTest extends ApiTestCase
{

    /**
     * This test must be run separately
     *
     * @test
     */
    public function testErrorResponse()
    {
        static::$apiKeyEntity->delete();

        $response = $this->request("/api/admin/" . static::$apiVersion . "/users/" . $this->getUser()->id, Request::METHOD_GET);

        $this->assertNotEmpty($response);

        $body = $response->getBody();

        $this->assertEquals(401, $response->status);

        $this->assertTrue(is_object($body));

        $this->assertArrayHasKey("content-type", $response->headers);
        $this->assertStringStartsWith("application/json", $response->headers["content-type"]);
    }
}