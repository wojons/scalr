<?php
namespace Scalr\Stats\CostAnalytics;

/**
 * QuarterPeriod
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (12.05.2014)
 */
class QuarterPeriod
{

    /**
     * Start date of the quarter
     *
     * @var \DateTime
     */
    public $start;

    /**
     * The end date of the quarter
     *
     * @var \DateTime
     */
    public $end;

    /**
     * The number of the quarter of the year (or "year" word)
     *
     * @var int|string
     */
    public $quarter;

    /**
     * The year which quarter corresponds to
     *
     * @var int
     */
    public $year;
}