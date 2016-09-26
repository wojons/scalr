<?php

use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Quarters;
use Scalr\Stats\CostAnalytics\Entity\QuarterlyBudgetEntity;
use Scalr\Stats\CostAnalytics\QuarterPeriod;

class Scalr_UI_Controller_Account2_Analytics_Budgets extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    public function hasAccess()
    {
        return $this->request->isAllowed(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT, Acl::PERM_ANALYTICS_PROJECTS_ALLOCATE_BUDGET);
    }

    public function defaultAction()
    {
        $days = SettingEntity::getQuarters(true);

        $quarters = new Quarters($days);

        $period = $quarters->getPeriodForDate();

        $this->response->page('ui/account2/analytics/budgets/view.js', [
            'quarters' => $days,
            'quartersConfirmed' => SettingEntity::getValue(SettingEntity::ID_QUARTERS_DAYS_CONFIRMED),
            'fiscalYear' => $period->year
        ], ['ui/analytics/analytics.js'], ['ui/analytics/analytics.css', '/ui/admin/analytics/admin.css', '/ui/admin/analytics/budgets/budgets.css']);
    }

    /**
     * xGetBudgetInfoAction
     *
     * @param    int      $year       The year
     * @param    string   $ccId       optional The identifier of the cost centre or parent cost centre
     * @param    string   $projectId  optional The identifier of the project
     * @throws   InvalidArgumentException
     */
    public function xGetBudgetInfoAction($year, $ccId = null, $projectId = null)
    {
        $this->response->data($this->getBudgetInfo($year, $ccId, $projectId));
    }

    /**
     * Gets budgeted cost for all quarters of specified year
     *
     * @param    int      $year       The year
     * @param    string   $ccId       optional The identifier of the cost centre or parent cost centre
     * @param    string   $projectId  optional The identifier of the project
     * @throws   InvalidArgumentException
     */
    protected function getBudgetInfo($year, $ccId = null, $projectId = null)
    {
        if (!empty($projectId)) {
            $subjectId = $projectId;
            $subjectType = QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT;
            $subjectEntity = ProjectEntity::findPk($projectId);
        } else if (!empty($ccId)) {
            $subjectId = $ccId;
            $subjectType = QuarterlyBudgetEntity::SUBJECT_TYPE_CC;
            $subjectEntity = CostCentreEntity::findPk($ccId);
        }

        if ($subjectEntity->accountId !== $this->user->getAccountId()) {
            //throw new Scalr_Exception_InsufficientPermissions();
        }

        if (empty($subjectId) || !preg_match('/^[[:xdigit:]-]{36}$/', $subjectId)) {
            throw new InvalidArgumentException(sprintf("Invalid identifier of the project or cost center."));
        }

        if (!preg_match('/^\d{4}$/', $year)) {
            throw new InvalidArgumentException(sprintf("Invalid year."));
        }

        if ($subjectType == QuarterlyBudgetEntity::SUBJECT_TYPE_CC) {
            $collection = QuarterlyBudgetEntity::getCcBudget($year, $subjectId);
            $prevCollection = QuarterlyBudgetEntity::getCcBudget($year - 1, $subjectId);
        } else {
            $collection = QuarterlyBudgetEntity::getProjectBudget($year, $subjectId);
            $prevCollection = QuarterlyBudgetEntity::getProjectBudget($year - 1, $subjectId);
        }

        $quarters = new Quarters(SettingEntity::getQuarters(true));

        $today = new DateTime('now', new DateTimeZone('UTC'));

        //Start dates for an each quarter
        $startDates = $quarters->getDays();

        $budgets = [];

        for ($quarter = 1; $quarter <= 4; ++$quarter) {
            $period = $quarters->getPeriodForQuarter($quarter, $year);
            $prevPeriod = $quarters->getPeriodForQuarter($quarter, $year - 1);

            //Finds budget for specified quarter in the collection
            $entity = current($collection->filterByQuarter($quarter));
            //Previous Year entity
            $prevEntity = current($prevCollection->filterByQuarter($quarter));

            if ($entity instanceof QuarterlyBudgetEntity) {
                $budget = [
                    'budget'           => round($entity->budget),
                    'budgetFinalSpent' => round($entity->cumulativespend),
                    'spentondate'      => $entity->spentondate instanceof DateTime ? $entity->spentondate->format('Y-m-d') : null,
                    'budgetSpent'      => round($entity->cumulativespend),
                ];
                if ($budget['budget']) {
                    $budget['budgetOverspend'] = max(round($entity->cumulativespend - $entity->budget), 0);
                    $budget['budgetOverspendPct'] = round($budget['budgetOverspend'] / $budget['budget'] * 100);
                }
            } else {
                //Budget has not been set yet.
                $budget = [
                    'budget'             => 0,
                    'budgetFinalSpent'   => 0,
                    'spentondate'        => null,
                    'budgetSpent'        => 0,
                    'budgetOverspend'    => 0,
                    'budgetOverspendPct' => 0,
                ];
            }

            $budget['year'] = $year;
            $budget['quarter'] = $quarter;
            $budget['startDate'] = $startDates[$quarter - 1];

            //Whether this quarter has been closed or not
            $budget['closed'] = $period->end->format('Y-m-d') < gmdate('Y-m-d');

            //The number of the days in the current quarter
            $daysInQuarter = $period->start->diff($period->end, true)->days + 1;

            //In case quarter is closed projection should be calculated
            if (!$budget['closed']) {
                $daysPassed = $period->start->diff($today, true)->days + 1;

                $budget['dailyAverage'] = $daysPassed == 0 ? 0 : round($budget['budgetSpent'] / $daysPassed, 2);

                $budget['projection'] = round($daysInQuarter * $budget['dailyAverage']);
            } else {
                $budget['dailyAverage'] = $daysInQuarter == 0 ? 0 : round($budget['budgetFinalSpent'] / $daysInQuarter, 2);
            }

            $budget['costVariance'] = round((isset($budget['projection']) ? $budget['projection'] : $budget['budgetSpent']) - $budget['budget'], 2);
            $budget['costVariancePct'] = $budget['budget'] == 0 ? null : round(abs($budget['costVariance']) / $budget['budget'] * 100);

            $budget['monthlyAverage'] = round($budget['dailyAverage'] * 30);

            if ($prevEntity instanceof QuarterlyBudgetEntity) {
                $budget['prev'] = [
                    'budget'          => $prevEntity->budget,
                    'budgetFinalSpent'=> $prevEntity->cumulativespend,
                    'spentondate'     => $prevEntity->spentondate instanceof DateTime ? $prevEntity->spentondate->format('Y-m-d') : null,
                    'closed'          => $prevPeriod->end->format('Y-m-d') < gmdate('Y-m-d'),
                    'costVariance'    => $prevEntity->cumulativespend - $prevEntity->budget,
                    'costVariancePct' => $prevEntity->budget == 0 ? null :
                                         round(abs($prevEntity->cumulativespend - $prevEntity->budget) / $prevEntity->budget * 100),
                ];
            } else {
                $budget['prev'] = [
                    'budget'          => null,
                    'budgetFinalSpent'=> 0,
                    'spentondate'     => null,
                    'closed'          => $prevPeriod->end->format('Y-m-d') < gmdate('Y-m-d'),
                    'budgetSpent'     => 0,
                    'costVariance'    => 0,
                    'costVariancePct' => 0,
                ];
            }

            $budgets[] = $budget;
        }

        $result = [
            'budgets'   => $budgets,
            'ccId'      => $ccId,
            'projectId' => $projectId,
            'quarter'   => $quarters->getQuarterForDate(),
            'year'      => $year,
        ];
        if (!empty($projectId)) {
            $result['shared'] = $subjectEntity->shared;
        }
        return $result;
    }

    /**
     * xListAction
     *
     * @param string        $ccId      optional Identifier of the cost center
     * @param string        $quarter   optional Current Quarter number
     * @param int           $year      optional Current year
     * @param string        $query     optional Search query
     */
    public function xListAction($ccId = null, $quarter = null, $year = null, $query = null)
    {
        $quarters = new Quarters(SettingEntity::getQuarters(true));

        $period = $quarters->getPeriodForDate();

        if (!$quarter) {
            $quarter = $period->quarter;
        }

        if (!$year) {
            $year = $period->year;
        }

        if ($quarter !== 'year') {
            $period = $quarters->getPeriodForQuarter($quarter, $year);
        } else {
            $period = $quarters->getPeriodForYear($year);
        }

        $this->response->data(array(
            'quarter'   => $period->quarter,
            'year'      => $period->year,
            'startDate' => $period->start->format('Y-m-d'),
            'endDate'   => $period->end->format('Y-m-d'),
            'nodes'     => $this->getNodesList($period, $ccId, trim($query))
        ));
    }

    /**
     * Gets the list of the cost centres
     *
     * @return   array Returns the list of the cost centres
     */
    private function getNodesList($period, $ccId = null, $query = null)
    {
        $nodes = [];

        $projectItems = $this->getContainer()->analytics->projects->findByKey($query, ['accountId' => $this->user->getAccountId()], true);

        foreach ($projectItems as $item) {
            /* @var $item ProjectEntity */
            if (!isset($nodes[$item->ccId])) {
                $nodes[$item->ccId] = $this->getCostCenterData($this->getContainer()->analytics->ccs->get($item->ccId), $period);
                $nodes[$item->ccId]['nodes'] = [];
            }

            $nodes[$item->ccId]['nodes'][] = $this->getProjectData($item, $period);
        }

        $ccsItems = $this->getContainer()->analytics->ccs->findByKey($query, ['accountId' => $this->user->getAccountId()], true);

        foreach ($ccsItems as $item) {
            /* @var $item CostCentreEntity */
            if (!isset($nodes[$item->ccId])) {
                $nodes[$item->ccId] = $this->getCostCenterData($item, $period);
                $nodes[$item->ccId]['nodes'] = [];

                foreach (ProjectEntity::findByCcId($item->ccId) as $projectItem) {
                    /* @var $projectItem ProjectEntity */
                    if ($projectItem->shared != ProjectEntity::SHARED_WITHIN_ACCOUNT ||
                        $projectItem->accountId != $this->user->getAccountId()) {
                        continue;
                    }

                    $nodes[$item->ccId]['nodes'][] = $this->getProjectData($projectItem, $period);
                }
            }
        }

        return array_values($nodes);
    }

    /**
     * Gets budget data for specified CC and period
     *
     * @param   CostCentreEntity $cc
     * @param   QuarterPeriod    $period
     * @return  array Returns budget data
     */
    private function getCostCenterData(CostCentreEntity $cc, QuarterPeriod $period)
    {
        $ret = array(
            'ccId'          => $cc->ccId,
            'name'          => $cc->name,
            'billingCode'   => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE),
            'description'   => $cc->getProperty(CostCentrePropertyEntity::NAME_DESCRIPTION),
        );

        $budget = $this->getBudgetUsedPercentage(['ccId' => $cc->ccId, 'period' => $period, 'getRelationDependentBudget' => true]);

        foreach (['budget', 'budgetRemain', 'budgetRemainPct', 'budgetSpent', 'budgetSpentPct',
                  'budgetOverspend', 'budgetOverspendPct', 'relationDependentBudget'] as $field) {
            $ret[$field] = $budget[$field];
        }

        return $ret;
    }

    /**
     * Gets budget data for specified Project and period
     *
     * @param   ProjectEntity $projectEntity                  Project entity
     * @param   QuarterPeriod $period                         Period object
     * @param   bool          $includeRelationDependentBudget optional Should we include relation dependent budget to response
     * @return  array Returns budget data
     */
    private function getProjectData(ProjectEntity $projectEntity, QuarterPeriod $period, $includeRelationDependentBudget = false)
    {
        $ret = array(
            'ccId'         => $projectEntity->ccId,
            'projectId'    => $projectEntity->projectId,
            'name'         => $projectEntity->name,
            'billingCode'  => $projectEntity->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE),
            'description'  => $projectEntity->getProperty(ProjectPropertyEntity::NAME_DESCRIPTION),
            'leaf'         => true
        );

        $budget = $this->getBudgetUsedPercentage(['projectId' => $ret['projectId'], 'ccId' => $ret['ccId'], 'period' => $period, 'getRelationDependentBudget' => $includeRelationDependentBudget] );

        foreach (['budget', 'budgetRemain', 'budgetRemainPct', 'budgetSpent', 'budgetSpentPct',
                  'budgetOverspend', 'budgetOverspendPct'] as $field) {
            $ret[$field] = $budget[$field];
        }

        if ($includeRelationDependentBudget) {
            $ret['relationDependentBudget'] = $budget['relationDependentBudget'];
        }

        return $ret;
    }


    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'projectId'      => ['type' => 'string'],
            'year'           => ['type' => 'int'],
            'quarters'       => ['type' => 'json'],
            'selectedQuarter'=> ['type' => 'string'],
        ));

        $year = $this->getParam('year');

        $selectedQuarter = $this->getParam('selectedQuarter');

        if ($selectedQuarter !== 'year' && ($selectedQuarter < 1 || $selectedQuarter > 4)) {
            throw new OutOfBoundsException(sprintf("Invalid selectedQuarter number."));
        }

        $quarterReq = [];
        foreach ($this->getParam('quarters') as $q) {
            if (!isset($q['quarter'])) {
                throw new InvalidArgumentException(sprintf("Missing quarter property for quarters data set in the request."));
            }

            if ($q['quarter'] < 1 || $q['quarter'] > 4) {
                throw new OutOfRangeException(sprintf("Quarter value should be between 1 and 4."));
            }

            if (!isset($q['budget'])) {
                throw new InvalidArgumentException(sprintf("Missing budget property for quarters data set in the request."));
            }

            $quarterReq[$q['quarter']] = $q;
        }

        if ($this->getParam('projectId')) {
            $subjectType = QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT;
            $subjectId = $this->getParam('projectId');
            $subjectEntity = ProjectEntity::findPk($subjectId);
            /* @var $subjectEntity ProjectEntity */
            if ($subjectEntity->accountId != $this->user->getAccountId() || $subjectEntity->shared != ProjectEntity::SHARED_WITHIN_ACCOUNT) {
                throw new Scalr_Exception_InsufficientPermissions();
            }
        } else {
            throw new InvalidArgumentException(sprintf('ProjectId must be provided with the request.'));
        }

        if (!preg_match("/^[[:xdigit:]-]{36}$/", $subjectId)) {
            throw new InvalidArgumentException(sprintf("Invalid UUID has been passed."));
        }

        if (!preg_match('/^\d{4}$/', $year)) {
            throw new InvalidArgumentException(sprintf("Invalid year has been passed."));
        }

        //Fetches the previous state of the entities from database
        $collection = QuarterlyBudgetEntity::getProjectBudget($year, $subjectId);

        $quarters = new Quarters(SettingEntity::getQuarters(true));

        //Updates|creates entities
        for ($quarter = 1; $quarter <= 4; ++$quarter) {
            if (!isset($quarterReq[$quarter])) continue;

            $period = $quarters->getPeriodForQuarter($quarter, $year);
            //Checks if period has already been closed and forbids update
            if ($period->end->format('Y-m-d') < gmdate('Y-m-d'))
                continue;

            $entity = current($collection->filterByQuarter($quarter));

            if ($entity instanceof QuarterlyBudgetEntity) {
                //We should update an entity
                $entity->budget = abs((float) $quarterReq[$quarter]['budget']);
            } else {
                //We should create a new one.
                $entity = new QuarterlyBudgetEntity($year, $quarter);
                $entity->subjectType = $subjectType;
                $entity->subjectId = $subjectId;
                $entity->budget = abs((float) $quarterReq[$quarter]['budget']);
            }

            $entity->save();
        }

        if ($selectedQuarter == 'year') {
            $selectedPeriod = $quarters->getPeriodForYear($year);
        } else {
            $selectedPeriod = $quarters->getPeriodForQuarter($selectedQuarter, $year);
        }

        $data = $this->getProjectData(ProjectEntity::findPk($subjectId), $selectedPeriod, true);
        $budgetInfo = $this->getBudgetInfo($year, $data['ccId'], $data['projectId']);

        $this->response->data(['data' => $data, 'budgetInfo' => $budgetInfo]);
        $this->response->success('Budget changes have been saved');
    }
}
