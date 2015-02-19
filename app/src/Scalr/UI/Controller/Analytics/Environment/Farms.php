<?php

use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\AccountTagEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

/**
 * Farms controller
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.0
 */
class Scalr_UI_Controller_Analytics_Environment_Farms extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return $this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_ANALYTICS);
    }

    /**
     * Default action
     */
    public function defaultAction()
    {
        $this->response->page('ui/analytics/environment/farms/view.js', array(
            'farms'  => $this->getFarmsList(),
            'quarters'   => SettingEntity::getQuarters(true)
        ), array('/ui/analytics/analytics.js'), array('ui/analytics/analytics.css', '/ui/analytics/admin/admin.css'));
    }

    /**
     * List farms action
     *
     * @param string $query optional Search query
     */
    public function xListAction($query = null)
    {
        $this->response->data(array(
            'farms' => $this->getFarmsList($query)
        ));
    }

    /**
     * Gets the list of farms
     *
     * @param string $query optional Search query
     * @return array        Retuens array of farms
     */
    private function getFarmsList($query = null)
    {
        $farms = [];

        $collection = $this->getContainer()->analytics->usage->findFarmsByKey($this->environment->id, $query);

        if ($collection->count()) {
            $iterator = ChartPeriodIterator::create('month', gmdate('Y-m-01'), null, 'UTC');
            $criteria = ['envId' => $this->environment->id];

            //It calculates usage for all provided cost centres
            $usage = $this->getContainer()->analytics->usage->getFarmData(
                $this->environment->clientId, $criteria, $iterator->getStart(), $iterator->getEnd(),
                [TagEntity::TAG_ID_FARM, TagEntity::TAG_ID_FARM_ROLE, TagEntity::TAG_ID_PLATFORM]
            );

            //It calculates usage for previous period same days
            $prevusage = $this->getContainer()->analytics->usage->getFarmData(
                $this->environment->clientId, $criteria, $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
                [TagEntity::TAG_ID_FARM]
            );

            //Calclulates usage for previous whole period
            if ($iterator->getPreviousEnd() != $iterator->getWholePreviousPeriodEnd()) {
                $prevWholePeriodUsage = $this->getContainer()->analytics->usage->getFarmData(
                    $this->environment->clientId, $criteria, $iterator->getPreviousStart(), $iterator->getWholePreviousPeriodEnd(),
                    [TagEntity::TAG_ID_FARM]
                );
            } else {
                $prevWholePeriodUsage = $prevusage;
            }

            foreach ($collection as $dbFarm) {
                /* @var $dbFarm \DBFarm */
                $totalCost = round((isset($usage['data'][$dbFarm->ID]) ?
                             $usage['data'][$dbFarm->ID]['cost'] : 0), 2);

                $farms[$dbFarm->ID] = $this->getFarmData($dbFarm);

                if (isset($usage['data'][$dbFarm->ID]['data'])) {
                    $farms[$dbFarm->ID]['topSpender'] = $this->getFarmRoleTopSpender($usage['data'][$dbFarm->ID]['data']);
                } else {
                    $farms[$dbFarm->ID]['topSpender'] = null;
                }

                $prevCost      = round((isset($prevusage['data'][$dbFarm->ID]) ?
                                 $prevusage['data'][$dbFarm->ID]['cost'] : 0), 2);

                $prevWholeCost = round((isset($prevWholePeriodUsage['data'][$dbFarm->ID]) ?
                                 $prevWholePeriodUsage['data'][$dbFarm->ID]['cost'] : 0), 2);

                $farms[$dbFarm->ID] = $this->getWrappedUsageData([
                    'farmId'         => $dbFarm->ID,
                    'iterator'       => $iterator,
                    'usage'          => $totalCost,
                    'prevusage'      => $prevCost,
                    'prevusagewhole' => $prevWholeCost,
                ]) + $farms[$dbFarm->ID];

            }
        }

        return array_values($farms);
    }

    /**
     * Gets farm properties and parameters
     *
     * @param   DBFarm    $dbFarm          DBFarm object
     * @return  array Returns farm properties and parameters
     */
    private function getFarmData(DBFarm $dbFarm)
    {
        $projectId = $dbFarm->GetSetting(\DBFarm::SETTING_PROJECT_ID);

        $ret = array(
            'farmId'         => $dbFarm->ID,
            'name'           => $dbFarm->Name,
            'description'    => $dbFarm->Comments,
            'createdByEmail' => $dbFarm->createdByUserEmail,
            'projectId'      => $projectId,
            'projectName'    => !empty($projectId) ? ProjectEntity::findPk($projectId)->name : null,
        );

        return $ret;
    }

    /**
     * xGetPeriodDataAction
     *
     * @param   int      $farmId    The identifier of the farm
     * @param   string   $mode      Mode (week, month, quarter, year, custom)
     * @param   string   $startDate Start date in UTC (Y-m-d)
     * @param   string   $endDate   End date in UTC (Y-m-d)
     */
    public function xGetPeriodDataAction($farmId, $mode, $startDate, $endDate)
    {
        $this->response->data($this->getContainer()->analytics->usage->getFarmPeriodData($farmId, $this->environment, $mode, $startDate, $endDate));
    }

    /**
     * Gets farm role with top cost
     *
     * @param array $farmRoles Array of farm roles
     * @return array Returns farm role top spender
     */
    private function getFarmRoleTopSpender(array $farmRoles)
    {
        $max = 0;

        foreach ($farmRoles as $farmRoleId => $farmRole) {
            if ($max <= $farmRole['cost']) {
                $max = $farmRole['cost'];
                $maxId = $farmRoleId;
            }
        }

        $result = [
            'id'          => $maxId,
            'alias'       => AccountTagEntity::fetchName($maxId, TagEntity::TAG_ID_FARM_ROLE),
            'periodTotal' => $farmRoles[$maxId]['cost'],
            'platform'    => key($farmRoles[$maxId]['data'])
        ];

        return $result;
    }

}
