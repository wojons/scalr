<?php

use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Exception\AnalyticsException;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Analytics_Projects extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        $this->response->page('ui/analytics/admin/projects/view.js', array(
            'projects' => $this->getProjectsList(),
            'quarters' => SettingEntity::getQuarters(true)
        ),array('/ui/analytics/analytics.js'), array('ui/analytics/analytics.css', '/ui/analytics/admin/admin.css'));
    }

    /**
     * xListAction
     *
     * @param string $query optional Search query
     * @param bool $showArchived optional show old archived projects
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xListAction($query = null, $showArchived = false)
    {
        $this->response->data(array(
            'projects' => $this->getProjectsList(trim($query), $showArchived)
        ));
    }

    public function addAction()
    {
        $this->editAction();
    }

    public function editAction($projectId = null, $ccId = null)
    {
        $ccs = [];

        $accountId = null;
        $envId = null;
        $userId = null;

        $collection = $this->getContainer()->analytics->ccs->all();

        if ($projectId) {
            $project = $this->getContainer()->analytics->projects->get($projectId);

            if (!$project) {
                throw new Scalr_UI_Exception_NotFound();
            }

            $accountId = $project->accountId;
            $envId = $project->envId;
            $userId = $project->shared == ProjectEntity::SHARED_TO_OWNER ? $project->createdById : null;
        }

        foreach ($collection as $cc) {
            /* @var $cc \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
            //For new projects we should exclude archived cost centres
            if ($cc->archived) {
                continue;
            }

            $ccs[$cc->ccId] = array(
                'ccId' => $cc->ccId,
                'name' => $cc->name,
                'billingCode' => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)
            );
        }

        if (isset($project)) {
            $projectData = $this->getProjectData($project, true);
            //Check whether it can be removed
            try {
                $projectData['removable'] = $project->checkRemoval();
            } catch (AnalyticsException $e) {
                $projectData['removable'] = false;
                $projectData['warning'] = $e->getMessage();
            }
        } else {
            $projectData = [];
        }

        $projectWidget = $this->getWidget([
            'accountId'  => $accountId,
            'envId'      => $envId,
            'userId'     => $userId,
            'ccId'       => $project->ccId,
            'shared'     => $project->shared,
            'farmsCount' => $projectData['farmsCount']
        ]);

        $this->response->page('ui/analytics/admin/projects/edit.js', array(
            'project'      => $projectData,
            'ccs'          => $ccs,
            'sharedWidget' => $projectWidget
        ));
    }

    /**
     * xSaveAction
     *
     * @param string $ccId
     * @param string $projectId
     * @param string $name
     * @param string $description
     * @param string $billingCode
     * @param string $leadEmail
     * @param int $shared
     * @param int $accountId optional
     * @param bool $checkAccountAccessToCc optional
     * @param bool $grantAccountAccessToCc optional
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xSaveAction($ccId, $projectId, $name, $description, $billingCode, $leadEmail, $shared, $accountId = null, $checkAccountAccessToCc = true, $grantAccountAccessToCc = false)
    {
        if ($projectId) {
            $project = $this->getContainer()->analytics->projects->get($projectId);

            if (!$project) {
                throw new Scalr_UI_Exception_NotFound();
            }
        } else {
            $project = new ProjectEntity();
            $project->createdById = $this->user->id;
            $project->createdByEmail = $this->user->getEmail();

            $cc = $this->getContainer()->analytics->ccs->get($ccId);
            if (!$cc) {
                $this->request->addValidationErrors('ccId', 'Cost center ID should be set');
            }

            $project->ccId = $ccId;

        }

        if ($shared == ProjectEntity::SHARED_WITHIN_ACCOUNT) {
            $project->shared = ProjectEntity::SHARED_WITHIN_ACCOUNT;
            $project->accountId = $accountId;
        } elseif ($shared == ProjectEntity::SHARED_WITHIN_CC) {
            $project->shared = ProjectEntity::SHARED_WITHIN_CC;
            $project->accountId = null;
        } else {
            throw new Scalr_UI_Exception_NotFound();
        }

        if (!$this->request->isValid()) {
            $this->response->data($this->request->getValidationErrors());
            $this->response->failure();
            return;
        }

        if ($project->shared == ProjectEntity::SHARED_WITHIN_ACCOUNT) {
            if (!AccountCostCenterEntity::findOne([['accountId' => $project->accountId], ['ccId' => $ccId]])) {
                if ($checkAccountAccessToCc) {
                    $this->response->data(['ccIsNotAllowedToAccount' => true]);
                    $this->response->failure();
                    return;
                } elseif ($grantAccountAccessToCc) {
                    //give account access to cc
                    $accountCcEntity = new AccountCostCenterEntity($project->accountId, $ccId);
                    $accountCcEntity->save();
                }
            }
        }
        
        $project->name = $name;

        $this->db->BeginTrans();

        try {
            $project->save();

            //NOTE please take into account the presence of the usage->createHostedScalrAccountCostCenter() method

            $project->saveProperty(ProjectPropertyEntity::NAME_BILLING_CODE, $billingCode);
            $project->saveProperty(ProjectPropertyEntity::NAME_DESCRIPTION, $description);
            $project->saveProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL, $leadEmail);

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();

            throw $e;
        }

        $this->response->data(['project' => $this->getProjectData($project)]);
        $this->response->success('Project has been successfully saved');

    }

    /**
     * xRemoveAction
     *
     * @param string $projectId Identifier of the project
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xRemoveAction($projectId)
    {
        $project = $this->getContainer()->analytics->projects->get($projectId);

        if ($project) {
            try {
                $removable = $project->checkRemoval();
            } catch (AnalyticsException $e) {
            }
            //Actually it archives the project and performs deletion
            //only if there are no records have been collected yet.
            $project->delete();
        } else {
            throw new Scalr_UI_Exception_NotFound();
        }

        $this->response->data(array('removable' => $removable));
        $this->response->success();
    }

    /**
     * @param string $projectId
     */
    public function notificationsAction($projectId)
    {
        $this->response->page('ui/analytics/admin/projects/notifications.js', array(
            'notifications' => NotificationEntity::find([['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT],['subjectId' => $projectId]]),
            'reports'       => ReportEntity::find([['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT],['subjectId' => $projectId]]),
        ), array(), array('ui/analytics/admin/notifications/view.css'));
    }

    /**
     * @param JsonData $notifications
     */
    public function xSaveNotificationsAction(JsonData $notifications)
    {
        $data = [];

        foreach ($notifications as $id => $settings) {
            if ($id == 'reports') {
                $this->saveReports($settings);
                $data[$id] = ReportEntity::all();
            } elseif ($id == 'notifications') {
                $this->saveNotifications(NotificationEntity::SUBJECT_TYPE_PROJECT, $settings);
                $data[$id] = NotificationEntity::findBySubjectType(NotificationEntity::SUBJECT_TYPE_PROJECT);
            }
        }

        $this->response->data($data);
        $this->response->success('Notifications successfully saved');
    }

    private function saveNotifications($subjectType, $settings)
    {
        $uuids = array();

        foreach ($settings['items'] as $item) {
            $notification = new NotificationEntity();

            if ($item['uuid']) {
                $notification->findPk($item['uuid']);
            }

            $notification->subjectType = $subjectType;
            $notification->subjectId = $item['subjectId'] ? $item['subjectId'] : null;
            $notification->notificationType = $item['notificationType'];
            $notification->threshold = $item['threshold'];
            $notification->recipientType = $item['recipientType'];
            $notification->emails = $item['emails'];
            $notification->status = $item['status'];
            $notification->save();
            $uuids[] = $notification->uuid;
        }

        foreach (NotificationEntity::findBySubjectType($subjectType) as $notification) {
            if (!in_array($notification->uuid, $uuids)) {
                $notification->delete();
            }
        }
    }

    private function saveReports($settings)
    {
        $uuids = array();

        foreach ($settings['items'] as $item) {
            $report = new ReportEntity();

            if ($item['uuid']) {
                $report->findPk($item['uuid']);
            }

            $report->subjectType = $item['subjectType'];

            $subject = null;

            if ($report->subjectType == ReportEntity::SUBJECT_TYPE_CC) {
                $subject = $this->getContainer()->analytics->ccs->get($item['subjectId']);
            } elseif ($report->subjectType == ReportEntity::SUBJECT_TYPE_PROJECT) {
                $subject = $this->getContainer()->analytics->projects->get($item['subjectId']);
            } else {
                $report->subjectType = null;
                $report->subjectId = null;
            }

            if ($report->subjectType) {
                if ($item['subjectId'] && !$subject) {
                    throw new Scalr_UI_Exception_NotFound();
                }
                $report->subjectId = $item['subjectId'] ? $item['subjectId'] : null;
            }

            $report->period = $item['period'];
            $report->emails = $item['emails'];
            $report->status = $item['status'];
            $report->save();
            $uuids[] = $report->uuid;
        }

        foreach (ReportEntity::all() as $report) {
            if (!in_array($report->uuid, $uuids)) {
                $report->delete();
            }
        }
    }

    /**
     * Gets an array of projects' data
     *
     * @param string $query Search query
     * @param bool $showArchived
     * @return array Returns project's list
     */
    private function getProjectsList($query = null, $showArchived = false)
    {
        $projects = [];

        $collection = $this->getContainer()->analytics->projects->findByKey($query);

        if ($collection->count()) {
            $iterator = ChartPeriodIterator::create('month', gmdate('Y-m-01'), null, 'UTC');

            //It calculates usage for all provided cost centres
            $usage = $this->getContainer()->analytics->usage->get(
                null, $iterator->getStart(), $iterator->getEnd(),
                [TagEntity::TAG_ID_PROJECT]
            );

            //It calculates usage for previous period same days
            $prevusage = $this->getContainer()->analytics->usage->get(
                null, $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
                [TagEntity::TAG_ID_PROJECT]
            );

            //Calclulates usage for previous whole period
            if ($iterator->getPreviousEnd() != $iterator->getWholePreviousPeriodEnd()) {
                $prevWholePeriodUsage = $this->getContainer()->analytics->usage->get(
                    null, $iterator->getPreviousStart(), $iterator->getWholePreviousPeriodEnd(),
                    [TagEntity::TAG_ID_PROJECT]
                );
            } else {
                $prevWholePeriodUsage = $prevusage;
            }

            foreach ($collection as $projectEntity) {
                /* @var $projectEntity \Scalr\Stats\CostAnalytics\Entity\ProjectEntity */
                $totalCost = round((isset($usage['data'][$projectEntity->projectId]) ?
                    $usage['data'][$projectEntity->projectId]['cost'] : 0), 2);

                //Archived projects are excluded only when there aren't any usage for this month and
                //query filter key has not been provided.
                if (($query === null || $query === '') && $projectEntity->archived && $totalCost < 0.01 && !$showArchived) {
                    continue;
                }

                $projects[$projectEntity->projectId] = $this->getProjectData($projectEntity);

                $prevCost      = round((isset($prevusage['data'][$projectEntity->projectId]) ?
                    $prevusage['data'][$projectEntity->projectId]['cost'] : 0), 2);

                $prevWholeCost = round((isset($prevWholePeriodUsage['data'][$projectEntity->projectId]) ?
                    $prevWholePeriodUsage['data'][$projectEntity->projectId]['cost'] : 0), 2);

                $projects[$projectEntity->projectId] = $this->getWrappedUsageData([
                        'projectId'      => $projectEntity->projectId,
                        'iterator'       => $iterator,
                        'usage'          => $totalCost,
                        'prevusage'      => $prevCost,
                        'prevusagewhole' => $prevWholeCost,
                    ]) + $projects[$projectEntity->projectId];

            }
        }

        return array_values($projects);
    }

    /**
     * Gets project properties and parameters
     *
     * @param   ProjectEntity    $projectEntity          Project entity
     * @param   string           $calculate   optional Whether response should be adjusted with cost usage data
     * @return  array Returns cost centre properties and parameters
     */
    private function getProjectData(ProjectEntity $projectEntity, $calculate = false)
    {
        $ret = array(
            'ccId'           => $projectEntity->ccId,
            'ccName'         => $projectEntity->getCostCenter() !== null ? $projectEntity->getCostCenter()->name : null,
            'projectId'      => $projectEntity->projectId,
            'name'           => $projectEntity->name,
            'billingCode'    => $projectEntity->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE),
            'description'    => $projectEntity->getProperty(ProjectPropertyEntity::NAME_DESCRIPTION),
            'leadEmail'      => $projectEntity->getProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL),
            'created'        => $projectEntity->created->format('Y-m-d'),
            'createdByEmail' => $projectEntity->createdByEmail,
            'archived'       => $projectEntity->archived,
            'shared'         => $projectEntity->shared,
            'farmsCount'     => count($projectEntity->getFarmsList()),
        );

        if (!empty($projectEntity->accountId) && $projectEntity->shared === ProjectEntity::SHARED_WITHIN_ACCOUNT) {
            $ret['accountId'] = $projectEntity->accountId;
            $ret['accountName'] = Scalr_Account::init()->loadById($projectEntity->accountId)->name;
        } elseif (!empty($projectEntity->envId) && $projectEntity->shared === ProjectEntity::SHARED_WITHIN_ENV) {
            $ret['accountId'] = $projectEntity->accountId;
            $ret['accountName'] = Scalr_Account::init()->loadById($projectEntity->accountId)->name;
            $ret['envId'] = $projectEntity->envId;
            $ret['envName'] = Scalr_Environment::init()->loadById($projectEntity->envId)->name;
        }

        if ($calculate) {
            $iterator = ChartPeriodIterator::create('month', gmdate('Y-m-01'), null, 'UTC');

            $usage = $this->getContainer()->analytics->usage->get(
                ['projectId' => $ret['projectId']], $iterator->getStart(), $iterator->getEnd()
            );

            //It calculates usage for previous period same days
            $prevusage = $this->getContainer()->analytics->usage->get(
                ['projectId' => $ret['projectId']], $iterator->getPreviousStart(), $iterator->getPreviousEnd()
            );

            //Calclulates usage for previous whole period
            if ($iterator->getPreviousEnd() != $iterator->getWholePreviousPeriodEnd()) {
                $prevWholePeriodUsage = $this->getContainer()->analytics->usage->get(
                    ['projectId' => $ret['projectId']], $iterator->getPreviousStart(), $iterator->getWholePreviousPeriodEnd()
                );
            } else {
                $prevWholePeriodUsage = $prevusage;
            }

            $ret = $this->getWrappedUsageData([
                    'ccId'           => $ret['ccId'],
                    'projectId'      => $ret['projectId'],
                    'iterator'       => $iterator,
                    'usage'          => $usage['cost'],
                    'prevusage'      => $prevusage['cost'],
                    'prevusagewhole' => $prevWholePeriodUsage['cost'],
                ]) + $ret;
        }

        return $ret;
    }

    /**
     * xGetPeriodDataAction
     *
     * @param   string   $projectId The identifier of the project (UUID)
     * @param   string   $mode      Mode (week, month, quarter, year, custom)
     * @param   string   $startDate Start date in UTC (Y-m-d)
     * @param   string   $endDate   End date in UTC (Y-m-d)
     */
    public function xGetPeriodDataAction($projectId, $mode, $startDate, $endDate)
    {
        $this->response->data($this->getContainer()->analytics->usage->getProjectPeriodData($projectId, $mode, $startDate, $endDate));
    }

    /**
     * xGetMovingAverageToDateAction
     *
     * @param   string      $projectId    The identifier of the project
     * @param   string      $mode         The mode
     * @param   string      $date         The UTC date within period ('Y-m-d H:00')
     * @param   string      $startDate    The start date of the period in UTC ('Y-m-d')
     * @param   string      $endDate      The end date of the period in UTC ('Y-m-d')
     * @param   string      $ccId         optional The identifier of the cost center (It is used only when project is null)
     */
    public function xGetMovingAverageToDateAction($projectId, $mode, $date, $startDate, $endDate, $ccId = null)
    {
        $this->response->data($this->getContainer()->analytics->usage->getProjectMovingAverageToDate(
            $projectId, $mode, $date, $startDate, $endDate, $ccId
        ));
    }

    /**
     * xGetProjectFarmsTopUsageOnDateAction
     *
     * @param   string|null $projectId    The identifier of the project
     * @param   string      $platform     The cloud platform
     * @param   string      $mode         The mode
     * @param   string      $date         The UTC date within period ('Y-m-d H:00')
     * @param   string      $start        The start date of the period in UTC ('Y-m-d')
     * @param   string      $end          The end date of the period in UTC ('Y-m-d')
     * @param   string      $ccId         optional The identifier of the cost center (It is used only when project is null)
     */
    public function xGetProjectFarmsTopUsageOnDateAction($projectId, $platform, $mode, $date, $start, $end, $ccId = null)
    {
        $this->response->data($this->getContainer()->analytics->usage->getProjectFarmsTopUsageOnDate(
            $projectId, $platform, $mode, $date, $start, $end, $ccId
        ));
    }

    /**
     * Gets data for dropdown shared type fields on project edbit page
     *
     * @param array $values Array of optional params for widget
     * @return array
     */
    private function getWidget(array $values)
    {
        $values['accounts'] = $this->getWidgetAccounts();

        return $values;
    }

    /**
     * Gets list of accounts for widget
     *
     * @return array
     */
    private function getWidgetAccounts()
    {
        $accounts = $this->db->GetAll('SELECT id, name FROM clients WHERE status = ? ORDER BY name', \Scalr_Account::STATUS_ACTIVE);

        return $accounts;
    }

    /**
     * xGetProjectWidgetAccountsAction
     *
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xGetProjectWidgetAccountsAction()
    {
        $this->response->data(array(
            'accounts' => $this->getWidgetAccounts()
        ));
    }

}