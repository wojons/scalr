<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, stdClass, DateTime, DateTimeZone, Exception;
use Scalr\System\Zmq\Cron\AbstractTask;
use \DNS_ZONE_STATUS;
use \DBDNSZone;
use \Scalr_Net_Dns_Bind_RemoteBind;
use \Scalr_Net_Dns_Bind_Transports_LocalFs;

/**
 * DnsManager service
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.3.0 (03.02.2015)
 */
class DnsManager extends AbstractTask
{

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        $db = \Scalr::getDb();

        $queue = new ArrayObject([]);

        if (!\Scalr::config('scalr.dns.global.enabled')) {
            return $queue;
        }

        $this->getLogger()->info("Fetching records to process...");

        $rs = $db->Execute("
            SELECT z.id
            FROM dns_zones z
            JOIN clients c ON c.id = z.client_id
            WHERE z.status NOT IN (?, ?) OR (z.isonnsserver = '1' AND z.status = ?)
            ORDER BY c.`priority` DESC
            LIMIT 100
        ", [
            DNS_ZONE_STATUS::ACTIVE,
            DNS_ZONE_STATUS::INACTIVE,
            DNS_ZONE_STATUS::INACTIVE
        ]);

        while ($rec = $rs->FetchRow()) {
            $obj = new stdClass();
            $obj->id = $rec['id'];

            $queue->append($obj);
        }

        if ($cnt = count($queue)) {
            $this->getLogger()->info("%d record%s %s been found.", $cnt, ($cnt == 1 ? '' : 's'), ($cnt == 1 ? 'has' : 'have'));
        } else {
            $this->getLogger()->info("Could not find any record.");
        }

        return $queue;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        if (!\Scalr::config('scalr.dns.global.enabled')) {
            $this->getLogger()->error("Unable to process the request. scalr.dns.global.enabled = false");
            return;
        }

        //Warming up static DI cache
        \Scalr::getContainer()->warmup();

        $DBDNSZone = DBDNSZone::loadById($request->id);

        $remoteBind = new Scalr_Net_Dns_Bind_RemoteBind();

        $transport = new Scalr_Net_Dns_Bind_Transports_LocalFs('/usr/sbin/rndc', '/var/named/etc/namedb/client_zones');

        $remoteBind->setTransport($transport);

        switch ($DBDNSZone->status) {
            case DNS_ZONE_STATUS::PENDING_DELETE:
            case DNS_ZONE_STATUS::INACTIVE:
                $remoteBind->removeZoneDbFile($DBDNSZone->zoneName);
                $DBDNSZone->isZoneConfigModified = 1;
                break;

            case DNS_ZONE_STATUS::PENDING_CREATE:
            case DNS_ZONE_STATUS::PENDING_UPDATE:
                $remoteBind->addZoneDbFile($DBDNSZone->zoneName, $DBDNSZone->getContents());

                if ($DBDNSZone->status == DNS_ZONE_STATUS::PENDING_CREATE) {
                    $DBDNSZone->isZoneConfigModified = 1;
                }

                $DBDNSZone->status = DNS_ZONE_STATUS::ACTIVE;
                break;
        }

        $DBDNSZone->save();

        return $request;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractTask::onCompleted()
     */
    public function onCompleted()
    {
        if (!\Scalr::config('scalr.dns.global.enabled'))
            return true;

        $db = \Scalr::getDb();

        $remoteBind = new Scalr_Net_Dns_Bind_RemoteBind();

        $transport = new Scalr_Net_Dns_Bind_Transports_LocalFs('/usr/sbin/rndc', '/var/named/etc/namedb/client_zones');

        $remoteBind->setTransport($transport);

        $zones = $db->GetAll("SELECT id FROM dns_zones WHERE iszoneconfigmodified = '1'");

        $s_zones = [];

        if (count($zones) != 0) {
            foreach ($zones as $zone) {
                $DBDNSZone = DBDNSZone::loadById($zone['id']);

                switch ($DBDNSZone->status) {
                    case DNS_ZONE_STATUS::PENDING_DELETE:
                    case DNS_ZONE_STATUS::INACTIVE:
                        $remoteBind->removeZoneFromNamedConf($DBDNSZone->zoneName);
                        break;

                    default:
                        $remoteBind->addZoneToNamedConf($DBDNSZone->zoneName, $DBDNSZone->getContents(true));
                        $DBDNSZone->status = DNS_ZONE_STATUS::ACTIVE;
                        break;
                }

                $s_zones[] = $DBDNSZone;
            }

            $remoteBind->saveNamedConf();

            foreach ($s_zones as $DBDNSZone) {
                if ($DBDNSZone->status == DNS_ZONE_STATUS::PENDING_DELETE) {
                    $DBDNSZone->remove();
                } else {
                    if ($DBDNSZone->status == DNS_ZONE_STATUS::INACTIVE) {
                        $DBDNSZone->isOnNsServer = 0;
                    } else {
                        $DBDNSZone->isOnNsServer = 1;
                    }

                    $DBDNSZone->isZoneConfigModified = 0;
                    $DBDNSZone->save();
                }
            }
        }

        $remoteBind->reloadBind();
    }
}