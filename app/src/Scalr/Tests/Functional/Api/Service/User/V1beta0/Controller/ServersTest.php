<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use DBServer;
use Exception;
use FarmTerminatedEvent;
use LOG_CATEGORY;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Http\Request;
use Scalr\Logger;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\Server;
use Scalr\Model\Entity\ServerProperty;
use Scalr\Observer\AbstractEventObserver;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use Scalr_Scaling_Manager;
use SERVER_PLATFORMS;
use SERVER_PROPERTIES;

/**
 * Servers test
 *
 * @author Vlad Dobrovolskiy v.dobrovolskiy@scalr.com
 */
class ServersTest extends ApiTestCase
{
    /**
     * Test cloud location
     */
    const TEST_REGION = 'us-east-1';

    /**
     * This test is skipped by drone
     */
    const TEST_TYPE = ApiTestCase::TEST_TYPE_CLOUD_DEPENDENT;

    /**
     * @var string
     */
    public $uuid;

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Functional\Api\ApiTestCase::$loggerConfiguration
     */
    protected static $loggerConfiguration = [
        LOG_CATEGORY::FARM           => Logger::LEVEL_ERROR,
        LOG_CATEGORY::SCALING        => Logger::LEVEL_ERROR,
        Scalr_Scaling_Manager::class => Logger::LEVEL_ERROR,
        AbstractEventObserver::class => Logger::LEVEL_ERROR
    ];

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\TestCase::tearDown()
     */
    protected function tearDown()
    {
        if (isset($this->governanceConfiguration)) {
            $this->restoreGovernanceConfiguration();
        }

        parent::tearDown();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\TestCase::tearDownAfterClass()
     */
    public static function tearDownAfterClass()
    {
        foreach (static::$testData as $rec) {
            if ($rec['class'] === Farm::class) {
                $entry = $rec['pk'];

                $farm = Farm::findPk(...$entry);
                /* @var $farm Farm */
                if (!empty($farm)) {
                    try {
                        \Scalr::FireEvent($farm->id, new FarmTerminatedEvent(
                            false,
                            false,
                            false,
                            false,
                            true,
                            static::$user->id
                        ));

                        foreach ($farm->servers as $server) {
                            try {
                                $dbServer = Server::findPk($server->serverId);
                                /* @var $dbServer Server */
                                $dbServer->terminate(Server::TERMINATE_REASON_FARM_TERMINATED, true, static::$user);
                            } catch (Exception $e) {
                                \Scalr::logException($e);
                            }

                            $server->delete();
                        }
                    } catch (Exception $e) {
                        \Scalr::logException($e);
                    }
                }
            }
        }

        parent::tearDownAfterClass();
    }

    public function __construct($name = null, $data = [], $dataName = null)
    {
        parent::__construct($name, $data, $dataName);

        $this->uuid = uniqid($this->getTestName());
    }

    /**
     * Fetches server's data
     *
     * @param string $serverId  Server's UUID
     *
     * @return ApiTestResponse
     */
    public function getServer($serverId)
    {
        $uri = self::getUserApiUrl("/servers/{$serverId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * Describes servers
     *
     * @param array $parent     optional    Farm or farm role that servers belong to
     * @param array $filters    optional    Filter values
     *
     * @return array
     */
    public function listServers(array $parent = [], array $filters = [])
    {
        $envelope = null;
        $servers = [];

        if (isset($parent['farmId'])) {
            $uri = self::getUserApiUrl("/farms/{$parent['farmId']}/servers");
        } else if (isset($parent['farmRoleId'])) {
            $uri = self::getUserApiUrl("/farm-roles/{$parent['farmRoleId']}/servers");
        } else {
            $uri = self::getUserApiUrl("/servers");
        }

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

            $servers[] = $envelope->data;
        } while (!empty($envelope->pagination->next));

        return call_user_func_array('array_merge', $servers);
    }

    /**
     * Terminates the server
     *
     * @param string $serverId  Server's UUID
     * @param bool   $force     optional
     *
     * @return ApiTestResponse
     */
    public function terminateServer($serverId, $force = false)
    {
        $uri = self::getUserApiUrl("/servers/{$serverId}/actions/terminate");

        return $this->request($uri, Request::METHOD_POST, [], ['force' => $force]);
    }

    /**
     * Suspends the server
     *
     * @param string $serverId  Server's UUID
     *
     * @return ApiTestResponse
     */
    public function suspendServer($serverId)
    {
        $uri = self::getUserApiUrl("/servers/{$serverId}/actions/suspend");

        return $this->request($uri, Request::METHOD_POST);
    }

    /**
     * Resumes the server
     *
     * @param string $serverId  Server's UUID
     *
     * @return ApiTestResponse
     */
    public function resumeServer($serverId)
    {
        $uri = self::getUserApiUrl("/servers/{$serverId}/actions/resume");

        return $this->request($uri, Request::METHOD_POST);
    }

    /**
     * Reboots the server
     *
     * @param string $serverId  Server's UUID
     * @param bool   $hard      optional
     *
     * @return ApiTestResponse
     */
    public function rebootServer($serverId, $hard = false)
    {
        $uri = self::getUserApiUrl("/servers/{$serverId}/actions/reboot");

        return $this->request($uri, Request::METHOD_POST, [], ['hard' => $hard]);
    }

    /**
     * @test
     * @functional
     */
    public function testComplex()
    {
        /* @var $farm Farm */
        $farm = $this->createTestFarm('server', ['base-ubuntu1404']);
        /* @var $farmRole FarmRole */
        $farmRole = $farm->farmRoles->current();

        $server = null;

        static::toDelete(Farm::class, [$farm->id]);

        $uri = self::getUserApiUrl("/farms/{$farm->id}/actions/launch");
        $this->request($uri, Request::METHOD_POST);

        for ($time = time(), $sleep = 50; time() - $time < 400 && (empty($server) || $server->status != Server::STATUS_RUNNING); $sleep += 50) {
            sleep($sleep);
            $testServers = $this->listServers(['farmId' => $farm->id]);

            if (count($testServers) > 0) {
                $server = reset($testServers);
                /* @var $server Server */
                continue;
            }
        }

        $this->assertNotEmpty($server);
        $this->assertEquals($server->status, 'running');

        $testDescribe = [['farmId' => $farm->id], ['farmRoleId' => $farmRole->id], ['serverId' => 'all']];
        // testing describe and fetch action
        foreach ($testDescribe as $value) {
            $servers = $this->listServers($value);

            $serverAdapter = $this->getAdapter('server');

            $filterable = $serverAdapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];

            foreach ($servers as $server) {
                /* @var $server Server */
                foreach ($filterable as $property) {
                    $filterValue = $server->{$property};

                    $listResult = $this->listServers($value, [$property => $filterValue]);

                    if (!static::isRecursivelyEmpty($filterValue)) {
                        foreach ($listResult as $filtered) {
                            $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                        }
                    }
                }

                $response = $this->getServer($server->id);

                $this->assertEquals(200, $response->status, $this->printResponseError($response));

                $dbServer = Server::findPk($server->id);

                $this->assertObjectEqualsEntity($response->getBody()->data, $dbServer, $serverAdapter);
            }
        }

        $entity = Server::findPk($server->id);
        /* @var $entity Server */
        $DBServerObject = $entity->__getDBServer();
        $oldModel = DBServer::LoadByID($server->id);

        $this->assertEquals($DBServerObject, $oldModel);

        $serverEntity = (new Server())->__getEntityFromDBServer($oldModel);

        $this->assertEquals($entity, $serverEntity);

        // testing reboot action
        $response = $this->rebootServer($server->id, true);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $data = $response->getBody()->data;
        $this->assertEquals($server->id, $data->id);

        $propertyReboot = ServerProperty::findOne([['serverId' => $server->id], ['name' => SERVER_PROPERTIES::REBOOTING]]);
        /* @var $propertyReboot ServerProperty */
        for ($time = time(), $sleep = 50; time() - $time < 300 && (!$propertyReboot || $propertyReboot->value); $sleep += 50) {
            $propertyReboot = ServerProperty::findOne([['serverId' => $server->id], ['name' => SERVER_PROPERTIES::REBOOTING]]);
        }

        $server = $this->waitForChanges($server->id, Server::STATUS_RUNNING);

        // testing suspend action
        $response = $this->suspendServer($server->id);
        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $data = $response->getBody()->data;
        $this->assertEquals($server->id, $data->id);
        $this->assertEquals($data->status, 'pending_suspend');

        $server = $this->waitForChanges($server->id, Server::STATUS_SUSPENDED, 600);

        // testing resume action
        $sleep = 0;

        do {
            try {
                $response = $this->resumeServer($server->id);

                if ($response->status == 200) {
                    break;
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), "is not in a state from which it can be started") !== false) {
                    $sleep += 60;
                    sleep(60);
                } else {
                    throw $e;
                }
            }
        } while ($sleep < 300);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $data = $response->getBody()->data;
        $this->assertEquals($server->id, $data->id);
        $this->assertEquals($data->status, 'resuming');

        $server = $this->waitForChanges($server->id, Server::STATUS_RUNNING);
    }

    /**
     * Creates new farm for testing purposes
     *
     * @param   string      $name       Farm name
     * @param   string[]    $rolesNames Roles names
     *
     * @return Farm
     */
    public function createTestFarm($name, array $rolesNames)
    {
        $user = $this->getUser();

        /* @var $farm Farm */
        $farm = static::createEntity(new Farm(), [
            'changedById' => $user->getId(),
            'name'        => "{$this->uuid}-{$name}-farm",
            'comments'    => "{$this->uuid}-description",
            'envId'       => $this->getEnvironment()->id,
            'accountId'   => $user->getAccountId(),
            'ownerId'     => $user->getId()
        ]);

        foreach ($rolesNames as $roleName) {
            /* @var $role Role */
            $role = Role::findOneByName($roleName);

            if (empty($role)) {
                $this->markTestSkipped("Not found suitable role, required role - 'base-ubuntu1404'");
            }

            /* @var $farmRole FarmRole */
            $farmRole = static::createEntity(new FarmRole(), [
                'farmId'        => $farm->id,
                'roleId'        => $role->id,
                'alias'         => 'test-launch-farm-role',
                'platform'      => SERVER_PLATFORMS::EC2,
                'cloudLocation' => static::TEST_REGION
            ]);

            /* @var $settings SettingsCollection */
            $settings = $farmRole->settings;
            $settings->saveSettings([
                FarmRoleSetting::INSTANCE_TYPE => 't1.micro',
                FarmRoleSetting::AWS_AVAIL_ZONE => '',
                FarmRoleSetting::SCALING_ENABLED => true,
                FarmRoleSetting::SCALING_MIN_INSTANCES => 1,
                FarmRoleSetting::SCALING_MAX_INSTANCES => 2
            ]);
        }

        return $farm;
    }

    /**
     * Waits until status changes
     *
     * @param string $serverId              Server's UUID
     * @param string $status                Expected Server's Status
     * @param int    $timeOut   optional    Waiting time
     * @return mixed
     */
    private function waitForChanges($serverId, $status, $timeOut = 300)
    {
        $status = lcfirst(str_replace(' ', '_', $status));

        $response = $this->getServer($serverId);
        $this->assertEquals(200, $response->status, $this->printResponseError($response));
        $server = $response->getBody()->data;

        for ($time = time(), $sleep = 50; time() - $time < $timeOut && $server->status != $status; $sleep += 50) {
            $response = $this->getServer($server->id);
            $server = $response->getBody()->data;
        }

        $this->assertEquals($server->status, $status);

        return $server;
    }

}