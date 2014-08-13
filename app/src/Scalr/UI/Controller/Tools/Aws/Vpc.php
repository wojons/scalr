<?php

use Scalr\Service\Aws\Ec2\DataType\SubnetFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\NetworkInterfaceFilterNameType;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\Aws\Ec2\DataType\CreateNetworkInterfaceRequestData;
use Scalr\Service\Aws\Ec2\DataType\AssociateAddressRequestData;
use Scalr\Service\Aws\Ec2\DataType\IpPermissionData;
use Scalr\Service\Aws\Ec2\DataType\IpRangeList;
use Scalr\Service\Aws\Ec2\DataType\IpRangeData;
use Scalr\Service\Aws\Ec2\DataType\NetworkInterfaceAttributeType;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupFilterNameType;

class Scalr_UI_Controller_Tools_Aws_Vpc extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        $enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();
        if (!in_array(SERVER_PLATFORMS::EC2, $enabledPlatforms))
            throw new Exception("You need to enable EC2 platform for current environment");

        return true;
    }

    public function createSubnetAction()
    {
        $ec2 = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->ec2;
        $subnetLength = 24;

        $subnetsList = $ec2->subnet->describe(null, array(array(
            'name'  => SubnetFilterNameType::vpcId(),
            'value' => $this->getParam('vpcId')
        )));
        $subnets = array();
        foreach ($subnetsList as $subnet) {
            @list($ip, $len) = explode('/', $subnet->cidrBlock);
            $subnets[] = array('min' => ip2long($ip), 'max' => (ip2long($ip) | (1<<(32-$len))-1));
        }

        $vpcInfo = $ec2->vpc->describe($this->getParam('vpcId'));
        /* @var $vpc \Scalr\Service\Aws\Ec2\DataType\VpcData */
        $vpc = $vpcInfo->get(0);

        $info = explode("/", $vpc->cidrBlock);
        $startIp = ip2long($info[0]);
        $maxIp = ($startIp | (1<<(32-$info[1]))-1);
        while ($startIp < $maxIp) {
            $sIp = $startIp;
            $eIp = ($sIp | (1<<(32-$subnetLength))-1);
            foreach ($subnets as $subnet) {
                $checkRange = ($subnet['min'] <= $sIp) && ($sIp <= $subnet['max']) && ($subnet['min'] <= $eIp) && ($eIp <= $subnet['max']);
                if ($checkRange)
                    break;
            }
            if ($checkRange) {
                $startIp = $eIp+1;
            } else {
                $subnetIp = long2ip($startIp);
                break;
            }
        }

        if (!$subnetIp)
            throw new Exception("You don't have free space in your VPC network ({$vpc->cidrBlock}) to create additional subnets");

        $this->response->page('ui/tools/aws/vpc/createSubnet.js', array(
            'subnet' => "{$subnetIp}/{$subnetLength}",
            'debug' => array(
                'vpcCIDR' => $vpc->cidrBlock,
                'subnets' => $subnets,
                'maxIp' => array(long2ip($maxIp), $maxIp)
            )
        ));
    }

    public function xCreateSubnetAction()
    {
        $ec2 = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->ec2;

        $subnet = $ec2->subnet->create($this->getParam('vpcId'), $this->getParam('subnet'), $this->getParam('availZone'));

        //associate route table
        if ($this->getParam('routeTableId')) {
            try {
                $ec2->routeTable->associate($this->getParam('routeTableId'), $subnet->subnetId);
            } catch (Exception $e) {
                $subnet->delete();
                throw new $e;
            }
        }

        // set name tag
        if ($this->getParam('name')) {
            try {
                $subnet->createTags(array(
                    array('key' => "Name", 'value' => $this->getParam('name'))
                ));
            } catch (Exception $e) {}
        }

        //get subnet type
        $subnetType = null;

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
        $routingTables = $platform->getRoutingTables($ec2, $this->getParam('vpcId'));
        foreach ($routingTables as $table) {
            if (in_array($subnet->subnetId, $table['subnets']))
                $subnetType = $table['type'];

            if ($table['main'])
                $mainTableType = $table['type'];
        }

        if (!$subnetType)
            $subnetType = $mainTableType;

        $this->response->data(array('subnet' => array(
            'id'                => $subnet->subnetId,
            'description'       => "{$subnet->subnetId} ({$subnet->cidrBlock} in {$subnet->availabilityZone})",
            'cidr'              => $subnet->cidrBlock,
            'availability_zone' => $subnet->availabilityZone,
            'ips_left'          => $subnet->availableIpAddressCount,
            'type'              => $subnetType
        )));
    }

    public function xListRoutingTablesAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
        $tables = $platform->getRoutingTables($aws->ec2, $this->getParam('vpcId'));

        $this->response->data(array('success' => true, 'data' => $tables));
    }

    public function xListScalrRoutersAction()
    {
        $vpcId = $this->getParam('vpcId');

        $farmRoles = $this->db->Execute("SELECT farm_roleid, value FROM farm_role_settings WHERE name=? AND value != ''", array(
            Scalr_Role_Behavior_Router::ROLE_VPC_NID
        ));
        $retval = array();
        while ($farmRole = $farmRoles->FetchRow()) {
            $dbFarmRole = DBFarmRole::LoadByID($farmRole['farm_roleid']);
            if ($dbFarmRole->GetFarmObject()->GetSetting(DBFarm::SETTING_EC2_VPC_ID) == $vpcId) {
                $itm = array(
                    'farm_role_id' => $dbFarmRole->ID,
                    'nid' => $farmRole['value'],
                    'ip' => "Not Running",
                    'farm_name' => $dbFarmRole->GetFarmObject()->Name,
                    'role_name' => $dbFarmRole->GetRoleObject()->name
                );
                $servers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
                if (count($servers) > 0) {
                    $router = $servers[0];
                    $itm['ip'] = $router->remoteIp;
                }

                $retval[] = $itm;
            }
        }

        $this->response->data(array('success' => true, 'data' => $retval));
    }

    public function xListNetworkInterfacesAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $filter = array(array(
            'name'  => NetworkInterfaceFilterNameType::subnetId(),
            'value' => $this->getParam('subnetId')
        ));

        $networkInterfaces = $aws->ec2->networkInterface->describe(null, $filter);
        $retval = array();
        /* @var $ni \Scalr\Service\Aws\Ec2\DataType\NetworkInterfaceData  */
        foreach ($networkInterfaces as $ni) {

            if ($ni->association->publicIp && !$ni->sourceDestCheck && $ni->status == 'available') {
                $itm = array(
                    'publicIp' => $ni->association->publicIp,
                    'id' => $ni->networkInterfaceId
                );

                $retval[] = $itm;
            }
        }

        $this->response->data(array('data' => $retval));
    }

    public function xListSubnetsAction()
    {
        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
        $retval = $platform->listSubnets(
            $this->getEnvironment(),
            $this->getParam('cloudLocation'),
            $this->getParam('vpcId'),
            $this->getParam('extended')
        );

        $this->response->data(array('success' => true, 'data' => $retval));
    }

    public function createAction()
    {
        $this->response->page('ui/tools/aws/vpc/create.js');
    }

    public function xCreateAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));
        $vpc = $aws->ec2->vpc->create($this->getParam('cidr_block'), $this->getParam('tenancy'));

        // set name tag
        if ($this->getParam('name')) {
            try {
                $vpc->createTags(array(
                    array('key' => "Name", 'value' => $this->getParam('name'))
                ));
            } catch (Exception $e) {}
        }

        $this->response->success('VPC successfully created');
        $this->response->data(array(
            'vpc' => array(
                'id' => $vpc->vpcId,
                'name' => $vpc->vpcId
            )
        ));
    }


    public function createNetworkInterfaceAction()
    {
        $ec2 = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->ec2;

        /* @var $subnet \Scalr\Service\Aws\Ec2\DataType\SubnetData */
        $subnet = $ec2->subnet->describe($this->getParam('subnetId'))->get();
        if ($subnet) {
            $subnet = array(
                'subnetId' => $subnet->subnetId,
                'availabilityZone' => $subnet->availabilityZone,
                'cidrBlock' => $subnet->cidrBlock
            );
        } else {
            throw new Exception("Subnet is not found");
        }

        $this->response->page('ui/tools/aws/vpc/createNetworkInterface.js', array(
            'subnet' => $subnet
        ));
    }

    public function xCreateNetworkInterfaceAction()
    {
        $ec2 = $this->getEnvironment()->aws($this->getParam('cloudLocation'))->ec2;

        try {
            $subnetId = $this->getParam('subnetId');
            $vpcId = $this->getParam('vpcId');

            $vpcInfo = $ec2->vpc->describe($vpcId);
            /* @var $vpc \Scalr\Service\Aws\Ec2\DataType\VpcData */
            $vpc = $vpcInfo->get(0);

            //Create Network interface
            $createNetworkInterfaceRequestData = new CreateNetworkInterfaceRequestData($subnetId);
            $routerSgName = Scalr::config('scalr.aws.security_group_prefix') . 'vpc-router';

            // Check and create security group
            $filter = array(
                array(
                    'name' => SecurityGroupFilterNameType::groupName(),
                    'value' => array(
                        $routerSgName,
                        'SCALR-VPC'
                    )
                ),
                array(
                    'name'  => SecurityGroupFilterNameType::vpcId(),
                       'value' => $vpcId
                   )
            );
            try {
                $list = $ec2->securityGroup->describe(null, null, $filter);
                if ($list->count() > 0 && in_array($list->get(0)->groupName, array('SCALR-VPC', $routerSgName)))
                    $sgId = $list->get(0)->groupId;

            } catch (Exception $e) {
                throw new Exception("Cannot get list of security groups (1): {$e->getMessage()}");
            }

            if (!$sgId) {
                $sgId = $aws->ec2->securityGroup->create($routerSgName, 'System SG for Scalr VPC integration', $vpcId);

                $ipRangeList = new IpRangeList();
                $ipRangeList->append(new IpRangeData('0.0.0.0/0'));

                $ipRangeListLocal = new IpRangeList();
                $ipRangeListLocal->append(new IpRangeData($vpc->cidrBlock));

                $attempts = 0;
                while (true) {
                    $attempts++;
                    try {
                        $aws->ec2->securityGroup->authorizeIngress(array(
                            new IpPermissionData('tcp', 8008, 8013, $ipRangeList),
                            new IpPermissionData('tcp', 80, 80, $ipRangeList),
                            new IpPermissionData('tcp', 443, 443, $ipRangeList),
                            new IpPermissionData('tcp', 0, 65535, $ipRangeListLocal),
                            new IpPermissionData('udp', 0, 65535, $ipRangeListLocal)
                        ), $sgId);

                        break;

                    } catch (Exception $e) {
                        if ($attempts >= 3)
                            throw $e;
                        else
                            sleep(1);
                    }
                }
            }

            $createNetworkInterfaceRequestData->setSecurityGroupId(array(
                'groupId' => $sgId
            ));

            $networkInterface = $ec2->networkInterface->create($createNetworkInterfaceRequestData);

            // Disable sourceDeskCheck
            $networkInterface->modifyAttribute(NetworkInterfaceAttributeType::sourceDestCheck(), 0);
            $niId = $networkInterface->networkInterfaceId;

            $attemptsCounter = 0;
            while (true) {
                try {
                    $networkInterface->createTags(array(
                        array('key' => "scalr-id", 'value' => SCALR_ID),
                        array('key' => "Name", 'value' => "VPC Router ENI")
                    ));
                    break;
                } catch (Exception $e) {
                    $attemptsCounter++;
                    if ($attemptsCounter < 5) {
                        sleep(1);
                        continue;
                    } else {
                        throw new Exception($e->getMessage());
                    }
                }
                break;
            }

            //ASSOCIATE PUBLIC IP

            $address = $ec2->address->allocate('vpc');
            $publicIp = $address->publicIp;

            $associateAddressRequestData = new AssociateAddressRequestData();
            $associateAddressRequestData->networkInterfaceId = $niId;
            $associateAddressRequestData->allocationId = $address->allocationId;
            $associateAddressRequestData->allowReassociation = true;

            //Associate PublicIP with NetworkInterface
            $ec2->address->associate($associateAddressRequestData);
        } catch (Exception $e) {
            if ($niId)
                $ec2->networkInterface->delete($niId);

            if ($publicIp)
                $ec2->address->release(null, $address->allocationId);

            throw $e;
        }

        $this->response->success('Network interface successfully created');
        $this->response->data(array(
            'ni' => array(
                'id' => $niId,
                'publicIp' => $publicIp
            )
        ));
    }


}
