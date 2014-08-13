<?php

use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Exception\AnalyticsException;

class Scalr_UI_Controller_Analytics_Costcenters extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        $this->response->page('ui/analytics/costcenters/view.js', array(
            'ccs' => $this->getCostCentersList(),
            'quarters' => SettingEntity::getQuarters(true)
        ), array('/ui/analytics/analytics.js'), array('/ui/analytics/analytics.css'));
    }

    public function xListAction()
    {
        $query = trim($this->getParam('query'));
        $this->response->data(array(
            'ccs' => $this->getCostCentersList($query)
        ));
    }

    public function editAction()
    {
        if ($this->getParam('ccId')) {
            $cc = $this->getContainer()->analytics->ccs->get($this->getParam('ccId'));
            if (!$cc)
                throw new Scalr_UI_Exception_NotFound();
        }

        if (isset($cc)) {
            $ccData = $this->getCostCenterData($cc, true);
            //Check whether it can be removed
            try {
                $ccData['removable'] = $cc->checkRemoval();
            } catch (AnalyticsException $e) {
                $ccData['removable'] = false;
                $ccData['warning'] = $e->getMessage();
            }
        } else {
            $ccData = [];
        }

        $this->response->page('ui/analytics/costcenters/edit.js', array(
            'cc' => $ccData
        ), array('/ui/analytics/analytics.js'));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'name'        => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true)),
            'billingCode' => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true, Scalr_Validator::ALPHANUM => true)),
            'leadEmail'   => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true, Scalr_Validator::EMAIL => true))
        ));

        if ($this->getParam('ccId')) {
            $cc = $this->getContainer()->analytics->ccs->get($this->getParam('ccId'));

            if (!$cc) {
                throw new Scalr_UI_Exception_NotFound();
            }
        } else {
            $cc = new CostCentreEntity();
        }

        if (!$this->request->validate()->isValid()) {
            $this->response->data($this->request->getValidationErrors());

            $this->response->failure();

            return;
        }

        $cc->name = $this->getParam('name');

        //Checks whether billing code specified in the request is already used in another Cost Centre
        $criteria = [['name' => CostCentrePropertyEntity::NAME_BILLING_CODE], ['value' => $this->getParam('billingCode')]];

        if ($cc->ccId !== null) {
            $criteria[] = ['ccId' => ['$ne' => $cc->ccId]];
        } else {
            //This is a new cost center.
            //We should set the email address and identifier of the user who creates the record.
            $cc->createdById = $this->user->id;

            $cc->createdByEmail = $this->user->getEmail();
        }

        $ccPropertyEntity = new CostCentrePropertyEntity();

        $record = $this->db->GetRow("
            SELECT " . $cc->fields('c') . "
            FROM " . $cc->table('c') . "
            JOIN " . $ccPropertyEntity->table('cp') . " ON cp.cc_id = c.cc_id
            WHERE " . $ccPropertyEntity->_buildQuery($criteria, 'AND', 'cp')['where'] . "
            LIMIT 1
        ");

        if ($record) {
            $found = new CostCentreEntity();
            $found->load($record);
        }

        if (!empty($found)) {
            throw new AnalyticsException(sprintf(
                'Billing code "%s" is already used in Cost center "%s"',
                strip_tags($this->getParam('billingCode')),
                $found->name
            ));
        }

        $this->db->BeginTrans();

        try {
            $cc->save();

            $cc->saveProperty(CostCentrePropertyEntity::NAME_BILLING_CODE, $this->getParam('billingCode'));
            $cc->saveProperty(CostCentrePropertyEntity::NAME_DESCRIPTION, $this->getParam('description'));
            $cc->saveProperty(CostCentrePropertyEntity::NAME_LEAD_EMAIL, $this->getParam('leadEmail'));

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();

            throw $e;
        }

        $this->response->data(array('cc' => $this->getCostCenterData($cc, true)));
        $this->response->success('Cost center has been successfully saved');
    }

    public function xRemoveAction()
    {
        $cc = $this->getContainer()->analytics->ccs->get($this->getParam('ccId'));
        if ($cc) {
            try {
                $removable = $cc->checkRemoval();
            } catch (AnalyticsException $e) {
            }
            //Actually it archives the cost centre and performs deletion
            //only if there are no records have been collected yet.
            $cc->delete();
        } else {
            throw new Scalr_UI_Exception_NotFound();
        }
        $this->response->data(array('removable' => $removable));
        $this->response->success();
    }

    /**
     * Gets the list of the cost centres
     *
     * @return   array Returns the list of the cost centres
     */
    private function getCostCentersList($query = null)
    {
        $ccs = array();
        $criteria = null;

        $collection = $this->getContainer()->analytics->ccs->findByKey($query);

        if ($collection->count()) {
            $iterator = new ChartPeriodIterator('month', gmdate('Y-m-01'), null, 'UTC');

            //It calculates usage for all provided cost centres
            $usage = $this->getContainer()->analytics->usage->get(
                null, $iterator->getStart(), $iterator->getEnd(),
                [TagEntity::TAG_ID_COST_CENTRE]
            );

            //It calculates usage for previous period same days
            $prevusage = $this->getContainer()->analytics->usage->get(
                null, $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
                [TagEntity::TAG_ID_COST_CENTRE]
            );

            //Calclulates usage for previous whole period
            if ($iterator->getPreviousEnd() != $iterator->getWholePreviousPeriodEnd()) {
                $prevWholePeriodUsage = $this->getContainer()->analytics->usage->get(
                    null, $iterator->getPreviousStart(), $iterator->getWholePreviousPeriodEnd(),
                    [TagEntity::TAG_ID_COST_CENTRE]
                );
            } else {
                $prevWholePeriodUsage = $prevusage;
            }

            foreach ($collection as $ccEntity) {
                /* @var $ccEntity \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
                $totalCost = round((isset($usage['data'][$ccEntity->ccId]) ?
                             $usage['data'][$ccEntity->ccId]['cost'] : 0), 2);

                //Archived cost centres are excluded only when there aren't any usage for this month and
                //query filter key has not been provided.
                if (($query === null || $query === '') && $ccEntity->archived && $totalCost < 0.01) {
                    continue;
                }

                $ccs[$ccEntity->ccId] = $this->getCostCenterData($ccEntity);

                $prevCost      = round((isset($prevusage['data'][$ccEntity->ccId]) ?
                                 $prevusage['data'][$ccEntity->ccId]['cost'] : 0), 2);

                $prevWholeCost = round((isset($prevWholePeriodUsage['data'][$ccEntity->ccId]) ?
                                 $prevWholePeriodUsage['data'][$ccEntity->ccId]['cost'] : 0), 2);

                $ccs[$ccEntity->ccId] = $this->getWrappedUsageData([
                    'ccId'           => $ccEntity->ccId,
                    'iterator'       => $iterator,
                    'usage'          => $totalCost,
                    'prevusage'      => $prevCost,
                    'prevusagewhole' => $prevWholeCost,
                ]) + $ccs[$ccEntity->ccId];
            }
        }

        return array_values($ccs);
    }

    /**
     * Gets cost centre properties and parameters
     *
     * @param   CostCentreEntity $cc          Cost centre entity
     * @param   string           $calculate   optional Whether response should be adjusted with cost usage data
     * @return  array Returns cost centre properties and parameters
     */
    private function getCostCenterData(CostCentreEntity $cc, $calculate = false)
    {
        $ret = array(
            'ccId'          => $cc->ccId,
            'name'          => $cc->name,
            'billingCode'   => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE),
            'description'   => $cc->getProperty(CostCentrePropertyEntity::NAME_DESCRIPTION),
            'leadEmail'     => $cc->getProperty(CostCentrePropertyEntity::NAME_LEAD_EMAIL),
            'created'       => $cc->created->format('Y-m-d'),
            'createdByEmail'=> $cc->createdByEmail,
            'archived'      => $cc->archived,
            'envCount'      => count($cc->getEnvironmentsList()),
            'projectsCount' => count($cc->getProjects())
        );

        if ($calculate) {
            $iterator = new ChartPeriodIterator('month', gmdate('Y-m-01'), null, 'UTC');

            $usage = $this->getContainer()->analytics->usage->get(['ccId' => $cc->ccId], $iterator->getStart(), $iterator->getEnd());

            //It calculates usage for previous period same days
            $prevusage = $this->getContainer()->analytics->usage->get(
                ['ccId' => $cc->ccId], $iterator->getPreviousStart(), $iterator->getPreviousEnd()
            );

            //Calclulates usage for previous whole period
            if ($iterator->getPreviousEnd() != $iterator->getWholePreviousPeriodEnd()) {
                $prevWholePeriodUsage = $this->getContainer()->analytics->usage->get(
                    ['ccId' => $cc->ccId], $iterator->getPreviousStart(), $iterator->getWholePreviousPeriodEnd()
                );
            } else {
                $prevWholePeriodUsage = $prevusage;
            }

            $ret = $this->getWrappedUsageData([
                'ccId'           => $cc->ccId,
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
     * @param   string   $ccId      The identifier of the cost center (UUID)
     * @param   string   $mode      Mode (week, month, quarter, year, custom)
     * @param   string   $startDate Start date in UTC (Y-m-d)
     * @param   string   $endDate   End date in UTC (Y-m-d)
     */
    public function xGetPeriodDataAction($ccId, $mode, $startDate, $endDate)
    {
        $this->response->data($this->getContainer()->analytics->usage->getCostCenterPeriodData($ccId, $mode, $startDate, $endDate));
    }

    /**
     * xGetMovingAverageToDateAction
     *
     * @param    string    $ccId       The identifier of the Cost center
     * @param    string    $mode       The mode
     * @param    string    $date       The date within specified period 'Y-m-d H:00'
     * @param    string    $startDate  The start date of the period 'Y-m-d'
     * @param    string    $endDate    The end date of the period 'Y-m-d'
     */
    public function xGetMovingAverageToDateAction($ccId, $mode, $date, $startDate, $endDate)
    {
        $this->response->data($this->getContainer()->analytics->usage->getCostCenterMovingAverageToDate(
            $ccId, $mode, $date, $startDate, $endDate
        ));
    }
}
