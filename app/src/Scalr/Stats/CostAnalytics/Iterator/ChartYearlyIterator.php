<?php

namespace Scalr\Stats\CostAnalytics\Iterator;

use Scalr\Stats\CostAnalytics\ChartPointInfo;
use DateTime, DateTimeZone;

/**
 * ChartYearlyIterator
 *
 * This iterator is used to iterate over the date period
 * according to cost analytics data retention policy.
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.0 (03.12.2014)
 */
class ChartYearlyIterator extends ChartPeriodIterator
{
    /**
     * Constructor
     *
     * @param   string       $start     The start date of the period 'YYYY-mm-dd'
     * @param   string       $end       optional End date
     * @param   string       $timezone  optional Timezone
     * @throws  \InvalidArgumentException
     */
    public function __construct($start, $end = null, $timezone = 'UTC')
    {
        $this->mode = 'year';
        $this->timezone = new DateTimeZone($timezone);
        $this->today = $this->getTodayDate();
        $this->start = new DateTime(($start instanceof DateTime ? $start->format('Y-m-d 00:00:00') : $start), $this->timezone);
        $this->end = (!empty($end) ? new DateTime(($end instanceof DateTime ? $end->format('Y-m-d 00:00:00') : $end), $this->timezone) : null);

        //Previous period is the previous year started from the first day of the year
        $this->prevInterval = new \DateInterval('P1Y');
        $this->interval = '1 month';

        $this->start = new DateTime($this->start->format('Y-01-01'), $this->timezone);
        $this->end = clone $this->start;
        $this->end->modify('+1 year -1 day');

        $this->prevStart = clone $this->start;
        $this->prevStart->sub($this->prevInterval);

        $this->wholePeriodPerviousEnd = clone $this->start;
        $this->wholePeriodPerviousEnd->modify('-1 day');

        $this->determinePrevEnd();

        $endoftheday = new \DateInterval('PT23H59M59S');

        $this->end->add($endoftheday);
        $this->prevEnd->add($endoftheday);
        $this->wholePeriodPerviousEnd->add($endoftheday);

        if (!$this->di)
            $this->di = \DateInterval::createFromDateString($this->interval);

        $this->dt = clone $this->start;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::current()
     * @return ChartPointInfo
     */
    public function current()
    {
        if (!isset($this->c[$this->i])) {
            $chartPoint = new ChartPointInfo($this);
            $previousPeriodDt = clone $chartPoint->dt;
            $previousPeriodDt->sub($this->getPreviousPeriodInterval());

            $chartPoint->show = $chartPoint->label = $chartPoint->dt->format('M');

            $chartPoint->key = $chartPoint->dt->format('Y-m');
            $chartPoint->previousPeriodKey = $previousPeriodDt->format('Y-m');

            $this->c[$this->i] = $chartPoint;
        }

        return $this->c[$this->i];
    }
} 