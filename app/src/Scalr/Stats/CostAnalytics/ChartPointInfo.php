<?php
namespace Scalr\Stats\CostAnalytics;

use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;

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
     * @throws  \InvalidArgumentException
     */
    public function __construct(ChartPeriodIterator $iterator)
    {
        $this->mode = $iterator->getMode();
        $this->dt = $iterator->getIterationTimestamp();
        $this->interval = $iterator->getInterval();
        $this->i = $iterator->getIterationNumber();

        $prevStart = $iterator->getPreviousStart();

        $previousPeriodDt = clone $this->dt;
        $previousPeriodDt->sub($iterator->getPreviousPeriodInterval());

        $this->start = $iterator->getStart();
        $this->end = $iterator->getEnd();

        $this->isLastPoint = $iterator->isLastPoint();

        if ($this->mode == 'year' || $this->mode == 'custom' && $this->interval == '1 month') {
            $this->show = $this->label = $this->dt->format('M');

            $this->key = $this->dt->format('Y-m');
            $this->previousPeriodKey = $previousPeriodDt->format('Y-m');

        } elseif ($this->mode == 'quarter' || $this->mode == 'custom' && $this->interval == '1 week') {
            $ddt = clone $this->dt;
            $ddt->modify('next saturday');

            if ($ddt > $this->end) {
                $ddt = $this->end;
            }

            $this->label = $this->dt->format('M j') . ' - ' . $ddt->format('M j');

            $this->key = \Scalr_Util_DateTime::yearweek($this->dt->format('Y-m-d'));
            $this->previousPeriodKey = \Scalr_Util_DateTime::yearweek($previousPeriodDt->format('Y-m-d'));

            $this->show = $this->i % 3 == 0 ? $this->dt->format('M j') : ($this->isLastPoint && $this->i % 3 > 1 ? $ddt->format('M j') : '');

        } elseif ($this->mode == 'week') {
            $this->label = $this->dt->format('l, M j');
            $this->show = $this->dt->format('M j');

            $this->key = $this->dt->format('Y-m-d');
            $this->previousPeriodKey = $previousPeriodDt->format('Y-m-d');

        } elseif ($this->mode == 'month' || $this->mode == 'custom' && $this->interval == '1 day') {
            $this->label = $this->dt->format('M j');

            $this->key = $this->dt->format('Y-m-d');
            $this->previousPeriodKey = $previousPeriodDt->format('Y-m-d');

            $this->show = $this->i % 4 == 0 || $this->isLastPoint && $this->i % 4 > 2 ? $this->dt->format('M j') : '';

        } elseif ($this->mode == 'custom') {
            switch ($this->interval) {
                case '1 hour':
                    $h = $this->dt->format('H');
                    $this->label = $this->dt->format('l, M j, g A');
                    $this->show = $h == 0 ? $this->dt->format('M j') : ($h % 3 == 0 ? $this->dt->format('g a') : '');

                    $this->key = $this->dt->format("Y-m-d H:00:00");
                    $this->previousPeriodKey = $previousPeriodDt->format('Y-m-d H:00:00');
                    break;

                case '1 quarter':
                    //Quarter breakdown is not supported yet
                    $quarters = new Quarters(SettingEntity::getQuarters());

                    $currentPeriod = $quarters->getPeriodForDate($this->start);

                    $prevPeriod = $quarters->getPeriodForDate($prevStart);

                    $this->show = $this->label = $currentPeriod->year . ' Q' . $currentPeriod->quarter;

                    $this->key = $currentPeriod->year . '-' . $currentPeriod->quarter;

                    $this->previousPeriodKey = $prevPeriod->year . '-' . $prevPeriod->quarter;
                    break;

                case '1 year':
                    $this->show = $this->label = $this->dt->format('Y');

                    $this->key = $this->label;
                    $this->previousPeriodKey = $previousPeriodDt->format('Y');
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf('Unsupported interval for custom mode %s.', $this->interval));
                    break;
            }
        } else {
            throw new \InvalidArgumentException(sprintf('Invalid mode %s', strip_tags($this->mode)));
        }
    }
}