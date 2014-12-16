<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;

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

    public function createAction()
    {
        $this->response->page('ui/tools/openstack/volumes/create.js', array(
            'locations'	=> self::loadController('Platforms')->getCloudLocations($this->getParam('platform'), false)
        ));
    }
    
    public function xCreateAction()
    {
        $openstack = $this->getEnvironment()->openstack($this->getParam('platform'), $this->getParam('cloudLocation'));
    
        $volume = $openstack->volume->createVolume(
            $this->getParam('size'),
            $this->getParam('name'),
            $this->getParam('description'),
            $this->getParam('snapshotId') ? $this->getParam('snapshotId') : null 
        );
    
        $this->response->success('Volume has been successfully created');
        $this->response->data(array('data' => array('volumeId' => $volume->id)));
    }
    
    public function xDetachAction()
    {
    	$client = $this->environment->openstack($this->getParam('platform'), $this->getParam('cloudLocation'));
    	 
    	$result = $client->servers->detachVolume(
    		$this->getParam('serverId'),
    		$this->getParam('attachmentId')
    	);
    
    	$this->response->success('Cinder volume has been successfully detached');
    }
    
    public function xAttachAction()
    {    
    	$client = $this->environment->openstack($this->getParam('platform'), $this->getParam('cloudLocation'));
    
    	$dbServer = DBServer::LoadByID($this->getParam('serverId'));
    
    	$deviceName = $dbServer->GetFreeDeviceName();
    	
    	$result = $client->servers->attachVolume(
    		$dbServer->GetCloudServerID(), 
    		$this->getParam('volumeId'), 
    		$deviceName
		);
    
    	$this->response->success('Cinder volume has been successfully attached');
    }
    
    public function attachAction()
    {
    	$platformName = $this->getParam('platform');
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        if (!$this->environment->isPlatformEnabled($platformName))
        	throw new Exception("Cloud is not configured in current environment");
    
    	$dbServers = $this->db->GetAll("SELECT server_id FROM servers WHERE platform=? AND status=? AND env_id=?", array(
    		$this->getParam('platform'),
    		SERVER_STATUS::RUNNING,
    		$this->getEnvironmentId()
    	));
    
    	if (count($dbServers) == 0)
    		throw new Exception("You have no running servers on {$platformName} platform");
        
    	$servers = array();
    	foreach ($dbServers as $dbServer) {
    		$dbServer = DBServer::LoadByID($dbServer['server_id']);
    		$servers[$dbServer->serverId] = $dbServer->getNameByConvention();
    	}
    
    	if (count($servers) == 0)
    		throw new Exception("You have no running servers on the availablity zone of this volume");
    
    	$this->response->page('ui/tools/openstack/volumes/attach.js', array(
    		'servers' => $servers
    	));
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

        $vols = array();

        $volumes = $client->volume->listVolumes(true);

        do {
            foreach ($volumes as $pk => $pv) {
                if ($this->getParam('volumeId') && $this->getParam('volumeId') != $pv->id)
                    continue;

                $item = array(
                    'name' => $pv->display_name,
                    'description' => $pv->display_description,
                    'snapshotId' => $pv->snapshot_id,
                    'volumeId'	=> $pv->id,
                    'size'	=> $pv->size,
                    'status' => $pv->status,
                    'attachmentStatus' => isset($pv->attachments[0]) ? 'attached' : 'available',
                    'device'	=> isset($pv->attachments[0]) ? $pv->attachments[0]->device : "",
                    'instanceId' => isset($pv->attachments[0]) ? $pv->attachments[0]->server_id : "",
                	'attachmentId' => isset($pv->attachments[0]) ? $pv->attachments[0]->id : "",
                    'type' 			=> $pv->volume_type,
                    'availability_zone'		=> $pv->availability_zone,
                	'debug' => $pv
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
        } while (false !== ($volumes = $volumes->getNextPage()));

        $response = $this->buildResponseFromData($vols, array('serverId', 'volumeId','farmId', 'farmRoleId', 'name', 'description', 'snapshotId'));

        $this->response->data($response);
    }
}
