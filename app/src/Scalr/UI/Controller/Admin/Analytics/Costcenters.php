<?php
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\UI\Request\JsonData;
use Scalr\Exception\AnalyticsException;

class Scalr_UI_Controller_Admin_Analytics_Costcenters extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        $this->response->page('ui/admin/analytics/costcenters/view.js', array(
            'ccs' => $this->getCostCentersList(),
            'quarters' => SettingEntity::getQuarters(true)
        ), array('/ui/analytics/analytics.js'), array('ui/analytics/analytics.css', '/ui/admin/analytics/admin.css'));
    }

    /**
     * xListAction
     *
     * @param string $query optional Search query
     * @param bool $showArchived optional show old archived cost centers
     */
    public function xListAction($query= null, $showArchived = false)
    {
        $this->response->data(array(
            'ccs' => $this->getCostCentersList(trim($query), $showArchived)
        ));
    }

    /**
     * Edit cost center action
     *
     * @param string $ccId  optional Cost center identifier
     * @throws Scalr_UI_Exception_NotFound
     */
    public function editAction($ccId = null)
    {
        if ($ccId) {
            $cc = $this->getContainer()->analytics->ccs->get($ccId);
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

        $this->response->page('ui/admin/analytics/costcenters/edit.js', array(
            'cc' => $ccData
        ));
    }

    /**
     * xSaveAction
     *
     * @param string $name         Cost center name
     * @param string $billingCode  Cost center billing code
     * @param string $leadEmail    Cost center lead's email address
     * @param int    $locked       1 if locked. 0 otherwise.
     * @param string $ccId         optional Cost center identifier
     * @param string $description  optional Description
     * @throws AnalyticsException
     * @throws Exception
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xSaveAction($name, $billingCode, $leadEmail, $locked, $ccId = null, $description = null)
    {
        $this->request->defineParams(array(
            'name'        => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true)),
            'billingCode' => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true)),
            'leadEmail'   => array('type' => 'string', 'validator' => array(Scalr_Validator::NOEMPTY => true, Scalr_Validator::EMAIL => true))
        ));

        if ($ccId) {
            $cc = $this->getContainer()->analytics->ccs->get($ccId);

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

        $cc->name = $name;

        //Checks whether billing code specified in the request is already used in another Cost Centre
        $criteria = [['name' => CostCentrePropertyEntity::NAME_BILLING_CODE], ['value' => $billingCode]];

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
                strip_tags($billingCode),
                $found->name
            ));
        }

        $this->db->BeginTrans();

        try {
            $cc->save();

            //NOTE please take into account the presence of the usage->createHostedScalrAccountCostCenter() method

            $cc->saveProperty(CostCentrePropertyEntity::NAME_BILLING_CODE, $billingCode);
            $cc->saveProperty(CostCentrePropertyEntity::NAME_DESCRIPTION, $description);
            $cc->saveProperty(CostCentrePropertyEntity::NAME_LEAD_EMAIL, $leadEmail);
            $cc->saveProperty(CostCentrePropertyEntity::NAME_LOCKED, $locked);

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();

            throw $e;
        }

        $this->response->data(array('cc' => $this->getCostCenterData($cc, true)));
        $this->response->success('Cost center has been successfully saved');
    }

    /**
     * xRemoveAction
     *
     * @param string $ccId Cost center identifier
     * @throws Scalr_UI_Exception_NotFound
     */
    public function xRemoveAction($ccId)
    {
        $cc = $this->getContainer()->analytics->ccs->get($ccId);

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
     * @param string $ccId
     */
    public function notificationsAction($ccId)
    {
        $this->response->page('ui/admin/analytics/costcenters/notifications.js', array(
            'notifications' => NotificationEntity::find([['subjectType' => NotificationEntity::SUBJECT_TYPE_CC],['subjectId' => $ccId]])->getArrayCopy(),
            'reports'       => ReportEntity::find([['subjectType' => ReportEntity::SUBJECT_TYPE_CC],['subjectId' => $ccId]])->getArrayCopy(),
        ), array(), array('ui/admin/analytics/notifications/view.css'));
    }

    /**
     * @param string $ccId
     * @param JsonData $notifications
     */
    public function xSaveNotificationsAction($ccId, JsonData $notifications)
    {
        $data = [];

        foreach ($notifications as $id => $settings) {
            if ($id == 'reports') {
                $this->saveReports($ccId, $settings);
                $data[$id] = ReportEntity::find([['subjectType' => ReportEntity::SUBJECT_TYPE_CC],['subjectId' => $ccId]])->getArrayCopy();
            } elseif ($id == 'notifications') {
                $this->saveNotifications($ccId, NotificationEntity::SUBJECT_TYPE_CC, $settings);
                $data[$id] = NotificationEntity::find([['subjectType' => NotificationEntity::SUBJECT_TYPE_CC],['subjectId' => $ccId]])->getArrayCopy();
            }
        }

        $this->response->data($data);
        $this->response->success('Notifications successfully saved');
    }

    /**
     * @param string $ccId
     * @param string $subjectType
     * @param array  $settings
     * @throws \Scalr\Exception\ModelException
     */
    private function saveNotifications($ccId, $subjectType, $settings)
    {
        $uuids = array();

        foreach ($settings['items'] as $item) {
            $notification = new NotificationEntity();
            if ($item['uuid']) {
                $notification->findPk($item['uuid']);
            }
            $notification->subjectType = $subjectType;
            $notification->subjectId = $ccId;
            $notification->notificationType = $item['notificationType'];
            $notification->threshold = $item['threshold'];
            $notification->recipientType = $item['recipientType'];
            $notification->emails = $item['emails'];
            $notification->status = $item['status'];
            $notification->save();
            $uuids[] = $notification->uuid;
        }

        foreach (NotificationEntity::find([['subjectType' => NotificationEntity::SUBJECT_TYPE_CC],['subjectId' => $ccId]]) as $notification) {
            if (!in_array($notification->uuid, $uuids)) {
                $notification->delete();
            }
        }
    }

    /**
     * @param string $ccId
     * @param array  $settings
     * @throws AnalyticsException
     * @throws Scalr_UI_Exception_NotFound
     */
    private function saveReports($ccId, $settings)
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

        foreach (ReportEntity::find([['subjectType' => ReportEntity::SUBJECT_TYPE_CC],['subjectId' => $ccId]]) as $report) {
            if (!in_array($report->uuid, $uuids)) {
                $report->delete();
            }
        }
    }

    /**
     * Gets the list of the cost centres
     *
     * @param string $query optional Search query
     * @param bool $showArchived
     * @return   array Returns the list of the cost centres
     */
    private function getCostCentersList($query = null, $showArchived = false)
    {
        $ccs = array();

        $collection = $this->getContainer()->analytics->ccs->findByKey($query);

        if ($collection->count()) {
            $iterator = ChartPeriodIterator::create('month', gmdate('Y-m-01'), null, 'UTC');

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

            foreach ($collection as $ccEntity) {
                /* @var $ccEntity \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
                $totalCost = round((isset($usage['data'][$ccEntity->ccId]) ?
                             $usage['data'][$ccEntity->ccId]['cost'] : 0), 2);

                //Archived cost centres are excluded only when there aren't any usage for this month and
                //query filter key has not been provided.
                if (($query === null || $query === '') && $ccEntity->archived && $totalCost < 0.01 && !$showArchived) {
                    continue;
                }

                $ccs[$ccEntity->ccId] = $this->getCostCenterData($ccEntity);

                $prevCost = round((isset($prevusage['data'][$ccEntity->ccId]) ?
                            $prevusage['data'][$ccEntity->ccId]['cost'] : 0), 2);

                $ccs[$ccEntity->ccId] = $this->getWrappedUsageData([
                    'ccId'           => $ccEntity->ccId,
                    'iterator'       => $iterator,
                    'usage'          => $totalCost,
                    'prevusage'      => $prevCost,
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
            'locked'        => $cc->getProperty(CostCentrePropertyEntity::NAME_LOCKED) ? 1 : 0,
            'created'       => $cc->created->format('Y-m-d'),
            'createdByEmail'=> $cc->createdByEmail,
            'archived'      => $cc->archived,
            'envCount'      => count($cc->getEnvironmentsList()),
            'projectsCount' => count($cc->getProjects())
        );

        if ($calculate) {
            $iterator = ChartPeriodIterator::create('month', gmdate('Y-m-01'), null, 'UTC');

            $usage = $this->getContainer()->analytics->usage->get(['ccId' => $cc->ccId], $iterator->getStart(), $iterator->getEnd());

            //It calculates usage for previous period same days
            $prevusage = $this->getContainer()->analytics->usage->get(
                ['ccId' => $cc->ccId], $iterator->getPreviousStart(), $iterator->getPreviousEnd()
            );

            $ret = $this->getWrappedUsageData([
                'ccId'           => $cc->ccId,
                'iterator'       => $iterator,
                'usage'          => $usage['cost'],
                'prevusage'      => $prevusage['cost'],
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
