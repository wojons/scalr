<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Openstack_Lb_Monitors extends Scalr_UI_Controller
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
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_OPENSTACK_ELB);
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
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/openstack/lb/monitors/view.js');
    }

    public function createAction()
    {
        $pools = array();
        foreach ($this->getClient()->network->lbPools->list() as $pool) {
            $pools[] = array('id' => $pool->id, 'name' => $pool->name);
        }

        $this->response->page('ui/tools/openstack/lb/monitors/create.js', array(
            'pools' => $pools
        ));
    }

    public function infoAction()
    {
        $lbMonitor = $this->getClient()->network->lbHealthMonitors->list($this->getParam('monitorId'));
        $this->response->page('ui/tools/openstack/lb/monitors/info.js', array(
            'monitor' => get_object_vars($lbMonitor),
        ));
    }

    public function xSaveAction()
    {
        $request = array(
            'type' => $this->getParam('type'),
            'delay' => $this->getParam('delay'),
            'timeout' => $this->getParam('timeout'),
            'max_retries' => $this->getParam('max_retries'),
            'http_method' => $this->getParam('http_method'),
            'url_path' => $this->getParam('url_path'),
            'expected_codes' => $this->getParam('expected_codes'),
            'admin_state_up' => $this->getParam('admin_state_up')
        );

        $result = $this->getClient()->network->lbHealthMonitors->create($request);
        $this->getClient()->network->lbPools->associateHealthMonitor($this->getParam('pool_id'), $result->id);
        $this->response->success('Monitor successfully created');
    }

    public function xListAction()
    {
        $client = $this->getClient();
        $items = $client->network->lbHealthMonitors->list();
        $monitors = array();

        foreach ($items as $item) {
            $monitors[] = get_object_vars($item);
        }

        $response = $this->buildResponseFromData($monitors, array('id'));
        $this->response->data($response);
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'monitorId' => array('type' => 'json'),
            'cloudLocation'
        ));

        foreach ($this->getParam('monitorId') as $monitorId) {
            $this->getClient()->network->lbHealthMonitors->delete($monitorId);
        }

        $this->response->success('Monitor(s) successfully removed');
    }
}
