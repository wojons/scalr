<?php
return array (
  0 =>
  array (
    'id' => '60975',
    'account' => 'Scalr-User1',
    'cpunumber' => '1',
    'cpuspeed' => '1600',
    'cpuused' => NULL,
    'created' =>
    DateTime::__set_state(array(
       'date' => '2014-05-13 22:14:14',
       'timezone_type' => 1,
       'timezone' => '+09:00',
    )),
    'diskioread' => NULL,
    'diskiowrite' => NULL,
    'diskkbsread' => NULL,
    'diskkbswrite' => NULL,
    'displayname' => 'c5911bcc-c0fa-4a4e-8ee6-1a5f7811e077',
    'displayvm' => NULL,
    'domain' => '70000001100',
    'domainid' => '1105',
    'forvirtualnetwork' => NULL,
    'group' => 'rabbitmq-ubuntu1204-devel',
    'groupid' => '5949',
    'guestosid' => '100',
    'haenable' => '',
    'hostid' => NULL,
    'hostname' => NULL,
    'hypervisor' => 'VMware',
    'instancename' => NULL,
    'isdynamicallyscalable' => NULL,
    'isodisplaytext' => NULL,
    'isoid' => NULL,
    'isoname' => NULL,
    'keypair' => NULL,
    'memory' => '2048',
    'name' => 'i-882-60975-VM',
    'networkkbsread' => NULL,
    'networkkbswrite' => NULL,
    'password' => NULL,
    'passwordenabled' => '1',
    'project' => NULL,
    'projectid' => NULL,
    'publicip' => NULL,
    'publicipid' => NULL,
    'rootdeviceid' => '0',
    'rootdevicetype' => 'Not created',
    'serviceofferingid' => '30',
    'serviceofferingname' => 'S2',
    'servicestate' => NULL,
    'state' => 'Stopped',
    'templatedisplaytext' => 'mbeh1-ubuntu1204-devel',
    'templateid' => '4668',
    'templatename' => 'mbeh1-ubuntu1204-devel-09102013',
    'zoneid' => '2',
    'zonename' => 'jp-east-f2v',
    'jobid' => NULL,
    'jobstatus' => NULL,
    'tags' =>
    array (
    ),
    'affinitygroup' =>
    array (
      0 =>
      array (
        'id' => '1',
        'account' => 'Scalr',
        'description' => 'test',
        'domain' => 'test.com',
        'domainid' => '42',
        'name' => 'testio',
        'type' => 'test',
        'virtualmachineIds' => '32',
      ),
    ),
    'nic' =>
    array (
      0 =>
      array (
        'id' => '80950',
        'broadcasturi' => NULL,
        'gateway' => '10.2.0.1',
        'ip6address' => NULL,
        'ip6cidr' => NULL,
        'ip6gateway' => NULL,
        'ipaddress' => '10.2.1.196',
        'isdefault' => '1',
        'isolationuri' => NULL,
        'macaddress' => '02:00:54:d1:03:0e',
        'netmask' => '255.255.252.0',
        'networkid' => '1527',
        'networkname' => NULL,
        'secondaryip' => NULL,
        'traffictype' => 'Guest',
        'type' => 'Virtual',
      ),
    ),
    'securitygroup' =>
    array (
      0 =>
      array (
        'id' => '1',
        'account' => 'Scalr',
        'description' => 'test',
        'domain' => 'test.com',
        'domainid' => '42',
        'name' => 'testio',
        'project' => 'test',
        'projectid' => '32',
        'jobid' => '666',
        'jobstatus' => 'pending',
        'egressrule' =>
        array (
          0 =>
          array (
            'account' => 'testio',
            'cidr' => 'testio',
            'endport' => '80',
            'icmpcode' => '42',
            'icmptype' => 'testing',
            'protocol' => 'http',
            'ruleid' => '666',
            'securitygroupname' => 'testio',
            'startport' => '81',
          ),
        ),
        'ingressrule' =>
        array (
          0 =>
          array (
            'account' => 'testio',
            'cidr' => 'testio',
            'endport' => '80',
            'icmpcode' => '42',
            'icmptype' => 'testing',
            'protocol' => 'http',
            'ruleid' => '666',
            'securitygroupname' => 'testio',
            'startport' => '81',
          ),
        ),
        'tags' =>
        array (
          0 =>
          array (
            'account' => 'testio',
            'customer' => 'testio',
            'domain' => 'test.com',
            'domainid' => '42',
            'key' => 'key test',
            'project' => 'Project Test',
            'projectid' => '666',
            'resourceid' => '11',
            'resourcetype' => 'test',
            'value' => 'testvalue',
          ),
        ),
      ),
    ),
  ),
);