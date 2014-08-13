<?php
namespace src\Scalr\Tests\Stats\CostAnalytics;

use DateTime, DateTimeZone;
use Scalr\Tests\TestCase;
use Scalr\Stats\CostAnalytics\Quarters;

/**
 * QuartersTest
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (05.05.2014)
 */
class QuartersTest extends TestCase
{
    /**
     * Data provider for testConstructor
     *
     * @return array
     */
    public function providerConstructor()
    {
        $array = [];

        $array[0] = [['01-01', '04-01', '07-01', '10-01'], [
            [
               'quarter'   => 1,
               'date'      => '2012-01-01',
               'year'      => '2012',
               'start'     => '2012-01-01',
               'end'       => '2012-03-31',
            ],
            [
               'quarter'   => 1,
               'date'      => '2012-02-12',
               'year'      => '2012',
               'start'     => '2012-01-01',
               'end'       => '2012-03-31',
            ],
            [
               'quarter'   => 3,
               'date'      => '2012-09-08',
               'year'      => '2012',
               'start'     => '2012-07-01',
               'end'       => '2012-09-30',
            ],
            [
               'quarter'   => 4,
               'date'      => '2012-12-12',
               'year'      => '2012',
               'start'     => '2012-10-01',
               'end'       => '2012-12-31',
            ]
        ]];

        $array[1] = [['12-20', '02-20', '06-20', '09-20'], [
            [
               'quarter'   => 1,
               'date'      => '2012-12-29',
               'year'      => '2013',
               'start'     => '2012-12-20',
               'end'       => '2013-02-19',
            ],
            [
               'quarter'   => 2,
               'date'      => '2013-02-20',
               'year'      => '2013',
               'start'     => '2013-02-20',
               'end'       => '2013-06-19',
            ],
            [
               'quarter'   => 2,
               'date'      => '2013-03-17',
               'year'      => '2013',
               'start'     => '2013-02-20',
               'end'       => '2013-06-19',
            ],
            [
               'quarter'   => 2,
               'date'      => '2013-06-08',
               'year'      => '2013',
               'start'     => '2013-02-20',
               'end'       => '2013-06-19',
            ],
            [
               'quarter'   => 4,
               'date'      => '2013-09-20',
               'year'      => '2013',
               'start'     => '2013-09-20',
               'end'       => '2013-12-19',
            ],
            [
               'quarter'   => 1,
               'date'      => '2013-01-01',
               'year'      => '2013',
               'start'     => '2012-12-20',
               'end'       => '2013-02-19',
            ],
        ]];

        $array[2] = [['01-01', '01-10', '01-20', '10-10'], [
            [
               'quarter'   => 1,
               'date'      => '2014-01-02',
               'year'      => '2014',
               'start'     => '2014-01-01',
               'end'       => '2014-01-09',
            ],
            [
               'quarter'   => 2,
               'date'      => '2014-01-11',
               'year'      => '2014',
               'start'     => '2014-01-10',
               'end'       => '2014-01-19',
            ],
            [
               'quarter'   => 4,
               'date'      => '2014-12-31',
               'year'      => '2014',
               'start'     => '2014-10-10',
               'end'       => '2014-12-31',
            ],
        ]];


        $array[3] = [['09-01', '09-15', '01-01', '04-01'], [
            [
               'quarter'   => 1,
               'date'      => '2013-09-11',
               'year'      => '2014',
               'start'     => '2013-09-01',
               'end'       => '2013-09-14',
            ],
            [
               'quarter'   => 2,
               'date'      => '2013-10-01',
               'year'      => '2014',
               'start'     => '2013-09-15',
               'end'       => '2013-12-31',
            ],
            [
               'quarter'   => 3,
               'date'      => '2014-01-01',
               'year'      => '2014',
               'start'     => '2014-01-01',
               'end'       => '2014-03-31',
            ],
            [
               'quarter'   => 4,
               'date'      => '2014-05-01',
               'year'      => '2014',
               'start'     => '2014-04-01',
               'end'       => '2014-08-31',
            ],
        ]];

        $array[4] = [['12-27', '04-02', '07-03', '10-04'], [
            [
               'quarter'   => 2,
               'date'      => '2014-07-02',
               'year'      => '2014',
               'start'     => '2014-04-02',
               'end'       => '2014-07-02',
            ],
        ]];

        return $array;
    }

    /**
     * @test
     * @dataProvider providerConstructor
     */
    public function testConstructor($days, $fixtures)
    {
        $quarters = new Quarters($days);
        foreach ($fixtures as $i => $v) {
            $this->assertEquals($v['quarter'], $quarters->getQuarterForDate($v['date']),
                sprintf('The number of the quarter for the date "%s" is expected to be %d.', $v['date'], $v['quarter']));

            $period = $quarters->getPeriodForQuarter($v['quarter'], $v['year']);

            $this->assertInternalType('object', $period);

            $this->assertEquals($v['start'], $period->start->format('Y-m-d'),
                sprintf('Start date is expected to be "%s".', $v['start']));

            $this->assertEquals($v['end'], $period->end->format('Y-m-d'),
                sprintf('End date is expected to be "%s".', $v['end']));

            $this->assertEquals($v['year'], $period->year);

            $this->assertEquals($v['quarter'], $period->quarter);

            $periodForDate = $quarters->getPeriodForDate(new DateTime($v['date'], new DateTimeZone('UTC')));

            $this->assertEquals($v['start'], $periodForDate->start->format('Y-m-d'),
                sprintf('getPeriodForDate for fixture#%d failed. Start date is expected to be "%s".', $i, $v['start']));

            $this->assertEquals($v['end'], $periodForDate->end->format('Y-m-d'),
                sprintf('getPeriodForDate for fixture#%d failed. End date is expected to be "%s".', $i, $v['end']));

            $this->assertEquals($v['year'], $periodForDate->year,
                sprintf('getPeriodForDate for fixture#%d failed. Year is expected to be "%s".', $i, $v['year']));

            $this->assertEquals($v['quarter'], $periodForDate->quarter,
                sprintf('getPeriodForDate for fixture#%d failed. Quarter is expected to be "%s".', $i, $v['quarter']));
        }
    }
}