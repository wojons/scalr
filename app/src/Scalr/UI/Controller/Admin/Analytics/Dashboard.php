<?php

use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

class Scalr_UI_Controller_Admin_Analytics_Dashboard extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        $this->response->page(
            'ui/admin/analytics/dashboard/view.js',
            ['quarters' => SettingEntity::getQuarters(true)],
            ['/ui/analytics/analytics.js'],
            ['ui/analytics/analytics.css', '/ui/admin/analytics/admin.css']
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
        $this->response->data($this->getContainer()->analytics->usage->getDashboardPeriodData($mode, $startDate, $endDate));
    }
}
