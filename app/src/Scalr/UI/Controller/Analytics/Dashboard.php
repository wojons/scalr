<?php

use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;

class Scalr_UI_Controller_Analytics_Dashboard extends Scalr_UI_Controller
{
    use Scalr\Stats\CostAnalytics\Forecast;

    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        $this->response->page(
            'ui/analytics/dashboard/view.js',
            ['quarters' => SettingEntity::getQuarters(true)],
            ['/ui/analytics/analytics.js'],
            ['/ui/analytics/analytics.css']
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

    /**
     * Gets events list
     *
     * @param string $mode      Chart mode
     * @param string $date      The requested date time
     * @param string $start     Start date of the current period
     * @param string $end       optional End date of the period
     * @param string $ccId      optional Cost center id
     * @param string $projectId optional Project id
     * @throws InvalidArgumentException
     */
    public function xGetTimelineEventsAction($mode, $date, $start, $end = null, $ccId = null, $projectId = null)
    {
        if (!preg_match('/^[\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:00$/', $date)) {
            throw new InvalidArgumentException(sprintf("Invalid date:%s. 'YYYY-MM-DD HH:00' is expected.", strip_tags($date)));
        }

        $analytics = $this->getContainer()->analytics;

        $iterator = new ChartPeriodIterator($mode, $start, ($end ?: null), 'UTC');

        foreach ($iterator as $chartPoint) {
            //FIXME rewrite search a point
            if ($chartPoint->dt->format('Y-m-d H:00') === $date) {
                $startDate = $chartPoint->dt;
                if ($chartPoint->isLastPoint) {
                    $endDate = $iterator->getEnd();
                } else {
                    $iterator->next();
                    $endDate = $iterator->current()->dt;
                    $endDate->modify("-1 second");
                }
                break;
            }
        }

        if (!isset($startDate)) {
            throw new OutOfBoundsException(sprintf("Date %s is inconsistent with the interval object", $date));
        }

        $entities = $analytics->events->get($startDate, $endDate, $ccId, $projectId);

        $data = [];

        foreach ($entities as $entity) {
            $data[] = [
                'dtime'       => $entity->dtime->format('Y-m-d H:i:s'),
                'description' => $entity->description,
                'type'        => $entity->eventType
            ];
        }

        $this->response->data(['data' => $data]);
    }

}
