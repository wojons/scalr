<?php
namespace Scalr\Stats\CostAnalytics\Iterator;

use Iterator, DateTime, DateTimeZone;
use Scalr\Stats\CostAnalytics\ChartPointInfo;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Quarters;
use Scalr\Stats\CostAnalytics\QuarterPeriod;

/**
 * ChartPeriod Iterator
 *
 * This iterator is used to iterete over the date period
 * according to cost analysics data retention policy.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (11.02.2014)
 */
class ChartPeriodIterator implements Iterator
{

    /**
     * The mode
     *
     * @var string
     */
    private $mode;

    /**
     * Timezone
     *
     * @var \DateTimeZone
     */
    private $timezone;

    /**
     * Today date
     *
     * @var \DateTime
     */
    public $today;

    /**
     * Start date
     *
     * @var \DateTime
     */
    private $start;

    /**
     * Previous preiod start date
     *
     * @var \DateTime
     */
    private $prevStart;

    /**
     * Previous period end date based on today date
     *
     * @var \DateTime
     */
    private $prevEnd;

    /**
     * Whole previous period end data
     *
     * @var \DateTime
     */
    private $wholePeriodPerviousEnd;

    /**
     * End date
     *
     * @var \DateTime
     */
    private $end;

    /**
     * Previous period interval
     *
     * @var \DateInterval
     */
    private $prevInterval;

    /**
     * The interval between each data point
     *
     * @var string
     */
    private $interval;

    /**
     * Interval between an each data point
     *
     * @var \DateInterval
     */
    private $di;

    /**
     * Date Time used to iterate over
     *
     * @var \DateTime
     */
    private $dt;

    /**
     * Counter
     *
     * @var int
     */
    private $i;

    /**
     * Cache
     *
     * @var array
     */
    private $c;

    /**
     * Gets the end date
     *
     * @return \DateTime Returns end date
     */
    public function getEnd()
    {
        return clone $this->end;
    }

    /**
     * Gets the start date
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return clone $this->start;
    }

    /**
     * Gets a previous period Start date
     *
     * @return \DateTime
     */
    public function getPreviousStart()
    {
        return clone $this->prevStart;
    }

    /**
     * Gets a previous period End date
     *
     * @return \DateTime
     */
    public function getPreviousEnd()
    {
        return clone $this->prevEnd;
    }

    /**
     * Gets interval used to form each point on chart withing period
     *
     * @return   string Returns calculated interval according
     *                  to better view and data retention policy
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * Gets interval used to form each point on chart within period
     *
     * @return  \DateInterval Returns interval used to form each point on chart within period
     */
    public function getIterationInterval()
    {
        return $this->di;
    }

    /**
     * Gets chart mode
     *
     * @return  string  Returns chart mode
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Gets the iteration timestamp
     *
     * @return \DateTime Gets iteration timestamp
     */
    public function getIterationTimestamp()
    {
        return clone $this->dt;
    }

    /**
     * Gets the iteration number
     *
     * @return number Returns iteration number
     */
    public function getIterationNumber()
    {
        return $this->i;
    }

    /**
     * Gets previous period interval
     *
     * @return \DateInterval Returns previous period interval
     */
    public function getPreviousPeriodInterval()
    {
        return $this->prevInterval;
    }

    /**
     * Gets whole previous period end date
     *
     * @return   \DateTime
     */
    public function getWholePreviousPeriodEnd()
    {
        return clone $this->wholePeriodPerviousEnd;
    }

    /**
     * Gets today date
     *
     * @return \DateTime
     */
    public function getTodayDate()
    {
        return new DateTime('now', $this->timezone);
    }

    /**
     * Gets QuarterPeriod object which has the most days of this interval.
     *
     * @return  QuarterPeriod
     */
    public function getQuarterPeriod()
    {
        $quarters = new Quarters(SettingEntity::getQuarters());

        if ($this->mode == 'year') {
            //Takes the first day of the period interval to obtain yearly period object
            $p =  $quarters->getPeriodForDate($this->getStart());
            $ret = $quarters->getPeriodForYear($p->year);
            return $ret;
        }

        $stat = [];
        $periods = [];

        $start = $this->getStart();
        $end = $this->getEnd();

        $fnstat = function($p) use (&$stat, &$periods) {
            $key = $p->quarter . ',' . $p->year;
            if (!isset($stat[$key])) {
                $stat[$key] = 1;
                $periods[$key] = $p;
            } else {
                $stat[$key]++;
            }
        };

        $fnstat($quarters->getPeriodForDate($start));
        $fnstat($quarters->getPeriodForDate($end));

        $days = $start->diff($end, true)->days;

        //Divides inteval into four parts and examine the most relevant period for this interval.
        $interval = max(floor($days / 4), 1);

        while ($start <= $end) {
            $fnstat($quarters->getPeriodForDate($start));
            $start->modify('+1 day');
        }

        arsort($stat);

        return $periods[key($stat)];
    }

    /**
     * Constructor
     *
     * @param   string       $mode      The mode
     * @param   string       $start     The start date of the period 'YYYY-mm-dd'
     * @param   string       $end       optional End date
     * @param   string       $timezone  optional Timezone
     * @throws  \InvalidArgumentException
     */
    public function __construct($mode, $start, $end = null, $timezone = 'UTC')
    {
        $this->mode = $mode;
        $this->timezone = new DateTimeZone($timezone);
        $this->today = $this->getTodayDate();
        $this->start = new DateTime(($start instanceof DateTime ? $start->format('Y-m-d 00:00:00') : $start), $this->timezone);
        $this->end = (!empty($end) ? new DateTime(($end instanceof DateTime ? $end->format('Y-m-d 00:00:00') : $end), $this->timezone) : null);

        switch ($mode) {
            case 'week':
                //Week should start from sunday
                if ($this->start->format('w') != 0) {
                    $this->start->modify('last sunday');
                }

                $this->prevInterval = new \DateInterval('P7D');

                //Each point
                $this->interval = '1 day';

                $this->end = clone $this->start;
                $this->end->add(new \DateInterval('P6D'));

                $this->prevStart = clone $this->start;
                $this->prevStart->sub($this->prevInterval);

                $this->wholePeriodPerviousEnd = clone $this->start;
                $this->wholePeriodPerviousEnd->modify('-1 day');

                $this->prevEnd = clone $this->prevStart;
                $this->prevEnd->add(new \DateInterval('P' . $this->start->diff(min($this->end, $this->today), true)->days . 'D'));

                //Week length is always the same so we don't need to use determinePrevEnd() method
                break;

            case 'month':
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
                break;

            case 'quarter':
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
                break;

            case 'year':
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
                break;

            case 'custom':
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
                break;

            default:
                throw new \UnexpectedValueException(sprintf("Unexpected chart mode %s", strip_tags($mode)));
                break;
        }

        $endoftheday = new \DateInterval('PT23H59M59S');

        $this->end->add($endoftheday);
        $this->prevEnd->add($endoftheday);
        $this->wholePeriodPerviousEnd->add($endoftheday);

        if (!$this->di)
            $this->di = \DateInterval::createFromDateString($this->interval);

        $this->dt = clone $this->start;
    }

    /**
     * Sets prevEnd property based on period.
     * It's only for internal usage.
     */
    private function determinePrevEnd()
    {
        if ($this->end < $this->today) {
            //For any historical period, has already happened in full, growth
            //should be compared to the entire previous period
            $this->prevEnd = clone $this->wholePeriodPerviousEnd;
        } else {
            $this->prevEnd = clone $this->prevStart;

            $days = $this->start->diff(min($this->end, $this->today), true)->days;

            if ($days > 0) {
                $this->prevEnd->modify(sprintf('+%d days', $days));
            }

            //Previous period should not overlap current
            if ($this->wholePeriodPerviousEnd < $this->prevEnd) {
                $this->prevEnd = clone $this->wholePeriodPerviousEnd;
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see Iterator::current()
     * @return ChartPointInfo
     */
    public function current()
    {
        if (!isset($this->c[$this->i])) {
            $this->c[$this->i] = new ChartPointInfo($this);
        }
        return $this->c[$this->i];
    }

    /**
     * Gets previous point
     *
     * @return ChartPointInfo|null Returns chart point info or null
     */
    public function previous()
    {
        if ($this->i < 1) return null;

        return isset($this->c[$this->i - 1]) ? $this->c[$this->i - 1] : null;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->current()->key;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::next()
     */
    public function next()
    {
        if ($this->interval == '1 week') {
            $this->dt->modify('next sunday');
        } elseif ($this->interval == '1 quarter') {
            //First day of next quarter
            $quarters = new Quarters(SettingEntity::getQuarters());
            $period = $quarters->getPeriodForDate($this->dt);
            $this->dt = $period->end;
            $this->dt->modify('+1 day');
        } else {
            $this->dt->add($this->di);
        }
        $this->i++;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->dt = clone $this->start;
        $this->i = 0;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::valid()
     */
    public function valid()
    {
        return $this->dt <= $this->end;
    }

    /**
     * Checks whether it is the last point on the chart
     *
     * @return boolean Returns true if it is the last point on the chart
     */
    public function isLastPoint()
    {
        $lp = clone $this->dt;
        $lp->add($this->di);

        return $lp > $this->end;
    }

    /**
     * Checks whether current point depicts a future date
     *
     * @return boolean Returns true if it is a future date
     */
    public function isFuture()
    {
        return $this->dt > $this->today;
    }
}