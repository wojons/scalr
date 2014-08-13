<?php

namespace Scalr\Stats\CostAnalytics\Entity;

use DateTime, InvalidArgumentException;
use Scalr\Model\Collections\ArrayCollection;

/**
 * QuarterlyBudgetEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (06.05.2014)
 * @Entity
 * @Table(name="quarterly_budget",service="cadb")
 */
class QuarterlyBudgetEntity extends \Scalr\Model\AbstractEntity
{

    const SUBJECT_TYPE_CC = 1;
    const SUBJECT_TYPE_PROJECT = 2;

    /**
     * Year
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $year;

    /**
     * Subject type
     *
     * Allowed values:
     * 1 - Cost Centere
     * 2 - Project
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $subjectType;

    /**
     * The name of the cost centre
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $subjectId;

    /**
     * The number of the quarter [1-4]
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $quarter;

    /**
     * Budget amount
     *
     * @Column(type="decimal", precision=12, scale=2)
     * @var float
     */
    public $budget;

    /**
     * Final spent
     *
     * This is rudimentary field that should be removed
     *
     * @Column(type="decimal", precision=12, scale=2)
     * @var float
     */
    public $final;

    /**
     * The date on which budget was totally spent
     *
     * @Column(type="UTCDatetime",nullable=true)
     * @var DateTime
     */
    public $spentondate;

    /**
     * Cumulative spend
     *
     * @Column(type="decimal", precision=12, scale=6)
     * @var float
     */
    public $cumulativespend;

    /**
     * Convenient constuctor
     *
     * @param   string $year     optional The year
     * @param   string $quarter  optional The quarter
     */
    public function __construct($year = null, $quarter = null)
    {
        $this->year = $year;
        $this->quarter = $quarter;
        $this->budget = .0;
        $this->final = .0;
        $this->cumulativespend = .0;
    }

    /**
     * Gets relation dependent budget
     *
     * Another words it will return total budgeted amount for all projects
     * which have relations to the cost center.
     *
     * If we use this method for the project it will return 0
     *
     * @return  float  Returns relation dependent budget amount according to current state in the database
     */
    public function getRelationDependentBudget()
    {
        if (empty($this->subjectId)) {
            throw new InvalidArgumentException(sprintf(
                "Identifier of the subject has not been provided for the %s", get_class($this)
            ));
        }

        //Projects have no descendants
        if ($this->subjectType == self::SUBJECT_TYPE_PROJECT) {
            return 0;
        }

        $params = [
            $this->year,
            self::SUBJECT_TYPE_PROJECT
        ];

        $stmt = '';
        foreach (ProjectEntity::findByCcId($this->subjectId) as $project) {
            $stmt .= ", " . $this->qstr('subjectId', $project->projectId);
        }

        if ($stmt != '') {
            $ret = $this->db()->GetOne("
                SELECT SUM(q.`budget`) AS `budget`
                FROM " . $this->table('q') . "
                WHERE q.`year` = ?
                AND q.`subject_type` = ?
                " . (is_numeric($this->quarter) ? " AND q.`quarter` =" . intval($this->quarter) : "") . "
                AND q.`subject_id` IN (" . ltrim($stmt, ',') . ")
            ", $params);
        } else {
            $ret = .0;
        }

        return $ret;
    }

    /**
     * Gets budget for the specific year and quarter for all cost centres
     * for which it does exist.
     *
     * @param   int    $year     The year
     * @param   string $quarter  The quarter
     * @return  ArrayCollection  Returns the collection of the QuarterlyBudgetEntity objects
     */
    public static function getBudgetForCostCentres($year, $quarter)
    {
        return self::find([['year' => $year], ['quarter' => $quarter], ['subjectType' => self::SUBJECT_TYPE_CC]]);
    }

    /**
     * Gets budget for the specific year and quarter for all projects
     * for which it does exist.
     *
     * @param   int    $year     The year
     * @param   string $quarter  The quarter
     * @return  ArrayCollection  Returns the collection of the QuarterlyBudgetEntity objects
     */
    public static function getBudgetForProjects($year, $quarter)
    {
        return self::find([['year' => $year], ['quarter' => $quarter], ['subjectType' => self::SUBJECT_TYPE_PROJECT]]);
    }

    /**
     * Gets budget for the specified year and Cost Centre for all quarters
     * which it has been defined for.
     *
     * @param   int    $year  The year
     * @param   string $ccId  Identifier of the cost centre
     * @return  ArrayCollection Returns the collection of the QuarterlyBudgetEntity objects
     */
    public static function getCcBudget($year, $ccId)
    {
        return self::find([['year' => $year], ['subjectType' => self::SUBJECT_TYPE_CC], ['subjectId' => $ccId]]);
    }

    /**
     * Gets budget for the specified year and Project for all quarters
     * which it has been defined for.
     *
     * @param   int    $year       The year
     * @param   string $projectId  Identifier of the project
     * @return  ArrayCollection Returns the collection of the QuarterlyBudgetEntity objects
     */
    public static function getProjectBudget($year, $projectId)
    {
        return self::find([['year' => $year], ['subjectType' => self::SUBJECT_TYPE_PROJECT], ['subjectId' => $projectId]]);
    }
}
