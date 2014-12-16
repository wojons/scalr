<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use Scalr\Service\CloudStack\DataType\ListIpAddressesData;

class Scalr_UI_Controller_Tools_Cloudstack_Ips extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'ipId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_CLOUDSTACK_PUBLIC_IPS);
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
            $locations = self::loadController('Platforms')->getCloudLocations(array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF), false);
        }

        $this->response->page('ui/tools/cloudstack/ips/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'ipId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $platformName = $this->getParam('platform');
        if (!$platformName) {
            throw new Exception("Cloud should be specified");
        }

        $cs = $this->environment->cloudstack($platformName);

        foreach ($this->getParam('ipId') as $ipId) {
            $cs->disassociateIpAddress($ipId);
        }

        $this->response->success('Public IP(s) successfully removed');
    }

    public function xListIpsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'ipId', 'direction' => 'ASC')),
            'ipId'
        ));

        $platformName = $this->getParam('platform');
        if (!$platformName) {
            throw new Exception("Cloud should be specified");
        }
        $platform = PlatformFactory::NewPlatform($platformName);

        $cs = $this->environment->cloudstack($platformName);

        $accountName = $platform->getConfigVariable(CloudstackPlatformModule::ACCOUNT_NAME, $this->getEnvironment(), false);
        $domainId = $platform->getConfigVariable(CloudstackPlatformModule::DOMAIN_ID, $this->getEnvironment(), false);

        $requestData = new ListIpAddressesData();
        $requestData->account = $accountName;
        $requestData->domainid = $domainId;
        $requestData->zoneid = $this->getParam('cloudLocation');
        $ipAddresses = $cs->listPublicIpAddresses($requestData);

        $systemIp = $platform->getConfigVariable(CloudstackPlatformModule::SHARED_IP.".".$this->getParam('cloudLocation'), $this->environment);

        $ips = array();
        if (!empty($ipAddresses)) {
            foreach ($ipAddresses as $pk=>$pv)
            {
                if ($this->getParam('ipId') && $this->getParam('ipId') != $pv->id) {
                    continue;
                }
                if ($pv->ipaddress == $systemIp) {
                    $pv->purpose = 'ScalrShared';
                }
                if ($pv->isstaticnat && !$pv->issystem) {
                    $pv->purpose = 'ElasticIP';
                }
                if ($pv->isstaticnat && $pv->issystem) {
                    $pv->purpose = 'PublicIP';
                }
                $item = array(
                    'ipId'	=> $pv->id,
                    'dtAllocated' => $pv->allocated,
                    'networkName' => $pv->associatednetworkname,
                    'purpose' => $pv->purpose ? $pv->purpose : "Not used",
                    'ip' => $pv->ipaddress,
                    'state' => $pv->state,
                    'instanceId' => $pv->virtualmachineid,
                    'fullinfo' => $pv,
                    'farmId' => false
                );

                if ($item['instanceId']) {
                    try {
                        $dbServer = DBServer::LoadByPropertyValue(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID, $item['instanceId']);

                        $item['farmId'] = $dbServer->farmId;
                        $item['farmRoleId'] = $dbServer->farmRoleId;
                        $item['serverIndex'] = $dbServer->index;
                        $item['serverId'] = $dbServer->serverId;
                        $item['farmName'] = $dbServer->GetFarmObject()->Name;
                        $item['roleName'] = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;

                    } catch (Exception $e) {}
                }

                $ips[] = $item;
            }
        }
        $response = $this->buildResponseFromData($ips, array('serverId', 'ipId', 'ip', 'farmId', 'farmRoleId'));

        $this->response->data($response);
    }
}
