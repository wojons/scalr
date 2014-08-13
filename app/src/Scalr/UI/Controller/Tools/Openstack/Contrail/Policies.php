<?php

class Scalr_UI_Controller_Tools_Openstack_Contrail_Policies extends Scalr_UI_Controller
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
        $this->response->page('ui/tools/openstack/contrail/policies/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xListAction()
    {
        $client = $this->getClient();
        $items = array();
        foreach ($client->contrail->listNetworkPolicies() as $item) {
            $policy = $client->contrail->listNetworkPolicies($item->uuid);
            $item = get_object_vars($policy);
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
        $networks = array('any');
        foreach ($this->getClient()->contrail->listVirtualNetworks() as $item) {
            $networks[] = array(implode(':', $item->fq_name));
        }

        if ($this->getParam('policyId'))
            $policy = $this->getClient()->contrail->listNetworkPolicies($this->getParam('policyId'));
        else
            $policy = null;

        $this->response->page('ui/tools/openstack/contrail/policies/create.js', array(
            'networks' => $networks,
            'policy' => $policy,
            'fqBaseName' => array('default-domain', $this->getClient()->getConfig()->getTenantName())
        ));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'request' => array('type' => 'json')
        ));

        if ($this->getParam('policyId'))
            $this->getClient()->contrail->updateNetworkPolicy($this->getParam('policyId'), $this->getParam('request'));
        else
            $this->getClient()->contrail->createNetworkPolicy($this->getParam('request'));

        $this->response->success('Network policy successfully saved');
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'policyId' => array('type' => 'json')
        ));

        foreach ($this->getParam('policyId') as $policyId) {
            $this->getClient()->contrail->deleteNetworkPolicy($policyId);
        }

        $this->response->success('Policy(s) successfully removed');
    }

}
