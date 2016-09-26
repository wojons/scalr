<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\ChefServer;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Core_Governance extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GOVERNANCE_ENVIRONMENT);
    }

    public function defaultAction()
    {
        $this->editAction();
    }

    public function editAction()
    {
        $platforms = array();
        $governanceEnabledPlatforms = array(
            SERVER_PLATFORMS::EC2,
            SERVER_PLATFORMS::CLOUDSTACK,
            SERVER_PLATFORMS::IDCF,
            SERVER_PLATFORMS::OPENSTACK,
            SERVER_PLATFORMS::NEBULA,
            SERVER_PLATFORMS::MIRANTIS,
            SERVER_PLATFORMS::VIO,
            SERVER_PLATFORMS::VERIZON,
            SERVER_PLATFORMS::CISCO,
            SERVER_PLATFORMS::HPCLOUD,
            SERVER_PLATFORMS::OCS,
            SERVER_PLATFORMS::RACKSPACENG_US,
            SERVER_PLATFORMS::AZURE
        );
        //intersection of enabled platforms and supported by governance
        foreach (array_intersect($this->getEnvironment()->getEnabledPlatforms(), $governanceEnabledPlatforms) as $platform) {
            //we only need ec2 locations at the moment
            $platforms[$platform] = $platform == SERVER_PLATFORMS::EC2 ? self::loadController('Platforms')->getCloudLocations($platform, false) : array();
        }

        $chefServers = [];
        foreach (ChefServer::getList($this->user->getAccountId(), $this->getEnvironmentId()) as $chefServer) {
            $chefServers[] = [
                'id'    => $chefServer->id,
                'url'   => $chefServer->url,
                'scope' => $chefServer->getScope()
            ];
        }

        $governance = new Scalr_Governance($this->getEnvironmentId());
        $this->response->page('ui/core/governance/edit.js', array(
            'platforms' => $platforms,
            'values' => $governance->getValues(),
            'chef' => [
                'servers' => $chefServers
            ],
            'scalr.aws.ec2.limits.security_groups_per_instance' => \Scalr::config('scalr.aws.ec2.limits.security_groups_per_instance')
        ), array('ui/core/governance/lease.js'), array('ui/core/governance/edit.css'));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'category' => array('type' => 'string'),
            'name' => array('type' => 'string'),
            'value' => array('type' => 'json')
        ));

        $governance = new Scalr_Governance($this->getEnvironmentId());
        $category = $this->getParam('category');
        $name = $this->getParam('name');
        $value = $this->getParam('value');

        if ($category == Scalr_Governance::CATEGORY_GENERAL && $name == Scalr_Governance::GENERAL_LEASE) {
            $enabled = (bool) $value['limits']['enableDefaultLeaseDuration'];
            unset($value['limits']['enableDefaultLeaseDuration']);

            if (! $governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE) && $value['enabled'] == 1 && $enabled) {
                $dt = new DateTime();
                $dt->add(new DateInterval('P' . $value['limits']['defaultLifePeriod'] . 'D'));
                $farms = $this->db->GetCol('SELECT id FROM farms WHERE env_id = ?', array($this->getEnvironmentId()));
                foreach ($farms as $farmId) {
                    $farm = DBFarm::LoadByID($farmId);
                    $farm->SetSetting(Entity\FarmSetting::LEASE_STATUS, 'Active');

                    if ($farm->Status == FARM_STATUS::RUNNING) {
                        $farm->SetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE, $dt->format('Y-m-d H:i:s'));
                        $farm->SetSetting(Entity\FarmSetting::LEASE_NOTIFICATION_SEND, '');
                        $farm->SetSetting(Entity\FarmSetting::LEASE_EXTEND_CNT, 0);
                    }
                }
            }
        }

        $governance->setValue($category, $name, $value['enabled'], $value['limits']);
        $this->response->data(['governance' => $governance->getValues(true)]);
        $this->response->success('Successfully saved');
    }
}
