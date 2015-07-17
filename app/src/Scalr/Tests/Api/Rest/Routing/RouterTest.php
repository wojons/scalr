<?php
namespace Scalr\Tests\Api\Rest\Routing;

use Scalr\Tests\TestCase;
use Scalr\Api\Rest\Routing\Router;
use Scalr\Api\Rest\Http\Request;

/**
 * RouterTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4 (20.02.2015)
 */
class RouterTest extends TestCase
{
    /**
     * @test
     */
    public function testMap()
    {
        $table = $this->getMockBuilder('Scalr\Api\Rest\Routing\Table')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $path = '/path';
        $group = '/api';
        $method = Request::METHOD_GET;
        $uri = '/api/path';

        $route = $this->getMockBuilder('Scalr\Api\Rest\Routing\Route')
            ->setConstructorArgs([$path])
            ->getMock()
        ;

        $table->expects($this->once())->method('appendRoute')->with($route);
        $table->expects($this->once())->method('getMatchedRoutes')->with($method, $uri)->willReturn([$route]);

        $route->expects($this->at(0))->method('getPath')->will($this->returnValue($path));
        $route->expects($this->once())->method('setPath')->with($group . $path);
        $route->expects($this->once())->method('addMiddleware')->with(['strrev']);

        $router = new Router();
        $router->pushGroup($group, ['strrev']);

        $refProp = new \ReflectionProperty($router, 'routingTable');
        $refProp->setAccessible(true);
        $refProp->setValue($router, $table);

        $router->map($route);
        $router->getMatchedRoutes($method, $uri);

        //It should not actually invoke getMatchedRoutes on routing table here
        //because it takes it from the internal cache
        $router->getMatchedRoutes($method, $uri . '/another');
    }
}