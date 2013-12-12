<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Openstack_Snapshots extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'snapshotId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_OPENSTACK_SNAPSHOTS);
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
            $locations = self::loadController('Platforms')->getCloudLocations(PlatformFactory::getOpenstackBasedPlatforms(), false);
        }

        $this->response->page('ui/tools/openstack/snapshots/view.js', array(
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

        $client = $this->environment->openstack($platformName, $this->getParam('cloudLocation'));

        foreach ($this->getParam('snapshotId') as $snapshotId) {
            $client->volume->deleteSnapshot($snapshotId);
        }

        $this->response->success('Snapshot(s) successfully removed');
    }

    public function xListSnapshotsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'volumeId', 'direction' => 'ASC')),
            'volumeId'
        ));

        $platformName = $this->getParam('platform');
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        $client = $this->environment->openstack($platformName, $this->getParam('cloudLocation'));

        $snapshots = $client->volume->listSnapshots(true);
        $snaps = array();
        foreach ($snapshots as $pk=>$pv)
        {
            if ($this->getParam('snapshotId') && $this->getParam('snapshotId') != $pv->id)
                continue;

            $item = array(
                'snapshotId'	=> $pv->id,
                'size'	        => $pv->size,
                'volumeId'      => $pv->volume_id,
                'createdAt'     => $pv->created_at,
                'status'	    => $pv->status,
                'progress'      => $pv->{"os-extended-snapshot-attributes:progress"}
            );

            $snaps[] = $item;
        }

        $response = $this->buildResponseFromData($snaps, array('snapshotId', 'volumeId', 'status'));

        $this->response->data($response);
    }
}
