<?php
namespace Scalr\Tests\Api\Rest;

use Scalr\Tests\TestCase;
use Scalr\Api\Rest\Application;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Rest\Exception\StopException;

/**
 * ApplicationTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4 (18.02.2015)
 */
class ApplicationTest extends TestCase
{

    const API_REST_NS = 'Scalr\Api\Rest';

    /**
     * @var Application
     */
    private $app;

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::setUp()
     */
    protected function setUp()
    {
        $this->app = new Application();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::tearDown()
     */
    protected function tearDown()
    {
        $this->app = null;
    }

    /**
     * @test
     */
    public function testGetDefaultSettings()
    {
        $settings = Application::getDefaultSettings();

        $this->assertInternalType('array', $settings);

        $this->assertContains(Application::SETTING_API_VERSION, array_keys($settings));
    }

    /**
     * @test
     */
    public function testConstructor()
    {
        $this->assertInstanceOf('Scalr\DependencyInjection\Container', $this->app->getContainer());

        $this->assertInstanceOf('Scalr\DependencyInjection\ApiContainer', $this->app->getContainer()->api);

        $this->assertEquals($this->app->getContainer()->api->router, $this->app->router);

        $this->assertInstanceOf(self::getApiClass('Routing\Router'), $this->app->getContainer()->api->router);

        $this->assertInstanceOf(self::getApiClass('Environment'), $this->app->environment);

        $this->assertInstanceOf(self::getApiClass('Http\Request'), $this->app->request);

        $this->assertInstanceOf(self::getApiClass('Http\Response'), $this->app->response);
    }

    /**
     * @test
     */
    public function testAddRoute()
    {
        $path = '/api/v:apiversion/users/:id';
        $requirements = ['id' => '[\d]+'];

        $route = $this->app->addRoute($path, function ($id) {/* do nothing */}, $requirements);

        $this->assertInstanceOf(self::getApiClass('Routing\Route'), $route);

        $this->assertEquals($path, $route->getPath());
        $this->assertEquals([], $route->getMethods());
        $this->assertEquals($requirements, $route->getRequirements());
        $this->assertNull($route->getParams());

        $apiVersion = 12;
        $id = 3;

        $this->assertTrue($route->matches('/api/v' . $apiVersion . '/users/' . $id));
        $this->app->handleApiVersion($route);

        $params = $route->getParams();

        $this->assertTrue(isset($params['id']));
        $this->assertEquals($id, $params['id']);
        $this->assertEquals($apiVersion, $this->app->settings[Application::SETTING_API_VERSION]);
    }

    /**
     * @test
     */
    public function testGet()
    {
        $route = $this->app->get('/path', function () {/* do nothing */});
        $this->assertTrue($route->hasMethod(Request::METHOD_HEAD));
        $this->assertTrue($route->hasMethod(Request::METHOD_GET));
        $this->assertFalse($route->hasMethod(Request::METHOD_POST));
    }

    /**
     * @test
     */
    public function testPost()
    {
        $route = $this->app->post('/path', function () {/* do nothing */});
        $this->assertEquals([Request::METHOD_POST], $route->getMethods());
    }

    /**
     * @test
     */
    public function testPut()
    {
        $route = $this->app->put('/path', function () {/* do nothing */});
        $this->assertEquals([Request::METHOD_PUT], $route->getMethods());
    }

    /**
     * @test
     */
    public function testPatch()
    {
        $route = $this->app->patch('/path', function () {/* do nothing */});
        $this->assertEquals([Request::METHOD_PATCH], $route->getMethods());
    }

    /**
     * @test
     */
    public function testDelete()
    {
        $route = $this->app->delete('/path', function () {/* do nothing */});
        $this->assertEquals([Request::METHOD_DELETE], $route->getMethods());
    }

    /**
     * @test
     */
    public function testOptions()
    {
        $route = $this->app->options('/path', function () {/* do nothing */});
        $this->assertEquals([Request::METHOD_OPTIONS], $route->getMethods());
    }

    /**
     * @test
     */
    public function testGroup()
    {
        $app = $this->app;
        $me = $this;

        $middlewares = [
            function () { return '1'; },
            function () { return '2'; },
        ];

        $this->app->group('/api/v1', $middlewares[0], $middlewares[1], function() use ($app, $me, $middlewares) {
            $route = $app->options('/path/:id', function ($id) {/* do nothing */});
            $me->assertEquals('/api/v1/path/:id', $route->getPath());

            //Inner routes
            $app->group('/details', function() use ($app, $me, $middlewares) {
                $inner = $app->options('/path/:id', function ($id) {/* do nothing */});
                $me->assertEquals('/api/v1/details/path/:id', $inner->getPath());
                //Route must have group's middlewares
                $me->assertContains($middlewares[0], $inner->getMidleware());
                $me->assertContains($middlewares[1], $inner->getMidleware());
            });
        });
    }

    /**
     * @test
     */
    public function testRun()
    {
        $this->app->run();
    }

    /**
     * @test
     * @expectedException \Scalr\Api\Rest\Exception\StopException
     */
    public function testStop()
    {
        $this->app->stop();
    }

    /**
     * @test
     */
    public function testNotFound()
    {
        try {
            $this->app->notFound();
            $this->assertTrue(false, 'app->notFound() should throw StopException');
        } catch (StopException $e) {
            $this->assertTrue(true);
            $this->assertEquals(404, $this->app->response->getStatus());
        }
    }

    /**
     * @test
     */
    public function testError()
    {
        $this->app->getContainer()->apilogger->setIsEnabled(false);

        try {
            $this->app->error();
            $this->assertTrue(false, 'app->error() should throw StopException');
        } catch (StopException $e) {
            $this->assertTrue(true);
            $this->assertEquals(500, $this->app->response->getStatus());
        }
    }

    /**
     * Gets REST API namespace
     *
     * @return   string  Returns namespace
     */
    protected static function getNamespase()
    {
        return self::API_REST_NS;
    }

    /**
     * Gets API class name
     *
     * @param    string   $suffix The name of the class without base namespace
     * @return   string   Returns the name of the class
     */
    protected static function getApiClass($suffix)
    {
        return self::getNamespase() . '\\' . $suffix;
    }
}