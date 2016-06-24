<?php
namespace Scalr\Tests\Service\CloudStack;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\AssociateIpAddressData;
use DateTime;
use DateTimeZone;

/**
 * CloudStack TestCase
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class CloudStackTest extends CloudStackTestCase
{

    const EMPTY_CONFIG = -1;

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service\CloudStack.CloudStackTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests\Service\CloudStack.CloudStackTestCase::tearDown()
     */
    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * Gets response callback
     *
     * @param   string   $method CloudStack API method
     * @return  \Closure
     */
    public function getResponseCallback($method)
    {
        $responseMock = $this->getQueryClientResponseMock($this->getFixtureFileContent($method . '.json'), $method);
        return function() use($responseMock) {
            return $responseMock;
        };
    }

    /**
     * Provider of the instances for the functional tests
     */
    public function providerRs()
    {
        $data = \Scalr::config('scalr.phpunit.cloudstack.platforms');

        if (empty($data) || !is_array($data)) {
            return array(
                array(self::EMPTY_CONFIG, 'jp-east-t1v', '2530', '24')
            );
        }

        return $data;
    }

    /**
     * @test
     */
    public function testGetAvailableServices()
    {
        $avail = CloudStack::getAvailableServices();
        $this->assertNotEmpty($avail);
        $this->assertInternalType('array', $avail);
        $this->assertArrayHasKey('network', $avail);
        $this->assertArrayNotHasKey('abstract', $avail);
    }

    /**
     * @test
     */
    public function testListSnapshots()
    {
        $cloudstack = $this->getCloudStackMock('snapshot', $this->getResponseCallback(substr(__FUNCTION__, 4)));
        $this->assertInstanceOf('Scalr\Service\CloudStack\CloudStack', $cloudstack);
        $snapshots = $cloudstack->snapshot->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotResponseList', $snapshots);
        $this->assertEquals(7, count($snapshots));

        foreach ($snapshots as $snapshot) {
            $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotResponseData', $snapshot);
            $this->assertEquals(137516, $snapshot->id);
            $this->assertEquals("Scalr-User1", $snapshot->account);
            $this->assertEquals(1105, $snapshot->domainid);
            $this->assertEquals("70000001100", $snapshot->domain);
            $this->assertEquals("MANUAL", $snapshot->snapshottype);
            $this->assertEquals(75681, $snapshot->volumeid);
            $this->assertEquals("ROOT-60414", $snapshot->volumename);
            $this->assertEquals("ROOT", $snapshot->volumetype);
            $this->assertEquals(new DateTime("2014-05-06T20:10:05+0900", new DateTimeZone('UTC')), $snapshot->created);
            $this->assertEquals("MANUAL", $snapshot->intervaltype);
            $this->assertEquals("BackedUp", $snapshot->state);
            $this->assertEquals("Project Test", $snapshot->project);
            $this->assertEquals(666, $snapshot->projectid);
            $this->assertEquals(true, $snapshot->revertable);
            $this->assertEquals(23, $snapshot->zoneid);
            $this->assertEquals(42, $snapshot->jobid);
            $this->assertEquals("status", $snapshot->jobstatus);
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsList', $snapshot->tags);
            foreach ($snapshot->tags as $tag) {
                $this->assertEquals("testio", $tag->account);
                $this->assertEquals("testio", $tag->customer);
                $this->assertEquals("test.com", $tag->domain);
                $this->assertEquals(42, $tag->domainid);
                $this->assertEquals("key test", $tag->key);
                $this->assertEquals("Project Test", $tag->project);
                $this->assertEquals(666, $tag->projectid);
                $this->assertEquals(11, $tag->resourceid);
                $this->assertEquals("test", $tag->resourcetype);
                $this->assertEquals("testvalue", $tag->value);
                break;
            }
            break;
        }
    }

    /**
     * @test
     */
    public function testListVolumes()
    {
        $cloudstack = $this->getCloudStackMock('volume', $this->getResponseCallback(substr(__FUNCTION__, 4)));
        $this->assertInstanceOf('Scalr\Service\CloudStack\CloudStack', $cloudstack);
        $volumes = $cloudstack->volume->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Volume\DataType\VolumeResponseList', $volumes);

        foreach ($volumes as $volume) {
            $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Volume\DataType\VolumeResponseData', $volume);
            $this->assertEquals(75678, $volume->id);
            $this->assertEquals("Scalr-User1", $volume->account);
            $this->assertEquals(new DateTime("2014-05-06T20:10:05+0900", new DateTimeZone('UTC')), $volume->attached);
            $this->assertEquals(new DateTime("2014-05-06T18:02:05+0900", new DateTimeZone('UTC')), $volume->created);
            $this->assertEquals(false, $volume->destroyed);
            $this->assertEquals(0, $volume->deviceid);
            $this->assertEquals(65, $volume->diskBytesReadRate);
            $this->assertEquals(66, $volume->diskBytesWriteRate);
            $this->assertEquals(67, $volume->diskIopsReadRate);
            $this->assertEquals(68, $volume->diskIopsWriteRate);
            $this->assertEquals("testio", $volume->diskofferingdisplaytext);
            $this->assertEquals(69, $volume->diskofferingid);
            $this->assertEquals("testio", $volume->diskofferingname);
            $this->assertEquals(true, $volume->displayvolume);
            $this->assertEquals("70000001100", $volume->domain);
            $this->assertEquals(1105, $volume->domainid);
            $this->assertEquals("VMware", $volume->hypervisor);
            $this->assertEquals(true, $volume->isextractable);
            $this->assertEquals(100, $volume->maxiops);
            $this->assertEquals(10, $volume->miniops);
            $this->assertEquals("ROOT-60413", $volume->name);
            $this->assertEquals("path", $volume->path);
            $this->assertEquals("Project Test", $volume->project);
            $this->assertEquals(42, $volume->projectid);
            $this->assertEquals(true, $volume->quiescevm);
            $this->assertEquals("S2 ( 1CPU / 2GB )", $volume->serviceofferingdisplaytext);
            $this->assertEquals(30, $volume->serviceofferingid);
            $this->assertEquals("S2", $volume->serviceofferingname);
            $this->assertEquals(16106127360, $volume->size);
            $this->assertEquals(666, $volume->snapshotid);
            $this->assertEquals("Ready", $volume->state);
            $this->assertEquals("Test", $volume->status);
            $this->assertEquals("jef2v-p02c-DS47", $volume->storage);
            $this->assertEquals(11, $volume->storageid);
            $this->assertEquals("shared", $volume->storagetype);
            $this->assertEquals("ROOT", $volume->type);
            $this->assertEquals(60413, $volume->virtualmachineid);
            $this->assertEquals("a43d0722-da3d-4e26-a1ff-f99981181a60", $volume->vmdisplayname);
            $this->assertEquals("i-882-60413-VM", $volume->vmname);
            $this->assertEquals("Destroyed", $volume->vmstate);
            $this->assertEquals(2, $volume->zoneid);
            $this->assertEquals("jp-east-f2v", $volume->zonename);
            $this->assertEquals(1, $volume->jobid);
            $this->assertEquals("Done", $volume->jobstatus);
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsList', $volume->tags);
            foreach ($volume->tags as $tag) {
                $this->assertEquals("testio", $tag->account);
                $this->assertEquals("testio", $tag->customer);
                $this->assertEquals("test.com", $tag->domain);
                $this->assertEquals(42, $tag->domainid);
                $this->assertEquals("key test", $tag->key);
                $this->assertEquals("Project Test", $tag->project);
                $this->assertEquals(666, $tag->projectid);
                $this->assertEquals(11, $tag->resourceid);
                $this->assertEquals("test", $tag->resourcetype);
                $this->assertEquals("testvalue", $tag->value);
                break;
            }
            break;
        }
    }

    /**
     * @test
     */
    public function testListPublicIpAddresses()
    {
        $cloudstack = $this->getCloudStackMock(null, $this->getResponseCallback(substr(__FUNCTION__, 4)));
        $this->assertInstanceOf('Scalr\Service\CloudStack\CloudStack', $cloudstack);
        $ips = $cloudstack->listPublicIpAddresses();
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\IpAddressResponseList', $ips);

        foreach ($ips as $ip) {
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\IpAddressResponseData', $ip);
            $this->assertEquals(13199, $ip->id);
            $this->assertEquals("Scalr-User1", $ip->account);
            $this->assertEquals(new DateTime("2014-02-19T00:04:23+0900", new DateTimeZone('UTC')), $ip->allocated);
            $this->assertEquals(1527, $ip->associatednetworkid);
            $this->assertEquals("Test Name", $ip->associatednetworkname);
            $this->assertEquals("70000001100", $ip->domain);
            $this->assertEquals(1105, $ip->domainid);
            $this->assertEquals(true, $ip->forvirtualnetwork);
            $this->assertEquals("210.140.144.19", $ip->ipaddress);
            $this->assertEquals(false, $ip->isportable);
            $this->assertEquals(true, $ip->issourcenat);
            $this->assertEquals(false, $ip->isstaticnat);
            $this->assertEquals(true, $ip->issystem);
            $this->assertEquals(1476, $ip->networkid);
            $this->assertEquals("testio", $ip->physicalnetworkid);
            $this->assertEquals("Project Test", $ip->project);
            $this->assertEquals(666, $ip->projectid);
            $this->assertEquals("testio", $ip->purpose);
            $this->assertEquals("Allocated", $ip->state);
            $this->assertEquals("test name", $ip->virtualmachinedisplayname);
            $this->assertEquals(42, $ip->virtualmachineid);
            $this->assertEquals("test name", $ip->virtualmachinename);
            $this->assertEquals(1, $ip->vlanid);
            $this->assertEquals("test", $ip->vlanname);
            $this->assertEquals("210.140.144.19", $ip->vmipaddress);
            $this->assertEquals(50, $ip->vpcid);
            $this->assertEquals(2, $ip->zoneid);
            $this->assertEquals("jp-east-f2v", $ip->zonename);
            $this->assertEquals(42, $ip->jobid);
            $this->assertEquals("Done", $ip->jobstatus);
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsList', $ip->tags);
            foreach ($ip->tags as $tag) {
                $this->assertEquals("testio", $tag->account);
                $this->assertEquals("testio", $tag->customer);
                $this->assertEquals("test.com", $tag->domain);
                $this->assertEquals(42, $tag->domainid);
                $this->assertEquals("key test", $tag->key);
                $this->assertEquals("Project Test", $tag->project);
                $this->assertEquals(666, $tag->projectid);
                $this->assertEquals(11, $tag->resourceid);
                $this->assertEquals("test", $tag->resourcetype);
                $this->assertEquals("testvalue", $tag->value);
                break;
            }
            break;
        }
    }

    /**
     * @test
     */
    public function testListSnapshotPolicies()
    {
        $cloudstack = $this->getCloudStackMock('snapshot', $this->getResponseCallback(substr(__FUNCTION__, 4)));
        $this->assertInstanceOf('Scalr\Service\CloudStack\CloudStack', $cloudstack);
        $volumeId = 75681;
        $snapshots = $cloudstack->snapshot->listPolicies($volumeId);
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotPolicyResponseList', $snapshots);

        foreach ($snapshots as $snapshot) {
            $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotPolicyResponseData', $snapshot);
            $this->assertEquals(137516, $snapshot->id);
            $this->assertEquals(75681, $snapshot->volumeid);
            $this->assertEquals(new DateTime("2014-02-19T00:04:23+0900", new DateTimeZone('UTC')), $snapshot->schedule);
            $this->assertEquals("MANUAL", $snapshot->intervaltype);
            $this->assertEquals(20, $snapshot->maxsnaps);
            $this->assertEquals("some zone", $snapshot->timezone);
            break;
        }
    }

    /**
     * @test
     */
    public function testListZones()
    {
        $cloudstack = $this->getCloudStackMock('zone', $this->getResponseCallback(substr(__FUNCTION__, 4)));
        $this->assertInstanceOf('Scalr\Service\CloudStack\CloudStack', $cloudstack);
        $zones = $cloudstack->zone->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Zone\DataType\ZoneList', $zones);

        foreach ($zones as $zone) {
            $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Zone\DataType\ZoneData', $zone);
            $this->assertEquals(1, $zone->id);
            $this->assertEquals("test", $zone->description);
            $this->assertEquals("some text", $zone->displaytext);
            $this->assertEquals("dns1", $zone->dns1);
            $this->assertEquals("dns2", $zone->dns2);
            $this->assertEquals("test.com", $zone->domain);
            $this->assertEquals(1, $zone->domainid);
            $this->assertEquals("testio", $zone->domainname);
            $this->assertEquals("guest", $zone->guestcidraddress);
            $this->assertEquals("test1", $zone->internaldns1);
            $this->assertEquals("test2", $zone->internaldns2);
            $this->assertEquals("120.0.0.1", $zone->ip6dns1);
            $this->assertEquals("120.0.0.1", $zone->ip6dns2);
            $this->assertEquals(true, $zone->localstorageenabled);
            $this->assertEquals("jp-east-t1v", $zone->name);
            $this->assertEquals("Advanced", $zone->networktype);
            $this->assertEquals("0.9", $zone->resourcedetails->{"pool.storage.capacity.disablethreshold"});
            $this->assertEquals("1.0", $zone->resourcedetails->{"storage.overprovisioning.factor"});
            $this->assertEquals(false, $zone->securitygroupsenabled);
            $this->assertEquals(42, $zone->vlan);
            $this->assertEquals("Enabled", $zone->allocationstate);
            $this->assertEquals("6a3bfa26-67cd-3ff2-867e-20e86b211bb1", $zone->zonetoken);
            $this->assertEquals("VirtualRouter", $zone->dhcpprovider);
            $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Zone\DataType\CapacityList', $zone->capacity);
            foreach ($zone->capacity as $capacity) {
                $this->assertEquals(42, $capacity->capacitytotal);
                $this->assertEquals(10, $capacity->capacityused);
                $this->assertEquals(10, $capacity->clusterid);
                $this->assertEquals("testio", $capacity->clustername);
                $this->assertEquals(100, $capacity->percentused);
                $this->assertEquals(1, $capacity->podid);
                $this->assertEquals("testio", $capacity->podname);
                $this->assertEquals("Type", $capacity->type);
                $this->assertEquals(2, $capacity->zoneid);
                $this->assertEquals("TestZone", $capacity->zonename);
                break;
            }
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsList', $zone->tags);
            foreach ($zone->tags as $tag) {
                $this->assertEquals("testio", $tag->account);
                $this->assertEquals("testio", $tag->customer);
                $this->assertEquals("test.com", $tag->domain);
                $this->assertEquals(42, $tag->domainid);
                $this->assertEquals("key test", $tag->key);
                $this->assertEquals("Project Test", $tag->project);
                $this->assertEquals(666, $tag->projectid);
                $this->assertEquals(11, $tag->resourceid);
                $this->assertEquals("test", $tag->resourcetype);
                $this->assertEquals("testvalue", $tag->value);
                break;
            }
            break;
        }
    }

    /**
     * @test
     */
    public function testListLoadBalancerRules()
    {
        $cloudstack = $this->getCloudStackMock('balancer', $this->getResponseCallback(substr(__FUNCTION__, 4)));
        $this->assertInstanceOf('Scalr\Service\CloudStack\CloudStack', $cloudstack);
        $balancers = $cloudstack->balancer->listRules();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Balancer\DataType\BalancerResponseList', $balancers);

        foreach ($balancers as $balancer) {
            $this->assertEquals(1, $balancer->id);
            $this->assertEquals("test", $balancer->description);
            $this->assertEquals("test.com", $balancer->domain);
            $this->assertEquals(1, $balancer->domainid);
            $this->assertEquals("jp-east-t1v", $balancer->name);
            $this->assertEquals("testio", $balancer->account);
            $this->assertEquals("roundrobin", $balancer->algorithm);
            $this->assertEquals("test", $balancer->cidrlist);
            $this->assertEquals(13, $balancer->networkid);
            $this->assertEquals(42, $balancer->privateport);
            $this->assertEquals("test project", $balancer->project);
            $this->assertEquals(7, $balancer->projectid);
            $this->assertEquals("http", $balancer->protocol);
            $this->assertEquals("192.168.0.1", $balancer->publicip);
            $this->assertEquals(12, $balancer->publicipid);
            $this->assertEquals(80, $balancer->publicport);
            $this->assertEquals("burning", $balancer->state);
            $this->assertEquals(2, $balancer->zoneid);
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsList', $balancer->tags);
            foreach ($balancer->tags as $tag) {
                $this->assertEquals("testio", $tag->account);
                $this->assertEquals("testio", $tag->customer);
                $this->assertEquals("test.com", $tag->domain);
                $this->assertEquals(42, $tag->domainid);
                $this->assertEquals("key test", $tag->key);
                $this->assertEquals("Project Test", $tag->project);
                $this->assertEquals(666, $tag->projectid);
                $this->assertEquals(11, $tag->resourceid);
                $this->assertEquals("test", $tag->resourcetype);
                $this->assertEquals("testvalue", $tag->value);
                break;
            }
            break;
        }
    }

    /**
     * @test
     */
    public function testListVirtualMachines()
    {
        $cloudstack = $this->getCloudStackMock('instance', $this->getResponseCallback(substr(__FUNCTION__, 4)));
        $this->assertInstanceOf('Scalr\Service\CloudStack\CloudStack', $cloudstack);
        $vms = $cloudstack->instance->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\VirtualMachineInstancesList', $vms);

        foreach ($vms as $vm) {
            $this->assertEquals(60975, $vm->id);
            $this->assertEquals("i-882-60975-VM", $vm->name);
            $this->assertEquals("c5911bcc-c0fa-4a4e-8ee6-1a5f7811e077", $vm->displayname);
            $this->assertEquals("Scalr-User1", $vm->account);
            $this->assertEquals(1105, $vm->domainid);
            $this->assertEquals("70000001100", $vm->domain);
            $this->assertEquals(new DateTime("2014-05-13T22:14:14+0900", new DateTimeZone('UTC')), $vm->created);
            $this->assertEquals("Stopped", $vm->state);
            $this->assertEquals(false, $vm->haenable);
            $this->assertEquals(5949, $vm->groupid);
            $this->assertEquals("rabbitmq-ubuntu1204-devel", $vm->group);
            $this->assertEquals(2, $vm->zoneid);
            $this->assertEquals("jp-east-f2v", $vm->zonename);
            $this->assertEquals(4668, $vm->templateid);
            $this->assertEquals("mbeh1-ubuntu1204-devel-09102013", $vm->templatename);
            $this->assertEquals("mbeh1-ubuntu1204-devel", $vm->templatedisplaytext);
            $this->assertEquals(true, $vm->passwordenabled);
            $this->assertEquals(30, $vm->serviceofferingid);
            $this->assertEquals("S2", $vm->serviceofferingname);
            $this->assertEquals(1, $vm->cpunumber);
            $this->assertEquals(1600, $vm->cpuspeed);
            $this->assertEquals(2048, $vm->memory);
            $this->assertEquals(100, $vm->guestosid);
            $this->assertEquals(0, $vm->rootdeviceid);
            $this->assertEquals("Not created", $vm->rootdevicetype);
            $this->assertEquals("VMware", $vm->hypervisor);
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\AffinityGroupList', $vm->affinitygroup);
            foreach ($vm->affinitygroup as $affinitygroup) {
                $this->assertEquals(1, $affinitygroup->id);
                $this->assertEquals("Scalr", $affinitygroup->account);
                $this->assertEquals("test", $affinitygroup->description);
                $this->assertEquals("test.com", $affinitygroup->domain);
                $this->assertEquals(42, $affinitygroup->domainid);
                $this->assertEquals("testio", $affinitygroup->name);
                $this->assertEquals("test", $affinitygroup->type);
                $this->assertEquals("32", $affinitygroup->virtualmachineIds);
                break;
            }
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\SecurityGroupList', $vm->securitygroup);
            foreach ($vm->securitygroup as $securitygroup) {
                $this->assertEquals(1, $securitygroup->id);
                $this->assertEquals("Scalr", $securitygroup->account);
                $this->assertEquals("test", $securitygroup->description);
                $this->assertEquals("test.com", $securitygroup->domain);
                $this->assertEquals(42, $securitygroup->domainid);
                $this->assertEquals("testio", $securitygroup->name);
                $this->assertEquals("test", $securitygroup->project);
                $this->assertEquals(32, $securitygroup->projectid);
                $this->assertEquals(666, $securitygroup->jobid);
                $this->assertEquals("pending", $securitygroup->jobstatus);
                $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\EgressruleList', $securitygroup->egressrule);
                foreach ($securitygroup->egressrule as $egressrule) {
                    $this->assertEquals("testio", $egressrule->account);
                    $this->assertEquals("testio", $egressrule->cidr);
                    $this->assertEquals(80, $egressrule->endport);
                    $this->assertEquals(42, $egressrule->icmpcode);
                    $this->assertEquals("testing", $egressrule->icmptype);
                    $this->assertEquals("http", $egressrule->protocol);
                    $this->assertEquals(666, $egressrule->ruleid);
                    $this->assertEquals("testio", $egressrule->securitygroupname);
                    $this->assertEquals(81, $egressrule->startport);
                    break;
                }
                $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\IngressruleList', $securitygroup->ingressrule);
                foreach ($securitygroup->ingressrule as $ingressrule) {
                    $this->assertEquals("testio", $ingressrule->account);
                    $this->assertEquals("testio", $ingressrule->cidr);
                    $this->assertEquals(80, $ingressrule->endport);
                    $this->assertEquals(42, $ingressrule->icmpcode);
                    $this->assertEquals("testing", $ingressrule->icmptype);
                    $this->assertEquals("http", $ingressrule->protocol);
                    $this->assertEquals(666, $ingressrule->ruleid);
                    $this->assertEquals("testio", $ingressrule->securitygroupname);
                    $this->assertEquals(81, $ingressrule->startport);
                    break;
                }
                $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsList', $securitygroup->tags);
                foreach ($securitygroup->tags as $tag) {
                    $this->assertEquals("testio", $tag->account);
                    $this->assertEquals("testio", $tag->customer);
                    $this->assertEquals("test.com", $tag->domain);
                    $this->assertEquals(42, $tag->domainid);
                    $this->assertEquals("key test", $tag->key);
                    $this->assertEquals("Project Test", $tag->project);
                    $this->assertEquals(666, $tag->projectid);
                    $this->assertEquals(11, $tag->resourceid);
                    $this->assertEquals("test", $tag->resourcetype);
                    $this->assertEquals("testvalue", $tag->value);
                    break;
                }
                unset($tag);
            }
            break;
        }

        $vmArray = $vms->toArray();
        $this->assertArrayHasKey('nic', $vmArray[0]);
        $this->assertArrayHasKey('securitygroup', $vmArray[0]);
        $this->assertArrayHasKey('tags', $vmArray[0]);
        $this->assertEmpty($vmArray[0]['tags']);
        $this->assertArrayHasKey('affinitygroup', $vmArray[0]);
        $fixtureArray = include($this->getFixtureFilePath(substr(__FUNCTION__, 4) . '.php'));
        $this->assertEquals($fixtureArray, $vmArray);
    }

    /**
     * @test
     */
    public function testListNetworks()
    {
        $cloudstack = $this->getCloudStackMock('network', $this->getResponseCallback(substr(__FUNCTION__, 4)));
        $this->assertInstanceOf('Scalr\Service\CloudStack\CloudStack', $cloudstack);
        $networks = $cloudstack->network->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseList', $networks);

        foreach ($networks as $network) {
            $this->assertEquals(1, $network->id);
            $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseServiceList', $network->service);
            foreach ($network->service as $service) {
                $this->assertEquals("testio", $service->name);
                $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseCapabilityList', $service->capability);
                foreach ($service->capability as $capability) {
                    $this->assertEquals(true, $capability->canchooseservicecapability);
                    $this->assertEquals("testio", $capability->name);
                    $this->assertEquals("Scalr", $capability->value);
                    break;
                }
                $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseProviderList', $service->provider);
                foreach ($service->provider as $provider) {
                    $this->assertEquals(7, $provider->id);
                    $this->assertEquals(true, $provider->canenableindividualservice);
                    $this->assertEquals(42, $provider->destinationphysicalnetworkid);
                    $this->assertEquals("testio", $provider->name);
                    $this->assertEquals(666, $provider->physicalnetworkid);
                    $this->assertEquals("services", $provider->servicelist);
                    $this->assertEquals("OK", $provider->state);
                    break;
                }
            }
            $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsList', $network->tags);
            foreach ($network->tags as $tag) {
                $this->assertEquals("testio", $tag->account);
                $this->assertEquals("testio", $tag->customer);
                $this->assertEquals("test.com", $tag->domain);
                $this->assertEquals(42, $tag->domainid);
                $this->assertEquals("key test", $tag->key);
                $this->assertEquals("Project Test", $tag->project);
                $this->assertEquals(666, $tag->projectid);
                $this->assertEquals(11, $tag->resourceid);
                $this->assertEquals("test", $tag->resourcetype);
                $this->assertEquals("testvalue", $tag->value);
                break;
            }
            break;
        }
    }

    /**
     * @test
     * @dataProvider providerRs
     * @functional
     */
    public function testFunctionalCloudStack($platform, $cloudLocation, $templateId, $serviceId)
    {
        if ($platform === self::EMPTY_CONFIG) {
            $this->markTestSkipped();
        }

        /* @var $cs CloudStack */
        if ($this->getContainer()->environment->isPlatformEnabled($platform)) {
            $cs = $this->getContainer()->cloudstack($platform);
//             $rs->setDebug();
            $this->assertInstanceOf($this->getCloudStackClassName('CloudStack'), $cs);
        } else {
            //Environment has not been activated yet.
            $this->markTestSkipped(sprintf('Environment for the "%s" platform has not been activated.', $platform));
        }

        $networks = $cs->network->describe();
        $this->assertNotEmpty($networks);
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseList', $networks);
        foreach ($networks as $network) {
            $this->assertNotNull($network->id);
            $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseData', $network);
            if (!empty($network->service)) {
                $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseServiceList', $network->service);
                foreach ($network->service as $service) {
                    $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseServiceData', $service);
                    $this->assertNotEmpty($service->name);
                }
            }
            if (!empty($network->tags)) {
                $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsList', $network->tags);
                foreach ($network->tags as $tag) {
                    $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseTagsData', $tag);
                }
            }
        }

        $snapshots = $cs->snapshot->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Snapshot\DataType\SnapshotResponseList', $snapshots);

        $volumes = $cs->volume->describe(array('listall' => true));

        $ips = $cs->listPublicIpAddresses();
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\IpAddressResponseList', $ips);

        $balancers = $cs->balancer->listRules();
        $ipRules = $cs->firewall->listIpForwardingRules();
        $portRules = $cs->firewall->listPortForwardingRules();

        $isos = $cs->iso->describe();

        $securityGroups = $cs->securityGroup->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\SecurityGroupList', $securityGroups);

        $sshKeyPairs = $cs->sshKeyPair->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshKeyResponseList', $sshKeyPairs);

        $vmGroups = $cs->vmGroup->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\VmGroup\DataType\InstanceGroupList', $vmGroups);

        $accounts = $cs->listAccounts(array('listall' => true));
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\AccountList', $accounts);

        $jobs = $cs->listAsyncJobs(array('listall' => true));

        $capabilities = $cs->listCapabilities();

        $events = $cs->listEvents(array('listall' => true));

        $osTypes = $cs->listOsTypes();
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\OsTypeList', $osTypes);

        $hypervisors = $cs->listHypervisors();
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\HypervisorsList', $hypervisors);

        $limits = $cs->listResourceLimits(array('listall' => true));
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResourceLimitList', $limits);

        $serviceOfferings = $cs->listServiceOfferings();
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ServiceOfferingList', $serviceOfferings);

        $templates = $cs->template->describe(array('templatefilter' => "featured", "listall" => true));
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\TemplateResponseList', $templates);

        $zones = $cs->zone->describe();
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Zone\DataType\ZoneList', $zones);
        $zoneId = $cloudLocation;

        $diskOfferings = $cs->listDiskOfferings();
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\DiskOfferingList', $diskOfferings);

        $testName = self::getTestName('test_vm');
        $virtuals = $cs->instance->describe();

        if (count($virtuals) > 0) {
            foreach ($virtuals as $virtual) {
                if ($virtual->jobstatus !== 0 && $testName == $virtual->displayname && 'Destroyed' != $virtual->state) {
                    $responseData = $cs->instance->destroy($virtual->id);
                    $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData', $responseData);
                    $sleep = 0;
                    do {
                        sleep(10);
                        $sleep += 10;
                        $jobstatus = $cs->queryAsyncJobResult($responseData->jobid);
                    } while ($jobstatus->jobstatus != 1 && $sleep <= 300);
                }
            }
        }

        $vResult = $cs->instance->deploy(
            array(
                'serviceofferingid' => $serviceId,
                'templateid'        => $templateId,
                'zoneid'            => $zoneId,
                'displayname'       => $testName,
            )
        );
        $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData', $vResult);
        $sleep = 0;
        do {
            sleep(10);
            $sleep += 10;
            $jobstatus = $cs->queryAsyncJobResult($vResult->jobid);
        } while ($jobstatus->jobstatus != 1 && $sleep <= 600);
        $this->assertEquals(1, $jobstatus->jobstatus);
        $listKeys = $cs->sshKeyPair->describe(array('listall' => true));
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshKeyResponseList', $listKeys);
        if (count($listKeys) > 0) {
            foreach ($listKeys as $key) {
                if ($key->name == "SCALR-ROLESBUILDER-".SCALR_ID) {
                    $cs->sshKeyPair->delete(array('name' => $key->name));
                }
            }
        }
        $keyPair = $cs->sshKeyPair->create(array('name' => "SCALR-ROLESBUILDER-".SCALR_ID));
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshPrivateKeyResponseData', $keyPair);
        $this->assertNotEmpty($keyPair->privatekey);
        $listKeys = $cs->sshKeyPair->describe(array('listall' => true));
        $this->assertInstanceOf('Scalr\Service\CloudStack\Services\SshKeyPair\DataType\SshKeyResponseList', $listKeys);
        if (count($listKeys) > 0) {
            foreach ($listKeys as $key) {
                if ($key->name == "SCALR-ROLESBUILDER-".SCALR_ID) {
                    $cs->sshKeyPair->delete(array('name' => $key->name));
                }
            }
        }

        $requestObject = new AssociateIpAddressData();
        $requestObject->zoneid = $zoneId;
        $ipResult = $cs->associateIpAddress($requestObject);
        $this->assertNotEmpty($ipResult->id);
        $sleep = 0;
        do {
            $sleep += 20;
            $job = $cs->queryAsyncJobResult($ipResult->jobid);
            sleep(20);
        } while ($job->jobstatus != 1 && $sleep <= 400);
        $this->assertEquals(1, $job->jobstatus);

        $ipInfo = $cs->listPublicIpAddresses(array('id' => $ipResult->id));
        $this->assertNotNull($ipInfo);
        $this->assertEquals($ipResult->id, $ipInfo[0]->id);

        $virtuals = $cs->instance->describe();

        if (count($virtuals) > 0) {
            foreach ($virtuals as $virtual) {
                if ($virtual->jobstatus !== 0 && $testName == $virtual->displayname && 'Destroyed' != $virtual->state) {
                    $resultRule = $cs->firewall->createPortForwardingRule(array(
                        'ipaddressid'      => $ipResult->id,
                        'privateport'      => 8014,
                        'protocol'         => "udp",
                        'publicport'       => 30002,
                        'virtualmachineid' => $virtual->id
                    ));
                    $this->assertInstanceOf('Scalr\Service\CloudStack\Services\Firewall\DataType\ForwardingRuleResponseData', $resultRule);
                    $cs->firewall->deletePortForwardingRule($resultRule->id);

                    $result = $cs->disassociateIpAddress($ipResult->id);
                    $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\ResponseDeleteData', $result);
                    $responseData = $cs->instance->destroy($virtual->id);
                    $this->assertInstanceOf('Scalr\Service\CloudStack\DataType\VirtualMachineInstancesData', $responseData);
                    $sleep = 0;
                    do {
                        $sleep += 10;
                        $jobstatus = $cs->queryAsyncJobResult($responseData->jobid);
                        sleep(10);
                    } while ($jobstatus->jobstatus != 1 && $sleep <= 300);
                }
            }
        }
    }
}