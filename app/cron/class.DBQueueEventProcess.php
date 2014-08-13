<?php

use Scalr\Model\Entity\WebhookHistory;
use Scalr\Model\Entity\WebhookEndpoint;

class DBQueueEventProcess implements \Scalr\System\Pcntl\ProcessInterface
{
    public $ThreadArgs;
    public $ProcessDescription = "Process events queue";
    public $Logger;
    public $IsDaemon = true;
    private $DaemonMtime;
    private $DaemonMemoryLimit = 20; // in megabytes

    public function __construct()
    {
        // Get Logger instance
        $this->Logger = Logger::getLogger(__CLASS__);

        $this->DaemonMtime = @filemtime(__FILE__);
    }

    public function OnStartForking()
    {
        $db = \Scalr::getDb();

        // Get pid of running daemon
        $pid = @file_get_contents(CACHEPATH . "/" . __CLASS__ . ".Daemon.pid");

        $this->Logger->info("Current daemon process PID: {$pid}");

        // Check is daemon already running or not
        if ($pid) {
            $Shell = new Scalr_System_Shell();

            // Set terminal width
            putenv("COLUMNS=400");

            // Execute command
            $ps = $Shell->queryRaw("ps ax -o pid,ppid,command | grep ' 1' | grep {$pid} | grep -v 'ps x' | grep DBQueueEvent");

            $this->Logger->info("Shell->queryRaw(): {$ps}");
            if ($ps) {
                // daemon already running
                $this->Logger->info("Daemon running. All ok!");
                return true;
            }
        }

        $rows = $db->Execute("SELECT history_id FROM webhook_history WHERE status='0'");
        while ($row = $rows->FetchRow()) {
            $history = WebhookHistory::findPk(bin2hex($row['history_id']));
            if (!$history)
                continue;

            $endpoint = WebhookEndpoint::findPk($history->endpointId);

            $request = new HttpRequest();
            $request->setMethod(HTTP_METH_POST);


            if ($endpoint->url == 'SCALR_MAIL_SERVICE')
                $request->setUrl('https://my.scalr.com/webhook_mail.php');
            else
                $request->setUrl($endpoint->url);

            $request->setOptions(array(
               'timeout'        => 3,
               'connecttimeout' => 3
            ));

            $dt = new DateTime('now', new DateTimeZone("UTC"));
            $timestamp = $dt->format("D, d M Y H:i:s e");
            $canonical_string = $history->payload . $timestamp;
            $signature = hash_hmac('SHA1', $canonical_string, $endpoint->securityKey);

            $request->addHeaders(array(
               'Date'        => $timestamp,
               'X-Signature' => $signature,
               'X-Scalr-Webhook-Id' => $history->historyId,
               'Content-type' => 'application/json'
            ));

            $request->setBody($history->payload);

            try {
                $request->send();

                $history->responseCode = $request->getResponseCode();

                if ($request->getResponseCode() <= 205)
                    $history->status = WebhookHistory::STATUS_COMPLETE;
                else
                    $history->status = WebhookHistory::STATUS_FAILED;
            } catch (Exception $e) {
                $history->status = WebhookHistory::STATUS_FAILED;
            }

            $history->save();
        }
    }

    public function OnEndForking()
    {

    }

    public function StartThread($eventId)
    {

    }

    /**
     * Return current memory usage by process
     *
     * @return float
     */
    private function GetMemoryUsage()
    {
        return round(memory_get_usage(true)/1024/1024, 2);
    }
}
