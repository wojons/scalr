<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;

class Scalr_UI_Controller_Platforms_Gce extends Scalr_UI_Controller
{
    private function getFarmRoleStaticIps($cloudLocation, $farmRoleId) {
        $gceClient = $this->getGceClient($this->environment);
        $projectId = $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID);

        $map = array();

        if ($farmRoleId) {
            $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
            $this->user->getPermissions()->validate($dbFarmRole);

            $maxInstances = $dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MAX_INSTANCES);
            for ($i = 1; $i <= $maxInstances; $i++) {
                $map[] = array('serverIndex' => $i);
            }

            $servers = $dbFarmRole->GetServersByFilter();
            for ($i = 0; $i < count($servers); $i++) {
                if ($servers[$i]->status != SERVER_STATUS::TERMINATED && $servers[$i]->status != SERVER_STATUS::TROUBLESHOOTING && $servers[$i]->index) {
                    $map[$servers[$i]->index - 1]['serverIndex'] = $servers[$i]->index;
                    $map[$servers[$i]->index - 1]['serverId'] = $servers[$i]->serverId;
                    $map[$servers[$i]->index - 1]['remoteIp'] = $servers[$i]->remoteIp;
                    $map[$servers[$i]->index - 1]['instanceId'] = $servers[$i]->GetProperty(GCE_SERVER_PROPERTIES::SERVER_NAME);
                }
            }

            $ips = $this->db->GetAll('SELECT ipaddress, instance_index FROM elastic_ips WHERE farm_roleid = ?', array($dbFarmRole->ID));
            for ($i = 0; $i < count($ips); $i++) {
                $map[$ips[$i]['instance_index'] - 1]['elasticIp'] = $ips[$i]['ipaddress'];
            }
        }

        $response = $gceClient->addresses->listAddresses($projectId, $cloudLocation);

        $ips = array();
        /* @var $ip \Google_Service_Compute_Address */
        foreach ($response as $ip) {

            var_dump($ip);

            $itm = array(
                'ipAddress'  => $ip->address
            );

            $info = $this->db->GetRow("
                SELECT * FROM elastic_ips WHERE ipaddress = ? LIMIT 1
            ", array($itm['ipAddress']));

            if ($info) {
                try {
                    if ($info['server_id'] && $itm['instanceId']) {
                        $dbServer = DBServer::LoadByID($info['server_id']);
                        if ($dbServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID) != $itm['instanceId']) {
                            for ($i = 0; $i < count($map); $i++) {
                                if ($map[$i]['elasticIp'] == $itm['ipAddress'])
                                    $map[$i]['warningInstanceIdDoesntMatch'] = true;
                            }
                        }
                    }

                    $farmRole = DBFarmRole::LoadByID($info['farm_roleid']);
                    $this->user->getPermissions()->validate($farmRole);

                    $itm['roleName'] = $farmRole->GetRoleObject()->name;
                    $itm['farmName'] = $farmRole->GetFarmObject()->Name;
                    $itm['serverIndex'] = $info['instance_index'];
                } catch (Exception $e) {}
            }

            //TODO: Mark Router EIP ad USED

            $ips[] = $itm;
        }

        return array('map' => $map, 'ips' => $ips);
    }

    private function getGceClient($environment)
    {
        $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);

        $client = new Google_Client();
        $client->setApplicationName("Scalr GCE");
        $client->setScopes(array('https://www.googleapis.com/auth/compute'));

        $key = base64_decode($environment->getPlatformConfigValue(GoogleCEPlatformModule::KEY));
        $client->setAssertionCredentials(new Google_Auth_AssertionCredentials(
            $environment->getPlatformConfigValue(GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME),
            array('https://www.googleapis.com/auth/compute'),
            $key
        ));

        $client->setClientId($environment->getPlatformConfigValue(GoogleCEPlatformModule::CLIENT_ID));

        return new Google_Service_Compute($client);
    }

    public function xGetOptionsAction()
    {
        $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);

        $gceClient = $this->getGceClient($this->environment);
        $projectId = $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID);

        $data['zones'] = array();
        $zones = $gceClient->zones->listZones($projectId);
        foreach ($zones->items as $item) {
            $data['zones'][] = array(
                'name' => $item->name,
                'description' => $item->description,
                'state' => $item->status
            );
        }

        $data['regions'] = array();
        $regions = $gceClient->regions->listRegions($projectId);
        foreach ($regions->items as $item) {
            /* @var $item \Google_Service_Compute_Region */

            $zones = array();
            if (!empty($item->zones)) {
                foreach ($item->zones as $zone) {
                    $name = $p->getObjectName($zone);
                    $zones[$name] = substr($name, strrpos($name, "-")+1);
                }
            }

            $data['regions'][] = array(
                'name' => $item->name,
                'description' => $item->description,
                'state' => $item->status,
                'deprecated' => $item->getDeprecated()->state,
                'zones' => $zones
            );
        }

        $data['networks'] = array();
        $networks = $gceClient->networks->listNetworks($projectId);
        foreach ($networks->items as $item) {

            $description = ($item->description != '') ? "{$item->name} - {$item->description} ({$item->IPv4Range})" : "{$item->name} ({$item->IPv4Range})";

            $data['networks'][] = array(
                'name' => $item->name,
                'description' => $description
            );
        }

        $this->response->data(array('data' => $data));
    }

    public function xGetMachineTypesAction()
    {
        //FIXME please use getInstanceTypes() of the platform factory
        $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);

        $client = new Google_Client();
        $client->setApplicationName("Scalr GCE");
        $client->setScopes(array('https://www.googleapis.com/auth/compute'));

        $key = base64_decode($this->environment->getPlatformConfigValue(GoogleCEPlatformModule::KEY));
        $client->setAssertionCredentials(new Google_Auth_AssertionCredentials(
            $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::SERVICE_ACCOUNT_NAME),
            array('https://www.googleapis.com/auth/compute'),
            $key
        ));

        $client->setClientId($this->environment->getPlatformConfigValue(GoogleCEPlatformModule::CLIENT_ID));

        $projectId = $this->environment->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID);

        $gceClient = new Google_Service_Compute($client);

        $data['types'] = array();
        $data['dbTypes'] = array();
        $types = $gceClient->machineTypes->listMachineTypes($projectId, $this->getParam('cloudLocation'));
        foreach ($types->items as $item) {
            $isEphemeral = (substr($item->name, -2) == '-d');

            if (!$isEphemeral) {
                $data['types'][] = array(
                    'name' => $item->name,
                    'description' => "{$item->name} ({$item->description})"
                );
            }
        }

        $this->response->data(array('data' => $data));
    }

}
