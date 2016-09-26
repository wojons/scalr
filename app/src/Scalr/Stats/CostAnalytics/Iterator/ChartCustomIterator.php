<?php

namespace Scalr\Stats\CostAnalytics\Iterator;

use DateTime, DateTimeZone;
use Scalr\Stats\CostAnalytics\ChartPointInfo;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Quarters;

/**
 * ChartCustomIterator
 *
 * This iterator is used to iterate over the date period
 * according to cost analytics data retention policy.
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.0 (03.12.2014)
 */
class ChartCustomIterator extends ChartPeriodIterator
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
        $this->mode = 'custom';
        $this->timezone = new DateTimeZone($timezone);
        $this->today = $this->getTodayDate();
        $this->start = new DateTime(($start instanceof DateTime ? $start->format('Y-m-d 00:00:00') : $start), $this->timezone);
        $this->end = (!empty($end) ? new DateTime(($end instanceof DateTime ? $end->format('Y-m-d 00:00:00') : $end), $this->timezone) : null);

        if (!($this->end instanceof DateTime) || $this->end < $this->start) {
            throw new \InvalidArgumentException(sprintf(
                "Invalid period. Start date should be either less or equal then End date."
            ));
        }

        //Difference in days between Start and End dates
        $diffdays = $this->start->diff($this->end, true)->days;

        //Difference in days between Start and Today dates
        $diffTodayDays = $this->start->diff($this->today, true)->days;

        //Previous interval is the same period in the past
        $this->prevInterval = new \DateInterval('P' . ($diffdays + 1) . 'D');

        if ($diffdays < 2 && $diffTodayDays < 14) {
            $this->interval = '1 hour';
        } else if ($diffdays < 31) {
            $this->interval = '1 day';
        } else if ($diffdays < 31 * 4) {
            $this->interval = '1 week';
        } else if ($diffdays < 366 * 3) {
            $this->interval = '1 month';
        } else {
            $this->interval = '1 year';
        }

        $this->prevStart = clone $this->start;
        $this->prevStart->sub($this->prevInterval);

        $this->wholePeriodPerviousEnd = clone $this->start;
        $this->wholePeriodPerviousEnd->modify('-1 day');

        $this->prevEnd = clone $this->prevStart;
        $this->prevEnd->add(new \DateInterval('P' . $this->start->diff(min($this->end, $this->today), true)->days . 'D'));

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

            switch ($chartPoint->interval) {
                case '1 hour':
                    $h = $chartPoint->dt->format('H');
                    $chartPoint->label = $chartPoint->dt->format('l, M j, g A');
                    $chartPoint->show = $h == 0
                        ? $chartPoint->dt->format('M j')
                        : ($h % 3 == 0 ? $chartPoint->dt->format('g a') : '');

                    $chartPoint->key = $chartPoint->dt->format("Y-m-d H:00:00");
                    $chartPoint->previousPeriodKey = $previousPeriodDt->format('Y-m-d H:00:00');
                    break;

                case '1 day':
                    $chartPoint->label = $chartPoint->dt->format('M j');

                    $chartPoint->key = $chartPoint->dt->format('Y-m-d');
                    $chartPoint->previousPeriodKey = $previousPeriodDt->format('Y-m-d');

                    $chartPoint->show = $chartPoint->i % 4 == 0 || $chartPoint->isLastPoint && $chartPoint->i % 4 > 2
                        ? $chartPoint->dt->format('M j')
                        : '';
                    break;

                case '1 week':
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
                    break;

                case '1 month':
                    $diffdays = $this->start->diff($this->end, true)->days;

                    if ($diffdays < 366) {
                        $chartPoint->show = $chartPoint->label = $chartPoint->dt->format('M');
                    } else {
                        $chartPoint->show = $chartPoint->label = $chartPoint->dt->format('M, Y');
                    }

                    $chartPoint->key = $chartPoint->dt->format('Y-m');
                    $chartPoint->previousPeriodKey = $previousPeriodDt->format('Y-m');
                    break;

                case '1 quarter':
                    //Quarter breakdown is not supported yet
                    $quarters = new Quarters(SettingEntity::getQuarters());

                    $currentPeriod = $quarters->getPeriodForDate($chartPoint->start);

                    $prevStart = $this->getPreviousStart();

                    $prevPeriod = $quarters->getPeriodForDate($prevStart);

                    $chartPoint->show = $chartPoint->label = $currentPeriod->year . ' Q' . $currentPeriod->quarter;

                    $chartPoint->key = $currentPeriod->year . '-' . $currentPeriod->quarter;

                    $chartPoint->previousPeriodKey = $prevPeriod->year . '-' . $prevPeriod->quarter;
                    break;

                case '1 year':
                    $chartPoint->show = $chartPoint->label = $chartPoint->dt->format('Y');

                    $chartPoint->key = $chartPoint->label;
                    $chartPoint->previousPeriodKey = $previousPeriodDt->format('Y');
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf('Unsupported interval for custom mode %s.', $chartPoint->interval));
                    break;
            }

            $this->c[$this->i] = $chartPoint;
        }

        return $this->c[$this->i];
    }

} 