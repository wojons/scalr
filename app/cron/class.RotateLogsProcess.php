<?php

use Scalr\Service\Aws\Plugin\Handlers\StatisticsPlugin;
use Scalr\Upgrade\Console;

class RotateLogsProcess implements \Scalr\System\Pcntl\ProcessInterface
{
    const DELETE_LIMIT = 1000;
    const SLEEP_TIMEOUT = 60;

    public $ThreadArgs;
    public $ProcessDescription = "Rotate logs table";
    public $Logger;
    public $IsDaemon;

    /**
     * @var Console
     */
    private $console;

    public function __construct()
    {
        $this->Logger = Logger::getLogger(__CLASS__);
        $this->console = new Console();
        $this->console->timeformat = 'H:i:s';
    }

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
        $db = Scalr::getContainer()->$service;
        $opt[] = self::DELETE_LIMIT;
        do {
            $db->Execute($query . " LIMIT ?", $opt);
            $affected = $db->Affected_Rows();
            $this->console->out("\t%d records have been removed", $affected);
            if ($affected == self::DELETE_LIMIT) {
                $this->console->out("\tI am waiting for %d seconds before removing next records.", self::SLEEP_TIMEOUT);
                sleep(self::SLEEP_TIMEOUT);
            }
        } while ($affected >= self::DELETE_LIMIT);
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
        $db = Scalr::getContainer()->$service;

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
                $this->console->out("Removing %s from archive", $tables[$i]);
                $db->Execute("DROP TABLE `" . $tables[$i] . "`");
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Pcntl\ProcessInterface::OnStartForking()
     */
    public function OnStartForking()
    {
        $db = \Scalr::getDb();

        $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $tenDaysAgo = date('Y-m-d H:i:s', strtotime('-10 days'));
        $twentyDaysAgo = date('Y-m-d H:i:s', strtotime('-20 days'));
        $monthAgo = date('Y-m-d H:i:s', strtotime('-1 months'));
        $twoMonthAgo = date('Y-m-d H:i:s', strtotime('-2 months'));
        $twoWeeksAgo = date('Y-m-d H:i:s', strtotime('-14 days'));

        $this->console->out("%s (UTC) Start RotateLogsProcess", gmdate('Y-m-d'));

        $this->console->out("Rotating logentries table");
        $this->rotateTable("DELETE FROM `logentries` WHERE `time` < ?", [strtotime('-10 days')]);

        $this->console->out("Rotating scripting_log table");
        $this->rotateTable("DELETE FROM `scripting_log` WHERE `dtadded` < ?", array($sevenDaysAgo));

        $this->console->out("Rotating events table");
        $this->rotateTable("DELETE FROM `events` WHERE `dtadded` < ?", array($twoMonthAgo));

        $this->console->out("Rotating messages table");
        $this->rotateTable("DELETE FROM messages WHERE type='out' AND status='1' AND `dtlasthandleattempt` < ?", array($tenDaysAgo));
        $this->rotateTable("DELETE FROM messages WHERE type='out' AND status='3' AND `dtlasthandleattempt` < ?", array($tenDaysAgo));
        $this->rotateTable("DELETE FROM messages WHERE type='in' AND status='1' AND `dtlasthandleattempt` <  ?", array($tenDaysAgo));

        $this->console->out('Rotating webhook_history table');
        $this->rotateTable("DELETE FROM webhook_history WHERE `created` < ?", array($twoWeeksAgo));

        $this->console->out("Rotating farm_role_scripts table");
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

        $this->console->out('Calculating number of the records in the syslog table');
        if ($db->GetOne("SELECT COUNT(*) FROM `syslog`") > 1000000) {
            $this->console->out("Rotating syslog table");
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

            $this->Logger->debug("Log rotated. New table 'syslog_{$dtstamp}' created.");

            $this->rotateBackup('^syslog_[0-9]{8,10}$');
        }

        //Rotate aws_statistics
        $this->console->out("Rotating AWS Statistics");
        StatisticsPlugin::rotate();

        //Rotate cost analytics data
        if (Scalr::getContainer()->analytics->enabled) {
            $this->console->out("Rotating analytics.poller_sessions table");
            $before = (new DateTime('-7 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $this->rotateTable("DELETE FROM `poller_sessions` WHERE `dtime` < ?", [$before], 'cadb');

            $this->console->out("Rotating analytics.usage_h table");
            $before = (new DateTime('-14 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $this->rotateTable("DELETE FROM `usage_h` WHERE `dtime` < ?", [$before], 'cadb');
            $this->console->out("Rotating analytics.nm_usage_h table");
            $this->rotateTable("DELETE FROM `nm_usage_h` WHERE `dtime` < ?", [$before], 'cadb');
        }

        $this->console->out('Done');
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Pcntl\ProcessInterface::OnEndForking()
     */
    public function OnEndForking()
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Pcntl\ProcessInterface::StartThread()
     */
    public function StartThread($farminfo)
    {
    }
}
