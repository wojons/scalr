<?php

class Scalr_Service_Gearman_Jobs_MessagesSender
{
    public static function getTasksList()
    {
        $db = \Scalr::getDb();

        $rows = $db->GetAll("
            SELECT messageid, server_id FROM messages
            WHERE `type`='out' AND status=?
            AND dtlasthandleattempt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL handle_attempts * 120 SECOND)
            ORDER BY dtadded DESC
            LIMIT 0,3000
        ", array(
            MESSAGE_STATUS::PENDING
        ));
        return $rows;
    }

    public static function doJob($job)
    {
        $db = \Scalr::getDb();

        $messageSerializer = new Scalr_Messaging_XmlSerializer();

        $message = $db->GetRow("SELECT messageid, server_id, message, handle_attempts FROM messages WHERE messageid = ? AND server_id = ?", $job->workload());
        try {
            if ($message['handle_attempts'] >= 3) {
                $db->Execute(
                    "UPDATE messages SET status=? WHERE messageid = ? AND server_id = ?",
                    [MESSAGE_STATUS::FAILED, $message['messageid'], $message['server_id']]
                );
            } else {
                try {
                    $DBServer = DBServer::LoadByID($message['server_id']);
                } catch (Exception $e) {
                    $db->Execute(
                        "UPDATE messages SET status=? WHERE messageid = ? AND server_id = ?",
                        [MESSAGE_STATUS::FAILED, $message['messageid'], $message['server_id']]
                    );
                    return;
                }

                if ($DBServer->status == SERVER_STATUS::RUNNING ||
                    $DBServer->status == SERVER_STATUS::INIT ||
                    $DBServer->status == SERVER_STATUS::IMPORTING ||
                    $DBServer->status == SERVER_STATUS::TEMPORARY ||
                    $DBServer->status == SERVER_STATUS::PENDING_TERMINATE
                ) {
                    // Only 0.2-68 or greater version support this feature.
                    if ($DBServer->IsSupported("0.2-68")) {
                        $msg = $messageSerializer->unserialize($message['message']);
                        $DBServer->SendMessage($msg);
                    } else {
                        $db->Execute(
                            "UPDATE messages SET status=? WHERE messageid = ? AND server_id = ?",
                            [MESSAGE_STATUS::UNSUPPORTED, $message['messageid'], $message['server_id']]
                        );
                    }
                } else if (in_array($DBServer->status, [SERVER_STATUS::TERMINATED, SERVER_STATUS::PENDING_TERMINATE])) {
                    $db->Execute(
                        "UPDATE messages SET status=? WHERE messageid = ? AND server_id = ?",
                        [MESSAGE_STATUS::FAILED, $message['messageid'], $message['server_id']]
                    );
                }
            }
        }
        catch(Exception $e) {
            //var_dump($e->getMessage());
        }
    }
}