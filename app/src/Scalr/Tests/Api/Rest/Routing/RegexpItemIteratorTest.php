<?php
namespace Scalr\Tests\Api\Rest\Routing;

use Scalr\Tests\TestCase;
use Scalr\Api\Rest\Routing\RegexpItemIterator;
use Scalr\Api\Rest\Routing\PathPart;
use Scalr\Api\Rest\Routing\Item;

/**
 * RegexpItemIteratorTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4 (21.02.2015)
 */
class RegexpItemIteratorTest extends TestCase
{
    /**
     * @test
     */
    public function testConstructor()
    {
        $stringItem = new Item(new PathPart('string'));
        $regexpItem = new Item(new PathPart('~^[\d]+$~', PathPart::TYPE_REGEXP));

        $this->assertTrue($stringItem->getPathPart()->isString());
        $this->assertTrue($regexpItem->getPathPart()->isRegexp());

        $iterator = new RegexpItemIterator(new \ArrayIterator([
            $stringItem, $regexpItem, $stringItem, $stringItem, $regexpItem
        ]));

        $count = 0;

        foreach ($iterator as $item) {
            /* @var $item Item */
            $this->assertFalse($item->getPathPart()->isString());
            $count++;
        }

        $this->assertEquals(2, $count);
    }
}