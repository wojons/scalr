<?php

use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Exception\AnalyticsException;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;

class Scalr_UI_Controller_Analytics_Projects extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    public function hasAccess()
    {
        return true;
    }

    public function defaultAction()
    {
        if (!$this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->response->page('ui/analytics/projects/view.js', array(
            'projects' => $this->getProjectsList(),
            'quarters' => SettingEntity::getQuarters(true)
        ),array('/ui/analytics/analytics.js'), array('/ui/analytics/analytics.css'));
    }

    public function xListAction()
    {
        $query = trim($this->getParam('query'));

        if (!$this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->response->data(array(
            'projects' => $this->getProjectsList($query)
        ));
    }

    public function addAction()
    {
        $this->editAction();
    }

    public function editAction()
    {
        $ccs = array();
        if ($this->user->isAdmin()) {
            $collection = $this->getContainer()->analytics->ccs->all();

            if ($this->getParam('projectId')) {
                $project = $this->getContainer()->analytics->projects->get($this->getParam('projectId'));
                if (!$project)
                    throw new Scalr_UI_Exception_NotFound();
            }

            foreach ($collection as $cc) {
                /* @var $cc \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
                //For new projects we should exclude archived cost centres
                if ($cc->archived) {
                    continue;
                }

                $ccs[] = array(
                    'ccId' => $cc->ccId,
                    'name' => $cc->name,
                );
            }
        } else {
            $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS);
            $cc = $this->getContainer()->analytics->ccs->get($this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID));
            $ccs[] = array(
                'ccId' => $cc->ccId,
                'name' => $cc->name,
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

        $this->response->page('ui/analytics/projects/edit.js', array(
            'project' => $projectData,
            'ccs'     => $ccs
        ), array('/ui/analytics/analytics.js'));
    }

    public function xSaveAction()
    {
        if (!$this->user->isAdmin() && !$this->request->isAllowed(Acl::RESOURCE_ANALYTICS_PROJECTS))
            throw new Scalr_Exception_InsufficientPermissions();


        $this->request->defineParams(array(
            'name'        => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true)),
            'billingCode' => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true, Scalr_Validator::ALPHANUM => true)),
            'leadEmail'   => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true, Scalr_Validator::EMAIL => true)),
            'shared'      => array('type' => 'int')
        ));

        if ($this->user->isAdmin()) {
            if ($this->getParam('projectId')) {
                $project = $this->getContainer()->analytics->projects->get($this->getParam('projectId'));

                if (!$project) {
                    throw new Scalr_UI_Exception_NotFound();
                }
            } else {
                $project = new ProjectEntity();
            }

            $cc = $this->getContainer()->analytics->ccs->get($this->getParam('ccId'));
        } else {
            $this->request->restrictAccess(Acl::RESOURCE_ANALYTICS_PROJECTS);

            $project = new ProjectEntity();

            $cc = $this->getContainer()->analytics->ccs->get(
                $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)
            );

            $project->shared = $this->getParam('shared');

            $project->envId = $this->getEnvironment()->id;

            $project->accountId = $this->user->getAccountId();
        }

        $this->request->validate();

        if (!$cc) {
            $this->request->addValidationErrors('ccId', 'Cost center ID should be set');
        }

        if (!$this->request->isValid()) {
            $this->response->data($this->request->getValidationErrors());
            $this->response->failure();
            return;
        }

        //Checks whether billing code specified in the request is already used in another Project
        $criteria = [['name' => ProjectPropertyEntity::NAME_BILLING_CODE], ['value' => $this->getParam('billingCode')]];

        if ($project->projectId !== null) {
            $criteria[] = ['projectId' => ['$ne' => $project->projectId]];
        } else {
            //This is a new record.
            //Email and identifier of the user who creates this record must be set.
            $project->createdById = $this->user->id;

            $project->createdByEmail = $this->user->getEmail();
        }

        $project->name = $this->getParam('name');
        $project->ccId = $cc->ccId;

        $pp = new ProjectPropertyEntity();

        $record = $this->db->GetRow("
            SELECT " . $project->fields('p') . "
            FROM " . $project->table('p') . "
            JOIN " . $pp->table('pp') . " ON pp.project_id = p.project_id
            WHERE " . $pp->_buildQuery($criteria, 'AND', 'pp')['where'] . "
            LIMIT 1
        ");

        if ($record) {
            $found = new ProjectEntity();
            $found->load($record);
        }

        if (!empty($found)) {
            throw new AnalyticsException(sprintf(
                'Billing code "%s" is already used in the Project "%s"',
                strip_tags($this->getParam('billingCode')),
                $found->name
            ));
        }

        $this->db->BeginTrans();

        try {
            $project->save();

            $project->saveProperty(ProjectPropertyEntity::NAME_BILLING_CODE, $this->getParam('billingCode'));
            $project->saveProperty(ProjectPropertyEntity::NAME_DESCRIPTION, $this->getParam('description'));
            $project->saveProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL, $this->getParam('leadEmail'));

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();

            throw $e;
        }

        $this->response->data(['project' => $this->getProjectData($project)]);
        $this->response->success('Project has been successfully saved');
    }

    public function xRemoveAction()
    {
        if (!$this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $project = $this->getContainer()->analytics->projects->get($this->getParam('projectId'));

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

    private function getProjectsList($query = null)
    {
        $projects = [];

        $collection = $this->getContainer()->analytics->projects->findByKey($query);

        if ($collection->count()) {
            $iterator = new ChartPeriodIterator('month', gmdate('Y-m-01'), null, 'UTC');

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
                if (($query === null || $query === '') && $projectEntity->archived && $totalCost < 0.01) {
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
            'farmsCount'     => count($projectEntity->getFarmsList()),
        );

        if ($calculate) {
            $iterator = new ChartPeriodIterator('month', gmdate('Y-m-01'), null, 'UTC');

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
        if (!$this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

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
        if (!$this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

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
        if (!$this->user->isAdmin())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->response->data($this->getContainer()->analytics->usage->getProjectFarmsTopUsageOnDate(
            $projectId, $platform, $mode, $date, $start, $end, $ccId
        ));
    }
}
