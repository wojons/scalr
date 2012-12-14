<?
	final class SERVER_PLATFORMS
	{
		const EC2		= 'ec2';
		const RDS		= 'rds';
		const RACKSPACE = 'rackspace';
		const EUCALYPTUS= 'eucalyptus';
		const NIMBULA	= 'nimbula';
		const GCE		= 'gce';
		
		// Openstack based
		const OPENSTACK = 'openstack';
		//const RACKSPACENG = 'rackspace-ng';
		
		// Cloudstack based
		const CLOUDSTACK = 'cloudstack';
		const IDCF		= 'idcf';
		const UCLOUD	= 'ucloud';
		
		
		public static function GetList()
		{
			return array(
				self::GCE			=> 'Google CE',
				self::EC2 			=> 'Amazon EC2',
				self::RDS 			=> 'Amazon RDS',
				self::EUCALYPTUS 	=> 'Eucalyptus',
				self::RACKSPACE		=> 'Rackspace',
				self::NIMBULA		=> 'Nimbula',
				self::CLOUDSTACK	=> 'Cloudstack',
				self::OPENSTACK		=> 'Openstack',
				self::IDCF			=> 'IDCF',
				self::UCLOUD		=> 'KT uCloud',
				//self::RACKSPACENG	=> 'Rackspace Next Gen'
			);
		}
		
		public static function GetName($const)
		{
			$list = self::GetList();
			
			return $list[$const];
		}
	}
?>