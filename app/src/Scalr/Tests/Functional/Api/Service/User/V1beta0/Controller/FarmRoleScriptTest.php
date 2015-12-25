<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Model\Entity\FarmRoleScript;
use Scalr\Tests\Functional\Api\ScriptsTestCase;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;
use Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRules\FarmRoleScriptAdapter;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity\Role;
use SERVER_PLATFORMS;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Model\Entity\Account\User;
use Scalr\Api\DataType\ApiEntityAdapter;

/**
 * Class FarmRoleScriptTest
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (24.12.2015)
 */
class FarmRoleScriptTest extends ScriptsTestCase
{

    /**
     * @see Aws::REGION_US_EAST_1
     */
    const TEST_REGION = 'us-east-1';


    public function ruleToDelete($ruleId)
    {
        static::toDelete('Scalr\Model\Entity\FarmRoleScript', $ruleId);
    }

    /**
     * @param int $farmRoleId
     * @param int $ruleId
     *
     * @return ApiTestResponse
     */
    public function getRule($farmRoleId, $ruleId)
    {
        $uri = self::getUserApiUrl("/farm-roles/{$farmRoleId}/orchestration-rules/{$ruleId}");
        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param int $farmRoleId
     * @param array $filters
     *
     * @return array
     */
    public function listRules($farmRoleId, array $filters = [])
    {
        $envelope = null;
        $rules = [];
        $uri = self::getUserApiUrl("/farm-roles/{$farmRoleId}/orchestration-rules/");

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
     * @param int $farmRoleId
     * @param array $ruleData
     *
     * @return ApiTestResponse
     */
    public function postRule($farmRoleId, array $ruleData)
    {
        $uri = self::getUserApiUrl("/farm-roles/{$farmRoleId}/orchestration-rules");
        return $this->request($uri, Request::METHOD_POST, [], $ruleData);
    }

    /**
     * @param int $farmRoleId
     * @param int $ruleId
     * @param array $scriptData
     *
     * @return ApiTestResponse
     */
    public function modifyRule($farmRoleId, $ruleId, $scriptData)
    {
        $uri = self::getUserApiUrl("/farm-roles/{$farmRoleId}/orchestration-rules/{$ruleId}");

        return $this->request($uri, Request::METHOD_PATCH, [], $scriptData);
    }

    /**
     * @param int $farmRoleId
     * @param int $ruleId
     *
     * @return ApiTestResponse
     */
    public function deleteRule($farmRoleId, $ruleId)
    {
        $uri = self::getUserApiUrl("/farm-roles/{$farmRoleId}/orchestration-rules/{$ruleId}");

        return $this->request($uri, Request::METHOD_DELETE);
    }

    public function createTestFarmRole($farm)
    {
        /* @var $role Role */
        $roles = Role::findByName('base-ubuntu1404');

        if (empty($roles) || !count($roles)) {
            $this->markTestSkipped("Not found suitable role, required role - 'base-ubuntu1404'");
        } else {
            $role = $roles->current();
        }


        /* @var $farmRole FarmRole */
        $farmRole = static::createEntity(new FarmRole(), [
            'farmId' => $farm->id,
            'roleId' => $role->id,
            'alias' => 'test-launch-farm-role',
            'platform' => SERVER_PLATFORMS::EC2,
            'cloudLocation' => static::TEST_REGION
        ]);

        /* @var $settings SettingsCollection */
        $settings = $farmRole->settings;
        $settings->saveSettings([
            FarmRoleSetting::AWS_INSTANCE_TYPE => 't1.micro',
            FarmRoleSetting::AWS_AVAIL_ZONE => '',
            FarmRoleSetting::SCALING_ENABLED => true,
            FarmRoleSetting::SCALING_MIN_INSTANCES => 1,
            FarmRoleSetting::SCALING_MAX_INSTANCES => 2
        ]);
        $farmRole->save();
        return $farmRole;
    }

    /**
     * @test
     */
    public function testComplex()
    {

        /* @var Script $script */
        $script = static::generateScripts([['os' => 'linux']])[0];
        /* @var ScriptVersion $version */
        $version = static::generateVersions($script, [['content' => '#!/bin/sh']])[0];
        $adapter = $this->getAdapter('OrchestrationRules\FarmRoleScript');
        /* @var User $user */
        $user = $this->getUser();
        $environment = $this->getEnvironment();
        /* @var $farm Farm */
        $farm = static::createEntity(new Farm(), [
            'changedById' => $user->getId(),
            'name' => "{$this->uuid}-farm",
            'description' => "{$this->uuid}-description",
            'envId' => $environment->id,
            'accountId' => $user->getAccountId(),
            'createdById' => $user->getId()
        ]);
        $farmRole = $this->createTestFarmRole($farm);

        static::createEntity(new FarmRoleScript(), [
            'farmRoleId' => $farmRole->id,
            'scriptId' => $script->id,
            'farmId' => $farm->id
        ]);

        //test get endpoint

        $filterable = $adapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];
        $rules = $this->listRules($farmRole->id);

        foreach ($rules as $rule) {
            foreach ($filterable as $property) {
                $filterValue = $rule->{$property};
                $listResult = $this->listRules($farmRole->id, [$property => $filterValue]);
                if (!static::isRecursivelyEmpty($filterValue)) {
                    foreach ($listResult as $filtered) {
                        $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                    }
                }
            }
            $response = $this->getRule($farmRole->id, $rule->id);
            $this->assertEquals(200, $response->status, $this->printResponseError($response));
            $dbRule = FarmRoleScript::findPk($rule->id);
            $this->assertObjectEqualsEntity($response->getBody()->data, $dbRule, $adapter);
        }

        $scalrFRScriptData = [
            'trigger' => [
                'triggerType' => FarmRoleScriptAdapter::TRIGGER_SINGLE_EVENT,
                'event' => [
                    'id' => 'HostInit'
                ]
            ],
            'target' => [
                'targetType' => FarmRoleScriptAdapter::TARGET_NAME_TRIGGERING_SERVER
            ],
            'action' => [
                'actionType' => FarmRoleScriptAdapter::ACTION_SCRIPT,
                'scriptVersion' => [
                    'script' => [
                        'id' => $script->id
                    ],
                    'version' => $version->version
                ]
            ]
        ];

        $localFRScriptData = [
            'trigger' => [
                'triggerType' => FarmRoleScriptAdapter::TRIGGER_ALL_EVENTS
            ],
            'target' => [
                'targetType' => FarmRoleScriptAdapter::TARGET_NAME_NULL
            ],
            'action' => [
                'actionType' => FarmRoleScriptAdapter::ACTION_URI,
                'path' => 'https://example.com'
            ]
        ];

        //post scalr rule
        $response = $this->postRule($farmRole->id, $scalrFRScriptData);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        /* @var $rule FarmRoleScript */
        $rule = FarmRoleScript::findPk($ruleId);

        $this->assertNotEmpty($rule);

        $this->ruleToDelete($ruleId);

        $this->assertObjectEqualsEntity($scalrFRScriptData, $rule, $adapter);

        //post local rule
        $response = $this->postRule($farmRole->id, $localFRScriptData);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        /* @var $rule FarmRoleScript */
        $rule = FarmRoleScript::findPk($ruleId);

        $this->assertNotEmpty($rule);

        $this->ruleToDelete($ruleId);

        $this->assertObjectEqualsEntity($localFRScriptData, $rule, $adapter);

        //post rule already existing
        $data = $scalrFRScriptData;
        $data['id'] = $ruleId;

        $response = $this->postRule($farmRole->id, $data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        $this->ruleToDelete($ruleId);

        $this->assertNotEquals($data['id'], $ruleId);

        //post rule with script that does not exists
        $data = $scalrFRScriptData;
        $data['action']['scriptVersion']['script']['id'] = Script::findOne([], null, ['id' => true])->id + 1;

        $response = $this->postRule($farmRole->id, $data);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //post rule with version that does not exists
        $data = $scalrFRScriptData;
        $data['action']['scriptVersion']['version'] = Script::findPk($data['action']['scriptVersion']['script']['id'])->getLatestVersion()->version + 1;

        $response = $this->postRule($farmRole->id, $data);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //post rule with properties that not existing
        $data = $scalrFRScriptData;
        $data['foo'] = 'bar';

        $response = $this->postRule($farmRole->id, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //post rule without required fields
        $data = $localFRScriptData;
        unset($data['action']);

        $response = $this->postRule($farmRole->id, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //post rule with invalid field
        $data = $localFRScriptData;
        $data['action'] = '';

        $response = $this->postRule($farmRole->id, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //modify rule

        //TODO::ape add modify rule
        //fetch rule
        $response = $this->getRule($farmRole->id, $rule->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $this->assertObjectEqualsEntity($response->getBody()->data, $rule, $adapter);

        //fetch rule that doe not exists
        $response = $this->getRule($farmRole->id, FarmRoleScript::findOne([], null, ['id' => false])->id + 1);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //fetch rule with missmatch farm role id
        $response = $this->getRule(FarmRole::findOne([], null, ['id' => false])->id + 1, $rule->id);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //test have access to all listed rules
        $rules = $this->listRules($farmRole->id);
        foreach ($rules as $rule) {
            $this->assertTrue(FarmRoleScript::findPk($rule->id)->hasAccessPermissions($user));
        }

        //test invalid filters
        $url = self::getUserApiUrl("/farm-roles/{$farmRole->id}/orchestration-rules/");
        $response = $this->request($url, Request::METHOD_GET, ['foo' => 'bar']);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);
        $response = $this->request($url, Request::METHOD_GET, ['scope' => 'foobar']);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //delete script
        /* @var $rule FarmRoleScript */
        $rule = static::createEntity(new FarmRoleScript(), [
            'farmRoleId' => $farmRole->id,
            'scriptId' => $script->id,
            'farmId' => $farm->id
        ]);

        $response = $this->deleteRule($farmRole->id, $rule->id);
        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        //delete script that does not exists
        $response = $this->deleteRule($farmRole->id, FarmRoleScript::findOne([], null, ['id' => false])->id + 1);
        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);
    }
}