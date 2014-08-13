<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;
use Scalr\Modules\PlatformFactory;

class Update20140211014306 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'd88a492d-b65f-4764-bbb1-f3746be61e10';

    protected $depends = array();

    protected $description = 'Update openstack settings';

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
        return false;
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

    private function getOpenStackOption($platform, $name)
    {
        return $platform . "." . constant("Scalr\\Modules\\Platforms\\Openstack\\OpenstackPlatformModule::" . $name);
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
        $environments = $this->db->Execute("SELECT id FROM client_environments WHERE status='Active'");
        while ($env = $environments->FetchRow()) {
            $environment = \Scalr_Environment::init()->loadById($env['id']);
            foreach (PlatformFactory::getOpenstackBasedPlatforms() as $platform) {
                if ($platform == \SERVER_PLATFORMS::RACKSPACENG_UK || $platform == \SERVER_PLATFORMS::RACKSPACENG_US)
                    continue;

                try {
                    if ($environment->isPlatformEnabled($platform)) {
                        $os = $environment->openstack($platform);
                        //It throws an exception on failure
                        $zones = $os->listZones();
                        $zone = array_shift($zones);

                        $os = $environment->openstack($platform, $zone->name);

                        // Check SG Extension
                        $pars[$this->getOpenStackOption($platform, 'EXT_SECURITYGROUPS_ENABLED')] = (int)$os->servers->isExtensionSupported(ServersExtension::securityGroups());

                        // Check Floating Ips Extension
                        $pars[$this->getOpenStackOption($platform, 'EXT_FLOATING_IPS_ENABLED')] = (int)$os->servers->isExtensionSupported(ServersExtension::floatingIps());

                        // Check Cinder Extension
                        $pars[$this->getOpenStackOption($platform, 'EXT_CINDER_ENABLED')] = (int)$os->hasService('volume');

                        // Check Swift Extension
                        $pars[$this->getOpenStackOption($platform, 'EXT_SWIFT_ENABLED')] = (int)$os->hasService('object-store');

                        // Check LBaas Extension
                        $pars[$this->getOpenStackOption($platform, 'EXT_LBAAS_ENABLED')] = $os->hasService('network') ? (int)$os->network->isExtensionSupported('lbaas') : 0;

                        $environment->setPlatformConfig($pars);
                    }
                } catch (\Exception $e) {
                    $this->console->out("Update settings for env: {$env['id']} failed: ". $e->getMessage());
                }
            }
        }
    }
}