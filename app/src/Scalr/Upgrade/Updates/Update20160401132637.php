<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Model\Entity\Server;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use SERVER_PLATFORMS;

class Update20160401132637 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '8eeefd4b-5231-44ec-91ff-d4c7670cf92d';

    protected $depends = [];

    protected $description = "Initialize Vcpu for running GCE instances.";

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $envs = [];

        $platform = SERVER_PLATFORMS::GCE;

        $platformModule = PlatformFactory::NewPlatform($platform);
        /* @var $platformModule GoogleCEPlatformModule*/
        $result = $this->db->Execute("
            SELECT s.`server_id`, s.`cloud_location`, s.`type`, s.`env_id`, sp.`value` AS vcpus
            FROM servers AS s
            LEFT JOIN server_properties sp ON sp.`server_id`= s.`server_id` AND sp.`name` = ?
            WHERE s.`status` NOT IN (?, ?)
            AND s.`type` IS NOT NULL
            AND s.`platform` = ?
        ", [Server::INFO_INSTANCE_VCPUS, Server::STATUS_PENDING_TERMINATE, Server::STATUS_TERMINATED, $platform]);

        while ($row = $result->FetchRow()) {
            if (!empty($row["type"]) && empty($row['vcpus'])) {
                if (!array_key_exists($row["env_id"], $envs)) {
                    $envs[$row["env_id"]] = \Scalr_Environment::init()->loadById($row["env_id"]);
                }

                try {
                    $instanceTypeInfo = $platformModule->getInstanceType(
                        $row["type"],
                        $envs[$row["env_id"]],
                        $row["cloud_location"]
                    );

                    if ($instanceTypeInfo instanceof CloudInstanceType) {
                        $vcpus = $instanceTypeInfo->vcpus;
                    } else {
                        trigger_error("Value of vcpus for instance type " . $row["type"] . " is missing for platform " . $platform, E_USER_WARNING);
                        $vcpus = 0;
                    }

                    if ((int) $vcpus > 0) {
                        $this->db->Execute("
                            INSERT INTO server_properties (`server_id`, `name`, `value`) VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE `value` = ?
                        ", [
                            $row["server_id"],
                            Server::INFO_INSTANCE_VCPUS,
                            $vcpus,
                            $vcpus
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->console->warning("Can't get access to %s, error: %s", $platform, $e->getMessage());
                }
            }
        }
    }
}