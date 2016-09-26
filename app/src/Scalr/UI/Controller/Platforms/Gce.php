<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Platforms_Gce extends Scalr_UI_Controller
{
    public function xGetFarmRoleStaticIpsAction($region, $cloudLocation, $farmRoleId) {
        $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $gceClient = $p->getClient($this->environment);
        $projectId = $this->environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

        $map = [];

        if ($farmRoleId) {
            $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
            $this->user->getPermissions()->validate($dbFarmRole);

            $maxInstances = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES);

            for ($i = 1; $i <= $maxInstances; $i++) {
                $map[] = array('serverIndex' => $i);
            }

            $servers = $dbFarmRole->GetServersByFilter();

            for ($i = 0, $c = count($servers); $i < $c; $i++) {
                if ($servers[$i]->status != SERVER_STATUS::TERMINATED && $servers[$i]->index) {
                    $map[$servers[$i]->index - 1]['serverIndex'] = $servers[$i]->index;
                    $map[$servers[$i]->index - 1]['serverId'] = $servers[$i]->serverId;
                    $map[$servers[$i]->index - 1]['remoteIp'] = $servers[$i]->remoteIp;
                    $map[$servers[$i]->index - 1]['instanceId'] = $servers[$i]->GetProperty(GCE_SERVER_PROPERTIES::SERVER_NAME);
                }
            }

            $ips = $this->db->GetAll('SELECT ipaddress, instance_index FROM elastic_ips WHERE farm_roleid = ?', array($dbFarmRole->ID));
            for ($i = 0, $c = count($ips); $i < $c; $i++) {
                $map[$ips[$i]['instance_index'] - 1]['elasticIp'] = $ips[$i]['ipaddress'];
            }
        }

        $response = $gceClient->addresses->listAddresses($projectId, $region);

        $ips = [];

        /* @var $ip \Google_Service_Compute_Address */
        foreach ($response as $ip) {
            $itm = array(
                'ipAddress'  => $ip->getAddress(),
                'description' => $ip->getDescription()
            );
            if ($ip->status == 'IN_USE')
                $itm['instanceId'] = substr(strrchr($ip->users[0], "/"), 1);

            $info = $this->db->GetRow("SELECT * FROM elastic_ips WHERE ipaddress = ? LIMIT 1", [$itm['ipAddress']]);

            if ($info) {
                try {
                    if ($info['server_id'] == $itm['instanceId']) {
                        for ($i = 0, $c = count($map); $i < $c; $i++) {
                            if ($map[$i]['elasticIp'] == $itm['ipAddress'])
                                $map[$i]['warningInstanceIdDoesntMatch'] = true;
                        }
                    }

                    $farmRole = DBFarmRole::LoadByID($info['farm_roleid']);
                    $this->user->getPermissions()->validate($farmRole);

                    $itm['roleName'] = $farmRole->Alias;
                    $itm['farmName'] = $farmRole->GetFarmObject()->Name;
                    $itm['serverIndex'] = $info['instance_index'];
                } catch (Exception $e) {}
            }

            //Invar: Mark Router EIP ad USED

            $ips[] = $itm;
        }

        $this->response->data(['data' => ['staticIps' => ['map' => $map, 'ips' => $ips]]]);
    }

    public function xGetOptionsAction()
    {
        $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);

        $gceClient = $p->getClient($this->environment);

        $projectId = $this->environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

        $data = ['zones' => []];

        $zones = $gceClient->zones->listZones($projectId);

        foreach ($zones->items as $item) {
            if ($item->deprecated) {
                $item->description .= " (Deprecated)";
            }

            $data['zones'][] = [
                'name'        => $item->name,
                'description' => $item->description,
                'state'       => $item->status
            ];
        }

        $data['regions'] = [];

        $regions = $gceClient->regions->listRegions($projectId);

        foreach ($regions->items as $item) {
            /* @var $item \Google_Service_Compute_Region */
            $zones = [];

            if (!empty($item->zones)) {
                foreach ($item->zones as $zone) {
                    $name = $p->getObjectName($zone);
                    $zones[$name] = substr($name, strrpos($name, "-")+1);
                }
            }

            $data['regions'][] = [
                'name'        => $item->name,
                'description' => $item->description,
                'state'       => $item->status,
                'deprecated'  => isset($item->getDeprecated()->state) ? $item->getDeprecated()->state : null,
                'zones'       => $zones
            ];
        }

        $data['networks'] = [];
        $networks = $gceClient->networks->listNetworks($projectId);

        foreach ($networks->items as $item) {
            $iPv4Range = empty($item->iPv4Range) ? '' : "({$item->iPv4Range})";

            $description = ($item->description != '') ?
                "{$item->name} - {$item->description} {$iPv4Range}" :
                "{$item->name} {$iPv4Range}";

            $data['networks'][] = [
                'name'        => $item->name,
                'description' => $description
            ];
        }

        $diskTypes = $gceClient->diskTypes->listDiskTypes($projectId, $data['zones'][0]['name']);

        foreach ($diskTypes as $diskType) {
            /* @var $diskType \Google_Service_Compute_DiskType */
            $data['diskTypes'][] = [
                'name'        => $diskType->name,
                'description' => $diskType->description,
                'defaultSize' => $diskType->defaultDiskSizeGb
            ];
        }

        $this->response->data(['data' => $data]);
    }

    /**
     * Gets the list of network subnets
     *
     * @param string $cloudLocation Aws region
     * @param string $name          Network name
     * @throws Exception
     */
    public function xGetSubnetsAction($cloudLocation, $name)
    {
        $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);

        $gceClient = $p->getClient($this->environment);

        $projectId = $this->environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

        $network = $gceClient->networks->get($projectId, $name);

        $subnets = false;

        if (empty($network->iPv4Range)) {
            $networkSubnets = $gceClient->subnetworks->listSubnetworks($projectId, $cloudLocation, ['filter' => "network eq {$network->selfLink}"]);

            $subnets = [];

            foreach ($networkSubnets as $subnet) {
                $subnets[] = [
                    'name'        => $subnet->name,
                    'description' => "{$subnet->name} ({$subnet->ipCidrRange})"
                ];
            }
        }

        $this->response->data(['data' => $subnets]);
    }

    /**
     * FIXME NOT USED. Should we remove this method?
     *
     * @deprecated
     */
    public function xGetMachineTypesAction()
    {
        $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);

        $data = [
            'types'     => [],
            'diskTypes' => [],
            'dbTypes'   => []
        ];

        $items = $p->getInstanceTypes($this->environment, $this->getParam('cloudLocation'), true);

        foreach ($items as $item) {
            $data['types'][] = [
                'name'        => $item['name'],
                'description' => $item['name'] . " (" . $item['description'] . ")"
            ];
        }


        $this->response->data(['data' => $data]);
    }
}
