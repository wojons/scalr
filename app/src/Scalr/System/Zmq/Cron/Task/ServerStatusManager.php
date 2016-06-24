<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject;
use DBServer;
use Exception;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr_Account;
use Scalr_Environment;
use SERVER_PROPERTIES;
use SERVER_STATUS;
use stdClass;

/**
 * Server status manager
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.1.0 (15.01.2015)
 */
class ServerStatusManager extends AbstractTask
{

    /**
     * Database handler
     *
     * @var \ADODB_mysqli
     */
    private $db;

    /**
     * Intervals of attempts to run instances
     *
     * @var string[]
     */
    private $attemptConfig = [];

    /**
     * ADODB prepared statement to fetch servers that it's time to launch
     *
     * @var \SQL
     */
    private $stmt;

    /**
     * Prepared query params
     *
     * @var array
     */
    private $params;

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (!empty($this->db)) {
            $this->db->Execute("DROP TEMPORARY TABLE IF EXISTS `temporary_server_status_check_config`;");
        }
    }

    /**
     * Prepares client to enqueue
     *
     * @throws Exception
     */
    public function prepare()
    {
        if (empty($this->db)) {
            $this->db = \Scalr::getDb();
        }

        if (empty($this->params)) {
            $this->prepareTemporary($this->parseIntervals($this->config()->intervals_attempts));
        }

        if (empty($this->stmt)) {
            $this->prepareStatement();
        }
    }

    /**
     * Normalizes intervals representations
     *
     * @param string[] $intervals Intervals
     *
     * @return int Returns intervals representations strings max length
     */
    public function parseIntervals(array $intervals)
    {
        $maxLength = 0;

        foreach ($intervals as $attempt => $interval) {
            preg_match_all('/(?:(?P<days>\d+)d)|(?:(?P<hours>\d+)h)|(?:(?P<minutes>\d+)m)|(?:(?P<seconds>\d+)s)/', $interval, $matches);

            $seconds = array_sum($matches['seconds']);
            $minutes = (int) floor($seconds / 60);
            $seconds = $seconds % 60;

            $minutes += array_sum($matches['minutes']);
            $hours = (int) floor($minutes / 60);
            $minutes = $minutes % 60;

            $hours += array_sum($matches['hours']);
            $days = (int) floor($hours / 24);
            $hours = $hours % 24;

            $days += array_sum($matches['days']);

            $interval = "{$days} {$hours}:{$minutes}:{$seconds}";

            $length = strlen($interval);
            if ($length > $maxLength) {
                $maxLength = $length;
            }

            $this->attemptConfig[$attempt] = $interval;
        }

        return $maxLength;
    }

    /**
     * Prepares temporary table
     *
     * @param int $maxLength Intervals representations strings max length
     */
    private function prepareTemporary($maxLength)
    {
        $maxLength = (int) $maxLength;

        $this->db->Execute("
        CREATE TEMPORARY TABLE IF NOT EXISTS `temporary_server_status_check_config` (
          `attempt` TINYINT UNSIGNED NOT NULL,
          `interval` CHAR({$maxLength}) NULL,
          PRIMARY KEY (`attempt`));
        ");

        $this->db->Execute('TRUNCATE TABLE `temporary_server_status_check_config`');

        $stmt = $this->db->Prepare("INSERT INTO `temporary_server_status_check_config` (`attempt`, `interval`) VALUES (?, ?)");

        foreach ($this->attemptConfig as $attempt => $interval) {
            $this->db->Execute($stmt, [$attempt, $interval]);
        }
    }

    /**
     * Prepares statement and params
     */
    private function prepareStatement()
    {
        $this->stmt = $this->db->Prepare("
            SELECT
              `s`.`server_id` AS `server_id`,
              `s`.`status`    AS `status`
            FROM `servers` AS `s`
              LEFT JOIN `server_properties` AS `attempt`
                ON `attempt`.`server_id` = `s`.`server_id` AND `attempt`.`name` = ?
              LEFT JOIN `server_properties` AS `last_try`
                ON `last_try`.`server_id` = `s`.`server_id` AND `last_try`.`name` = ?
              LEFT JOIN `temporary_server_status_check_config` AS `config`
                ON IF(`attempt`.`value` > ?, ?, `attempt`.`value`) = `config`.`attempt`
              JOIN `clients` AS `c`
                ON `c`.`id` = `client_id`
              JOIN `client_environments` AS `e`
                ON `e`.`id` = `env_id`
            WHERE
              `s`.`status` IN (?, ?) AND `s`.`dtadded` < NOW() - INTERVAL 1 DAY OR (
                `s`.`status` = ? AND
                `c`.`status` = ? AND
                `e`.`status` = ? AND (
                  `attempt`.`value` IS NULL OR
                  `last_try`.`value` < NOW() - INTERVAL `config`.`interval` DAY_SECOND
                )
              )
        ");

        $maxAttempts = max(array_keys($this->attemptConfig));

        $this->params = [
            SERVER_PROPERTIES::LAUNCH_ATTEMPT,
            SERVER_PROPERTIES::LAUNCH_LAST_TRY,
            $maxAttempts,
            $maxAttempts,
            SERVER_STATUS::IMPORTING,
            SERVER_STATUS::TEMPORARY,
            SERVER_STATUS::PENDING_LAUNCH,
            Scalr_Account::STATUS_ACTIVE,
            Scalr_Environment::STATUS_ACTIVE
        ];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $this->prepare();

        $queue = new ArrayObject([]);

        $rs = $this->db->Execute($this->stmt, $this->params);

        while ($row = $rs->FetchRow()) {
            $obj = new stdClass;
            $obj->serverId = $row['server_id'];
            $obj->status = $row['status'];

            $queue->append($obj);
        }

        if (($cnt = count($queue)) > 0) {
            $this->getLogger()->info("Found %d server%s to manage.", $cnt , ($cnt == 1 ? '' : 's'));
        }

        return $queue;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        //It must be done for any worker in daemon mode. (Policy engine, PM, etc.. settings shouldn't be cached)
        \Scalr::getContainer()->warmup();

        try {
            $dbServer = DBServer::LoadByID($request->serverId);

            if ($dbServer->status == SERVER_STATUS::TEMPORARY) {
                try {
                    $dbServer->terminate(DBServer::TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER);
                } catch (Exception $e) {
                }
            } else if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                $dbServer->Remove();
            } else if ($dbServer->status == SERVER_STATUS::PENDING_LAUNCH) {
                $account = Scalr_Account::init()->loadById($dbServer->clientId);

                if ($account->status == Scalr_Account::STATUS_ACTIVE) {
                    \Scalr::LaunchServer(null, $dbServer);
                }
            }
        } catch (Exception $e) {
            $this->getLogger()->error("Server: %s, manager failed with exception: %s", $request->serverId, $e->getMessage());
        }

        return $request;
    }
}