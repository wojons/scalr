<?php

use Scalr\Stats\CostAnalytics\Entity\ReportPayloadEntity;
use Scalr\Util\Api\Describer;
use Scalr\Util\Api\Mutators\AnalyticsSubtractor;

/**
 * Class Scalr_UI_Controller_Public
 *
 * Special guest controller for public links.
 * CSRF protection MUST be implemented itself in the action.
 */
class Scalr_UI_Controller_Public extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return true;
    }

    /**
     * View report action
     *
     * @param string $uuid          Report uuid
     * @param string $secretHash    Report secret hash
     */
    public function reportAction($uuid, $secretHash)
    {
        $data = ReportPayloadEntity::findOne([['uuid' => $uuid], ['secret' => hex2bin($secretHash)]]);

        if (empty($data) || !property_exists($data, 'payload')) {
            throw new Scalr_UI_Exception_NotFound();
        }

        $this->response->page('ui/public/report.js', json_decode($data->payload, true), array(), array('ui/analytics/analytics.css', 'ui/admin/analytics/admin.css', 'ui/public/report.css'));
    }

    /**
     * Describes API specifications
     *
     * @param   string  $version    API version
     * @param   string  $service    API service
     */
    public function describeApiSpecAction($version, $service) {
        $describer = new Describer($version, $service, \Scalr::getContainer()->config());

        $describer->mutate(new AnalyticsSubtractor())
                  ->describe($this->response);
    }
}
