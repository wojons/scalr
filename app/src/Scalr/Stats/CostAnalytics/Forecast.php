<?php
namespace Scalr\Stats\CostAnalytics;

use Scalr\DataType\AggregationCollection;
use Scalr_Util_Arrays,
    DateTime,
    DateTimeZone,
    DomainException,
    InvalidArgumentException;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\QuarterlyBudgetEntity;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;

/**
 * Forecast trait
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (04.04.2014)
 */
trait Forecast
{

    /**
     * Calculates forecast usage according to current spend over the period
     *
     * @param   float          $currentUsage    Current usage (month to day)
     * @param   DateTime       $start           Start date of the period
     * @param   DateTime       $end             End date of the period
     * @param   float          $previousUsage   optional The previous whole period usage
     * @param   float          $growthPct       optional Growth percentage over the same days of previous period.
     *                                          Negative value of the percent means reduction.
     * @param   float          $dailyRollingAvg optional Daily rolling average for the better forecast
     * @return  float          Returns forecast usage
     * @throws  \DomainException
     */
    public static function calculateForecast($currentUsage, DateTime $start, DateTime $end,
                                             $previousUsage = null, $growthPct = null, $dailyRollingAvg = null)
    {
        if ($end < $start) {
            throw new DomainException(sprintf("Invalid period. Start date %s should be less then End date %s.",
                $start->format('Y-m-d'), $end->format('Y-m-d')));
        }

        $today = new DateTime('now', new DateTimeZone('UTC'));

        $daysOfPeriod = $start->diff($end, true)->days + 1;

        $daysUpToday = $start->diff($today, true)->days + 1;

        $remainsDays = $daysOfPeriod - $daysUpToday;

        if ($dailyRollingAvg > 0) {
            //Forecasting based on spending trends (daily rolling average)
            $forecast = $currentUsage + $remainsDays * $dailyRollingAvg;
        } else if (isset($previousUsage) && $previousUsage > 0.01 && ($growthPct !== null && abs($growthPct) > 0.01)) {
            //Forecasting based on previous period growth
            $forecast = $previousUsage + $previousUsage * $growthPct / 100;
        } else {
            //Forecasting based on this period daily average
            $forecast = $currentUsage + $remainsDays * ($currentUsage / $daysUpToday);
        }

        return round($forecast, 2);
    }

    /**
     * Wraps data into array
     *
     * @param   array $data
     */
    public function getWrappedUsageData($data)
    {
        $ret = [];

        $usage = $data['usage'];
        $prevusage = $data['prevusage'];

        $growth = $usage - $prevusage;
        $growthPct = $prevusage == 0 ? null : abs($growth / $prevusage * 100);

        $ret['periodTotal'] = $usage;

        $ret['growth'] = $growth;

        $ret['growthPct'] = $growthPct === null ? null : round($growthPct, 0);

        if (isset($data['ccId']) || isset($data['projectId'])) {
            //Gets budget for projects and ccs
            $ret = $this->getBudgetUsedPercentage([
                'ccId'      => isset($data['ccId']) ? $data['ccId'] : null,
                'projectId' => isset($data['projectId']) ? $data['projectId'] : null,
            ]) + $ret;
        }

        return $ret;
    }

    /**
     * Gets budget used percentage
     *
     * @param   array    $request  Request array should look like
     *                             ['projectId' => id, 'ccId' => id, 'period' => period, 'getRelationDependentBudget' => true]
     * @return  array
     * @throws  \InvalidArgumentException
     */
    public function getBudgetUsedPercentage($request)
    {
        $ret = [];

        if (!empty($request['projectId'])) {
            $subjectType = QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT;
            $subjectId = $request['projectId'];
        } elseif (!empty($request['ccId'])) {
            $subjectType = QuarterlyBudgetEntity::SUBJECT_TYPE_CC;
            $subjectId = $request['ccId'];
        }

        $ret['budget'] = 0;
        $ret['budgetSpentPct'] = null;
        $ret['budgetSpent'] = 0;
        $ret['budgetRemain'] = null;
        $ret['budgetRemainPct'] = null;
        $ret['budgetOverspend'] = 0;
        $ret['budgetOverspendPct'] = 0;
        $ret['budgetSpentOnDate'] = null;
        $ret['budgetSpentThisPeriodPct'] = 0;
        $ret['quarter'] = null;
        $ret['year'] = null;
        $ret['quarterStartDate'] = null;
        $ret['quarterEndDate'] = null;
        //This is a rudiment as we use cumulativespend column everywhere
        $ret['budgetFinalSpent'] = 0;

        if (isset($subjectId)) {
            $period = isset($request['period']) ? $request['period'] : $this->_getCurrentPeriod();

            if (!($period instanceof QuarterPeriod)) {
                throw new InvalidArgumentException(sprintf(
                    "Period must be instance of the Scalr\\Stats\\CostAnalytics\\QuarterPeriod class. %s given",
                    gettype($period)
                ));
            }

            $quarter = $period->quarter;
            $year = $period->year;

            $ret['quarter'] = $quarter;
            $ret['year'] = $year;
            $ret['quarterStartDate'] = $period->start->format('Y-m-d');
            $ret['quarterEndDate'] = $period->end->format('Y-m-d');
            $ret['closed'] = $ret['quarterEndDate'] < gmdate('Y-m-d');

            //Retrieves budget from database
            if ($quarter == 'year') {
                $quarters = new Quarters(SettingEntity::getQuarters());

                //Calculates total budgeted cost for the specified year
                $quarterlyBudget = new QuarterlyBudgetEntity();
                $quarterlyBudget->year = $year;
                $quarterlyBudget->subjectId = $subjectId;
                $quarterlyBudget->subjectType = $subjectType;

                $collection = QuarterlyBudgetEntity::find([
                    ['year'        => $year],
                    ['subjectType' => $subjectType],
                    ['subjectId'   => $subjectId],
                ]);

                //List of the quarters which budget has not been set for
                $arrNotSet = [1,2,3,4];

                foreach ($collection as $entity) {
                    $period = $quarters->getPeriodForQuarter($entity->quarter, $year);

                    //It shoud take into account only ongoing or future quarters without budget set
                    if ($entity->budget > 0 || $period->end->format('Y-m-d') < gmdate('Y-m-d')) {
                        if (isset($arrNotSet[$entity->quarter - 1])) {
                            unset($arrNotSet[$entity->quarter - 1]);
                        }
                    }

                    $quarterlyBudget->budget += $entity->budget;
                    $quarterlyBudget->cumulativespend += $entity->cumulativespend;
                }

                if (!empty($arrNotSet)) {
                    $ret['budgetAlert'] = "Budget has not been allocated for Q" . join(", Q", $arrNotSet);
                }
            } else {
                $quarterlyBudget = QuarterlyBudgetEntity::findPk($year, $subjectType, $subjectId, $quarter);
            }

            if ($quarterlyBudget instanceof QuarterlyBudgetEntity) {
                $ret['budget'] = round($quarterlyBudget->budget);

                $ret['budgetSpent'] = round($quarterlyBudget->cumulativespend);

                if ($ret['budget']) {
                    $ret['budgetOverspend'] = max(round($quarterlyBudget->cumulativespend - $quarterlyBudget->budget), 0);
                    $ret['budgetOverspendPct'] = round($ret['budgetOverspend'] / $ret['budget'] * 100);
                }

                $ret['budgetSpentPct'] = $ret['budget'] == 0 ? null : min(100, round($ret['budgetSpent'] / $ret['budget'] * 100));

                if (isset($request['usage'])) {
                    $ret['budgetSpentThisPeriodPct'] = $ret['budget'] == 0 ? null : min(100, round($request['usage'] / $ret['budget'] * 100));
                }

                $ret['budgetRemain'] = max(0, round($ret['budget'] - $ret['budgetSpent']));

                $ret['budgetRemainPct'] = $ret['budgetSpentPct'] !== null ? 100 - $ret['budgetSpentPct'] : null;

                if ($ret['closed']) {
                    $ret['costVariance'] = $ret['budgetSpent'] - $ret['budget'];
                    $ret['costVariancePct'] = $ret['budget'] == 0 ? null : round(abs($ret['costVariance']) / $ret['budget'] * 100);
                }

                $ret['budgetFinalSpent'] = round($quarterlyBudget->final);

                $ret['budgetSpentOnDate'] = $quarterlyBudget->spentondate instanceof DateTime ?
                    $quarterlyBudget->spentondate->format('Y-m-d') : null;

                if (!empty($request['getRelationDependentBudget'])) {
                    if ($quarterlyBudget->subjectType != QuarterlyBudgetEntity::SUBJECT_TYPE_CC && isset($request['ccId'])) {
                        $ccQuarterlyBudget = clone $quarterlyBudget;
                        $ccQuarterlyBudget->subjectType = QuarterlyBudgetEntity::SUBJECT_TYPE_CC;
                        $ccQuarterlyBudget->subjectId = $request['ccId'];

                        $ret['relationDependentBudget'] = round($ccQuarterlyBudget->getRelationDependentBudget());
                        unset($ccQuarterlyBudget);
                    } else {
                        $ret['relationDependentBudget'] = round($quarterlyBudget->getRelationDependentBudget());
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * Calculates estimates for budget data
     *
     * @param   array    $budget       The budget that is calculated by getBudgetUsedPercentage method
     * @param   float    $forecastCost optional Forecast usage for this period calculated externally
     * @throws  \DomainException
     */
    public function calculateBudgetEstimateOverspend(&$budget, $forecastCost = null)
    {
        $budget['estimateOverspend'] = null;
        $budget['estimateOverspendPct'] = null;

        if (isset($forecastCost)) {
            $budget['estimateOverspend'] = $forecastCost - $budget['budget'];
        }

        $budget['estimateDate'] = null;

        if ($budget['budgetSpentOnDate']) {
            //It's the fact that Budget have already exceeded
            $budget['estimateDate'] = $budget['budgetSpentOnDate'];
        }

        if ($budget['budget'] > 0 && $budget['budgetSpent'] > 0 && $budget['quarterEndDate'] && $budget['quarterStartDate']) {
            //Calculates the date on which we will have budget exceeded.
            $today = new DateTime('now', new DateTimeZone('UTC'));

            $quarterStartDate = new DateTime($budget['quarterStartDate'], new DateTimeZone('UTC'));
            $quarterEndDate = new DateTime($budget['quarterEndDate'], new DateTimeZone('UTC'));

            if ($quarterStartDate > $quarterEndDate) {
                throw new DomainException(sprintf(
                    'Quarter Start date (%s) can not be greater than End date (%s).',
                    $quarterStartDate->format('Y-m-d'),
                    $quarterEndDate->format('Y-m-d')
                ));
            }

            //It is ongoing quarter
            if ($quarterStartDate <= $today && $today <= $quarterEndDate) {
                //Days remain to the end of the quarter
                $daysToEnd = $today->diff($quarterEndDate)->days + 1;

                //Days past from the start of the quarter
                $daysFromStart = $today->diff($quarterStartDate, true)->days + 1;

                //Spend by day
                $spendByDay = $daysFromStart == 0 ? 0 : $budget['budgetSpent'] / $daysFromStart;

                //Days need to exceed budget
                $needDays = $spendByDay == 0 ? 0 : ceil($budget['budget'] / $spendByDay);

                $budgetRunOut = clone $quarterStartDate;

                if ($needDays > 0) {
                    $budgetRunOut->modify(sprintf("+%d day", $needDays));
                }

                if (!isset($budget['estimateDate']) && $budgetRunOut <= $quarterEndDate) {
                    //Sets only if estimateDate less or equal than End day of the quarter
                    $budget['estimateDate'] = $budgetRunOut->format('Y-m-d');
                }

                if (!isset($budget['estimateOverspend']) && !empty($budget['estimateDate'])) {
                    $forecastCost = round($spendByDay * ($quarterStartDate->diff($quarterEndDate, true)->days + 1));
                    $budget['estimateOverspend'] = $forecastCost - $budget['budget'];
                }

                //Estimate cost variance
                if (isset($forecastCost)) {
                    $budget['costVariance'] = $forecastCost - $budget['budget'];
                    $budget['costVariancePct'] = $budget['budget'] == 0 ? null : round(abs($budget['costVariance']) / $budget['budget'] * 100, 2);
                }
            } else if ($quarterEndDate < $today) {
                //Past quarter
                $budget['budgetFinalSpent'] = $budget['budgetSpent'];

                //This is real overspend for the past quarter
                $budget['estimateOverspend'] = round($budget['budget'] - $budget['budgetSpent']);
            }
        } else if ($budget['budget'] > 0 && $budget['budgetSpent'] < 0.00001) {
            $budget['estimateOverspend'] = -$budget['budget'];
        }

        if (!empty($budget['estimateOverspend']) && $budget['budget'] > 0) {
            $budget['estimateOverspendPct'] = round($budget['estimateOverspend'] / $budget['budget'] * 100, 2);
        }
    }

    /**
     * Gets quarter period object for the current date
     *
     * @return QuarterPeriod Returns quarter period object for the current date
     */
    private static function _getCurrentPeriod()
    {
        static $period = null;

        if ($period === null) {
            $quarters = new Quarters(SettingEntity::getQuarters());
            $period = $quarters->getPeriodForDate();
        }

        return $period;
    }

    /**
     * Gets point data array
     *
     * @param   array    $currentPeriod     Current period data set
     * @param   array    $previousPeriod    Previous period data set
     * @param   array    $previousPoint     Previous point data set
     * @return  array    Returns current point data array
     */
    public function getPointDataArray($currentPeriod, $previousPeriod, $previousPoint)
    {
        $r = [
           'cost'        => isset($currentPeriod['cost']) ? round($currentPeriod['cost'], 2) : 0, // usage cost
           'costPct'     => isset($currentPeriod['cost_percentage']) ? $currentPeriod['cost_percentage'] : 0, // usage percentage
           'prevCost'    => (isset($previousPeriod) ? round($previousPeriod['cost'], 2) : 0), // previous period amount
           'prevCostPct' => (isset($previousPeriod) ? $previousPeriod['cost_percentage'] : 0), // previous period percentage
        ];

        //prev point usage
        $prevPointUsage = !empty($previousPoint['cost']) ? round($previousPoint['cost'], 2) : 0;

        // growth from previous period cost
        $r['growth'] = $r['cost'] - $r['prevCost'];

        // growth percentage from previous period
        $r['growthPct'] = $r['prevCost'] == 0 ? null : round(abs($r['growth'] / $r['prevCost'] * 100), 0);

        // growth from previous point amount
        $r['growthPrevPoint'] = $prevPointUsage == 0 ? $r['cost'] : $r['cost'] - $prevPointUsage;

        // percentage from previous point
        $r['growthPrevPointPct'] = $prevPointUsage == 0 ? null : round(abs(($r['growthPrevPoint'] / $prevPointUsage) * 100), 0);

        return $r;
    }

    /**
     * Gets detailed point data array
     *
     * @param   string    $id                The identifier
     * @param   string    $name              The name of the subject
     * @param   array     $currentPeriod     Current period cost
     * @param   array     $prevPeriod        Previous period cost
     * @param   array     $prevPointPeriod   Previous point cost
     * @return  array     Returns detailed data array
     */
    public function getDetailedPointDataArray($id, $name, $currentPeriod, $prevPeriod, $prevPointPeriod)
    {
        $pr = [
            'id'      => $id,
            'name'    => $name,
            'cost'    => isset($currentPeriod['cost']) ? round($currentPeriod['cost'], 2) : 0, // current period cost usage
            'costPct' => isset($currentPeriod['cost_percentage']) ? $currentPeriod['cost_percentage'] : 0, // current period percentage
        ];

        //previous period amount
        $prevUsage = !empty($prevPeriod['cost']) ? round($prevPeriod['cost'], 2) : 0;

        //previous point usage
        $prevPointUsage = !empty($prevPointPeriod['cost']) ? round($prevPointPeriod['cost'], 2) : 0;

        //growth from previous period
        $pr['growth'] = $pr['cost'] - $prevUsage;

        //percentage of growth from previous period
        $pr['growthPct'] = $prevUsage == 0 ? null : round(abs($pr['growth'] / $prevUsage * 100), 0);

        //growth from previous point amount
        $pr['growthPrevPoint'] = $prevPointUsage == 0 ? $pr['cost'] : $pr['cost'] - $prevPointUsage;

        //growth percentage from previous point
        $pr['growthPrevPointPct'] = $prevPointUsage == 0 ? null : round(abs(($pr['growthPrevPoint'] / $prevPointUsage) * 100), 0);

        return $pr;
    }

    /**
     * Gets total data array
     *
     * @param   string              $id                  The identifier of the subject
     * @param   string              $name                The name of the subject
     * @param   array               $currentPeriod
     * @param   array               $previousPeriod
     * @param   array               $previousWholePeriod
     * @param   array               $detailed            Details by each point on chart
     * @param   ChartPeriodIterator $iterator            optional Iterator is needed when it returns long form of array
     * @param   bool                $bshort              optional Whether it should return short form of array
     * @return  array               Returns data array
     */
    public function getTotalDataArray($id, $name, $currentPeriod, $previousPeriod, $previousWholePeriod,
                                      $detailed, ChartPeriodIterator $iterator = null, $bshort = false)
    {
        $cl = [
            'id'                => $id, // identifier
            'name'              => $name, // the name
            'cost'              => isset($currentPeriod['cost']) ? round($currentPeriod['cost'], 2) : 0, // period total
            'costPct'           => isset($currentPeriod['cost_percentage']) ? $currentPeriod['cost_percentage'] : 0, // percentage of the period total
            'prevCost'          => (isset($previousPeriod['cost']) ? round($previousPeriod['cost'], 2) : 0),  // last period total
            'prevCostPct'       => (isset($previousPeriod['cost_percentage']) ? $previousPeriod['cost_percentage'] : 0), // percentage of the last period total
        ];

        $cl['growth']           = $cl['cost'] - $cl['prevCost']; // growth
        $cl['growthPct']        = $cl['prevCost'] == 0 ? null : round(abs($cl['growth'] / $cl['prevCost'] * 100), 0); //growth percentage

        //short form of the data array
        if ($bshort) return $cl;

        $queryInterval = preg_replace('/^1 /', '', $iterator->getInterval());

        $itemsRollingAvg = $this->getRollingAvg([], $queryInterval, $iterator->getEnd(), null, $currentPeriod);
        // forecasted spend for period
        $cl['forecastCost'] = self::calculateForecast(
            $cl['cost'], $iterator->getStart(), $iterator->getEnd(), $previousWholePeriod['cost'],
            ($cl['growth'] > 0 ? 1 : -1) * $cl['growthPct'],
            (isset($itemsRollingAvg['rollingAverageDaily']) ? $itemsRollingAvg['rollingAverageDaily'] : null)
        );


        $mediandata = [];
        if (!empty($detailed[$id]['data'])) {
            $dt = $iterator->getStart();

            foreach ($detailed[$id]['data'] as $i => $v) {
                if ($dt > $iterator->today) break;
                $mediandata[] = !isset($v['cost']) ? 0 : $v['cost'];
                if ($iterator->getInterval() == '1 week') {
                    $dt->modify('next sunday');
                } else {
                    $dt->add($iterator->getIterationInterval());
                }
            }
        }

        $cl['median'] = empty($detailed[$id]['data']) ? 0 : round((float)Scalr_Util_Arrays::median($mediandata), 2);

        $cl['averageCost'] = count($mediandata) ? round(array_sum($mediandata) / count($mediandata), 2) : 0;

        // percentage difference between current and previous period
        $cl['curPrevPctGrowth'] = $cl['costPct'] - $cl['prevCostPct'];

        return $cl;
    }

    /**
     * Gets rolling average for the past N intervals of the same length
     *
     * @param   array    $criteria      Criteria accepts two parameters 'projectId' and 'ccId'
     * @param   array    $queryInterval The interval of each point on chart
     * @param   string   $toDate        To date
     * @param   int      $accountId     optional Current user id. Required for calculating trends for farms
     * @param   string   $usage         optional Current usage
     * @param   array    $breakdown     optional Array of subtotals and item names ('subtotal' => 'items')
     * @throws  \InvalidArgumentException
     * @return  array
     */
    public function getRollingAvg($criteria, $queryInterval, $toDate = null, $accountId = null, $usage = null, array $breakdown = null)
    {
        $tz = new DateTimeZone('UTC');

        $end = $toDate instanceof DateTime ? clone $toDate : new DateTime($toDate ?: 'yesterday', $tz);

        switch ($queryInterval) {
            case 'hour':
                //Past 24 hours rolling average
                $info = '24-hour rolling average';
                $start = clone $end;
                $num = 24;
                $days = 1;
                break;

            case 'day':
                //Past 7 days rolling average
                $info = '7-day rolling average';
                $start = clone $end;
                $start->modify("-6 days");
                $num = 7;
                break;

            case 'week':
                //Past 4 weeks rolling average
                $info = '4-week rolling average';
                $end->modify('last saturday');

                $start = clone $end;
                $start->modify('last sunday');
                $start->modify('-3 weeks');
                $num = 4;
                break;

            case 'month':
            case 'quarter':
            case 'year':
                //Past 3 months rolling average
                $info = '3-month rolling average';
                $end = $end->modify('last day of last month');

                $start = new DateTime($end->format('Y-m-01'), $tz);
                $start->modify('-2 months');
                $num = 3;
                break;

            default:
                throw new \UnexpectedValueException(sprintf("Unexpected query interval %s", $queryInterval));
        }

        if (!isset($days)) {
            //The number of the complete days takes part in the calculation of the rolling Average
            $days = $start->diff($end, true)->days + 1;
        }

        $end->setTime(23, 59, 59);
        $start->setTime(0, 0, 0);

        $itemsAverage = [];

        if (!isset($usage)) {
            if (isset($criteria['farmId']) && isset($accountId)) {
                $usage = \Scalr::getContainer()->analytics->usage->getFarmData($accountId, ['farmId' => $criteria['farmId']], $start, $end);
            } elseif (isset($criteria['envId']) && isset($accountId)) {
                $usage = \Scalr::getContainer()->analytics->usage->getFarmData($accountId, ['envId' => $criteria['envId']], $start, $end);
            } else {
                $usage = \Scalr::getContainer()->analytics->usage->get($criteria, $start, $end, $queryInterval);
            }
        } elseif (isset($breakdown)) {
            foreach ($breakdown as $itemId => $itemName) {
                $arr = (new AggregationCollection([$itemId], ['cost' => 'sum']))->load($usage)->calculatePercentage();

                if (!empty($arr['data'])) {
                    foreach ($arr['data'] as $id => $value) {
                        $itemsAverage[$itemName][$id] = [
                            'rollingAverage'        => round(($num == 0 ? 0 : $value['cost'] / $num), 2),
                            'rollingAverageMessage' => $info,
                            'rollingAverageDaily'   => round(($days == 0 ? 0 : $value['cost'] / $days), 2),
                        ];
                    }
                }
            }
        }

        return [
            'rollingAverage'        => round(($num == 0 || !isset($usage['cost']) ? 0 : $usage['cost'] / $num), 2),
            'rollingAverageMessage' => $info,
            'rollingAverageDaily'   => round(($days == 0 || !isset($usage['cost']) ? 0 : $usage['cost'] / $days), 2),
        ] + $itemsAverage;
    }

    /**
     * Calculates spending trends data according to specified data sets
     *
     * @param   array    $criteria      Criteria accepts two parameters 'projectId' and 'ccId'
     * @param   array    $timeline      Timeline array
     * @param   array    $queryInterval The interval of each point on chart (hour, day, week, month, quarter)
     * @param   DateTime $toDate        The date that rolling average should be calculated to.
     * @param   int      $accountId     optional Curretn user id. Required for calculating trends for farms
     * @throws  \InvalidArgumentException
     * @return  array
     */
    public function calculateSpendingTrends($criteria, &$timeline, $queryInterval, $toDate, $accountId = null)
    {
        $dailyusage = [];

        $max = null;
        $maxDate = null;

        $min = null;
        $minDate = null;

        $date = new DateTime('now', new DateTimeZone('UTC'));

        $todayLabel = $date->format('Y-m-d ' . ($queryInterval == 'hour' ? 'H' : '00') . ':00');

        foreach ($timeline as $key => $v) {
            if ($todayLabel <= $v['datetime']) break;

            $cost = isset($v['cost']) ? $v['cost'] : 0;

            if (null === $max || $cost > $max) {
                $max = $cost;
                $maxDate = $v['label'];
            }

            if (null === $min || $cost < $min) {
                $min = $cost;
                $minDate = $v['label'];
            }

            $dailyusage[$key] = $cost;
        }

        //Today statistics is excluded from rolling average calculation
        $date->modify('-1 day');

        if ($date < $toDate) {
            $rollingAverage = $this->getRollingAvg($criteria, $queryInterval, min($date, $toDate), $accountId);
        } else {
            //For previous complete periods it returns usual average of datapoints
            $rollingAverage = [
                'rollingAverage'        => count($dailyusage) == 0 ? 0 : round(array_sum($dailyusage) / count($dailyusage), 2),
                'rollingAverageMessage' => ucfirst(preg_replace('/y$/', 'i', $queryInterval)) . 'ly Average',
            ];
        }

        return $rollingAverage + [
            'periodHigh'     => (isset($max) ? round($max, 2) : null),
            'periodLow'      => (isset($min) ? round($min, 2) : null),
            'periodHighDate' => $maxDate,
            'periodLowDate'  => $minDate,
        ];
    }

    /**
     * Returns iterator for current quarter
     *
     * @return Iterator\ChartQuarterlyIterator
     */
    public function getCurrentQuarterIterator()
    {
        $quarters = new Quarters(SettingEntity::getQuarters());
        $currentQuarter = $quarters->getQuarterForDate(new \DateTime('now', new \DateTimeZone('UTC')));
        $currentYear = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y');

        if ($currentQuarter === 1) {
            $quarter = 4;
            $year = $currentYear - 1;
        } else {
            $quarter = $currentQuarter - 1;
            $year = $currentYear;
        }

        $date = $quarters->getPeriodForQuarter($quarter, $year);
        $iterator = ChartPeriodIterator::create('quarter', $date->start, $date->end, 'UTC');

        return $iterator;
    }

}
