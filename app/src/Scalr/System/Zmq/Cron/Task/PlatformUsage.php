<?php

namespace Scalr\System\Zmq\Cron\Task;

use Scalr\Model\Entity\Server;
use Scalr\System\Zmq\Cron\AbstractTask;
use ArrayObject;

/**
 * Collecting VCPU usage
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 */
class PlatformUsage extends AbstractTask
{
    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $plt = [];
        $args = [date("Y-m-d H:00:00"), Server::STATUS_RUNNING];
        foreach (array_keys(\SERVER_PLATFORMS::GetList()) as $platform) {
            $plt[] = "SELECT CONVERT(? USING latin1) AS `platform`";
            $args[] = $platform;
        }

        $args[] = Server::INFO_INSTANCE_VCPUS;

        \Scalr::getDb()->Execute("
            INSERT IGNORE INTO platform_usage (`time`, `platform`, `value`)
            SELECT ? AS `time`, p.`platform`, SUM(IF(s.status = ?, IFNULL(sp.`value`, 0), 0))
            FROM (" . implode(" UNION ALL ", $plt) . ") AS p
            LEFT JOIN servers AS s ON p.platform = s.platform
            LEFT JOIN server_properties AS sp ON s.server_id = sp.server_id AND sp.`name` = ?
            GROUP BY p.`platform`
            ", $args
        );

        return new ArrayObject([]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        return $request;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractTask::config()
     */
    public function config()
    {
        $config = parent::config();

        if ($config->daemon) {
            //Report a warning to log
            trigger_error(sprintf("Demonized mode is not allowed for '%s' job.", $this->name), E_USER_WARNING);

            //Forces normal mode
            $config->daemon = false;
        }

        if ($config->workers != 1) {
            //It cannot be performed through ZMQ MDP as execution time is more than heartbeat
            trigger_error(sprintf("It is allowed only one worker for the '%s' job.", $this->name), E_USER_WARNING);
            $config->workers = 1;
        }

        return $config;
    }
}
