<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Cloudstack_Snapshots extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'snapshotId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_CLOUDSTACK_SNAPSHOTS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        if ($this->getParam('platform')) {
            $locations = self::loadController('Platforms')->getCloudLocations(array($this->getParam('platform')), false);
        } else {
            $locations = self::loadController('Platforms')->getCloudLocations(array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::UCLOUD), false);
        }

        $this->response->page('ui/tools/cloudstack/snapshots/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'snapshotId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $platformName = $this->getParam('platform');
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        $platform = PlatformFactory::NewPlatform($platformName);

        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->environment),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->environment),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->environment),
            $platformName
        );

        foreach ($this->getParam('snapshotId') as $snapshotId) {
            $cs->deleteSnapshot($snapshotId);
        }

        $this->response->success('Snapshot(s) successfully removed');
    }

    public function getSnapshots($platformName, $snapshotId = null)
    {
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        $platform = PlatformFactory::NewPlatform($platformName);

        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->environment),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->environment),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->environment),
            $platformName
        );

        $snapshots = $cs->listSnapshots();

        $snaps = array();
        foreach ($snapshots as $pk => $pv) {
            if ($snapshotId && $snapshotId != $pv->id)
                continue;

            $item = array(
                'snapshotId' => (string) $pv->id,
                'type'	=> $pv->snapshottype,
                'volumeId' => $pv->volumeid,
                'volumeType' => $pv->volumetype,
                'createdAt' => $pv->created,
                'intervalType' => $pv->intervaltype,
                'state'	=> $pv->state
            );

            $snaps[] = $item;
        }

        return $snaps;
    }

    public function xGetSnapshotsAction()
    {
        $snaps = $this->getSnapshots($this->getParam('platform'));
        $this->response->data(array('data' => $snaps));
    }

    public function xListSnapshotsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'volumeId', 'direction' => 'ASC')),
            'volumeId'
        ));

        $snaps = $this->getSnapshots($this->getParam('platform'), $this->getParam('snapshotId'));
        $response = $this->buildResponseFromData($snaps, array('snapshotId', 'volumeId', 'state'));
        $this->response->data($response);
    }
}
