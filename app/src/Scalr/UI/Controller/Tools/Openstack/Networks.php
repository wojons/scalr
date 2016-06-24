<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;

class Scalr_UI_Controller_Tools_Openstack_Networks extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'networkId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        //TODO:
        //return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_OPENSTACK_SNAPSHOTS);
        return parent::hasAccess();
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

        $this->response->page('ui/tools/openstack/networks/view.js', array(
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

        foreach ($this->getParam('networkId') as $networkId) {
            $client->network->deleteNetwork($networkId);
        }

        $this->response->success('Network(s) successfully removed');
    }

    public function xListSnapshotsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'networkId', 'direction' => 'ASC')),
            'networkId'
        ));

        $platformName = $this->getParam('platform');
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        $client = $this->environment->openstack($platformName, $this->getParam('cloudLocation'));

        $snaps = array();

        $networks = $client->network->listNetworks();
        do {
            foreach ($networks as $pk => $pv) {
                if ($this->getParam('networkId') && $this->getParam('networkId') != $pv->id)
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
        } while (false !== ($snapshots = $snapshots->getNextPage()));

        $response = $this->buildResponseFromData($snaps, array('snapshotId', 'volumeId', 'status'));

        $this->response->data($response);
    }
}
