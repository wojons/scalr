<?php

class Scalr_UI_Controller_Platforms_Eucalyptus extends Scalr_UI_Controller
{
    public function xGetAvailZonesAction()
    {
        $euca = $this->getEnvironment()->eucalyptus($this->getParam('cloudLocation'));

        $result = $euca->ec2->availabilityZone->describe();

        $data = array();
        foreach ($result as $zone)
            $data[] = array('id' => (string) $zone->zoneName, 'name' => (string) $zone->zoneName, 'state' => 'available');

        $this->response->data(array('data' => $data));
    }
}
