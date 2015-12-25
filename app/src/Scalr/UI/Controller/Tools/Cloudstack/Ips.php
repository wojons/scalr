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
        $this->response->page('ui/tools/cloudstack/ips/view.js');
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_CLOUDSTACK_PUBLIC_IPS, Acl::PERM_CLOUDSTACK_PUBLIC_IPS_MANAGE);

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

        $ccProps = $this->environment->cloudCredentials($platformName)->properties;

        $accountName = $ccProps[\Scalr\Model\Entity\CloudCredentialsProperty::CLOUDSTACK_ACCOUNT_NAME];
        $domainId = $ccProps[\Scalr\Model\Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_ID];

        $requestData = new ListIpAddressesData();
        $requestData->account = $accountName;
        $requestData->domainid = $domainId;
        $requestData->zoneid = $this->getParam('cloudLocation');
        $ipAddresses = $cs->listPublicIpAddresses($requestData);

        $systemIp = $ccProps[\Scalr\Model\Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP.".{$this->getParam('cloudLocation')}"];

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
