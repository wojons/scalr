<?php

final class SERVER_PLATFORMS
{
    const EC2		= 'ec2';
    const RACKSPACE = 'rackspace';
    const EUCALYPTUS= 'eucalyptus';
    const GCE		= 'gce';

    // Openstack based
    const OPENSTACK = 'openstack';
    const ECS = 'ecs';
    const OCS = 'ocs';
    const NEBULA = 'nebula';
    const CONTRAIL = 'contrail';


    const RACKSPACENG_US = 'rackspacengus';
    const RACKSPACENG_UK = 'rackspacenguk';


    // Cloudstack based
    const CLOUDSTACK = 'cloudstack';
    const IDCF		= 'idcf';

    public static function GetList()
    {
        return array(
            self::EC2 			=> 'Amazon EC2',
            self::GCE			=> 'Google Compute Engine',
            self::EUCALYPTUS 	=> 'Eucalyptus',
            self::RACKSPACE		=> 'Legacy Rackspace',
            self::CLOUDSTACK	=> 'Cloudstack',
            self::OPENSTACK		=> 'Openstack',
            self::IDCF			=> 'IDC Frontier',
            self::RACKSPACENG_US=> 'Rackspace',
            self::RACKSPACENG_UK=> 'Rackspace UK',
            self::ECS           => 'Enter Cloud Suite',
            self::CONTRAIL      => 'Contrail',
            self::OCS           => 'CloudScaling',
            self::NEBULA        => 'Nebula'
        );
    }

    public static function GetName($const)
    {
        $list = self::GetList();

        return $list[$const];
    }
}
