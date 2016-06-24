<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, DateTime, DateTimeZone, stdClass;
use BundleTask;
use Scalr\Model\Entity\Account\EnvironmentProperty;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Account\User\UserSetting;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Service\Aws\Plugin\Handlers\StatisticsPlugin;

/**
 * Rotate
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (27.11.2014)
 */
class Rotate extends AbstractTask
{

    /**
     * Rotates table by specified query.
     *
     * It uses delay between deletions and limit
     *
     * @param   string    $query   SQL statement
     * @param   array     $opt     optional Query criteria
     * @param   string    $service optional The database connection service from DI container
     */
    private function rotateTable($query, $opt = array(), $service = 'adodb')
    {
        $db = \Scalr::getContainer()->$service;
        $opt[] = $this->config()->delete['limit'];
        do {
            $db->Execute($query . " LIMIT ?", $opt);
            $affected = $db->Affected_Rows();
            $this->getLogger()->info("%d record%s %s removed", $affected, ($affected != 1 ? 's' : ''), ($affected > 1 ? 'were' : 'was'));
            if ($affected == $this->config()->delete['limit']) {
                $this->getLogger()->info("I am waiting for %d seconds before removing next records.", $this->config()->delete['sleep']);
                sleep($this->config()->delete['sleep']);
            }
        } while ($affected >= $this->config()->delete['limit']);
    }

    /**
     * Rotates backup for the table using regexp mask
     *
     * @param   string   $regexp        The regular expression
     * @param   string   $numberBackups The number of the archive files to persist
     * @param   string   $service       optional The service name
     */
    private function rotateBackup($regexp, $numberBackups = null, $service = 'adodb')
    {
        $db = \Scalr::getContainer()->$service;

        //Persists only recent seven backups by default
        $numberBackups = $numberBackups ?: 7;

        $tables = $db->GetCol("
            SELECT `TABLE_NAME`
            FROM `INFORMATION_SCHEMA`.`TABLES`
            WHERE `TABLE_SCHEMA` = DATABASE()
            AND `TABLE_NAME` REGEXP '" . $regexp . "'
            ORDER BY `CREATE_TIME`
       ");

        if (!empty($tables) && ($cnt = count($tables)) > $numberBackups) {
            for ($i = 0; $i + $numberBackups < $cnt; ++$i) {
                $this->getLogger()->info("Removing %s from archive", $tables[$i]);
                $db->Execute("DROP TABLE `" . $tables[$i] . "`");
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $db = \Scalr::getDb();

        $keep = $this->config()->keep;

        if ($db->GetOne("SHOW TABLES LIKE 'api_counters'")) {
            $this->getLogger()->info("Rotating api rate limit counters");
            $db->Execute("TRUNCATE TABLE `api_counters`");
        }

        $this->getLogger()->info("Rotating logentries table. Keep:'%s'", $keep['scalr']['logentries']);
        $this->rotateTable("DELETE FROM `logentries` WHERE `time` < ?", [strtotime($keep['scalr']['logentries'])]);

        $this->getLogger()->info("Rotating orchestration_log table. Keep:'%s'", $keep['scalr']['orchestration_log']);
        $this->rotateTable("DELETE FROM `orchestration_log` WHERE `dtadded` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['orchestration_log']))
        ]);

        $this->getLogger()->info("Rotating orchestration_log_manual_scripts table. Keep:'%s'", $keep['scalr']['orchestration_log']);
        $this->rotateTable("DELETE FROM `orchestration_log_manual_scripts` WHERE `added` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['orchestration_log']))
        ]);

        $this->getLogger()->info("Rotating api_log table. Keep:'%s'", $keep['scalr']['api_log']);
        $this->rotateTable("DELETE FROM `api_log` WHERE `dtadded` < ?", [
            strtotime($keep['scalr']['api_log'])
        ]);

        $this->getLogger()->info("Rotating events table. Keep:'%s'", $keep['scalr']['events']);
        $this->rotateTable("DELETE FROM `events` WHERE `dtadded` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['events']))
        ]);

        $this->getLogger()->info("Rotating messages table. Keep:'%s'", $keep['scalr']['messages']);
        $this->rotateTable("DELETE FROM messages WHERE type='out' AND status='1' AND `dtlasthandleattempt` < ?", [
            (new DateTime($keep['scalr']['messages'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s')
        ]);

        $this->rotateTable("DELETE FROM messages WHERE type='out' AND status='3' AND `dtlasthandleattempt` < ?", [
            (new DateTime($keep['scalr']['messages'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s')
        ]);
        $this->rotateTable("DELETE FROM messages WHERE type='in' AND status='1' AND `dtlasthandleattempt` <  ?", [
            (new DateTime($keep['scalr']['messages'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s')
        ]);

        $this->getLogger()->info("Rotating webhook_history table. Keep:'%s'", $keep['scalr']['webhook_history']);
        $this->rotateTable("DELETE FROM webhook_history WHERE `created` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['webhook_history']))
        ]);

        $this->getLogger()->info("Rotating ui_errors table");
        $this->rotateTable("DELETE FROM ui_errors WHERE `tm` < ?", [
            date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $this->getLogger()->info("Rotating farm_role_scripts table");
        $year = date('Y');
        $month = date('m', strtotime('-1 months'));
        $this->rotateTable("
            DELETE FROM `farm_role_scripts`
            WHERE ismenuitem='0' AND event_name LIKE 'CustomEvent-{$year}{$month}%'
        ");
        $this->rotateTable("
            DELETE FROM `farm_role_scripts`
            WHERE ismenuitem='0' AND event_name LIKE 'APIEvent-{$year}{$month}%'
        ");

        $this->getLogger()->info('Calculating number of the records in the syslog table');
        if ($db->GetOne("SELECT COUNT(*) FROM `syslog`") > $keep['scalr']['syslog']) {
            $this->getLogger()->info("Rotating syslog table. Keep:'%d'", $keep['scalr']['syslog']);
            $dtstamp = date("HdmY");

            try {
                if ($db->GetOne("SHOW TABLES LIKE ?", ['syslog_tmp'])) {
                    $db->Execute("DROP TABLE `syslog_tmp`");
                }
                $db->Execute("CREATE TABLE `syslog_tmp` LIKE `syslog`");
                $db->Execute("RENAME TABLE `syslog` TO `syslog_" . $dtstamp . "`, `syslog_tmp` TO `syslog`");

                $db->Execute("TRUNCATE TABLE syslog_metadata");
                $db->Execute("OPTIMIZE TABLE syslog");
                $db->Execute("OPTIMIZE TABLE syslog_metadata");
            } catch (Exception $e) {
                $this->console->error($e->getMessage());
            }

            $this->getLogger()->debug("Log rotated. New table 'syslog_{$dtstamp}' created.");

            $this->rotateBackup('^syslog_[0-9]{8,10}$');
        }

        //Rotate aws_statistics
        $this->getLogger()->info("Rotating AWS Statistics");
        StatisticsPlugin::rotate();

        //Rotate cost analytics data
        if (\Scalr::getContainer()->analytics->enabled) {
            $this->getLogger()->info("Rotating analytics.poller_sessions table. Keep:'%s'", $keep['analytics']['poller_sessions']);
            $before = (new DateTime($keep['analytics']['poller_sessions'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $this->rotateTable("DELETE FROM `poller_sessions` WHERE `dtime` < ?", [$before], 'cadb');

            $this->getLogger()->info("Rotating analytics.usage_h table. Keep:'%s'", $keep['analytics']['usage_h']);
            $before = (new DateTime($keep['analytics']['usage_h'], new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $this->rotateTable("DELETE FROM `usage_h` WHERE `dtime` < ?", [$before], 'cadb');
            $this->getLogger()->info("Rotating analytics.nm_usage_h table");
            $this->rotateTable("DELETE FROM `nm_usage_h` WHERE `dtime` < ?", [$before], 'cadb');

            $this->getLogger()->info("Rotating analytics.aws_billing_records table. Keep:'%s'", $keep['analytics']['aws_billing_records']);
            $before = (new DateTime($keep['analytics']['aws_billing_records'], new DateTimeZone('UTC')))->format('Y-m-d');
            $this->rotateTable("DELETE FROM `aws_billing_records` WHERE `date` < ?", [$before], 'cadb');
        }

        $this->getLogger()->info("Update bundle_tasks table. Fail for 3 days expired tasks.");
        $affected = BundleTask::failObsoleteTasks();
        $this->getLogger()->info("%d task%s %s failed by timeout", $affected, ($affected != 1 ? 's' : ''), ($affected > 1 ? 'were' : 'was'));

        if (\Scalr::config('scalr.auth_mode') == 'scalr') {
            // suspend user based on config settings
            $days = (int) \Scalr::config('scalr.security.user.suspension.inactivity_days');
            if ($days > 0) {
                $dt = date('Y-m-d H:i:s', strtotime("-{$days} day"));

                $db->Execute("
                    UPDATE `account_users`
                    SET `status` = ?
                    WHERE `email` != 'admin' AND (
                        `dtlastlogin` IS NOT NULL AND `dtlastlogin` < ? OR `dtlastlogin` IS NULL AND `dtcreated` < ?
                    )
                ", [User::STATUS_INACTIVE, $dt, $dt]);
                $affected = $db->Affected_Rows();
                if ($affected > 0) {
                    $this->getLogger()->info("%d %s suspended due to inactivity", $affected, $affected > 1 ? 'users were' : 'user was');
                }
            }
        }

        $this->getLogger()->info('Done');

        //It does not need to handle a work because all stuff is handled in the client.
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
