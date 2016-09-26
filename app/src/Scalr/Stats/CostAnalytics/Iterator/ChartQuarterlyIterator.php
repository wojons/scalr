<?php

namespace Scalr\Stats\CostAnalytics\Iterator;

use DateTime, DateTimeZone;
use Scalr\Stats\CostAnalytics\ChartPointInfo;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Quarters;

/**
 * ChartQuarterlyIterator
 *
 * This iterator is used to iterate over the date period
 * according to cost analytics data retention policy.
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.0 (03.12.2014)
 */
class ChartQuarterlyIterator extends ChartPeriodIterator
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
        $this->mode = 'quarter';
        $this->timezone = new DateTimeZone($timezone);
        $this->today = $this->getTodayDate();
        $this->start = new DateTime(($start instanceof DateTime ? $start->format('Y-m-d 00:00:00') : $start), $this->timezone);
        $this->end = (!empty($end) ? new DateTime(($end instanceof DateTime ? $end->format('Y-m-d 00:00:00') : $end), $this->timezone) : null);

        $quarters = new Quarters(SettingEntity::getQuarters());

        $this->interval = '1 week';

        $currentPeriod = $quarters->getPeriodForDate($this->start);

        $this->start = $currentPeriod->start;
        $this->end = $currentPeriod->end;

        $this->wholePeriodPerviousEnd = clone $this->start;
        $this->wholePeriodPerviousEnd->modify('-1 day');

        $prevPeriod = $quarters->getPeriodForDate($this->wholePeriodPerviousEnd);
        $this->prevStart = $prevPeriod->start;

        $this->prevInterval = new \DateInterval('P' . sprintf("%d", $this->start->diff($this->end, true)->days) . 'D');

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

            $ddt = clone $chartPoint->dt;
            $ddt->modify('next saturday');

            if ($ddt > $chartPoint->end) {
                $ddt = $chartPoint->end;
            }

            $chartPoint->label = $chartPoint->dt->format('M j') . ' - ' . $ddt->format('M j');

            $chartPoint->key = \Scalr_Util_DateTime::yearweek($chartPoint->dt->format('Y-m-d'));
            $chartPoint->previousPeriodKey = \Scalr_Util_DateTime::yearweek($previousPeriodDt->format('Y-m-d'));

            $chartPoint->show = $chartPoint->i % 3 == 0
                ? $chartPoint->dt->format('M j')
                : ($chartPoint->isLastPoint && $chartPoint->i % 3 > 1
                    ? $ddt->format('M j')
                    : '');

            $this->c[$this->i] = $chartPoint;
        }

        return $this->c[$this->i];
    }

} 