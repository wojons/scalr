<?php

namespace Scalr\Tests\Functional\Api\V2;

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
 * Class ApiTestCaseV2
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (03.12.2015)
 */
class ApiTestCaseV2 extends ApiTestCase
{
    /**
     * Object generated of User Api specifications
     *
     * @var SpecManager
     */
    protected $userSpec;

    /**
     * Object generated of Account Api specifications
     *
     * @var SpecManager
     */
    protected $accountSpec;

    /**
     * Test data container for testGetEndpoint()
     *
     * @var array
     */
    protected static $data = [];

    /**
     * Mapping params in path definitions and response objects
     *
     * @var array
     */
    protected $paramMap = [
        'farms' => 'id',
        'scripts' => 'id',
        'global-variables' => 'name',
        'os' => 'id',
        'farm-roles' => 'id',
        'events' => 'id',
        'cost-centers' => 'id',
        'projects' => 'id',
        'role-categories' => 'id',
        'script-versions' => 'version',
        'roles' => 'id',
        'cloud-credentials' => 'id',
        'images' => 'image',
        'orchestration-rules' => 'id',
    ];

    /**
     * Map save structure if object need other data structure
     * key based on object name in Api definitions
     *
     * @var array
     */
    protected $pathMap = [
        'FarmRoleSummary' => [
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
              'cloudPlatform',
              'cloudLocation'
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
        $this->userSpec = new SpecManager(self::$apiVersion, 'user');
        $this->accountSpec = new SpecManager(self::$apiVersion, 'account');
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
    }

    /**
     *  Data provider for testApiV2User()
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
     * @dataProvider dataApiProvider
     * @test
     */
    public function testApiV2User($path, $method, $responseCode, $params, $filterable, $body, $entityClass)
    {
        $method = strtoupper($method);
        $params['envId'] = self::$testEnvId;
        $requestUrl = '/api/' . static::$apiVersion . '/user' . $this->matchApiUrl($path, $params);
        $response = $this->request($requestUrl, $method, $filterable, $body);
        $apiResp = $this->userSpec->getResponse($path, $method, $responseCode);
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
        $basePaths = $this->userSpec->getPathTemplates(Request::METHOD_GET);
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
        $basePaths = $this->accountSpec->getPathTemplates(Request::METHOD_GET);
        foreach ($basePaths as $basePath) {
            $data[$basePath] = [$basePath, 'account'];
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
            $specFile = $this->accountSpec;
            $baseUrl = '/api/' . static::$apiVersion . '/account/';
        } else {
            $specFile = $this->userSpec;
            $baseUrl = '/api/' . static::$apiVersion . '/user/';
        }
        $apiResp = $specFile->getResponse($path, Request::METHOD_GET, 200);
        $entity = $apiResp->getObjectEntity();
        $nameEn = $entity->getObjectName();
        $apiUrls = $this->mapPath($path, in_array($nameEn, $this->checkScope) ? $type : null);
        if($apiUrls->valid()) {
            foreach ($apiUrls as $uri) {
                $url = $baseUrl . $uri;
                $saveUrl = array_key_exists($nameEn, $this->pathMap)
                    ? preg_replace($this->pathMap[$nameEn]['pattern'], $this->pathMap[$nameEn]['replace'], $uri) : $uri;
                $objects = $this->assertGetEndpoint($url, $apiResp, ['maxResults' => 5]);
                foreach ($objects as $object) {
                    //check list filterable properties
                    $this->assertFilterableProperty($url, $apiResp, $object);
                    $this->saveUrlData($saveUrl, $object);
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
            $this->assertGetEndpoint($url, $apiResp, $filter);
        } else {
            foreach ($filterable as $property) {
                $this->assertGetEndpoint($url, $apiResp, [$property => $object->{$property}]);
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
     * @param string   $url    api url
     * @param stdClass $object api object
     */
    protected function saveUrlData($url, $object)
    {
        $part = null;
        $pointer = &self::$data;
        foreach (explode('/', trim($url, '/')) as $part) {
            if (!isset($pointer[$part])) {
                $pointer[$part] = [];
            }
            $pointer = &$pointer[$part];
        }

        if (isset($this->paramMap[$part])) {
            $param = $this->paramMap[$part];
            $pointer[$object->$param] = (array)$object;
        }
    }

    /**
     * Return list of Objects available in this account or environment
     *
     * @param string       $uri              Request uri
     * @param ListResponse $apiResp          schema current object generated of api specification
     * @param array        $filters optional filterable properties
     * @return array
     */
    public function assertGetEndpoint($uri, $apiResp, array $filters = [])
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
                $objects[] = $envelope->data;
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
            $objects[] = [$envelope->data];
        }
        return call_user_func_array('array_merge', $objects);
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
        return parent::getFixturesDirectory() . '/Api/V2/Paths';
    }
}
