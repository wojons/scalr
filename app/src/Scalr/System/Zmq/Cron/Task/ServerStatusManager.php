<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, DateTime, DateTimeZone, Exception, stdClass;
use Scalr\System\Zmq\Cron\AbstractTask;
use \DBServer;
use \SERVER_STATUS;
use \Scalr_Account;
use \Scalr_Environment;

/**
 * Server status manager
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.1.0 (15.01.2015)
 */
class ServerStatusManager extends AbstractTask
{

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $queue = new ArrayObject([]);

        $db = \Scalr::getDb();

        $rs = $db->Execute("
            SELECT server_id, status
            FROM servers
            WHERE status IN (?, ?) AND `dtadded` < NOW() - INTERVAL 1 DAY

            UNION ALL

            SELECT s.server_id, s.status
            FROM servers s
            JOIN clients c ON c.id = s.client_id
            JOIN client_environments ce ON ce.id = s.env_id
            WHERE s.status = ? AND c.status = ? AND ce.status = ?
        ", array(
            SERVER_STATUS::IMPORTING,
            SERVER_STATUS::TEMPORARY,
            SERVER_STATUS::PENDING_LAUNCH,
            Scalr_Account::STATUS_ACTIVE,
            Scalr_Environment::STATUS_ACTIVE
        ));

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