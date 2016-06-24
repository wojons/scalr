<?php
namespace Scalr\Tests\Api\Rest\Routing;

use Scalr\Tests\TestCase;
use Scalr\Api\Rest\Routing\Table;
use Scalr\Api\Rest\Routing\Route;
use Scalr\Api\Rest\Http\Request;

/**
 * TableTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4 (19.02.2015)
 */
class TableTest extends TestCase
{
    /**
     * @test
     */
    public function testAppendRoute()
    {
        /* @var $tableItem \Scalr\Api\Rest\Routing\Item */

        $ret = $table = new Table();
        $route1 = (new Route('/api/users/v:apiversion/hotels', function(){}, ['apiversion' => '[\d]+']))->setMethods([Request::METHOD_GET]);
        $route2 = (new Route('/api/users/v:apiversion/flights', function(){}, ['apiversion' => '[\d]+']))->setMethods([Request::METHOD_POST]);

        $table->appendRoute($route1);
        $table->appendRoute($route2);

        //api level
        $this->assertEquals(1, $table->count());
        $this->assertSubClassOf('Traversable', $table);
        $this->assertInstanceOf('ArrayAccess', $table);
        $this->assertArrayHasKey('api', $table);
        $this->assertEmpty($table->hasRegexp());
        $this->assertEquals([], $table->getRegexpItemIterator()->getArrayCopy());

        $tableItem = $table['api'];

        $this->assertInstanceOf('Scalr\Api\Rest\Routing\Item', $tableItem);
        $this->assertInstanceOf('Scalr\Api\Rest\Routing\PathPart', $tableItem->getPathPart());
        $this->assertEquals('api', $tableItem->getPathPart()->value);
        $this->assertEmpty($tableItem->routes);

        //users level
        $table = $tableItem->getTable();
        $this->assertEquals(1, $table->count());
        $this->assertSubClassOf('Traversable', $table);
        $this->assertInstanceOf('ArrayAccess', $table);
        $this->assertArrayHasKey('users', $table);
        $this->assertEmpty($table->hasRegexp());
        $this->assertEquals([], $table->getRegexpItemIterator()->getArrayCopy());

        $tableItem = $table['users'];

        $this->assertInstanceOf('Scalr\Api\Rest\Routing\Item', $tableItem);
        $this->assertInstanceOf('Scalr\Api\Rest\Routing\PathPart', $tableItem->getPathPart());
        $this->assertEquals('users', $tableItem->getPathPart()->value);
        $this->assertEmpty($tableItem->routes);

        //v:apiversion level
        $table = $tableItem->getTable();
        $this->assertEquals(1, $table->count());
        $this->assertSubClassOf('Traversable', $table);
        $this->assertInstanceOf('ArrayAccess', $table);

        $iterator = $table->getIterator();
        list($uriPart, $tableItem) = each($iterator);

        $this->assertContains('^v(?<apiversion>', $uriPart);
        $this->assertNotEmpty($table->hasRegexp());
        $this->assertInstanceOf('Scalr\Api\Rest\Routing\Item', $tableItem);
        $this->assertInstanceOf('Scalr\Api\Rest\Routing\PathPart', $tableItem->getPathPart());
        $this->assertContains('^v(?<apiversion>', $tableItem->getPathPart()->value);
        $this->assertTrue(!$tableItem->getPathPart()->isString());
        $this->assertEmpty($tableItem->routes);

        //last level
        $table = $tableItem->getTable();
        $this->assertEquals(2, $table->count());
        $this->assertSubClassOf('Traversable', $table);
        $this->assertInstanceOf('ArrayAccess', $table);
        $this->assertArrayHasKey('hotels', $table);
        $this->assertArrayHasKey('flights', $table);
        $this->assertEmpty($table->hasRegexp());
        $this->assertEquals([], $table->getRegexpItemIterator()->getArrayCopy());
        $this->assertNotEmpty($table['hotels']->routes);
        $this->assertEquals($route1, $table['hotels']->routes[0]);
        $this->assertNotEmpty($table['flights']->routes);
        $this->assertEquals($route2, $table['flights']->routes[0]);

        return $ret;
    }

    /**
     * @test
     * @depends testAppendRoute
     * @param   Table $table
     */
    public function testGetMatchedRoutes($table)
    {
        $this->assertEmpty($table->getMatchedRoutes(Request::METHOD_GET, '/nothing'));

        //Existing routes
        $res = $table->getMatchedRoutes(Request::METHOD_GET, '/api/users/v12/hotels');
        $this->assertNotEmpty($res);
        $this->assertEquals(1, count($res));

        //Trailing slash should not be a trouble
        $res = $table->getMatchedRoutes(Request::METHOD_GET, '/api/users/v12/hotels/');
        $this->assertNotEmpty($res);
        $this->assertEquals(1, count($res));

        $route = current($res);

        $this->assertInstanceOf('Scalr\Api\Rest\Routing\Route', $route);
        $this->assertEquals(['apiversion' => 12], $route->getParams());


        $res = $table->getMatchedRoutes(Request::METHOD_POST, '/api/users/v1/flights');
        $this->assertNotEmpty($res);
        $this->assertEquals(1, count($res));

        $route = current($res);

        $this->assertInstanceOf('Scalr\Api\Rest\Routing\Route', $route);
        $this->assertEquals(['apiversion' => 1], $route->getParams());

        //Missing method on existing route
        $this->assertEmpty($table->getMatchedRoutes(Request::METHOD_GET, '/api/users/v1/flights'));
        //Not complete path
        $this->assertEmpty($table->getMatchedRoutes(Request::METHOD_POST, '/api/users/v1'));

        //Two different routes on the same path with different methods
        $getFlightsRoute = (new Route('/api/users/v:apiversion/flights', function(){}, ['apiversion' => '[\d]+']))->setMethods([Request::METHOD_GET]);
        $table->appendRoute($getFlightsRoute);

        $res = $table->getMatchedRoutes(Request::METHOD_GET, '/api/users/v1/flights');
        $this->assertNotEmpty($res);
        $this->assertEquals(1, count($res));

        $route = current($res);

        $this->assertSame($getFlightsRoute, $route);

        //Two different routes on the same path with different requirements and handlers
        $getFlightsRoute2 = (new Route('/api/users/v:apiversion/flights', function () {},['apiversion' => '[\w]+']))->setMethods([Request::METHOD_GET]);
        $table->appendRoute($getFlightsRoute2);

        //It matches the first
        $res = $table->getMatchedRoutes(Request::METHOD_GET, '/api/users/v1/flights');
        $this->assertEquals(1, count($res));
        $this->assertSame($getFlightsRoute, $res[0]);

        //It does not matches the first but matches the second
        $res = $table->getMatchedRoutes(Request::METHOD_GET, '/api/users/version2/flights');
        $this->assertEquals(1, count($res));
        $this->assertSame($getFlightsRoute2, $res[0]);

        //Two different routes on the same path with the same requirements, same methods but with different handlers
        $getFlightsRoute3 = (new Route('/api/users/v:apiversion/flights', function () {/* handler 2 */},['apiversion' => '[\w]+']))->setMethods([Request::METHOD_GET]);
        $table->appendRoute($getFlightsRoute3);

        $res = $table->getMatchedRoutes(Request::METHOD_GET, '/api/users/version2/flights');
        $this->assertEquals(2, count($res));
        $this->assertContains($getFlightsRoute2, $res);
        $this->assertContains($getFlightsRoute3, $res);
    }
}