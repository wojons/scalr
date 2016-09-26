<?php
namespace Scalr\Stats\CostAnalytics;

use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;

/**
 * ChartPointInfo
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (11.02.2014)
 */
class ChartPointInfo
{
    /**
     * The label
     *
     * @var string
     */
    public $label;

    /**
     * The data key
     *
     * @var string
     */
    public $key;

    /**
     * Previous period key
     *
     * @var string
     */
    public $previousPeriodKey;

    /**
     * Counter
     *
     * @var int
     */
    public $i;

    /**
     * The caption shown on chart
     *
     * @var string
     */
    public $show;

    /**
     * The chart mode
     *
     * @var string
     */
    public $mode;

    /**
     * The current start time within the point period
     *
     * @var \DateTime
     */
    public $dt;

    /**
     * The interval
     *
     * @var string
     */
    public $interval;

    /**
     * Start date of the whole period
     *
     * @var \DateTime
     */
    public $start;

    /**
     * End date of the whole period
     *
     * @var \DateTime
     */
    public $end;

    /**
     * Whether it is the last point on the chart
     *
     * @var bool
     */
    public $isLastPoint;

    /**
     * Constructor
     *
     * @param   ChartPeriodIterator $iterator  The iterator
     */
    public function __construct(ChartPeriodIterator $iterator)
    {
        $this->mode = $iterator->getMode();
        $this->dt = $iterator->getIterationTimestamp();
        $this->interval = $iterator->getInterval();
        $this->i = $iterator->getIterationNumber();
        $this->start = $iterator->getStart();
        $this->end = $iterator->getEnd();
        $this->isLastPoint = $iterator->isLastPoint();
    }

}