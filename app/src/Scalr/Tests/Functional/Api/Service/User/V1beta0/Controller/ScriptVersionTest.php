<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\ScriptVersionAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;
use Scalr\Model\Objects\BaseAdapter;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use Scalr\Tests\Functional\Api\ScriptsTestCase;

/**
 * ScriptVersion Test
 *
 * @author N.V.
 */
class ScriptVersionTest extends ScriptsTestCase
{

    /**
     * @param int $scriptId
     * @param int $version
     *
     * @return ApiTestResponse
     */
    public function getVersion($scriptId, $version)
    {
        $uri = self::getUserApiUrl("/scripts/{$scriptId}/script-versions/{$version}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param int $scriptId
     * @param array $filters
     *
     * @return array
     */
    public function listVersions($scriptId, array $filters = [])
    {
        $envelope = null;
        $versions = [];
        $uri = self::getUserApiUrl("/scripts/{$scriptId}/script-versions/");

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

            $versions[] = $envelope->data;
        } while (!empty($envelope->pagination->next));

        return call_user_func_array('array_merge', $versions);
    }

    /**
     * @param int $scriptId
     * @param array $versionData
     *
     * @return ApiTestResponse
     */
    public function postVersion($scriptId, $versionData)
    {
        $uri = self::getUserApiUrl("/scripts/{$scriptId}/script-versions");

        return $this->request($uri, Request::METHOD_POST, [], $versionData);
    }

    /**
     * @param int $scriptId
     * @param int $version
     * @param array $versionData
     *
     * @return ApiTestResponse
     */
    public function modifyVersion($scriptId, $version, $versionData)
    {
        $uri = self::getUserApiUrl("/scripts/{$scriptId}/script-versions/{$version}");

        return $this->request($uri, Request::METHOD_PATCH, [], $versionData);
    }

    /**
     * @param int $scriptId
     *
     * @param int $version
     *
     * @return ApiTestResponse
     */
    public function deleteVersion($scriptId, $version)
    {
        $uri = self::getUserApiUrl("/scripts/{$scriptId}/script-versions/{$version}");

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

        /* @var $script Script */
        $script = static::createEntity(new Script(), [
            'name' => "{$this->uuid}-script",
            'description' => "{$this->uuid}-description",
            'envId' => $environment->id,
            'createdById' => $user->getId()
        ]);

        //post script version
        $data = [ 'body' => '#!cmd' ];
        $response = $this->postVersion($script->id, $data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $versionNumber = $response->getBody()->data->version;

        /* @var $version ScriptVersion */
        $version = ScriptVersion::findPk($script->id, $versionNumber);

        $this->assertNotEmpty($version);

        $this->assertObjectEqualsEntity($data, $version);

        //post script version already existing
        $data = [
            'version' => $version->version,
            'body' => '#!/bin/sh'
        ];

        $response = $this->postVersion($script->id, $data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $versionNumber = $response->getBody()->data->version;

        $this->assertNotEquals($data['version'], $versionNumber);

        //post script with properties that not existing
        $data = [ 'foo' => 'bar' ];
        $response = $this->postVersion($script->id, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //post version to scalr-scoped script
        /* @var $scalrScript Script */
        $scalrScript = static::createEntity(new Script(), [
            'name' => "{$this->uuid}-script-scalr-scoped",
            'description' => "{$this->uuid}-description-scalr-scoped",
            'createdById' => $user->getId()
        ]);

        $data = [ 'body' => '#!/bin/sh' ];
        $response = $this->postVersion($scalrScript->id, $data);

        $this->assertErrorMessageContains($response, 403, ErrorMessage::ERR_PERMISSION_VIOLATION);

        //test script fetch
        $response = $this->getVersion($script->id, $version->version);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $this->assertObjectEqualsEntity($response->getBody()->data, $version);

        //test fetch script that doe not exists
        $response = $this->getVersion($script->id, $script->getLatestVersion()->version + 1);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //modify script version
        $data = [ 'body' => '#!/bin/bash' ];
        $response = $this->modifyVersion($script->id, $version->version, $data);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $this->assertObjectEqualsEntity($response->getBody()->data, ScriptVersion::findPk($script->id, $version->version));

        //modify property that does not exists
        $data = [ 'foo' => 'bar' ];
        $response = $this->modifyVersion($script->id, $version->version, $data);

        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //modify properties that not alterable
        $scriptVersionAdapter = new ScriptVersionAdapter($fictionController);
        $adapterRules = $scriptVersionAdapter->getRules();

        $publicProperties = $adapterRules[BaseAdapter::RULE_TYPE_TO_DATA];
        $alterableProperties = $adapterRules[ApiEntityAdapter::RULE_TYPE_ALTERABLE];
        $nonAlterableProperties = array_diff(array_values($publicProperties), $alterableProperties);

        foreach ($nonAlterableProperties as $property) {
            if (in_array($property, [ 'id', 'version' ])) {
                continue;
            }

            $data = [ $property => 'foo' ];
            $response = $this->modifyVersion($script->id, $version->version, $data);

            $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);
        }

        //modify script that does not exists
        $data = [ 'body' => '#!powershell' ];
        $response = $this->modifyVersion($script->id, $script->getLatestVersion()->version + 1, $data);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        //modify Scalr-scoped script version
        /* @var $scalrVersion ScriptVersion */
        $scalrVersion = static::createEntity(new ScriptVersion(), [
            'scriptId' => $scalrScript->id,
            'version' => 2,
            'content' => '#!/bin/sh'
        ]);

        //modify Scalr-scoped script version
        $data = [ 'body' => '#!cmd' ];
        $response = $this->modifyVersion($scalrScript->id, $scalrVersion->version, $data);

        $this->assertErrorMessageContains($response, 403, ErrorMessage::ERR_PERMISSION_VIOLATION, 'Insufficient permissions');

        /* @var $version ScriptVersion */
        $version = static::createEntity(new ScriptVersion(), [
            'scriptId' => $script->id,
            'version' => $script->getLatestVersion()->version + 1,
            'content' => '#!foobar'
        ]);

        //test have access to all listed scripts versions
        $versions = $this->listVersions($script->id);

        foreach ($versions as $version) {
            $this->assertTrue(ScriptVersion::findPk($script->id, $version->version)->hasAccessPermissions($user));
        }

        $listUri = static::getUserApiUrl("/scripts/{$script->id}/script-versions/");

        //test list script versions filters
        $filterable = $scriptVersionAdapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];

        /* @var $version ScriptVersion */
        foreach ($versions as $version) {
            foreach ($filterable as $property) {
                $filterValue = $version->{$property};

                $listResult = $this->listVersions($script->id, [ $property => $filterValue ]);

                if (!static::isRecursivelyEmpty($filterValue)) {
                    foreach ($listResult as $filtered) {
                        $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                    }
                }
            }

            $response = $this->getVersion($script->id, $version->version);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $dbScriptVersions = ScriptVersion::findPk($script->id, $version->version);

            $this->assertObjectEqualsEntity($response->getBody()->data, $dbScriptVersions, $scriptVersionAdapter);
        }

        //test invalid filters
        $response = $this->request($listUri, Request::METHOD_GET, [ 'foo' => 'bar' ]);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //delete script version
        /* @var $version ScriptVersion */
        $version = static::createEntity(new ScriptVersion(), [
            'scriptId' => $script->id,
            'version' => $script->getLatestVersion()->version + 1,
            'content' => '#!/bin/sh foobar'
        ]);

        $response = $this->deleteVersion($script->id, $version->version);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        //delete scalr-scoped script version
        $response = $this->deleteVersion($scalrVersion->scriptId, $scalrVersion->version);

        $this->assertErrorMessageContains($response, 403, ErrorMessage::ERR_PERMISSION_VIOLATION);

        //delete script version that does not exists
        $response = $this->deleteVersion($script->id, $script->getLatestVersion()->version + 1);

        $this->assertErrorMessageContains($response, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        $scripts = Script::find([['$or' => [['accountId' => $user->accountId], ['accountId' => null]]],['$or' => [['envId' => $environment->id], ['envId' => null]]]]);

        foreach ($scripts as $script) {
            //test have access to all listed scripts versions
            $versions = $this->listVersions($script->id);

            foreach ($versions as $version) {
                $version = ScriptVersion::findPk($script->id, $version->version);
                $this->assertTrue($version->hasAccessPermissions($user));
            }

            if ($version->getScope() !== ScopeInterface::SCOPE_ENVIRONMENT) {
                $response = $this->postVersion($version->scriptId, ['body' => '#!/bin/sh' . $this->getTestName()]);
                $this->assertErrorMessageContains($response, 403, ErrorMessage::ERR_PERMISSION_VIOLATION);
            }
        }
    }
}