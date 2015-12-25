<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Exception;
use FARM_STATUS;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Model\Entity\FarmSetting;
use Scalr\Model\Entity\Role;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Ec2\DataType\VpcData;
use Scalr\Service\Aws\Ec2\DataType\VpcList;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use SERVER_PLATFORMS;
use Scalr_Governance;
use FarmTerminatedEvent;
use DBServer;
use Scalr\AuditLogger;

/**
 * Farms test
 *
 * @author N.V.
 */
class FarmsTest extends ApiTestCase
{

    /**
     * @see Aws::REGION_US_EAST_1
     */
    const TEST_REGION = 'us-east-1';

    public $uuid;

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

    public static function tearDownAfterClass()
    {
        ksort(static::$testData, SORT_REGULAR);
        foreach (static::$testData as $priority => $data) {
            foreach ($data as $class => $ids) {
                if ($class === 'Scalr\Model\Entity\Farm') {
                    $ids = array_unique($ids, SORT_REGULAR);

                    foreach ($ids as $entry) {
                        if (!empty($entry)) {
                            /* @var $farm Farm */
                            $farm = call_user_func_array([$class, 'findPk'], is_object($entry) ? [$entry] : (array) $entry);

                            if (!empty($farm)) {
                                try {
                                    $farm->checkLocked();

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
                                            $DBServer = DBServer::LoadByID($server->id);
                                            $DBServer->terminate(\DBServer::TERMINATE_REASON_FARM_TERMINATED, true, static::$user->id);
                                        } catch (Exception $e) {
                                            error_log("{$class}:\t" . $e->getMessage());
                                            error_log(print_r($entry, true));
                                        }

                                        $server->delete();
                                    }
                                    $farm->delete();
                                } catch (Exception $e) {
                                    error_log("{$class}:\t" . $e->getMessage());
                                    error_log(print_r($entry, true));
                                }
                            }
                        }
                    }

                    unset(static::$testData[$priority][$class]);
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

    public function farmToDelete($farmId)
    {
        static::toDelete('Scalr\Model\Entity\Farm', $farmId);
    }

    public function createTestProject()
    {
        $user = $this->getUser();

        /* @var $cc CostCentreEntity */
        $cc = $this->createEntity(new CostCentreEntity(), [
            'accountId' => $user->getAccountId(),
            'name' => $this->getTestName(),
            'createdById' => $user->id,
            'createdByEmail' => $user->email
        ], 2);

        $cc->setProperty(CostCentrePropertyEntity::NAME_BILLING_CODE, $this->getTestName());

        $cc->save();

        /* @var $project ProjectEntity */
        $project = $this->createEntity(new ProjectEntity(), [
            'name' => $this->getTestName(),
            'accountId' => $user->getAccountId(),
            'envId' => $this->getEnvironment()->id,
            'createdById' => $user->id,
            'createdByEmail' => $user->email,
            'ccId' => $cc->ccId
        ], 1);

        $project->setCostCenter($cc);
        $project->setProperty(ProjectPropertyEntity::NAME_BILLING_CODE, $this->getTestName());

        $project->save();

        return $project;
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
            'name' => "{$this->uuid}-{$name}-farm",
            'description' => "{$this->uuid}-description",
            'envId' => $this->getEnvironment()->id,
            'accountId' => $user->getAccountId(),
            'createdById' => $user->getId()
        ]);

        foreach ($rolesNames as $roleName) {
            /* @var $role Role */
            $role = Role::findOneByName($roleName);

            if (empty($role)) {
                $this->markTestSkipped("Not found suitable role, required role - 'base-ubuntu1404'");
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
        }

        return $farm;
    }

    /**
     * @param int $farmId
     *
     * @return ApiTestResponse
     */
    public function getFarm($farmId)
    {
        $uri = self::getUserApiUrl("/farms/{$farmId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param array $filters
     *
     * @return array
     */
    public function listFarms(array $filters = [])
    {
        $envelope = null;
        $farms = [];
        $uri = self::getUserApiUrl('/farms');

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

            $farms[] = $envelope->data;
        } while (!empty($envelope->pagination->next));

        return call_user_func_array('array_merge', $farms);
    }

    /**
     * @param array $farmData
     *
     * @return ApiTestResponse
     */
    public function postFarm(array &$farmData)
    {
        if (isset($farmData['name']) && is_string($farmData['name'])) {
            $farmData['name'] = "{$this->uuid}-farm-name-{$farmData['name']}";
        }

        $uri = self::getUserApiUrl('/farms');
        return $this->request($uri, Request::METHOD_POST, [], $farmData);
    }

    /**
     * @param int $farmId
     * @param array $farmData
     *
     * @return ApiTestResponse
     */
    public function modifyFarm($farmId, $farmData)
    {
        $uri = self::getUserApiUrl("/farms/{$farmId}");

        return $this->request($uri, Request::METHOD_PATCH, [], $farmData);
    }

    /**
     * @param int $farmId
     *
     * @return ApiTestResponse
     */
    public function deleteFarm($farmId)
    {
        $uri = self::getUserApiUrl("/farms/{$farmId}");

        return $this->request($uri, Request::METHOD_DELETE);
    }

    /**
     * @param $farmId
     *
     * @return ApiTestResponse
     */
    public function launchFarm($farmId)
    {
        $uri = self::getUserApiUrl("/farms/{$farmId}/actions/launch");

        return $this->request($uri, Request::METHOD_POST);
    }

    public function terminateFarm($farmId, $force = false)
    {
        $uri = self::getUserApiUrl("/farms/{$farmId}/actions/terminate");

        $params = [];

        if ($force) {
            $params['force'] = true;
        }

        return $this->request($uri, Request::METHOD_POST, $params);
    }

    /**
     * @test
     * @functional
     */
    public function testComplex()
    {
        $user = $this->getUser();
        $environment = $this->getEnvironment();
        $fictionController = new ApiController();

        //test farm post without required field
        $data = [
            'name' => 'test-post',
        ];

        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        $testProject = $this->createTestProject();

        //test farm post with wrong field value
        $data = [
            'name' => ['foo' => 'bar'],
            'project' => [ 'id' => $testProject->projectId ]
        ];

        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        $data = [
            'name' => (object) ['foo' => 'bar'],
            'project' => [ 'id' => $testProject->projectId ]
        ];

        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        $data = [
            'name' => ['foo' => (object) ['foo' => 'bar', 'bar' => ['foo', 'bar']]],
            'project' => [ 'id' => $testProject->projectId ]
        ];

        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        $data = [
            'project' => [ 'id' => $testProject->projectId ]
        ];

        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //test farm post
        $data = [
            'name' => 'test-post',
            'project' => [ 'id' => $testProject->projectId ],
            'description' => 'foobar',
            'launchOrder' => 'sequential',
            'timezone' => 'Europe/London',
        ];

        $region = self::TEST_REGION;
        /* @var $vpc VpcData */
        $vpc = \Scalr::getContainer()->aws($region, $environment)->ec2->vpc->describe()->current();

        if (isset($vpc->vpcId)) {
            $data['vpc'] = ['region' => $region, 'id' => $vpc->vpcId];
        }

        $owner = ['owner' => $this->getUser()->getId()];
        $userTeams = $this->getUser()->getTeams();

        if (count($userTeams)) {
            $teamOwner = ['teamOwner' => ['id' => reset($userTeams)['id']]];
        } else {
            $teamOwner = [];
        }

        $errorData = array_merge($data, $owner);

        $response = $this->postFarm($errorData);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        $data = array_merge($data, $teamOwner);

        $response = $this->postFarm($data);
        $this->assertPostFarms($response, $data);

        $farmId = $response->getBody()->data->id;

        /* @var $farm Farm */
        $farm = Farm::findPk($farmId);

        $this->assertNotEmpty($farm);

        $this->farmToDelete($farmId);

        $this->assertObjectEqualsEntity($data, $farm);

        //test post farm with name already exists
        $data = [
            'name' => 'test-post',
            'project' => [ 'id' => $testProject->projectId ]
        ];

        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 409, ErrorMessage::ERR_UNICITY_VIOLATION);

        foreach ($this->listFarms([ 'name' => $data['name'] ]) as $farm) {
            $response = $this->getFarm($farm->id);

            $this->assertEquals($farm, $response->getBody()->data);
        }

        //test Governance
        /* @var $vpcList VpcList */
        $vpcList = \Scalr::getContainer()->aws(self::TEST_REGION, $this->getEnvironment())->ec2->vpc->describe();
        /* @var  $vpc VpcData */
        $vpc = $vpcList->current();
        $governanceConfiguration = [
            SERVER_PLATFORMS::EC2 => [
                Scalr_Governance::AWS_VPC => [
                    'enabled' => true,
                    'limits' => [
                        'regions' => [
                            self::TEST_REGION => [
                                'default' => true,
                                'ids' => [
                                    $vpc->vpcId
                                ]
                            ]
                        ],
                        'ids' => []
                    ]
                ]
            ]
        ];
        $this->setupGovernanceConfiguration($governanceConfiguration);

        //farm data
        $data = [
            'name' => 'test-post-governance',
            'project' => ['id' => $testProject->projectId],
            'vpc' => [
                'id' => $vpc->vpcId
            ]
        ];

        //test post farm without region
        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //test post farm with wrong region
        $data['vpc']['region'] = Aws::REGION_US_WEST_1;
        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //test post farm with wrong vpc
        $vpcList->next();
        /* @var  $incorrectVpc VpcData */
        $incorrectVpc = $vpcList->current();
        $data['vpc'] = [
            'id' => $incorrectVpc->vpcId,
            'region' => self::TEST_REGION
        ];
        $response = $this->postFarm($data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //test post farm
        $data['vpc']['id'] = $vpc->vpcId;
        $response = $this->postFarm($data);
        $this->assertPostFarms($response, $data);

        //set default governance settings
        $this->restoreGovernanceConfiguration();

        //test list farms filters
        $farms = $this->listFarms();

        $farmAdapter = $this->getAdapter('farm');

        $filterable = $farmAdapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];

        /* @var $farm Farm */
        foreach ($farms as $farm) {
            foreach ($filterable as $property) {
                if (isset($farm->{$property})) {
                    $filterValue = $farm->{$property};
                } else {
                    continue;
                }

                $listResult = $this->listFarms([ $property => $filterValue ]);

                if (!static::isRecursivelyEmpty($filterValue)) {
                    foreach ($listResult as $filtered) {
                        $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                    }
                }
            }

            $response = $this->getFarm($farm->id);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $dbFarm = Farm::findPk($farm->id);

            $this->assertObjectEqualsEntity($response->getBody()->data, $dbFarm, $farmAdapter);
        }
    }

    /**
     * @test
     * @functional
     */
    public function testFarmLaunch()
    {
        $user = $this->getUser();
        $environment = $this->getEnvironment();
        $fictionController = new ApiController();

        /* @var $farm Farm */
        $farm = $this->createTestFarm('launch', [ 'base-ubuntu1404' ]);

        $response = $this->launchFarm($farm->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $data = $response->getBody()->data;

        $this->assertEquals($farm->id, $data->id);

        $farm = Farm::findPk($farm->id);

        $this->assertEquals($farm->status, FARM_STATUS::RUNNING);

        $this->assertObjectEqualsEntity($data, $farm);

        \Scalr::FireEvent($farm->id, new FarmTerminatedEvent(
            false,
            false,
            false,
            false,
            true,
            $user->id
        ));
    }

    /**
     * @test
     * @functional
     */
    public function testFarmTerminate()
    {
        $user = $this->getUser();
        $environment = $this->getEnvironment();
        $fictionController = new ApiController();

        /* @var $farm Farm */
        $farm = $this->createTestFarm('terminate', [ 'base-ubuntu1404' ]);

        //launch farm before terminating
        $farm->launch($user);

        $response = $this->getFarm($farm->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $data = $response->getBody()->data;

        $this->assertEquals($farm->id, $data->id);

        $farm = Farm::findPk($farm->id);

        $this->assertEquals($farm->status, FARM_STATUS::RUNNING);

        $this->assertObjectEqualsEntity($data, $farm);

        //terminate farm
        $response = $this->terminateFarm($farm->id);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $data = $response->getBody()->data;

        $this->assertEquals($farm->id, $data->id);

        $farm = Farm::findPk($farm->id);

        $this->assertEquals($farm->status, FARM_STATUS::TERMINATED);

        $this->assertObjectEqualsEntity($data, $farm);
    }

    /**
     * @test
     * @functional
     */
    public function testFarmGlobalVariables()
    {
        $db = \Scalr::getDb();

        $testName = str_replace('-', '', $this->getTestName());

        $farm = Farm::findOne([['envId' => static::$testEnvId]]);
        /* @var $farm Farm */
        $farmId = $farm->id;

        $uri = static::getUserApiUrl("farms/{$farmId}/global-variables");

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

        $notFoundRoleId = 10 + $db->GetOne("SELECT MAX(f.id) FROM farms f");

        $describe = $this->request(static::getUserApiUrl("/farms/{$notFoundRoleId}/global-variables"), Request::METHOD_GET);
        $this->assertErrorMessageContains($describe, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND);

        $create = $this->request($uri, Request::METHOD_POST, [], ['invalid' => 'value']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $create = $this->request($uri, Request::METHOD_POST, [], ['name' => 'invalid val--ue']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Name should contain only letters, numbers and underscores, start with letter and be from 2 to 128 chars long');

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
     * Asserts post farm response
     *
     * @param ApiTestResponse $response
     * @param array           $data
     */
    public function assertPostFarms(ApiTestResponse $response, $data)
    {
        $this->assertEquals(201, $response->status, $this->printResponseError($response));
        $farmId = $response->getBody()->data->id;
        /* @var $farm Farm */
        $farm = Farm::findPk($farmId);
        $this->assertNotEmpty($farm);
        $this->farmToDelete($farmId);
        $this->assertObjectEqualsEntity($data, $farm);
    }


}