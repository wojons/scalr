<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, DateTime, DateTimeZone, Exception;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr_SchedulerTask;
use Scalr_Account;

/**
 * Scheduler
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (27.10.2014)
 */
class Scheduler extends AbstractTask
{

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $db = \Scalr::getDb();

        // get active tasks: first run (condition and last_start_time is null), others (condition and last_start_time + interval * 0.9 < now())
        $taskList = $db->GetAll("
            SELECT *
            FROM scheduler
            WHERE `status` = ?
            AND (`start_time` IS NULL OR CONVERT_TZ(`start_time`,'SYSTEM',`timezone`) <= CONVERT_TZ(NOW(),'SYSTEM',`timezone`))
            AND (`last_start_time` IS NULL OR
                 `last_start_time` IS NOT NULL AND `start_time` IS NULL AND (CONVERT_TZ(last_start_time + INTERVAL restart_every MINUTE, 'SYSTEM', `timezone`) < CONVERT_TZ(NOW(),'SYSTEM',`timezone`)) OR
                 `last_start_time` IS NOT NULL AND `start_time` IS NOT NULL AND (CONVERT_TZ(last_start_time + INTERVAL (restart_every * 0.9) MINUTE, 'SYSTEM', `timezone`) < CONVERT_TZ(NOW(),'SYSTEM',`timezone`))
            )
            ORDER BY IF (last_start_time, last_start_time, start_time) ASC
        ", [Scalr_SchedulerTask::STATUS_ACTIVE]);

        if (!$taskList) {
            $this->getLogger()->info("There are no tasks to execute in scheduler table.");
            return $queue;
        }

        $this->getLogger()->info("Found %d tasks", count($taskList));

        foreach ($taskList as $task) {
            try {
                // check account status (active or inactive)
                if (Scalr_Account::init()->loadById($task['account_id'])->status != Scalr_Account::STATUS_ACTIVE) {
                    continue;
                }
            } catch (Exception $e) {
                $this->getLogger()->info("Scheduler task #%s could not start: %s", $task['id'], $e->getMessage());
            }

            if ($task['last_start_time'] && $task['start_time']) {
                // try to auto-align time to start time
                $startTime = new DateTime($task['start_time']);
                $startTime->setTimezone(new DateTimeZone($task['timezone']));
                $currentTime = new DateTime('now', new DateTimeZone($task['timezone']));

                $offset = $startTime->getOffset() - $currentTime->getOffset();
                $num = ($currentTime->getTimestamp() - $startTime->getTimestamp() - $offset) / ($task['restart_every'] * 60);
                $numFloor = floor($num);

                // we check tasks which are longer than hour
                if ($task['restart_every'] > 55) {
                    // check how much intervals were missed
                    $lastStartTime = new DateTime($task['last_start_time']);
                    $lastStartTime->setTimezone(new DateTimeZone($task['timezone']));

                    if ((($currentTime->getTimestamp() - $lastStartTime->getTimestamp() - ($lastStartTime->getOffset() - $currentTime->getOffset())) / ($task['restart_every'] * 60)) > 2) {
                        // we missed one extra (or more) interval, so let's check if currentTime is synchronized with startTime
                        if (($num - $numFloor) > 0.1) {
                            $this->getLogger()->debug(sprintf('Delay task (missed interval): %s, num: %f', $task['name'], $num));
                            continue;
                        }
                    }
                }

                // because of timezone's transitions
                // num should be less than 0.5 (because of interval * 0.9 in SQL query)
                if ($numFloor != round($num, 0, PHP_ROUND_HALF_UP)) {
                    $this->getLogger()->debug(sprintf('Delay task (interval): %s, Offset: %d, num: %f, floor: %f, round: %f', $task['name'], $offset, $num, floor($num), round($num, 0, PHP_ROUND_HALF_UP)));
                    continue;
                }
            }

            $this->log('DEBUG', "Adding task %s to queue", $task['id']);
            $queue->append($task['id']);
        }

        return $queue;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        $task = new Scalr_SchedulerTask();
        $task->setLogger($this->getLogger());
        $task->loadById($request);

        $this->log("DEBUG", "Trying to execute task:%d", $task->id);

        $container = \Scalr::getContainer();
        $container->release('auditlogger');
        //Ajdusts both account & environment for the audit log
        $container->auditlogger->setAccountId($task->accountId)->setEnvironmentId($task->envId);

        if ($task->execute()) {
            $task->updateLastStartTime();
            $this->getLogger()->info("Task %s:%d successfully sent", $task->name, $task->id);
        } else {
            $this->log('DEBUG', "Failed to execute task:%d", $task->id);
        }

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
            //Report a warning to the log
            trigger_error(sprintf("Demonized mode is not allowed for '%s' task. Forcing normal mode.", $this->name), E_USER_WARNING);

            //Forces normal mode
            $config->daemon = false;
        }

        return $config;
    }
}