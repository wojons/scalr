<?php

use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

class Scalr_UI_Controller_Admin_Analytics extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    /**
     * xGetPeriodLogAction
     *
     * @param   string    $mode      The mode (week, month, quarter, year)
     * @param   string    $startDate The start date of the period in UTC ('Y-m-d')
     * @param   string    $endDate   The end date of the period in UTC ('Y-m-d')
     * @param   string    $type      Type of the data gathered in log file (farms, clouds, projects)
     * @param   string    $ccId      optional The identifier of the cost center
     * @param   string    $projectId optional The identifier of the project
     */
    public function xGetPeriodCsvAction($mode, $startDate, $endDate, $type, $ccId = null, $projectId = null)
    {
        $name = 'Cloud';

        if (!empty($ccId) && empty($projectId)) {
            $data = $this->getContainer()->analytics->usage->getCostCenterPeriodData($ccId, $mode, $startDate, $endDate);
            $entity = CostCentreEntity::findPk($ccId);

            if ($type !== 'clouds') {
                $name = 'Project';
                $extraFields = 'Cost Center name;Billing code;Lead email address;';
            }
        } else if (!empty($projectId)) {
            $data = $this->getContainer()->analytics->usage->getProjectPeriodData($projectId, $mode, $startDate, $endDate);
            $entity = ProjectEntity::findPk($projectId);

            if ($type !== 'clouds') {
                $name = 'Farm';
                $extraFields = 'Project name;Billing code;Lead email address;';
            }
        } else {
            throw new \InvalidArgumentException(sprintf(
                "Method %s requires both ccId or projectId to be specified.",
                __METHOD__
            ));
        }

        $extraData = [$entity->name, $entity->getProperty('billing.code'), $entity->getProperty('lead.email')];

        $head[] = $name;
        $end[] = "Total spent";

        if (isset($extraFields)) {
            $head = array_merge($head, $extraData);
            $end = array_merge($end, $extraData);
        }

        $totals = 0;

        foreach ($data['timeline'] as $timeline) {
            $totals += $timeline['cost'];
            $head[] = $timeline['label'];
            $end[] = $timeline['cost'];
        }

        $head[] = 'Total';
        $end[] = $totals;

        $temp = tmpfile();

        fputcsv($temp, $head);

        foreach ($data[$type] as $item) {
            $row = [];

            $row[] = $item['name'];

            $dataCost = [];

            foreach ($data['timeline'] as $key => $value) {
                $dataCost[] = (isset($item['data'][$key]['cost']) ? $item['data'][$key]['cost'] : 0);
            }

            $itemTotal = array_sum($dataCost);

            if (isset($extraFields)) {
                $row = array_merge($row, $extraData);
            }

            $row = array_merge($row, $dataCost);
            $row[] = $itemTotal;

            fputcsv($temp, $row);
        }

        fputcsv($temp, $end);

        $metadata = stream_get_meta_data($temp);

        $label = Scalr_Util_DateTime::convertTz(time(), 'M_j_Y_H:i:s');
        $fileName = $entity->name . '.' . $entity->getProperty('billing.code') . '.' . $type . '.' . $label;

        $bad = array_merge(
            array_map('chr', range(0,31)),
            ["<", ">", ":", '"', "/", "\\", "|", "?", "*"]);

        $fileName = str_replace($bad, "", $fileName);

        $this->response->setHeader('Content-Encoding', 'utf-8');
        $this->response->setHeader('Content-Type', 'text/csv', true);
        $this->response->setHeader('Expires', 'Mon, 10 Jan 1997 08:00:00 GMT');
        $this->response->setHeader('Pragma', 'no-cache');
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $this->response->setHeader('Cache-Control', 'post-check=0, pre-check=0');
        $this->response->setHeader('Content-Disposition', 'attachment; filename=' . $fileName . ".csv");
        $this->response->setResponse(file_get_contents($metadata['uri']));
        fclose($temp);
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

        $iterator = ChartPeriodIterator::create($mode, $start, ($end ?: null), 'UTC');

        $pointPosition = $iterator->searchPoint($date);

        if ($pointPosition !== false) {
            $chartPoint = $iterator->current();
            $startDate = $chartPoint->dt;

            if ($chartPoint->isLastPoint) {
                $endDate = $chartPoint->end;
            } else {
                $iterator->next();
                $endDate = $iterator->current()->dt;
                $endDate->modify("-1 second");
            }
        }

        if (!isset($startDate)) {
            throw new OutOfBoundsException(sprintf("Date %s is inconsistent with the interval object", $date));
        }

        $entities = $analytics->events->get($startDate, $endDate, ['ccId' => $ccId, 'projectId' => $projectId]);

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
