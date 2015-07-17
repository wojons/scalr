<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140128205641 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '6cac5c0b-60b6-4c26-82af-8be586283e26';

    protected $depends = array();

    protected $description = 'Set owner for servers';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    /**
     * Checks whether the update of the stage ONE is applied.
     *
     * Verifies whether current update has already been applied to this install.
     * This ensures avoiding the duplications. Implementation of this method should give
     * the definite answer to question "has been this update applied or not?".
     *
     * @param   int  $stage  optional The stage number
     * @return  bool Returns true if the update has already been applied.
     */
    protected function isApplied1($stage)
    {
        return class_exists('FarmRoleService');
    }

    /**
     * Validates an environment before it will try to apply the update of the stage ONE.
     *
     * Validates current environment or inspects circumstances that is expected to be in the certain state
     * before the update is applied. This method may not be overridden from AbstractUpdate class
     * which means current update is always valid.
     *
     * @param   int  $stage  optional The stage number
     * @return  bool Returns true if the environment meets the requirements.
     */
    protected function validateBefore1($stage)
    {
        return true;
    }

    /**
     * Performs upgrade literally for the stage ONE.
     *
     * Implementation of this method performs update steps needs to be taken
     * to accomplish upgrade successfully.
     *
     * If there are any error during an execution of this scenario it must
     * throw an exception.
     *
     * @param   int  $stage  optional The stage number
     * @throws  \Exception
     */
    protected function run1($stage)
    {
        // remove zomby records
        $this->db->Execute("DELETE FROM farm_role_settings WHERE name='lb.use_elb' AND value='0'");
        $settings = $this->db->Execute("SELECT farm_roleid FROM farm_role_settings WHERE name='lb.use_elb' AND value='1'");
        while ($farmRoleInfo = $settings->FetchRow()) {
            $dbFarmRole = \DBFarmRole::LoadByID($farmRoleInfo['farm_roleid']);
            $name = $dbFarmRole->GetSetting(\DBFarmRole::SETTING_BALANCING_NAME);
            if ($name) {
                $service = FarmRoleService::findFarmRoleService($dbFarmRole->GetFarmObject()->EnvID, $name);
                if (!$service) {
                    $dbFarmRole->SetSetting(\DBFarmRole::SETTING_AWS_ELB_ENABLED, 1);
                    $dbFarmRole->SetSetting(\DBFarmRole::SETTING_AWS_ELB_ID, $name);

                    // Setup new service
                    // ADD ELB to role_cloud_services
                    $service = new FarmRoleService($dbFarmRole, $name);
                    $service->setType(FarmRoleService::SERVICE_AWS_ELB);
                    $service->save();

                    $dbFarmRole->SetSetting(\DBFarmRole::SETTING_BALANCING_NAME, null, \DBFarmRole::TYPE_LCL);
                    $dbFarmRole->SetSetting(\DBFarmRole::SETTING_BALANCING_HOSTNAME, null, \DBFarmRole::TYPE_LCL);
                    $dbFarmRole->SetSetting(\DBFarmRole::SETTING_BALANCING_USE_ELB, null, \DBFarmRole::TYPE_LCL);
                    $dbFarmRole->SetSetting(\DBFarmRole::SETTING_BALANCING_HC_HASH, null, \DBFarmRole::TYPE_LCL);
                    $dbFarmRole->ClearSettings("lb.avail_zone");
                    $dbFarmRole->ClearSettings("lb.healthcheck");
                    $dbFarmRole->ClearSettings("lb.role.listener");

                    $this->console->out("Fixed ELB config for FarmRole #{$dbFarmRole->ID} of Farm #{$dbFarmRole->GetFarmObject()->ID}");
                } else {
                    $this->console->out("Found conflict with ELB: {$name}");
                }
            } else
                $dbFarmRole->ClearSettings('lb.');
        }

        $services = $this->db->Execute("SELECT * FROM farm_role_cloud_services");
        while ($service = $services->FetchRow()) {
            $dbFarmRole = \DBFarmRole::LoadByID($service['farm_role_id']);
            $dbFarmRole->SetSetting(\DBFarmRole::SETTING_AWS_ELB_ENABLED, 1);
            $dbFarmRole->SetSetting(\DBFarmRole::SETTING_AWS_ELB_ID, $service['id']);
        }
    }
}