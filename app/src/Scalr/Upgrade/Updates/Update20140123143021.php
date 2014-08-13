<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;

class Update20140123143021 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '9c9f6b83-f8e9-4c15-8514-543c276bfea0';

    protected $depends = array();

    protected $description = 'Validates AWS Account numbers for an each EC2 platforms';

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
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isApplied()
     */
    public function isApplied($stage = null)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('client_environments') &&
               $this->hasTable('client_environment_properties') &&
               $this->hasTableColumn('client_environment_properties', 'name') &&
               $this->hasTableColumn('client_environment_properties', 'value');
    }

    protected function run1($stage)
    {
        $this->console->out("Retrieving all environments");

        $res = $this->db->Execute("
            SELECT e.id FROM client_environments e
            JOIN client_environment_properties p ON e.id = p.env_id
            WHERE p.name = ? AND p.value != ''
        ", array(
            Ec2PlatformModule::ACCESS_KEY
        ));
        while ($rec = $res->FetchRow()) {
            $env = \Scalr_Environment::init()->loadById($rec['id']);
            if (!($env instanceof \Scalr_Environment)) continue;
            $accountNumber = $env->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID);
            try {
                $num = $env->aws()->getAccountNumber();
                if ($num != $accountNumber) {
                    $this->console->out('Updating account_number for %d environment: "%s" -> "%s"', $env->id, $accountNumber, $num);
                    $env->setPlatformConfig(array(
                        Ec2PlatformModule::ACCOUNT_ID => $num,
                    ));
                }
            } catch (\Exception $e) {
                $this->console->warning("Environment %s fails: %s", $env->id, $e->getMessage());
            }
        }
    }
}