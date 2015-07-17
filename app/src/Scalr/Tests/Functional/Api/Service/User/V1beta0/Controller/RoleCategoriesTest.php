<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Api\Rest\Http\Request;
use Scalr\DataType\ScopeInterface;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\DataType\ErrorMessage;

/**
 * RoleCategoriesTest
 *
 * @author   Vitaliy Demidov     <vitaliy@scalr.com>
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (18.03.2015)
 */
class RoleCategoriesTest extends ApiTestCase
{
    /**
     * @test
     */
    public function testRoleCategories()
    {
        $db = \Scalr::getDb();

        $uri = self::getUserApiUrl('/role-categories');
        $response = $this->request($uri, Request::METHOD_GET, ['invalidKey' => 'invalidValue', 'scope' => 'environment']);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_STRUCTURE, $response);
        $this->assertErrorMessageStatusEquals(400, $response);

        $response = $this->request($uri, Request::METHOD_GET, ['scope' => 'invalidValue']);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected scope value');

        $response = $this->request($uri, Request::METHOD_GET, ['scope' => ScopeInterface::SCOPE_SCALR, ApiController::QUERY_PARAM_MAX_RESULTS => 1]);
        $this->assertEquals(200, $response->status);

        $number = count($response->getBody()->data);
        $this->assertLessThanOrEqual(1, $number);

        if ($number > 0) {
            $roleCategory = $response->getBody()->data[0];

            $this->assertTrue(!empty($roleCategory->id));
            $this->assertTrue(!empty($roleCategory->name));

            $notFoundRoleCategoryId = 10 + $db->GetOne("SELECT MAX(rc.id) FROM role_categories rc");
            $response = $this->request($uri . '/' . $notFoundRoleCategoryId, Request::METHOD_GET);
            $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "The Role Category either does not exist or isn't in scope for the current Environment");

            $response = $this->request($uri . '/' . $roleCategory->id, Request::METHOD_GET);
            $this->assertEquals(200, $response->status);
            $this->assertEquals($roleCategory, $response->getBody()->data);

            $responseData = $response->getBody()->data;

            // test filtering
            $describe = $this->request($uri, Request::METHOD_GET, ['scope' => ScopeInterface::SCOPE_ENVIRONMENT]);
            $this->assertDescribeResponseNotEmpty($describe);

            foreach ($describe->getBody()->data as $data) {
                $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $data->scope);
            }

            $describe = $this->request($uri, Request::METHOD_GET, ['name' => $responseData->name]);
            $this->assertDescribeResponseNotEmpty($describe);

            foreach ($describe->getBody()->data as $data) {
                $this->assertEquals($responseData->name, $data->name);
            }

            $describe = $this->request($uri, Request::METHOD_GET, ['id' => $responseData->id]);
            $this->assertDescribeResponseNotEmpty($describe);

            foreach ($describe->getBody()->data as $data) {
                $this->assertEquals($responseData->id, $data->id);
            }
        }

    }

}