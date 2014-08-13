<?php

class Scalr_UI_Controller_Tools_Openstack_Contrail_Dns extends Scalr_UI_Controller
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
        $this->response->page('ui/tools/openstack/contrail/dns/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xListAction()
    {
        $client = $this->getClient();
        $items = array();
        foreach ($client->contrail->listVirtualDns() as $item) {
            $dns = $client->contrail->listVirtualDns($item->uuid);
            $items[] = get_object_vars($dns);
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
        $listDns = array();
        foreach ($this->getClient()->contrail->listVirtualDns() as $item) {
            $listDns[] = implode(':', $item->fq_name);
        }

        $ipams = array();
        foreach ($this->getClient()->contrail->listIpam() as $item) {
            $item = get_object_vars($item);

            if ($item['fq_name'][1] == $tenantName) {
                $a = $item['fq_name'];
                array_shift($a);
                $item['name'] = implode(':', $a);
            } else {
                $item['name'] = implode(':', $item['fq_name']);
            }

            $ipams[] = $item;
        }

        if ($this->getParam('dnsId'))
            $dns = $this->getClient()->contrail->listVirtualDns($this->getParam('dnsId'));
        else
            $dns = null;

        $this->response->page('ui/tools/openstack/contrail/dns/create.js', array(
            'listDns' => $listDns,
            'dns' => $dns,
            'ipams' => $ipams,
            'fqBaseName' => array('default-domain')
        ), array('ux-boxselect.js'));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'request' => array('type' => 'json')
        ));

        if ($this->getParam('dnsId'))
            $this->getClient()->contrail->updateVirtualDns($this->getParam('dnsId'), $this->getParam('request'));
        else
            $this->getClient()->contrail->createVirtualDns($this->getParam('request'));

        $this->response->success('DNS server successfully saved');
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'dnsId' => array('type' => 'json')
        ));

        foreach ($this->getParam('dnsId') as $dnsId) {
            $this->getClient()->contrail->deleteVirtualDns($dnsId);
        }

        $this->response->success('DNS(s) successfully removed');
    }

}
