<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;

class Scalr_UI_Controller_Tools_Gce_Disks extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'diskId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GCE_PERSISTENT_DISKS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $locations = self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::GCE, false);

        $this->response->page('ui/tools/gce/disks/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'diskId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment, $this->getParam('cloudLocation'));
        /* @var $client Google_Service_Compute */

        foreach ($this->getParam('diskId') as $diskId) {
            $client->disks->delete(
                $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID),
                $this->getParam('cloudLocation'),
                $diskId
            );
        }

        $this->response->success('Persistent disk(s) successfully removed');
    }

    public function xListDisksAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'ASC')),
            'diskId'
        ));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment, $this->getParam('cloudLocation'));
        /* @var $client Google_Service_Compute */

        $retval = array();

        $disks = $client->disks->listDisks(
            $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID),
            $this->getParam('cloudLocation')
        );

        foreach ($disks as $disk) {
            /* @var $disk Google_Service_Compute_Disk */
            if ($this->getParam('diskId') && $this->getParam('diskId') != $disk->name)
                continue;

            $item = array(
                'id'	=> $disk->name,
                'description'	=> $disk->description,
                'createdAt' => Scalr_Util_DateTime::convertTz(strtotime($disk->creationTimestamp)),
                'size' => $disk->sizeGb,
                'status' => $disk->status,
                'snapshotId' => $disk->sourceSnapshotId
            );

            $retval[] = $item;
        }

        $response = $this->buildResponseFromData($retval, array('id', 'name','description', 'snapshotId'));

        $this->response->data($response);
    }
}
