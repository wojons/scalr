<?php

use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Quarters;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportPayloadEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr\Upgrade\Console;
use \SERVER_PLATFORMS;
use \DateTime, \DateTimeZone;

class AnalyticsNotificationsProcess implements \Scalr\System\Pcntl\ProcessInterface
{
    public $ThreadArgs;
    public $ProcessDescription = "Analytics notification process";
    public $Logger;
    public $IsDaemon;

    public function __construct()
    {
        $this->Logger = Logger::getLogger(__CLASS__);
        $this->console = new Console();
        $this->console->timeformat = 'H:i:s';
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Pcntl\ProcessInterface::OnStartForking()
     */
    public function OnStartForking()
    {
        if (!\Scalr::getContainer()->analytics->enabled) {
            die("Terminating the process as Cost analytics is disabled in the config.\n");
        }

        $this->console->out("%s (UTC) Start Analytics Notifications process", gmdate('Y-m-d'));
        $baseUrl = rtrim(\Scalr::getContainer()->config('scalr.endpoint.scheme') . "://" . \Scalr::getContainer()->config('scalr.endpoint.host') , '/');

        if (SettingEntity::getValue(SettingEntity::ID_NOTIFICATIONS_CCS_ENABLED) || SettingEntity::getValue(SettingEntity::ID_NOTIFICATIONS_PROJECTS_ENABLED)) {
            $this->console->out('Calculating data for projects notifications');

            $quarters = new Quarters(SettingEntity::getQuarters());
            $date = $quarters->getPeriodForDate('yesterday');
            $formatedTitle = 'Q' . $quarters->getQuarterForDate('now') . ' budget (' . (new \DateTime('now', new \DateTimeZone('UTC')))->format('M j, Y') . ')';

            $projects = ProjectEntity::find();

            foreach ($projects as $project) {
                $periodProjectData = \Scalr::getContainer()->analytics->usage->getProjectPeriodData($project->projectId, 'quarter', $date->start->format('Y-m-d'), $date->end->format('Y-m-d'));
                $projectAnalytics[$project->projectId] = [
                    'budget'         => $periodProjectData['totals']['budget'],
                    'name'           => $project->name,
                    'trends'         => $periodProjectData['totals']['trends'],
                    'forecastCost'   => $periodProjectData['totals']['forecastCost'],
                    'interval'       => $periodProjectData['interval'],
                    'date'           => $formatedTitle,
                    'detailsUrl'     => $baseUrl . '#/analytics/projects?projectId=' . $project->projectId,
                    'jsonVersion'    => '1.0.0',
                    'farms'          => []
                ];

                if (!empty($periodProjectData['totals']['farms'])) {
                    foreach ($periodProjectData['totals']['farms'] as $farm) {
                        $projectAnalytics[$project->projectId]['farms'][] = [
                            'id'           => $farm['id'],
                            'name'         => $farm['name'],
                            'averageCost'  => $farm['averageCost'],
                            'cost'         => $farm['cost'],
                            'costPct'      => $farm['costPct']
                        ];
                    }
                    if (count($projectAnalytics[$project->projectId]['farms'] > 1)) {
                        usort($projectAnalytics[$project->projectId]['farms'], array($this, 'sortItems'));
                        if (count($projectAnalytics[$project->projectId]['farms'] > 6)) {
                            array_splice($projectAnalytics[$project->projectId]['farms'], 6, count($projectAnalytics[$project->projectId]['farms']));
                        }
                    }
                }

                if ($project->archived) {
                    continue;
                }

                if (SettingEntity::getValue(SettingEntity::ID_NOTIFICATIONS_PROJECTS_ENABLED)) {
                    $projectNotifications = NotificationEntity::findBySubjectType(NotificationEntity::SUBJECT_TYPE_PROJECT);

                    foreach ($projectNotifications as $notification) {
                        $this->saveNotificationData('project', $notification, $project->projectId, $projectAnalytics);
                    }
                }
            }
        }

        if (SettingEntity::getValue(SettingEntity::ID_NOTIFICATIONS_CCS_ENABLED)) {
            $this->console->out('Calculating data for cost center notifications');

            $ccs = CostCentreEntity::find();

            foreach ($ccs as $cc) {
                if ($cc->archived) {
                    continue;
                }
                $periodCostCenterData = \Scalr::getContainer()->analytics->usage->getCostCenterPeriodData($cc->ccId, 'quarter', $date->start->format('Y-m-d'), $date->end->format('Y-m-d'));
                $ccAnalytics[$cc->ccId] = [
                    'budget'         => $periodCostCenterData['totals']['budget'],
                    'name'           => $cc->name,
                    'trends'         => $periodCostCenterData['totals']['trends'],
                    'forecastCost'   => $periodCostCenterData['totals']['forecastCost'],
                    'interval'       => $periodCostCenterData['interval'],
                    'date'           => $formatedTitle,
                    'detailsUrl'     => $baseUrl . '#/analytics/costcenters?ccId=' . $cc->ccId,
                    'jsonVersion'    => '1.0.0',
                    'projects'       => []
                ];

                if (!empty($periodCostCenterData['totals']['projects'])) {

                    foreach ($periodCostCenterData['totals']['projects'] as $key => $project) {
                        if (!empty($project['id'])) {
                            $projectBudget = $projectAnalytics[$project['id']]['budget'];
                            $projectBudget['name'] = $project['name'];
                            $projectBudget['id'] = $project['id'];
                            $projectBudget['averageCost'] = $project['averageCost'];
                            $ccAnalytics[$cc->ccId]['projects'][] = $projectBudget;
                        } else {
                            $otherProjectsKey = $key;
                        }
                    }

                    if (isset($otherProjectsKey)) {
                        $ccAnalytics[$cc->ccId]['projects'][] = [
                            'id'                => '',
                            'budgetSpent'       => $periodCostCenterData['totals']['projects'][$otherProjectsKey]['cost'],
                            'averageCost'       => $periodCostCenterData['totals']['projects'][$otherProjectsKey]['averageCost'],
                            'name'              => $periodCostCenterData['totals']['projects'][$otherProjectsKey]['name'],
                            'estimateOverspend' => null,
                        ];
                        unset($otherProjectsKey);
                    }

                    if (count($ccAnalytics[$cc->ccId]['projects'] > 1)) {
                        usort($ccAnalytics[$cc->ccId]['projects'], array($this, 'sortItems'));
                        if (count($ccAnalytics[$cc->ccId]['projects'] > 6)) {
                            array_splice($ccAnalytics[$cc->ccId]['projects'], 6, count($ccAnalytics[$cc->ccId]['projects']));
                        }
                    }
                }

                $ccsNotifications = NotificationEntity::findBySubjectType(NotificationEntity::SUBJECT_TYPE_CC);

                foreach ($ccsNotifications as $notification) {
                    $this->saveNotificationData('cc', $notification, $cc->ccId, $ccAnalytics);
                }
            }
        }

        if (SettingEntity::getValue(SettingEntity::ID_REPORTS_ENABLED)) {
            $this->console->out('Calculating data for reports');
            $reports = ReportEntity::find();

            foreach ($reports as $report) {
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
                        $currentQuarter = $quarters->getQuarterForDate(new \DateTime('yesterday', new \DateTimeZone('UTC')));
                        $currentYear = (new \DateTime('yesterday', new \DateTimeZone('UTC')))->format('Y');

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

                        $startForecast = $currentYear . '-01-01';
                        $endForecast = $currentYear . '-12-31';
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
                    $currentQuarter = $quarters->getQuarterForDate(new \DateTime($start, new \DateTimeZone('UTC')));
                    $currentYear = (new \DateTime($start, new \DateTimeZone('UTC')))->format('Y');
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
                    $periodData = \Scalr::getContainer()->analytics->usage->getDashboardPeriodData($period, $start, $end);
                    $periodDataForecast = \Scalr::getContainer()->analytics->usage->getDashboardPeriodData($periodForecast, $startForecast, $endForecast);
                    $periodData['period'] = $period;
                    $periodData['forecastPeriod'] = $formatedForecastDate;
                    $periodData['totals']['forecastCost'] = $periodDataForecast['totals']['forecastCost'];
                    $periodData['name'] = 'Cloud Cost Report';
                    $periodData['jsonVersion'] = '1.0.0';
                    $periodData['detailsUrl'] = $baseUrl . '#/analytics/dashboard';
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
                        $this->console->out('Summary report email has been sent');
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
        }

        $this->console->out('Done');

        exit();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Pcntl\ProcessInterface::OnEndForking()
     */
    public function OnEndForking()
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Pcntl\ProcessInterface::StartThread()
     */
    public function StartThread($id)
    {
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

        $periodData['detailsUrl'] = $baseUrl . '#/analytics/' . $subjects . '?' . $subjectIdName . '=' . $subjectId;
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
            $this->console->out('Report email has been sent');
            $payload['date'] = $entity->created->format('Y-m-d');
            $entity->payload = json_encode($payload);
            $entity->save();
        }
    }

    /**
     * Saves notifications
     *
     * @param string             $subject           Subject name (project or cc)
     * @param NotificationEntity $notification      Notification entity
     * @param string             $subjectId         Cost Center or project id
     * @param array              $subjectAnalytics  Array of subjects with data to save
     */
    private function saveNotificationData($subject, $notification, $subjectId, &$subjectAnalytics)
    {
        if ($notification->notificationType === NotificationEntity::NOTIFICATION_TYPE_USAGE) {
            $reportType = $subject . 'Usage';
            $budgetThreshold = 'budgetSpentPct';
            $emailSubject = $subjectAnalytics[$subjectId]['name'] . ' usage notification.';
        } else if ($notification->notificationType === NotificationEntity::NOTIFICATION_TYPE_PROJECTED_OVERSPEND) {
            $reportType = $subject . 'Overspend';
            $budgetThreshold = 'estimateOverspendPct';
            $emailSubject = $subjectAnalytics[$subjectId]['name'] . ' overspend notification.';
        }

        if ($subjectAnalytics[$subjectId]['budget'][$budgetThreshold] >= $notification->threshold) {
            $subjectAnalytics[$subjectId]['reportType'] = $reportType;
            $entity = ReportPayloadEntity::init([$notification->notificationType, $notification->subjectType, $subjectId, $notification->threshold], $subjectAnalytics[$subjectId]);

            if (!ReportPayloadEntity::findPk($entity->uuid)) {
                $payload = json_decode($entity->payload, true);

                if (!empty($subjectAnalytics[$subjectId]['budget']['estimateDate'])) {
                    $subjectAnalytics[$subjectId]['budget']['estimateDate'] = (new DateTime($subjectAnalytics[$subjectId]['budget']['estimateDate'], new DateTimeZone('UTC')))->format('M j, Y');
                    $subjectAnalytics[$subjectId]['reportUrl'] = $payload['reportUrl'];
                }

                \Scalr::getContainer()->mailer->setSubject($emailSubject)->setContentType('text/html')->sendTemplate(SCALR_TEMPLATES_PATH . '/emails/budget_notification_' . $subject . '.html.php', $subjectAnalytics[$subjectId], $notification->emails);
                $this->console->out('Notification email has been sent');
                $payload['date'] = $entity->created->format('Y-m-d');
                $entity->payload = json_encode($payload);
                $entity->save();
            }
        }
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

}
