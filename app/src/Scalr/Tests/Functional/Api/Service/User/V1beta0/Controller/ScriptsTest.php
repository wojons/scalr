<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\ScriptAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Script;
use Scalr\Model\Objects\BaseAdapter;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use Scalr\Tests\Functional\Api\ScriptsTestCase;

/**
 * Scripts Test
 *
 * @author N.V.
 */
class ScriptsTest extends ScriptsTestCase
{

    public function scriptToDelete($scriptId)
    {
        static::toDelete(Script::class, [$scriptId]);
    }

    /**
     * @param int $scriptId
     *
     * @return ApiTestResponse
     */
    public function getScript($scriptId)
    {
        $uri = self::getUserApiUrl("/scripts/{$scriptId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param array $filters
     *
     * @return array
     */
    public function listScripts(array $filters = [])
    {
        $envelope = null;
        $scripts = [];
        $uri = self::getUserApiUrl('/scripts');

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

            $scripts[] = $envelope->data;
        } while (!empty($envelope->pagination->next));

        return call_user_func_array('array_merge', $scripts);
    }

    /**
     * @param array $scriptData
     *
     * @return ApiTestResponse
     */
    public function postScript(array &$scriptData)
    {
        $scriptData['name'] = "{$this->uuid}-script-name-{$scriptData['name']}";
        $scriptData['description'] = "{$this->uuid}-script-description-{$scriptData['description']}";

        $uri = self::getUserApiUrl('/scripts');
        return $this->request($uri, Request::METHOD_POST, [], $scriptData);
    }

    /**
     * @param int $scriptId
     * @param array $scriptData
     *
     * @return ApiTestResponse
     */
    public function modifyScript($scriptId, $scriptData)
    {
        $uri = self::getUserApiUrl("/scripts/{$scriptId}");

        return $this->request($uri, Request::METHOD_PATCH, [], $scriptData);
    }

    /**
     * @param int $scriptId
     *
     * @return ApiTestResponse
     */
    public function deleteScript($scriptId)
    {
        $uri = self::getUserApiUrl("/scripts/{$scriptId}");

        return $this->request($uri, Request::METHOD_DELETE);
    }

    /**
     * @test
     *
     * @throws \Scalr\Exception\ModelException
     */
    public function testComplex()
    {
        $user = $this->getUser();
        $environment = $this->getEnvironment();
        $fictionController = new ApiController();

        //test script post
        $data = [
            'name' => 'test-post',
            'description' => 'test-post',
            'timeoutDefault' => 1000,
            'blockingDefault' => true,
            'osType' => 'linux',
        ];
        $response = $this->postScript($data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $scriptId = $response->getBody()->data->id;

        /* @var $script Script */
        $script = Script::findPk($scriptId);

        $this->assertNotEmpty($script);

        $this->scriptToDelete($scriptId);

        $this->assertObjectEqualsEntity($data, $script);

        //post environment-scoped script
        $data['scope'] = ScopeInterface::SCOPE_ENVIRONMENT;

        $response = $this->postScript($data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $scriptId = $response->getBody()->data->id;

        $script = Script::findPk($scriptId);

        $this->assertNotEmpty($script);

        $this->scriptToDelete($scriptId);

        $this->assertObjectEqualsEntity($data, $script);

        //post script already existing
        $data = [
            'id' => $script->id,
            'name' => 'test-post-existing',
            'description' => 'test-post-existing',
            'osType' => 'linux',
            'scope' => ScopeInterface::SCOPE_ENVIRONMENT
        ];
        $response = $this->postScript($data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $scriptId = $response->getBody()->data->id;

        $this->scriptToDelete($scriptId);

        $this->assertNotEquals($data['id'], $scriptId);

        unset($data['id']);
        $scriptData = $data;
        $scriptData['accountId'] = $user->getAccountId();
        $scriptData['envId'] = $environment->id;
        $scriptData['os'] = $data['osType'];
        unset($scriptData['osType'], $scriptData['scope']);
        $envScript = $this->createEntity(new Script(), $scriptData);

        //post script with name already exists in current (environment) scope
        $data['name'] = 'test-post-existing';
        $response = $this->postScript($data);

        $this->assertErrorMessageContains($response, 409, ErrorMessage::ERR_UNICITY_VIOLATION);

        $envScript->delete();

        //post script with properties that not existing
        $data = [
            'name' => 'test-post-not-existing-field',
            'description' => 'test-post-not-existing-field',
            'foo' => 'bar',
            'osType' => 'linux',
        ];
        $response = $this->postScript($data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //post script without required fields
        $data = [
            'name' => 'foobar',
            'description' => 'test-post-no-scope',
        ];
        $response = $this->postScript($data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //test script fetch
        $response = $this->getScript($script->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $this->assertObjectEqualsEntity($response->getBody()->data, $script);

        //test fetch script that doe not exists
        $response = $this->getScript(Script::findOne([], null, ['id' => false])->id + 1);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //test script modify
        $data = [
            'name' => 'test-modify',
            'description' => 'test-modify',
            'timeoutDefault' => 0,
            'blockingDefault' => false
        ];
        $response = $this->modifyScript($script->id, $data);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $this->assertObjectEqualsEntity($response->getBody()->data, Script::findPk($script->id));

        //modify property that does not exists
        $data = [ 'foo' => 'bar' ];
        $response = $this->modifyScript($script->id, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //modify properties that not alterable
        $scriptAdapter = new ScriptAdapter($fictionController);
        $adapterRules = $scriptAdapter->getRules();

        $publicProperties = $adapterRules[BaseAdapter::RULE_TYPE_TO_DATA];
        $alterableProperties = $adapterRules[ApiEntityAdapter::RULE_TYPE_ALTERABLE];
        $nonAlterableProperties = array_diff(array_values($publicProperties), $alterableProperties);

        foreach ($nonAlterableProperties as $property) {
            $data = [ $property => 'foo' ];
            $response = $this->modifyScript($script->id, $data);

            $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);
        }

        //modify script that does not exists
        $data = [ 'name' => 'test-modify-not-found' ];
        $response = $this->modifyScript(Script::findOne([], null, ['id' => false])->id + 1, $data);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //modify Scalr-scoped script
        /* @var $script Script */
        $script = static::createEntity(new Script(), [
            'name' => "{$this->uuid}-script",
            'description' => "{$this->uuid}-description",
            'createdById' => $user->getId()
        ]);

        //modify Scalr-scoped script
        $data = [ 'name' => 'test-modify-scalr-scoped' ];
        $response = $this->modifyScript($script->id, $data);

        $this->assertErrorMessageContains($response, 403, ErrorMessage::ERR_PERMISSION_VIOLATION, 'Insufficient permissions');

        /* @var $script Script */
        $script = static::createEntity(new Script(), [
            'name' => "{$this->uuid}-script",
            'description' => "{$this->uuid}-description",
            'accountId' => $user->getAccountId(),
            'createdById' => $user->getId()
        ]);

        //test have access to all listed scripts
        $scripts = $this->listScripts();

        foreach ($scripts as $script) {
            $this->assertTrue(Script::findPk($script->id)->hasAccessPermissions($user), "Script id: {$script->id}");
        }

        //test convertible data filters
        $mergedLists = array_merge(
            $this->listScripts([ 'scope' => ScopeInterface::SCOPE_SCALR ]),
            $this->listScripts([ 'scope' => ScopeInterface::SCOPE_ENVIRONMENT ]),
            $this->listScripts([ 'scope' => ScopeInterface::SCOPE_ACCOUNT ])
        );

        foreach ($mergedLists as $script) {
            $this->assertTrue(Script::findPk($script->id)->hasAccessPermissions($user), "Script id: {$script->id}");
        }

        //test list scripts filters
        $filterable = $scriptAdapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];

        /* @var $script Script */
        foreach ($scripts as $script) {
            foreach ($filterable as $property) {
                $filterValue = $script->{$property};

                $listResult = $this->listScripts([ $property => $filterValue ]);

                if (!static::isRecursivelyEmpty($filterValue)) {
                    foreach ($listResult as $filtered) {
                        $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                    }
                }
            }

            $response = $this->getScript($script->id);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $dbScript = Script::findPk($script->id);

            $this->assertObjectEqualsEntity($response->getBody()->data, $dbScript, $scriptAdapter);
        }

        //test have write access to environments and account scoped scripts
        foreach (array_merge(
                     $this->listScripts([ 'scope' => ScopeInterface::SCOPE_ENVIRONMENT ]),
                     $this->listScripts([ 'scope' => ScopeInterface::SCOPE_ACCOUNT ])
                 ) as $script) {
            $this->assertTrue(Script::findPk($script->id)->hasAccessPermissions($user, null, true));
        }

        $listUri = static::getUserApiUrl("/scripts");

        //test invalid filters
        $response = $this->request($listUri, Request::METHOD_GET, [ 'foo' => 'bar' ]);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //test invalid filters values
        $response = $this->request($listUri, Request::METHOD_GET, [ 'scope' => 'foobar' ]);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //delete script
        /* @var $script Script */
        $script = static::createEntity(new Script(), [
            'name' => "{$this->uuid}-script",
            'description' => "{$this->uuid}-description",
            'envId' => $environment->id,
            'createdById' => $user->getId()
        ]);

        $response = $this->deleteScript($script->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        //delete scalr-scoped script
        $scripts = $this->listScripts([ 'scope' => ScopeInterface::SCOPE_SCALR ]);

        $script = array_shift($scripts);

        $response = $this->deleteScript($script->id);

        $this->assertErrorMessageContains($response, 403, ErrorMessage::ERR_PERMISSION_VIOLATION);

        //delete script that does not exists
        $response = $this->deleteScript(Script::findOne([], null, ['id' => false])->id + 1);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);
    }
}