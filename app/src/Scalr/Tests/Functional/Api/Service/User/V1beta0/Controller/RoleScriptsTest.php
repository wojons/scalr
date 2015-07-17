<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\RoleScriptAdapter;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\RoleScript;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use Scalr\Tests\Functional\Api\ScriptsTestCase;

/**
 * RoleScripts Test
 *
 * @author N.V.
 */
class RoleScriptsTest extends ScriptsTestCase
{

    /**
     * @return Role[]
     */
    public function getTestRoles()
    {
        $roles = Role::find([[ 'envId' => $this->getEnvironment()->id ]]);

        return array_filter(iterator_to_array($roles), function (Role $role) {
            $dbRole = \DBRole::loadById($role->id);


            $farms = $dbRole->getFarms();

            foreach ($farms as $farmId) {
                $farm = \DBFarm::LoadByID($farmId);

                if ($farm->Status) {
                    return false;
                }
            }

            return true;
        });
    }

    public function ruleToDelete($ruleId)
    {
        static::$testData['Scalr\Model\Entity\RoleScript'][] = $ruleId;
    }

    /**
     * @param int $roleId
     * @param int $ruleId
     *
     * @return ApiTestResponse
     */
    public function getRule($roleId, $ruleId)
    {
        $uri = self::getUserApiUrl("/roles/{$roleId}/orchestration-rules/{$ruleId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param int $roleId
     * @param array $filters
     *
     * @return array
     */
    public function listRules($roleId, array $filters = [])
    {
        $envelope = null;
        $rules = [];
        $uri = self::getUserApiUrl("/roles/{$roleId}/orchestration-rules/");

        do {
            $params = $filters;

            if (isset($envelope->pagination->next)) {
                $parts = parse_url($envelope->pagination->next);
                parse_str($parts['query'], $params);
            }

            $response = $this->request($uri, Request::METHOD_GET, $params);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $this->assertDescribeResponseNotEmpty($response);

            $envelope = $response->getBody();

            $rules[] = $envelope->data;
        } while (!empty($envelope->pagination->next));

        return call_user_func_array('array_merge', $rules);
    }

    /**
     * @param int $roleId
     * @param array $ruleData
     *
     * @return ApiTestResponse
     */
    public function postRule($roleId, array $ruleData)
    {
        $uri = self::getUserApiUrl("/roles/{$roleId}/orchestration-rules");
        return $this->request($uri, Request::METHOD_POST, [], $ruleData);
    }

    /**
     * @param int $roleId
     * @param int $ruleId
     * @param array $scriptData
     *
     * @return ApiTestResponse
     */
    public function modifyRule($roleId, $ruleId, $scriptData)
    {
        $uri = self::getUserApiUrl("/roles/{$roleId}/orchestration-rules/{$ruleId}");

        return $this->request($uri, Request::METHOD_PATCH, [], $scriptData);
    }

    /**
     * @param int $roleId
     * @param int $ruleId
     *
     * @return ApiTestResponse
     */
    public function deleteRule($roleId, $ruleId)
    {
        $uri = self::getUserApiUrl("/roles/{$roleId}/orchestration-rules/{$ruleId}");

        return $this->request($uri, Request::METHOD_DELETE);
    }

    /**
     * @test
     */
    public function testComplex()
    {
        $user = $this->getUser();
        $environment = $this->getEnvironment();
        $fictionController = new ApiController();

        //foreach iterates through values in order of ads
        //we need to remove rules first, then - scripts
        //we initialize data set for removal with non-existing rule
        $this->ruleToDelete(-1);

        /* @var $roles Role[] */
        $roles = $this->getTestRoles();

        /* @var $role Role */
        $role = array_shift($roles);

        /* @var $scalrRole Role */
        $scalrRole = Role::findOne([[ 'envId' => null, 'accountId' => null ]]);

        /* @var $script Script */
        $script = static::createEntity(new Script(), [
            'name' => 'test-role-scripts',
            'description' => 'test-role-scripts',
            ''
        ]);

        $isWindows = $role->getOs()->family == 'windows';

        /* @var $version ScriptVersion */
        $version = static::createEntity(new ScriptVersion(), [
            'scriptId' => $script->id,
            'version' => $script->getLatestVersion()->version + 1,
            'content' => $isWindows ? '#!cmd' : '#!/bin/sh'
        ]);

        $script->os = $isWindows ? 'windows' : 'linux';

        $script->save();

        $scalrRoleScriptData = [
            'trigger' => [
                'type' => RoleScriptAdapter::TRIGGER_SINGLE_EVENT,
                'event' => [
                    'id' => 'HostInit'
                ]
            ],
            'target' => [
                'type' => RoleScriptAdapter::TARGET_TRIGGERING_FARM_ROLE
            ],
            'action' => [
                'actionType' => RoleScriptAdapter::ACTION_SCRIPT,
                'scriptVersion' => [
                    'script' => [
                        'id' => $script->id
                    ],
                    'version' => $version->version
                ]
            ]
        ];

        $localRoleScriptData = [
            'trigger' => [
                'type' => RoleScriptAdapter::TRIGGER_ALL_EVENTS
            ],
            'target' => [
                'type' => RoleScriptAdapter::TARGET_NULL
            ],
            'action' => [
                'actionType' => RoleScriptAdapter::ACTION_URI,
                'uri' => 'https://example.com'
            ]
        ];

        //post scalr rule
        $response = $this->postRule($role->id, $scalrRoleScriptData);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        /* @var $rule RoleScript */
        $rule = RoleScript::findPk($ruleId);

        $this->assertNotEmpty($rule);

        $this->ruleToDelete($ruleId);

        $this->assertObjectEqualsEntity($scalrRoleScriptData, $rule);

        //post local rule
        $response = $this->postRule($role->id, $localRoleScriptData);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        /* @var $rule RoleScript */
        $rule = RoleScript::findPk($ruleId);

        $this->assertNotEmpty($rule);

        $this->ruleToDelete($ruleId);

        $this->assertObjectEqualsEntity($localRoleScriptData, $rule);

        //post rule to environment-scoped role
        $response = $this->postRule($scalrRole->id, $scalrRoleScriptData);

        $this->assertErrorMessageContains($response, 403, ErrorMessage::ERR_PERMISSION_VIOLATION);

        //post rule already existing
        $data = $scalrRoleScriptData;
        $data['id'] = $ruleId;

        $response = $this->postRule($role->id, $data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        $this->ruleToDelete($ruleId);

        $this->assertNotEquals($data['id'], $ruleId);

        //post rule with script that does not exists
        $data = $scalrRoleScriptData;
        $data['action']['scriptVersion']['script']['id'] = Script::findOne([], [ 'id' => true ])->id + 1;

        $response = $this->postRule($role->id, $data);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //post rule with version that does not exists
        $data = $scalrRoleScriptData;
        $data['action']['scriptVersion']['version'] = Script::findPk($data['action']['scriptVersion']['script']['id'])->getLatestVersion()->version + 1;

        $response = $this->postRule($role->id, $data);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //post rule with properties that not existing
        $data = $scalrRoleScriptData;
        $data['foo'] = 'bar';

        $response = $this->postRule($role->id, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //post rule without required fields
        $data = $localRoleScriptData;
        unset($data['action']);

        $response = $this->postRule($role->id, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //post rule with invalid field
        $data = $localRoleScriptData;
        $data['action'] = '';

        $response = $this->postRule($role->id, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //fetch rule
        $response = $this->getRule($role->id, $rule->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $this->assertObjectEqualsEntity($response->getBody()->data, $rule);

        //fetch rule that doe not exists
        $response = $this->getRule($role->id, RoleScript::findOne([], [ 'id' => '' ])->id + 1);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //fetch rule with missmatch role id
        $response = $this->getRule(Role::findOne([], [ 'id' => '' ])->id + 1, $rule->id);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //test have access to all listed rules
        $rules = $this->listRules($role->id);

        foreach ($rules as $rule) {
            $this->assertTrue(RoleScript::findPk($rule->id)->hasAccessPermissions($user));
        }

        $listUri = static::getUserApiUrl("/scripts");

        //test invalid filters
        $response = $this->request($listUri, Request::METHOD_GET, [ 'foo' => 'bar' ]);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //test invalid filters values
        $response = $this->request($listUri, Request::METHOD_GET, [ 'scope' => 'foobar' ]);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //delete script
        /* @var $rule RoleScript */
        $rule = static::createEntity(new RoleScript(), [
            'roleId' => $role->id,
            'scriptId' => $script->id
        ]);

        $response = $this->deleteRule($role->id, $rule->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        //delete scalr-scoped script
        /* @var $rule RoleScript */
        $rule = static::createEntity(new RoleScript(), [
            'roleId' => $scalrRole->id,
            'scriptId' => $script->id,
            'version' => -1
        ]);

        $response = $this->deleteRule($scalrRole->id, $rule->id);

        $this->assertErrorMessageContains($response, 403, ErrorMessage::ERR_PERMISSION_VIOLATION);

        //delete script that does not exists
        $response = $this->deleteRule($role->id, RoleScript::findOne([], [ 'id' => '' ])->id + 1);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);
    }
}