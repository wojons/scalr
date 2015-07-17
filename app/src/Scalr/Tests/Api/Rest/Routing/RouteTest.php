<?php
namespace Scalr\Tests\Api\Rest\Routing;

use Scalr\Tests\TestCase;
use Scalr\Api\Rest\Routing\Route;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Rest\ApiApplication;
use ArrayObject;

/**
 * RouteTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4 (20.02.2015)
 */
class RouteTest extends TestCase
{
    /**
     * Gets route fixture object
     *
     * @return \Scalr\Api\Rest\Routing\Route Gets route fixture object
     */
    public function getRouteFixture()
    {
        $route = new Route('/api/path/:id', function(){}, ['id' => '[\d]+']);
        $route->setMethods([Request::METHOD_GET]);

        return $route;
    }

    /**
     * @test
     */
    public function testSetPath()
    {
        $route = $this->getRouteFixture();

        $route->setPath('/new/path/:id');
        $this->assertEquals('/new/path/:id', $route->getPath());

        //Path without leading slash and space suffix
        $route->setPath('path-without-leading-slash ');
        $this->assertEquals('/path-without-leading-slash', $route->getPath());

        //utf-8 characters in the path
        $utf8 = "\xD1\x80\xD0\xB5\xD1\x86\xD0\xB5\xD0\xBF\xD1\x82";
        $route->setPath("/" . $utf8 . "/recipe");
        $this->assertEquals("/" . $utf8 . "/recipe", $route->getPath());
    }

    /**
     * @test
     */
    public function testSetDefaults()
    {
        $route = $this->getRouteFixture();

        $defaults = $route->getDefaults();
        $this->assertInternalType('array', $defaults);

        $defaults['foo'] = 'bar';

        $route->setDefaults($defaults);

        $this->assertArrayHasKey('foo', $route->getDefaults());
        $this->assertEquals('bar', $route->getDefaults()['foo']);

        $route->setDefaults(['controller' => function(){}]);

        $this->assertEmpty($route->getDefaults()['methods']);

        //callable controller
        $route->setDefaults('strrev');
        $this->assertEquals('strrev', $route->getDefaults()['controller']);

        //restore previous state
        $route->setDefaults($defaults);

        //Methods should not be overridden here
        $route->addDefaults('strrev');
        $this->assertEquals('strrev', $route->getDefaults()['controller']);
        $this->assertTrue($route->hasMethod(Request::METHOD_GET));
    }

    /**
     * @test
     */
    public function testSetRequirements()
    {
        $route = $this->getRouteFixture();

        $route->setRequirements(['id' => 'string']);

        $this->assertArrayHasKey('id', $route->getRequirements());
        $this->assertEquals('string', $route->getRequirements()['id']);
    }

    /**
     * @test
     */
    public function setMiddleware()
    {
        $route = $this->getRouteFixture();

        $route->setMiddleware(['strrev', 'crc32']);

        $arr = $route->getMidleware();

        $this->assertContains('strrev', $arr);
        $this->assertContains('crc32', $arr);

        foreach (['this-is-not-callable',['not-callable-arr']] as $arg) {
            try {
                $route->addMiddleware($arg);
                $this->assertTrue(false, 'InvalidArgumentException must be thrown here.');
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * @test
     */
    public function setMethods()
    {
        $route = $this->getRouteFixture();

        $this->assertContains(Request::METHOD_GET, $route->getMethods());

        $route->setMethods([Request::METHOD_POST]);
        $this->assertTrue($route->hasMethod(Request::METHOD_POST));
        $this->assertFalse($route->hasMethod(Request::METHOD_GET));

        $route->addMethod(Request::METHOD_HEAD);
        $this->assertTrue($route->hasMethod(Request::METHOD_HEAD));
        $this->assertTrue($route->hasMethod(Request::METHOD_POST));

        //Invalid method
        try {
            $route->addMethod('invalid-method');
            $this->assertTrue(false, 'InvalidArgumentException must be thrown here.');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @test
     */
    public function testMatches()
    {
        $route = $this->getRouteFixture();

        $this->assertFalse($route->matches('/api'));
        $this->assertFalse($route->matches('/api/foo'));
        $this->assertFalse($route->matches('/api/path/:id'));

        $this->assertTrue($route->matches('/api/path/123'));
        $this->assertCount(1, $route->getParams());
        $this->assertArrayHas(123, 'id', $route->getParams());

        $this->assertTrue($route->matches('/api/path/167'));
        $this->assertCount(1, $route->getParams());
        $this->assertArrayHas(167, 'id', $route->getParams());

        $this->assertFalse($route->matches('/api/path/892/23'));
        $this->assertCount(0, $route->getParams());

        $route2 = new Route('/api/path/(:namepath)+', function(){}, ['namepath' => '[a-z/-]+']);
        $route2->setMethods([Request::METHOD_GET]);

        $pathName = 'news/recent/open-stack-journey';
        $this->assertTrue($route2->matches('/api/path/' . $pathName));
        $this->assertArrayHas($pathName, 'namepath', $route2->getParams());

        $pathName = '_0news/recent';
        $this->assertFalse($route2->matches('/api/path/' . $pathName));
    }

    /**
     * @test
     */
    public function testGetHandler()
    {
        //Application instance is needed to setup container
        $app = new ApiApplication([ApiApplication::SETTING_API_VERSION => '1']);

        $route = $this->getRouteFixture();

        $mdHandleApiVersion = [$app, 'handleApiVersion'];

        $route->addMiddleware($mdHandleApiVersion);

        $handler1 = function($id) {};
        $handler2 = function($id) {return 1;};

        $this->assertInternalType('callable', $route->getHandler());

        $route->addDefaults(['controller' => $handler1]);
        $this->assertSame($handler1, $route->getHandler());

        $route->addDefaults($handler2);
        $this->assertSame($handler2, $route->getHandler());

        //AbstractController based handler
        $route->setDefaults(['controller' => $app->getRouteHandler('Admin_Users:get')]);
        $this->assertInternalType('callable', $route->getHandler());
        $this->assertInstanceOf('Scalr\Api\Service\Admin\V1\Controller\Users', $route->getHandler()[0]);
        $this->assertEquals('get', $route->getHandler()[1]);
    }

    /**
     * @test
     */
    public function testDipatch()
    {
        $me = $this;
        $app = new ApiApplication();

        $actual = new ArrayObject([]);

        $route = $this->getRouteFixture();

        $route->addMiddleware([$app, 'handleApiVersion']);

        $route->setMiddleware([function($r) use ($actual) {
            $actual['md1'] = $r->getPath();
        }, function($r) use ($actual) {
            $actual['md2'] = true;
        }]);

        $route->addDefaults(function($id) use ($route, $me, $actual) {
            $me->assertEquals(123, $id);
            $me->assertArrayHas($route->getPath(), 'md1', $actual, 'First middleware has not been run.');
            $me->assertArrayHas(true, 'md2', $actual, 'Second middleware has not been run.');
            $actual['handler'] = true;
        });

        $this->assertTrue($route->matches('/api/path/123'));

        $this->assertEquals(true, $route->dispatch());

        $this->assertArrayHasKey('handler', $actual, 'Route handler has not been run.');
    }
}