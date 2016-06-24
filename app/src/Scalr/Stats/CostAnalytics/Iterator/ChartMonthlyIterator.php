<?php

namespace Scalr\Stats\CostAnalytics\Iterator;

use DateTime, DateTimeZone;
use Scalr\Stats\CostAnalytics\ChartPointInfo;

/**
 * ChartMonthlyIterator
 *
 * This iterator is used to iterate over the date period
 * according to cost analytics data retention policy.
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.0 (03.12.2014)
 */
class ChartMonthlyIterator extends ChartPeriodIterator
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
        $this->mode = 'month';
        $this->timezone = new DateTimeZone($timezone);
        $this->today = $this->getTodayDate();
        $this->start = new DateTime(($start instanceof DateTime ? $start->format('Y-m-d 00:00:00') : $start), $this->timezone);
        $this->end = (!empty($end) ? new DateTime(($end instanceof DateTime ? $end->format('Y-m-d 00:00:00') : $end), $this->timezone) : null);

        //Previous period is the previous month started from the the first day of the month
        $this->prevInterval = new \DateInterval('P1M');
        $this->interval = '1 day';

        $this->start = new DateTime($this->start->format('Y-m-01'), $this->timezone);
        $this->end = new DateTime($this->start->format('Y-m-t'), $this->timezone);

        $this->prevStart = clone $this->start;
        $this->prevStart->sub($this->prevInterval);

        $this->wholePeriodPerviousEnd = clone $this->prevStart;
        $this->wholePeriodPerviousEnd->modify('last day of this month');

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

            $chartPoint->label = $chartPoint->dt->format('M j');

            $chartPoint->key = $chartPoint->dt->format('Y-m-d');
            $chartPoint->previousPeriodKey = $previousPeriodDt->format('Y-m-d');

            $chartPoint->show = $chartPoint->i % 4 == 0 || $chartPoint->isLastPoint && $chartPoint->i % 4 > 2
                ? $chartPoint->dt->format('M j')
                : '';

            $this->c[$this->i] = $chartPoint;
        }

        return $this->c[$this->i];
    }

} 