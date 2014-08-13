<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;

class Scalr_UI_Controller_Tools_Gce_Snapshots extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'snapshotId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GCE_SNAPSHOTS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $locations = self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::GCE, false);

        $this->response->page('ui/tools/gce/snapshots/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'snapshotId' => array('type' => 'json')
        ));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        foreach ($this->getParam('snapshotId') as $snapId) {
            $client->snapshots->delete(
                $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID),
                $snapId
            );
        }

        $this->response->success('Snapshot(s) successfully removed');
    }

    public function xListSnapshotsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'ASC')),
            'snapshotId'
        ));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        $retval = array();

        $snaps = $client->snapshots->listSnapshots(
            $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID)
        );

        foreach ($snaps as $snap) {
            /* @var $snap Google_Service_Compute_Snapshot */
            if ($this->getParam('snapshotId') && $this->getParam('snapshotId') != $snap->name)
                continue;

            $item = array(
                'id'	=> $snap->name,
                'description'	=> $snap->description,
                'createdAt' => Scalr_Util_DateTime::convertTz(strtotime($snap->creationTimestamp)),
                'size' => $snap->diskSizeGb,
                'status' => $snap->status/*,
                'details' => (array)$snap->toSimpleObject()*/
            );

            $retval[] = $item;
        }

        $response = $this->buildResponseFromData($retval, array('id', 'description'));

        $this->response->data($response);
    }
}
