<?php

use Scalr\Acl\Acl;
use Scalr\Service\CloudStack\Services\Volume\DataType\ListVolumesData;

class Scalr_UI_Controller_Tools_Cloudstack_Volumes extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'volumeId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_CLOUDSTACK_VOLUMES);
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

        $this->response->page('ui/tools/cloudstack/volumes/view.js', array(
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

        if (!$platformName) {
            throw new Exception("Cloud should be specified");
        }

        $cs = $this->environment->cloudstack($platformName);

        foreach ($this->getParam('volumeId') as $volumeId) {
            $cs->volume->delete($volumeId);
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
        if (!$platformName) {
            throw new Exception("Cloud should be specified");
        }

        $cs = $this->environment->cloudstack($platformName);
        $requestData = new ListVolumesData();
        $requestData->zoneid = $this->getParam('cloudLocation');
        $requestData->listall = true;
        $volumes = $cs->volume->describe($requestData);

        $vols = array();
        if (!empty($volumes)) {
            foreach ($volumes as $pk=>$pv)
            {
                if ($this->getParam('volumeId') && $this->getParam('volumeId') != $pv->id) {
                    continue;
                }

                $item = array(
                    'volumeId'	=> $pv->id,
                    'size'	=> round($pv->size / 1024 / 1024 / 1024, 2),
                    'status' => $pv->state,
                    'attachmentStatus' => ($pv->virtualmachineid) ? 'attached' : 'available',
                    'device'	=> $pv->deviceid,
                    'instanceId' => $pv->virtualmachineid,
                    'type' 			=> $pv->type ." ({$pv->storagetype})",
                    'storage'		=> $pv->storage
                );

                $item['autoSnaps'] = ($this->db->GetOne("SELECT id FROM autosnap_settings WHERE objectid=? AND object_type=? LIMIT 1",
                     array($pv->id, AUTOSNAPSHOT_TYPE::CSVOL))) ? true : false;


                if ($item['instanceId']) {
                    try {
                        $dbServer = DBServer::LoadByPropertyValue(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID, $item['instanceId']);

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
        }
        $response = $this->buildResponseFromData($vols, array('serverId', 'volumeId','farmId', 'farmRoleId', 'storage'));

        $this->response->data($response);
    }
}
