<?php

use Scalr\Service\Aws\Ec2\DataType\AssociateAddressRequestData;
use Scalr\Service\Aws\Ec2\DataType\AddressFilterNameType;
use Scalr\Model\Entity;

class Scalr_Role_Behavior_Router extends Scalr_Role_Behavior implements Scalr_Role_iBehavior
{
    const ROLE_VPC_NID               = 'router.vpc.networkInterfaceId';
    const ROLE_VPC_IP                = 'router.vpc.ip';
    const ROLE_VPC_AID               = 'router.vpc.ipAllocationId';
    const ROLE_VPC_ROUTER_CONFIGURED = 'router.vpc.configured';
    const ROLE_VPC_NAT_ENABLED       = 'router.vpc.nat_enabled';
    const ROLE_VPC_SCALR_ROUTER_ID   = 'router.scalr.farm_role_id';

    const INTERNET_ACCESS_FULL = 'full';
    const INTERNET_ACCESS_OUTBOUND = 'outbound-only';

    public function __construct($behaviorName)
    {
        parent::__construct($behaviorName);
    }

    public function getSecurityRules()
    {
        return array();
    }

    public function createSubnet($type) {

    }

    public function onFarmSave(DBFarm $dbFarm, DBFarmRole $dbFarmRole)
    {
        $vpcId = $dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_ID);
        if (!$vpcId) {
            //REMOVE VPC RELATED SETTINGS
            return;
        }

        if ($dbFarmRole->GetSetting(self::ROLE_VPC_ROUTER_CONFIGURED) == 1) {
            // ALL OBJECTS ALREADY CONFIGURED
            return true;
        }

        $aws = $dbFarm->GetEnvironmentObject()->aws($dbFarmRole->CloudLocation);

        $niId = $dbFarmRole->GetSetting(self::ROLE_VPC_NID);


        // If there is no public IP allocate it and associate with NI
        $publicIp = $dbFarmRole->GetSetting(self::ROLE_VPC_IP);
        if ($niId && !$publicIp) {

            $filter = array(array(
                'name'  => AddressFilterNameType::networkInterfaceId(),
                'value' => $niId,
            ));

            $addresses = $aws->ec2->address->describe(null, null, $filter);
            $address = $addresses->get(0);
            $associate = false;
            if (!$address) {
                $address = $aws->ec2->address->allocate('vpc');
                $associate = true;
            }

            $publicIp = $address->publicIp;

            if ($associate) {
                $associateAddressRequestData = new AssociateAddressRequestData();
                $associateAddressRequestData->networkInterfaceId = $niId;
                $associateAddressRequestData->allocationId = $address->allocationId;
                $associateAddressRequestData->allowReassociation = true;

                //Associate PublicIP with NetworkInterface
                $aws->ec2->address->associate($associateAddressRequestData);
            }

            $dbFarmRole->SetSetting(self::ROLE_VPC_IP, $publicIp, Entity\FarmRoleSetting::TYPE_LCL);
            $dbFarmRole->SetSetting(self::ROLE_VPC_AID, $address->allocationId, Entity\FarmRoleSetting::TYPE_LCL);
        }

        $dbFarmRole->SetSetting(self::ROLE_VPC_ROUTER_CONFIGURED, 1, Entity\FarmRoleSetting::TYPE_LCL);
    }


    /**
     * {@inheritdoc}
     * @see Scalr_Role_Behavior::getConfiguration()
     */
    public function getConfiguration(DBServer $dbServer)
    {
        $router = new stdClass();

        // Set scalr address
        $router->scalrAddr =
            \Scalr::config('scalr.endpoint.scheme') . "://" .
            \Scalr::config('scalr.endpoint.host');

        // Set scalr IPs whitelist
        $router->whitelist = \Scalr::config('scalr.aws.ip_pool');

        // Set CIDR
        $router->cidr = '10.0.0.0/8';

        return $router;
    }

    public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
    {
        $message = parent::extendMessage($message, $dbServer);

        switch (get_class($message)) {
            case "Scalr_Messaging_Msg_HostInitResponse":
                $message->router = $this->getConfiguration($dbServer);
        }

        return $message;
    }

    public static function setupBehavior(Entity\FarmRole $farmRole)
    {
        $farm = $farmRole->farm;
        $vpcId = $farm->settings[Entity\FarmSetting::EC2_VPC_ID];
        if (!$vpcId) {
            //REMOVE VPC RELATED SETTINGS
            return;
        }

        if ($farmRole->settings[self::ROLE_VPC_ROUTER_CONFIGURED] == 1) {
            // ALL OBJECTS ALREADY CONFIGURED
            return;
        }

        $aws = $farm->GetEnvironmentObject()->aws($farmRole->CloudLocation);

        $niId = $farmRole->settings[self::ROLE_VPC_NID];


        // If there is no public IP allocate it and associate with NI
        $publicIp = $farmRole->settings[self::ROLE_VPC_IP];
        if ($niId && !$publicIp) {

            $filter = array(array(
                'name'  => AddressFilterNameType::networkInterfaceId(),
                'value' => $niId,
            ));

            $addresses = $aws->ec2->address->describe(null, null, $filter);
            $address = $addresses->get(0);
            $associate = false;
            if (!$address) {
                $address = $aws->ec2->address->allocate('vpc');
                $associate = true;
            }

            $publicIp = $address->publicIp;

            if ($associate) {
                $associateAddressRequestData = new AssociateAddressRequestData();
                $associateAddressRequestData->networkInterfaceId = $niId;
                $associateAddressRequestData->allocationId = $address->allocationId;
                $associateAddressRequestData->allowReassociation = true;

                //Associate PublicIP with NetworkInterface
                $aws->ec2->address->associate($associateAddressRequestData);
            }

            $farmRole->settings[static::ROLE_VPC_IP] = (new Entity\FarmRoleSetting())->setValue($publicIp)->setType(Entity\FarmRoleSetting::TYPE_LCL);
            $farmRole->settings[static::ROLE_VPC_AID] = (new Entity\FarmRoleSetting())->setValue($address->allocationId)->setType(Entity\FarmRoleSetting::TYPE_LCL);
        }

        $farmRole->settings[static::ROLE_VPC_ROUTER_CONFIGURED] = (new Entity\FarmRoleSetting())->setValue(1)->setType(Entity\FarmRoleSetting::TYPE_LCL);
    }
}