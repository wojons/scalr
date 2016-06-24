<?php

namespace Scalr\Tests\Functional\Ui\Controller\Farms;

use Scalr\Tests\WebTestCase;
use Scalr_UI_Controller_Farms_Builder;

/**
 * Unit test for the Scalr_UI_Controller_Farms class
 *
 * @author  Sergy Goncharov <s.honcharov@scalr.com>
 * @since   02.12.2016
 */
class BuilderTest extends WebTestCase
{

    /**
     * @var Scalr_UI_Controller_Farms_Builder
     */
    private $builder;

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();

        $this->builder = $this->getMock('Scalr_UI_Controller_Farms_Builder', [], [], '', false);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\WebTestCase::tearDown()
     */
    protected function tearDown()
    {
        $this->builder = null;

        parent::tearDown();
    }

    public function providerCheckBounds()
    {
        return [
            // invalid type, skip set build error
            [false, null,   '2', 'integer'],
            [false, '',     '2', 'integer'],
            [false, 'not empty string', '2', 'integer'],
            [false, '1.1', '2', 'integer'],
            [false, [], '2', 'integer'],
            [false, ['1'], '2', 'integer'],
            [false, [0 => '1'], '2', 'integer'],
            [false, (object) [], '2', 'integer'],
            [false, (object) [0 => '1'], '2', 'integer'],

            // valid type, invalid bounds, skip set build error
            [false, '2',  '1', 'integer'],
            [false, '2',  '2', 'integer'],
            [false, '2.2',  '2', 'float'],
            [false, '2.2',  '2.2', 'float'],

            // valid type, invalid bounds, default Error message
            [false, '2',    '2', 'float', null, null],
            [false, '2.2',  '2', 'float', true, null],
            [false, '2',    '2', 'float', null, ''],
            [false, '2.2',  '2', 'float', true, ''],

            // valid type, invalid bounds, custom Error message
            [false, '2.2',  '2', 'float', null, 'Check weapon @ door.'],
            [false, '2.2',  '2', 'float', null, ['This', 'is']],
            [false, '2.2',  '2', 'float', null, ['custom' => 'error']],
            [false, '2.2',  '2', 'float', null, (object)['message' => '.']],

            // valid bounds
            [true,  '1',    '2', 'integer'],
            [true,  '2',    '2', 'integer', true],
            [true,  '2.0',  '2.1', 'float'],
            [true,  '2.0',  '2', 'float', true],
        ];
    }

    /**
     * @test
     * @dataProvider providerCheckBounds
     * @covers Scalr_UI_Controller_Farms_Builder::checkBounds
     *
     * @param bool $valid Expected result of testing.
     * @param string $minBound Minimum bound.
     * @param string $maxBound Maximum bound.
     * @param string $validator Numeric type of bounds. [integer|float=default]
     * @param bool $allowEqual Is equal bounds allowed.
     * @param mixed $msg Custom Error message.
     */
    public function testCheckBounds($valid, $minBound, $maxBound, $validator, $allowEqual = false, $msg = false)
    {
        $roleId        = 33;
        $setting       = 'scaling';
        $methodName    = 'checkBounds';
        $defaultErrMsg = 'Invalid bounds.';

        $checkBoundsFn = self::getAccessibleMethod($this->builder, $methodName);
        $checkTypeFn = $validator === 'integer' ? 'is_int' : 'is_float';
        $errCountBefore = $this->builder->errors['error_count'];

        $ret = $checkBoundsFn->invoke($this->builder, $roleId, $setting, $minBound, $maxBound, $validator, $allowEqual, $msg);

        if ($valid) {
            $this->assertTrue(
                is_array($ret) && count($ret) === 2 && isset($ret[0]) &&
                $checkTypeFn($ret[0]) && isset($ret[1]) && $checkTypeFn($ret[1]) &&
                ($allowEqual ? $ret[0] <= $ret[1] : $ret[0] < $ret[1]),
                'Returns sorted array with two elements, both integer or float type.'
            );

        } else {
            $this->assertFalse($ret, 'Check not passed.');

            if ($msg === false) {
                $this->assertEquals(
                    $errCountBefore,
                    $this->builder->errors['error_count'],
                    'Explicitly set Error message to *FALSE* prevents set build error.'
                );

            } else {
                $this->assertEquals(
                    $errCountBefore + 1,
                    $this->builder->errors['error_count'],
                    'Error count have been incremented.'
                );

                if (!$msg) {
                    $this->assertEquals(
                        $defaultErrMsg,
                        $this->builder->errors['roles'][$roleId][$setting],
                        'Not provided Error message leads to set default one.'
                    );

                } else {
                    $this->assertEquals(
                        $msg,
                        $this->builder->errors['roles'][$roleId][$setting],
                        'Provided Error message used.'
                    );
                }
            }
        }
    }
}
