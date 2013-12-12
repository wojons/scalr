<?php

use \Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;

class Scalr_UI_Controller_Platforms_Openstack extends Scalr_UI_Controller
{
    public function xGetOpenstackResourcesAction()
    {
        $client = $this->environment->openstack($this->getParam('platform'), $this->getParam('cloudLocation'));
        $data = array();

        // List flavors
        $data['flavors'] = array();
        foreach ($client->servers->listFlavors() as $flavor) {
            $data['flavors'][] = array(
                'id' => (string)$flavor->id,
                'name' => $flavor->name
            );
        }

        if ($client->hasService('network')) {
            $data['ipPools'] = array(array('id' =>'', 'name' => ''));
            $networks = $client->network->listNetworks();
            foreach ($networks as $network) {
                if ($network->{"router:external"} == true) {
                    $data['ipPools'][] = array(
                        'id' => $network->id,
                        'name' => $network->name
                    );
                }
            }
        } else {
            //Check floating IPs
            if ($client->servers->isExtensionSupported(ServersExtension::EXT_FLOATING_IP_POOLS))
            {
                $data['ipPools'] = array(array('id' =>'', 'name' => ''));
                $pools = $client->servers->listFloatingIpPools();
                foreach ($pools as $pool) {
                    $data['ipPools'][] = array(
                        'id' => $pool->name,
                        'name' => $pool->name
                    );
                }
            }
        }


        $this->response->data(array('data' => $data));
    }
}
