<?php
namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Http\Request;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\EventDefinition;
use Scalr\Service\Aws;
use Scalr\Tests\Functional\Api\ApiTestCase;

/**
 * EventsTest
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (07.05.2015)
 */
class EventsTest extends ApiTestCase
{

    public function eventToDelete($eventId)
    {
        static::$testData['Scalr\Model\Entity\EventDefinition'][] = $eventId;
    }

    public function getCriteria($environment = false)
    {
        $criteria = [
            ['$and' => [['envId' => null], ['accountId' => null]]],
            ['$and' => [['envId' => null], ['accountId' => $this->getUser()->accountId]]]
        ];

        if ($environment) {
            $criteria[] = [
                '$and' => [['envId' => $this->getEnvironment()->id], ['accountId' => $this->getUser()->accountId]]
            ];
        }

        return [[ '$or' => $criteria ]];
    }

    public function postEvent(array $eventData, $environment = false)
    {
        $uri = self::getUserApiUrl('/events', $environment);

        $response = $this->request($uri, Request::METHOD_POST, [], $eventData);

        $body = $response->getBody();

        if ($response->status == 201 && isset($body->data->id)) {
            $criteria = $this->getCriteria($environment === null);

            $criteria[] = [ 'name' => $body->data->id ];

            $this->eventToDelete(EventDefinition::findOne($criteria)->id);
        }

        return $response;
    }

    /**
     * @test
     */
    public function testEventsFunctional()
    {
        $db = \Scalr::getDb();
        $testName = str_replace('-', '', static::getTestName());

        $events = null;
        $uri = self::getUserApiUrl('/events', false);

        static::createEntity(new EventDefinition(), [
            'name' => 'testAccount',
            'description' => 'testAccount',
            'accountId' => $this->getUser()->getAccountId()
        ]);

        // test describe pagination
        do {
            $query = [];

            if (isset($events->pagination->next)) {
                $parts = parse_url($events->pagination->next);
                parse_str($parts['query'], $query);
            }

            $describe = $this->request($uri, Request::METHOD_GET, $query);
            $this->assertDescribeResponseNotEmpty($describe);

            $this->assertNotEmpty($describe->getBody());

            $events = $describe->getBody();

            foreach ($events->data as $event) {
                $this->assertEventObjectNotEmpty($event);

                if ($event->id == $testName) {
                    $delete = $this->request($uri . '/' . $event->id, Request::METHOD_DELETE);
                    $this->assertEquals(200, $delete->status);
                }
            }
        } while (!empty($events->pagination->next));

        // test create action
        $create = $this->postEvent([]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');

        $create = $this->postEvent(['id' => $testName, 'invalid' => 'value']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $create = $this->postEvent(['scope' => ScopeInterface::SCOPE_ACCOUNT]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Required field');

        $create = $this->postEvent(['id' => 'invalid*^']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid id of the Event');

        $create = $this->postEvent(['id' => $testName, 'description' => '<br>tags']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid description');

        $create = $this->postEvent([
            'id' => $testName,
            'description' => $testName,
            'scope' => ScopeInterface::SCOPE_ACCOUNT
        ]);

        $createSame = $this->postEvent([
            'id' => $testName,
            'description' => $testName,
            'scope' => ScopeInterface::SCOPE_ACCOUNT
        ]);

        $this->assertErrorMessageContains($createSame, 409, ErrorMessage::ERR_UNICITY_VIOLATION);

        //test event with same id already exists in other scope
        static::createEntity(new EventDefinition(), [
            'name' => 'testEnvAccount',
            'description' => 'testEnvAccount',
            'envId' => $this->getEnvironment()->id,
            'accountId' => $this->getUser()->getAccountId()
        ]);

        $scopeConflict = $this->postEvent([
            'id' => 'testEnvAccount',
            'description' => 'testEnvAccount',
            'scope' => ScopeInterface::SCOPE_ACCOUNT
        ]);

        $this->assertErrorMessageContains($scopeConflict, 409, ErrorMessage::ERR_UNICITY_VIOLATION);

        $body = $create->getBody();
        $this->assertEquals(201, $create->response->getStatus());
        $this->assertFetchResponseNotEmpty($create);
        $this->assertEventObjectNotEmpty($body->data);

        $this->assertNotEmpty($body->data->id);
        $this->assertEquals($testName, $body->data->id);
        $this->assertEquals($testName, $body->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ACCOUNT, $body->data->scope);

        // test filtering
        $describe = $this->request($uri, Request::METHOD_GET, ['description' => $testName]);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Unsupported filter');

        $describe = $this->request($uri, Request::METHOD_GET, ['scope' => 'wrong<br>']);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid scope value');

        $describe = $this->request($uri, Request::METHOD_GET, ['scope' => ScopeInterface::SCOPE_ACCOUNT]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertEventObjectNotEmpty($data);
            $this->assertEquals(ScopeInterface::SCOPE_ACCOUNT, $data->scope);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['id' => $testName]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertEventObjectNotEmpty($data);
            $this->assertEquals($testName, $data->id);
        }

        // test fetch action
        $eventId = $body->data->id;

        $fetch = $this->request($uri . '/' . $eventId . 'invalid', Request::METHOD_GET);
        $this->assertErrorMessageContains($fetch, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND, 'The Event either does not exist');

        $fetch = $this->request($uri . '/' . $eventId, Request::METHOD_GET);

        $fetchBody = $fetch->getBody();

        $this->assertEquals(200, $fetch->response->getStatus());
        $this->assertFetchResponseNotEmpty($fetch);
        $this->assertEventObjectNotEmpty($fetchBody->data);

        $this->assertEquals($testName, $fetchBody->data->id);
        $this->assertEquals($testName, $fetchBody->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ACCOUNT, $fetchBody->data->scope);

        // test modify action
        $modify = $this->request($uri . '/' . $eventId, Request::METHOD_PATCH);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');

        $scalrEventId = $db->GetOne("SELECT e.name FROM event_definitions e WHERE e.env_id IS NULL AND e.account_id IS NULL");

        if (!empty($scalrEventId)) {
            $fetch = $this->request($uri . '/' . $scalrEventId, Request::METHOD_PATCH, [], ['description' => '']);
            $this->assertErrorMessageContains($fetch, 403, ErrorMessage::ERR_SCOPE_VIOLATION);
        }

        $modify = $this->request($uri . '/' . $eventId, Request::METHOD_PATCH, [], ['scope' => ScopeInterface::SCOPE_ENVIRONMENT]);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set the property');

        $modify = $this->request($uri . '/' . $eventId, Request::METHOD_PATCH, [], ['description' => '']);

        $modifyBody = $modify->getBody();

        $this->assertEquals(200, $modify->response->getStatus());
        $this->assertFetchResponseNotEmpty($modify);
        $this->assertEventObjectNotEmpty($modifyBody->data);

        $this->assertEquals($testName, $modifyBody->data->id);
        $this->assertEquals('', $modifyBody->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ACCOUNT, $modifyBody->data->scope);

        // test delete action
        if (!empty($scalrEventId)) {
            $delete = $this->request($uri . '/' . $scalrEventId, Request::METHOD_DELETE);
            $this->assertErrorMessageContains($delete, 403, ErrorMessage::ERR_SCOPE_VIOLATION);
        }

        $delete = $this->request($uri . '/' . $eventId, Request::METHOD_DELETE);
        $this->assertEquals(200, $delete->status);
    }

    /**
     * @test
     * @depends testEventsFunctional
     */
    public function testEnvironmentEventFuncional()
    {
        $db = \Scalr::getDb();
        $testName = str_replace('-', '', static::getTestName());

        $events = null;
        $uri = self::getUserApiUrl('/events');

        static::createEntity(new EventDefinition(), [
            'name' => 'testEnvironment',
            'description' => 'testEnvironment',
            'envId' => $this->getEnvironment()->id
        ]);

        // test describe pagination
        do {
            $query = [];

            if (isset($events->pagination->next)) {
                $parts = parse_url($events->pagination->next);
                parse_str($parts['query'], $query);
            }

            $describe = $this->request($uri, Request::METHOD_GET, $query);
            $this->assertDescribeResponseNotEmpty($describe);

            $this->assertNotEmpty($describe->getBody());

            $events = $describe->getBody();

            foreach ($events->data as $event) {
                $this->assertEventObjectNotEmpty($event);

                if ($event->id == $testName) {
                    $delete = $this->request($uri . '/' . $event->id, Request::METHOD_DELETE);
                    $this->assertEquals(200, $delete->status, $this->printResponseError($delete));
                }
            }
        } while (!empty($events->pagination->next));

        // test create action
        $create = $this->postEvent([], null);
        $create = $this->request($uri, Request::METHOD_POST);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');

        $create = $this->postEvent(['id' => $testName, 'invalid' => 'value'], null);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $create = $this->postEvent(['scope' => ScopeInterface::SCOPE_ENVIRONMENT], null);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Required field');

        $create = $this->postEvent(['id' => 'invalid*^'], null);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid id of the Event');

        $create = $this->postEvent(['id' => $testName, 'description' => '<br>tags'], null);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid description');

        $create = $this->postEvent([
            'id' => $testName,
            'description' => $testName,
            'scope' => ScopeInterface::SCOPE_ENVIRONMENT
        ], null);

        $createSame = $this->postEvent([
            'id' => $testName,
            'description' => $testName,
            'scope' => ScopeInterface::SCOPE_ENVIRONMENT
        ], null);

        $this->assertErrorMessageContains($createSame, 409, ErrorMessage::ERR_UNICITY_VIOLATION);

        $body = $create->getBody();
        $this->assertEquals(201, $create->response->getStatus());
        $this->assertFetchResponseNotEmpty($create);
        $this->assertEventObjectNotEmpty($body->data);

        $this->assertNotEmpty($body->data->id);
        $this->assertEquals($testName, $body->data->id);
        $this->assertEquals($testName, $body->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $body->data->scope);

        // test filtering
        $describe = $this->request($uri, Request::METHOD_GET, ['description' => $testName]);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Unsupported filter');

        $describe = $this->request($uri, Request::METHOD_GET, ['scope' => 'wrong<br>']);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid scope value');

        $describe = $this->request($uri, Request::METHOD_GET, ['scope' => ScopeInterface::SCOPE_ENVIRONMENT]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertEventObjectNotEmpty($data);
            $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $data->scope);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['id' => $testName]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertEventObjectNotEmpty($data);
            $this->assertEquals($testName, $data->id);
        }

        // test fetch action
        $eventId = $body->data->id;

        $fetch = $this->request($uri . '/' . $eventId . 'invalid', Request::METHOD_GET);
        $this->assertErrorMessageContains($fetch, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND, 'The Event either does not exist');

        $fetch = $this->request($uri . '/' . $eventId, Request::METHOD_GET);

        $fetchBody = $fetch->getBody();

        $this->assertEquals(200, $fetch->response->getStatus());
        $this->assertFetchResponseNotEmpty($fetch);
        $this->assertEventObjectNotEmpty($fetchBody->data);

        $this->assertEquals($testName, $fetchBody->data->id);
        $this->assertEquals($testName, $fetchBody->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $fetchBody->data->scope);

        // test modify action
        $modify = $this->request($uri . '/' . $eventId, Request::METHOD_PATCH);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');

        $accountEventId = $db->GetOne("SELECT e.name FROM event_definitions e WHERE e.env_id IS NULL AND e.account_id IS NOT NULL");

        if (!empty($accountEventId)) {
            $fetch = $this->request($uri . '/' . $accountEventId, Request::METHOD_PATCH, [], ['description' => '']);
            $this->assertErrorMessageContains($fetch, 403, ErrorMessage::ERR_SCOPE_VIOLATION);
        }

        $modify = $this->request($uri . '/' . $eventId, Request::METHOD_PATCH, [], ['scope' => ScopeInterface::SCOPE_ACCOUNT]);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set the property');

        $modify = $this->request($uri . '/' . $eventId, Request::METHOD_PATCH, [], ['description' => '']);

        $modifyBody = $modify->getBody();

        $this->assertEquals(200, $modify->response->getStatus());
        $this->assertFetchResponseNotEmpty($modify);
        $this->assertEventObjectNotEmpty($modifyBody->data);

        $this->assertEquals($testName, $modifyBody->data->id);
        $this->assertEquals('', $modifyBody->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $modifyBody->data->scope);

        // test delete action
        if (!empty($accountEventId)) {
            $delete = $this->request($uri . '/' . $accountEventId, Request::METHOD_DELETE);
            $this->assertErrorMessageContains($delete, 403, ErrorMessage::ERR_SCOPE_VIOLATION);
        }

        $delete = $this->request($uri . '/' . $eventId, Request::METHOD_DELETE);
        $this->assertEquals(200, $delete->status);
    }

    /**
     * Asserts if event object has all properties
     *
     * @param object $data     Single event item
     */
    public function assertEventObjectNotEmpty($data)
    {
        $this->assertObjectHasAttribute('id', $data);
        $this->assertObjectHasAttribute('description', $data);
        $this->assertObjectHasAttribute('scope', $data);
    }

}