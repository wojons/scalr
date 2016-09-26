<?php

namespace Scalr\Tests\Util\Api;

use Scalr\Tests\Fixtures\Util\Api\TestMutator;
use Scalr\Tests\TestCase;

/**
 * SpecMutators Test
 *
 * @author N.V.
 */
class SpecMutatorTest extends TestCase
{

    public static function providerModifications()
    {
        return [
            [
                [
                    'list'      => [1, 2, 3, 4, 5],
                    'foo'       => [ 'bar' => 'foobar' ],
                    'foobar'    => true,
                    'barfoo'    => [ 'test' => 'test', 'test1' => [1, 2, 3] ]
                ],
                [
                    [ 'list', [2, 4] ],
                    [ 'foo.bar' ],
                    [ 'barfoo' ]
                ],
                [
                    'list'      => [1, 3, 5],
                    'foo'       => [  ],
                    'foobar'    => true
                ]
            ]
        ];
    }

    /**
     * @test
     * @dataProvider providerModifications
     *
     * @param array $data
     * @param array $modifications
     * @param array $expected
     */
    public function testModifications($data, $modifications, $expected)
    {
        $mutator = new TestMutator($modifications);

        $mutator->setSpec($data);

        $mutator->apply(\Scalr::getContainer()->config(), 'test');

        $this->assertEquals($data, $expected);
    }
}