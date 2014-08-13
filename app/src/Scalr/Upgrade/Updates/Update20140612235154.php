<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Modules\PlatformFactory;

class Update20140612235154 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'e38a31ae-dd51-4117-9abc-153db3ae173f';

    protected $depends = [];

    protected $description = 'VPC version 2 migration fixes';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function run1($stage)
    {
        $farms = $this->db->Execute("SELECT farmid FROM farm_settings WHERE name='ec2.vpc.id' AND value != '' AND value IS NOT NULL");
        while ($farm = $farms->FetchRow()) {
            $dbFarm = \DBFarm::LoadByID($farm['farmid']);
            $routerRole = $dbFarm->GetFarmRoleByBehavior(\ROLE_BEHAVIORS::VPC_ROUTER);
            if ($routerRole) {
                if (!$routerRole->GetSetting(\Scalr_Role_Behavior_Router::ROLE_VPC_NID)) {
                    $server = $routerRole->GetServersByFilter(array('status' => \SERVER_STATUS::RUNNING));
                    if ($server[0]) {
                        $platform = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::EC2);
                        $info = $platform->GetServerExtendedInformation($server[0]);
                        if ($info['Network Interface']) {
                            $this->console->out("Updating router.vpc.networkInterfaceId property for Farm Role: %s", $routerRole->ID);
                            $routerRole->SetSetting(\Scalr_Role_Behavior_Router::ROLE_VPC_NID, $info['Network Interface']);
                        }
                    }
                }
            }
        }
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function run2($stage)
    {
        $farms = $this->db->Execute("SELECT farmid, value FROM farm_settings WHERE name='ec2.vpc.id' AND value != '' AND value IS NOT NULL");
        while ($farm = $farms->FetchRow()) {
            $dbFarm = \DBFarm::LoadByID($farm['farmid']);
            $roles = $dbFarm->GetFarmRoles();
            foreach ($roles as $dbFarmRole) {
                $vpcSubnetId = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_AWS_VPC_SUBNET_ID);
                if ($vpcSubnetId && substr($vpcSubnetId, 0, 6) != 'subnet') {
                    $subnets = json_decode($vpcSubnetId);
                    $vpcSubnetId = $subnets[0];
                }

                if ($vpcSubnetId) {
                    try {
                        $platform = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::EC2);
                        $info = $platform->listSubnets(
                            \Scalr_Environment::init()->loadById($dbFarm->EnvID),
                            $dbFarmRole->CloudLocation, $farm['value'], true, $vpcSubnetId);
                        if ($info && $info['type'] != 'public') {
                            $routerRole = $dbFarm->GetFarmRoleByBehavior(\ROLE_BEHAVIORS::VPC_ROUTER);
                            $dbFarmRole->SetSetting(\Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID, $routerRole->ID);
                            $this->console->out("Updating router.scalr.farm_role_id property for Farm Role: %s", $dbFarmRole->ID);
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
    }
}