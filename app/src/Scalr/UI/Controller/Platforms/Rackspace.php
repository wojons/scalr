<?php

use Scalr\Model\Entity;

class Scalr_UI_Controller_Platforms_Rackspace extends Scalr_UI_Controller
{
    public function xGetFlavorsAction()
    {
        //TODO: check correct platform name
        $ccProps = $this->environment->cloudCredentials("{$this->getParam('cloudLocation')}." . SERVER_PLATFORMS::RACKSPACE)->properties;

        $cs = Scalr_Service_Cloud_Rackspace::newRackspaceCS(
            $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_USERNAME],
            $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_API_KEY],
            $this->getParam('cloudLocation')
        );

        $data = array();
        foreach ($cs->listFlavors(true)->flavors as $flavor) {
            $data[] = array(
                'id' => $flavor->id,
                'name' => sprintf('RAM: %s MB Disk: %s GB', $flavor->ram, $flavor->disk)
            );
        }

        $this->response->data(array('data' => $data));
    }
}
