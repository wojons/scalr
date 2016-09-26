<?php

use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Forecast;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Exception\AnalyticsException;
use Scalr\Model\Entity;
use Scalr\UI\Controller\Account2\Analytics\NotificationTrait;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;

class Scalr_UI_Controller_Account2_Analytics_Projects extends \Scalr_UI_Controller
{
    use Forecast, NotificationTrait;

    /**
     * {@inheritdoc}
     * @see \Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess();
    }

    /**
     * Default action
     */
    public function defaultAction()
    {
        $this->response->page(
            'ui/account2/analytics/projects/view.js',
            [
                'quarters' => SettingEntity::getQuarters(true),
                'projects' => $this->getProjectsList(),
            ],
            ['ui/analytics/analytics.js'],
            ['ui/analytics/analytics.css', 'ui/admin/analytics/admin.css']
        );
    }

    /**
     * List projects action
     *
     * @param string $query optional Search query
     */
    public function xListAction($query = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT);

        $this->response->data(array(
            'projects' => $this->getProjectsList(trim($query))
        ));
    }

    public function addAction($projectId = null)
    {
        $this->editAction($projectId);
    }

    /**
     * Edit project action
     *
     * @param string $projectId
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function editAction($projectId = null)
    {
        $scope = $this->request->getScope();

        $ccs = [];

        if (!empty($projectId)) {
            $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT, Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_UPDATE);

            $project = $this->getContainer()->analytics->projects->get($projectId);

            if ($project->shared != ProjectEntity::SHARED_WITHIN_ACCOUNT || $project->accountId != $this->user->getAccountId()) {
                throw new Scalr_Exception_InsufficientPermissions();
            }
            $cc = $project->getCostCenter();
            $projectData = $this->getProjectData($project, true);

            $currentCc = CostCentreEntity::findPk($projectData['ccId']);
            /* @var $currentCc CostCentreEntity */
            if ($currentCc) {
                $ccs[$currentCc->ccId] = [
                    'ccId'        => $currentCc->ccId,
                    'name'        => $currentCc->name,
                    'billingCode' => $currentCc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)
                ];
            }
            //Check whether it can be removed
            try {
                $projectData['removable'] = $project->checkRemoval();
            } catch (AnalyticsException $e) {
                $projectData['removable'] = false;
                $projectData['warning'] = $e->getMessage();
            }

        } else {
            $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT, Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_CREATE);

            if ($scope == 'environment') {
                $cc = $this->getContainer()->analytics->ccs->get($this->getEnvironment()->getPlatformConfigValue(\Scalr_Environment::SETTING_CC_ID));
            }

            $projectData = [];
        }

        if ($scope == 'environment') {
            $accountCcs = AccountCostCenterEntity::findOne([['accountId' => $this->user->getAccountId()], ['ccId' => $cc->ccId]]);

            if (($accountCcs instanceof AccountCostCenterEntity) && empty($ccs[$cc->ccId])) {
                $ccs[$cc->ccId] = [
                    'ccId' => $cc->ccId,
                    'name' => $cc->name,
                    'billingCode' => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)
                ];
            }
        } elseif ($scope == 'account') {
            foreach ($this->user->getEnvironments() as $row) {
                $env = \Scalr_Environment::init()->loadById($row['id']);
                $ccEntity = CostCentreEntity::findPk($env->getPlatformConfigValue(\Scalr_Environment::SETTING_CC_ID));
                /* @var $ccEntity \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
                if ($ccEntity) {
                    $accountCcs = AccountCostCenterEntity::findOne([['accountId' => $env->clientId], ['ccId' => $ccEntity->ccId]]);

                    if (($accountCcs instanceof AccountCostCenterEntity) && empty($ccs[$ccEntity->ccId])) {
                        $ccs[$ccEntity->ccId] = [
                            'ccId' => $ccEntity->ccId,
                            'name' => $ccEntity->name,
                            'billingCode' => $ccEntity->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE)
                        ];
                    }
                }
            }
        }

        $this->response->page('ui/admin/analytics/projects/edit.js', array(
            'project'      => $projectData,
            'ccs'          => $ccs,
            'scope'        => 'account'
        ));
    }

    /**
     * xSaveAction
     *
     * @param string $projectId
     * @param string $name
     * @param string $description
     * @param string $billingCode
     * @param string $leadEmail
     * @param string $ccId optional
     * @throws \InvalidArgumentException
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xSaveAction($projectId, $name, $description, $billingCode, $leadEmail, $ccId = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT);

        $validator = new Validator();
        $validator->validate($name, 'name', Validator::NOEMPTY);

        if (!$validator->isValid($this->response))
            return;

        if ($projectId) {
            $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT, Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_UPDATE);

            $project = $this->getContainer()->analytics->projects->get($projectId);

            if (!$project) {
                throw new Scalr_UI_Exception_NotFound();
            } elseif ($project->shared != ProjectEntity::SHARED_WITHIN_ACCOUNT || $project->accountId != $this->user->getAccountId()) {
                throw new Scalr_Exception_InsufficientPermissions();
            }
        } else {
            $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT, Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_CREATE);

            $project = new ProjectEntity();
            if ($this->request->getScope() == 'environment') {
                $project->ccId = $this->getEnvironment()->getPlatformConfigValue(\Scalr_Environment::SETTING_CC_ID);
            } else {
                $project->ccId = $ccId;
            }

            $cc = $this->getContainer()->analytics->ccs->get($project->ccId);

            if (!empty($cc)) {
                if ($cc->getProperty(CostCentrePropertyEntity::NAME_LOCKED) == 1) {
                    throw new Scalr_Exception_InsufficientPermissions();
                }

                $email = $cc->getProperty(CostCentrePropertyEntity::NAME_LEAD_EMAIL);
                $emailData = [
                    'projectName' => $name,
                    'ccName'      => $cc->name
                ];

            } else {
                throw new Scalr_UI_Exception_NotFound();
            }

            $project->shared = ProjectEntity::SHARED_WITHIN_ACCOUNT;
            $project->accountId = $this->user->getAccountId();
            $project->createdById = $this->user->id;
            $project->createdByEmail = $this->user->getEmail();
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

        if (!$projectId && !empty($email)) {
            \Scalr::getContainer()->mailer->sendTemplate(SCALR_TEMPLATES_PATH . '/emails/analytics_on_project_add.eml.php', $emailData, $email);
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
        $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT, Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_DELETE);

        $project = $this->getContainer()->analytics->projects->get($projectId);
        if ($project) {
            if ($project->shared != ProjectEntity::SHARED_WITHIN_ACCOUNT || $project->accountId != $this->user->getAccountId()) {
                throw new Scalr_Exception_InsufficientPermissions();
            }

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
        $this->response->success('Project successfully ' . ($removable ? 'removed' : 'archived'));
    }

    /**
     * xGetPeriodProjectDataAction
     *
     * @param   string    $projectId The identifier of the project
     * @param   string    $mode      The mode (week, month, quarter, year)
     * @param   string    $startDate The start date of the period in UTC ('Y-m-d')
     * @param   string    $endDate   The end date of the period in UTC ('Y-m-d')
     */
    public function xGetPeriodDataAction($projectId, $mode, $startDate, $endDate)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT);

        $filter = ['accountId' => $this->user->getAccountId()];
        $this->response->data($this->getContainer()->analytics->usage->getProjectPeriodData($projectId, $mode, $startDate, $endDate, $filter));
    }

    /**
     * @param string $projectId
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function notificationsAction($projectId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT);

        $project = ProjectEntity::findPk($projectId);
        /* @var $project ProjectEntity */
        if (!$project->hasAccessPermissions($this->getUser())) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->response->page('ui/admin/analytics/projects/notifications.js', [
            'notifications.projects' => NotificationEntity::find([['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT],['subjectId' => $projectId]])->getArrayCopy(),
            'reports'                => ReportEntity::find([['subjectType' => ReportEntity::SUBJECT_TYPE_PROJECT],['subjectId' => $projectId]])->getArrayCopy(),
        ], [], ['ui/admin/analytics/notifications/view.css']);
    }

    /**
     * @param string $projectId
     * @param JsonData $notifications
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xSaveNotificationsAction($projectId, JsonData $notifications)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT);

        $project = ProjectEntity::findPk($projectId);
        /* @var $project ProjectEntity */
        if (!$project->hasAccessPermissions($this->getUser())) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $data = [];

        foreach ($notifications as $id => $settings) {
            if ($id == 'reports') {
                $this->saveReports($settings, $projectId);
                $data[$id] = ReportEntity::find([['subjectType' => ReportEntity::SUBJECT_TYPE_PROJECT],['subjectId' => $projectId]])->getArrayCopy();
            } elseif ($id == 'notifications.projects') {
                $this->saveNotifications(NotificationEntity::SUBJECT_TYPE_PROJECT, $settings, $projectId);
                $data[$id] = NotificationEntity::find([['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT],['subjectId' => $projectId]])->getArrayCopy();
            }
        }

        $this->response->data($data);
        $this->response->success('Notifications successfully saved');
    }

    /**
     * Gets a list of projects by key
     *
     * @param string $query Search query
     * @return array Returns array of projects
     */
    private function getProjectsList($query = null)
    {
        $projects = [];

        $collection = $this->getContainer()->analytics->projects->getAccountProjects($this->user->getAccountId(), $query);

        if ($collection->count()) {
            $iterator = ChartPeriodIterator::create('month', gmdate('Y-m-01'), null, 'UTC');

            //It calculates usage for all provided cost centres
            $usage = $this->getContainer()->analytics->usage->getFarmData(
                $this->user->getAccountId(), [], $iterator->getStart(), $iterator->getEnd(),
                [TagEntity::TAG_ID_PROJECT]
            );

            //It calculates usage for previous period same days
            $prevusage = $this->getContainer()->analytics->usage->getFarmData(
                $this->user->getAccountId(), [], $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
                [TagEntity::TAG_ID_PROJECT]
            );

            foreach ($collection as $projectEntity) {
                /* @var $projectEntity \Scalr\Stats\CostAnalytics\Entity\ProjectEntity */
                $totalCost = round((isset($usage['data'][$projectEntity->projectId]) ?
                             $usage['data'][$projectEntity->projectId]['cost'] : 0), 2);

                //Archived projects are excluded only when there aren't any usage for this month and
                //query filter key has not been provided.
                if (($query === null || $query === '') && $projectEntity->archived && $totalCost < 0.01) {
                    continue;
                }

                $projects[$projectEntity->projectId] = $this->getProjectData($projectEntity);

                $prevCost = round((isset($prevusage['data'][$projectEntity->projectId]) ?
                            $prevusage['data'][$projectEntity->projectId]['cost'] : 0), 2);

                $projects[$projectEntity->projectId] = $this->getWrappedUsageData([
                    'projectId'      => $projectEntity->projectId,
                    'iterator'       => $iterator,
                    'usage'          => $totalCost,
                    'prevusage'      => $prevCost,
                ]) + $projects[$projectEntity->projectId];

            }
        }

        return array_values($projects);
    }

    /**
     * Gets project properties and parameters
     *
     * @param   ProjectEntity    $projectEntity          Project entity
     * @return  array Returns cost centre properties and parameters
     */
    private function getProjectData(ProjectEntity $projectEntity)
    {
        $ret = array(
            'projectId'      => $projectEntity->projectId,
            'name'           => $projectEntity->name,
            'ccId'           => $projectEntity->ccId,
            'ccName'         => $projectEntity->getCostCenter() !== null ? $projectEntity->getCostCenter()->name : null,
            'billingCode'    => $projectEntity->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE),
            'description'    => $projectEntity->getProperty(ProjectPropertyEntity::NAME_DESCRIPTION),
            'leadEmail'      => $projectEntity->getProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL),
            'created'        => $projectEntity->created->format('Y-m-d'),
            'createdByEmail' => $projectEntity->createdByEmail,
            'archived'       => $projectEntity->archived,
            'shared'         => $projectEntity->shared,
        );

        if (!empty($projectEntity->accountId) && $projectEntity->shared === ProjectEntity::SHARED_WITHIN_ACCOUNT) {
            $ret['accountId'] = $projectEntity->accountId;
            $ret['accountName'] = Scalr_Account::init()->loadById($projectEntity->accountId)->name;
        } elseif (!empty($projectEntity->envId) && $projectEntity->shared === ProjectEntity::SHARED_WITHIN_ENV) {
            $ret['accountId'] = $projectEntity->accountId;
            $ret['accountName'] = Scalr_Account::init()->loadById($projectEntity->accountId)->name;
            $ret['envId'] = $projectEntity->envId;
            $ret['envName'] = \Scalr_Environment::init()->loadById($projectEntity->envId)->name;
        }

        return $ret;
    }
}
