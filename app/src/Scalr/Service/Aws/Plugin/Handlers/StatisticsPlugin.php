<?php

namespace Scalr\Service\Aws\Plugin\Handlers;

use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\Event\ErrorResponseEvent;
use Scalr\Service\Aws\Event\SendRequestEvent;
use Scalr\Service\Aws\Event\EventInterface;
use Scalr\Service\Aws\Event\EventType;
use Scalr\Service\Aws\Plugin\AbstractPlugin;
use Scalr\Service\Aws\Plugin\PluginInterface;

/**
 * AWS client statistics plugin
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     26.09.2013
 */
class StatisticsPlugin extends AbstractPlugin implements PluginInterface
{

    /**
     * Database table to store statistics
     */
    const DB_TABLE_NAME = 'aws_statistics';

    /**
     * AWS client send request event
     */
    const EVENT_ID_REQUEST_SENT = 1;

    /**
     * AWS error response with request limit exceeded error
     */
    const EVENT_ID_ERROR_REQUEST_LIMIT_EXCEEDED = 2;

	/**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Plugin.PluginInterface::getSubscribedEvents()
     */
    public function getSubscribedEvents()
    {
        return array(EventType::EVENT_ERROR_RESPONSE, EventType::EVENT_SEND_REQUEST);
    }

	/**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Plugin.PluginInterface::handle()
     */
    public function handle(EventInterface $event)
    {
        return $this->handleEvent($event);
    }

    /**
     * Handles an AWS client event
     *
     * @param   EventInterface $event   An Event object
     * @param   int            $attempt optional Attempt number
     */
    private function handleEvent(EventInterface $event, $attempt = 0)
    {
        try {
            $environment = $this->aws->getEnvironment();
            if ($environment instanceof \Scalr_Environment) {
                $eventId = self::EVENT_ID_REQUEST_SENT;
                if ($event instanceof ErrorResponseEvent) {
                    $errorData = $event->exception->getErrorData();
                    if ($errorData instanceof ErrorData &&
                        $errorData->getCode() == ErrorData::ERR_REQUEST_LIMIT_EXCEEDED) {
                        $eventId = self::EVENT_ID_ERROR_REQUEST_LIMIT_EXCEEDED;
                    } else {
                        return;
                    }
                }
                $db = $this->getDb();

                $pid = function_exists('posix_getpid') ? posix_getpid() : null;

                $apicall = isset($event->apicall) ? $event->apicall : null;

                $db->Execute("
                    INSERT INTO `" . self::DB_TABLE_NAME . "`
                    SET envid = ?,
                        event = ?,
                        pid = ?,
                        apicall = ?
                    ", array(
                    $environment->id,
                    $eventId,
                    $pid,
                    $apicall,
                ));
            }
        } catch (\Exception $e) {
            if ($attempt == 0 && !$db->GetOne("SHOW TABLES LIKE ?", array(self::DB_TABLE_NAME))) {
                $this->createStorage();
                return $this->handleEvent($event, 1);
            }
            //It should not cause process termination
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
    }

    /**
     * Gets database instance
     *
     * @return \ADODB_mysqli
     */
    protected function getDb()
    {
        return $this->container->adodb;
    }

    /**
     * Creates storage for saving data
     */
    protected function createStorage()
    {
        $db = $this->getDb();

        $ret = $db->GetOne("SHOW TABLES LIKE ?", array(self::DB_TABLE_NAME));

        if (!$ret) {
            //Previous value
            $prevHeapTableSize = $db->GetRow("SHOW VARIABLES LIKE 'max_heap_table_size'");
            if ($prevHeapTableSize) {
                $prevHeapTableSize = $prevHeapTableSize['Value'];
                $db->Execute("SET `max_heap_table_size` = " . intval($this->container->config('scalr.aws.plugins.statistics.storage_max_size')));
            }

            //Table needs to be created at once

            //1-366
            //Creates all patritions from today to last day of the year
            $days = $db->GetRow("
                SELECT
                    DAYOFYEAR(CURDATE() + INTERVAL 1 DAY) AS tomorrow,
                    DAYOFYEAR(CONCAT(YEAR(CURDATE() + INTERVAL 1 DAY), '-12-31')) AS lastday
            ");

            $dt = new \DateTime('tomorrow');
            $patritionSet = '';
            for ($i = $days['tomorrow']; $i <= $days['lastday']; $i++) {
                $patritionSet .= "PARTITION p" . $dt->format('Ymd') . " VALUES LESS THAN (UNIX_TIMESTAMP('" . $dt->format('Y-m-d'). " 00:00:00')),";
                $dt->add(new \DateInterval("P1D"));
            }

            $db->Execute("
                CREATE TABLE IF NOT EXISTS `" . self::DB_TABLE_NAME . "` (
                	`envid` INT NOT NULL,
                	`stated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `apicall` VARCHAR(64) DEFAULT NULL,
                    `pid` INT DEFAULT NULL,
                	`event` TINYINT DEFAULT NULL,
                	INDEX `idx_find` USING BTREE (`envid`, `event`, `stated`),
                    INDEX `idx_stated` USING BTREE (`stated`),
                    INDEX `idx_event` USING HASH (`event`)
                ) ENGINE = MEMORY PARTITION BY RANGE(UNIX_TIMESTAMP(stated)) (" . rtrim($patritionSet, ',') . ")
            ");

            //This is necessary for slaves.
            $db->Execute("DELETE FROM `" . self::DB_TABLE_NAME . "`");

            if ($prevHeapTableSize) {
                //Restore previous value
                $db->Execute("SET `max_heap_table_size` = " . $prevHeapTableSize);
            }
        }
    }

    /**
     * Rotate table procedure
     */
    public static function rotate()
    {
        $db = \Scalr::getDb();
        $ret = $db->GetOne("SHOW TABLES LIKE ?", array(self::DB_TABLE_NAME));
        if ($ret) {
            //Table does exist.
            $rec = $db->GetRow("EXPLAIN PARTITIONS SELECT * FROM `" . self::DB_TABLE_NAME . "`");
            $partitions = explode(',p', ltrim($rec['partitions'], 'p'));
            if (!$partitions) {
                $partitions = array();
            }
            $dt = new \DateTime('-1 day');
            $start = $dt->format('Ymd');

            $toRemove = array_filter($partitions, function ($v) use ($start) {
                return $v < $start;
            });

            if (!empty($toRemove)) {
                $db->Execute("ALTER TABLE `" . self::DB_TABLE_NAME . "` DROP PARTITION " . ('p' . join(', p', $toRemove)));
            }

            $dt = new \DateTime('+1 month');
            $max = max($partitions);
            $intDay = new \DateInterval('P1D');
            $partitionSet = '';
            while ($max < $dt->format('Ymd')) {
                $partitionSet = ",PARTITION p" . $dt->format('Ymd') . " VALUES LESS THAN (UNIX_TIMESTAMP('" . $dt->format('Y-m-d') . " 00:00:00'))" . $partitionSet;
                $dt->sub($intDay);
            }
            if ($partitionSet != '') {
                $db->Execute("ALTER TABLE `" . self::DB_TABLE_NAME . "` ADD PARTITION (" . ltrim($partitionSet, ',') . ")");
            }
        }
    }
}