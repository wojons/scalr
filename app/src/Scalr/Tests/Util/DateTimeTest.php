<?php

namespace Scalr\Tests\Util;

use Scalr\Tests\WebTestCase;
use DateTime;

/**
 * DateTimeTest
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (28.05.2014)
 */
class DateTimeTest extends WebTestCase
{

    public function providerYearweek()
    {
        $recordset = [];
        for ($year = 2014; $year <= 2018; $year++) {
            $recordset[] = [$year . '-01-01'];
            $recordset[] = [$year . '-01-02'];
            $recordset[] = [$year . '-01-03'];
            $recordset[] = [$year . '-01-04'];
            $recordset[] = [$year . '-01-05'];
            $recordset[] = [$year . '-01-06'];
            $recordset[] = [$year . '-01-07'];

            $recordset[] = [$year . '-09-09'];

            $recordset[] = [$year . '-12-31'];
            $recordset[] = [$year . '-12-30'];
            $recordset[] = [$year . '-12-29'];
            $recordset[] = [$year . '-12-28'];
            $recordset[] = [$year . '-12-27'];
            $recordset[] = [$year . '-12-26'];
            $recordset[] = [$year . '-12-25'];
            $recordset[] = [$year . '-12-24'];
        }
        return $recordset;
    }

    /**
     * @test
     * @dataProvider providerYearweek
     */
    public function testYearweek($date)
    {
        $db = \Scalr::getDb();
        $expected = $db->GetOne("SELECT YEARWEEK(?, 0)", [$date]);
        $this->assertEquals($expected, \Scalr_Util_DateTime::yearweek($date));
    }

    public function providerIncrescentTimeInterval()
    {
        return [
            ['2016-02-15 15:10:10', '2016-02-15 15:10:12', 'just now'],
            ['2016-02-15 15:10:10', '2016-02-15 15:15:55', '5 min ago'],
            ['2016-02-15 15:10:10', '2016-02-15 16:10:10', '3:10 PM'],
            ['2016-02-15 15:10:10', '2016-02-16 16:10:10', 'Yesterday, 3:10 PM'],
            ['2016-02-15 15:10:10', '2016-02-17 16:10:10', 'Feb. 15, 3:10 PM'],
            ['2016-02-15 15:10:10', '2017-02-17 16:10:10', 'Feb. 15, 2016, 3:10 PM']
        ];
    }

    /**
     * @test
     * @dataProvider providerIncrescentTimeInterval
     */
    public function testIncrescentTimeInterval($date, $curDate, $expected)
    {
        $this->assertEquals($expected, \Scalr_Util_DateTime::getIncrescentTimeInterval($date, $curDate));
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        $curDate = DateTime::createFromFormat('Y-m-d H:i:s', $curDate);
        $this->assertEquals($expected, \Scalr_Util_DateTime::getIncrescentTimeInterval($date, $curDate));
    }
}