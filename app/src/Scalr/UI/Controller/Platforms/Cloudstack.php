<?php

class Scalr_UI_Controller_Platforms_Cloudstack extends Scalr_UI_Controller
{
    public function  buildSorter($key) {
        return function ($a, $b) use ($key) {
            return strnatcmp($a[$key], $b[$key]);
        };
    }

    public function getFarmRoleElasticIps($platform, $cloudLocation, $farmRoleId) {
        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->getEnvironment()),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->getEnvironment()),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->getEnvironment()),
            $platform
        );

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
                    $map[$servers[$i]->index - 1]['instanceId'] = $servers[$i]->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID);
                }
            }

            $ips = $this->db->GetAll('SELECT ipaddress, instance_index FROM elastic_ips WHERE farm_roleid = ?', array($dbFarmRole->ID));
            for ($i = 0; $i < count($ips); $i++) {
                $map[$ips[$i]['instance_index'] - 1]['elasticIp'] = $ips[$i]['ipaddress'];
            }
        }

        $accountName = $platform->getConfigVariable(Modules_Platforms_Cloudstack::ACCOUNT_NAME, $this->getEnvironment(), false);
        $domainId = $platform->getConfigVariable(Modules_Platforms_Cloudstack::DOMAIN_ID, $this->getEnvironment(), false);

        $ipAddresses = $cs->listPublicIpAddresses(null, $accountName, null, $domainId, null, null, null, null, null, null, $cloudLocation);

        $ips = array();
        if (isset($ipAddresses->publicipaddress) && is_array($ipAddresses->publicipaddress)) {
            /* @var $ip \Scalr\Service\Aws\Ec2\DataType\AddressData */
            foreach ($ipAddresses->publicipaddress as $address) {

                if ($address->purpose != '' && !$address->isstaticnat)
                    continue;

                if ($address->issystem)
                    continue;

                $itm = array(
                    'ipAddress'  => $address->ipaddress,
                    'ipAddressId' => (string)$address->id,
                    'instanceId' => $address->virtualmachineid,
                );

                $info = $this->db->GetRow("
                    SELECT * FROM elastic_ips WHERE ipaddress = ? LIMIT 1
                ", array($itm['ipAddress']));

                if ($info) {
                    try {
                        if ($info['server_id'] && $itm['instanceId']) {
                            $dbServer = DBServer::LoadByID($info['server_id']);
                            if ($dbServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID) != $itm['instanceId']) {
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

                //TODO: Mark System EIP ad USED

                $ips[] = $itm;
            }
        }

        return array('map' => $map, 'ips' => $ips);
    }

    public function xGetOfferingsListAction()
    {
        $platform = PlatformFactory::NewPlatform($this->getParam('platform'));

        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->getEnvironment()),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->getEnvironment()),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->getEnvironment()),
            $this->getParam('platform')
        );

        $data = array();
        try {
            $data['eips'] = $this->getFarmRoleElasticIps($platform, $this->getParam('cloudLocation'), $this->getParam('farmRoleId'));
        } catch (Exception $e) {
            //DO NOTHING
        }

        if ($this->getParam('platform') == SERVER_PLATFORMS::UCLOUD) {
            $data = array();
            $disks = array();
            $types = array();

            foreach ($cs->listAvailableProductTypes()->producttypes as $product) {
                if (!$types[$product->serviceofferingid]) {
                    $data['serviceOfferings'][] = array(
                        'id' => (string)$product->serviceofferingid,
                        'name' => $product->serviceofferingdesc
                    );

                    $types[$product->serviceofferingid] = true;
                }

                usort($data['serviceOfferings'], $this->buildSorter('name'));
                $data['serviceOfferings'] = array_reverse($data['serviceOfferings']);

                if (!$disks[$product->diskofferingid]) {
                    $data['diskOfferings'][] = array(
                        'id' => (string)$product->diskofferingid,
                        'name' => $product->diskofferingdesc
                    );
                    $disks[$product->diskofferingid] = true;
                }
            }

            $ipAddresses = $cs->listPublicIpAddresses();
            foreach ($ipAddresses->publicipaddress as $address) {
                $data['ipAddresses'][] = array(
                    'id' => (string)$address->id,
                    'name' => $address->ipaddress
                );
            }
        } else {
            foreach ($cs->listDiskOfferings() as $offering) {
                $data['diskOfferings'][] = array(
                    'id' => (string)$offering->id,
                    'name' => $offering->displaytext,
                    'size' => $offering->disksize,
                    'type' => $offering->storagetype,
                    'custom_size' => $offering->iscustomized
                );
            }

            foreach ($cs->listServiceOfferings() as $offering) {

                $data['serviceOfferings'][] = array(
                    'id' => (string)$offering->id,
                    'name' => $offering->displaytext
                );
            }

            $accountName = $platform->getConfigVariable(Modules_Platforms_Cloudstack::ACCOUNT_NAME, $this->getEnvironment(), false);
            $domainId = $platform->getConfigVariable(Modules_Platforms_Cloudstack::DOMAIN_ID, $this->getEnvironment(), false);

            $networks = $cs->listNetworks($this->getParam('cloudLocation'), $accountName, $domainId);

            $data['networks'][] = array(
                'id' => '',
                'name' => 'Do not use network offering'
            );

            foreach ($networks as $network) {
                $data['networks'][] = array(
                        'id' => (string)$network->id,
                        'name' => "{$network->id}: {$network->name} ({$network->networkdomain})"
                );
            }

            $ipAddresses = $cs->listPublicIpAddresses(null, $accountName, null, $domainId, null, null, null, null, null, null, $this->getParam('cloudLocation'));
            $data['ipAddresses'][] = array(
                'id' => "",
                'name' => "Use system defaults"
            );

            if(isset($ipAddresses->publicipaddress) && is_array($ipAddresses->publicipaddress)) {
                foreach ($ipAddresses->publicipaddress as $address) {

                    //TODO: Filter by not used IPs

                    $data['ipAddresses'][] = array(
                        'id' => (string)$address->id,
                        'name' => $address->ipaddress
                    );
                }
            }
        }

        $this->response->data(array('data' => $data));
    }

    public function xGetServiceOfferingsAction()
    {
        $data = array();
        $platform = PlatformFactory::NewPlatform($this->getParam('platform'));

        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->getEnvironment()),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->getEnvironment()),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->getEnvironment()),
            $this->getParam('platform')
        );

        if ($this->getParam('platform') == SERVER_PLATFORMS::UCLOUD) {
            $types = array();
            foreach ($cs->listAvailableProductTypes()->producttypes as $product) {
                if (!$types[$product->serviceofferingid]) {
                    $data['serviceOfferings'][] = array(
                        'id' => (string)$product->serviceofferingid,
                        'name' => $product->serviceofferingdesc
                    );

                    $types[$product->serviceofferingid] = true;
                }

                usort($data['serviceOfferings'], $this->buildSorter('name'));
                $data = array_reverse($data['serviceOfferings']);
            }
        } else {
            foreach ($cs->listServiceOfferings() as $offering) {

                $data[] = array(
                    'id' => (string)$offering->id,
                    'name' => $offering->displaytext
                );
            }

        }
        $this->response->data(array('data' => $data));
    }

    public function xGetNetworksAction()
    {
        $platform = PlatformFactory::NewPlatform($this->getParam('platform'));

        $cs = Scalr_Service_Cloud_Cloudstack::newCloudstack(
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->getEnvironment()),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->getEnvironment()),
            $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->getEnvironment()),
            $this->getParam('platform')
        );

        $data = array();

        if (!$this->getParam('cloudLocation')) {
            $cloudLocations = array_keys(self::loadController('Platforms')->getCloudLocations($this->getParam('platform'), false));
        } else {
            $cloudLocations = array($this->getParam('cloudLocation'));
        }

        $accountName = $platform->getConfigVariable(Modules_Platforms_Cloudstack::ACCOUNT_NAME, $this->getEnvironment(), false);
        $domainId = $platform->getConfigVariable(Modules_Platforms_Cloudstack::DOMAIN_ID, $this->getEnvironment(), false);

        foreach ($cloudLocations as $cloudLocation) {
            $networks = $cs->listNetworks($cloudLocation, $accountName, $domainId);
            $data[$cloudLocation][] = array(
                'id' => '',
                'name' => 'Do not use network offering'
            );

            foreach ($networks as $network) {
                $data[$cloudLocation][] = array(
                    'id' => (string)$network->id,
                    'name' => "{$network->id}: {$network->name} ({$network->networkdomain})"
                );
            }
        }

        $this->response->data(array('data' => $data));
    }

}
