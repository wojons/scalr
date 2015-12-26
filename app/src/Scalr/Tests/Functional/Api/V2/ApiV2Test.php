<?php

namespace Scalr\Tests\Functional\Api\V2;

use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Tests\Functional\Api\V2\Iterator\ApiDataRecursiveFilterIterator;
use Scalr\Tests\Functional\Api\V2\SpecSchema\Constraint\ResponseBodyConstraint;
use Scalr\Tests\Functional\Api\V2\SpecSchema\SpecManager;
use Scalr\Tests\Functional\Api\V2\TestData\TestDataFixtures;
use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\ListResponse;
use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\DetailResponse;
use UnexpectedValueException;
use stdClass;

/**
 * ApiV2 Test
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (03.12.2015)
 */
class ApiV2Test extends ApiTestCase
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
     * Mapping params in path definitions and response objects
     *
     * @var array
     */
    protected $paramMap = [
        'images' => [
            'Image' => 'id',
            'RoleImage' => 'image'
        ],
        'farm-roles' => [
            'FarmRole' => 'id',
            'FarmRoleSummary' => 'id'
        ],
        'cloud-credentials' => [
            'CloudCredentials' => 'id',
            'CloudCredentialsSummary' => 'id'
        ],
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
        'orchestration-rules' => ['OrchestrationRule' => 'id']
    ];

    /**
     * Test data container for testGetEndpoint()
     *
     * @var array
     */
    protected static $data = [];

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
    }

    /**
     * Add test environment
     *
     * {@inheritdoc}
     * @see ApiTestCase::setUpBeforeClass()
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        static::$data = [
            static::$testEnvId => []
        ];

        if (empty(static::$userSpec)) {
            static::$userSpec = new SpecManager(self::$apiVersion, 'user');
        }

        if (empty(static::$accountSpec)) {
            static::$accountSpec = new SpecManager(self::$apiVersion, 'account');
        }
    }

    /**
     * Data provider for testApiV2User()
     *
     * @return array
     */
    public function dataApiProvider()
    {
        return TestDataFixtures::dataFixtures($this->getFixturesDirectory() . '/User');
    }

    /**
     * Test for user Api endpoints
     * Data provider prepares data for Api request
     * UserSpec get responses definition from user specifications for each endpoint
     * ResponseBodyConstraint compares Api definition with request
     *
     * @param string $path Api endpoint
     * @param string $method HTTP method
     * @param int    $responseCode HTTP code
     * @param array  $params Array of path  parameters
     * @param array  $filterable Array of GET  parameters
     * @param array  $body Array of POST parameters
     * @param string $entityClass Entity class name
     * @test
     */
    public function testApiV2User($path = null, $method = null, $responseCode = null, $params = null, $filterable = null, $body = null, $entityClass = null)
    {
        $this->markTestSkipped('Fixtures don\'t implemented yet');
        $method = strtoupper($method);
        $params['envId'] = self::$testEnvId;
        $requestUrl = '/api/' . static::$apiVersion . '/user' . $this->matchApiUrl($path, $params);
        $response = $this->request($requestUrl, $method, $filterable, $body);
        $apiResp = static::$userSpec->getResponse($path, $method, $responseCode);
        // delete created object
        if (Request::METHOD_POST === $method && isset($response->getBody()->data)) {
            self::toDelete($entityClass, $response->getBody()->data->id);
        }
        if (Request::METHOD_GET === $method && 200 == $response) {
            $objects = $this->assertGetEndpoint($requestUrl, $apiResp);
            foreach ($objects as $object) {
                foreach ($filterable as $property) {
                    $filterValue = $object->{$property};
                    $this->assertGetEndpoint($requestUrl, $apiResp, [$property => $filterValue]);
                }
            }
        } else {
            $this->assertEquals($responseCode, $response->status, $this->printResponseError($response));
            $this->assertThat($response->getBody(), new ResponseBodyConstraint($apiResp));
        }
    }

    /**
     *  Data provider for testGetEndpoint()
     *
     * @return array[]
     */
    public function dataBaseGetEndpointProvider()
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
     * @dataProvider dataBaseGetEndpointProvider
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
                $url = $baseUrl . $uri;

                /* @var $objectFilters FilterRule[] */
                $objectFilters = [];

                foreach ($this->assertGetEndpoint($url, $apiResp) as $object) {
                    //get list of filterable properties
                    $filterable = $entity->filterable;
                    if (isset($this->filterPropertyMap[$objectName])) {
                        foreach ($this->filterPropertyMap[$objectName] as $rule) {
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
                            $filterable = array_diff($filterable, $rule);
                        }
                    }

                    foreach ($filterable as $property) {
                        if (isset($object->{$property}) && !static::isRecursivelyEmpty($object->{$property})) {
                            $objectFilters[] = new FilterRule([$property => $object->{$property}]);
                        }
                    }

                    $saveUrl = empty($this->pathMap[$objectName]) ? $uri : preg_replace($this->pathMap[$objectName]['pattern'], $this->pathMap[$objectName]['replace'], $uri);
                    $this->saveUrlData($saveUrl, $object, $objectName);
                }

                if (!empty($objectFilters)) {
                    $objectFilters = array_unique($objectFilters, SORT_STRING);
                    foreach ($objectFilters as $filter) {
                        $listResult = $this->assertGetEndpoint($url, $apiResp, $filter->getFilters());
                        $this->assertNotEmpty($listResult);
                        foreach ($listResult as $filtered) {
                            foreach ($filter as $property => $filterValue) {
                                $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                            }
                        }
                    }
                }
            }
        } else {
            $this->markTestIncomplete("No data for $path endpoint or parent endpoint failed");
        }
    }

    /**
     * Check filterable property in object
     *
     * @param string                      $url     api url
     * @param DetailResponse|ListResponse $apiResp schema current object generated of api specification
     * @param stdClass                    $object  api object
     */
    public function assertFilterableProperty($url, $apiResp, $object)
    {
        $entity = $apiResp->getObjectEntity();
        $filterable = $entity->filterable;
        if (isset($this->filterPropertyMap[$entity->getObjectName()])) {
            $filter = [];
            foreach ($this->filterPropertyMap[$entity->getObjectName()] as $property) {
                $filter[$property] = $object->{$property};
                unset($filterable[$property]);
            }
            $this->assertGetEndpoint($url, $apiResp, $filter, false);
        } else {
            foreach ($filterable as $property) {
                if(isset($object->{$property}) && !static::isRecursivelyEmpty($object->{$property})) {
                    $this->assertGetEndpoint($url, $apiResp, [$property => $object->{$property}], false);
                }
            }
        }
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
            $id = ApiController::getBareId($object, $this->paramMap[$part][$nameEntity]);

            if (!empty($id)) {
                $pointer[$id] = (array)$object;
            }
        }
    }

    /**
     * Return list of Objects available in this account or environment
     *
     * @param string       $uri     Request uri
     * @param ListResponse $apiResp schema current object generated of api specification
     * @param array        $filters optional Filterable properties
     * @param bool         $collect optional Collect response data
     *
     * @return array
     */
    public function assertGetEndpoint($uri, $apiResp, array $filters = [], $collect = true)
    {
        $envelope = null;
        $objects = [];
        $constraint = new ResponseBodyConstraint($apiResp);
        if ($apiResp instanceof ListResponse) {
            do {
                $params = $filters;
                if (isset($envelope->pagination->next)) {
                    $parts = parse_url($envelope->pagination->next);
                    parse_str($parts['query'], $params);
                }
                $response = $this->request($uri, Request::METHOD_GET, $params);
                $this->assertEquals(200, $response->status, $this->printResponseError($response));
                /* @var  $envelope ListResultEnvelope */
                $envelope = $response->getBody();
                $this->assertObjectHasAttribute('meta', $envelope);
                $this->assertObjectHasAttribute('data', $envelope);
                $this->assertObjectHasAttribute('pagination', $envelope);
                $this->assertObjectNotHasAttribute('errors', $envelope);
                $this->assertThat($envelope, $constraint, "Api url $uri");

                if ($collect) {
                    $objects[] = $envelope->data;
                }
            } while (!empty($envelope->pagination->next));
        } else {
            $response = $this->request($uri, Request::METHOD_GET, $filters);
            $this->assertEquals(200, $response->status, $this->printResponseError($response));
            /* @var $envelope ResultEnvelope */
            $envelope = $response->getBody();
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
     * Match API url params
     *
     * @param string $url    Api endpoint
     * @param array  $params path parameters
     * @return string
     * @throws UnexpectedValueException
     */
    protected function matchApiUrl($url, $params = [])
    {
        $pathRequirements = "#{(\w*)}#";
        preg_match_all($pathRequirements, $url, $matches);
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
        return preg_replace($pattern, $replace, $url, 1);
    }

    /**
     * {@inheritdoc}
     * @see TestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/Api/V2/';
    }
}
