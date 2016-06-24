<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Controller\FarmRoles;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Collections\EntityIterator;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\Role;
use Scalr\Service\Aws;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use SERVER_PLATFORMS;
use Scalr_Governance;
use Scalr\Service\Aws\Ec2\DataType\VpcList;
use Scalr\Service\Aws\Ec2\DataType\VpcData;
use Scalr\Service\Aws\Ec2\DataType\SubnetFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\SubnetData;
use Scalr\Model\Entity\FarmSetting;
use Scalr\Service\Azure\Services\Network\DataType\SubnetList;

/**
 * FarmRoles test
 *
 * @author N.V.
 */
class FarmRolesTest extends ApiTestCase
{

    /**
     * @see Aws::REGION_US_EAST_1
     */
    const TEST_REGION = 'us-east-1';

    const TEST_VPC_ID = 'vpc-8371b8e6';

    public $uuid;

    /**
    * For the purpose of data conversion
    *
    * @var FarmRoles
    */
    protected static $apiController;

    public function __construct($name = null, $data = [], $dataName = null)
    {
        parent::__construct($name, $data, $dataName);

        static::$apiController = new FarmRoles();

        $this->uuid = uniqid($this->getTestName());
    }

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

    public function farmRoleToDelete($farmId)
    {
        static::toDelete(FarmRole::class, [$farmId]);
    }

    /**
     * @param int $farmRoleId
     *
     * @return ApiTestResponse
     */
    public function getFarmRole($farmRoleId)
    {
        $uri = self::getUserApiUrl("/farm-roles/{$farmRoleId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param int $farmId
     * @param array $filters
     *
     * @return array
     */
    public function listFarmRoles($farmId, array $filters = [])
    {
        $envelope = null;
        $roles = [];
        $uri = self::getUserApiUrl("/farms/{$farmId}/farm-roles");

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

            $roles[] = $envelope->data;
        } while (!empty($envelope->pagination->next));

        return call_user_func_array('array_merge', $roles);
    }

    /**
     * @param int $farmId
     * @param array $farmRoleData
     *
     * @return ApiTestResponse
     */
    public function postFarmRole($farmId, array &$farmRoleData)
    {
        //NOTE: do not exceed the field size
        $farmRoleData['alias'] = "{$this->uuid}-farm-role-{$farmRoleData['alias']}";

        $uri = self::getUserApiUrl("/farms/{$farmId}/farm-roles");
        return $this->request($uri, Request::METHOD_POST, [], $farmRoleData);
    }

    /**
     * @param int   $farmRoleId
     * @param array $farmRoleData
     *
     * @return ApiTestResponse
     */
    public function modifyFarmRole($farmRoleId, $farmRoleData)
    {
        $uri = self::getUserApiUrl("/farm-roles/{$farmRoleId}");

        return $this->request($uri, Request::METHOD_PATCH, [], $farmRoleData);
    }

    /**
     * @param int $farmRoleId
     *
     * @return ApiTestResponse
     */
    public function deleteFarmRole($farmRoleId)
    {
        $uri = self::getUserApiUrl("/farm-roles/{$farmRoleId}");

        return $this->request($uri, Request::METHOD_DELETE);
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

        /* @var $farm Farm */
        $farm = static::createEntity(new Farm(), [
            'changedById'   => $user->getId(),
            'name'          => "{$this->uuid}-farm",
            'comments'   => "{$this->uuid}-description",
            'envId'         => $environment->id,
            'accountId'     => $user->getAccountId(),
            'ownerId'   => $user->getId()
        ]);

        /* @var $roles EntityIterator */
        /* @var $role Role */
        $roles = Role::findByName('base-ubuntu1404');

        if (empty($roles) || !count($roles)) {
            $this->markTestSkipped("Not found suitable role, required role - 'base-ubuntu1404'");
        } else {
            $role = $roles->current();
        }

        //test Governance
        $this->getGovernance();
        /* @var $vpcList VpcList */
        $vpcList = \Scalr::getContainer()->aws(self::TEST_REGION, $this->getEnvironment())->ec2->vpc->describe(self::TEST_VPC_ID);
        /* @var  $vpc VpcData */
        $vpc = $vpcList->current();
        /* @var  $subnetList SubnetList */
        $subnetList = \Scalr::getContainer()->aws(self::TEST_REGION, $this->getEnvironment())->ec2->subnet->describe(null, [[
            'name' => SubnetFilterNameType::vpcId(),
            'value' => $vpc->vpcId
        ]]);

        /* @var  $subnet SubnetData */
        $subnet = $subnetList->current();
        //setup test governance
        $vpcId = $vpc->vpcId;
        $subnetId = $subnet->subnetId;
        $governanceConfiguration = [
            SERVER_PLATFORMS::EC2 => [
                Scalr_Governance::INSTANCE_TYPE => [
                    'enabled' => true,
                    'limits' => [
                        'value' => ['t1.micro', 't2.small', 't2.medium', 't2.large'],
                        'default' => ['t2.small']
                    ]
                ],
                Scalr_Governance::AWS_VPC => [
                    'enabled' => true,
                    'limits' => [
                        'regions' => [
                            self::TEST_REGION => [
                                'default' => true,
                                'ids' => [
                                    $vpcId
                                ]
                            ]
                        ],
                        'ids' => [
                            $vpcId => [
                                $subnetId
                            ]
                        ]
                    ]

                ]
            ]
        ];
        $this->setupGovernanceConfiguration($governanceConfiguration);

        //farm role data
        $data = [
            'role' => [ 'id' => $role->id ],
            'alias' => 't-ps',
            'platform' => SERVER_PLATFORMS::EC2,
            'placement' => [
                'placementConfigurationType' => FarmRoles::AWS_CLASSIC_PLACEMENT_CONFIGURATION,
                'region' => static::TEST_REGION
            ],
            'scaling' => [
                'enabled' => true,
                'minInstances' => 2,
                'maxInstances' => 3
            ],
            'instance' => [
                'instanceConfigurationType' => FarmRoles::AWS_INSTANCE_CONFIGURATION,
                'instanceType' => [
                    'id' => 't1.micro'
                ]
            ]
        ];

        //create farmRole with wrong instance type
        $data['instance']['instanceType']['id'] = 'm1.small';
        $response = $this->postFarmRole($farm->id, $data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //Add AWS VPC settings
        $farm->settings[FarmSetting::EC2_VPC_ID] = $vpc->vpcId;
        $farm->settings[FarmSetting::EC2_VPC_REGION] = self::TEST_REGION;
        $farm->save();

        //create farm role with AwsClassicPlacementConfiguration
        $data['instance']['instanceType']['id'] = 't2.small';
        $response = $this->postFarmRole($farm->id, $data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_STRUCTURE);

        //create farm role with incorrect subnet
        $subnetList->next();
        /* @var  $incorrectSubnet SubnetData */
        $incorrectSubnet = $subnetList->current();
        $data['placement'] = [
            'region' => self::TEST_REGION,
            'placementConfigurationType' => 'AwsVpcPlacementConfiguration',
            'subnets' => [['id' => $incorrectSubnet->subnetId ]]

        ];
        $response = $this->postFarmRole($farm->id, $data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //create farm role with incorrect region
        $data['placement'] = [
            'region' => Aws::REGION_US_WEST_1,
            'placementConfigurationType' => 'AwsVpcPlacementConfiguration',
            'subnets' => [['id' => $subnetId ]]

        ];
        $response = $this->postFarmRole($farm->id, $data);
        $this->assertErrorMessageContains($response, 400, ErrorMessage::ERR_INVALID_VALUE);

        //post farm role correct data
        $data['placement']['region'] = self::TEST_REGION;
        $data['alias'] = 't-ps-1';

        $response = $this->postFarmRole($farm->id, $data);
        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $farmRoleId = $response->getBody()->data->id;
        /* @var $farmRole FarmRole */
        $farmRole = FarmRole::findPk($farmRoleId);
        $this->assertNotEmpty($farmRole);

        $this->farmRoleToDelete($farmRoleId);
        $data['scaling']['rules'] = [];
        $this->assertObjectEqualsEntity($data, $farmRole);

        //Reset AWS VPC settings
        $farm->settings[FarmSetting::EC2_VPC_ID] = null;
        $farm->settings[FarmSetting::EC2_VPC_REGION] = null;
        $farm->save();

        //set default governance settings
        $this->restoreGovernanceConfiguration();

        //test farm roles post
        $data = [
            'role' => [ 'id' => $role->id ],
            'alias' => 't-ps-2',
            'platform' => SERVER_PLATFORMS::EC2,
            'placement' => [
                'placementConfigurationType' => FarmRoles::AWS_CLASSIC_PLACEMENT_CONFIGURATION,
                'region' => static::TEST_REGION
            ],
            'scaling' => [
                'enabled' => true,
                'minInstances' => 2,
                'maxInstances' => 3
            ],
            'instance' => [
                'instanceConfigurationType' => FarmRoles::AWS_INSTANCE_CONFIGURATION,
                'instanceType' => [
                    'id' => 't1.micro'
                ]
            ]
        ];

        $response = $this->postFarmRole($farm->id, $data);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $farmRoleId = $response->getBody()->data->id;

        /* @var $farmRole FarmRole */
        $farmRole = FarmRole::findPk($farmRoleId);

        $this->assertNotEmpty($farmRole);

        $this->farmRoleToDelete($farmRoleId);

        $data['placement']['availabilityZones'] = '';
        $data['scaling']['rules'] = [];

        $this->assertObjectEqualsEntity($data, $farmRole);

        //test farm role modify scaling
        $data = [
            'scaling' => [
                'enabled' => false
            ]
        ];

        $response = $this->modifyFarmRole($farmRole->id, $data);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $farmRoleData = $response->getBody()->data;

        $this->assertObjectHasAttribute('scaling', $farmRoleData);
        $scalingConfiguration = $farmRoleData->scaling;
        $this->assertObjectNotHasAttribute('enabled', $scalingConfiguration);

        //test modify instance
        $data = [
            'instance' => [
                'instanceConfigurationType' => FarmRoles::AWS_INSTANCE_CONFIGURATION,
                'instanceType' => 'm3.medium'
            ]
        ];

        $response = $this->modifyFarmRole($farmRole->id, $data);

        $this->assertEquals(200, $response->status, $this->printResponseError($response));

        $farmRoleData = $response->getBody()->data;

        $this->assertObjectHasAttribute('instance', $farmRoleData);
        $instanceConfiguration = $farmRoleData->instance;
        $this->assertObjectHasAttribute('instanceType', $instanceConfiguration);
        $instanceType = $instanceConfiguration->instanceType;
        $this->assertObjectHasAttribute('id', $instanceType);
        $this->assertEquals('m3.medium', $instanceType->id);

        //test list farm roles filters
        $farmRoles = $this->listFarmRoles($farm->id);

        $farmRoleAdapter = $this->getAdapter('farmRole');

        $filterable = $farmRoleAdapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];

        /* @var $farmRole FarmRole */
        foreach ($farmRoles as $farmRole) {
            foreach ($filterable as $property) {
                $filterValue = $farmRole->{$property};

                $listResult = $this->listFarmRoles($farm->id, [ $property => $filterValue ]);

                if (!static::isRecursivelyEmpty($filterValue)) {
                    foreach ($listResult as $filtered) {
                        $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                    }
                }
            }

            $response = $this->getFarmRole($farmRole->id);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $dbFarmRole = FarmRole::findPk($farmRole->id);

            $this->assertObjectEqualsEntity($response->getBody()->data, $dbFarmRole, $farmRoleAdapter);
        }
    }

    /**
     * @test
     * @functional
     */
    public function testFarmRoleGlobalVariables()
    {
        $db = \Scalr::getDb();

        $testName = str_replace('-', '', $this->getTestName());

        $farm = Farm::findOne([['envId' => static::$testEnvId]]);
        /* @var $farm Farm */
        $farmRole = FarmRole::findOne([['farmId' => $farm->id]]);
        /* @var $farmRole FarmRole */
        $roleId = $farmRole->id;

        $uri = static::getUserApiUrl("farm-roles/{$roleId}/global-variables");

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

                if (empty($declaredNotInRole) && $variable->declaredIn !== ScopeInterface::SCOPE_FARMROLE && !$variable->hidden) {
                    $declaredNotInRole = $variable->name;
                }

                if (strpos($variable->name, $testName) !== false) {
                    $delete = $this->request($uri . '/' . $variable->name, Request::METHOD_DELETE);
                    $this->assertEquals(200, $delete->response->getStatus());
                }
            }
        } while (!empty($variables->pagination->next));

        $this->assertNotNull($declaredNotInRole);

        $notFoundRoleId = 10 + $db->GetOne("SELECT MAX(f.id) FROM farm_roles f");

        $describe = $this->request(static::getUserApiUrl("/farm-roles/{$notFoundRoleId}/global-variables"), Request::METHOD_GET);
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

}