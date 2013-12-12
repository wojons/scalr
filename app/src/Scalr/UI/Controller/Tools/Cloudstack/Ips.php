<?php
use Scalr\Acl\Acl;

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
            $locations = self::loadController('Platforms')->getCloudLocations(array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::UCLOUD), false);
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
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        $platform = PlatformFactory::NewPlatform($platformName);

        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->environment),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->environment),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->environment),
            $platformName
        );

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
        if (!$platformName)
            throw new Exception("Cloud should be specified");

        $platform = PlatformFactory::NewPlatform($platformName);

        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->environment),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->environment),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->environment),
            $platformName
        );

        $accountName = $platform->getConfigVariable(Modules_Platforms_Cloudstack::ACCOUNT_NAME, $this->getEnvironment(), false);
        $domainId = $platform->getConfigVariable(Modules_Platforms_Cloudstack::DOMAIN_ID, $this->getEnvironment(), false);

        $ipAddresses = $cs->listPublicIpAddresses(null, $accountName, null, $domainId, null, null, null, null, null, null, $this->getParam('cloudLocation'));

        $systemIp = $platform->getConfigVariable(Modules_Platforms_Cloudstack::SHARED_IP.".".$this->getParam('cloudLocation'), $this->environment);

        $ips = array();
        foreach ($ipAddresses->publicipaddress as $pk=>$pv)
        {
            if ($this->getParam('ipId') && $this->getParam('ipId') != $pv->id)
                continue;

            if ($pv->ipaddress == $systemIp)
                $pv->purpose = 'ScalrShared';

            if ($pv->isstaticnat && !$pv->issystem)
                $pv->purpose = 'ElasticIP';

            if ($pv->isstaticnat && $pv->issystem)
                $pv->purpose = 'PublicIP';

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

        $response = $this->buildResponseFromData($ips, array('serverId', 'ipId', 'ip', 'farmId', 'farmRoleId'));

        $this->response->data($response);
    }
}
