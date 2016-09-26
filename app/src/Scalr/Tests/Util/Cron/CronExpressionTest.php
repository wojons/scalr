<?php

namespace Scalr\Tests\Util\Cron;

use Scalr\Tests\TestCase;
use Scalr\Util\Cron\CronExpression;
use DateTimeZone, DateTime;

/**
 * CronExpressionTest
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0 (16.09.2014)
 */
class CronExpressionTest extends TestCase
{
    //Let's imagine system timezone is +05:45 NPT
    const SYSTEM_TS = 'Asia/Katmandu';

    /**
     * To store php.ini original timezone
     * @var string
     */
    private $initz;

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::setUp()
     */
    protected function setUp()
    {
        $this->initz = date_default_timezone_get();
        date_default_timezone_set(self::SYSTEM_TS);
    }

	/**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::tearDown()
     */
    protected function tearDown()
    {
        date_default_timezone_set($this->initz);
    }

    /**
     * Data provider for testExpression method
     *
     * @return array
     */
	public function providerExpression()
    {
        return [
            ['*/2 */2 * * *', null, [
                // System time        => due to run
                '2015-08-08 00:00:00' => true,
                '2015-08-08 00:01:00' => false,
                '2015-08-08 01:00:00' => false,
                '2015-08-08 06:30:00' => true,
                '2015-08-08 22:00:00' => true,
            ]],
            ['*/2 */2 * * *', 'Etc/GMT-4', [
                '2015-08-08 00:01:00' => true,  //2015-08-07 22:16 +04:00
                '2015-08-08 00:00:00' => false, //2015-08-07 22:15 +04:00
                '2015-08-08 01:00:00' => false, //2015-08-07 23:16 +04:00
                '2015-08-08 06:31:00' => true,  //2015-08-08 04:46 +04:00
                '2015-08-08 07:32:00' => false, //2015-08-08 05:47 +04:00
            ]],
            ['* * * * *', null, [
                '2018-01-08 12:10:13' => true,
                '2015-12-12 07:03:00' => true,
            ]],
            ['0-59/4 1-12/2 * * *', null, [
                '2015-06-23 13:04:00' => false,
                '2015-06-23 11:04:00' => true,
            ]],
            ['* 20,21,22 * * *', null, [
                '2017-07-23 21:50:04' => true,
                '2017-06-23 22:51:02' => true,
                '2017-06-23 20:00:01' => true,
                '2017-06-23 01:01:00' => false,
            ]],
            ['37-40,55,59 * */9 * *', null, [
                '2019-03-09 21:38:04' => true,
                '2019-03-10 23:40:00' => false,
                '2019-03-27 01:59:59' => true,
                '2019-03-29 02:59:59' => false,
            ]],
            ['10 * * 1-12/2 *', null, [
                '2019-03-10 21:10:04' => true,
                '2019-03-10 00:10:00' => true,
                '2019-03-10 00:00:00' => false,
                '2019-02-10 00:00:00' => false,
                '2019-01-01 00:10:00' => true,
                '2019-05-10 21:10:04' => true,
                '2019-07-10 00:10:00' => true,
                '2019-09-10 00:10:00' => true,
                '2019-11-10 00:10:00' => true,
            ]],
            ['0 0 * * 1,7', null, [
                '2019-03-10 00:00:08' => true,
                '2019-03-11 00:00:08' => true,
                '2019-03-12 00:00:08' => false,
            ]],
            ['*/20 * * * *', null, [
                '2019-03-10 00:00:00' => true,
                '2019-03-11 00:20:00' => true,
                '2019-03-12 00:40:00' => true,
                '2019-03-12 00:41:00' => false,
            ]],
        ];
    }

    /**
     * @test
     * @dataProvider providerExpression
     */
    public function testExpression($expression, $expressionTimezone = null, $data)
    {
        $ce = new CronExpression($expression, $expressionTimezone);

        foreach ($data as $time => $due) {
            $dt = new DateTime($time);

            $et = clone $dt;
            $et->setTimezone(new DateTimeZone($expressionTimezone ?: self::SYSTEM_TS));

            $this->assertEquals($due, $ce->isDue($dt), sprintf("Expr: '%s' against system: %s -> cron: %s", $expression,
                $dt->format('r'),
                $et->format('r')
            ));
        }
    }
}