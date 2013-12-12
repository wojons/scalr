<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Openstack_Volumes extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'volumeId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_OPENSTACK_VOLUMES);
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

        $this->response->page('ui/tools/openstack/volumes/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'volumeId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $platformName = $this->getParam('platform');
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        $client = $this->environment->openstack($platformName, $this->getParam('cloudLocation'));

        foreach ($this->getParam('volumeId') as $volumeId) {
            $client->volume->deleteVolume($volumeId);
        }

        $this->response->success('Volume(s) successfully removed');
    }

    public function xListVolumesAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'volumeId', 'direction' => 'ASC')),
            'volumeId'
        ));

        $platformName = $this->getParam('platform');
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        $client = $this->environment->openstack($platformName, $this->getParam('cloudLocation'));

        $volumes = $client->volume->listVolumes(true);

        $vols = array();
        foreach ($volumes as $pk=>$pv)
        {
            if ($this->getParam('volumeId') && $this->getParam('volumeId') != $pv->id)
                continue;

            $item = array(
                'volumeId'	=> $pv->id,
                'size'	=> $pv->size,
                'status' => $pv->status,
                'attachmentStatus' => isset($pv->attachments[0]) ? 'attached' : 'available',
                'device'	=> isset($pv->attachments[0]) ? $pv->attachments[0]->device : "",
                'instanceId' => isset($pv->attachments[0]) ? $pv->attachments[0]->server_id : "",
                'type' 			=> $pv->volume_type,
                'availability_zone'		=> $pv->availability_zone
            );

            if ($item['instanceId']) {
                try {
                    $dbServer = DBServer::LoadByPropertyValue(OPENSTACK_SERVER_PROPERTIES::SERVER_ID, $item['instanceId']);

                    $item['farmId'] = $dbServer->farmId;
                    $item['farmRoleId'] = $dbServer->farmRoleId;
                    $item['serverIndex'] = $dbServer->index;
                    $item['serverId'] = $dbServer->serverId;
                    $item['farmName'] = $dbServer->GetFarmObject()->Name;
                    $item['mountStatus'] = false;
                    $item['roleName'] = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;

                } catch (Exception $e) {}
            }

            $vols[] = $item;
        }

        $response = $this->buildResponseFromData($vols, array('serverId', 'volumeId','farmId', 'farmRoleId'));

        $this->response->data($response);
    }
}
