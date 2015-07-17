<?php
namespace Scalr\Tests\Api\Rest\Routing;

use Scalr\Tests\TestCase;
use Scalr\Api\Rest\Routing\Item;

/**
 * ItemTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4 (19.02.2015)
 */
class ItemTest extends TestCase
{

    /**
     * @test
     */
    public function testConstructor()
    {
        $pathPart = $this->getMock('Scalr\Api\Rest\Routing\PathPart');

        $item = new Item($pathPart);

        $this->assertInternalType('array', $item->routes);
        $this->assertEquals([], $item->routes);

        $this->assertEquals($pathPart, $item->getPathPart());

        $this->assertInstanceOf('Scalr\Api\Rest\Routing\Table', $item->getTable());
        $this->assertEquals(0, count($item->getTable()));
        $this->assertSubClassOf('Traversable', $item->getTable());
    }

}