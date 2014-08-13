<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;

class Scalr_UI_Controller_Tools_Gce_Addresses extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'addressId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GCE_STATIC_IPS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        $locations = array();
        $regions = $client->regions->listRegions(
            $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID)
        );
        foreach ($regions as $region) {
            /* @var $region Google_Service_Compute_Region */
            $locations[$region->name] = $region->description;
        }


        $this->response->page('ui/tools/gce/addresses/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'addressId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        foreach ($this->getParam('addressId') as $addressId) {
            $client->addresses->delete(
                $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID),
                $this->getParam('cloudLocation'),
                $addressId
            );
        }

        $this->response->success('Address(s) successfully removed');
    }

    public function xListAddressesAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'id', 'direction' => 'ASC')),
            'addressId'
        ));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        $retval = array();

        $addresses = $client->addresses->listAddresses(
            $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID),
            $this->getParam('cloudLocation')
        );

        foreach ($addresses as $address) {
            /* @var $address Google_Service_Compute_Address */
            if ($this->getParam('addressId') && $this->getParam('addressId') != $address->name)
                continue;

            $item = array(
                'id'	=> $address->name,
                'ip'    => $address->address,
                'description'	=> $address->description,
                'createdAt' => Scalr_Util_DateTime::convertTz(strtotime($address->creationTimestamp)),
                'status' => $address->status
            );

            if ($item['status'] == 'IN_USE') {
                $instanceURL = $address->users[0];
                $instanceName = substr($instanceURL, strrpos($instanceURL, "/")+1);
                $item['instanceId'] = $instanceName;

                try {
                    $dbServer = DBServer::LoadByID($item['instanceId']);
                    if ($dbServer && $dbServer->envId == $this->environment->id) {
                        $item['farmId'] = $dbServer->farmId;
                        $item['farmName'] = $dbServer->GetFarmObject()->Name;
                        $item['farmRoleId'] = $dbServer->farmRoleId;
                        $item['roleName'] = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;
                        $item['serverIndex'] = $dbServer->index;
                        $item['serverId'] = $dbServer->serverId;
                    }
                } catch (Exception $e) {}
            }

            $retval[] = $item;
        }

        $response = $this->buildResponseFromData($retval, array('id', 'ip', 'description'));

        $this->response->data($response);
    }
}
