<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws\Elb\DataType\ListenerData;
use Scalr\Service\Aws\Elb\DataType\LoadBalancerDescriptionData;
use Scalr\Service\Aws\Elb\DataType\ListenerList;
use Scalr\Service\Aws\Elb\DataType\HealthCheckData;
use Scalr\Farm\Role\FarmRoleService;
use Scalr\Service\Aws\Elb\DataType\TagsList;
use Scalr\Util\CryptoTool;

class Scalr_UI_Controller_Tools_Aws_Ec2_Elb extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'elbName';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_ELB);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    protected function validateAccessToLoadBalancer()
    {
        $roleid = $this->db->GetOne("SELECT farm_roleid FROM farm_role_settings WHERE name=? AND value=? LIMIT 1",
        array(
            DBFarmRole::SETTING_AWS_ELB_ID,
            $this->getParam('elbName')
        ));

        if ($roleid) {
            $DBFarmRole = DBFarmRole::LoadByID($roleid);
            $this->user->getPermissions()->validate($DBFarmRole);
        }
    }


    public function xDeleteAction()
    {
        $roleid = $this->db->GetOne("SELECT farm_roleid FROM farm_role_settings WHERE name=? AND value=? LIMIT 1",
        array(
            DBFarmRole::SETTING_AWS_ELB_ID,
            $this->getParam('elbName')
        ));

        if ($roleid) {
            $DBFarmRole = DBFarmRole::LoadByID($roleid);
            $this->user->getPermissions()->validate($DBFarmRole);
        }

        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;
        $elb->loadBalancer->delete($this->getParam('elbName'));

        if ($DBFarmRole instanceof \DBFarmRole) {
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_AWS_ELB_ENABLED, false);
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_AWS_ELB_ID, false);

            $service = FarmRoleService::findFarmRoleService($this->getEnvironmentId(), $this->getParam('elbName'));
            if ($service)
                $service->remove();
        }
        $this->response->success("Selected Elastic Load Balancers successfully removed");
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/ec2/elb/view.js', array(
            'locations'	=> self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false)
        ));
    }

    public function createAction()
    {
        $governance = new Scalr_Governance($this->getEnvironmentId());
        $this->response->page('ui/tools/aws/ec2/elb/create.js', array(
            'vpcLimits' => $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, null),
            'zones' => self::loadController('Ec2', 'Scalr_UI_Controller_Platforms')->getAvailZones($this->getParam('cloudLocation'))
        ), array('ux-boxselect.js'));
    }

    public function xCreateAction()
    {
        $this->request->defineParams(array(
            'listeners' => array('type' => 'json'),
            'healthcheck' => array('type' => 'json'),
            'zones' => array('type' => 'array'),
            'subnets' => array('type' => 'array'),
            'scheme' => array('type' => 'string'),
        ));

        $healthCheck = $this->getParam('healthcheck');

        $elb = $this->environment->aws($this->getParam('cloudLocation'))->elb;

        //prepare listeners
        $listenersList = new ListenerList();
        $li = 0;
        foreach ($this->getParam('listeners') as $listener) {
            $listener_chunks = explode("#", $listener);
            $listenersList->append(new ListenerData(
                trim($listener_chunks[1]), trim($listener_chunks[2]),
                trim($listener_chunks[0]), null,
                trim($listener_chunks[3])
            ));
        }

        $availZones = $this->getParam('zones');
        $subnets = $this->getParam('subnets');
        $scheme = $this->getParam('scheme');

        $elb_name = sprintf("scalr-%s-%s", CryptoTool::sault(10), rand(100,999));

        $healthCheckType = new HealthCheckData();
        $healthCheckType->target = $healthCheck['target'];
        $healthCheckType->healthyThreshold = $healthCheck['healthyThreshold'];
        $healthCheckType->interval = $healthCheck['interval'];
        $healthCheckType->timeout = $healthCheck['timeout'];
        $healthCheckType->unhealthyThreshold = $healthCheck['unhealthyThreshold'];

        //Creates a new ELB
        $dnsName = $elb->loadBalancer->create($elb_name, $listenersList, !empty($availZones) ? $availZones : null, !empty($subnets) ? $subnets : null, null, !empty($scheme) ? $scheme : null);

        $tags = array(
            array('key' => "scalr-env-id", 'value' => $this->environment->id),
            array('key' => "scalr-owner", 'value' => $this->user->getEmail())
        );
        
        //Tags governance
        $governance = new \Scalr_Governance($this->environment->id);
        $gTags = (array)$governance->getValue('ec2', 'aws.tags');
        if (count($gTags) > 0) {
            foreach ($gTags as $tKey => $tValue)
                $tags[] = array('key' => $tKey, 'value' => $this->environment->applyGlobalVarsToValue($tValue));
        }
        
        $elb->loadBalancer->addTags($elb_name, $tags);
        
        try {
            $elb->loadBalancer->configureHealthCheck($elb_name, $healthCheckType);
        } catch (Exception $e) {
            $elb->loadBalancer->delete($elb_name);
            throw $e;
        }

        // return all as in xListElb
        $this->response->data(array(
            'elb' => array(
                'name'     => $elb_name,
                'dnsName' => $dnsName,
            )
        ));
    }

    public function xDeleteListenersAction()
    {
        $this->validateAccessToLoadBalancer();
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;
        $elb->loadBalancer->deleteListeners($this->getParam('elbName'), $this->getParam('lbPort'));
        $this->response->success('Listener successfully removed from load balancer');
    }

    public function xCreateListenersAction()
    {
        $this->validateAccessToLoadBalancer();
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;
        $elb->loadBalancer->createListeners($this->getParam('elbName'), new ListenerData(
            $this->getParam('lbPort'), $this->getParam('instancePort'),
            $this->getParam('protocol'), null, $this->getParam('certificateId')
        ));

        $this->response->success(_("New listener successfully created on load balancer"));
    }

    public function xDeregisterInstanceAction()
    {
        $this->validateAccessToLoadBalancer();
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;
        $elb->loadBalancer->deregisterInstances($this->getParam('elbName'), $this->getParam('awsInstanceId'));
        $this->response->success(_("Instance successfully deregistered from the load balancer"));
    }

    public function instanceHealthAction()
    {
        $this->validateAccessToLoadBalancer();
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;
        $info = $elb->loadBalancer->describeInstanceHealth($this->getParam('elbName'), $this->getParam('awsInstanceId'))->get(0);
        $this->response->page('ui/tools/aws/ec2/elb/instanceHealth.js', $info->toArray());
    }

    public function xDeleteSpAction()
    {
        $this->validateAccessToLoadBalancer();
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;
        $elb->loadBalancer->deletePolicy($this->getParam('elbName'), $this->getParam('policyName'));
        $this->response->success(_("Stickiness policy successfully removed"));
    }

    public function xCreateSpAction()
    {
        $this->validateAccessToLoadBalancer();
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;

        if ($this->getParam('policyType') == 'AppCookie') {
            $elb->loadBalancer->createAppCookieStickinessPolicy(
                $this->getParam('elbName'), $this->getParam('policyName'), $this->getParam('cookieSettings')
            );
        } else {
            $elb->loadBalancer->createLbCookieStickinessPolicy(
                $this->getParam('elbName'), $this->getParam('policyName'), $this->getParam('cookieSettings')
            );
        }
        $this->response->success(_("Stickiness policy successfully created"));
    }

    public function xAssociateSpAction()
    {
        $this->validateAccessToLoadBalancer();
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;
        $policyName = $this->getParam('policyName');
        $elb->loadBalancer->setPoliciesOfListener(
            $this->getParam('elbName'), $this->getParam('elbPort'), empty($policyName) ? null : $policyName
        );
        $this->response->success(_("Stickiness policies successfully associated with listener"));
    }

    public function detailsAction()
    {
        $this->validateAccessToLoadBalancer();
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;
        $lb = $elb->loadBalancer->describe($this->getParam('elbName'))->get(0);

        $arrLb = $lb->toArray();
        $policies = array();
        if (!empty($arrLb['policies']['appCookieStickinessPolicies'])) {
            foreach ($arrLb['policies']['appCookieStickinessPolicies'] as $member) {
                $member['policyType'] = 'AppCookie';
                $member['cookieSettings'] = $member['cookieName'];
                unset($member['cookieName']);
                $policies[] = $member;
            }
        }
        if (!empty($arrLb['policies']['lbCookieStickinessPolicies'])) {
            foreach ($arrLb['policies']['lbCookieStickinessPolicies'] as $member) {
                $member['policyType'] = 'LbCookie';
                $member['cookieSettings'] = $member['cookieExpirationPeriod'];
                unset($member['cookieExpirationPeriod']);
                $policies[] = $member;
            }
        }        
        
        $arrLb['policies'] = $policies;

        $this->response->page('ui/tools/aws/ec2/elb/details.js', array('elb' => $arrLb));
    }

    public function xListElasticLoadBalancersAction()
    {
        $elb = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->elb;

        $rowz1 = array();
        /* @var $lb LoadBalancerDescriptionData */
        foreach ($elb->loadBalancer->describe() as $lb) {

            if ($this->getParam('vpcId') && $this->getParam('vpcId') != $lb->vpcId)
                continue;

            if ($this->getParam('placement')) {
                if ($this->getParam('placement') == 'ec2' && $lb->vpcId != null)
                    continue;

                if ($this->getParam('placement') != 'ec2' && $lb->vpcId != $this->getParam('placement'))
                    continue;
            }

            $roleid = $this->db->GetOne("SELECT farm_roleid FROM farm_role_settings WHERE name=? AND value=? LIMIT 1",
                array(DBFarmRole::SETTING_AWS_ELB_ID, $lb->dnsName)
            );

            $farmId = false;
            $farmRoleId = false;
            $farmName = false;
            $roleName = false;

            if ($roleid) {
                try {
                    $DBFarmRole = DBFarmRole::LoadByID($roleid);

                    if ($DBFarmRole instanceof \DBFarmRole && !$this->user->getPermissions()->check($DBFarmRole))
                        continue;

                    $farmId = $DBFarmRole->FarmID;
                    $farmRoleId = $roleid;
                    $farmName = $DBFarmRole->GetFarmObject()->Name;
                    $roleName = $DBFarmRole->GetRoleObject()->name;
                } catch (Exception $e) {
                }
            }

            $info = array(
                "name"		 => $lb->loadBalancerName,
                "dtcreated"	 => $lb->createdTime->format('c'),
                "dnsName"	 => $lb->dnsName,
                "availZones" => $lb->availabilityZones,
                "subnets"    => $lb->subnets,
                "vpcId"      => $lb->vpcId
            );

            $farmRoleService = FarmRoleService::findFarmRoleService($this->environment->id, $lb->loadBalancerName);
            if ($farmRoleService) {
                $info['used'] = true;
                $info['farmRoleId'] = $farmRoleService->getFarmRole()->ID;
                $info['farmId'] = $farmRoleService->getFarmRole()->FarmID;
                $info['roleName'] = $farmRoleService->getFarmRole()->GetRoleObject()->name;
                $info['farmName'] = $farmRoleService->getFarmRole()->GetFarmObject()->Name;
            }

            //OLD notation
            try {
                $farmRoleId = $this->db->GetOne("SELECT farm_roleid FROM farm_role_settings WHERE name='lb.name' AND value=? LIMIT 1", array(
                    $lb->loadBalancerName
                ));
                if ($farmRoleId) {
                    $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
                    $info['used'] = true;
                    $info['farmRoleId'] = $dbFarmRole->ID;
                    $info['farmId'] = $dbFarmRole->FarmID;
                    $info['roleName'] = $dbFarmRole->GetRoleObject()->name;
                    $info['farmName'] = $dbFarmRole->GetFarmObject()->Name;
                }
            } catch (Exception $e) {}

            $rowz1[] = $info;
        }

        $response = $this->buildResponseFromData($rowz1, array('name', 'dnsname', 'farmName', 'roleName'));
        foreach($response['data'] as $k => $row) {
            $response['data'][$k]['dtcreated'] = Scalr_Util_DateTime::convertTz($row['dtcreated']);
        }

        $this->response->data($response);
    }
}
