<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws\Ec2\DataType\InstanceData;
use Scalr\Service\Aws\Ec2\DataType\ReservationData;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupData;
use Scalr\Service\Aws\Elb\DataType\AttributesData;
use Scalr\Service\Aws\Elb\DataType\CrossZoneLoadBalancingData;
use Scalr\Service\Aws\Elb\DataType\InstanceStateData;
use Scalr\Service\Aws\Elb\DataType\ListenerData;
use Scalr\Service\Aws\Elb\DataType\LoadBalancerDescriptionData;
use Scalr\Service\Aws\Elb\DataType\ListenerList;
use Scalr\Service\Aws\Elb\DataType\HealthCheckData;
use Scalr\Model\Entity\CloudResource;
use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\Aws\Elb\DataType\ModifyLoadBalancerAttributes;
use Scalr\UI\Request\JsonData;
use Scalr\Util\CryptoTool;
use Scalr\Service\Aws;

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

    /**
     * Validates Access To Load Balancer
     *
     * @param string $elbName
     * @param string $cloudLocation
     * @throws Exception
     * @throws Scalr_Exception_InsufficientPermissions
     */
    protected function validateAccessToLoadBalancer($elbName, $cloudLocation)
    {
        $farmRoleService = CloudResource::findPk(
            $elbName,
            CloudResource::TYPE_AWS_ELB,
            $this->getEnvironmentId(),
            \SERVER_PLATFORMS::EC2,
            $cloudLocation
        );

        /* @var $farmRoleService CloudResource*/
        if ($farmRoleService) {
            $dbFarmRole = DBFarmRole::LoadByID($farmRoleService->farmRoleId);
            $this->user->getPermissions()->validate($dbFarmRole);
        }

    }

    /**
     * @param string   $cloudLocation       Ec2 region
     * @param JsonData $elbNames            Array of elbNames to delete
     * @throws Exception
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws \Scalr\Exception\ModelException
     */
    public function xDeleteAction($cloudLocation, JsonData $elbNames)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_ELB, Acl::PERM_AWS_ELB_MANAGE);

        $processed = [];

        foreach ($elbNames as $elbName) {
            $farmRoleService = CloudResource::findPk(
                $elbName,
                CloudResource::TYPE_AWS_ELB,
                $this->getEnvironmentId(),
                \SERVER_PLATFORMS::EC2,
                $cloudLocation
            );
            /* @var $farmRoleService CloudResource */
            $DBFarmRole = null;

            if ($farmRoleService) {
                $DBFarmRole = DBFarmRole::LoadByID($farmRoleService->farmRoleId);
            }

            $elb = $this->getEnvironment()->aws($cloudLocation)->elb;
            $elb->loadBalancer->delete($elbName);

            if ($DBFarmRole instanceof \DBFarmRole) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::AWS_ELB_ENABLED, false);
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::AWS_ELB_ID, false);
            }

            if ($farmRoleService) {
                $farmRoleService->delete();
            }

            $processed[] = $elbName;
        }

        $this->response->data(['processed' => $processed]);
        $this->response->success("Selected Elastic Load Balancers successfully removed");
    }

    public function viewAction()
    {
        $this->response->page(
            ['ui/tools/aws/ec2/elb/view.js', 'ui/security/groups/sgeditor.js'], array(
                'accountId'     => $this->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
                'remoteAddress' => $this->request->getRemoteAddr(),
            ), ['ui/tools/aws/ec2/elb/create.js']
        );
    }

    public function createAction($cloudLocation)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_ELB, Acl::PERM_AWS_ELB_MANAGE);

        $governance = new Scalr_Governance($this->getEnvironmentId());

        $this->response->page('ui/tools/aws/ec2/elb/create.js', array(
            'vpcLimits' => $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, null),
            'zones' => self::loadController('Ec2', 'Scalr_UI_Controller_Platforms')->getAvailZones($cloudLocation)
        ));
    }

    /**
     * @param string     $cloudLocation                     Ec2 Region
     * @param JsonData   $listeners                         Listeners list
     * @param bool       $crossLoadBalancing                Enable Cross balancing
     * @param JsonData   $healthcheck                       Health check data
     * @param string     $scheme                            optional Scheme
     * @param JsonData   $securityGroups                    optional Security groups
     * @param string     $vpcId                             optional Vpc id
     * @param JsonData   $zones                             optional Availability zones
     * @param JsonData   $subnets                           optional Subnets
     * @param string     $name                              optional Name
     * @throws Exception
     */
    public function xCreateAction($cloudLocation, JsonData $listeners,
                                  $crossLoadBalancing, JsonData $healthcheck,
                                  $scheme = null, JsonData $securityGroups = null, $vpcId = null,
                                  JsonData $zones = null, JsonData $subnets = null, $name = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_ELB, Acl::PERM_AWS_ELB_MANAGE);

        $elb = $this->environment->aws($cloudLocation)->elb;

        //prepare listeners
        $listenersList = new ListenerList();

        foreach ($listeners as $listener) {
            $listener_chunks = explode("#", $listener);
            $listenersList->append(new ListenerData(
                trim($listener_chunks[1]), trim($listener_chunks[2]),
                trim($listener_chunks[0]), null,
                trim($listener_chunks[3])
            ));
        }

        $zones = !empty($zones) ? (array) $zones : null;
        $subnets = !empty($subnets) ? (array) $subnets : null;

        if (empty($name)) {
            $name = sprintf("scalr-%s-%s", CryptoTool::sault(10), rand(100,999));
        } else if (!preg_match('/^[-a-zA-Z0-9]+$/', $name)) {
            throw new Exception('Load Balancer names must only contain alphanumeric characters or dashes.');
        }

        $healthCheckType = new HealthCheckData();
        $healthCheckType->target = $healthcheck['target'];
        $healthCheckType->healthyThreshold = $healthcheck['healthyThreshold'];
        $healthCheckType->interval = $healthcheck['interval'];
        $healthCheckType->timeout = $healthcheck['timeout'];
        $healthCheckType->unhealthyThreshold = $healthcheck['unhealthyThreshold'];

        $securityGroupIds = [];

        foreach ($securityGroups as $securityGroup) {
            $securityGroupIds[] = $securityGroup['id'];
        }

        $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkSecurityGroupsPolicy($securityGroups, Aws::SERVICE_INTERFACE_ELB);

        if ($result === true) {
            $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkVpcPolicy($vpcId, $subnets, $cloudLocation);
        }

        if ($result !== true) {
            throw new Exception($result);
        }

        //Creates a new ELB
        $dnsName = $elb->loadBalancer->create(
            $name, $listenersList,
            $zones,
            $subnets,
            !empty($securityGroupIds) ? $securityGroupIds : null,
            !empty($scheme) ? $scheme : null
        );

        if ($crossLoadBalancing) {
            $attributes = new AttributesData();
            $attributes->setCrossZoneLoadBalancing(new CrossZoneLoadBalancingData($crossLoadBalancing));
            $requestData = new ModifyLoadBalancerAttributes($name, $attributes);

            $elb->loadBalancer->modifyAttributes($requestData);
        }

        $elb->loadBalancer->addTags($name, $this->getEnvironment()->getAwsTags());

        try {
            $elb->loadBalancer->configureHealthCheck($name, $healthCheckType);
        } catch (Exception $e) {
            $elb->loadBalancer->delete($name);
            throw $e;
        }

        $lb = $elb->loadBalancer->describe($name)->get(0);

        // return all as in xListElb
        $this->response->data([
            'elb' => [
                'name'      => $name,
                'dnsName'   => $dnsName,
                'dtcreated' => $lb->createdTime->format('c'),
                'subnets'   => $lb->subnets
            ]
        ]);
    }

    /**
     * xSaveAction
     *
     * @param string    $elbName            Load balancer name
     * @param string    $cloudLocation      Ec2 region
     * @param JsonData  $listeners          Listeners array to create/update/delete
     * @param JsonData  $policies           Policies array to create/delete
     */
    public function xSaveAction($elbName, $cloudLocation, JsonData $listeners, JsonData $policies = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_ELB, Acl::PERM_AWS_ELB_MANAGE);

        $this->validateElb($cloudLocation, $elbName);
        $elb = $this->getEnvironment()->aws($cloudLocation)->elb;

        try {
            if (!empty($listeners['remove'])) {
                foreach ($listeners['remove'] as $listenerDelete) {
                    $elb->loadBalancer->deleteListeners($elbName, $listenerDelete['loadBalancerPort']);
                }
            }

            if (!empty($policies['remove'])) {
                foreach ($policies['remove'] as $policyDelete) {
                    $elb->loadBalancer->deletePolicy($elbName, $policyDelete['policyName']);
                }
            }

            if (!empty($policies['create'])) {
                foreach ($policies['create'] as $policyCreate) {
                    if ($policyCreate['policyType'] == 'AppCookie') {
                        $elb->loadBalancer->createAppCookieStickinessPolicy(
                            $elbName, $policyCreate['policyName'], $policyCreate['cookieSettings']
                        );
                    } else {
                        $elb->loadBalancer->createLbCookieStickinessPolicy(
                            $elbName, $policyCreate['policyName'], $policyCreate['cookieSettings']
                        );
                    }
                }
            }

            if (!empty($listeners['create'])) {
                foreach ($listeners['create'] as $listenerCreate) {
                    $elb->loadBalancer->createListeners($elbName, new ListenerData(
                        $listenerCreate['loadBalancerPort'], $listenerCreate['instancePort'],
                        $listenerCreate['protocol'], null, $listenerCreate['sslCertificate']
                    ));

                    if (!empty($listenerCreate['policyNames'])) {
                        $elb->loadBalancer->setPoliciesOfListener(
                            $elbName, $listenerCreate['loadBalancerPort'], $listenerCreate['policyNames']
                        );
                    }
                }
            }

            if (!empty($listeners['update'])) {
                foreach ($listeners['update'] as $listenerUpdate) {
                    $elb->loadBalancer->setPoliciesOfListener(
                        $elbName, $listenerUpdate['loadBalancerPort'], empty($listenerUpdate['policyNames']) ? null : $listenerUpdate['policyNames']
                    );
                }
            }
        } catch (Exception $e) {
            $errorMessage = "Could not complete the full set of requested changes of the Elastic Load Balancer. " . $e->getMessage();
        }

        $arrLb = $this->getDetails($cloudLocation, $elbName);

        $this->response->data([
            'elb' => [
                'name'                 => $elbName,
                'policies'             => $arrLb['policies'],
                'listenerDescriptions' => $arrLb['listenerDescriptions']
            ]
        ]);

        if (!empty($errorMessage)) {
            $this->response->failure($errorMessage);
        } else {
            $this->response->success("Selected Elastic Load Balancers successfully saved");
        }
    }

    /**
     * @param string $cloudLocation     Ec2 region
     * @param string $elbName           Elb name
     * @param string $awsInstanceId
     */
    public function xDeregisterInstanceAction($cloudLocation, $elbName, $awsInstanceId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_ELB, Acl::PERM_AWS_ELB_MANAGE);

        $this->validateElb($cloudLocation, $elbName);
        $elb = $this->getEnvironment()->aws($cloudLocation)->elb;
        $elb->loadBalancer->deregisterInstances($elbName, $awsInstanceId);

        $this->response->data([
            'instanceId' => $awsInstanceId
        ]);

        $this->response->success(_("Instance has been successfully removed from the Load Balancer"));
    }

    /**
     * @param string  $cloudLocation    Ec2 region
     * @param string  $elbName          Elb name
     * @param string  $awsInstanceId
     */
    public function xGetInstanceHealthAction($cloudLocation, $elbName, $awsInstanceId)
    {
        $elb = $this->getEnvironment()->aws($cloudLocation)->elb;
        $info = $elb->loadBalancer->describeInstanceHealth($elbName, $awsInstanceId)->get(0);

        if (!$info) {
            throw new InvalidArgumentException(sprintf("Could not find specified instance's state of Elastic Load balancer with name %s", $elbName));
        }

        $this->response->data($info->toArray());
    }

    /**
     * @param string $cloudLocation     Ec2 region
     * @param string $elbName           Elb name
     */
    public function xGetDetailsAction($cloudLocation, $elbName)
    {
        $this->response->data([
            'elb' => $this->getDetails($cloudLocation, $elbName)
        ]);
    }

    /**
     * @param string $cloudLocation Ec2 region
     * @param string $elbName Elb name
     * @return array
     */
    private function getDetails($cloudLocation, $elbName)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $lb = $aws->elb->loadBalancer->describe($elbName)->get(0);

        if (!$lb) {
            throw new InvalidArgumentException(sprintf("Elastic Load Balancer with name %s does not exist", $elbName));
        }

        $arrLb = $lb->toArray();
        $policies = [];

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

        if (!empty($arrLb['securityGroups'])) {
            $securityGroups = [];

            $describeResponse = $aws->ec2->securityGroup->describe();

            foreach ($describeResponse as $securityGroup) {
                /* @var $securityGroup SecurityGroupData */
                if (in_array($securityGroup->groupId, $arrLb['securityGroups'])) {
                    $securityGroups[] = [
                        'id'    => $securityGroup->groupId,
                        'name'  => $securityGroup->groupName
                    ];
                }
            }

            $arrLb['securityGroups'] = $securityGroups;
        }

        if (!empty($arrLb['instances'])) {
            $instanceIds = [];

            foreach ($arrLb['instances'] as $instance) {
                $instanceIds[] = $instance['instanceId'];
            }

            $instancesList = $aws->ec2->instance->describe($instanceIds);
            $instancesHealthStatus = $lb->describeInstanceHealth($instanceIds);

            $instanceStates = [];

            foreach ($instancesHealthStatus as $instanceHealth) {
                /* @var $instanceHealth InstanceStateData */
                $instanceStates[$instanceHealth->instanceId] = $instanceHealth->state;
            }

            $instances = [];

            foreach ($instancesList as $instance) {
                /* @var $instance ReservationData */
                $instanceData = $instance->instancesSet->get();

                $instances[] = [
                    'instanceId'       => $instanceData->instanceId,
                    'availabilityZone' => $instanceData->placement->availabilityZone,
                    'status'           => $instanceStates[$instanceData->instanceId]
                ];
            }

            $arrLb['instances'] = $instances;
        }

        $arrLb['policies'] = $policies;

        return $arrLb;
    }

    /**
     * @param string    $cloudLocation      Ec2 region
     * @param string    $placement          optional Placement
     * @throws Exception
     * @throws Scalr_Exception_Core
     */
    public function xListElasticLoadBalancersAction($cloudLocation, $placement = null)
    {
        $data = $this->getElasticLoadBalancersList($cloudLocation, $placement);
        $this->response->data($this->buildResponseFromData($data, ['name', 'dnsname', 'farmName', 'roleName']));
    }
    /**
     * @param string    $cloudLocation      Ec2 region
     * @param string    $placement          optional Placement
     * @param int       $limit              optional Limit
     * @throws Exception
     * @throws Scalr_Exception_Core
     */
    public function getElasticLoadBalancersList($cloudLocation, $placement = null)
    {
        $elb = $this->getEnvironment()->aws($cloudLocation)->elb;

        if ($placement == 'ec2') {
            $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
            $defaultVpc = $p->getDefaultVpc($this->environment, $cloudLocation);

            if ($defaultVpc) {
                $placement = $defaultVpc;
            }
        }

        $rowz1 = [];
        /* @var $lb LoadBalancerDescriptionData */
        foreach ($elb->loadBalancer->describe() as $lb) {

            if ($placement) {
                if ($placement == 'ec2' && $lb->vpcId != null)
                    continue;

                if ($placement != 'ec2' && $lb->vpcId != $placement)
                    continue;
            }

            $info = [
                "name"		 => $lb->loadBalancerName,
                "dtcreated"	 => Scalr_Util_DateTime::convertTz($lb->createdTime->format('c')),
                "dnsName"	 => $lb->dnsName,
                "availZones" => $lb->availabilityZones,
                "subnets"    => $lb->subnets,
                "vpcId"      => $lb->vpcId
            ];

            $farmRoleService = CloudResource::findPk(
                $lb->loadBalancerName,
                CloudResource::TYPE_AWS_ELB,
                $this->getEnvironmentId(),
                \SERVER_PLATFORMS::EC2,
                $cloudLocation
            );
            /* @var $farmRoleService CloudResource*/
            if ($farmRoleService) {
                $dbFarmRole = DBFarmRole::LoadByID($farmRoleService->farmRoleId);

                $info['used'] = true;
                $info['farmRoleId'] = $dbFarmRole->ID;
                $info['farmId'] = $dbFarmRole->FarmID;
                $info['roleName'] = $dbFarmRole->GetRoleObject()->name;
                $info['farmName'] = $dbFarmRole->GetFarmObject()->Name;
                $info['farmRoleAlias'] = $dbFarmRole->Alias;
            }

            $rowz1[] = $info;
        }

        return $rowz1;
    }

    /**
     * Checks if elb exists
     *
     * @param string $cloudLocation     Ec2 region
     * @param string $elbName           Elb name
     * @throws InvalidArgumentException
     */
    private function validateElb($cloudLocation, $elbName)
    {
        $elb = $this->getEnvironment()->aws($cloudLocation)->elb;
        $info = $elb->loadBalancer->describe($elbName)->get(0);

        if (!$info) {
            throw new InvalidArgumentException(sprintf("Elastic Load Balancer with name %s does not exist", $elbName));
        }
    }

    /**
     * Lists security groups
     *
     * @param string   $platform    Platform
     * @param string   $cloudLocation Cloud location
     * @param JsonData $filters
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xListSecurityGroupsAction($platform, $cloudLocation, JsonData $filters = null)
    {
        $this->response->data(self::loadController('Groups', 'Scalr_UI_Controller_Security')->listGroups($platform, $cloudLocation, (array)$filters));
    }


}
