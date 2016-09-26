<?php

namespace Scalr\Tests\Stats\CostAnalytics\Iterator;

use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\ChartPointInfo;
use Scalr\Tests\WebTestCase;

/**
 * ChartPeriodIterator test
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (29.05.2014)
 */
class ChartPeriodIteratorTest extends WebTestCase
{

    /**
     * {@inheritdoc}
     * @see \Scalr\Tests\TestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();

        if (!\Scalr::getContainer()->analytics->enabled) {
            $this->markTestSkipped("Cost analytics has not been enabled in the configuration.");
        }
    }

    private function getFixtureDates($start, $end, \DateInterval $interval, $fmt = 'Y-m-d')
    {
        $arr = [];

        $endDate = new \DateTime($end);

        for ($i = new \DateTime($start); $i <= $endDate; $i->add($interval)) {
            $arr[] = $i->format($fmt);
        }

        return $arr;
    }

    public function providerConstructor()
    {
        $array = [];

        //Weeks should start from sunday
        $array[] = [
           'week',
           '2014-04-06',
           '2014-04-12',
           [
               'startDate'     => '2014-04-06',
               'endDate'       => '2014-04-12',
               'prevStartDate' => '2014-03-30',
               'prevEndDate'   => '2014-04-05',
               'interval'      => '1 day',
               'keys'          => $this->getFixtureDates('2014-04-06', '2014-04-12', new \DateInterval('P1D'), 'Y-m-d'),
            ],
        ];

        $array[] = [
           'week',
           '2014-04-07', //Start date should be forcibly changed to last sunday
           null,
           [
               'startDate'     => '2014-04-06',
               'endDate'       => '2014-04-12',
               'prevStartDate' => '2014-03-30',
               'prevEndDate'   => '2014-04-05',
               'interval'      => '1 day',
               'keys'          => $this->getFixtureDates('2014-04-06', '2014-04-12', new \DateInterval('P1D'), 'Y-m-d'),
            ],
        ];

        $array[] = [
           'month',
           '2013-04-03', //Start date should be forcibly changed to first date of the month
           null,
           [
               'startDate'     => '2013-04-01',
               'endDate'       => '2013-04-30',
               'prevStartDate' => '2013-03-01',
               //We should compare whole months
               'prevEndDate'   => '2013-03-31',
               'interval'      => '1 day',
               'keys'          => $this->getFixtureDates('2013-04-01', '2013-04-30', new \DateInterval('P1D'), 'Y-m-d'),
            ],
        ];

        $array[] = [
           'month',
           '2014-03-01',
           null,
           [
               'startDate'     => '2014-03-01',
               'endDate'       => '2014-03-31',
               'prevStartDate' => '2014-02-01',
               'prevEndDate'   => '2014-02-28',
               'interval'      => '1 day',
               'keys'          => $this->getFixtureDates('2014-03-01', '2014-03-31', new \DateInterval('P1D'), 'Y-m-d'),
            ],
        ];

        $array[] = [
           'quarter',
           '2013-04-01',
           null,
           [
               'startDate'     => '2013-04-01',
               'endDate'       => '2013-06-30',
               'prevStartDate' => '2013-01-01',
               //We should compare whole quarters
               'prevEndDate'   => '2013-03-31',
               'interval'      => '1 week',
               'keys'          => ['201313', '201314', '201315', '201316', '201317', '201318', '201319', '201320', '201321', '201322', '201323', '201324', '201325', '201326'],
            ],
        ];

        return $array;
    }

    /**
     * @test
     * @dataProvider providerConstructor
     */
    public function testConstructor($mode, $start, $end, $fixture)
    {
        $iterator = ChartPeriodIterator::create($mode, $start, $end);

        $this->assertEquals($fixture['interval'], $iterator->getInterval());

        $this->assertEquals($fixture['startDate'], $iterator->getStart()->format('Y-m-d'), 'Start date does not match.');

        $this->assertEquals($fixture['endDate'], $iterator->getEnd()->format('Y-m-d'), 'End date does not match');

        $this->assertEquals($fixture['prevStartDate'], $iterator->getPreviousStart()->format('Y-m-d'), 'Previous period Start date does not match.');

        $this->assertEquals($fixture['prevEndDate'], $iterator->getPreviousEnd()->format('Y-m-d'), 'Previous period End date does not match.');

        foreach ($iterator as $chartPoint) {
            /* @var $chartPoint ChartPointInfo */
            $this->assertTrue(isset($fixture['keys'][$chartPoint->i]),
                sprintf("Fixture with number %d is not expected.", $chartPoint->i));

            $this->assertEquals($fixture['keys'][$chartPoint->i], $chartPoint->key, "Keys does not match.");
        }
    }
}