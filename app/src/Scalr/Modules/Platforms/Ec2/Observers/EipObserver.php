<?php

namespace Scalr\Modules\Platforms\Ec2\Observers;

use Scalr\Service\Aws\Ec2\DataType\AssociateAddressRequestData;
use Scalr\Model\Entity;
use Scalr\Observer\AbstractEventObserver;

class EipObserver extends AbstractEventObserver
{

    public $ObserverName = 'Elastic IPs';

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Release used elastic IPs if farm terminated
     *
     * @param FarmTerminatedEvent $event
     */
    public function OnFarmTerminated(\FarmTerminatedEvent $event)
    {
        $this->Logger->info(sprintf(_("Keep elastic IPs: %s"), $event->KeepElasticIPs));
        if ($event->KeepElasticIPs == 1) return;
        $DBFarm = \DBFarm::LoadByID($this->FarmID);
        $ips = $this->DB->GetAll("SELECT * FROM elastic_ips WHERE farmid=?", array(
            $this->FarmID
        ));
        if (count($ips) > 0) {
            foreach ($ips as $ip) {
                try {
                    $DBFarmRole = \DBFarmRole::LoadByID($ip['farm_roleid']);
                    $aws = $DBFarm->GetEnvironmentObject()->aws($DBFarmRole->CloudLocation);
                    $aws->ec2->address->release($ip["ipaddress"]);
                } catch (\Exception $e) {
                    if (!stristr($e->getMessage(), "does not belong to you")) {
                        $this->Logger->error(sprintf(
                            _("Cannot release elastic IP %s from farm %s: %s"),
                            $ip['ipaddress'], $DBFarm->Name, $e->getMessage()
                        ));
                        continue;
                    }
                }
                $this->DB->Execute("DELETE FROM elastic_ips WHERE ipaddress=?", array(
                    $ip['ipaddress']
                ));
            }
        }
    }

    /**
     * Checks Elastic IP availability
     *
     * @param   string             $ipaddress public IP address
     * @param   \Scalr\Service\Aws $aws       AWS instance
     * @return  boolean Returns true if IP address is available.
     */
    private function CheckElasticIP($ipaddress, \Scalr\Service\Aws $aws)
    {
        $this->Logger->debug(sprintf(_("Checking IP: %s"), $ipaddress));
        try {
            $info = $aws->ec2->address->describe($ipaddress);
            if (count($info)) return true;
            else return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Allocate and Assign Elastic IP to instance if role use it.
     *
     * @param HostUpEvent $event
     */
    public function OnHostUp(\HostUpEvent $event)
    {
        \Scalr\Modules\Platforms\Ec2\Helpers\EipHelper::setEipForServer($event->DBServer);
    }

    /**
     * Release IP address when instance terminated
     *
     * @param HostDownEvent $event
     */
    public function OnHostDown(\HostDownEvent $event)
    {
        if ($event->DBServer->IsRebooting()) return;
        try {
            $DBFarm = \DBFarm::LoadByID($this->FarmID);
            
            $this->DB->Execute("UPDATE elastic_ips SET state='0', server_id='' WHERE server_id=?", array(
                $event->DBServer->serverId
            ));
        } catch (\Exception $e) {}
    }
}
