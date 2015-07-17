<?php

use Scalr\Service\Aws\Ec2\DataType\SecurityGroupList;
use Scalr\Service\Aws\Ec2\DataType\SnapshotFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\AddressFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\SnapshotData;
use Scalr\Service\Aws\Ec2\DataType\SubnetFilterNameType;
use Scalr\Model\Entity\CloudResource;
use Scalr\Service\Aws\Ec2\DataType\RouteTableFilterNameType;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Modules\PlatformFactory;

class Scalr_UI_Controller_Platforms_Ec2 extends Scalr_UI_Controller
{
    public function xListElbAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));
        $response = $aws->elb->describeLoadBalancers();
        $data = array();
        /* @var $elb \Scalr\Service\Aws\Elb\DataType\LoadBalancerDescriptionData */
        foreach ($response as $elb) {
            $info = array(
                'name' => $elb->loadBalancerName,
                'hostname' => $elb->dnsName
            );

            $farmRoleService = CloudResource::findPk(
                $elb->loadBalancerName,
                $this->environment->id,
                \SERVER_PLATFORMS::EC2,
                $this->getParam('cloudLocation')
            );
            if ($farmRoleService) {
                $dbFarmRole = DBFarmRole::LoadByID($farmRoleService->farmRoleId);
                $info['used'] = true;
                $info['farmRoleId'] = $dbFarmRole->ID;
                $info['farmId'] = $dbFarmRole->FarmID;
                $info['roleName'] = $dbFarmRole->GetRoleObject()->name;
                $info['farmName'] = $dbFarmRole->GetFarmObject()->Name;
            }

            //OLD notation
            try {
                $farmRoleId = $this->db->GetOne("SELECT farm_roleid FROM farm_role_settings WHERE name='lb.name' AND value=? LIMIT 1", array(
                    $elb->loadBalancerName
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

            $data[] = $info;
        }

        $this->response->data(array('data' => $data));
    }

    public function getAvailZones($cloudLocation)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        // Get Avail zones
        $response = $aws->ec2->availabilityZone->describe();
        $data = array();
        /* @var $zone \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
        foreach ($response as $zone) {
            $data[] = array(
                'id'    => (string)$zone->zoneName,
                'name'  => (string)$zone->zoneName,
                'state' => (string)$zone->zoneState,
            );
        }

        return $data;
    }

    public function xGetAvailZonesAction()
    {
        $this->response->data(array('data' => $this->getAvailZones($this->getParam('cloudLocation'))));

    }

    public function xGetFarmRoleElasicIpsAction()
    {
        $vpcId = $this->environment->getPlatformConfigValue(Ec2PlatformModule::DEFAULT_VPC_ID.".{$this->getParam('cloudLocation')}");

        $retval = $this->getFarmRoleElasticIps($this->getParam('cloudLocation'), $this->getParam('farmRoleId'), $vpcId);

        $this->response->data(array('data' => $retval));
    }

    public function xGetSnapshotsAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $response = $aws->ec2->snapshot->describe(null, null, array(array(
            'name'  => SnapshotFilterNameType::ownerId(),
            'value' => $this->getEnvironment()->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID),
        ), array(
            'name'  => SnapshotFilterNameType::status(),
            'value' => SnapshotData::STATUS_COMPLETED,
        )));

        $data = array();
        /* @var $pv \Scalr\Service\Aws\Ec2\DataType\SnapshotData */
        foreach ($response as $pv) {
            if ($pv->status == SnapshotData::STATUS_COMPLETED) {
                $data[] = array(
                    // old format
                    'snapid'        => $pv->snapshotId,
                    'createdat'     => Scalr_Util_DateTime::convertTz($pv->startTime),
                    'size'          => $pv->volumeSize,
                    // new format
                    'snapshotId'    => $pv->snapshotId,
                    'createdDate'   => Scalr_Util_DateTime::convertTz($pv->startTime),
                    'size'          => $pv->volumeSize,
                    'volumeId'      => $pv->volumeId,
                    'description'   => (string)$pv->description,
                    'encrypted'     => $pv->encrypted
                );
            }
        }

        $this->response->data(array('data' => $data));
    }

    public function xGetSubnetsListAction()
    {
        $this->response->data(array(
            'data' => $this->getSubnetsList($this->getParam('cloudLocation'), $this->getParam('vpcId')),
        ));
    }

    public function xGetRoutingTableListAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $tables = $aws->ec2->routeTable->describe(null, array(array(
            'name'  => RouteTableFilterNameType::vpcId(),
            'value' => $this->getParam('vpcId')
        )));
        $rows = array();
        /* @var $tableData Scalr\Service\Aws\Ec2\DataType\RouteTableData */
        foreach ($tables as $tableData) {
            $rows[] = array(
                'id'   => $tableData->routeTableId,
                'name' => $tableData->routeTableId,
                'info' => $tableData->toArray()
            );
        }

        $this->response->data(array(
            'tables' => $rows,
        ));
    }

    public function xGetVpcListAction($cloudLocation = null)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $vpcList = $aws->ec2->vpc->describe();
        $vpcSglist = $aws->ec2->securityGroup->describe();

        $rows = array();

        foreach ($vpcList as $vpcData) {
            /* @var $vpcData Scalr\Service\Aws\Ec2\DataType\VpcData */
            $name = 'No name';

            foreach ($vpcData->tagSet as $tag) {
                if ($tag->key == 'Name') {
                    $name = $tag->value;
                    break;
                }
            }

            $rows[] = array(
                'id'                        => $vpcData->vpcId,
                'name'                      => "{$name} - {$vpcData->vpcId} ({$vpcData->cidrBlock}, Tenancy: {$vpcData->instanceTenancy})",
                'defaultSecurityGroups'     => $this->getDefaultSgRow($vpcSglist, $vpcData->vpcId)
            );
        }

        $platform = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::EC2);
        $default = $platform->getDefaultVpc($this->getEnvironment(), $cloudLocation);

        $this->response->data(array(
            'vpc'     => $rows,
            'default' => !empty($default) ? $default : null
        ));
    }

    public function xGetDefaultVpcSegurityGroupsAction($cloudLocation, $vpcId)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $vpcSglist = $aws->ec2->securityGroup->describe();

        $this->response->data(['data' => $this->getDefaultSgRow($vpcSglist, $vpcId)]);
    }

    /**
     * Gets default vpc security group list
     *
     * @param SecurityGroupList   $vpcSglist
     * @param string    $vpcId
     * @return array
     */
    private function getDefaultSgRow($vpcSglist, $vpcId)
    {
        $governance = new Scalr_Governance($this->getEnvironmentId());
        $values = $governance->getValues(true);

        if (!empty($values['ec2']['aws.additional_security_groups']->value)) {
            $sgDefaultNames = explode(',', $values['ec2']['aws.additional_security_groups']->value);
        }

        $defaultSecurityGroups = [];
        $vpcSgNames = [];

        foreach ($vpcSglist as $vpcSg) {
            /* @var $vpcSg Scalr\Service\Aws\Ec2\DataType\SecurityGroupData */
            if (!empty($sgDefaultNames)) {
                if ($vpcSg->vpcId == $vpcId && in_array($vpcSg->groupName, $sgDefaultNames)) {
                    $defaultSecurityGroups[] = [
                        'securityGroupId'   => $vpcSg->groupId,
                        'securityGroupName' => $vpcSg->groupName
                    ];
                }
                $vpcSgNames[] = $vpcSg->groupName;
            } else if ($vpcSg->vpcId == $vpcId && $vpcSg->groupName == 'default') {
                $defaultSecurityGroups[] = [
                    'securityGroupId'   => $vpcSg->groupId,
                    'securityGroupName' => $vpcSg->groupName
                ];

                break;
            }
        }

        if (!empty($sgDefaultNames)) {
            $missingSgs = array_diff($sgDefaultNames, $vpcSgNames);

            foreach ($missingSgs as $missingSg) {
                $defaultSecurityGroups[] = [
                    'securityGroupId'   => null,
                    'securityGroupName' => $missingSg
                ];
            }
        }

        return $defaultSecurityGroups;
    }

    public function xGetPlatformDataAction(){
        $cloudLocation = $this->getParam('cloudLocation');
        $farmRoleId = $this->getParam('farmRoleId');

        $vpcId = $this->environment->getPlatformConfigValue(Ec2PlatformModule::DEFAULT_VPC_ID.".{$cloudLocation}");
        if ($this->getParam('vpcId') != '')
            $vpcId = $this->getParam('vpcId');

        $retval = array();
        $retval['eips'] = $this->getFarmRoleElasticIps($cloudLocation, $farmRoleId, $vpcId);
        $retval['subnets'] = $this->getSubnetsList($cloudLocation, $vpcId);
        $retval['iamProfiles'] = $this->getInstanceProfilesList();

        $this->response->data(array('data' => $retval));
    }

    public function getFarmRoleElasticIps($cloudLocation, $farmRoleId, $vpcId = null) {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $map = array();

        if ($farmRoleId) {
            $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
            $this->user->getPermissions()->validate($dbFarmRole);

            $maxInstances = $dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MAX_INSTANCES);
            for ($i = 1; $i <= $maxInstances; $i++) {
                $map[] = array('serverIndex' => $i);
            }

            $servers = $dbFarmRole->GetServersByFilter();
            for ($i = 0; $i < count($servers); $i++) {
                if ($servers[$i]->status != SERVER_STATUS::TERMINATED && $servers[$i]->status != SERVER_STATUS::TROUBLESHOOTING && $servers[$i]->index) {
                    $map[$servers[$i]->index - 1]['serverIndex'] = $servers[$i]->index;
                    $map[$servers[$i]->index - 1]['serverId'] = $servers[$i]->serverId;
                    $map[$servers[$i]->index - 1]['remoteIp'] = $servers[$i]->remoteIp;
                    $map[$servers[$i]->index - 1]['instanceId'] = $servers[$i]->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
                }
            }

            $ips = $this->db->GetAll('SELECT ipaddress, instance_index FROM elastic_ips WHERE farm_roleid = ?', array($dbFarmRole->ID));
            for ($i = 0; $i < count($ips); $i++) {
                $map[$ips[$i]['instance_index'] - 1]['elasticIp'] = $ips[$i]['ipaddress'];
            }
        }

        $domain = $vpcId ? 'vpc' : 'standard';

        $response = $aws->ec2->address->describe(null, null, array(array(
            'name'  => AddressFilterNameType::domain(),
            'value' => $domain
        )));

        $ips = array();
        /* @var $ip \Scalr\Service\Aws\Ec2\DataType\AddressData */
        foreach ($response as $ip) {
            $itm = array(
                'ipAddress'  => $ip->publicIp,
                'instanceId' => $ip->instanceId,
            );

            $info = $this->db->GetRow("
                SELECT * FROM elastic_ips WHERE ipaddress = ? LIMIT 1
            ", array($itm['ipAddress']));

            if ($info) {
                try {
                    if ($info['server_id'] && $itm['instanceId']) {
                        $dbServer = DBServer::LoadByID($info['server_id']);
                        if ($dbServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID) != $itm['instanceId']) {
                            for ($i = 0; $i < count($map); $i++) {
                                if ($map[$i]['elasticIp'] == $itm['ipAddress'])
                                    $map[$i]['warningInstanceIdDoesntMatch'] = true;
                            }
                        }
                    }

                    $farmRole = DBFarmRole::LoadByID($info['farm_roleid']);
                    $this->user->getPermissions()->validate($farmRole);

                    $itm['roleName'] = $farmRole->Alias;
                    $itm['farmName'] = $farmRole->GetFarmObject()->Name;
                    $itm['serverIndex'] = $info['instance_index'];
                } catch (Exception $e) {}
            }

            //TODO: Mark Router EIP ad USED

            $ips[] = $itm;
        }

        return array('map' => $map, 'ips' => $ips);
    }

    public function getSubnetsList($cloudLocation, $vpcId)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $subnets = $aws->ec2->subnet->describe(null, array(array(
            'name'  => SubnetFilterNameType::vpcId(),
            'value' => $vpcId,
        )));

        $rows = array();
        foreach($subnets as $subnet) {
            /* @var $subnet \Scalr\Service\Aws\Ec2\DataType\SubnetData */
            $item = array(
                'id'          => $subnet->subnetId,
                'description' => "{$subnet->subnetId}",
                'sidr'        => $subnet->cidrBlock,
                'availability_zone' => $subnet->availabilityZone,
                'ips_left' => $subnet->availableIpAddressCount,
                'name'      => 'No name'
            );

            foreach ($subnet->tagSet as $tag) {
                if ($tag->key == 'scalr-sn-type')
                    $item['internet'] = $tag->value;

                if ($tag->key == 'Name')
                    $item['name'] = $tag->value;
            }

            $item['description'] = "{$item['name']} - {$subnet->subnetId}";

            $rows[] = $item;
        }

        return $rows;
    }

    public function getInstanceProfilesList()
    {
        $list = array();

        try {
            /* @var $instanceProfileData \Scalr\Service\Aws\Iam\DataType\InstanceProfileData */
            foreach ($this->getEnvironment()->aws('us-east-1')->iam->instanceProfile->describe() as $instanceProfileData) {
                $list[] = array(
                    'arn' => $instanceProfileData->arn,
                    'name' => $instanceProfileData->instanceProfileName
                );
            }
        } catch (Exception $e) {}

        return $list;
    }

    /**
     * @param string $cloudLocation
     */
    public function xGetKmsKeysListAction($cloudLocation)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $keys = [];
        foreach ($aws->kms->alias->list() as $key) {
            $keys[] = [
                'id' => $key->targetKeyId,
                'alias' => $key->aliasName,
            ];
        }
        $this->response->data([
            'keys' => $keys
        ]);
    }
}
