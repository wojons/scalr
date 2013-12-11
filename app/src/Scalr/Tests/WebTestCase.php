<?php
namespace Scalr\Tests;

use \Scalr_UI_Request;
use \Scalr_UI_Response;
use \Scalr_UI_Controller;
use \CONFIG;

/**
 * WebTestCase class which is used for functional testing of the interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     21.02.2013
 */
abstract class WebTestCase extends TestCase
{
    /**
     * ID of the user which is used in the functional test
     * @var int
     */
    protected $_testUserId;

    /**
     * ID of the user's environment
     * @var int
     */
    protected $_testEnvId;

    /**
     * Scalr_Environment instance
     * @var \Scalr_Environment
     */
    private $env;

    /**
     * Test User
     * @var \Scalr_Account_User
     */
    private $user;

    /**
     * Error report level
     * @var int
     */
    private $errorLevel;

    /**
     * Returns true if current test is for Scalr Admin privilege
     *
     * @return boolean
     */
    protected function isAdminUserTestClass()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        $this->errorLevel = error_reporting();
        if (\Scalr::config('scalr.phpunit.skip_functional_tests')) {
            $this->markTestSkipped();
        }

        if (\Scalr::config('scalr.phpunit.userid')) {
            $this->_testUserId = \Scalr::config('scalr.phpunit.userid');
        }

        if (\Scalr::config('scalr.phpunit.envid')) {
            $this->_testEnvId = \Scalr::config('scalr.phpunit.envid');
        }

        if (!$this->isAdminUserTestClass() && (!empty($this->_testUserId) && $this->getUser()->isScalrAdmin())) {
            $this->markTestSkipped('Current test class cannot be passed with scalr admin session.');
        }
    }

    /**
     * {@inheritdoc}
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown()
    {
        $this->env = null;
        $this->user = null;
        error_reporting($this->errorLevel);
        parent::tearDown();
    }

    /**
     * Makes a request to site
     *
     * @param   string    $uri         A request uri
     * @param   array      $parameters  optional Request parameters
     * @param   string     $method      optional HTTP Request method
     * @param   array      $server      optional Additional server options
     * @param   array      $files       optional Uploaded files array
     * @return  array|string            Returns array which represents returned json object or raw body content in the
     *                                  case if the responce is not a json.
     */
    protected function request($uri, array $parameters = array(), $method = 'GET', array $server = array(), array $files = array())
    {
        $aUri = parse_url($uri);
        call_user_func_array(array($this, 'getRequest'), array_merge(array(Scalr_UI_Request::REQUEST_TYPE_UI, null), func_get_args()));

        $path = explode('/', trim($aUri['path'], '/'));
        Scalr_UI_Controller::handleRequest($path);
        $content = Scalr_UI_Response::getInstance()->getResponse();

        $arr = @json_decode($content, true);
        return $arr === null ? $content : $arr;
    }

    /**
     * Get internal request to the controller's action ignoring checking aliases
     *
     * @param   string   $uri
     * @param   array    $parameters
     * @return  mixed    Returns raw response as it is returned by action
     */
    protected function internalRequest($uri, array $parameters = array())
    {
        $aUri = parse_url($uri);
        call_user_func_array(array($this, 'getRequest'), array_merge(array(Scalr_UI_Request::REQUEST_TYPE_UI, null), func_get_args()));

        $path = explode('/', trim($aUri['path'], '/'));
        $method = array_pop($path) . 'Action';
        $subController = ucfirst(array_pop($path));
        $controller = 'Scalr_UI_Controller' . (count($path) ? '_' . join('_', array_map('ucfirst', $path)) : '');

        $c = \Scalr_UI_Controller::loadController($subController, $controller, true);
        $c->$method();

        $content = Scalr_UI_Response::getInstance()->getResponse();

        $arr = @json_decode($content, true);
        return $arr === null ? $content : $arr;
    }

    /**
     * Prepares request
     *
     * @param   striing    $uri         A request uri
     * @param   array      $parameters  optional Request parameters
     * @param   string     $method      optional HTTP Request method
     * @param   array      $server      optional Additional server options
     * @param   array      $files       optional Uploaded files array
     * @return  array|string            Returns array which represents returned json object or raw body content in the
     *                                  case if the response is not a json.
     * @return  \Scalr_UI_Request
     */
    protected function getRequest($requestType = Scalr_UI_Request::REQUEST_TYPE_UI, $requestClass = null, $uri, array $parameters = array(), $method = 'GET', array $server = array(), array $files = array())
    {
        $aUrl = parse_url($uri);

        if (!empty($aUrl['query'])) {
            foreach(explode('&', $aUrl['query']) as $v) {
                $v = array_map('html_entity_decode', explode('=', $v));
                $parameters[$v[0]] = isset($v[1]) ? $v[1] : null;
            }
        }

        $parametersConvert = array();
        foreach ($parameters as $key => $value) {
            $parametersConvert[str_replace('.', '_', $key)] = $value;
        }

        Scalr_UI_Response::getInstance()->resetResponse();

        $testEnv = $this->getUser()->isScalrAdmin() ? null : $this->_testEnvId;

        $requestClass = $requestClass ?: 'Scalr_UI_Request';
        $instance = $requestClass::initializeInstance(
            $requestType, array(), $server, $parametersConvert, $files, $this->_testUserId, $testEnv
        );

        return $instance;
    }

    /**
     * Asserts that response data array has necessary data keys.
     *
     * @param   array  $keys           Array of the keys or Index array that looks like array($key => $constraint)
     * @param   array  $responseData   Response array
     * @param   bool   $checkAll       optional Whether it should check all data array or only the first.
     * @param   string $dataColumnName optional The name of the data column
     */
    protected function assertResponseDataHasKeys($keys, $responseData, $checkAll = false, $dataColumnName = 'data')
    {
        $this->assertInternalType('array', $responseData);
        if (isset($responseData['success']) && $responseData['success'] === false &&
            isset($responseData['errorMessage'])) {
            echo "\n" . $responseData['errorMessage'] . "\n";
        }
        $this->assertArrayHas(true, 'success', $responseData);
        $this->assertArrayHasKey($dataColumnName, $responseData);
        if (!empty($responseData[$dataColumnName])) {
            $this->assertInternalType('array', $responseData[$dataColumnName]);
            foreach ($responseData[$dataColumnName] as $obj) {
                $this->assertNotEmpty($obj);
                $this->assertInternalType('array', $obj);
                foreach ($keys as $key => $val) {
                    if (is_numeric($key)) {
                        $this->assertArrayHasKey($val, $obj);
                    } else {
                        $this->assertArrayHasKey($key, $obj);
                        $this->assertThat($obj[$key], $val);
                    }
                }
                if (!$checkAll) break;
            }
        }
    }

    /**
     * Gets a test environment instance
     *
     * @return  \Scalr_Environment Returns environment instance
     */
    protected function getEnvironment()
    {
        if (!isset($this->env)) {
            if (empty($this->_testEnvId)) {
                $this->_testEnvId = \Scalr::config('scalr.phpunit.envid');
            }
            $this->env = \Scalr_Environment::init()->loadById($this->_testEnvId);
        }

        return $this->env;
    }

    /**
     * Gets an test User instance
     *
     * @return  \Scalr_Account_user Returns user instance
     */
    protected function getUser()
    {
        if (!isset($this->user)) {
            if (empty($this->_testUserId)) {
                $this->_testUserId = \Scalr::config('scalr.phpunit.userid');
            }
            $this->user = \Scalr_Account_User::init();
            $this->user->loadById($this->_testUserId);
        }

        return $this->user;
    }

    /**
     * Skip test if platform does not enabled
     *
     * @param   string    $platform
     */
    protected function skipIfPlatformDisabled($platform)
    {
        if (!$this->getEnvironment() || !$this->getEnvironment()->isPlatformEnabled($platform)) {
            $this->markTestSkipped($platform . ' platform is not enabled');
        }
    }
}