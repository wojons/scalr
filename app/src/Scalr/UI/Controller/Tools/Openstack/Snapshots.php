<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;

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

    public function createAction()
    {
        $this->response->page('ui/tools/openstack/snapshots/create.js', array());
    }
    
    /**
     * @param string $volumeId
     * @param string $cloudLocation
     * @param string $platform
     * @param string $name
     * @param string $description
     */
    public function xCreateAction($volumeId, $cloudLocation, $platform, $name = '', $description = '')
    {
        $client = $this->environment->openstack($platform, $cloudLocation);
        $snapshot = $client->volume->createSnapshot($volumeId, $name, $description, true);

        $this->response->data(array('data' => array('snapshotId' => $snapshot->id)));
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/openstack/snapshots/view.js');
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

        $snaps = array();

        $snapshots = $client->volume->listSnapshots(true);
        do {
            foreach ($snapshots as $pk => $pv) {
                if ($this->getParam('snapshotId') && $this->getParam('snapshotId') != $pv->id)
                    continue;

                $item = array(
                    'name'			=> $pv->display_name,
                    'description'   => $pv->display_description,
                    'snapshotId'	=> $pv->id,
                    'size'	        => $pv->size,
                    'volumeId'      => $pv->volume_id,
                    'createdAt'     => $pv->created_at,
                    'status'	    => $pv->status,
                    'progress'      => $pv->{"os-extended-snapshot-attributes:progress"},
                    'debug'         => $pv
                );

                $snaps[] = $item;
            }
        } while (false !== ($snapshots = $snapshots->getNextPage()));

        $response = $this->buildResponseFromData($snaps, array('snapshotId', 'volumeId', 'status', 'name', 'description'));

        $this->response->data($response);
    }
}
