<?php

class Scalr_UI_Controller_Tools_Openstack_Contrail_Ipam extends Scalr_UI_Controller
{
    /**
     * Cloud location
     *
     * @var string
     */
    protected $cloudLocation;

    /**
     * Openstack platform
     *
     * @var string
     */
    protected $platform;

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess(); // && $this->request->isAllowed(Acl::RESOURCE_OPENSTACK_ELB);
    }

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::init()
     */
    public function init()
    {
        $this->cloudLocation = $this->getParam('cloudLocation');
        $this->platform = $this->getParam('platform');

        if (!$this->platform)
            throw new Exception("Cloud should be specified.");

    }

    /**
     * Gets openstack client
     *
     * @return \Scalr\Service\OpenStack\OpenStack Returns openstack client
     */
    protected function getClient()
    {
        return $this->environment->openstack($this->platform, $this->cloudLocation);
    }

    public function defaultAction()
    {
        $locations = self::loadController('Platforms')->getCloudLocations($this->platform, false);
        $this->response->page('ui/tools/openstack/contrail/ipam/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xListAction()
    {
        $client = $this->getClient();
        $items = array();
        foreach ($client->contrail->listIpam() as $item) {
            $ipam = $client->contrail->listIpam($item->uuid);
            $item = get_object_vars($ipam);

            if (isset($ipam->network_ipam_mgmt->dhcp_option_list)) {
                foreach ($ipam->network_ipam_mgmt->dhcp_option_list->dhcp_option as $dh) {
                    if ($dh->dhcp_option_name == '4')
                        $item['scalr_ntp_server_ip'] = $dh->dhcp_option_value;
                    if ($dh->dhcp_option_name == '15')
                        $item['scalr_domain_name'] = $dh->dhcp_option_value;
                }
            }

            $items[] = $item;
        }

        $response = $this->buildResponseFromData($items, array('uuid'));
        $this->response->data($response);
    }

    public function createAction()
    {
        $this->editAction();
    }

    public function editAction()
    {
        $tenantName = $this->getClient()->getConfig()->getTenantName();
        $dns = array();
        foreach ($this->getClient()->contrail->listVirtualDns() as $item) {
            $dns[] = array('uuid' => $item->uuid, 'name' => implode(':', $item->fq_name), 'fq_name' => $item->fq_name);
        }

        $networks = array();
        foreach ($this->getClient()->contrail->listVirtualNetworks() as $item) {
            if ($item->fq_name[1] == $tenantName) {
                $name = $item->fq_name[2];
            } else {
                $name = implode(':', $item->fq_name);
            }

            $networks[] = array('uuid' => $item->uuid, 'fq_name' => $item->fq_name, 'name' => $name);
        }

        if ($this->getParam('ipamId'))
            $ipam = $this->getClient()->contrail->listIpam($this->getParam('ipamId'));
        else
            $ipam = null;

        $this->response->page('ui/tools/openstack/contrail/ipam/create.js', array(
            'dns' => $dns,
            'networks' => $networks,
            'ipam' => $ipam,
            'fqBaseName' => array('default-domain', $tenantName)
        ));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'request' => array('type' => 'json')
        ));

        if ($this->getParam('ipamId'))
            $this->getClient()->contrail->updateIpam($this->getParam('ipamId'), $this->getParam('request'));
        else
            $this->getClient()->contrail->createIpam($this->getParam('request'));

        $this->response->success('IPAM successfully saved');
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'ipamId' => array('type' => 'json')
        ));

        foreach ($this->getParam('ipamId') as $ipamId) {
            $this->getClient()->contrail->deleteIpam($ipamId);
        }

        $this->response->success('IPAM(s) successfully removed');
    }

}
