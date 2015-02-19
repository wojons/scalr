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
 * This iterator is used to iterate over the date period
 * according to cost analytics data retention policy.
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
    protected $mode;

    /**
     * Timezone
     *
     * @var \DateTimeZone
     */
    protected $timezone;

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
    protected $start;

    /**
     * Previous preiod start date
     *
     * @var \DateTime
     */
    protected $prevStart;

    /**
     * Previous period end date based on today date
     *
     * @var \DateTime
     */
    protected $prevEnd;

    /**
     * Whole previous period end data
     *
     * @var \DateTime
     */
    protected $wholePeriodPerviousEnd;

    /**
     * End date
     *
     * @var \DateTime
     */
    protected $end;

    /**
     * Previous period interval
     *
     * @var \DateInterval
     */
    protected $prevInterval;

    /**
     * The interval between each data point
     *
     * @var string
     */
    protected $interval;

    /**
     * Interval between an each data point
     *
     * @var \DateInterval
     */
    protected $di;

    /**
     * Date Time used to iterate over
     *
     * @var \DateTime
     */
    protected $dt;

    /**
     * Counter
     *
     * @var int
     */
    protected $i;

    /**
     * Cache
     *
     * @var array
     */
    protected $c;

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
     * Sets prevEnd property based on period.
     * It's only for internal usage.
     */
    protected  function determinePrevEnd()
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

    /**
     * Creates iterator object according to mode value
     *
     * @param   string       $mode      The mode
     * @param   string       $start     The start date of the period 'YYYY-mm-dd'
     * @param   string       $end       optional End date
     * @param   string       $timezone  optional Timezone
     * @throws  \InvalidArgumentException
     * @return  ChartDailyIterator|ChartWeeklyIterator|ChartMonthlyIterator|ChartQuarterlyIterator|ChartYearlyIterator|ChartCustomIterator
     */
    public static function create($mode, $start, $end = null, $timezone = 'UTC')
    {
        if (substr($mode, -1) == 'y') {
            $modeName = substr_replace($mode, 'i', -1);
        } else {
            $modeName = $mode;
        }

        $modeName = ucfirst($modeName);

        if ($mode != 'custom') {
            $modeName .= 'ly';
        }

        $chartClass = 'Scalr\\Stats\\CostAnalytics\\Iterator\\Chart' . $modeName . 'Iterator';
        $iterator = new $chartClass($start, $end, $timezone);

        return $iterator;
    }

    /**
     * Search chart point by date
     *
     * @param string $date         Date time
     * @return null|int Returns chart point position if found. False otherwise
     */
    public function searchPoint($date)
    {
        foreach ($this as $chartPoint) {
            if ($chartPoint->dt->format('Y-m-d H:00') === $date) {
                return $chartPoint->i;
            }
        }

        return false;
    }

}