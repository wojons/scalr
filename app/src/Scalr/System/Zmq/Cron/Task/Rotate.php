<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, DateTime, DateTimeZone, stdClass;
use BundleTask;
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

        $this->getLogger()->info("Rotating logentries table. Keep:'%s'", $keep['scalr']['logentries']);
        $this->rotateTable("DELETE FROM `logentries` WHERE `time` < ?", [strtotime($keep['scalr']['logentries'])]);

        $this->getLogger()->info("Rotating scripting_log table. Keep:'%s'", $keep['scalr']['scripting_log']);
        $this->rotateTable("DELETE FROM `scripting_log` WHERE `dtadded` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['scripting_log']))
        ]);

        $this->getLogger()->info("Rotating events table. Keep:'%s'", $keep['scalr']['events']);
        $this->rotateTable("DELETE FROM `events` WHERE `dtadded` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['events']))
        ]);

        $this->getLogger()->info("Rotating messages table. Keep:'%s'", $keep['scalr']['messages']);
        $this->rotateTable("DELETE FROM messages WHERE type='out' AND status='1' AND `dtlasthandleattempt` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['messages']))
        ]);
        $this->rotateTable("DELETE FROM messages WHERE type='out' AND status='3' AND `dtlasthandleattempt` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['messages']))
        ]);
        $this->rotateTable("DELETE FROM messages WHERE type='in' AND status='1' AND `dtlasthandleattempt` <  ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['messages']))
        ]);

        $this->getLogger()->info("Rotating webhook_history table. Keep:'%s'", $keep['scalr']['webhook_history']);
        $this->rotateTable("DELETE FROM webhook_history WHERE `created` < ?", [
            date('Y-m-d H:i:s', strtotime($keep['scalr']['webhook_history']))
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
        }

        $this->getLogger()->info("Update bundle_tasks table. Fail for 3 days expired tasks.");
        $affected = BundleTask::failObsoleteTasks();
        $this->getLogger()->info("%d task%s %s failed by timeout", $affected, ($affected != 1 ? 's' : ''), ($affected > 1 ? 'were' : 'was'));

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