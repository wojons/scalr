<?php
/**
 * Created by PhpStorm.
 * User: andriy
 * Date: 24.12.15
 * Time: 20:39
 */

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\Rest\Http\Request;
use Scalr\Model\Entity\AccountScript;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;
use Scalr\Model\Entity\Account\User;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Tests\Functional\Api\ScriptsTestCase;
use Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRules\AccountScriptAdapter;
use Scalr\Api\DataType\ErrorMessage;

/**
 * Class AccountScriptTest
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (24.12.2015)
 */
class AccountScriptTest extends ScriptsTestCase
{


    public function ruleToDelete($ruleId)
    {
        static::toDelete('Scalr\Model\Entity\AccountScript', $ruleId);
    }

    /**
     * @param int $ruleId
     *
     * @return ApiTestResponse
     */
    public function getRule($ruleId)
    {
        $uri = self::getAccountApiUrl("/orchestration-rules/{$ruleId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param array $ruleData
     *
     * @return ApiTestResponse
     */
    public function postRule(array $ruleData)
    {
        $uri = self::getAccountApiUrl("/orchestration-rules");
        return $this->request($uri, Request::METHOD_POST, [], $ruleData);
    }

    /**
     * @param int $ruleId
     * @param array $scriptData
     *
     * @return ApiTestResponse
     */
    public function modifyRule($ruleId, $scriptData)
    {
        $uri = self::getAccountApiUrl("/orchestration-rules/{$ruleId}");

        return $this->request($uri, Request::METHOD_PATCH, [], $scriptData);
    }

    /**
     * @param int $ruleId
     *
     * @return ApiTestResponse
     */
    public function deleteRule($ruleId)
    {
        $uri = self::getAccountApiUrl("/orchestration-rules/{$ruleId}");

        return $this->request($uri, Request::METHOD_DELETE);
    }


    /**
     * @param array $filters
     *
     * @return array
     */
    public function listRules(array $filters = [])
    {
        $envelope = null;
        $rules = [];
        $uri = self::getAccountApiUrl("/orchestration-rules/");

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


    public function testComplex()
    {
        /* @var Script $script */
        $script = static::generateScripts([['os' => 'linux']])[0];
        /* @var ScriptVersion $version */
        $version = static::generateVersions($script, [['content' => '#!/bin/sh']])[0];

        $adapter = $this->getAdapter('OrchestrationRules\AccountScript');

        /* @var User $user */
        $user = $this->getUser();

        static::createEntity(new AccountScript(), [
            'accountId' => $user->getAccountId(),
            'scriptId' => $script->id
        ]);

        $filterable = $adapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];
        $rules = $this->listRules();

        foreach ($rules as $rule) {
            foreach ($filterable as $property) {
                $filterValue = $rule->{$property};
                $listResult = $this->listRules([$property => $filterValue]);
                if (!static::isRecursivelyEmpty($filterValue)) {
                    foreach ($listResult as $filtered) {
                        $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                    }
                }
            }
            $response = $this->getRule($rule->id);
            $this->assertEquals(200, $response->status, $this->printResponseError($response));
            $dbRule = AccountScript::findPk($rule->id);
            $this->assertObjectEqualsEntity($response->getBody()->data, $dbRule, $adapter);
        }

        $scalrAccountcriptData = [
            'trigger' => [
                'triggerType' => AccountScriptAdapter::TRIGGER_SINGLE_EVENT,
                'event' => [
                    'id' => 'HostInit'
                ]
            ],
            'target' => [
                'targetType' => AccountScriptAdapter::TARGET_NAME_NULL
            ],
            'action' => [
                'actionType' => AccountScriptAdapter::ACTION_SCRIPT,
                'scriptVersion' => [
                    'script' => [
                        'id' => $script->id
                    ],
                    'version' => $version->version
                ]
            ]
        ];

        $localAccountScriptData = [
            'trigger' => [
                'triggerType' => AccountScriptAdapter::TRIGGER_ALL_EVENTS
            ],
            'target' => [
                'targetType' => AccountScriptAdapter::TARGET_NAME_NULL
            ],
            'action' => [
                'actionType' => AccountScriptAdapter::ACTION_URI,
                'path' => 'https://example.com'
            ]
        ];

        //post scalr rule
        $response = $this->postRule($scalrAccountcriptData);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        /* @var $rule AccountScript */
        $rule = AccountScript::findPk($ruleId);

        $this->assertNotEmpty($rule);

        $this->ruleToDelete($ruleId);

        $this->assertObjectEqualsEntity($scalrAccountcriptData, $rule, $adapter);

        //post local rule
        $response = $this->postRule($localAccountScriptData);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        /* @var $rule AccountScript */
        $rule = AccountScript::findPk($ruleId);

        $this->assertNotEmpty($rule);

        $this->ruleToDelete($ruleId);

        $this->assertObjectEqualsEntity($localAccountScriptData, $rule, $adapter);

        //post rule already existing
        $data = $localAccountScriptData;
        $data['id'] = $ruleId;

        $response = $this->postRule($data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $ruleId = $response->getBody()->data->id;

        $this->ruleToDelete($ruleId);

        $this->assertNotEquals($data['id'], $ruleId);

        //post rule with script that does not exists
        $data = $scalrAccountcriptData;
        $data['action']['scriptVersion']['script']['id'] = Script::findOne([], null, ['id' => true])->id + 1;

        $response = $this->postRule($data);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //post rule with version that does not exists
        $data = $scalrAccountcriptData;
        $data['action']['scriptVersion']['version'] = Script::findPk($data['action']['scriptVersion']['script']['id'])->getLatestVersion()->version + 1;

        $response = $this->postRule($data);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //post rule with properties that not existing
        $data = $scalrAccountcriptData;
        $data['foo'] = 'bar';

        $response = $this->postRule($data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //post rule without required fields
        $data = $localAccountScriptData;
        unset($data['action']);

        $response = $this->postRule($data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //post rule with invalid field
        $data = $localAccountScriptData;
        $data['action'] = '';

        $response = $this->postRule($data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //modify rule
        //TODO::ape add modify rule

        //fetch rule
        $response = $this->getRule($rule->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $this->assertObjectEqualsEntity($response->getBody()->data, $rule, $adapter);

        //fetch rule that doe not exists
        $response = $this->getRule(AccountScript::findOne([], null, ['id' => false])->id + 1);
        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //test have access to all listed rules
        $rules = $this->listRules();
        foreach ($rules as $rule) {
            $this->assertTrue(AccountScript::findPk($rule->id)->hasAccessPermissions($user));
        }

        //test invalid filters
        $url = self::getAccountApiUrl("/orchestration-rules/");
        $response = $this->request($url, Request::METHOD_GET, ['foo' => 'bar']);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);
        $response = $this->request($url, Request::METHOD_GET, ['scope' => 'foobar']);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        /* @var $rule AccountScript */
        $rule = static::createEntity(new AccountScript(), [
            'accountId' => $user->getAccountId(),
            'scriptId' => $script->id
        ]);

        $response = $this->deleteRule($rule->id);
        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        //delete script that does not exists
        $response = $this->deleteRule(AccountScript::findOne([], null, ['id' => false])->id + 1);
        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);
    }
}