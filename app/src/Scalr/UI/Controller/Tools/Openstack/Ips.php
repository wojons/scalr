<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;
use Scalr\Service\OpenStack\OpenStack;

class Scalr_UI_Controller_Tools_Openstack_Ips extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'ipId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_OPENSTACK_PUBLIC_IPS);
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

        $this->response->page('ui/tools/openstack/ips/view.js', array(
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
        $networkType = $platform->getConfigVariable(OpenstackPlatformModule::NETWORK_TYPE, $this->environment, false);
        $openstack = $this->environment->openstack($platformName, $this->getParam('cloudLocation'));

        foreach ($this->getParam('ipId') as $ipId) {
            if ($networkType == OpenstackPlatformModule::NETWORK_TYPE_QUANTUM) {
                $openstack->network->floatingIps->delete($ipId);
            } else {
                $openstack->servers->floatingIps->delete($ipId);
            }
        }

        $this->response->success('Floating IP(s) successfully removed');
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

        //$platform = PlatformFactory::NewPlatform($platformName);
        //$networkType = $platform->getConfigVariable(OpenstackPlatformModule::NETWORK_TYPE, $this->environment, false);
        $openstack = $this->environment->openstack($platformName, $this->getParam('cloudLocation'));

        //!FIXME dicsydel remove debug
        if ($openstack->hasService(OpenStack::SERVICE_NETWORK)) {
            var_dump($openstack->network->floatingIps->list()->toArray());
        }

        var_dump($openstack->servers->floatingIps->list()->toArray());
        var_dump($openstack->servers->listFloatingIpPools()->toArray());
        var_dump($openstack->network->listNetworks()->toArray());


        exit();

        $ips = array();
        foreach ($ipAddresses->publicipaddress as $pk => $pv) {
            if ($this->getParam('ipId') && $this->getParam('ipId') != $pv->id)
                continue;

            //!FIXME $systemIp variable has not been defined
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
