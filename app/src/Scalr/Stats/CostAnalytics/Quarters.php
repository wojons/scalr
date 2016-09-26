<?php
namespace Scalr\Stats\CostAnalytics;

use Iterator, DateTimeZone, DateTime, DateInterval, DatePeriod,
    OutOfBoundsException;

/**
 * Quarters
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (30.04.2014)
 */
class Quarters implements Iterator
{
    /**
     * Quarter days
     *
     * @var array
     */
    private $days;

    /**
     * @var array
     */
    private $ny;

    /**
     * The sum of the ny
     *
     * @var int
     */
    private $sum;

    /**
     * Timezone
     *
     * @var DateTimeZone
     */
    private $tz;

    /**
     * Internal position of the iterator
     *
     * @var int
     */
    private $pos;

    /**
     * Today date
     *
     * @var DateTime
     */
    private $today;

    /**
     * Constructor
     */
    public function __construct($days)
    {
        $this->days = $days;

        $ny = 0;

        for ($i = 0; $i < 4; ++$i) {
            $this->ny[$i] = $ny;

            if ($this->days[$i] > $this->days[($i + 1) % 4]) {
                $ny = 1;
            }
        }

        $this->tz = new DateTimeZone('UTC');

        $this->today = new DateTime('now', $this->tz);

        $this->pos = 0;

        $this->sum = array_sum($this->ny);
    }

    /**
     * Get quarter start days array
     *
     * @return  array
     */
    public function getDays()
    {
        return $this->days;
    }

    /**
     * Returns quarter number for the specified date
     *
     * @param   DateTime|string   $date   optional Date provided with format YYYY-mm-dd
     * @return  int|null   Returns number of the quarter [1-4] on success or null on error
     */
    public function getQuarterForDate($date = null)
    {
        $result = null;

        if ($date === null) {
            $date = new DateTime('now', $this->tz);
        } else {
            $date = $date instanceof DateTime ? $date : new DateTime($date, $this->tz);
        }

        $p = $date->format('m-d');

        for ($i = 0; $i < 4; ++$i) {
            $next = ($i + 1) % 4;

            //through the new year
            $y = $this->days[$i] <= $p && $p <= '12-31' ? '0' : '1';

            if ($this->days[$i] < $this->days[$next]) {
                if ($this->days[$i] <= $p && $p < $this->days[$next]) {
                    $result = $i + 1;
                    break;
                }
            } else {
                if (('0' . $this->days[$i]) <= ($y . $p) && ($y . $p) < ('1' . $this->days[$next])) {
                    $result = $i + 1;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Gets start and end dates for the quarter
     *
     * It expects the number of the year which encloses the most quaters
     *
     * @param   int    $quarter  The number of the quarter [1-4]
     * @param   int    $year     The year
     * @return  QuarterPeriod    Returns the start and end dates for the quarter
     * @throws  OutOfBoundsException
     */
    public function getPeriodForQuarter($quarter, $year)
    {
        //The most of the quarters is in the specified year
        if ($this->sum >= 2)
            $add = -1;
        else
            $add = 0;

        if ($quarter < 1 || $quarter > 4) {
            throw new OutOfBoundsException(sprintf("Number of the quarter should be from 1 to 4."));
        }

        $std = new QuarterPeriod();
        $std->start = new DateTime(sprintf("%04d-%s", $year + $add + $this->ny[$quarter - 1], $this->days[$quarter - 1]), $this->tz);
        $std->end = new DateTime(sprintf("%04d-%s", $year + $add + max($this->ny[$quarter - 1], $this->ny[$quarter % 4], ($this->days[$quarter - 1] > $this->days[$quarter % 4] ? $add + 1 : $add)), $this->days[$quarter % 4]), $this->tz);
        $std->end->modify('-1 day');
        $std->year = $year;
        $std->quarter = $quarter;

        return $std;
    }

    /**
     * Gets start and end dates for the fiscal year
     *
     * It expects the number of the year which encloses the most quaters
     *
     * @param   int    $year     The year
     * @return  QuarterPeriod    Returns the start and end dates for the quarter
     */
    public function getPeriodForYear($year)
    {
        //The most of the quarters is in the specified year
        if ($this->sum >= 2)
            $add = -1;
        else
            $add = 0;

        $std = new QuarterPeriod();
        $std->start = new DateTime(sprintf("%04d-%s", $year + $add + $this->ny[0], $this->days[0]), $this->tz);
        $std->end = new DateTime(sprintf("%04d-%s", $year + $add + max($this->ny[3], $this->ny[0], ($this->days[3] > $this->days[0] ? $add + 1 : $add)), $this->days[0]), $this->tz);
        $std->end->modify('-1 day');
        $std->quarter = 'year';
        $std->year = $year;

        return $std;
    }

    /**
     * Gets start and end date of quarter for the specified date of the quarter
     *
     * @param    string|DateTime    optional $date
     * @return   QuarterPeriod      Returns the start and end dates for the quarter
     */
    public function getPeriodForDate($date = null)
    {
        if ($date === null) {
            $date = new DateTime('now', $this->tz);
        } else {
            $date = $date instanceof DateTime ? $date : new DateTime($date, $this->tz);
        }

        $quarter = $this->getQuarterForDate($date);

        $period = $this->getPeriodForQuarter($quarter, $date->format('Y'));

        if ($period->end->format('Y-m-d') < $date->format('Y-m-d')) {
            $period = $this->getPeriodForQuarter($quarter, sprintf("%04d", $date->format('Y') + 1));
        } else if ($period->start->format('Y-m-d') > $date->format('Y-m-d')) {
            $period = $this->getPeriodForQuarter($quarter, sprintf("%04d", $date->format('Y') - 1));
        }

        return $period;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::current()
     */
    public function current()
    {
        return $this->days[$this->pos];
    }

    /**
     * {@inheritdoc}
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->pos + 1;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::next()
     */
    public function next()
    {
        $this->pos++;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->pos = 0;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::valid()
     */
    public function valid()
    {
        return isset($this->days[$this->pos]);
    }
}