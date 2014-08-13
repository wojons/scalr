<?php
namespace Scalr\Tests\DataType\Iterator;

use Scalr\Tests\TestCase;
use Scalr\Tests\Fixtures\DataType\Iterator\TestFilterIterator1;
use ArrayIterator;

/**
 * FilterTest
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.0 (15.05.2014)
 */
class FilterTest extends TestCase
{
    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::getFixturesDirectory()
     */
    public function getFixturesDirectory()
    {
        return parent::getFixturesDirectory() . '/DataType/Iterator';
    }

    /**
     * Provider for testFilterIterator1
     *
     * @return array
     */
    public function providerFilterIterator1()
    {
        return [
           [[1, 2, 3, 4], [1 => 2, 3 => 4]],
           [[0 => 30, 'dev' => 3, 'less' => 4, 'foo' => 5], [0 => 30, 'less' => 4]],
           [[], []],
        ];
    }

    /**
     * @test
     * @dataProvider providerFilterIterator1
     * @param   array   $array
     * @param   array   $result
     */
    public function testFilterIterator1($array, $expected)
    {
        $iterator = new TestFilterIterator1(new ArrayIterator($array));

        $res = [];
        foreach ($iterator as $key => $current) {
            $res[$key] = $current;
        }

        $this->assertEquals($expected, $res);
    }
}