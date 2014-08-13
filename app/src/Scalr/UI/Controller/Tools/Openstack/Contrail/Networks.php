<?php

class Scalr_UI_Controller_Tools_Openstack_Contrail_Networks extends Scalr_UI_Controller
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
        $this->response->page('ui/tools/openstack/contrail/networks/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xListAction()
    {
        $client = $this->getClient();
        $items = array();
        foreach ($client->contrail->listVirtualNetworks() as $item) {
            $network = $client->contrail->listVirtualNetworks($item->uuid);
            $item = get_object_vars($network);
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
        $policies = array();
        foreach ($this->getClient()->contrail->listNetworkPolicies() as $item) {
            $policies[] = array('name' => $item->fq_name[2], 'fq_name' => $item->fq_name, 'uuid' => $item->uuid);
        }

        $ipams = array();
        foreach ($this->getClient()->contrail->listIpam() as $item) {
            $ipams[] = array('name' => $item->fq_name[2], 'fq_name' => $item->fq_name, 'uuid' => $item->uuid);
        }

        if ($this->getParam('networkId'))
            $network = $this->getClient()->contrail->listVirtualNetworks($this->getParam('networkId'));
        else
            $network = null;

        $this->response->page('ui/tools/openstack/contrail/networks/create.js', array(
            'policies' => $policies,
            'ipams' => $ipams,
            'network' => $network,
            'fqBaseName' => array('default-domain', $this->getClient()->getConfig()->getTenantName())
        ), array('ux-boxselect.js'));

    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'request' => array('type' => 'json')
        ));

        if ($this->getParam('networkId'))
            $this->getClient()->contrail->updateVirtualNetwork($this->getParam('networkId'), $this->getParam('request'));
        else
            $this->getClient()->contrail->createVirtualNetwork($this->getParam('request'));

        $this->response->success('Network successfully saved');
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'networkId' => array('type' => 'json')
        ));

        foreach ($this->getParam('networkId') as $networkId) {
            $this->getClient()->contrail->deleteVirtualNetwork($networkId);
        }

        $this->response->success('Network(s) successfully removed');
    }

}
