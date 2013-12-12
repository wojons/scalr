<?php
require_once(dirname(__FILE__) . "/../src/prepend.inc.php");

/*
0ce1388d-9bbc-45b1-b3f9-150414d09b29 - memcached-centos6-shared-11102013
5fb15d6e-333e-4367-93bb-0cd27e00a0dd - tomcat-centos6-shared-11102013
e4ac6980-4eab-43f0-a298-b6a1e462e12d - haproxy-centos6-shared-11102013
449780ec-68d9-498f-b6aa-74e54aa376b7 - rabbitmq-centos6-shared-11102013
1ed9ea0a-0363-4714-af1b-0a0ddde9451d - mongodb-centos6-shared-11102013
2d40a20c-e749-408c-b936-feb6ac7fdb50 - nginx-centos6-shared-11102013
e5b5ddc1-baae-4fc1-b0b2-edfa248ce291 - percona-centos6-shared-11102013
9144dff0-5440-4029-8ca1-86192cdc0be1 - postgresql-centos6-shared-11102013
2892d4f4-4cc4-4468-94ac-fb8c5651fe8c - redis-centos6-shared-11102013
085c37a7-8355-41a8-b6c2-52cb3345be10 - mysql-centos6-shared-11102013
13d16a4a-3972-40a6-a058-2d6176a70cae - apache-centos6-shared-11102013
9b723be4-c34d-4e00-9c89-aea36f4ba9f5 - base-centos6-shared-11102013

366554c9-6961-4238-b7f5-b012909e55a8 - tomcat-ubuntu1204-shared-10102013
95c27eb7-4973-4b14-a9b4-3508b02b3392 - memcached-ubuntu1204-shared-10102013
b10299b5-464a-4871-b354-6c5d12e00ce1 - haproxy-ubuntu1204-shared-10102013
933cc1b0-9130-4979-a873-1c4cc6adc4eb - mongodb-ubuntu1204-shared-10102013
128fd870-c897-455f-88a6-77f0c4ee538f - rabbitmq-ubuntu1204-shared-10102013
e35edb3b-f995-4915-bd37-2008e29c1db7 - nginx-ubuntu1204-shared-10102013
68ad6daa-05c1-4286-b2dd-5deca7c1a6c8 - percona-ubuntu1204-shared-10102013
734fa0c7-cbec-4ebc-a8b1-ffed833be097 - psql-ubuntu1204-shared-10102013
156f8821-ea33-4d59-b36e-2dbae17eed62 - redis-ubuntu1204-shared-10102013
4ce9d4ce-1798-462e-8ee9-22dc21c19375 - mysql-ubuntu1204-shared-10102013
37751ad0-8507-4f58-966a-328ef0a7c7f0 - apache-ubuntu1204-shared-10102013
b54b4117-fd27-4576-9947-a1b03b1209ce - base-ubuntu1204-shared-10102013
 */

$roles = array(
    array(
        'name' => 'base-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'osFamily' => 'ubuntu',
        'catId' => 1,
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('base','chef'),
        'description' => 'Base role',
        'images' => array(
            array(
                'image_id' => 'b54b4117-fd27-4576-9947-a1b03b1209ce',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'mysql5-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'catId' => 2,
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('mysql2','chef'),
        'description' => 'MySQL 5',
        'images' => array(
            array(
                'image_id' => '4ce9d4ce-1798-462e-8ee9-22dc21c19375',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'percona5-ubuntu1204',
        'catId' => 2,
        'os' => 'Ubuntu 12.04',
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('percona','chef'),
        'description' => 'Percona 5',
        'images' => array(
            array(
                'image_id' => '68ad6daa-05c1-4286-b2dd-5deca7c1a6c8',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'postgresql-ubuntu1204',
        'catId' => 2,
        'os' => 'Ubuntu 12.04',
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('postgresql', 'chef'),
        'description' => 'PostgreSQL',
        'images' => array(
            array(
                'image_id' => '734fa0c7-cbec-4ebc-a8b1-ffed833be097',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'redis-ubuntu1204',
        'catId' => 2,
        'os' => 'Ubuntu 12.04',
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('redis','chef'),
        'description' => 'Redis',
        'images' => array(
            array(
                'image_id' => '156f8821-ea33-4d59-b36e-2dbae17eed62',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'mongodb-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'catId' => 2,
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('mongodb','chef'),
        'description' => 'MongoDB',
        'images' => array(
            array(
                'image_id' => '933cc1b0-9130-4979-a873-1c4cc6adc4eb',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'apache-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'catId' => 3,
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('app','chef'),
        'description' => 'Apache',
        'images' => array(
            array(
                'image_id' => '37751ad0-8507-4f58-966a-328ef0a7c7f0',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),array(
        'name' => 'tomcat-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'catId' => 3,
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('tomcat','chef'),
        'description' => 'Tomcat',
        'images' => array(
            array(
                'image_id' => '366554c9-6961-4238-b7f5-b012909e55a8',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),array(
        'name' => 'nginx-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'osFamily' => 'ubuntu',
        'catId' => 4,
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('www','chef'),
        'description' => 'Nginx',
        'images' => array(
            array(
                'image_id' => 'e35edb3b-f995-4915-bd37-2008e29c1db7',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),array(
        'name' => 'haproxy-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'osFamily' => 'ubuntu',
        'catId' => 4,
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('haproxy','chef'),
        'description' => 'HAProxy',
        'images' => array(
            array(
                'image_id' => 'b10299b5-464a-4871-b354-6c5d12e00ce1',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ), array(
        'name' => 'rabbitmq-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'catId' => 5,
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('rabbitmq','chef'),
        'description' => 'RabbitMQ',
        'images' => array(
            array(
                'image_id' => '128fd870-c897-455f-88a6-77f0c4ee538f',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'memcached-ubuntu1204',
        'os' => 'Ubuntu 12.04',
        'catId' => 6,
        'osFamily' => 'ubuntu',
        'osGeneration' => '12.04',
        'osVersion' => '12.04',
        'behaviors' => array('memcached','chef'),
        'description' => 'Memcached',
        'images' => array(
            array(
                'image_id' => '95c27eb7-4973-4b14-a9b4-3508b02b3392',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'base-centos6',
        'os' => 'CentOS 6.4',
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'catId' => 1,
        'osVersion' => '6.4',
        'behaviors' => array('base','chef'),
        'description' => 'Base role',
        'images' => array(
            array(
                'image_id' => '9b723be4-c34d-4e00-9c89-aea36f4ba9f5',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'mysql5-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 2,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('mysql2','chef'),
        'description' => 'MySQL 5',
        'images' => array(
            array(
                'image_id' => '085c37a7-8355-41a8-b6c2-52cb3345be10',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'percona5-centos6',
        'os' => 'CentOS 6.4',
        'osFamily' => 'centos',
        'catId' => 2,
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('percona','chef'),
        'description' => 'Percona 5',
        'images' => array(
            array(
                'image_id' => 'e5b5ddc1-baae-4fc1-b0b2-edfa248ce291',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'postgresql-centos6',
        'os' => 'CentOS 6.4',
        'osFamily' => 'centos',
        'catId' => 2,
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('postgresql', 'chef'),
        'description' => 'PostgreSQL',
        'images' => array(
            array(
                'image_id' => '9144dff0-5440-4029-8ca1-86192cdc0be1',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'redis-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 2,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('redis','chef'),
        'description' => 'Redis',
        'images' => array(
            array(
                'image_id' => '2892d4f4-4cc4-4468-94ac-fb8c5651fe8c',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'mongodb-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 2,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('mongodb','chef'),
        'description' => 'MongoDB',
        'images' => array(
            array(
                'image_id' => '1ed9ea0a-0363-4714-af1b-0a0ddde9451d',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),
    array(
        'name' => 'apache-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 3,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('app','chef'),
        'description' => 'Apache',
        'images' => array(
            array(
                'image_id' => '13d16a4a-3972-40a6-a058-2d6176a70cae',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),array(
        'name' => 'tomcat-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 3,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('tomcat','chef'),
        'description' => 'Tomcat',
        'images' => array(
            array(
                'image_id' => '5fb15d6e-333e-4367-93bb-0cd27e00a0dd',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),array(
        'name' => 'nginx-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 4,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('www','chef'),
        'description' => 'Nginx',
        'images' => array(
            array(
                'image_id' => '2d40a20c-e749-408c-b936-feb6ac7fdb50',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),array(
        'name' => 'haproxy-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 4,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('haproxy','chef'),
        'description' => 'HAProxy',
        'images' => array(
            array(
                'image_id' => 'e4ac6980-4eab-43f0-a298-b6a1e462e12d',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),array(
        'name' => 'rabbitmq-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 5,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('rabbitmq','chef'),
        'description' => 'RabbitMQ',
        'images' => array(
            array(
                'image_id' => '449780ec-68d9-498f-b6aa-74e54aa376b7',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    ),array(
        'name' => 'memcached-centos6',
        'os' => 'CentOS 6.4',
        'catId' => 6,
        'osFamily' => 'centos',
        'osGeneration' => '6',
        'osVersion' => '6.4',
        'behaviors' => array('memcached','chef'),
        'description' => 'Memcached',
        'images' => array(
            array(
                'image_id' => '0ce1388d-9bbc-45b1-b3f9-150414d09b29',
                'platform' => 'openstack',
                'location' => 'ItalyMilano1',
                'szr_version' => '0.19.10',
                'architecture' => 'x86_64'
            )
        )
    )
);


foreach ($roles as $info) {

    if (!$info['name'])
        continue;

    $dbInfoId = $db->GetOne("SELECT id FROM roles WHERE name = ? AND origin = ? LIMIT 1", array($info['name'], 'SHARED'));
    if ($dbInfoId) {
        $dbRole = DBRole::loadById($dbInfoId);
    } else {
        $dbRole = new DBRole(0);

        $dbRole->generation = 2;
        $dbRole->origin = ROLE_TYPE::SHARED;
        $dbRole->envId = 0;
        $dbRole->clientId = 0;
        $dbRole->catId = $info['catId'];
        $dbRole->name = $info['name'];
        $dbRole->os = $info['os'];
        $dbRole->osFamily = $info['osFamily'];
        $dbRole->osGeneration = $info['osGeneration'];
        $dbRole->osVersion = $info['osVersion'];


        foreach ($info['behaviors'] as $behavior) {
            foreach (Scalr_Role_Behavior::loadByName($behavior)->getSecurityRules() as $rr)
                $rules[] = array('rule' => $rr);
        }
    }

    $dbRole->setBehaviors(array_values($info['behaviors']));

    $dbRole->description = $info['description'];

    $dbRole = $dbRole->save();

    $db->Execute("DELETE FROM role_security_rules WHERE role_id = ?", array($dbRole->id));
    foreach ($rules as $rule) {
        $db->Execute("INSERT INTO role_security_rules SET `role_id`=?, `rule`=?", array(
            $dbRole->id, $rule['rule']
        ));
        if ($rule['comment']) {
            $db->Execute("REPLACE INTO `comments` SET `env_id` = ?, `comment` = ?, `sg_name` = ?, `rule` = ?", array(
                0, $rule['comment'], "role:{$dbRole->id}", $rule['rule']
            ));
        }
    }

    foreach ($info['images'] as $image) {
        $image = (array)$image;
        $dbRole->setImage(
            $image['image_id'],
            SERVER_PLATFORMS::OPENSTACK,
            $image['location'],
            $image['szr_version'],
            $image['architecture']
        );

        $dbRole->setImage(
            $image['image_id'],
            SERVER_PLATFORMS::ECS,
            $image['location'],
            $image['szr_version'],
            $image['architecture']
        );
    }

    $dbRole->save();
}


