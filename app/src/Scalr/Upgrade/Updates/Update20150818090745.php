<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Modules\PlatformFactory;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Server;
use Scalr_Environment;
use SERVER_PLATFORMS;

class Update20150818090745 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '5ac2b878-36af-4f6e-aa17-7c2ce7eded40';

    protected $depends = [];

    protected $description = "Adding table to store platform statistics";

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
        return $this->hasTable("platform_usage");
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Creating table");
        $this->db->Execute("
            CREATE TABLE `platform_usage` (
                `time` DATETIME NOT NULL COMMENT 'Start time of an interval',
                `platform` VARCHAR(20) NOT NULL COMMENT 'Platform name',
                `value` INT(10) UNSIGNED NOT NULL COMMENT 'The value of a sensor',
                PRIMARY KEY (`time`, `platform`),
                INDEX `idx_time` (`time` ASC)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT 'Platform usage statistics'
        ");
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return true;
    }

    private function getEnabledPlatforms(Scalr_Environment $environment)
    {
        $enabled = [];

        foreach (array_keys(SERVER_PLATFORMS::getList()) as $platform) {
            $value = $this->db->GetOne("
                SELECT value
                FROM client_environment_properties
                WHERE env_id = ? AND `group` = ''
                AND name = ?
                LIMIT 1
            ", [$environment->id, $platform . '.is_enabled']);

            if (!empty($value)) {
                $enabled[] = $platform;
            }
        }

        return $enabled;
    }

    private function isPlatformEnabled(Scalr_Environment $env, $platform)
    {
        return in_array($platform, $this->getEnabledPlatforms($env));
    }

    protected function run2($stage)
    {
        $this->console->out("Populating new properties");
        $platforms = $envs = [];

        foreach (array_keys(\SERVER_PLATFORMS::GetList()) as $platform) {
            $platforms[$platform] = PlatformFactory::NewPlatform($platform);
        }

        $result = $this->db->Execute("
                SELECT s.server_id, s.`platform`, s.`cloud_location`, s.env_id, s.`type`
                FROM servers AS s
                WHERE s.`status` NOT IN (?, ?) AND s.`type` IS NOT NULL
            ", [Server::STATUS_PENDING_TERMINATE, Server::STATUS_TERMINATED]
        );

        while ($row = $result->FetchRow()) {
            if (!empty($row["type"])) {
                if (!array_key_exists($row["env_id"], $envs)) {
                    $envs[$row["env_id"]] = \Scalr_Environment::init()->loadById($row["env_id"]);
                }

                if ($this->isPlatformEnabled($envs[$row["env_id"]], $row["platform"])) {
                    try {
                        $instanceTypeInfo = $platforms[$row["platform"]]->getInstanceType(
                            $row["type"],
                            $envs[$row["env_id"]],
                            $row["cloud_location"]
                        );

                        if ($instanceTypeInfo instanceof CloudInstanceType) {
                            $vcpus = $instanceTypeInfo->vcpus;
                        } else if (isset($instanceTypeInfo['vcpus'])) {
                            $vcpus = $instanceTypeInfo['vcpus'];
                        } else {
                            trigger_error("Value of vcpus for instance type " . $row["type"] . " is missing for platform " . $row["platform"], E_USER_WARNING);
                            $vcpus = 0;
                        }

                        if ((int) $vcpus > 0) {
                            $this->db->Execute("
                                INSERT IGNORE INTO server_properties (`server_id`, `name`, `value`) VALUES (?, ?, ?)
                            ", [
                                $row["server_id"],
                                Server::INFO_INSTANCE_VCPUS,
                                $vcpus
                            ]);
                        }
                    } catch (\Exception $e) {
                        $this->console->warning("Can't get access to %s, error: %s", $row["platform"], $e->getMessage());
                    }
                }
            }
        }
    }
}
