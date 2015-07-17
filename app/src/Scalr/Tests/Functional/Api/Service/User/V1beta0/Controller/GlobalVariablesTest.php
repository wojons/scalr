<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Role;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Api\Rest\Http\Request;

/**
 * GlobalVariablesTest
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (18.03.2015)
 */
class GlobalVariablesTest extends ApiTestCase
{
    /**
     * @test
     */
    public function testGlobalVariables()
    {
        $db = \Scalr::getDb();

        $testName = str_replace('-', '', $this->getTestName());

        $role = Role::findOne([['envId' => static::$testEnvId]]);
        /* @var $role Role */
        $roleId = $role->id;

        $uri = static::getUserApiUrl("roles/{$roleId}/global-variables");

        $variables = null;
        $declaredNotInRole = null;

        do {
            $query = [];

            if (isset($variables->pagination->next)) {
                $parts = parse_url($variables->pagination->next);
                parse_str($parts['query'], $query);
            }

            $query[ApiController::QUERY_PARAM_MAX_RESULTS] = 2;

            $describe = $this->request($uri, Request::METHOD_GET, $query);

            $this->assertDescribeResponseNotEmpty($describe);

            $this->assertNotEmpty($describe->getBody());

            $variables = $describe->getBody();
            $this->assertLessThanOrEqual(2, count($variables->data));

            foreach ($variables->data as $variable) {
                $this->assertVariableObjectNotEmpty($variable);

                if (empty($declaredNotInRole) && $variable->declaredIn !== ScopeInterface::SCOPE_ROLE) {
                    $declaredNotInRole = $variable->name;
                }

                if (strpos($variable->name, $testName) !== false) {
                    $delete = $this->request($uri . '/' . $variable->name, Request::METHOD_DELETE);
                    $this->assertEquals(200, $delete->response->getStatus());
                }
            }
        } while (!empty($variables->pagination->next));

        $this->assertNotNull($declaredNotInRole);

        $notFoundRoleId = 10 + $db->GetOne("SELECT MAX(r.id) FROM roles r");

        $describe = $this->request(static::getUserApiUrl("/roles/{$notFoundRoleId}/global-variables"), Request::METHOD_GET);
        $this->assertErrorMessageContains($describe, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "The Role either does not exist or isn't in scope for the current Environment");

        $adminRole = Role::findOne([['envId' => null]]);
        /* @var $adminRole Role */
        $this->assertInstanceOf("Scalr\\Model\\Entity\\Role", $adminRole);
        $notAccessibleId = $adminRole->id;
        $this->assertNotEmpty($notAccessibleId);

        $describe = $this->request(self::getUserApiUrl("/roles/{$notAccessibleId}/global-variables"), Request::METHOD_GET);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_SCOPE_VIOLATION, $describe);
        $this->assertErrorMessageStatusEquals(403, $describe);

        $create = $this->request($uri, Request::METHOD_POST, [], ['invalid' => 'value']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $create = $this->request($uri, Request::METHOD_POST, [], ['name' => 'invalid val--ue']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid name');

        //test invalid category name
        $create = $this->request($uri, Request::METHOD_POST, [], ['name' => 'TestName', 'category' => 'invalid category']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE);

        $create = $this->request($uri, Request::METHOD_POST);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');

        $create = $this->request($uri, Request::METHOD_POST, [], ['name' => $testName, 'value' => $testName, 'description' => $testName]);
        $this->assertEquals(201, $create->response->getStatus());
        $this->assertFetchResponseNotEmpty($create);

        $createBody = $create->getBody();
        $this->assertNotEmpty($createBody);
        $this->assertVariableObjectNotEmpty($createBody->data);

        $this->assertEquals($testName, $createBody->data->name);
        $this->assertEquals($testName, $createBody->data->value);
        $this->assertEquals($testName, $createBody->data->description);

        $create = $this->request($uri, Request::METHOD_POST, [], ['name' => $testName]);
        $this->assertErrorMessageContains($create, 409, ErrorMessage::ERR_UNICITY_VIOLATION, 'Variable with name');

        $fetch = $this->request($uri . '/' . $testName, Request::METHOD_GET);
        $this->assertEquals(200, $fetch->response->getStatus());
        $this->assertFetchResponseNotEmpty($fetch);

        $fetchBody = $fetch->getBody();
        $this->assertNotEmpty($fetchBody);
        $this->assertVariableObjectNotEmpty($fetchBody->data);

        $this->assertEquals($testName, $fetchBody->data->name);
        $this->assertEquals($testName, $fetchBody->data->value);

        $modify = $this->request($uri . '/' . $testName, Request::METHOD_PATCH, [], ['value' => '']);
        $this->assertEquals(200, $modify->response->getStatus());
        $this->assertFetchResponseNotEmpty($modify);

        $modifyBody = $modify->getBody();
        $this->assertNotEmpty($modifyBody);
        $this->assertVariableObjectNotEmpty($modifyBody->data);

        $this->assertEquals($testName, $modifyBody->data->name);
        $this->assertEquals('', $modifyBody->data->value);

        $modify = $this->request($uri . '/' . $testName . 'notFound', Request::METHOD_PATCH, [], ['value' => '']);
        $this->assertEquals(404, $modify->response->getStatus());
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_OBJECT_NOT_FOUND, $modify);

        $modify = $this->request($uri . '/' . $testName, Request::METHOD_PATCH, [], ['name' => '']);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $modify = $this->request($uri . '/' . $declaredNotInRole, Request::METHOD_PATCH, [], ['hidden' => 1]);
        $this->assertEquals(403, $modify->response->getStatus());
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_SCOPE_VIOLATION, $modify);

        $delete = $this->request($uri . '/' . $declaredNotInRole, Request::METHOD_DELETE);
        $this->assertEquals(403, $delete->response->getStatus());
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_SCOPE_VIOLATION, $delete);

        $delete = $this->request($uri . '/' . $testName . 'notfound', Request::METHOD_DELETE);
        $this->assertEquals(404, $delete->response->getStatus());
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_OBJECT_NOT_FOUND, $delete);

        $delete = $this->request($uri . '/' . $testName, Request::METHOD_DELETE);
        $this->assertEquals(200, $delete->response->getStatus());

    }

    /**
     * Asserts if variable's object has all properties
     *
     * @param object $data     Single image's item
     */
    public function assertVariableObjectNotEmpty($data)
    {
        $this->assertObjectHasAttribute('name', $data);
        $this->assertObjectHasAttribute('value', $data);
        $this->assertObjectHasAttribute('computedValue', $data);
        $this->assertObjectHasAttribute('declaredIn', $data);
        $this->assertObjectHasAttribute('hidden', $data);
        $this->assertObjectHasAttribute('locked', $data);
        $this->assertObjectHasAttribute('outputFormat', $data);
        $this->assertObjectHasAttribute('requiredIn', $data);
        $this->assertObjectHasAttribute('validationPattern', $data);
        $this->assertObjectHasAttribute('description', $data);
    }

}