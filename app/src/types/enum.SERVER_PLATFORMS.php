<?php

final class SERVER_PLATFORMS
{
    const EC2		= 'ec2';
    const GCE		= 'gce';
    const AZURE     = 'azure';

    // Openstack based
    const OPENSTACK = 'openstack';
    const OCS = 'ocs';
    const NEBULA = 'nebula';
    const MIRANTIS = 'mirantis';
    const VIO = 'vio';
    const VERIZON = 'verizon';
    const CISCO = 'cisco';
    const HPCLOUD = 'hpcloud';


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
            self::CLOUDSTACK	=> 'Cloudstack',
            self::OPENSTACK		=> 'Openstack',
            self::IDCF			=> 'IDC Frontier',
            self::RACKSPACENG_US=> 'Rackspace',
            self::RACKSPACENG_UK=> 'Rackspace UK',
            self::OCS           => 'CloudScaling',
            self::NEBULA        => 'Nebula',
            self::MIRANTIS      => 'Mirantis',
            self::VIO           => 'VMWare VIO',
            self::VERIZON       => 'Verizon',
            self::CISCO         => 'Cisco Metapod',
            self::HPCLOUD       => 'HP Helion',
            self::AZURE         => 'Azure'
        );
    }

    public static function GetName($const)
    {
        $list = self::GetList();

        return $list[$const];
    }
}
