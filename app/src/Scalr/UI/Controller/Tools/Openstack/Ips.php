<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;

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

        //TODO incomplete
        throw new Exception("This action is under development yet");
    }
}
