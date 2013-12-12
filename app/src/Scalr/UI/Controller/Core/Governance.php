<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Core_Governance extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_ADMINISTRATION_GOVERNANCE);
    }

    public function defaultAction()
    {
        $this->editAction();
    }

    public function editAction()
    {
        $platforms = array();
        //intersection of enabled platforms and supported by governance
        foreach (array_intersect($this->getEnvironment()->getEnabledPlatforms(), array('ec2', 'cloudstack', 'idcf')) as $platform) {
            $platforms[$platform] = self::loadController('Platforms')->getCloudLocations($platform, false);
        }

        $governance = new Scalr_Governance($this->getEnvironmentId());
        $this->response->page('ui/core/governance/edit.js', array(
            'platforms' => $platforms,
            'values' => $governance->getValues()
        ), array('ux-boxselect.js', 'ui/core/governance/lease.js'), array('ui/core/governance/edit.css'));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'name' => array('type' => 'string'),
            'value' => array('type' => 'json')
        ));

        $governance = new Scalr_Governance($this->getEnvironmentId());
        $governance->setValue($this->request->getParam('name'), $this->request->getParam('value'));

        $this->response->success('Successfully saved');
    }
}
