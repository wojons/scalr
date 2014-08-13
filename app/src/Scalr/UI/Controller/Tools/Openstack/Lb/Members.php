<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;

class Scalr_UI_Controller_Tools_Openstack_Lb_Members extends Scalr_UI_Controller
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
        $locations = self::loadController('Platforms')->getCloudLocations($this->platform, false);
        $this->response->page('ui/tools/openstack/lb/members/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function createAction()
    {
        $pools = array();
        foreach ($this->getClient()->network->lbPools->list() as $pool) {
            $pools[] = array('id' => $pool->id, 'name' => $pool->name);
        }

        $instances = array();
        foreach ($this->getClient()->servers->list() as $instance) {
            $instances[] = array('name' => $instance->name, 'id' => $instance->id);
        }

        $this->response->page('ui/tools/openstack/lb/members/create.js', array(
            'pools' => $pools,
            'instances' => $instances
        ));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'members' => array('type' => 'array')
        ));

        $platformObject = PlatformFactory::NewPlatform($this->platform);
        $request = array(
            'pool_id' => $this->getParam('pool_id'),
            'weight' => $this->getParam('weight'),
            'protocol_port' => $this->getParam('protocol_port'),
            'admin_state_up' => $this->getParam('admin_state_up')
        );

        foreach ($this->getParam('members') as $memberId) {
            $details = $this->getClient()->servers->getServerDetails($memberId);
            $ips = $platformObject->determineServerIps($this->getClient(), $details);

            $request['address'] = $ips['localIp'];
            $this->getClient()->network->lbMembers->create($request);
        }

        $this->response->success('Member(s) successfully created');
    }

    public function xListAction()
    {
        $client = $this->getClient();
        $pools = array();

        foreach ($client->network->lbPools->list() as $pool) {
            $pools[$pool->id] = $pool->name;
        }

        $items = $client->network->lbMembers->list();
        $members = array();

        foreach ($items as $item) {
            $member = get_object_vars($item);
            $member['pool_name'] = $pools[$member['pool_id']];
            $members[] = $member;
        }

        $response = $this->buildResponseFromData($members, array('id', 'pool_id'));
        $this->response->data($response);
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'memberId' => array('type' => 'json'),
            'cloudLocation'
        ));

        foreach ($this->getParam('memberId') as $memberId) {
            $this->getClient()->network->lbMembers->delete($memberId);
        }

        $this->response->success('Member(s) successfully removed');
    }
}
