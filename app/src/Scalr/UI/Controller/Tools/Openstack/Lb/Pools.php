<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Openstack_Lb_Pools extends Scalr_UI_Controller
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
        $this->response->page('ui/tools/openstack/lb/pools/view.js');
    }

    public function createAction()
    {
        $subnets = array();
        foreach ($this->getClient()->network->subnets->list() as $sub) {
            $subnets[] = array('id' => $sub->id, 'cidr' => $sub->cidr);
        }

        $this->response->page('ui/tools/openstack/lb/pools/create.js', array(
            'subnets' => $subnets
        ));
    }

    public function editAction()
    {
        $subnets = array();
        foreach ($this->getClient()->network->subnets->list() as $sub) {
            $subnets[] = array('id' => $sub->id, 'cidr' => $sub->cidr);
        }

        $lbPool = $this->getClient()->network->lbPools->list($this->getParam('poolId'));
        $this->response->page('ui/tools/openstack/lb/pools/create.js', array(
            'pool' => get_object_vars($lbPool),
            'subnets' => $subnets
        ));
    }

    public function infoAction()
    {
        $lbPool = $this->getClient()->network->lbPools->list($this->getParam('poolId'));
        $pool = get_object_vars($lbPool);

        foreach ($this->getClient()->network->subnets->list() as $sub) {
            if ($sub->id == $pool['subnet_id'])
                $pool['subnet'] = $sub->cidr;
        }

        $this->response->page('ui/tools/openstack/lb/pools/info.js', array(
            'pool' => $pool
        ));
    }

    public function xSaveAction()
    {
        $client = $this->getClient();

        $poolId = $this->getParam('poolId');
        $request = array(
            'name' => $this->getParam('name'),
            'description' => $this->getParam('description'),
            'lb_method' => $this->getParam('lb_method'),
            'admin_state_up' => $this->getParam('admin_state_up')
        );

        if (!$poolId) {
            $request['subnet_id'] = $this->getParam('subnet_id');
            $request['protocol'] = $this->getParam('protocol');

            $client->network->lbPools->create($request);
            $this->response->success('Pool successfully created');
        } else {
            $client->network->lbPools->update($poolId, $request);
            $this->response->success('Pool successfully updated');
        }
    }

    public function xListAction()
    {
        $client = $this->getClient();

        $subnets = array();
        foreach ($this->getClient()->network->subnets->list() as $sub) {
            $subnets[$sub->id] = $sub->cidr;
        }

        $pools = array();

        $lbPools = $client->network->lbPools->list();

        foreach ($lbPools as $lbPool) {
            $lbP = get_object_vars($lbPool);
            $lbP['subnet_cidr'] = $subnets[$lbP['subnet_id']];
            $pools[] = $lbP;
        }

        $response = $this->buildResponseFromData($pools, array('id', 'subnet_id','vip_id', 'name', 'description'));
        $this->response->data($response);
    }

    public function addVipAction()
    {
        $lbPool = $this->getClient()->network->lbPools->list($this->getParam('poolId'));
        $pool = get_object_vars($lbPool);
        $subnet = array();

        foreach ($this->getClient()->network->subnets->list() as $sub) {
            if ($sub->id == $pool['subnet_id'])
                $subnet['cidr'] = $sub->cidr;
                $subnet['id'] = $sub->id;
        }

        $this->response->page('ui/tools/openstack/lb/pools/addvip.js', array(
            'subnet' => $subnet,
            'protocol' => $pool['protocol']
        ));

    }

    public function xAddVipAction()
    {
        $request = array(
            'name' => $this->getParam('name'),
            'description' => $this->getParam('description'),
            'protocol' => $this->getParam('protocol'),
            'protocol_port' => $this->getParam('protocol_port'),
            'pool_id' => $this->getParam('pool_id'),
            'admin_state_up' => $this->getParam('admin_state_up'),
            'subnet_id' => $this->getParam('subnet_id')
        );

        if ($this->getParam('address'))
            $request['address'] = $this->getParam('address');

        if ($this->getParam('connection_limit'))
            $request['connection_limit'] = $this->getParam('connection_limit');

        if ($this->getParam('session_persistence'))
            $request['session_persistence'] = array(
                'type' => $this->getParam('session_persistence'),
                'cookie_name' => $this->getParam('cookie_name')
            );

        $this->getClient()->network->lbVips->create($request);
        $this->response->success('Vip successfully created');
    }

    public function xRemoveVipAction()
    {
        $this->getClient()->network->lbVips->delete($this->getParam('vipId'));
        $this->response->success('Vip successfully deleted');
    }

    public function vipInfoAction()
    {
        $lbVip = $this->getClient()->network->lbVips->list($this->getParam('vipId'));
        $vip = get_object_vars($lbVip);

        if ($vip['session_persistence']) {
            $sess = get_object_vars($vip['session_persistence']);
            $vip['session_persistence_type'] = $sess['type'];
            $vip['session_persistence_cookie_name'] = $sess['cookie_name'];
        }

        $this->response->page('ui/tools/openstack/lb/pools/vipinfo.js', array(
            'vip' => $vip
        ));
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'poolId' => array('type' => 'json'),
            'cloudLocation'
        ));
        foreach ($this->getParam('poolId') as $poolId) {
            $this->getClient()->network->lbPools->delete($poolId);
        }

        $this->response->success('Pool(s) successfully removed');
    }
}
