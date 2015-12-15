<?php

namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportPayloadEntity;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\QuarterPeriod;
use Scalr\Stats\CostAnalytics\Quarters;
use Scalr\System\Zmq\Cron\AbstractTask;
use SERVER_PLATFORMS;

/**
 * AnalyticsNotifications task
 *
 * @author  N.V.
 */
class AnalyticsNotifications extends AbstractTask
{

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        if (!\Scalr::getContainer()->analytics->enabled) {
            $this->getLogger()->info("Terminating the process as Cost analytics is disabled in the config.\n");
            return new ArrayObject();
        }

        $this->getLogger()->info("%s (UTC) Start Analytics Notifications process", gmdate('Y-m-d'));

        $notifications = NotificationEntity::find();
        $this->getLogger()->info('Calculating data for projects and cost centers notifications');

        foreach ($notifications as $notification) {
            /* @var $notification NotificationEntity */
            if ($notification->status === NotificationEntity::STATUS_DISABLED) {
                continue;
            }

            if ($notification->subjectType === NotificationEntity::SUBJECT_TYPE_CC) {
                $subjectEntityName = 'Scalr\\Stats\\CostAnalytics\\Entity\\CostCentre';
            } else if ($notification->subjectType === NotificationEntity::SUBJECT_TYPE_PROJECT) {
                $subjectEntityName = 'Scalr\\Stats\\CostAnalytics\\Entity\\Project';
            }

            if (!empty($notification->subjectId)) {
                $subject = call_user_func($subjectEntityName . 'Entity::findPk', $notification->subjectId);
                $this->saveNotificationData($subject, $notification);
            } else {
                $subjects = call_user_func($subjectEntityName . 'Entity::find');

                foreach ($subjects as $subject) {
                    if ($subject->archived) {
                        continue;
                    }

                    $this->saveNotificationData($subject, $notification);
                }
            }
        }

        $this->getLogger()->info('Calculating data for reports');
        $reports = ReportEntity::find();

        foreach ($reports as $report) {
            /* @var $report ReportEntity */
            if ($report->status === ReportEntity::STATUS_DISABLED) {
                continue;
            }

            switch ($report->period) {
                case ReportEntity::PERIOD_DAILY:
                    $period = 'custom';
                    $start = (new \DateTime('yesterday', new \DateTimeZone('UTC')))->format('Y-m-d');
                    $end = $start;
                    $startForecast = (new \DateTime('first day of this month', new \DateTimeZone('UTC')))->format('Y-m-d');
                    $endForecast = (new \DateTime('last day of this month', new \DateTimeZone('UTC')))->format('Y-m-d');

                    if ($startForecast == (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d')) {
                        $startForecast = (new \DateTime('first day of last month', new \DateTimeZone('UTC')))->format('Y-m-d');
                        $endForecast = (new \DateTime('last day of last month', new \DateTimeZone('UTC')))->format('Y-m-d');
                    }

                    $periodForecast = 'month';
                    $formatedTitle = (new \DateTime($start, new \DateTimeZone('UTC')))->format('M j');
                    $formatedForecastDate = (new \DateTime($start, new \DateTimeZone('UTC')))->format('F');
                    break;

                case ReportEntity::PERIOD_MONTHLY:
                    $period = 'month';
                    $start = (new \DateTime('first day of last month', new \DateTimeZone('UTC')))->format('Y-m-d');
                    $end = (new \DateTime('last day of last month', new \DateTimeZone('UTC')))->format('Y-m-d');
                    $formatedTitle = (new \DateTime($start, new \DateTimeZone('UTC')))->format('M Y');
                    break;

                case ReportEntity::PERIOD_QUARTELY:
                    $period = 'quarter';
                    $quarters = new Quarters(SettingEntity::getQuarters());

                    $currentPeriod = $quarters->getPeriodForDate(new \DateTime('yesterday', new \DateTimeZone('UTC')));

                    $currentQuarter = $currentPeriod->quarter;
                    $currentYear = $currentPeriod->year;

                    if ($currentQuarter === 1) {
                        $quarter = 4;
                        $year = $currentYear - 1;
                    } else {
                        $quarter = $currentQuarter - 1;
                        $year = $currentYear;
                    }

                    $date = $quarters->getPeriodForQuarter($quarter, $year);

                    $start = $date->start->format('Y-m-d');
                    $end = $date->end->format('Y-m-d');

                    $formatedTitle = 'Q' . $quarter . ' ' . $year;
                    $formatedForecastDate = 'End of ' . $currentYear;

                    $forecastPeriod = $quarters->getPeriodForYear($year);

                    $startForecast = $forecastPeriod->start;
                    $endForecast = $forecastPeriod->end;
                    $periodForecast = 'year';
                    break;

                case ReportEntity::PERIOD_WEEKLY:
                    $period = 'week';
                    $end = (new \DateTime('yesterday', new \DateTimeZone('UTC')))->modify('last saturday')->format('Y-m-d');
                    $start = (new \DateTime($end, new \DateTimeZone('UTC')))->modify('last sunday')->format('Y-m-d');
                    $formatedTitle = (new \DateTime($start, new \DateTimeZone('UTC')))->format('M j') . ' - ' . (new \DateTime($end, new \DateTimeZone('UTC')))->format('M j');
                    break;
            }

            if ($report->period !== ReportEntity::PERIOD_DAILY && $report->period !== ReportEntity::PERIOD_QUARTELY) {
                $quarters = new Quarters(SettingEntity::getQuarters());

                $currentPeriod = $quarters->getPeriodForDate(new \DateTime($start, new \DateTimeZone('UTC')));

                $currentQuarter = $currentPeriod->quarter;
                $currentYear = $currentPeriod->year;

                $date = $quarters->getPeriodForQuarter($currentQuarter, $currentYear);
                $formatedForecastDate = 'End of Q' . $currentQuarter;

                $startForecast = $date->start->format('Y-m-d');
                $endForecast = $date->end->format('Y-m-d');
                $periodForecast = 'quarter';
            }

            if ($report->subjectType === ReportEntity::SUBJECT_TYPE_CC) {
                $getPeriodicSubjectData = 'getCostCenterPeriodData';
                $subjectEntityName = 'Scalr\\Stats\\CostAnalytics\\Entity\\CostCentre';
                $subjectId = 'ccId';
            } else if ($report->subjectType === ReportEntity::SUBJECT_TYPE_PROJECT) {
                $getPeriodicSubjectData = 'getProjectPeriodData';
                $subjectEntityName = 'Scalr\\Stats\\CostAnalytics\\Entity\\Project';
                $subjectId = 'projectId';
            } else {
                $baseUrl = rtrim(\Scalr::getContainer()->config('scalr.endpoint.scheme') . "://" . \Scalr::getContainer()->config('scalr.endpoint.host') , '/');
                $periodData = \Scalr::getContainer()->analytics->usage->getDashboardPeriodData($period, $start, $end);
                $periodDataForecast = \Scalr::getContainer()->analytics->usage->getDashboardPeriodData($periodForecast, $startForecast, $endForecast);

                $periodData['period'] = $period;
                $periodData['forecastPeriod'] = $formatedForecastDate;
                $periodData['totals']['forecastCost'] = $periodDataForecast['totals']['forecastCost'];
                $periodData['name'] = 'Cloud Cost Report';
                $periodData['jsonVersion'] = '1.0.0';
                $periodData['detailsUrl'] = $baseUrl . '#/admin/analytics/dashboard';
                $periodData['totals']['clouds'] = $this->changeCloudNames($periodData['totals']['clouds']);
                $periodData['date'] = $formatedTitle;
                $periodData['totals']['budget']['budget'] = null;

                if ($period !== 'custom') {
                    $periodData['totals']['prevPeriodDate'] = (new \DateTime($periodData['previousStartDate'], new \DateTimeZone('UTC')))->format('M d') . " - " . (new \DateTime($periodData['previousEndDate'], new \DateTimeZone('UTC')))->format('M d');
                } else {
                    $periodData['totals']['prevPeriodDate'] = (new \DateTime($periodData['previousEndDate'], new \DateTimeZone('UTC')))->format('M d');
                }

                if ($period == 'quarter') {
                    $periodData['totals']['budget'] = [
                        'quarter'           => $quarter,
                        'year'              => $year,
                        'quarterStartDate'  => $start,
                        'quarterEndDate'    => $end
                    ];
                } else if ($period == 'month') {
                    $periodData['totals']['budget'] = [
                        'quarter' => $currentQuarter,
                    ];
                }
                unset($periodData['projects'], $periodData['budget']['projects']);

                if (count($periodData['costcenters'] > 1)) {
                    uasort($periodData['costcenters'], array($this, 'sortItems'));
                    if (count($periodData['costcenters'] > 6)) {
                        array_splice($periodData['costcenters'], 6, count($periodData['costcenters']));
                    }
                }

                if (count($periodData['totals']['clouds'] > 1)) {
                    usort($periodData['totals']['clouds'], array($this, 'sortItems'));
                }

                $entity = ReportPayloadEntity::init([$report->subjectType, $report->subjectId, $period], $periodData, $start);

                if (!ReportPayloadEntity::findPk($entity->uuid)) {
                    $payload = json_decode($entity->payload, true);
                    \Scalr::getContainer()->mailer->setSubject('Summary report.')->setContentType('text/html')->sendTemplate(SCALR_TEMPLATES_PATH . '/emails/report_summary.html.php', $payload, $report->emails);

                    $this->getLogger()->info('Summary report email has been sent');

                    $payload['date'] = $entity->created->format('Y-m-d');
                    $entity->payload = json_encode($payload);
                    $entity->save();
                }
            }

            unset($currentQuarter, $currentYear);

            if (!empty($report->subjectType) && !empty($report->subjectId)) {
                $subject = call_user_func($subjectEntityName . 'Entity::findPk', $report->subjectId);

                if ($subject->archived) {
                    continue;
                }

                $this->saveReportData($getPeriodicSubjectData, $subjectEntityName,
                                      ['period' => $period, 'start' => $start, 'end' => $end],
                                      ['period' => $periodForecast, 'start' => $startForecast, 'end' => $endForecast],
                                      $report->subjectId, $report->subjectType, $report->emails, $formatedTitle, $formatedForecastDate
                );
            } else if (!empty($report->subjectType)) {
                $subjects = call_user_func($subjectEntityName . 'Entity::find');

                foreach ($subjects as $subject) {

                    if ($subject->archived) {
                        continue;
                    }

                    $this->saveReportData($getPeriodicSubjectData, $subjectEntityName,
                                          ['period' => $period, 'start' => $start, 'end' => $end],
                                          ['period' => $periodForecast, 'start' => $startForecast, 'end' => $endForecast],
                                          $subject->{$subjectId}, $report->subjectType, $report->emails, $formatedTitle, $formatedForecastDate
                    );
                }

            }
        }

        $this->getLogger()->info('Done');

        return new ArrayObject();
    }

    /**
     * Calculates data for reports and saves it
     *
     * @param string $getPeriodicSubjectData  Periodic data function name
     * @param string $subjectEntityName       Entity name of a subject (project or cost center)
     * @param array  $params                  Params array to retrieve periodic data for report period
     * @param array  $forecastParams          Params array to retrieve forecast for bigger period
     * @param string $subjectId               Cost center or project id
     * @param string $subjectType             Subject type of a report
     * @param string $emails                  Target emails
     * @param string $formatedTitle           Formated title name for report
     * @param string $formatedForecastDate    Formated forecast end estimate
     */
    private function saveReportData($getPeriodicSubjectData, $subjectEntityName, array $params, array $forecastParams, $subjectId, $subjectType, $emails, $formatedTitle, $formatedForecastDate)
    {
        $periodData = \Scalr::getContainer()->analytics->usage->$getPeriodicSubjectData($subjectId, $params['period'], $params['start'], $params['end']);
        $periodDataForecast = \Scalr::getContainer()->analytics->usage->$getPeriodicSubjectData($subjectId, $forecastParams['period'], $forecastParams['start'], $forecastParams['end']);
        $subjectEntity = call_user_func($subjectEntityName . 'Entity::findPk', $subjectId);
        $baseUrl = rtrim(\Scalr::getContainer()->config('scalr.endpoint.scheme') . "://" . \Scalr::getContainer()->config('scalr.endpoint.host') , '/');

        if (strpos($subjectEntityName, 'Project') !== false) {
            $subjects = 'projects';
            $subjectIdName = 'projectId';
        } else {
            $subjects = 'costcenters';
            $subjectIdName = 'ccId';
        }

        $periodData['detailsUrl'] = $baseUrl . '#/admin/analytics/' . $subjects . '?' . $subjectIdName . '=' . $subjectId;
        $periodData['period'] = $params['period'];
        $periodData['forecastPeriod'] = $formatedForecastDate;
        $periodData['totals']['forecastCost'] = $periodDataForecast['totals']['forecastCost'];
        $periodData['name'] = $subjectEntity->name;
        $periodData['jsonVersion'] = '1.0.0';
        $periodData['totals']['clouds'] = $this->changeCloudNames($periodData['totals']['clouds']);

        if ($params['period'] !== 'custom') {
            $periodData['totals']['prevPeriodDate'] = (new \DateTime($periodData['previousStartDate'], new \DateTimeZone('UTC')))->format('M d') . " - " . (new \DateTime($periodData['previousEndDate'], new \DateTimeZone('UTC')))->format('M d');
        } else {
            $periodData['totals']['prevPeriodDate'] = (new \DateTime($periodData['previousEndDate'], new \DateTimeZone('UTC')))->format('M d');
        }

        $periodData['date'] = $formatedTitle;
        $itemKey = isset($periodData['totals']['projects']) ? 'projects' : 'farms';

        if (count($periodData['totals'][$itemKey] > 1)) {
            uasort($periodData['totals'][$itemKey], array($this, 'sortItems'));
            if (count($periodData['totals'][$itemKey] > 6)) {
                array_splice($periodData['totals'][$itemKey], 6, count($periodData['totals'][$itemKey]));
            }
        }

        if (count($periodData['totals']['clouds'] > 1)) {
            usort($periodData['totals']['clouds'], array($this, 'sortItems'));
        }

        $entity = ReportPayloadEntity::init([$subjectType, $subjectId, $params['period']], $periodData, $params['start']);

        if (!ReportPayloadEntity::findPk($entity->uuid)) {
            $payload = json_decode($entity->payload, true);
            $emailTemplate = (strpos($subjectEntityName, 'Project') !== false) ? 'project' : 'cc';

            if ($periodData['period'] === 'custom') {
                $subjectPeriod = 'Daily';
            } else {
                $subjectPeriod = ucfirst($periodData['period']) . 'ly';
            }

            $emailSubject = ((strpos($subjectEntityName, 'Project') !== false) ? 'Project' : 'Cost Center') . ' ' . $subjectEntity->name . ' ' . $subjectPeriod . ' report';
            \Scalr::getContainer()->mailer->setSubject($emailSubject)->setContentType('text/html')->sendTemplate(SCALR_TEMPLATES_PATH . '/emails/report_' . $emailTemplate . '.html.php', $payload, $emails);
            $this->getLogger()->info('Report email has been sent');
            $payload['date'] = $entity->created->format('Y-m-d');
            $entity->payload = json_encode($payload);
            $entity->save();
        }
    }

    /**
     * Saves project or cost center notification
     *
     * @param ProjectEntity|CostCentreEntity $subject       Project or cost center entity
     * @param NotificationEntity             $notification  Current notification object
     * @throws InvalidArgumentException
     */
    private function saveNotificationData($subject, NotificationEntity $notification)
    {
        $baseUrl = rtrim(\Scalr::getContainer()->config('scalr.endpoint.scheme') . "://" . \Scalr::getContainer()->config('scalr.endpoint.host') , '/');
        $quarters = new Quarters(SettingEntity::getQuarters());
        $date = $quarters->getPeriodForDate('yesterday');
        $formatedTitle = 'Q' . $quarters->getQuarterForDate('now') . ' budget (' . (new \DateTime('now', new \DateTimeZone('UTC')))->format('M j, Y') . ')';

        if ($subject instanceof ProjectEntity) {
            $getPeriodicSubjectData = 'getProjectPeriodData';
            $subjects = 'projects';
            $childItems = 'farms';
            $subjectIdName = 'projectId';
            $subjectName = 'project';
        } else if ($subject instanceof CostCentreEntity) {
            $getPeriodicSubjectData = 'getCostCenterPeriodData';
            $subjects = 'costcenters';
            $childItems = 'projects';
            $subjectIdName = 'ccId';
            $subjectName = 'cc';
        } else {
            throw new InvalidArgumentException("Invalid subject parameter. It must be either ProjectEntity or CostCentreEntity type.");
        }

        $periodSubjectData = \Scalr::getContainer()->analytics->usage->$getPeriodicSubjectData($subject->{$subjectIdName}, 'quarter', $date->start->format('Y-m-d'), $date->end->format('Y-m-d'));

        $subjectAnalytics = [
            'budget'         => $periodSubjectData['totals']['budget'],
            'name'           => $subject->name,
            'trends'         => $periodSubjectData['totals']['trends'],
            'forecastCost'   => $periodSubjectData['totals']['forecastCost'],
            'interval'       => $periodSubjectData['interval'],
            'date'           => $formatedTitle,
            'detailsUrl'     => $baseUrl . '#/admin/analytics/' . $subjects . '?' . $subjectIdName . '=' . $subject->{$subjectIdName},
            'jsonVersion'    => '1.0.0',
            $childItems      => [],
        ];

        if (!empty($periodSubjectData['totals'][$childItems])) {
            $subjectAnalytics[$childItems] = $this->getSubjectChildItems($subject, $periodSubjectData['totals'][$childItems], $date);
        }

        if ($notification->notificationType === NotificationEntity::NOTIFICATION_TYPE_USAGE) {
            $reportType = $subjectName . 'Usage';
            $budgetThreshold = 'budgetSpentPct';
            $emailSubject = $subjectAnalytics['name'] . ' usage notification.';
        } else if ($notification->notificationType === NotificationEntity::NOTIFICATION_TYPE_PROJECTED_OVERSPEND) {
            $reportType = $subjectName . 'Overspend';
            $budgetThreshold = 'estimateOverspendPct';
            $emailSubject = $subjectAnalytics['name'] . ' overspend notification.';
        }

        if ($subjectAnalytics['budget'][$budgetThreshold] >= $notification->threshold) {
            $subjectAnalytics['reportType'] = $reportType;
            $entity = ReportPayloadEntity::init([$notification->notificationType, $notification->subjectType, $subject->{$subjectIdName}, $notification->threshold], $subjectAnalytics);

            if (!ReportPayloadEntity::findPk($entity->uuid)) {
                $payload = json_decode($entity->payload, true);

                if (!empty($subjectAnalytics['budget']['estimateDate'])) {
                    $subjectAnalytics['budget']['estimateDate'] = (new DateTime($subjectAnalytics['budget']['estimateDate'], new DateTimeZone('UTC')))->format('M j, Y');
                    $subjectAnalytics['reportUrl'] = $payload['reportUrl'];
                }

                \Scalr::getContainer()->mailer->setSubject($emailSubject)->setContentType('text/html')->sendTemplate(SCALR_TEMPLATES_PATH . '/emails/budget_notification_' . $subjectName . '.html.php', $subjectAnalytics, $notification->emails);

                $this->getLogger()->info('Notification email has been sent');

                $payload['date'] = $entity->created->format('Y-m-d');
                $entity->payload = json_encode($payload);
                $entity->save();
            }
        }
    }

    /**
     * Gets an array of farms or projects data for notification
     *
     * @param ProjectEntity|CostCentreEntity $subject     Project or cost center entity
     * @param array                          $childItems  Array of farms or projects of the current subject
     * @param QuarterPeriod                  $date        Current date time object
     * @return array
     */
    private function getSubjectChildItems($subject, array $childItems, QuarterPeriod $date)
    {
        $result = [];

        if ($subject instanceof ProjectEntity) {
            foreach ($childItems as $farm) {
                $result[] = [
                    'id'           => $farm['id'],
                    'name'         => $farm['name'],
                    'averageCost'  => $farm['averageCost'],
                    'cost'         => $farm['cost'],
                    'costPct'      => $farm['costPct']
                ];
            }
        } else {
            foreach ($childItems as $key => $project) {
                if (!empty($project['id'])) {
                    $periodProjectData = \Scalr::getContainer()->analytics->usage->getProjectPeriodData($project['id'], 'quarter', $date->start->format('Y-m-d'), $date->end->format('Y-m-d'));
                    $projectBudget = $periodProjectData['totals']['budget'];
                    $projectBudget['name'] = $project['name'];
                    $projectBudget['id'] = $project['id'];
                    $projectBudget['averageCost'] = $project['averageCost'];
                    $result[] = $projectBudget;
                } else {
                    $otherProjectsKey = $key;
                }
            }

            if (isset($otherProjectsKey)) {
                $result[] = [
                    'id'                => '',
                    'budgetSpent'       => $childItems[$otherProjectsKey]['cost'],
                    'averageCost'       => $childItems[$otherProjectsKey]['averageCost'],
                    'name'              => $childItems[$otherProjectsKey]['name'],
                    'estimateOverspend' => null,
                ];
                unset($otherProjectsKey);
            }
        }
        if (count($result > 1)) {
            usort($result, array($this, 'sortItems'));
            if (count($result > 6)) {
                array_splice($result, 6, count($result));
            }
        }

        return $result;
    }

    /**
     * Changes clouds name format
     *
     * @param array $clouds
     * @return array
     */
    private function changeCloudNames($clouds)
    {
        if (!empty($clouds)) {
            foreach ($clouds as &$cloud) {
                $cloud['name'] = SERVER_PLATFORMS::GetName($cloud['id']);
            }
        }

        return $clouds;
    }

    /**
     * Callback sort function
     *
     * @param int $item1 Cost of the 1 item
     * @param int $item2 Cost of the 2 item
     *
     * @return int
     */
    private function sortItems($item1, $item2)
    {
        if (empty($item1['id']) || $item1['id'] == 'everything else') {
            return 1;
        }

        if (empty($item2['id']) || $item2['id'] == 'everything else') {
            return -1;
        }

        $element1 = isset($item1['budgetSpent']) ? $item1['budgetSpent'] : $item1['cost'];
        $element2 = isset($item2['budgetSpent']) ? $item2['budgetSpent'] : $item2['cost'];

        if ($element1 == $element2) {
            return 0;
        }
        return ($element1 > $element2) ? -1 : 1;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        return $request;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractTask::config()
     */
    public function config()
    {
        $config = parent::config();

        if ($config->daemon) {
            //Report a warning to log
            trigger_error(sprintf("Demonized mode is not allowed for '%s' job.", $this->name), E_USER_WARNING);

            //Forces normal mode
            $config->daemon = false;
        }

        if ($config->workers != 1) {
            //It cannot be performed through ZMQ MDP as execution time is more than heartbeat
            trigger_error(sprintf("It is allowed only one worker for the '%s' job.", $this->name), E_USER_WARNING);
            $config->workers = 1;
        }

        return $config;
    }
}
