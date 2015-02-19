<?php

use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\AccountTagEntity;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use \Scalr_Exception_InsufficientPermissions;
use \Scalr_Environment;

class Scalr_UI_Controller_Analytics_Account_Environments extends \Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    /**
     * {@inheritdoc}
     * @see \Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return $this->request->isAllowed(Acl::RESOURCE_ADMINISTRATION_ANALYTICS);
    }

    /**
     * Default action
     */
    public function defaultAction()
    {
        $this->response->page(
            'ui/analytics/account/environments/view.js',
            [
                'quarters' => SettingEntity::getQuarters(true),
                'environments' => $this->getEnvironmentsList(),
            ],
            ['ui/analytics/analytics.js', 'ui/analytics/account/account.js'],
            ['ui/analytics/analytics.css', 'ui/analytics/admin/admin.css']
        );
    }

    /**
     * List environments action
     *
     * @param string $query optional Search query
     */
    public function xListAction()
    {
        $query = trim($this->getParam('query'));

        $this->response->data(array(
            'environments' => $this->getEnvironmentsList($query)
        ));
    }

    /**
     * xGetPeriodDataAction
     *
     * @param   string    $envId     Environment id
     * @param   string    $mode      The mode (week, month, quarter, year)
     * @param   string    $startDate The start date of the period in UTC ('Y-m-d')
     * @param   string    $endDate   The end date of the period in UTC ('Y-m-d')
     */
    public function xGetPeriodDataAction($envId, $mode, $startDate, $endDate)
    {
        $env = Scalr_Environment::init()->loadById($envId);

        if ($env->clientId !== $this->environment->clientId) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->response->data($this->getContainer()->analytics->usage->getEnvironmentPeriodData($env, $mode, $startDate, $endDate));
    }

    /**
     * Gets a list of environments by key
     *
     * @param string $query Search query
     * @return array Returns array of environments
     */
    private function getEnvironmentsList($query = null)
    {
        $envs = [];

        $environments = $this->user->getEnvironments($query);

        if (count($environments) > 0) {
            $iterator = ChartPeriodIterator::create('month', gmdate('Y-m-01'), null, 'UTC');

            //It calculates usage for all provided enviroments
            $usage = $this->getContainer()->analytics->usage->getFarmData(
                $this->environment->clientId, [], $iterator->getStart(), $iterator->getEnd(),
                [TagEntity::TAG_ID_ENVIRONMENT, TagEntity::TAG_ID_FARM]
            );

            //It calculates usage for previous period same days
            $prevusage = $this->getContainer()->analytics->usage->getFarmData(
                $this->environment->clientId, [], $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
                [TagEntity::TAG_ID_ENVIRONMENT, TagEntity::TAG_ID_FARM]
            );

            //Calclulates usage for previous whole period
            if ($iterator->getPreviousEnd() != $iterator->getWholePreviousPeriodEnd()) {
                $prevWholePeriodUsage = $this->getContainer()->analytics->usage->getFarmData(
                    $this->environment->clientId, [], $iterator->getPreviousStart(), $iterator->getWholePreviousPeriodEnd(),
                    [TagEntity::TAG_ID_ENVIRONMENT, TagEntity::TAG_ID_FARM]
                );
            } else {
                $prevWholePeriodUsage = $prevusage;
            }

            foreach ($environments as $env) {

                if (isset($usage['data'][$env['id']]['data'])) {
                    $envs[$env['id']]['topSpender'] = $this->getFarmTopSpender($usage['data'][$env['id']]['data']);
                } else {
                    $envs[$env['id']]['topSpender'] = null;
                }

                $envs[$env['id']]['name'] = $env['name'];
                $envs[$env['id']]['envId'] = $env['id'];
                $ccId = Scalr_Environment::init()->loadById($env['id'])->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);

                if (!empty($ccId)) {
                    $envs[$env['id']]['ccId'] = $ccId;
                    $envs[$env['id']]['ccName'] = CostCentreEntity::findPk($ccId)->name;
                }

                $totalCost = round((isset($usage['data'][$env['id']]) ?
                    $usage['data'][$env['id']]['cost'] : 0), 2);

                $prevCost      = round((isset($prevusage['data'][$env['id']]) ?
                                 $prevusage['data'][$env['id']]['cost'] : 0), 2);

                $prevWholeCost = round((isset($prevWholePeriodUsage['data'][$env['id']]) ?
                                 $prevWholePeriodUsage['data'][$env['id']]['cost'] : 0), 2);

                $envs[$env['id']] = $this->getWrappedUsageData([
                    'iterator'       => $iterator,
                    'usage'          => $totalCost,
                    'prevusage'      => $prevCost,
                    'prevusagewhole' => $prevWholeCost,
                ]) + $envs[$env['id']];

            }
        }

        return array_values($envs);
    }

    /**
     * Gets farm with top cost
     *
     * @param array $farms Array of farm roles
     * @return array Returns farm top spender
     */
    private function getFarmTopSpender(array $farms)
    {
        $max = 0;

        foreach ($farms as $farmId => $farm) {
            if ($max <= $farm['cost']) {
                $max = $farm['cost'];
                $maxId = $farmId;
            }
        }

        $result = [
            'id'          => $maxId,
            'name'        => AccountTagEntity::fetchName($maxId, TagEntity::TAG_ID_FARM),
            'periodTotal' => $farms[$maxId]['cost'],
        ];

        return $result;
    }

}

