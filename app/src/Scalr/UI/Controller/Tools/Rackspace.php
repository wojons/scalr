<?php

use Scalr\Model\Entity;

class Scalr_UI_Controller_Tools_Rackspace extends Scalr_UI_Controller
{
    public function xListLimitsAction()
    {
        //TODO: check correct platform name
        $ccProps = $this->environment->cloudCredentials($this->getParam('cloudLocation') . SERVER_PLATFORMS::RACKSPACE)->properties;

        $cs = Scalr_Service_Cloud_Rackspace::newRackspaceCS(
            $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_USERNAME],
            $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_API_KEY],
            $this->getParam('cloudLocation')
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
