<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Stats\CostAnalytics\Entity\QuarterlyBudgetEntity;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Quarters;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151119104251 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '3b3375c1-fd82-494e-ade5-b8b5980d92e1';

    protected $depends = [];

    protected $description = "Corrections in quarter spend for the last year.";

    protected $dbservice = 'cadb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        $quarters = new Quarters(SettingEntity::getQuarters());
        $period = $quarters->getPeriodForDate();

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $currentQuarter = ceil($now->format('m') / 3);

        if ($period->quarter != $currentQuarter) {
            return false;
        }

        return true;
    }

    protected function run1($stage)
    {
        $this->console->out('Initializing quarterly_budget data...');

        $quarters = new Quarters(SettingEntity::getQuarters());

        $currentYearPeriod = $quarters->getPeriodForDate();
        $currentFiscalYear = $currentYearPeriod->year;

        $prevFiscalYear = $currentFiscalYear - 1;

        $this->db->Execute("
            UPDATE quarterly_budget b
            SET b.`cumulativespend` = 0.000000
            WHERE b.`year` = ? OR b.`year` = ?
        ", [$currentFiscalYear, $prevFiscalYear]);

        foreach ([$prevFiscalYear, $currentFiscalYear] as $year) {
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $quarterPeriod = $quarters->getPeriodForQuarter($quarter, $year);

                $this->db->Execute("
                    INSERT INTO quarterly_budget (`year`, `quarter`, `subject_type`, `subject_id`, `cumulativespend`)
                    SELECT ?, u.`quarter`, u.`subject_type`, u.`subject_id`, u.`cumulativespend`
                    FROM (
                        SELECT ? AS `quarter`, ? AS `subject_type`, `cc_id` AS `subject_id`, SUM(`cost`) AS `cumulativespend`
                        FROM usage_d
                        WHERE `date` BETWEEN ? AND ?
                        GROUP BY `cc_id`
                        UNION ALL
                        SELECT ? AS `quarter`, ? AS `subject_type`, `project_id` AS `subject_id`, SUM(`cost`) AS `cumulativespend`
                        FROM usage_d
                        WHERE `date` BETWEEN ? AND ?
                        GROUP BY `project_id`
                    ) AS u
                    ON DUPLICATE KEY UPDATE
                        `cumulativespend` = u.`cumulativespend`
                ", [
                    $year,
                    $quarter,
                    QuarterlyBudgetEntity::SUBJECT_TYPE_CC,
                    $quarterPeriod->start->format('Y-m-d'),
                    $quarterPeriod->end->format('Y-m-d'),
                    $quarter,
                    QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT,
                    $quarterPeriod->start->format('Y-m-d'),
                    $quarterPeriod->end->format('Y-m-d')
                ]);

                $this->db->Execute("
                    UPDATE quarterly_budget b
                    JOIN (
                        (SELECT SUM(`cost`) AS `cost`, `date`, `cc_id` AS `subject_id`
                        FROM usage_d
                        WHERE `date` BETWEEN ? AND ?
                        GROUP BY `cc_id`, `date`)
                        UNION ALL
                        (SELECT SUM(`cost`) AS `cost`, `date`, `project_id` AS `subject_id`
                        FROM usage_d
                        WHERE `date` BETWEEN ? AND ?
                        GROUP BY `project_id`, `date`)
                        ORDER BY `date`
                    ) AS ud ON b.subject_id = ud.`subject_id` AND b.`year` = ?
                    SET b.`spentondate` = IF (
                        b.`budget` > 0 AND b.`spentondate` IS NULL AND ud.`cost` >= b.`budget`,
                        ud.`date`,
                        b.`spentondate`
                    )
                    WHERE b.`quarter` = ?
                ", [
                    $quarterPeriod->start->format('Y-m-d'),
                    $quarterPeriod->end->format('Y-m-d'),
                    $quarterPeriod->start->format('Y-m-d'),
                    $quarterPeriod->end->format('Y-m-d'),
                    $year,
                    $quarter
                ]);
            }
        }
    }

}