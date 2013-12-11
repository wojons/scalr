<?php

final class SERVER_PLATFORMS
{
    const EC2		= 'ec2';
    const RACKSPACE = 'rackspace';
    const EUCALYPTUS= 'eucalyptus';
    const NIMBULA	= 'nimbula';
    const GCE		= 'gce';

    // Openstack based
    const OPENSTACK = 'openstack';
    const ECS = 'ecs';
    const OCS = 'ocs';
    const NEBULA = 'nebula';


    const RACKSPACENG_US = 'rackspacengus';
    const RACKSPACENG_UK = 'rackspacenguk';


    // Cloudstack based
    const CLOUDSTACK = 'cloudstack';
    const IDCF		= 'idcf';
    const UCLOUD	= 'ucloud';


    public static function GetList()
    {
        return array(
            self::EC2 			=> 'Amazon EC2',
            self::GCE			=> 'Google CE',
            self::EUCALYPTUS 	=> 'Eucalyptus',
            self::RACKSPACE		=> 'Rackspace',
            self::NIMBULA		=> 'Nimbula',
            self::CLOUDSTACK	=> 'Cloudstack',
            self::OPENSTACK		=> 'Openstack',
            self::IDCF			=> 'IDC Frontier',
            self::UCLOUD		=> 'KT uCloud',
            self::RACKSPACENG_US=> 'Rackspace Open Cloud (US)',
            self::RACKSPACENG_UK=> 'Rackspace Open Cloud (UK)',
            self::ECS           => 'Enter Cloud Suite',
            self::OCS           => 'CloudScaling',
            self::NEBULA        => 'Nebula',
        );
    }

    public static function GetName($const)
    {
        $list = self::GetList();

        return $list[$const];
    }
}
