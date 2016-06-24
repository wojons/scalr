<?php

use Scalr\Model\Entity\OrchestrationLog;
use Scalr\Model\Entity\OrchestrationLogManualScript;

class Scalr_Messaging_Service_LogQueueHandler implements Scalr_Messaging_Service_QueueHandler
{

    /**
     * @var \ADODB_mysqli
     */
    private $db;

    private $logger;

    private static $severityCodes = array(
        'DEBUG'   => 1,
        'INFO'    => 2,
        'WARN'    => 3,
        'WARNING' => 3,
        'ERROR'   => 4,
    );

    function __construct ()
    {
        $this->db = \Scalr::getDb();
        $this->logger = \Scalr::getContainer()->logger(__CLASS__);
    }

    function accept($queue) {
        return $queue == "log";
    }

    function handle($queue, Scalr_Messaging_Msg $message, $rawMessage) {
        $dbserver = DBServer::LoadByID($message->getServerId());
        $msg = '';

        if ($message instanceOf Scalr_Messaging_Msg_ExecScriptResult) {
            try {
                $storage = \Scalr::config('scalr.system.scripting.logs_storage');

                if (!$message->executionId || $storage == 'scalr')
                    $msg = sprintf("STDERR: %s \n\n STDOUT: %s", base64_decode($message->stderr), base64_decode($message->stdout));

                if ($message->scriptPath) {
                    $name = (stristr($message->scriptPath, 'C:\Windows\TEMP\scalr-scripting') || 
                            stristr($message->scriptPath, '/usr/local/bin/scalr-scripting') || 
                            preg_match('/fatmouse-agent\/tasks\/[^\/]+\/[^\/]+\/bin/', $message->scriptPath)
                    )
                        ? $message->scriptName : $message->scriptPath;
                } else {
                    $name = $message->scriptName;
                }

                $log = new OrchestrationLog();

                if (strpos($message->eventName, 'Scheduler (TaskID: ') === 0) {
                    $type = OrchestrationLog::TYPE_SCHEDULER;
                    $log->taskId = filter_var($message->eventName, FILTER_SANITIZE_NUMBER_INT);
                } else if ($message->eventName == 'Manual') {
                    $type = OrchestrationLog::TYPE_MANUAL;
                } else {
                    $type = OrchestrationLog::TYPE_EVENT;
                }

                $log->farmId        = $dbserver->farmId;
                $log->serverId      = $message->getServerId();
                $log->type          = $type;
                $log->message       = $msg;
                $log->added         = new DateTime('now');
                $log->scriptName    = $name;
                $log->execTime      = round($message->timeElapsed, 2);
                $log->execExitCode  = $message->returnCode;
                $log->eventId       = $message->eventId;
                $log->eventServerId = $message->eventServerId;
                $log->executionId   = $message->executionId;
                $log->runAs         = $message->runAs;

                $log->save();

                if ($type === OrchestrationLog::TYPE_MANUAL) {
                    $logManual = OrchestrationLogManualScript::findOne([['executionId' => $message->executionId], ['serverId' => $message->getServerId()]]);
                    /* @var $logManual OrchestrationLogManualScript */
                    if ($logManual && empty($logManual->orchestrationLogId)) {
                        $logManual->orchestrationLogId = $log->id;
                        $logManual->save();
                    }
                }

                if ($message->meta[Scalr_Messaging_MsgMeta::SZR_VERSION]) {
                    DBServer::LoadByID($message->getServerId())->setScalarizrVersion($message->meta[Scalr_Messaging_MsgMeta::SZR_VERSION]);
                }

                if ($message->eventId) {
                    $updateTotal = '';

                    if ($message->returnCode == 130) {
                        $field = 'scripts_timedout';
                    } elseif ($message->returnCode != 0) {
                        $field = 'scripts_failed';
                    } else {
                        $field = 'scripts_completed';
                    }

                    if (stristr($name, '[Scalr built-in]'))
                        $updateTotal = ', `scripts_total` = `scripts_total`+1';

                    $this->db->Execute("UPDATE events SET `{$field}` = `{$field}`+1 {$updateTotal} WHERE event_id = ?", [$message->eventId]);
                }

            } catch (Exception $e) {
                $this->logger->fatal($e->getMessage());
            }

        } elseif ($message instanceof Scalr_Messaging_Msg_Log) {

            try {
                if ($message->meta[Scalr_Messaging_MsgMeta::SZR_VERSION]) {
                    DBServer::LoadByID($message->getServerId())->setScalarizrVersion($message->meta[Scalr_Messaging_MsgMeta::SZR_VERSION]);
                }
            } catch (Exception $e) {}

            foreach ($message->entries as $entry) {
                if (self::$severityCodes[$entry->level] < 3) {
                    continue;
                }

                $level = $entry->level === "WARNING" ? "warn" : strtolower($entry->level);
                \Scalr::getContainer()->logger($entry->name)->{$level}(new FarmLogMessage($dbserver, !empty($entry->msg) ? $entry->msg : null));
            }
        } elseif ($message instanceof Scalr_Messaging_Msg_RebundleLog) {
            try {
                $this->db->Execute("INSERT INTO bundle_task_log SET
                    bundle_task_id = ?,
                    dtadded = NOW(),
                    message = ?
                ", [
                    $message->bundleTaskId,
                    $message->message
                ]);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }
}
