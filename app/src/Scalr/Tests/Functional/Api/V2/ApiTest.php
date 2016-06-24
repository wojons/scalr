<?php

namespace Scalr\Tests\Functional\Api\V2;

use RecursiveIteratorIterator;
use Scalr;
use stdClass;
use Exception;
use UnexpectedValueException;
use PHPUnit_Framework_MockObject_MockObject;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Model\Entity\Account\User;
use Scalr\Acl\Acl;
use Scalr\Tests\Fixtures\Api\V2\Acl\ApiTestAcl;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Tests\Functional\Api\V2\Iterator\ApiDataRecursiveFilterIterator;
use Scalr\Tests\Functional\Api\V2\Iterator\FilterRule;
use Scalr\Tests\Functional\Api\V2\SpecSchema\Constraint\ResponseBodyConstraint;
use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\ListResponse;
use Scalr\Tests\Functional\Api\V2\SpecSchema\SpecManager;
use Scalr\Tests\Functional\Api\V2\TestData\ApiFixture;
use Scalr\Model\Entity\Account\User\ApiKeyEntity;
use ROLE_BEHAVIORS;
use Scalr\Model\Entity\ScalingMetric;
use Scalr\Model\Entity\FarmRoleScalingMetric;
use Scalr\Model\Entity\ScriptVersion;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Model\Entity\Account\TeamEnvs;
use Scalr\Model\Entity\GlobalVariable;
use Scalr_Scripting_GlobalVariables;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\FarmRole;
use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\ObjectEntity;
/**
 * ApiV2 Test
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (03.12.2015)
 */
class ApiTest extends ApiTestCase
{
    /**
     * Object generated of User Api specifications
     *
     * @var SpecManager
     */
    protected static $userSpec;

    /**
     * Object generated of Account Api specifications
     *
     * @var SpecManager
     */
    protected static $accountSpec;

    /**
     * List of adapters using in test
     *
     * @var ApiEntityAdapter[]
     */
    protected static $adapters = [];

    /**
     * @var ApiTestAcl
     */
    protected static $fullAccessAcl;

    /**
     * @var ApiTestAcl
     */
    protected static $readOnlyAccessAcl;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject
     */
    protected static $noAccessAcl;

    /**
     * Mapping params in path definitions and response objects
     *
     * @var array
     */
    protected $paramMap = [
        'acl-roles' => ['AclRole' => 'id'],
        'images' => [
            'Image' => 'id',
            'RoleImage' => 'image'
        ],
        'farm-roles' => [
            'FarmRole' => 'id',
            'FarmRoleSummary' => 'id'
        ],
        'clouds' => ['Cloud' => 'cloud'],
        'cloud-credentials' => [
            'CloudCredentials' => 'id',
            'CloudCredentialsSummary' => 'id'
        ],
        'teams' => [
            'Team' => 'id',
            'TeamForeignKey' => 'id',
            'EnvironmentTeam' => 'team'
        ],
        'environments' => ['Environment' => 'id'],
        'os' => ['Os' => 'id'],
        'roles' => ['Role' => 'id'],
        'farms' => ['Farm' => 'id'],
        'scripts' => ['Script' => 'id'],
        'events' => ['Event' => 'id'],
        'cost-centers' => ['CostCenter' => 'id'],
        'projects' => ['Project' => 'id'],
        'role-categories' => ['RoleCategory' => 'id'],
        'script-versions' => ['ScriptVersion' => 'version'],
        'global-variables' => ['GlobalVariable' => 'name'],
        'orchestration-rules' => ['OrchestrationRule' => 'id'],
        'scaling' => [
            'ScalingConfiguration' => 'rules',
            'ScalingRule' => 'name'
        ],
        'scaling-metrics' => ['ScalingMetric' => 'name'],
        'clone' => ['Farm' => 'id'],
        'servers' => ['ServerSummary' => 'id']
    ];

    /**
     * Mapping api object name and specific which gives an entity this object
     * Key is object name
     * Value is functions name
     *
     * @var array
     */
    protected $dbObjectMapping = [
        'ScalingRule' => 'getFarmRoleScalingMetrics',
        'ScalingMetric' => 'getScalingMetric',
        'Environment' => 'getEnvironmentEntity',
        'ScriptVersion' => 'getScriptVersion',
        'EnvironmentTeam' => 'getEnvironmentTeam',
        'GlobalVariable' => 'getGlobalVariable'
    ];

    /**
     * Mapping specifics assert for properties
     * Key is property name
     * Value is functions name
     *
     * @var array
     */
    protected $propertyAssertMap = [
        'builtinAutomation' => 'assertBuiltinAutomation',
        'teams' => 'assertTeams'
    ];

    /**
     * Ids of data generated during the test
     * Destructor clean up data by these ids
     *
     * @var array
     */
    protected static $testData = [];

    /**
     * Max response results
     *
     * @var false|int
     */
    protected static $maxResults = false;

    /**
     * Test data container for testGetEndpoint()
     *
     * @var array
     */
    protected static $data = [];

    /**
     * Default test Acl
     *
     * @var Acl
     */
    protected static $defaultAcl;

    /**
     * Default user type
     *
     * @var string
     */
    protected static $testUserType;

    /**
     * List of objects where should ignore check filterable properties
     *
     * @var array
     */
    protected $ignoreCheckRules = [
        'ScalingConfiguration',
        'GlobalVariable'
    ];

    /**
     * Map save structure if object need other data structure
     * key based on object name in Api definitions
     *
     * @var array
     */
    protected $pathMap = [
        'FarmRole' => [
            'replace' => '/$1/$4',
            'pattern' => '#^(\d+)/(farms)/(\d+)/(farm-roles)$#'
        ]
    ];

    /**
     * Map filterable properties
     * key based on object name in Api definitions
     *
     * @var array
     */
    protected $filterPropertyMap = [
        'Image' => [
            ['cloudPlatform', 'cloudLocation']
        ]
    ];

    protected $simpleFilterRules = [
        'Farm' => ['teams']
    ];

    /**
     * Object where should check scope
     *
     * @var array
     */
    protected $checkScope = [
       'GlobalVariable', 'OrchestrationRule'
    ];

    /**
     * {@inheritdoc}
     * @see ApiTestCase::setUpBeforeClass()
     */
    public function __construct($name = null, $data = [], $dataName = null)
    {
        parent::__construct($name, $data, $dataName);

        if (empty(static::$userSpec)) {
            static::$userSpec = new SpecManager(self::$apiVersion, 'user');
        }
        if (empty(static::$accountSpec)) {
            static::$accountSpec = new SpecManager(self::$apiVersion, 'account');
        }

        if (empty(static::$noAccessAcl)) {
            static::$noAccessAcl = $this->getMock('ApiTestAcl', ['hasAccessTo', 'isUserAllowedByEnvironment']);
            static::$noAccessAcl->expects($this->any())->method('hasAccessTo')->willReturn(false);
            static::$noAccessAcl->expects($this->any())->method('isUserAllowedByEnvironment')->willReturn(false);
            static::$noAccessAcl->aclType = ApiFixture::ACL_NO_ACCESS;
        }
    }

    /**
     * Add test environment, test Acl, setups test user, environment and API key
     */
    public static function setUpBeforeClass()
    {
        if (!Scalr::getContainer()->config->defined('scalr.phpunit.apiv2')) {
            static::markTestIncomplete('phpunit apiv2 configurations is invalid');
        }

        if (Scalr::getContainer()->config->defined('scalr.phpunit.apiv2.params.max_results')) {
            static::$maxResults = Scalr::config('scalr.phpunit.apiv2.params.max_results');
        }

        static::$testUserId = Scalr::config('scalr.phpunit.apiv2.userid');
        static::$user = User::findPk(static::$testUserId);
        static::$testUserType = static::$user->type;

        static::$testEnvId = Scalr::config('scalr.phpunit.apiv2.envid');
        static::$env = Environment::findPk(static::$testEnvId);

        if (empty(static::$user) || empty(static::$env)) {
            static::markTestIncomplete('Either test environment or user is invalid.');
        }

        $apiKeyName = static::getTestName();
        $apiKeyEntity = ApiKeyEntity::findOne([['name' => $apiKeyName], ['userId' => static::$testUserId]]);

        if (empty($apiKeyEntity)) {
            $apiKeyEntity = new ApiKeyEntity(static::$testUserId);
            $apiKeyEntity->name = $apiKeyName;
            $apiKeyEntity->save();
        }

        static::$apiKeyEntity = $apiKeyEntity;

        static::$defaultAcl = Scalr::getContainer()->acl;
        static::$data = [
            static::$testEnvId => []
        ];

        if (empty(static::$fullAccessAcl)) {
            static::$fullAccessAcl = new ApiTestAcl();
            static::$fullAccessAcl->setDb(Scalr::getContainer()->adodb);
            static::$fullAccessAcl->createTestAccountRole(static::$user->getAccountId(), static::getTestName(ApiFixture::ACL_FULL_ACCESS), ApiTestAcl::ROLE_ID_FULL_ACCESS);
            static::$fullAccessAcl->aclType = ApiFixture::ACL_FULL_ACCESS;
        }

        if (empty(static::$readOnlyAccessAcl)) {
            static::$readOnlyAccessAcl = new ApiTestAcl();
            static::$readOnlyAccessAcl->setDb(Scalr::getContainer()->adodb);
            static::$readOnlyAccessAcl->createTestAccountRole(static::$user->getAccountId(), static::getTestName(ApiFixture::ACL_READ_ONLY_ACCESS), ApiTestAcl::ROLE_ID_READ_ONLY_ACCESS);
            static::$readOnlyAccessAcl->aclType = ApiFixture::ACL_READ_ONLY_ACCESS;
        }
    }

    /**
     * Removes API key generated for test
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        static::$fullAccessAcl->deleteTestAccountRole(static::$user->getAccountId(), static::getTestName(ApiFixture::ACL_FULL_ACCESS));

        static::$readOnlyAccessAcl->deleteTestAccountRole(static::$user->getAccountId(), static::getTestName(ApiFixture::ACL_READ_ONLY_ACCESS));

        if (static::$defaultAcl instanceof Scalr\Acl\Acl) {
            Scalr::getContainer()->release('acl')->setShared('acl', function () {
                return static::$defaultAcl;
            });
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        switch ($this->getName(false)) {
            case 'testGetEndpoint':
                if (!static::$user->canManageAcl()) {
                    $this->setUserType(User::TYPE_ACCOUNT_ADMIN);
                }
                $this->setTestAcl(ApiFixture::ACL_FULL_ACCESS);
                break;
            case 'testApi':
                if (static::$user->canManageAcl()) {
                    $this->markTestIncomplete("Specified test user has always full access. It's not valid for this test");
                }
                break;

        }
    }

    /**
     * {@inheritdoc}
     * @see ApiTestCase::tearDown()
     */
    protected function tearDown()
    {
        parent::tearDown();
        $this->setUserType(static::$testUserType);
    }

    /**
     * Data provider for testGetEndpoint()
     *
     * @return array[]
     */
    public function dataGetEndpointProvider()
    {
        $basePaths = static::$userSpec->getPathTemplates(Request::METHOD_GET);
        $farmRoles = array_filter($basePaths, function ($v) {
            return preg_match('#^/{envId}/farm-roles#', $v);
        });
        foreach ($farmRoles as $i => $farmRole) {
            unset($basePaths[$i]);
        }
        array_push($basePaths, ...$farmRoles);
        $data = [];
        foreach ($basePaths as $basePath) {
            $data[$basePath] = [$basePath, 'user'];
        }
        $basePaths = static::$accountSpec->getPathTemplates(Request::METHOD_GET);
        foreach ($basePaths as $basePath) {
            $data[$basePath] = [$basePath, 'account'];
        }

        if (file_exists($ignoreFile = $this->getFixturesDirectory() . '/ignorePath.yaml')) {
            $ignorePaths = yaml_parse_file($ignoreFile);
            if (!empty($ignorePaths = $ignorePaths['paths'])) {
                $data = array_filter($data, function ($k) use ($ignorePaths) {
                    foreach ($ignorePaths as $path) {
                        if (preg_match("#$path#", $k)) {
                            return false;
                        }
                    }
                    return true;
                }, ARRAY_FILTER_USE_KEY);
            }
        }
        return $data;
    }

    /**
     * Test for base user and account Api GET endpoints
     *
     * @param string   $path path from api specifications
     * @param string   $type api  specifications type
     * @dataProvider dataGetEndpointProvider
     * @test
     */
    public function testGetEndpoint($path, $type)
    {
        if ($type === 'account') {
            $specFile = static::$accountSpec;
            $baseUrl = '/api/' . static::$apiVersion . '/account/';
        } else {
            $specFile = static::$userSpec;
            $baseUrl = '/api/' . static::$apiVersion . '/user/';
        }
        $apiResp = $specFile->getResponse($path, Request::METHOD_GET, 200);
        $entity = $apiResp->getObjectEntity();
        $objectName = $entity->getObjectName();
        $apiUrls = $this->mapPath($path, in_array($objectName, $this->checkScope) ? $type : null);
        if ($apiUrls->valid()) {
            foreach ($apiUrls as $uri) {
                $this->assertFilterableProperties($baseUrl . $uri, $apiResp, $uri);
            }
        } else {
            $this->markTestIncomplete("No data for $path endpoint or parent endpoint failed");
        }
    }

    /**
     * Data provider for testApi()
     *
     * @return array
     */
    public function dataApiProvider()
    {
        if (Scalr::getContainer()->config->defined('scalr.phpunit.apiv2')) {
            $userData = ApiFixture::loadData($this->getFixturesDirectory() . 'Paths/User', 'user');
            $accountData = ApiFixture::loadData($this->getFixturesDirectory() . 'Paths/Account', 'account');
            $data = array_merge($userData, $accountData);
            if (empty($data)) {
                $this->markTestIncomplete('No test data');
            }

            return $data;
        }
        $this->markTestIncomplete('phpunit apiv2 configurations is invalid');
    }

    /**
     * Test for Api endpoints
     * Data provider prepares data for Api request
     * SpecFile get responses definition from api specifications for each endpoint
     * ResponseBodyConstraint compares Api definition with request
     *
     * @param string           $path          Api endpoint
     * @param string|Exception $type          Api specifications type
     * @param string           $adapter       Adapter name
     * @param string           $aclType       Acl type for test
     * @param string           $userType      UserType for test
     * @param string           $method        HTTP method
     * @param int              $responseCode  HTTP code
     * @param array            $params        Array of path  parameters
     * @param array            $filterable    Array of GET  parameters
     * @param array            $body          Array of POST parameters
     * @dataProvider dataApiProvider
     * @test
     */
    public function testApi($path, $type, $adapter, $aclType, $userType, $method, $responseCode, $params, $filterable, $body)
    {
        if ($type instanceof Exception) {
            $this->markTestIncomplete(sprintf('Class or file name %s. Exception message %s', $path, $type->getMessage()));
        }

        if ($type === 'account') {
            $specFile = static::$accountSpec;
            $baseUrl = '/api/' . static::$apiVersion . '/account';
        } else {
            $specFile = static::$userSpec;
            $baseUrl = '/api/' . static::$apiVersion . '/user';
            $params['envId'] = static::$testEnvId;
        }
        $this->setTestAcl($aclType);
        $this->setUserType($userType);

        $method = strtoupper($method);

        $adapter = $this->getAdapter($adapter);

        $entityClass = $adapter->getEntityClass();

        $requestUrl = $baseUrl . $this->matchApiUrl($path, $params);

        $apiResp = $specFile->getResponse($path, $method, $responseCode);

        if ($method == Request::METHOD_GET && !isset($filterable[ApiController::QUERY_PARAM_MAX_RESULTS])) {
            // Tests should be considerably fast.
            $filterable[ApiController::QUERY_PARAM_MAX_RESULTS] = static::$maxResults ? static::$maxResults : 10;
        }

        $response = $this->request($requestUrl, $method, $filterable, $body);

        $envelope = $response->getBody();

        $this->assertEquals($responseCode, $response->status, $this->printResponseError($response));

        $checkRules = false;
        switch ($method) {
            case Request::METHOD_GET:
                if (200 == $responseCode) {
                    $this->assertFilterableProperties($requestUrl, $apiResp);
                    if (count($filterable) > 1) {
                        $this->assertNotEmpty($envelope->data);
                    }
                    $checkRules = true;
                }

                break;

            case Request::METHOD_POST:
                if (201 == $responseCode || 200 === $responseCode) {
                    $objectName = $apiResp->getObjectEntity()->getObjectName();
                    $pk = $this->mapApiResponsePk($path, $objectName);
                    $id = ApiController::getBareId($envelope->data, $pk);
                    if (!empty($id)) {
                        $dbObject = $this->getDbObject($objectName, $id, $entityClass, $params);
                        $this->assertNotEmpty($dbObject, sprintf('Object %s should exist in db', $objectName));
                        $this->assertObjectEqualsEntity($envelope->data, $dbObject, $adapter);
                    }
                    $checkRules = true;
                }

                break;

            case Request::METHOD_PATCH:
                if (200 == $responseCode) {
                    $objectName = $apiResp->getObjectEntity()->getObjectName();
                    $pk = $this->mapApiResponsePk($path, $objectName);
                    $id = ApiController::getBareId($envelope->data, $pk);
                    if (!empty($id)) {
                        $dbObject = $this->getDbObject($objectName, $id, $entityClass, $params);
                        $this->assertNotEmpty($dbObject, sprintf('Object %s should exist in db', $objectName));
                        $this->assertObjectEqualsEntity($envelope->data, $dbObject, $adapter);
                    }
                    $checkRules = true;
                }

                break;
        }

        if ($checkRules) {
            $this->checkAdapterRules($apiResp->getObjectEntity(), $adapter->getRules());
        }

        $this->assertThat($envelope, new ResponseBodyConstraint($apiResp));
    }

    /**
     * Check adapter rules
     *
     * @param ObjectEntity $objectEntity api object entity generated form specs
     * @param array $rules list of rules
     */
    public function checkAdapterRules(ObjectEntity $objectEntity, $rules)
    {
        //check filterable properties
        if (isset($rules[ApiEntityAdapter::RULE_TYPE_FILTERABLE]) && !in_array($objectEntity->getObjectName(), $this->ignoreCheckRules)) {
            $diffProperties = array_diff($rules[ApiEntityAdapter::RULE_TYPE_FILTERABLE], $objectEntity->filterable);
            $this->assertEmpty($diffProperties, sprintf(
                'filterable properties %s in specifications and adapter rules do not match', implode(' , ', $diffProperties)
            ));
        }

        //check alterable properties. Alterable properties can not be createOnly
        if (isset($rules[ApiEntityAdapter::RULE_TYPE_ALTERABLE])) {
            $intersectProp = array_intersect($rules[ApiEntityAdapter::RULE_TYPE_ALTERABLE], $objectEntity->createOnly);
            $this->assertEmpty($intersectProp, sprintf(
                "The property %s is mutually exclusive. should be alterable or create-only. Object %s",
                implode(' , ', $intersectProp), $objectEntity->getObjectName()
            ));

            //alterable properties can not be readOnly
            $intersectProp = array_intersect($rules[ApiEntityAdapter::RULE_TYPE_ALTERABLE], $objectEntity->readOnly);
            $this->assertEmpty($intersectProp, sprintf(
                "The property %s is mutually exclusive. should be alterable or read-only. Object %s",
                implode(' , ', $intersectProp), $objectEntity->getObjectName()
            ));
        }

        // check data and specification properties
        if (isset($rules[ApiEntityAdapter::RULE_TYPE_TO_DATA]) && !in_array($objectEntity->getObjectName(), $this->ignoreCheckRules)) {
            $dataRules = $rules[ApiEntityAdapter::RULE_TYPE_TO_DATA];
            if (!empty($rules[ApiEntityAdapter::RULE_TYPE_SETTINGS])) {
                $dataRules = array_merge($dataRules, $rules[ApiEntityAdapter::RULE_TYPE_SETTINGS]);
            }
            $diffProperties = array_diff(array_keys($objectEntity->getProperties()), $dataRules);
            $this->assertEmpty($diffProperties, sprintf(
                'Properties %s in specifications and adapter rules do not match', implode(' , ', $diffProperties)
            ));
        }
    }

    /**
     * Get db object
     *
     * @param string $objectName api object name
     * @param string $criteria   search criteria
     * @param string $entityClass
     * @param array  $params array params
     * @return mixed
     */
    protected function getDbObject($objectName, $criteria, $entityClass, $params)
    {
        if (isset($this->dbObjectMapping[$objectName])) {
            $function = $this->dbObjectMapping[$objectName];
            if (method_exists($this, $function)) {
                return $this->{$function}($criteria ,$params);
            } else {
                $this->markTestIncomplete(sprintf('% function does not exist', $function));
            }
        }

        static::toDelete($entityClass, [$criteria]);
        return $entityClass::findPk($criteria);
    }

    /**
     * Generate url with api endpoint and saved data
     *
     * @param string $path api endpoint
     * @param string $type api  specifications type
     * @return \Generator
     */
    protected function mapPath($path, $type)
    {
        $iterator = new \RecursiveIteratorIterator(
            new ApiDataRecursiveFilterIterator(new \RecursiveArrayIterator(self::$data), explode('/', trim($path, '/')), $type)
        );
        foreach ($iterator as $part => $value) {
            // Build path based on parent keys
            for ($i = $iterator->getDepth() - 1; $i >= 0; $i--) {
                $part = $iterator->getSubIterator($i)->key() . '/' . $part;
            }
            yield $part;
        }
    }

    /**
     * Save data for x-usedIn endpoints
     *
     * @param string   $url        api url
     * @param stdClass $object     api object
     * @param string   $nameEntity name api entity
     */
    protected function saveUrlData($url, $object, $nameEntity)
    {
        $part = null;
        $pointer = &self::$data;
        foreach (explode('/', trim($url, '/')) as $part) {
            if (!isset($pointer[$part])) {
                $pointer[$part] = [];
            }
            $pointer = &$pointer[$part];
        }

        if (isset($this->paramMap[$part][$nameEntity])) {
            $param = $this->paramMap[$part][$nameEntity];
            $id = ApiController::getBareId($object, $param);
            $object = (array) $object;
            if (!empty($id)) {
                $pointer[$id] = $object;
            } else if (isset($object[$param])) {
                $pointer = array_flip($object[$param]);
            }
        }
    }

    /**
     * Assert and check unique filterable properties from requested url
     *
     * @param string       $url          Request uri
     * @param ListResponse $apiResp      Schema current object generated of api specification
     * @param string       $saveDataPath Save url for child endpoints
     */
    public function assertFilterableProperties($url, $apiResp, $saveDataPath = null)
    {
        $entity = $apiResp->getObjectEntity();
        $objectName = $entity->getObjectName();
        /* @var $objectFilters FilterRule[] */
        $objectFilters = [];
        foreach ($this->assertGetEndpoint($url, $apiResp, [], true, static::$maxResults) as $object) {
            //get list of filterable properties
            $filterable = $entity->filterable;

            //handle filters with singular make logic
            if (isset($this->filterPropertyMap[$objectName])) {
                $rules = $this->filterPropertyMap[$objectName];

                foreach ($rules as $rule) {
                    $filterRule = new FilterRule();

                    foreach ($rule as $property) {
                        //when there is only part of the required fields - filter should be discarded
                        if (!isset($object->{$property})) {
                            $filterRule = null;
                            break;
                        }

                        $filterRule[$property] = $object->{$property};
                    }

                    if (count($filterRule)) {
                        $objectFilters[] = $filterRule;
                    }

                    //remove the fields included in complex filter from the list of filterable fields
                    $filterable = array_diff($filterable, $rule);
                }
            }

            foreach ($filterable as $property) {
                if (isset($object->{$property}) && !static::isRecursivelyEmpty($object->{$property})) {
                    $value = $object->{$property};
                    if (is_array($value) && isset($this->simpleFilterRules[$objectName]) && in_array($property, $this->simpleFilterRules[$objectName])) {
                        foreach ($value as $entry) {
                            $objectFilters[] = new FilterRule([$property => $entry]);
                        }
                    } else {
                        $objectFilters[] = new FilterRule([$property => $value]);
                    }
                }
            }

            if (!is_null($saveDataPath)) {
                $saveUrl = empty($this->pathMap[$objectName]) ? $saveDataPath : preg_replace($this->pathMap[$objectName]['pattern'], $this->pathMap[$objectName]['replace'], $saveDataPath);
                $this->saveUrlData($saveUrl, $object, $objectName);
            }
        }

        if (!empty($objectFilters)) {
            $objectFilters = array_unique($objectFilters, SORT_STRING);
            foreach ($objectFilters as $filter) {
                $listResult = $this->assertGetEndpoint($url, $apiResp, $filter->getFilters(), true, static::$maxResults);
                $this->assertNotEmpty($listResult);
                foreach ($listResult as $filtered) {
                    foreach ($filter as $property => $filterValue) {
                        if (isset($this->propertyAssertMap[$property])) {
                            $assert = $this->propertyAssertMap[$property];
                            if (method_exists($this, $assert)) {
                                $this->{$assert}($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                            } else {
                                $this->markTestIncomplete(sprintf('% assertion does not exist', $assert));
                            }
                        } else {
                            $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                        }
                    }
                }
            }
        }
    }

    /**
     * Return list of Objects available in this account or environment
     *
     * @param string       $uri     Request uri
     * @param ListResponse $apiResp schema current object generated of api specification
     * @param array        $filters    optional Filterable properties
     * @param bool         $collect    optional Collect response data
     * @param null|int     $maxResults optional max list results
     *
     * @return array
     */
    public function assertGetEndpoint($uri, $apiResp, array $filters = [], $collect = true, $maxResults = null)
    {
        $envelope = null;
        $objects = [];
        $constraint = new ResponseBodyConstraint($apiResp);
        if ($apiResp instanceof ListResponse) {
            do {
                $params = $filters;

                if ($maxResults) {
                    $params[ApiController::QUERY_PARAM_MAX_RESULTS] = $maxResults;
                }

                if (isset($envelope->pagination->next)) {
                    $parts = parse_url($envelope->pagination->next);
                    parse_str($parts['query'], $params);
                }
                $response = $this->request($uri, Request::METHOD_GET, $params);
                $this->assertEquals(200, $response->status, sprintf('%s. Api url %s', $this->printResponseError($response), $uri));
                /* @var  $envelope ListResultEnvelope */
                $envelope = $response->getBody();
                $this->assertNotEmpty($envelope);
                $this->assertObjectHasAttribute('meta', $envelope);
                $this->assertObjectHasAttribute('data', $envelope);
                $this->assertObjectHasAttribute('pagination', $envelope);
                $this->assertObjectNotHasAttribute('errors', $envelope);
                $this->assertThat($envelope, $constraint, "Api url $uri");

                if ($collect) {
                    $objects[] = $envelope->data;
                }
            } while (!empty($envelope->pagination->next) && !$maxResults);
        } else {
            $response = $this->request($uri, Request::METHOD_GET, $filters);
            $this->assertEquals(200, $response->status, sprintf('%s. Api url %s', $this->printResponseError($response), $uri));
            /* @var $envelope ResultEnvelope */
            $envelope = $response->getBody();
            $this->assertNotEmpty($envelope);
            $this->assertObjectHasAttribute('meta', $envelope);
            $this->assertObjectHasAttribute('data', $envelope);
            $this->assertObjectNotHasAttribute('errors', $envelope);
            $this->assertThat($envelope, $constraint, "Api url $uri");

            if ($collect) {
                $objects[] = [$envelope->data];
            }
        }

        return empty($objects) ? [] : call_user_func_array('array_merge', $objects);
    }

    /**
     * Custom assert BuiltinAutomation property
     *
     * @param array  $filterValue Filter value property
     * @param array  $property    Object property
     * @param string $message     Assert message
     */
    public function assertBuiltinAutomation($filterValue, $property, $message)
    {
        $builtinAutomation = array_intersect($filterValue, $property);
        if (empty($builtinAutomation)) {
            $builtinAutomation = array_intersect($property, array_flip(ROLE_BEHAVIORS::GetName(null, true)));
        }
        $this->assertNotEmpty($builtinAutomation, $message);
    }

    /**
     * Reports an error if specified team is not present in the list of teams of the Farm obtained by filtration
     *
     * @param   object      $team                Filter value
     * @param   object[]    $teams               Filtering property of the object obtained by filtration
     * @param   string      $message    optional Message in case of error
     */
    public function assertTeams($team, $teams, $message)
    {
        $teamIds = [];
        foreach ($teams as $teamFk) {
            $teamIds[] = $teamFk->id;
        }

        $this->assertContains($team->id, $teamIds, $message);
    }

    /**
     * Set mock acl for test
     *
     * @param string $acl Acl type for tests
     */
    protected function setTestAcl($acl)
    {
        /* @var $aclContainer ApiTestAcl */
        $aclContainer = Scalr::getContainer()->acl;
        if (!property_exists($aclContainer, 'aclType') || $aclContainer->aclType != $acl) {
            Scalr::getContainer()->release('acl')
                ->setShared('acl', function () use ($acl) {
                    return ($acl === ApiFixture::ACL_NO_ACCESS) ? static::$noAccessAcl : (($acl === ApiFixture::ACL_READ_ONLY_ACCESS) ? static::$readOnlyAccessAcl : static::$fullAccessAcl);
                });
        }
    }

    /**
     * Set user types for test
     * Some endpoints need extra credentials
     *
     * @param string $type User type
     */
    protected function setUserType($type)
    {
        if (static::$user->type !== $type) {
            static::$user->update(['type' => $type]);
        }
    }

    /**
     * On the based api endpoint and name api object return primary key
     * If pk don't exist in paramMath throw fail
     *
     * @param string $path       Api endpoint
     * @param string $objectName Name object entity from specification
     * @return string
     */
    protected function mapApiResponsePk($path, $objectName)
    {
        if (preg_match('#/(?<object>[\w-]+)/({\w+}\/?)?$#', $path, $match) && isset($this->paramMap[$match['object']][$objectName])) {
            return $this->paramMap[$match['object']][$objectName];
        }
        $this->markTestIncomplete(sprintf("%s %s don't exist in ApiTest::paramMap", $match['object'], $objectName));
    }

    /**
     * Match API url params
     *
     * @param string $path   Api endpoint
     * @param array  $params path parameters
     * @return string
     * @throws UnexpectedValueException
     */
    protected function matchApiUrl($path, $params = [])
    {
        $pathRequirements = "#{(\w*)}#";
        preg_match_all($pathRequirements, $path, $matches);
        $replace = $pattern = [];
        $matches = array_pop($matches);
        foreach ($matches as $match) {
            if (!isset($params[$match])) {
                throw new UnexpectedValueException("Don't isset $match in params");
            }
            $pattern[] = $pathRequirements;
            $replace[] = $params[$match];
            unset($params[$match]);
        }
        return preg_replace($pattern, $replace, $path, 1);
    }

    /**
     * Get FarmRoleScalingMetric entity
     *
     * @param string $criteria search criteria
     * @param array  $params   optional query params
     * @return FarmRoleScalingMetric
     */
    protected function getFarmRoleScalingMetrics($criteria, $params = [])
    {
        $metric = ScalingMetric::findOne([['name' => $criteria]]);
        /* @var  $farmRoleMetric FarmRoleScalingMetric  */
        $farmRoleMetric = FarmRoleScalingMetric::findOne([['metricId' => $metric->id], ['farmRoleId' => $params['farmRoleId']]]);
        $farmRoleMetric->metric = $metric;
        static::toDelete(FarmRoleScalingMetric::class, [$farmRoleMetric->id]);
        return $farmRoleMetric;
    }

    /**
     * Get ScalingMetric entity
     *
     * @param string $criteria search criteria
     * @return ScalingMetric
     */
    protected function getScalingMetric($criteria)
    {
        /* @var $metric ScalingMetric */
        $metric = ScalingMetric::findOne([['name' => $criteria]]);
        static::toDelete(ScalingMetric::class, [$metric->id]);
        return $metric;
    }

    /**
     * Gets Environment entity
     *
     * @param int $envId environment identifier
     * @return Environment
     */
    public function getEnvironmentEntity($envId)
    {
        /* @var $env Environment */
        $env = Environment::findPk($envId);
        static::toDelete(Environment::class, [$envId], ['accountId' => $env->accountId]);
        return $env;
    }

    /**
     * Gets ScriptVersion entity
     *
     * @param string $criteria search criteria
     * @param array $params query params
     * @return ScriptVersion
     */
    protected function getScriptVersion($criteria, $params)
    {
        /* @var  $sv ScriptVersion */
        $sv = ScriptVersion::findOne([['scriptId' => $params['scriptId']], ['version' => $criteria]]);
        static::toDelete(ScriptVersion::class, [$sv->scriptId, $sv->version]);
        return $sv;
    }

    /**
     * Gets TeamEnvs entity
     *
     * @param string $criteria search criteria
     * @param array $params query params
     * @return TeamEnvs
     */
    protected function getEnvironmentTeam($criteria, $params)
    {
        /* @var  $teamEnv TeamEnvs */
        $teamEnv = TeamEnvs::findOne([['teamId' => $criteria], ['envId' => $params['envId']]]);
        static::toDelete(TeamEnvs::class, [$teamEnv->envId, $teamEnv->teamId]);
        return $teamEnv;
    }

    /**
     * Get GV
     *
     * @param string $criteria search criteria
     * @param array $params query params
     *
     * @return array
     */
    public function getGlobalVariable($criteria, $params)
    {
        $roleId = 0;
        $farmId = 0;
        $farmRoleId = 0;
        $serverId = 0;
        $scope = ScopeInterface::SCOPE_ENVIRONMENT;

        if (isset($params['farmId'])) {
            $scope = ScopeInterface::SCOPE_FARM;
            $farmId = $params['farmId'];
        } else if (isset($params['farmRoleId'])) {
            /* @var  $farmRole FarmRole */
            $farmRole = FarmRole::findOne([['id' => $params['farmRoleId']]]);
            $farmRoleId = $farmRole->id;
            $roleId = $farmRole->roleId;
            $farmId = $farmRole->farmId;
            $scope = ScopeInterface::SCOPE_FARMROLE;
        } else if(isset($params['roleId'])) {
            $roleId = $params['roleId'];
            $scope = ScopeInterface::SCOPE_ROLE;
        }

        $variableScopeIdentity = [
            $roleId, //0
            $farmId, //0
            $farmRoleId, //0
            $serverId //0
        ];

        $varName = $criteria;

        $gv = new Scalr_Scripting_GlobalVariables(
            $this->getUser()->getAccountId(),
            $this->getEnvironment()->id,
            $scope //corresponding scope
        );

        $variable = [];

        $list = $gv->getValues(...$variableScopeIdentity);

        foreach ($list as $var) {
            if ((!empty($var['current']['name']) && $var['current']['name'] == $varName)
                || (!empty($var['default']['name']) && $var['default']['name'] == $varName)) {

                $variable = $var;
                break;
            }
        }

        return $variable;
    }

    /**
     * {@inheritdoc}
     * @see TestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Api/V2/';
    }

    /**
     * Gets API entity adapter
     *
     * @param  string        $class               Adapter class
     * @param  ApiController $controller optional API Controller
     *
     * @return ApiEntityAdapter
     */
    public function getAdapter($class, ApiController $controller = null)
    {
        if (!isset(static::$adapters[$class])) {
            static::$adapters[$class] = new $class($controller ?: static::$apiController);
        }

        return static::$adapters[$class];
    }

    /**
     * Makes the filtering rule for the specified properties of an object
     *
     * @param   string  $class      optional The object name
     * @param   string  $property   optional The property name
     * @param   array   $values     optional The array of filter values
     *
     * @return  FilterRule
     */
    public function getFilter($class = null, $property = null, array $values = [])
    {
        if (isset($this->simpleFilterRules[$class][$property])) {
            $filterClass = $this->simpleFilterRules[$class][$property];
            return new $filterClass($values);
        }

        return new FilterRule($values);
    }
}
