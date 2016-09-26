<?php

namespace Scalr\Tests\Functional\Api;

use Exception;
use Scalr\Api\Service\User\V1beta0\Adapter\GlobalVariableAdapter;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\ApiApplication;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Account\User\ApiKeyEntity;
use Scalr\Model\Entity\Image;
use Scalr\Tests\TestCase;
use Scalr_Governance;
use Scalr\Logger;
use InvalidArgumentException;
use Scalr;

/**
 * ApiTestCase
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (12.03.2015)
 */
class ApiTestCase extends TestCase
{

    const TEST_TYPE = TestCase::TEST_TYPE_API;

    /**
     * ID of the user which is used in the functional test
     *
     * @var int
     */
    protected static $testUserId;

    /**
     * ID of the user's environment
     *
     * @var int
     */
    protected static $testEnvId;

    /**
     * Api key entity
     *
     * @var ApiKeyEntity
     */
    protected static $apiKeyEntity;

    /**
     * Scalr_Environment instance
     *
     * @var Environment
     */
    protected static $env;

    /**
     * Test User
     *
     * @var User
     */
    protected static $user;

    /**
     * Scalr Governance
     *
     * @var Scalr_Governance
     */
    protected $governance;

    /**
     * Governance configuration
     *
     * @var array
     */
    protected $governanceConfiguration;

    /**
     * For the purpose of data conversion
     *
     * @var ApiController
     */
    protected static $apiController;

    /**
     * Ids of data generated during the test
     * Destructor clean up data by these ids
     *
     * @var array
     */
    protected static $testData = [];

    /**
     * List of logger what we should be excluded from debug output
     * key is logger name and value is logger level
     *
     * @var array
     */
    protected static $loggerConfiguration = [];

    /**
     * API version
     *
     * @var string
     */
    protected static $apiVersion = 'v1beta0';

    const API_NAMESPACE = '\Scalr\Api\Service\User';

    const TEST_REMOTE_ADDR = '127.0.0.1';

    const TEST_SERVER_NAME = 'localhost';

    const TEST_SERVER_PORT = '80';

    const TEST_CLIENT_IP = '127.0.0.1';

    const TEST_HTTP_HOST = 'localhost';

    const TEST_CONTENT_TYPE = 'application/json; charset=utf-8';

    const TEST_HTTP_USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.11 (KHTML, like Gecko) Chrome/20.0.1132.57 Safari/536.11';

    const TEST_HTTP_REFERER = 'https://localhost/test.php';

    const TEST_SCRIPT_NAME = '/test.php';

    public function __construct($name = null, $data = [], $dataName = null)
    {
        parent::__construct($name, $data, $dataName);

        if (empty(static::$apiController)) {
            static::$apiController = new ApiController();
        }
    }

    /**
     * Setups test user, environment and API key
     *
     * @throws \Scalr\Exception\ModelException
     */
    public static function setUpBeforeClass()
    {
        static::$testUserId = \Scalr::config('scalr.phpunit.userid');
        static::$user = User::findPk(static::$testUserId);

        static::$testEnvId = \Scalr::config('scalr.phpunit.envid');
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

        static::changeLoggerConfiguration();
    }

    /**
     * Change logger level and save current level
     */
    protected static function changeLoggerConfiguration()
    {
        if (!empty(static::$loggerConfiguration)) {
            foreach (static::$loggerConfiguration as $logName => $level) {
                /* @var $logger Logger */
                $logger =  \Scalr::getContainer()->logger($logName);
                static::$loggerConfiguration[$logName] = $logger->getLevel();
                $logger->setLevel($level);
            }
        }
    }

    /**
     * Removes API key and Entities generated for test
     *
     * @throws \Scalr\Exception\ModelException
     */
    public static function tearDownAfterClass()
    {
        foreach (array_reverse(static::$testData) as $rec) {
            $class = $rec['class'];
            $entry = $rec['pk'];
            $initProperties = $rec['initProp'];

            $entity = new $class;

            /* @var $entity AbstractEntity */
            foreach ($entity->getIterator()->getPrimaryKey() as $pos => $prop) {
                $entity->$prop = $entry[$pos];
            }

            //we should init properties which will be used in delete action
            foreach ($initProperties as $prop => $value) {
                $entity->$prop = $value;
            }

            try {
                //deletePk method does not remove related objects
                $entity->delete();
            } catch (Exception $e) {
                //we should remove all created Entities
                \Scalr::logException($e);
            }
        }

        static::$testData = [];

        if (!empty(static::$apiKeyEntity)) {
            static::$apiKeyEntity->delete();
        }

        static::changeLoggerConfiguration();
    }

    /**
     * Gets governance object
     *
     * @return  Scalr_Governance
     */
    public function getGovernance()
    {
        if (empty($this->governance)) {
            $this->governance = new Scalr_Governance(static::$testEnvId);
        }

        return $this->governance;
    }

    /**
     * Setup governance configuration
     *
     * @param   array $governanceConfiguration           Governance configuration
     * @param   bool  $savePrevious             optional If true, current governance configuration be saved before setting up a new
     */
    public function setupGovernanceConfiguration(array $governanceConfiguration, $savePrevious = true)
    {
        $governance = $this->getGovernance();

        $this->governanceConfiguration = $savePrevious ? $governance->getValues() : null;

        if (!empty($governanceConfiguration)) {
            foreach ($governanceConfiguration as $categoryName => $category) {
                foreach ($category as $name => $value) {
                    $governance->setValue($categoryName, $name, $value['enabled'], $value['limits']);
                }
            }
            $governance->clearCache();
        }
    }

    /**
     * Restores a previously saved governance configuration
     *
     * @throws  \UnderflowException If there was no saved configuration
     */
    public function restoreGovernanceConfiguration()
    {
        if (!isset($this->governanceConfiguration)) {
            throw new \UnderflowException("Previous governance configuration has not been saved!");
        }

        $this->setupGovernanceConfiguration($this->governanceConfiguration, false);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();

        if (static::isSkippedFunctionalTest()) {
            $this->markTestSkipped();
        }

        if (!\Scalr::getContainer()->config('scalr.system.api.enabled')) {
            $this->markTestSkipped('API is not enabled. See scalr.system.api.enabled');
        }
    }

    /**
     * Asserts api error response
     *
     * @param ApiTestResponse  $response                    Api response
     * @param int              $expectedStatus              Expected Status
     * @param string           $expectedError      optional Expected error
     * @param string           $expectedMessage    optional Expected message
     */
    public function assertErrorMessageContains(ApiTestResponse $response, $expectedStatus, $expectedError = null, $expectedMessage = null)
    {
        $body = $response->getBody();
        $this->assertNotEmpty($body);

        $printError = $this->printResponseError($response);

        $this->assertEquals($expectedStatus, $response->response->getStatus(), $printError);
        $this->assertObjectHasAttribute('errors', $body);
        $this->assertNotEmpty($body->errors);

        $error = reset($body->errors);

        if ($expectedError !== null) {
            $this->assertObjectHasAttribute('code', $error);
            $this->assertEquals($expectedError, $error->code, $printError);
        }

        if ($expectedMessage !== null) {
            $this->assertObjectHasAttribute('message', $error);
            $this->assertContains($expectedMessage, $error->message, $printError);
        }
    }

    /**
     * Asserts error message status
     *
     * @param int               $expectedStatus     Expected Status
     * @param ApiTestResponse   $response           Api response
     */
    public function assertErrorMessageStatusEquals($expectedStatus, ApiTestResponse $response)
    {
        $this->assertEquals($expectedStatus, $response->response->getStatus());
    }

    /**
     * Asserts error message error
     *
     * @param string            $expectedError      Expected error
     * @param ApiTestResponse   $response           Api response
     */
    public function assertErrorMessageErrorEquals($expectedError, ApiTestResponse $response)
    {
        $body = $response->getBody();
        $this->assertNotEmpty($body);

        $this->assertObjectHasAttribute('errors', $body);
        $this->assertNotEmpty($body->errors);

        $error = reset($body->errors);

        $this->assertObjectHasAttribute('code', $error);
        $this->assertEquals($expectedError, $error->code);
    }

    /**
     * Asserts error message text
     *
     * @param string            $expectedMessage    Expected message
     * @param ApiTestResponse   $response           Api response
     */
    public function assertErrorMessageTextEquals($expectedMessage, ApiTestResponse $response)
    {
        $body = $response->getBody();
        $this->assertNotEmpty($body);

        $this->assertObjectHasAttribute('errors', $body);
        $this->assertNotEmpty($body->errors);

        $error = reset($body->errors);

        $this->assertObjectHasAttribute('message', $error);
        $this->assertContains($expectedMessage, $error->message);
    }

    /**
     * Asserts fetch response has all properties
     *
     * @param ApiTestResponse $response     Api response
     */
    public function assertFetchResponseNotEmpty(ApiTestResponse $response)
    {
        $body = $response->getBody();
        $this->assertNotEmpty($body);

        $this->assertObjectNotHasAttribute('errors', $body);

        $this->assertObjectHasAttribute('data', $body);
        $this->assertNotEmpty($body->data);
    }

    /**
     * Asserts describe response has all properties
     *
     * @param ApiTestResponse $response     Api response
     */
    public function assertDescribeResponseNotEmpty(ApiTestResponse $response)
    {
        $body = $response->getBody();
        $this->assertEquals(200, $response->status, $this->printResponseError($response));
        $this->assertNotEmpty($body);

        $this->assertObjectNotHasAttribute('errors', $body);

        $this->assertObjectHasAttribute('data', $body);
        $this->assertObjectHasAttribute('pagination', $body);
    }

    /**
     * Asserts if images's object has all properties
     *
     * @param object $data     Single image's item
     */
    public function assertImageObjectNotEmpty($data)
    {
        $this->assertObjectHasAttribute('id', $data);
        $this->assertObjectHasAttribute('name', $data);
        $this->assertObjectHasAttribute('cloudImageId', $data);
        $this->assertObjectHasAttribute('scope', $data);
        $this->assertObjectHasAttribute('cloudLocation', $data);
        $this->assertObjectHasAttribute('os', $data);
        $this->assertObjectHasAttribute('cloudPlatform', $data);
        $this->assertObjectHasAttribute('added', $data);
        $this->assertObjectHasAttribute('lastUsed', $data);
        $this->assertObjectHasAttribute('architecture', $data);
        $this->assertObjectHasAttribute('deprecated', $data);
        $this->assertObjectHasAttribute('source', $data);
        $this->assertObjectHasAttribute('status', $data);
        $this->assertObjectHasAttribute('type', $data);
        $this->assertObjectHasAttribute('statusError', $data);
    }

    /**
     * Asserts if variable's object has all properties
     *
     * @param object $data     Single image's item
     */
    public function assertVariableObjectNotEmpty($data)
    {
        $this->assertObjectHasAttribute('name', $data);
        $this->assertObjectHasAttribute('value', $data);
        $this->assertObjectHasAttribute('computedValue', $data);
        $this->assertObjectHasAttribute('declaredIn', $data);
        $this->assertObjectHasAttribute('hidden', $data);
        $this->assertObjectHasAttribute('locked', $data);
        $this->assertObjectHasAttribute('outputFormat', $data);
        $this->assertObjectHasAttribute('requiredIn', $data);
        $this->assertObjectHasAttribute('validationPattern', $data);
        $this->assertObjectHasAttribute('description', $data);
    }

    /**
     * Calls api controllers' actions
     *
     * @param  string $uri              Request uri
     * @param  string $method           Http action
     * @param  array  $params  optional Array of GET values
     * @param  array  $body             optional POST fields => values
     * @param  array  $headers          optional Custom headers
     *
     * @return ApiTestResponse Returns API test response
     */
    public function request($uri, $method = Request::METHOD_GET, array $params = [], array $body = [], array $headers = [])
    {
        //Releases API container
        \Scalr::getContainer()->release('api');

        $jsonEncodedBody = !empty($body) ? json_encode($body) : '';

        $date = gmdate('Y-m-d\TH:i:s\Z');

        $c11dQueryString = '';

        if (!empty($params)) {
            ksort($params);

            $c11dQueryString = http_build_query($params, null, '&', PHP_QUERY_RFC3986);
        }

        $stringToSign =
            $method . "\n" // request method
            . $date . "\n" // scalr-date value
            . self::TEST_SCRIPT_NAME . $uri . "\n" // query path
            . $c11dQueryString . "\n" // canonicalized query string
            . (!empty($jsonEncodedBody) ? $jsonEncodedBody : '') // request body sha256 hash
        ;

        $sig = base64_encode(hash_hmac('sha256', $stringToSign, static::$apiKeyEntity->secretKey, 1));

        $defaultHeaders = [
            'Content-Type'      => 'application/json; charset=utf-8',
            'x-scalr-key-id'    => static::$apiKeyEntity->keyId,
            'x-scalr-date'      => $date,
            'x-scalr-signature' => 'V1-HMAC-SHA256 ' . $sig
        ];

        $headers = array_merge($defaultHeaders, $headers);

        $properties = [
            'REQUEST_METHOD'    => $method,
            'QUERY_STRING'      => $c11dQueryString,
            'REQUEST_URI'       => self::TEST_HTTP_REFERER . $uri,
            'raw.body'          => $jsonEncodedBody,
            'request.headers'   => $headers,
            'PATH_INFO'         => $uri,
        ];

        $properties = array_merge($this->getTestEnvProperties(), $properties);

        $app = new ApiApplication([ApiApplication::SETTING_ENV_MOCK => $properties]);
        $app->setupRoutes();
        $app->call();

        return new ApiTestResponse($app->response);
    }

    /**
     * Gets a test environment instance
     *
     * @return  \Scalr\Model\Entity\Account\Environment Returns environment instance
     */
    protected function getEnvironment()
    {
        return static::$env;
    }

    /**
     * Gets an test User instance
     *
     * @return  \Scalr\Model\Entity\Account\User Returns user instance
     */
    protected function getUser()
    {
        return static::$user;
    }

    /**
     * Gets test properties for applicatiob environment
     *
     * @return array
     */
    private function getTestEnvProperties()
    {
        return [
            'REMOTE_ADDR'       => self::TEST_REMOTE_ADDR,
            'SERVER_NAME'       => self::TEST_SERVER_NAME,
            'SERVER_PORT'       => self::TEST_SERVER_PORT,
            'CLIENT_IP'         => self::TEST_CLIENT_IP,
            'HTTP_HOST'         => self::TEST_HTTP_HOST,
            'CONTENT_TYPE'      => self::TEST_CONTENT_TYPE,
            'HTTP_USER_AGENT'   => self::TEST_HTTP_USER_AGENT,
            'HTTP_REFERER'      => self::TEST_HTTP_REFERER,
            'SCRIPT_NAME'       => self::TEST_SCRIPT_NAME,
            'SCHEME'            => 'https',
        ];
    }

    /**
     * Gets unused image id from the cloud
     *
     * @param \Scalr_Environment $env               Scalr environment
     * @param string             $cloudLocation     Region
     * @return null|string
     */
    protected function getNewImageId(\Scalr_Environment $env, $cloudLocation)
    {
        $aws = $env->aws($cloudLocation);

        $cloudImageId = null;
        $existedImages = [];

        foreach (Image::find([
            ['platform'      => \SERVER_PLATFORMS::EC2],
            ['cloudLocation' => $cloudLocation],
            ['envId'         => static::$testEnvId]
        ]) as $img) {
            /* @var $img Image */
            $existedImages[$img->id] = $img;
        }

        foreach ($aws->ec2->image->describe(null, 'self') as $awsImage) {
            /* @var $awsImage \Scalr\Service\Aws\Ec2\DataType\ImageData */
            if (!isset($existedImages[$awsImage->imageId])) {
                $cloudImageId = $awsImage->imageId;
                break;
            }
        }

        return $cloudImageId;
    }

    /**
     * Gets User API url
     *
     * @param   string  $uriPart    Part of the api uri
     * @param   int     $envId      optional Identifier of the Environment.
     *                              If it is not specified method uses default test Environment.
     * @return  string  Returns User API url
     */
    public static function getUserApiUrl($uriPart, $envId = null)
    {
        return '/api/' . static::$apiVersion . '/user/' . ($envId === null ? self::$testEnvId : $envId) . '/' . ltrim($uriPart, '/');
    }

    /**
     * Gets Account API url
     *
     * @param   string  $uriPart    Part of the api uri
     * @return  string  Returns User API url
     */
    public static function getAccountApiUrl($uriPart)
    {
        return '/api/' . static::$apiVersion . '/account/' . ltrim($uriPart, '/');
    }

    /**
     * Gets API entity adapter
     *
     * @param   string          $name                Adapter name
     * @param   ApiController   $controller optional API Controller
     * @return  ApiEntityAdapter
     */
    public function getAdapter($name, ApiController $controller = null)
    {
        $class = static::API_NAMESPACE . '\\' . ucfirst(static::$apiVersion) . '\\Adapter\\' . ucfirst($name) . 'Adapter';

        return new $class($controller ?: static::$apiController);
    }

    /**
     * Asserts that object equals entity
     *
     * @param                      $object
     * @param AbstractEntity|array $entity
     * @param string         $adapter   optional Entity adapter name
     */
    public function assertObjectEqualsEntity($object, $entity, $adapter = null)
    {
        if (empty($adapter)) {
            $classParts = preg_split('/\\\\/', get_class($entity));
            $adapter = $this->getAdapter(lcfirst(array_pop($classParts)));
        } else if (is_string($adapter)) {
            $adapter = $this->getAdapter($adapter);
        }

        /* @var $adapter ApiEntityAdapter */
        if ($adapter instanceof  GlobalVariableAdapter) {
            $data = (object) $adapter->convertData($entity);
        } else {
            $data = $adapter->toData($entity);
        }

        foreach ($object as $property => $value) {
            $this->assertObjectHasAttribute($property, $data);
            $this->assertEquals(json_decode(json_encode($data->{$property})), json_decode(json_encode($value)), $property);
        }
    }

    /**
     * Makes string representation of error
     *
     * @param ApiTestResponse $response Response envelope
     *
     * @return string
     */
    public function printResponseError(ApiTestResponse $response)
    {
        return implode(PHP_EOL, array_map(function($entry) {
            return "{$entry->code}:\t{$entry->message}";
        }, empty($response->getBody()->errors) ? [] : $response->getBody()->errors));
    }

    /**
     * Creates and save entity to DB, keeps entity to delete after test
     *
     * @param AbstractEntity $entity       Entity instance
     * @param array          $data         Properties to initialize
     * @param array          $requiredData The list of names properties which should be save and initialize after delete
     * @return AbstractEntity
     * @throws \Scalr\Exception\ModelException
     */
    public static function createEntity(AbstractEntity $entity, array $data, array $requiredData = null)
    {
        if (!empty($data)) {
            $it = $entity->getIterator();

            foreach ($data as $prop => $value) {
                if ($it->getField($prop)) {
                    $entity->$prop = $value;
                } else {
                    throw new InvalidArgumentException(sprintf("Field %s does not exist in %s entity.", $prop, get_class($entity)));
                }
            }
        }

        $initProperties = [];
        if (!empty($requiredData)) {
            foreach ($requiredData as $prop) {
                if (isset($data[$prop])) {
                    $initProperties[$prop] = $data[$prop];
                } else {
                    throw new InvalidArgumentException(sprintf("Field %s does not exist in data.", $prop));
                }
            }
        }

        $entity->save();

        $key = [];

        foreach ($entity->getIterator()->getPrimaryKey() as $position => $property) {
            $key[$position] = $entity->{$property};
        }

        static::toDelete(get_class($entity), $key, $initProperties);

        return $entity;
    }

    /**
     * Registers specified Entity for removal
     *
     * @param  string    $class              The name of the class
     * @param  array     $identifier         The PrimaryKey value of the Entity
     * @param  array     $initProperties     The properties value what use in delete action
     */
    public static function toDelete($class, $identifier, $initProperties = null)
    {
        if (is_null($initProperties)) {
            $initProperties = [];
        }

        $rec = ['class' => $class, 'pk' => $identifier, 'initProp' => $initProperties];

        if (!in_array($rec, static::$testData)) {
            array_push(static::$testData, $rec);
        }
    }

    /**
     * Recursively checks that data is empty
     *
     * @param   mixed   $data   Data to check
     *
     * @return  bool    Returns true if $data are empty or $data is object/array,
     *                  all child elements of which recursively empty, false otherwise
     */
    public static function isRecursivelyEmpty($data)
    {
        if (is_object($data)) {
            foreach ($data as $property) {
                if (!static::isRecursivelyEmpty($property)) {
                    return false;
                }
            }

            return true;
        } else if (is_array($data)) {
            if (!empty($data)) {
                foreach ($data as $entry) {
                    if (!static::isRecursivelyEmpty($entry)) {
                        return false;
                    }
                }

                return true;
            }

            return true;
        }

        return empty($data);
    }
}
