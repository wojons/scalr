<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\Server;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use SERVER_PLATFORMS;

class Update20160301153358 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '5873fc0a-b5e2-4fe4-9cc4-1cf6e3d52419';

    protected $depends = [];

    protected $description = "Initializing vcpus property for running ec2 servers";

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
        $platform = SERVER_PLATFORMS::EC2;

        $platformModule = PlatformFactory::NewPlatform($platform);
        /* @var $platformModule Ec2PlatformModule*/
        $instanceTypes = $platformModule->getInstanceTypes(null, null, true);

        $result = $this->db->Execute("
            SELECT s.server_id, s.`type`, sp.`value` AS vcpus
            FROM servers AS s
            LEFT JOIN server_properties sp ON sp.`server_id`= s.`server_id` AND sp.`name` = ?
            WHERE s.`status` NOT IN (?, ?)
            AND s.`type` IS NOT NULL
            AND s.`platform` = ?
        ", [Server::INFO_INSTANCE_VCPUS, Server::STATUS_PENDING_TERMINATE, Server::STATUS_TERMINATED, $platform]);

        while ($row = $result->FetchRow()) {
            if (!empty($row["type"]) && empty($row['vcpus'])) {
                if (isset($instanceTypes[$row['type']]['vcpus']) && $instanceTypes[$row['type']]['vcpus'] > 0) {
                    $this->db->Execute("
                        INSERT INTO server_properties (`server_id`, `name`, `value`) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE `value` = ?
                    ", [
                        $row["server_id"],
                        Server::INFO_INSTANCE_VCPUS,
                        $instanceTypes[$row['type']]['vcpus'],
                        $instanceTypes[$row['type']]['vcpus']
                    ]);
                }
            }
        }
    }
}
