<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use Scalr\Service\CloudStack\DataType\ListIpAddressesData;

class Scalr_UI_Controller_Platforms_Cloudstack extends Scalr_UI_Controller
{
    public function  buildSorter($key) {
        return function ($a, $b) use ($key) {
            return strnatcmp($a[$key], $b[$key]);
        };
    }

    public function getFarmRoleElasticIps($platform, $cloudLocation, $farmRoleId) {
        $platformName = $platform;
        $platform = PlatformFactory::NewPlatform($platform);
        $cs = $this->getEnvironment()->cloudstack($platformName);

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

        $accountName = $platform->getConfigVariable(CloudstackPlatformModule::ACCOUNT_NAME, $this->getEnvironment(), false);
        $domainId = $platform->getConfigVariable(CloudstackPlatformModule::DOMAIN_ID, $this->getEnvironment(), false);

        $requestObject = new ListIpAddressesData();
        $requestObject->account = $accountName;
        $requestObject->domainid = $domainId;
        $requestObject->zoneid = $cloudLocation;

        $ipAddresses = $cs->listPublicIpAddresses($requestObject);

        $ips = array();
        if (count($ipAddresses) > 0) {
            /* @var $ip \Scalr\Service\Aws\Ec2\DataType\AddressData */
            foreach ($ipAddresses as $address) {

                if ($address->purpose != '' && !$address->isstaticnat) {
                    continue;
                }
                if ($address->issystem) {
                    continue;
                }
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

        $cs = $this->getEnvironment()->cloudstack($this->getParam('platform'));

        $data = array();
        try {
            $data['eips'] = $this->getFarmRoleElasticIps($this->getParam('platform'), $this->getParam('cloudLocation'), $this->getParam('farmRoleId'));
        } catch (Exception $e) {
            //DO NOTHING
        }

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

        $accountName = $platform->getConfigVariable(CloudstackPlatformModule::ACCOUNT_NAME, $this->getEnvironment(), false);
        $domainId = $platform->getConfigVariable(CloudstackPlatformModule::DOMAIN_ID, $this->getEnvironment(), false);

        $data['networks'] = $this->getNetworks($this->getParam('platform'), $this->getParam('cloudLocation'));

        $requestObject = new ListIpAddressesData();
        $requestObject->account = $accountName;
        $requestObject->domainid = $domainId;
        $requestObject->zoneid = $this->getParam('cloudLocation');

        $ipAddresses = $cs->listPublicIpAddresses($requestObject);
        $data['ipAddresses'][] = array(
            'id' => "",
            'name' => "Use system defaults"
        );

        if (count($ipAddresses) > 0) {
            foreach ($ipAddresses as $address) {

                //TODO: Filter by not used IPs

                $data['ipAddresses'][] = array(
                    'id' => (string)$address->id,
                    'name' => $address->ipaddress
                );
            }
        }

        $this->response->data(array('data' => $data));
    }

    public function xGetServiceOfferingsAction()
    {
        $data = array();

        $cs = $this->getEnvironment()->cloudstack($this->getParam('platform'));

        foreach ($cs->listServiceOfferings() as $offering) {

            $data[] = array(
                'id' => (string)$offering->id,
                'name' => $offering->displaytext
            );
        }

        $this->response->data(array('data' => $data));
    }

    private function getNetworks($platformName, $cloudLocation = false, $skipScalrOptions = false)
    {
        $platform = PlatformFactory::NewPlatform($platformName);

        $cs = $this->getEnvironment()->cloudstack($platformName);

        $data = array();

        if (!$cloudLocation) {
            $cloudLocations = array_keys(self::loadController('Platforms')->getCloudLocations($platformName, false));
        } else {
            $cloudLocations = array($cloudLocation);
        }

        $accountName = $platform->getConfigVariable(CloudstackPlatformModule::ACCOUNT_NAME, $this->getEnvironment(), false);
        $domainId = $platform->getConfigVariable(CloudstackPlatformModule::DOMAIN_ID, $this->getEnvironment(), false);

        foreach ($cloudLocations as $cl) {
            $networks = $cs->network->describe(
                array(
                    'account'   => $accountName,
                    'domainid'  => $domainId,
                    'zoneid'    => $cl
                )
            );

            if (!$skipScalrOptions) {
                $data[$cl][] = array(
                    'id' => '',
                    'name' => 'Do not use network offering'
                );

                $data[$cl][] = array(
                    'id' => 'SCALR_MANUAL',
                    'name' => 'Set servers IP manually'
                );
            }

            foreach ($networks as $network) {
                $data[$cl][] = array(
                    'id' => (string)$network->id,
                    'name' => "{$network->id}: {$network->name} ({$network->networkdomain})"
                );
            }
        }

        return ($cloudLocation) ? $data[$cloudLocation] : $data;
    }

    public function xGetNetworksAction()
    {
        $data = $this->getNetworks($this->getParam('platform'), false, true);
        $this->response->data(array('data' => $data));
    }

}
