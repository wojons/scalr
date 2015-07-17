<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Rackspace\RackspacePlatformModule;

class Scalr_UI_Controller_Tools_Rackspace extends Scalr_UI_Controller
{
    public function xListLimitsAction()
    {
        $cloudLocation = $this->getParam('cloudLocation');

        $cs = Scalr_Service_Cloud_Rackspace::newRackspaceCS(
            $this->environment->getPlatformConfigValue(RackspacePlatformModule::USERNAME, true, $cloudLocation),
            $this->environment->getPlatformConfigValue(RackspacePlatformModule::API_KEY, true, $cloudLocation),
            $cloudLocation
        );

        $limits = $cs->limits();
        $l = array();
        foreach ($limits->limits->rate as $limit) {

            $limit->resetTime = Scalr_Util_DateTime::convertTz(date("c", $limit->resetTime));

            $l[] = (array)$limit;
        }

        $response = $this->buildResponseFromData($l, array());

        $this->response->data($response);
    }

    public function limitsAction()
    {
        $this->response->page('ui/tools/rackspace/limits.js');
    }
}
