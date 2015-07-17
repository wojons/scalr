<?php

namespace Scalr\Modules\Platforms\Verizon\Observers;


use Scalr\Modules\PlatformFactory;
class VerizonObserver extends \EventObserver
{
    public $ObserverName = 'Verizon';

    public function OnHostInit(\HostInitEvent $event) {
        
        if ($event->DBServer->platform != \SERVER_PLATFORMS::VERIZON)
            return;
    
        try {
            $dbServer = $event->DBServer;
            $environment = $dbServer->GetEnvironmentObject();
            $client = $environment->openstack($dbServer->platform, $dbServer->GetCloudLocation());
            
            $cloudLocation = $dbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION);
            $serverId = $dbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
            $iinfo = $client->servers->getServerDetails($serverId);
            $p = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::VERIZON);
            $ips = $p->determineServerIps($client, $iinfo);
            
            if ($iinfo->security_groups) {
                //TEMPORARY OPEN PORT 22
                $ports = array(8008,8010,8013,22);
            
                $list = array();
                foreach ($iinfo->security_groups as $sg) {
                    if ($sg->name == $ips['remoteIp']) {
                        if (!$sg->id) {
                           $sgs = $client->listSecurityGroups();
                           foreach ($sgs as $sgroup) {
                               if ($sgroup->name == $sg->name)
                                   $sg = $sgroup;
                           }
                        }
                        
                        foreach ($ports as $port) {
                            $request = [
                                'security_group_id'  => $sg->id,
                                'protocol'           => 'tcp',
                                "direction"          => "ingress",
                                'port_range_min'     => $port,
                                'port_range_max'     => $port,
                                'remote_ip_prefix'   => '0.0.0.0/0',
                                'remote_group_id'    => null
                            ];
                            
                            $client->createSecurityGroupRule($request);
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            \Logger::getLogger(\LOG_CATEGORY::FARM)->error(
                new \FarmLogMessage($event->DBServer->farmId, sprintf(
                    _("Scalr was unable to open ports for server '%s': %s"),
                    $event->DBServer->serverId,
                    $e->getMessage()
                ), $event->DBServer->serverId)
            );
        }
    }
}
