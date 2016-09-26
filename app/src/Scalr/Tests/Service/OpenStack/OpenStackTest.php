<?php
namespace Scalr\Tests\Service\OpenStack;

use Scalr\Service\OpenStack\Services\Network\Type\CreateRouter;
use Scalr\Service\OpenStack\Services\Network\Type\NetworkExtension;
use Scalr\Service\OpenStack\Services\Servers\Type\Personality;
use Scalr\Service\OpenStack\Services\Servers\Type\PersonalityList;
use Scalr\Service\OpenStack\Services\Volume\Type\VolumeStatus;
use Scalr\Service\OpenStack\Exception\OpenStackException;
use Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;
use Scalr\Service\OpenStack\Exception\RestClientException;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\Type\DefaultPaginationList;
use Scalr\Service\OpenStack\Client\RestClient;
use Scalr\Service\OpenStack\OpenStackConfig;
use ReflectionMethod;

/**
 * OpenStack TestCase
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    05.12.2012
 */
class OpenStackTest extends OpenStackTestCase
{

    const VOLUME_SIZE = 100;

    const NAME_NETWORK = 'net';

    const NAME_SUBNET = 'subnet';

    const NAME_PORT = 'port';

    const NAME_ROUTER = 'router';

    const NAME_LB_POOL = 'lbpool';

    const NAME_LB_VIP = 'vip';

    const NAME_FQ = 'fq';

    const NAME_SECURITY_GROUP = 'sg';

    const NAME_FQ_NETWORK = 'net';

    const NAME_DOMAIN = 'd';

    const NAME_NETWORK_POLICY = 'np';

    const DNS_SERVER_ADDRESS = '7.7.7.7';

    const NEXT_VIRTUAL_DNS_ADDRESS = '5.6.7.8';

    const SUBNET_CIDR = '10.0.3.0/24';

    const LB_MEMBER_ADDRESS = '10.0.3.5';

    const EMPTY_CONFIG = -1;

    const ABSTRACT_PAGINATION_CLASS = 'Scalr\\Service\\OpenStack\\Type\\AbstractPaginationList';

    /**
     * Provider of the instances for the functional tests
     */
    public function providerRs()
    {
        $data = \Scalr::config('scalr.phpunit.openstack.platforms');

        if (empty($data) || !is_array($data)) {
            return array(
                array(self::EMPTY_CONFIG, 'DFW', '3afe97b2-26dc-49c5-a2cc-a2fc8d80c001')
            );
        }

        return $data;
    }

    /**
     * Gets test server name
     *
     * @param   string $suffix optional Name suffix
     * @return  string Returns test server name
     */
    public static function getTestServerName($suffix = '')
    {
        return self::getTestName('server' . (!empty($suffix) ? '-' . $suffix : ''));
    }

    /**
     * Gets test volume name
     *
     * @param   string $suffix optional Name suffix
     * @return  string Returns test volume name
     */
    public static function getTestVolumeName($suffix = '')
    {
        return self::getTestName('volume' . (!empty($suffix) ? '-' . $suffix : ''));
    }

    /**
     * Gets test snapshot name
     *
     * @param   string $suffix optional Name suffix
     * @return  string Returns test snapshot name
     */
    public static function getTestSnapshotName($suffix = '')
    {
        return self::getTestName('snapshot' . (!empty($suffix) ? '-' . $suffix : ''));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service\OpenStack.OpenStackTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service\OpenStack.OpenStackTestCase::tearDown()
     */
    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testGetAvailableServices()
    {
        $avail = OpenStack::getAvailableServices();
        $this->assertNotEmpty($avail);
        $this->assertInternalType('array', $avail);
        $this->assertArrayHasKey('servers', $avail);
        $this->assertArrayNotHasKey('abstract', $avail);
    }

    /**
     * @test
     */
    public function testApiClientOptions()
    {
        $client = new RestClient(new OpenStackConfig('foo', 'foo', 'regionOne'));
        $this->assertInstanceOf('Scalr\\Service\\OpenStack\\Client\\RestClient', $client);

        $refMethod = new ReflectionMethod($client, 'createHttpRequest');
        $refMethod->setAccessible(true);
        $res = $refMethod->invoke($client);

        $opt = $res->getOptions();

        $this->assertEquals(\Scalr::config('scalr.openstack.api_client.timeout'), $opt['timeout']);
    }

    /**
     * @test
     * @dataProvider providerRs
     * @functional
     */
    public function testFunctionalOpenStack($platform, $region, $imageId)
    {
        if ($platform === self::EMPTY_CONFIG) {
            $this->markTestSkipped();
        }

        /* @var $rs OpenStack */
        if ($this->getContainer()->environment->isPlatformEnabled($platform)) {
            $rs = $this->getContainer()->openstack($platform, $region);
            //$rs->setDebug();
            $this->assertInstanceOf($this->getOpenStackClassName('OpenStack'), $rs);
        } else {
            //Environment has not been activated yet.
            $this->markTestSkipped(sprintf('Environment for the "%s" platform has not been activated.', $platform));
        }

        $os = $this->getContainer()->openstack($platform, 'INVALID-REGION-TEST');
        try {
            $ext = $os->servers->listExtensions();
            unset($os);
            $this->assertTrue(false, 'An exception must be thrown in this test');
        } catch (OpenStackException $e) {
            $this->assertTrue(true);
        }
        unset($os);

        // security groups test
        $sgTestName = self::getTestName(self::NAME_SECURITY_GROUP);

        $listSecurityGroups = $rs->listSecurityGroups();
        $this->assertTrue($listSecurityGroups instanceof \ArrayIterator);
        foreach ($listSecurityGroups as $v) {
            if ($v->name == self::getTestName('security-group')) {
                $rs->deleteSecurityGroup($v->id);
            }
        }
        unset($listSecurityGroups);

        //Create security group test
        $sg = $rs->createSecurityGroup($sgTestName, 'phpunit test security group');
        $this->assertNotEmpty($sg);
        $this->assertInternalType('object', $sg);
        $this->assertNotEmpty($sg->id);
        $this->assertEquals($sgTestName, $sg->name);
        $this->assertNotEmpty($sg->description);

        $ruleToAdd = [
            "security_group_id" => $sg->id,
            "protocol"          => 'tcp',
            "remote_group_id"   => $sg->id,
            "direction"         => "ingress",
            "port_range_max"    => null,
            "port_range_min"    => null,
        ];

        //Add a new rule to security group
        $rule = $rs->createSecurityGroupRule($ruleToAdd);
        $this->assertNotEmpty($rule);
        $this->assertNotEmpty($rule->id);
        $this->assertInternalType('object', $rule);

        //Removes rule
        $ret = $rs->deleteSecurityGroupRule($rule->id);
        $this->assertTrue($ret);

        //Delete security group test
        $ret = $rs->deleteSecurityGroup($sg->id);
        $this->assertTrue($ret);
        unset($sg);

        //Pagination test
        $list = $rs->servers->listImages(true, array('limit' => 10));
        $this->assertInstanceOf(self::ABSTRACT_PAGINATION_CLASS, $list);
        do {
            foreach ($list as $image) {
                $this->assertNotEmpty($image->id);
            }
        } while (false !== ($list = $list->getNextPage()));

        $one = $rs->servers;
        $this->assertInstanceOf($this->getOpenStackClassName('Services\\ServersService'), $one);
        $two = $rs->servers;
        $this->assertInstanceOf($this->getOpenStackClassName('Services\\ServersService'), $two);
        $this->assertSame($one, $two, 'Service interface is expected to be cached within each separate OpenStack instance.');

        $aZones = $rs->listZones();
        $this->assertNotEmpty($aZones);
        unset($aZones);

        //List tenants test
        //IMPORTANT: It does not work with API v3
        //$tenants = $rs->listTenants();
        //$this->assertNotEmpty($tenants);
        //$this->assertTrue(is_array($tenants));
        //unset($tenants);

        //Get Limits test
        $limits = $rs->servers->getLimits();
        $this->assertTrue(is_object($limits));
        unset($limits);

        $aExtensions = $rs->servers->listExtensions();
        $this->assertTrue(is_array($aExtensions));
        unset($aExtensions);

        $aExtensions = $rs->volume->listExtensions();
        $this->assertTrue(is_array($aExtensions));
        unset($aExtensions);

        $hasNetwork = $rs->hasService(OpenStack::SERVICE_NETWORK);
        if ($hasNetwork) {
            $aExtensions = $rs->network->listExtensions();
            $this->assertTrue(is_array($aExtensions));
            unset($aExtensions);

            //Quantum API tests
            $testNetworkName = self::getTestName(self::NAME_NETWORK);
            $testSubnetName = self::getTestName(self::NAME_SUBNET);
            $testPortName = self::getTestName(self::NAME_PORT);
            $testRouterName = self::getTestName(self::NAME_ROUTER);
            $testLbPoolName = self::getTestName(self::NAME_LB_POOL);
            $testLbVipName = self::getTestName(self::NAME_LB_VIP);

            //ListNetworks test
            $networks = $rs->network->networks->list(null, array(
                'status' => 'ACTIVE',
                'shared' => false
            ));

            $this->assertTrue($networks instanceof \ArrayIterator);

            if (isset($networks[0])) {
                $this->assertInternalType('object', $networks[0]);
                $this->assertNotEmpty($networks[0]->id);

                //Show Network test
                $network = $rs->network->networks->list($networks[0]->id);
                $this->assertEquals($networks[0], $network);
                unset($network);
            }

            $publicNetworkId = null;

            foreach ($networks as $network) {
                if ($network->{"router:external"} == true) {
                    $publicNetworkId = $network->id;
                }
            }

            unset($networks);

            if (empty($publicNetworkId)) {
                $networks = $rs->network->networks->list();

                foreach ($networks as $network) {
                    if ($network->shared == true && $network->status == 'ACTIVE') {
                        $publicNetworkId = $network->id;
                        break;
                    }
                }

                unset($networks);
            }

            $this->assertNotEmpty($publicNetworkId, 'Could not find public network to continue.');

            //ListSubnets test
            $subnets = $rs->network->subnets->list();
            $this->assertTrue($subnets instanceof \ArrayIterator);
            if (isset($subnets[0])) {
                $this->assertInternalType('object', $subnets[0]);
                $this->assertNotEmpty($subnets[0]->id);

                //Show Subnet test
                $subnet = $rs->network->subnets->list($subnets[0]->id);
                $this->assertEquals($subnets[0], $subnet);
                unset($subnet);
            }
            unset($subnets);

            //ListPorts test
            $ports = $rs->network->ports->list();
            $this->assertTrue($ports instanceof \ArrayIterator);
            if (isset($ports[0])) {
                $this->assertInternalType('object', $ports[0]);
                $this->assertNotEmpty($ports[0]->id);

                //Show Port test
                $port = $rs->network->ports->list($ports[0]->id);
                $this->assertEquals($ports[0], $port);
                unset($port);
            }
            unset($ports);

            if ($rs->network->isExtensionSupported(NetworkExtension::loadbalancingService())) {
                //Removes previously created LBaaS VIP if it exists
                $lbVips = $rs->network->lbVips->list(null, array(
                    'name' => $testLbVipName,
                ));
                foreach ($lbVips as $lbVip) {
                    $ret = $rs->network->lbVips->delete($lbVip->id);
                    $this->assertTrue($ret, 'Could not remove previously created Load balancer VIP (id:' . $lbVip->id . ').');
                }
                $this->assertTrue($lbVips instanceof \ArrayIterator);
                unset($lbVips);

                //Removes previously created pools if they exist.
                $lbPools = $rs->network->lbPools->list(null, array(
                    'name' => $testLbPoolName,
                ));
                $this->assertTrue($lbPools instanceof \ArrayIterator);
                foreach ($lbPools as $lbPool) {
                    if (!empty($lbPool->health_monitors)) {
                        foreach ($lbPool->health_monitors as $healthMonitorId) {
                            //Removes previously associated health monitors with test pools
                            $ret = $rs->network->lbPools->disassociateHealthMonitor($lbPool->id, $healthMonitorId);
                            $this->assertTrue($ret);
                            $ret = $rs->network->lbHealthMonitors->delete($healthMonitorId);
                            $this->assertTrue($ret);
                        }
                    }
                    if (!empty($lbPool->members)) {
                        foreach ($lbPool->members as $memberId) {
                            //Remove previously created members
                            $ret = $rs->network->lbMembers->delete($memberId);
                            $this->assertTrue($ret, 'Could not remove previously created LBaas member (id:' . $memberId . ').');
                        }
                    }
                    $ret = $rs->network->lbPools->delete($lbPool->id);
                    $this->assertTrue($ret, 'Could not remove previously created LBaaS pool.');
                }
                unset($lbPools);
            }

            //Tries to find the ports which have been created recently by this test
            $ports = $rs->network->ports->list(null, array(
                'name' => array($testPortName, $testPortName . '1')
            ));
            foreach ($ports as $port) {
                //Removes previously created ports
                $rs->network->ports->delete($port->id);
            }
            unset($ports);

            //Tries to find the networks that have been created recently by this test
            $networks = $rs->network->networks->list(null, array(
                'name' => $testNetworkName
            ));
            foreach ($networks as $network) {
                //Removes previously created networks
                $rs->network->networks->update($network->id, null, false);
                //Trying to remove allocated ports
                $portsToRemove = $rs->network->ports->list(null, array('networkId' => $network->id));
                foreach ($portsToRemove as $p) {
                    if (isset($p->device_owner) && isset($p->device_id) && $p->device_owner == 'network:router_interface') {
                        $rs->network->ports->update($p->id, array('admin_state_up' => false));
                        $rs->network->routers->removeInterface($p->device_id, null, $p->id);
                    }
                }
                $rs->network->networks->delete($network->id);
            }
            unset($networks);

            //Tries to find the subnets that where created by this test but hadn't been removed yet.
            $subnets = $rs->network->subnets->list(null, array(
                'name' => array($testSubnetName, $testSubnetName . '1')
            ));
            $this->assertTrue($subnets instanceof \ArrayIterator);
            foreach ($subnets as $subnet) {
                //Removes previously created subnets
                $rs->network->subnets->delete($subnet->id);
            }

            //Creates new network
            $network = $rs->network->networks->create($testNetworkName, false, false);
            $this->assertInternalType('object', $network);
            $this->assertNotEmpty($network->id);
            $this->assertEquals(false, $network->admin_state_up);
            $this->assertEquals(false, $network->shared);

            //Updates newtork state
            $network = $rs->network->networks->update($network->id, null, true);
            $this->assertInternalType('object', $network);
            $this->assertEquals(true, $network->admin_state_up);

            //Creates subnet
            $subnet = $rs->network->subnets->create(array(
                'network_id'       => $network->id,
                //ip_version is set internally with 4, but you can provide it explicitly
                'cidr'             => self::SUBNET_CIDR,
                'name'             => $testSubnetName,
            ));
            $this->assertInternalType('object', $subnet);
            $this->assertEquals($testSubnetName, $subnet->name);
            $this->assertNotEmpty($subnet->id);

            //Updates the subnet
            $subnet = $rs->network->subnets->update($subnet->id, array(
                'name' => $testSubnetName . '1'
            ));
            $this->assertInternalType('object', $subnet);
            $this->assertNotEmpty($subnet->name);
            $this->assertEquals($testSubnetName . '1', $subnet->name);

            //Load Balancing Service (LBaaS) tests
            if ($rs->network->isExtensionSupported(NetworkExtension::loadbalancingService())) {
                $this->assertNotEmpty($subnet->id, 'Subnet is needed to proceed.');

                //The tenant creates a pool, which is initially empty
                $lbPool = $rs->network->lbPools->create(array(
                    'subnet_id' => $subnet->id,
                    'lb_method' => 'ROUND_ROBIN',
                    'protocol'  => 'TCP',
                    'name'      => $testLbPoolName,
                    //Current tenant will be used by default
                    //'tenant_id' => $rs->getConfig()->getAuthToken()->getTenantId(),
                ));
                $this->assertNotEmpty($lbPool);
                $this->assertInstanceOf('stdClass', $lbPool);
                $this->assertEquals($testLbPoolName, $lbPool->name);
                $this->assertNotEmpty($lbPool->id);

                //Tests update pool method
                $lbPool = $rs->network->lbPools->update($lbPool->id, array(
                    'name' => $testLbPoolName,
                ));
                $this->assertNotEmpty($lbPool);
                $this->assertInstanceOf('stdClass', $lbPool);
                $this->assertEquals($testLbPoolName, $lbPool->name);

                //The tenant creates one or several members in the pool
                $lbMember = $rs->network->lbMembers->create(array(
                    'pool_id' => $lbPool->id,
                    'protocol_port' => 8080,
                    'address' => self::LB_MEMBER_ADDRESS,
                    'weight' => 2
                ));
                $this->assertNotEmpty($lbMember);
                $this->assertInternalType('object', $lbMember);
                $this->assertEquals($lbPool->id, $lbMember->pool_id);
                $this->assertTrue($lbMember->admin_state_up);

                //Tests update member method
                $lbMember = $rs->network->lbMembers->update($lbMember->id, array(
                    'weight' => 3,
                ));
                $this->assertNotEmpty($lbMember);
                $this->assertInternalType('object', $lbMember);
                $this->assertEquals(3, $lbMember->weight);

                //The tenant create one or several health monitors
                $lbHealthMonitor = $rs->network->lbHealthMonitors->create(array(
                    'delay'       => 4,
                    'max_retries' => 3,
                    'type'        => 'TCP',
                    'timeout'     => 1,
                ));
                $this->assertNotEmpty($lbHealthMonitor);
                $this->assertInternalType('object', $lbHealthMonitor);
                $this->assertNotEmpty($lbHealthMonitor->id);
                $this->assertTrue($lbHealthMonitor->admin_state_up);
                $this->assertEquals(4, $lbHealthMonitor->delay);
                $this->assertEquals(3, $lbHealthMonitor->max_retries);
                $this->assertEquals('TCP', $lbHealthMonitor->type);
                $this->assertEquals(1, $lbHealthMonitor->timeout);

                //Tests update health monitor
                $lbHealthMonitor = $rs->network->lbHealthMonitors->update($lbHealthMonitor->id, array(
                    'max_retries' => 4,
                ));
                $this->assertNotEmpty($lbHealthMonitor);
                $this->assertInternalType('object', $lbHealthMonitor);
                $this->assertNotEmpty($lbHealthMonitor->id);
                $this->assertEquals(4, $lbHealthMonitor->max_retries);

                //The tenant associates the Health Monitors with the Pool
                $ret = $rs->network->lbPools->associateHealthMonitor($lbPool->id, $lbHealthMonitor->id);
                $this->assertInternalType('object', $ret);

                //Checks if health monitor is successfully associated
                $tmpPool = $rs->network->lbPools->list($lbPool->id);
                $this->assertNotEmpty($tmpPool);
                $this->assertInternalType('object',$tmpPool);
                $this->assertEquals($lbPool->id, $tmpPool->id);
                $this->assertNotEmpty($tmpPool->health_monitors);
                $this->assertContains($lbHealthMonitor->id, $tmpPool->health_monitors);
                $lbPool = $tmpPool;
                unset($tmpPool);

                //The tenant finally creates a VIP associated with the Pool
                $lbVip = $rs->network->lbVips->create(array(
                    'protocol'      => 'TCP',
                    'protocol_port' => 8080,
                    'name'          => $testLbVipName,
                    'subnet_id'     => $subnet->id,
                    'pool_id'       => $lbPool->id,
                ));
                $this->assertNotEmpty($lbVip);
                $this->assertInternalType('object', $lbVip);
                $this->assertEquals('TCP', $lbVip->protocol);
                $this->assertEquals(8080, $lbVip->protocol_port);
                $this->assertEquals($testLbVipName, $lbVip->name);
                $this->assertEquals($subnet->id, $lbVip->subnet_id);
                $this->assertEquals($lbPool->id, $lbVip->pool_id);

                //Tests update method
                $lbVip = $rs->network->lbVips->update($lbVip->id, array(
                    'name' => $testLbVipName,
                ));
                $this->assertNotEmpty($lbVip);
                $this->assertInternalType('object', $lbVip);

                sleep(1);

                //Deletes VIP
                $ret = $rs->network->lbVips->delete($lbVip->id);
                $this->assertTrue($ret);

                //Disassotiates the Health Monitors with the pool
                $ret = $rs->network->lbPools->disassociateHealthMonitor($lbPool->id, $lbHealthMonitor->id);
                $this->assertTrue($ret);

                //Checks if health monitor is successfully disassociated
                $tmpPool = $rs->network->lbPools->list($lbPool->id);
                $this->assertNotEmpty($tmpPool);
                $this->assertInternalType('object',$tmpPool);
                $this->assertEquals($lbPool->id, $tmpPool->id);
                $this->assertNotContains($lbHealthMonitor->id, $tmpPool->health_monitors);
                $lbPool = $tmpPool;
                unset($tmpPool);

                //Deletes LBaaS health monitor
                $ret = $rs->network->lbHealthMonitors->delete($lbHealthMonitor->id);
                $this->assertTrue($ret);

                //Delete LBaaS member
                $ret = $rs->network->lbMembers->delete($lbMember->id);
                $this->assertTrue($ret);

                //Delete LBaaS pool
                $ret = $rs->network->lbPools->delete($lbPool->id);
                $this->assertTrue($ret);
            }

            //Removes subnet
            $ret = $rs->network->subnets->delete($subnet->id);
            $this->assertTrue($ret);

            //Removes created network
            $rs->network->networks->update($network->id, null, false);
            $ret = $rs->network->networks->delete($network->id);
            $this->assertTrue($ret);
            unset($network);

            //Security group extension test
            if ($rs->network->isExtensionSupported(NetworkExtension::securityGroup())) {
                $sgTestName = self::getTestName(self::NAME_SECURITY_GROUP);

                //Removes previously created test security group if it actually exists
                $sgList = $rs->network->securityGroups->list(null, ['name' => $sgTestName]);
                $this->assertInstanceOf(self::ABSTRACT_PAGINATION_CLASS, $sgList);

                foreach ($sgList as $sg) {
                    $this->assertNotEmpty($sg->name);
                    $this->assertNotEmpty($sg->id);

                    if ($sg->name === $sgTestName) {
                        //Removes previously created test security group
                        $res = $rs->network->securityGroups->delete($sg->id);
                        $this->assertTrue($res);
                    }
                }
                unset($sgList);

                //List Security groups test
                $sgList = $rs->network->securityGroups->list();
                $this->assertInstanceOf(self::ABSTRACT_PAGINATION_CLASS, $sgList);
                unset($sgList);

                //Create security group test
                $sg = $rs->network->securityGroups->create($sgTestName, 'phpunit test security group');
                $this->assertNotEmpty($sg);
                $this->assertInternalType('object', $sg);
                $this->assertNotEmpty($sg->id);
                $this->assertEquals($sgTestName, $sg->name);
                $this->assertNotEmpty($sg->description);

                //Update security group test
                //ListRules test
                //Gets the rules set for the created security group
                $rulesList = $rs->network->securityGroups->listRules(null, ['securityGroupId' => $sg->id]);
                $this->assertInstanceOf(self::ABSTRACT_PAGINATION_CLASS, $rulesList);
                if (count($rulesList)) {
                    //Some providers have default rules out from the box
                    foreach ($rulesList as $r) {
                        //Removing default rules
                        $rs->network->securityGroups->deleteRule($r->id);
                    }
                }

                $ruleToAdd = [
                    "security_group_id" => $sg->id,
                    "remote_group_id"   => null,
                    "direction"         => "ingress",
                    "remote_ip_prefix"  => "0.0.0.0/0",
                    "port_range_max"    => null,
                    "port_range_min"    => null,
                ];

                //Add a new rule to security group
                $rule = $rs->network->securityGroups->addRule($ruleToAdd);
                $this->assertNotEmpty($rule);
                $this->assertNotEmpty($rule->id);
                $this->assertInternalType('object', $rule);

                //Verifies that all properties are set properly
                foreach ($ruleToAdd as $property => $value) {
                    $this->assertObjectHasAttribute($property, $rule);
                    $this->assertEquals($value, $rule->$property);
                }

                //Checks that new rule does exist
                $rulesList = $rs->network->securityGroups->listRules(null, ['securityGroupId' => $sg->id]);
                $this->assertEquals(1, count($rulesList));
                unset($rulesList);

                //Removes rule
                $ret = $rs->network->securityGroups->deleteRule($rule->id);
                $this->assertTrue($ret);

                //Checks whether rule is removed properly
                $rulesList = $rs->network->securityGroups->listRules(null, ['securityGroupId' => $sg->id]);
                $this->assertEquals(0, count($rulesList));
                unset($rulesList);

                //Delete security group test
                $ret = $rs->network->securityGroups->delete($sg->id);
                $this->assertTrue($ret);
                unset($sg);
            }
        }

        //List snapshots test
        $snList = $rs->volume->snapshots->list();
        $this->assertTrue($snList instanceof \ArrayIterator);
        foreach ($snList as $v) {
            if ($v->display_name == self::getTestSnapshotName()) {
                $rs->volume->snapshots->delete($v->id);
            }
        }
        unset($snList);

        //List Volume Types test
        $volumeTypes = $rs->volume->listVolumeTypes();
        $this->assertTrue($volumeTypes instanceof \ArrayIterator);
        foreach ($volumeTypes as $v) {
            $volumeTypeDesc = $rs->volume->getVolumeType($v->id);
            $this->assertTrue(is_object($volumeTypeDesc));
            unset($volumeTypeDesc);
            break;
        }

        //List Volumes test
        $aVolumes = $rs->volume->listVolumes();
        $this->assertTrue($aVolumes instanceof \ArrayIterator);
        foreach ($aVolumes as $v) {
            if ($v->display_name == self::getTestVolumeName()) {
                if (in_array($v->status, array(VolumeStatus::STATUS_AVAILABLE, VolumeStatus::STATUS_ERROR))) {
                    $ret = $rs->volume->deleteVolume($v->id);
                }
            }
        }

        //Create Volume test
        $volume = $rs->volume->createVolume(self::VOLUME_SIZE, self::getTestVolumeName());
        $this->assertTrue(is_object($volume));
        $this->assertNotEmpty($volume->id);

        for ($t = time(), $s = 1; (time() - $t) < 300 &&
            !in_array($volume->status, array(VolumeStatus::STATUS_AVAILABLE, VolumeStatus::STATUS_ERROR)); $s += 5) {
            sleep($s);
            $volume = $rs->volume->getVolume($volume->id);
            $this->assertTrue(is_object($volume));
            $this->assertNotEmpty($volume->id);
        }
        $this->assertContains($volume->status, array(VolumeStatus::STATUS_AVAILABLE, VolumeStatus::STATUS_ERROR));

//         //Create snapshot test
//         //WARNING! It takes too long time.
//         $snap = $rs->volume->snapshots->create($volume->id, self::getTestSnapshotName());
//         $this->assertTrue(is_object($snap));
//         $this->assertNotEmpty($snap->id);
//         for ($t = time(), $s = 1; (time() - $t) < 600 && !in_array($snap->status, array('available', 'error')); $s += 5) {
//             sleep($s);
//             $snap = $rs->volume->snapshots->get($snap->id);
//             $this->assertNotEmpty($snap->id);
//         }
//         $this->assertContains($snap->status, array('available', 'error'));

//         //Delete snapshot test
//         $ret = $rs->volume->snapshots->delete($snap->id);
//         $this->assertTrue($ret);
//         unset($snap);

//         sleep(5);

        //Delete Volume test
        $ret = $rs->volume->deleteVolume($volume->id);
        $this->assertTrue($ret);
        unset($volume);

        sleep(5);

        $pool = null;
        if ($rs->servers->isExtensionSupported(ServersExtension::floatingIpPools())) {
            $aFloatingIpPools = $rs->servers->listFloatingIpPools();
            $this->assertTrue($aFloatingIpPools instanceof \ArrayIterator);
            foreach ($aFloatingIpPools as $v) {
                $pool = $v->name;
                break;
            }
            $this->assertNotNull($pool);
            unset($aFloatingIpPools);
        }
        if ($rs->servers->isExtensionSupported(ServersExtension::floatingIps())) {
            $this->assertNotNull($pool);
            $aFloatingIps = $rs->servers->floatingIps->list();
            $this->assertTrue($aFloatingIps instanceof \ArrayIterator);
            foreach ($aFloatingIps as $v) {
                $r = $rs->servers->floatingIps->get($v->id);
                $this->assertTrue(is_object($r));
                break;
            }
            unset($aFloatingIps);

            //default pool for rackspase is 'nova'
            $fip = $rs->servers->floatingIps->create($pool);
            $this->assertTrue(is_object($fip));
            $r = $rs->servers->floatingIps->delete($fip->id);
            $this->assertTrue($r);
            try {
                //Verifies that ip has been successfully removed
                $res = $rs->servers->floatingIps->get($fip->id);
                $this->assertTrue(false, 'Exception must be thrown here');
            } catch (RestClientException $e) {
                if ($e->error->code == 404) {
                    $this->assertTrue(true);
                } else {
                    //OpenStack Grizzly fails with 500 error code.
                    //throw $e;
                }
            }
            unset($fip);
        }

        //List flavors test
        $flavorsList = $listFlavors = $rs->servers->listFlavors();
        $this->assertTrue($flavorsList instanceof \ArrayIterator);
        $flavorId = null;
        foreach ($flavorsList as $v) {
            $flavorId = $v->id;
            break;
        }
        $this->assertNotNull($flavorId);
        unset($flavorsList);

        //List servers test
        $ret = $rs->servers->list();
        if (!empty($ret) && $ret->count()) {
            foreach ($ret as $v) {
                if ($v->name == self::getTestServerName() || $v->name == self::getTestServerName('renamed')) {
                    //Removes servers
                    try {
                        $rs->servers->deleteServer($v->id);
                    } catch (RestClientException $e) {
                        echo $e->getMessage() . "\n";
                    }
                }
            }
        }

        $personality = new PersonalityList();
        $personality->append(new Personality('/etc/scalr/private.d/.user-data', base64_encode('super data')));
        $personality->append(new Personality('/etc/.scalr-user-data', base64_encode('super data')));

        $netList = null;

        //Create server test
        $srv = $rs->servers->createServer(
            self::getTestServerName(), $flavorId, $imageId, null, null, $personality, $netList
        );
        $this->assertInstanceOf('stdClass', $srv);

        $srv = $rs->servers->getServerDetails($srv->id);
        $this->assertInstanceOf('stdClass', $srv);
        $this->assertNotEmpty($srv->status);

        for ($t = time(), $s = 10; (time() - $t) < 600 && !in_array($srv->status, array('ACTIVE', 'ERROR')); $s += 1) {
            sleep($s);
            $srv = $rs->servers->getServerDetails($srv->id);
        }
        $this->assertContains($srv->status, array('ACTIVE', 'ERROR'));

        if ($rs->servers->isExtensionSupported(ServersExtension::consoleOutput())) {
            $consoleOut = $rs->servers->getConsoleOutput($srv->id, 50);
        }

        //List Addresses test
        $addresses = $rs->servers->listAddresses($srv->id);
        $this->assertTrue(is_object($addresses));

        //Get server details test
        $srvDetails = $rs->servers->getServerDetails($srv->id);
        $this->assertInstanceOf('stdClass', $srvDetails);
        unset($srvDetails);

        //Images List test
        $imagesList = $rs->servers->images->list();
        $this->assertTrue($imagesList instanceof DefaultPaginationList);
        foreach ($imagesList as $img) {
            if ($img->name == self::getTestName('image')) {
                $rs->servers->images->delete($img->id);
            }
            $imageDetails = $rs->servers->images->get($img->id);
            $this->assertTrue(is_object($imageDetails));
            unset($imageDetails);
            break;
        }
        unset($imagesList);

        //Keypairs extension test
        if ($rs->servers->isExtensionSupported(ServersExtension::keypairs())) {
            $aKeypairs = $rs->servers->keypairs->list();
            $this->assertTrue($aKeypairs instanceof \ArrayIterator);
            foreach ($aKeypairs as $v) {
                if ($v->keypair->name == self::getTestName('key')) {
                    $rs->servers->keypairs->delete($v->keypair->name);
                }
            }
            unset($aKeypairs);
            $kp = $rs->servers->keypairs->create(self::getTestName('key'));
            $this->assertNotEmpty($kp);
            $this->assertTrue(is_object($kp));

            $kptwin = $rs->servers->keypairs->get($kp->name);
            $this->assertNotEmpty($kptwin);
            $this->assertEquals($kp->public_key, $kptwin->public_key);
            unset($kptwin);

            $res = $rs->servers->keypairs->delete($kp->name);
            $this->assertTrue($res);
            unset($kp);
        }

        //Security Groups extension test
        if ($rs->servers->isExtensionSupported(ServersExtension::securityGroups())) {
            $listSecurityGroups = $rs->servers->securityGroups->list();
            $this->assertTrue($listSecurityGroups instanceof \ArrayIterator);
            foreach ($listSecurityGroups as $v) {
                if ($v->name == self::getTestName('security-group')) {
                    $rs->servers->securityGroups->delete($v->id);
                }
            }
            unset($listSecurityGroups);

            $listForSpecificServer = $rs->servers->securityGroups->list($srv->id);
            $this->assertTrue(is_array($listForSpecificServer) || $listForSpecificServer instanceof \ArrayIterator);

            unset($listForSpecificServer);

            $sg = $rs->servers->securityGroups->create(self::getTestName('security-group'), 'This is phpunit security group test.');
            $this->assertNotEmpty($sg);
            $this->assertTrue(is_object($sg));

            $sgmirror = $rs->servers->securityGroups->get($sg->id);
            $this->assertNotEmpty($sgmirror);
            $this->assertEquals($sg->id, $sgmirror->id);
            unset($sgmirror);

            $sgrule = $rs->servers->securityGroups->addRule(array(
                "ip_protocol"     => "tcp",
                "from_port"       => "80",
                "to_port"         => "8080",
                "cidr"            => "0.0.0.0/0",
                "parent_group_id" => $sg->id,
            ));
            $this->assertNotEmpty($sgrule);
            $this->assertTrue(is_object($sgrule));
            $this->assertEquals($sg->id, $sgrule->parent_group_id);

            $ret = $rs->servers->securityGroups->deleteRule($sgrule->id);
            $this->assertTrue($ret);
            unset($sgrule);

            $ret = $rs->servers->securityGroups->delete($sg->id);
            $this->assertTrue($ret);
        }

        //Create image test
        $imageId = $rs->servers->images->create($srv->id, self::getTestName('image'));
        $this->assertTrue(is_string($imageId));

        //It requires ACTIVE state of the server
//         $res = $rs->servers->resizeServer($srv->id, $srv->name, '3');
//         $this->assertTrue($res);

//         $res = $rs->servers->confirmResizedServer($srv->id);
//         $this->assertTrue($res);

        $ret = $rs->servers->images->delete($imageId);
        $this->assertTrue($ret);

        //Update server test
        $renamedDetails = $rs->servers->updateServer($srv->id, self::getTestServerName('renamed'));
        $this->assertInstanceOf('stdClass', $renamedDetails);
        $this->assertEquals(self::getTestServerName('renamed'), $renamedDetails->server->name);
        unset($renamedDetails);

        //Delete Server test
        $ret = $rs->servers->deleteServer($srv->id);
        $this->assertTrue($ret);
    }
}