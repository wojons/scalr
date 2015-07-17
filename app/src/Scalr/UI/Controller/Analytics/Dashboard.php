<?php

use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;

class Scalr_UI_Controller_Analytics_Dashboard extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_ENVADMINISTRATION_ANALYTICS);
    }

    /**
     * Default action
     */
    public function defaultAction()
    {
        $this->response->page(
            'ui/analytics/dashboard/view.js',
            [
                'quarters' => SettingEntity::getQuarters(true),
                'envName' => $this->environment->name,
                'ccName' => $this->getContainer()->analytics->ccs->get($this->environment->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID))->name
            ],
            ['/ui/analytics/analytics.js'],
            ['ui/analytics/analytics.css', 'ui/admin/analytics/admin.css']
        );
    }

    /**
     * xGetPeriodDataAction
     *
     * @param   string    $mode      The mode (week, month, quarter, year)
     * @param   string    $startDate The start date of the period in UTC ('Y-m-d')
     * @param   string    $endDate   The end date of the period in UTC ('Y-m-d')
     */
    public function xGetPeriodDataAction($mode, $startDate, $endDate)
    {
        $this->response->data($this->getContainer()->analytics->usage->getEnvironmentPeriodData($this->environment, $mode, $startDate, $endDate));
    }

}
