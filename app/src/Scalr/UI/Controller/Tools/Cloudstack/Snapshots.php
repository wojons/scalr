<?php

use Scalr\Acl\Acl;
use Scalr\Service\CloudStack\Services\Snapshot\DataType\ListSnapshotData;

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
        $this->response->page('ui/tools/cloudstack/snapshots/view.js');
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_CLOUDSTACK_SNAPSHOTS, Acl::PERM_CLOUDSTACK_SNAPSHOTS_MANAGE);

        $this->request->defineParams(array(
            'snapshotId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $platformName = $this->getParam('platform');
        if (!$platformName) {
            throw new Exception("Cloud should be specified");
        }

        $cs = $this->environment->cloudstack($platformName);

        foreach ($this->getParam('snapshotId') as $snapshotId) {
            $cs->snapshot->delete($snapshotId);
        }

        $this->response->success('Snapshot(s) successfully removed');
    }

    public function getSnapshots($platformName, $snapshotId = null, $cloudLocation = null)
    {
        if (!$platformName) {
            throw new Exception("Cloud should be specified");
        }

        $cs = $this->environment->cloudstack($platformName);

        $r = new ListSnapshotData();
        $r->zoneid = $cloudLocation;

        $snapshots = $cs->snapshot->describe($r);

        $snaps = array();
        if (!empty($snapshots)) {
            foreach ($snapshots as $pk => $pv) {
                if ($snapshotId && $snapshotId != $pv->id) {
                    continue;
                }
                $item = array(
                    'snapshotId' => (string) $pv->id,
                    'type'	=> $pv->snapshottype,
                    'volumeId' => $pv->volumeid,
                    'volumeType' => $pv->volumetype,
                    'createdAt' => $pv->created->format('c'),
                    'intervalType' => $pv->intervaltype,
                    'state'	=> $pv->state,
                    'zone' => $pv->zoneid
                );

                $snaps[] = $item;
            }
        }

        return $snaps;
    }

    public function xGetSnapshotsAction()
    {
        $snaps = $this->getSnapshots($this->getParam('platform'), null, $this->getParam('cloudLocation'));
        $this->response->data(array('data' => $snaps));
    }

    public function xListSnapshotsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'volumeId', 'direction' => 'ASC')),
            'volumeId'
        ));

        $snaps = $this->getSnapshots($this->getParam('platform'), $this->getParam('snapshotId'), $this->getParam('cloudLocation'));
        $response = $this->buildResponseFromData($snaps, array('snapshotId', 'volumeId', 'state'));
        $this->response->data($response);
    }
}
