<?php
	class Modules_Platforms_Ec2 extends Modules_Platforms_Aws implements IPlatformModule
	{
		private $db;
		
		/** Properties **/
		const ACCOUNT_ID 	= 'ec2.account_id';
		const ACCESS_KEY	= 'ec2.access_key';
		const SECRET_KEY	= 'ec2.secret_key';
		const PRIVATE_KEY	= 'ec2.private_key';
		const CERTIFICATE	= 'ec2.certificate';
		
		/**
		 * 
		 * @var AmazonEC2
		 */
		private $instancesListCache = array();
		
		public function __construct()
		{
			$this->db = Core::GetDBInstance();
		}
		
		public function getRoleBuilderBaseImages()
		{
			//ap-southeast-2
				
			return array(
				// Oracle Enterprise Linux 5.X
				'ami-2cca7c2d'	=> array('name' => 'OEL 5.7', 'os_dist' => 'oel', 'location' => 'ap-northeast-1', 'architecture' => 'x86_64'),
				'ami-e8f4b1ba'	=> array('name' => 'OEL 5.7', 'os_dist' => 'oel', 'location' => 'ap-southeast-1', 'architecture' => 'x86_64'),
				'ami-d594aba1'	=> array('name' => 'OEL 5.7', 'os_dist' => 'oel', 'location' => 'eu-west-1', 'architecture' => 'x86_64'),
				'ami-9c578881'	=> array('name' => 'OEL 5.7', 'os_dist' => 'oel', 'location' => 'sa-east-1', 'architecture' => 'x86_64'),
				'ami-9fa076f6'	=> array('name' => 'OEL 5.7', 'os_dist' => 'oel', 'location' => 'us-east-1', 'architecture' => 'x86_64'),
				'ami-5ddb8518'	=> array('name' => 'OEL 5.7', 'os_dist' => 'oel', 'location' => 'us-west-1', 'architecture' => 'x86_64'),
				'ami-4446cb74'	=> array('name' => 'OEL 5.7', 'os_dist' => 'oel', 'location' => 'us-west-2', 'architecture' => 'x86_64'),
					
				// Ubuntu 10.04 LTS
				'ami-e49c24e5'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'ap-northeast-1', 'architecture' => 'i386'),
				'ami-f49c24f5'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'ap-northeast-1', 'architecture' => 'x86_64'),
			
				'ami-9ccb88ce'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'ap-southeast-1', 'architecture' => 'i386'),
				'ami-86cb88d4'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'ap-southeast-1', 'architecture' => 'x86_64'),			
			
				'ami-8756c1bd' 	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'ap-southeast-2', 'architecture' => 'x86_64'),
			
				'ami-a31e13d7'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'eu-west-1', 'architecture' => 'i386'),
				'ami-ab1e13df'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'eu-west-1', 'architecture' => 'x86_64'),			
				
				'ami-0ea67e13'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'sa-east-1', 'architecture' => 'i386'),
				'ami-0aa67e17'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'sa-east-1', 'architecture' => 'x86_64'),			

				'ami-fb64e492'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'us-east-1', 'architecture' => 'i386'),
				'ami-f364e49a'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'us-east-1', 'architecture' => 'x86_64'),			
				
				'ami-f4e4c5b1'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'us-west-1', 'architecture' => 'i386'),
				'ami-fce4c5b9'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'us-west-1', 'architecture' => 'x86_64'),

				'ami-06b83036'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'us-west-2', 'architecture' => 'i386'),
				'ami-0cb8303c'	=> array('name' => 'Ubuntu 10.04', 'os_dist' => 'ubuntu', 'location' => 'us-west-2', 'architecture' => 'x86_64'),
							
			
				/*				
				// Ubuntu 11.10
				'ami-0e299f0f'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'ap-northeast-1', 'architecture' => 'i386'),
				'ami-10299f11'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'ap-northeast-1', 'architecture' => 'x86_64'),			
						
				'ami-5c96d20e'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'ap-southeast-1', 'architecture' => 'i386'),
				'ami-4296d210'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'ap-southeast-1', 'architecture' => 'x86_64'),			

				'ami-8d5069f9'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'eu-west-1', 'architecture' => 'i386'),			
				'ami-895069fd'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'eu-west-1', 'architecture' => 'x86_64'),

				'ami-b473aca9'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'sa-east-1', 'architecture' => 'i386'),
				'ami-b673acab'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'sa-east-1', 'architecture' => 'x86_64'),			

				'ami-c2ba68ab'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'us-east-1', 'architecture' => 'i386'),			
				'ami-baba68d3'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'us-east-1', 'architecture' => 'x86_64'),

				'ami-63a8f126'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'us-west-1', 'architecture' => 'i386'),			
				'ami-6da8f128'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'us-west-1', 'architecture' => 'x86_64'),

				'ami-ac05889c'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'us-west-2', 'architecture' => 'i386'),			
				'ami-ae05889e'	=> array('name' => 'Ubuntu 11.10', 'os_dist' => 'ubuntu', 'location' => 'us-west-2', 'architecture' => 'x86_64'),
				*/

			
				// Ubuntu 12.04
				'ami-109f2711'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'ap-northeast-1', 'architecture' => 'i386'),
				'ami-249f2725'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'ap-northeast-1', 'architecture' => 'x86_64'),			

				'ami-94ca89c6'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'ap-southeast-1', 'architecture' => 'i386'),
				'ami-84ca89d6'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'ap-southeast-1', 'architecture' => 'x86_64'),			
				
				'ami-2755c21d' 	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'ap-southeast-2', 'architecture' => 'x86_64'),
				
				'ami-131b1667'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'eu-west-1', 'architecture' => 'i386'),			
				'ami-091b167d'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'eu-west-1', 'architecture' => 'x86_64'),

				'ami-8ea67e93'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'sa-east-1', 'architecture' => 'i386'),
				'ami-bca67ea1'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'sa-east-1', 'architecture' => 'x86_64'),			

				'ami-f96fef90'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'us-east-1', 'architecture' => 'i386'),			
				'ami-cd6fefa4'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'us-east-1', 'architecture' => 'x86_64'),

				'ami-1ce6c759'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'us-west-1', 'architecture' => 'i386'),			
				'ami-36e6c773'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'us-west-1', 'architecture' => 'x86_64'),

				'ami-14ba3224'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'us-west-2', 'architecture' => 'i386'),			
				'ami-02ba3232'	=> array('name' => 'Ubuntu 12.04', 'os_dist' => 'ubuntu', 'location' => 'us-west-2', 'architecture' => 'x86_64'),

				

				// CentOS 5.7
				'ami-4aca7c4b'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'ap-northeast-1', 'architecture' => 'i386'),				
				'ami-d6cd7bd7'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'ap-northeast-1', 'architecture' => 'x86_64'),

				'ami-caf5b098'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'ap-southeast-1', 'architecture' => 'i386'),
				'ami-28f5b07a'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'ap-southeast-1', 'architecture' => 'x86_64'),	
				
				'ami-33caf547'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'eu-west-1', 'architecture' => 'i386'),
				'ami-45caf531'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'eu-west-1', 'architecture' => 'x86_64'),			

				'ami-e2a977ff'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'sa-east-1', 'architecture' => 'i386'),			
				'ami-f0a977ed'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'sa-east-1', 'architecture' => 'x86_64'),

				'ami-39fe2950'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'us-east-1', 'architecture' => 'i386'),
				'ami-fbfe2992'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'us-east-1', 'architecture' => 'x86_64'),			

				'ami-1789d752'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'us-west-1', 'architecture' => 'i386'),
				'ami-0189d744'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'us-west-1', 'architecture' => 'x86_64'),	

				'ami-aaff739a'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'us-west-2', 'architecture' => 'i386'),			
				'ami-90fe72a0'	=> array('name' => 'CentOS 5.7', 'os_dist' => 'centos', 'location' => 'us-west-2', 'architecture' => 'x86_64'),
					
					
					
				// CentOS 6.3
				'ami-a249fba3'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'ap-northeast-1', 'architecture' => 'i386'),
				'ami-aa49fbab'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'ap-northeast-1', 'architecture' => 'x86_64'),
				
				'ami-de60218c'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'ap-southeast-1', 'architecture' => 'i386'),
				'ami-d860218a'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'ap-southeast-1', 'architecture' => 'x86_64'),
				
				'ami-c54641b1'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'eu-west-1', 'architecture' => 'i386'),
				'ami-e3464197'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'eu-west-1', 'architecture' => 'x86_64'),
				
				'ami-e2a977ff'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'sa-east-1', 'architecture' => 'i386'),
				'ami-c84896d5'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'sa-east-1', 'architecture' => 'x86_64'),
				
				'ami-9110c7f8'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'us-east-1', 'architecture' => 'i386'),
				'ami-bb10c7d2'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'us-east-1', 'architecture' => 'x86_64'),
				
				'ami-a9ddf8ec'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'us-west-1', 'architecture' => 'i386'),
				'ami-a3ddf8e6'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'us-west-1', 'architecture' => 'x86_64'),
				
				'ami-6e46c95e'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'us-west-2', 'architecture' => 'i386'),
				'ami-4841ce78'	=> array('name' => 'CentOS 6.3', 'os_dist' => 'centos', 'location' => 'us-west-2', 'architecture' => 'x86_64')

			);
		}
		
		public function getPropsList()
		{
			return array(
				self::ACCOUNT_ID	=> 'AWS Account ID',
				self::ACCESS_KEY	=> 'AWS Access Key',
				self::SECRET_KEY	=> 'AWS Secret Key',
				self::CERTIFICATE	=> 'AWS x.509 Certificate',
				self::PRIVATE_KEY	=> 'AWS x.509 Private Key'
			);
		}
		
		public function GetServerCloudLocation(DBServer $DBServer)
		{
			return $DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION);
		}
		
		public function GetServerID(DBServer $DBServer)
		{
			return $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
		}
		
		public function GetServerFlavor(DBServer $DBServer)
		{
			return $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_TYPE);
		}
		
		public function IsServerExists(DBServer $DBServer, $debug = false)
		{
			return in_array(
				$DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID), 
				@array_keys($this->GetServersList(
					$DBServer->GetEnvironmentObject(), 
					$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)
				))
			);
		}
		
		public function GetServerIPAddresses(DBServer $DBServer)
		{
			$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
				$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION), 
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
			);
	        
	        $iinfo = $EC2Client->DescribeInstances($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));
		    $iinfo = $iinfo->reservationSet->item->instancesSet->item;
		    
		    return array(
		    	'localIp'	=> $iinfo->privateIpAddress,
		    	'remoteIp'	=> $iinfo->ipAddress
		    );
		}
		
		public function GetServersList(Scalr_Environment $environment, $region, $skipCache = false)
		{
			if (!$region)
				return array();
			
			if (!$this->instancesListCache[$environment->id][$region] || $skipCache)
			{
				$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
					$region, 
					$environment->getPlatformConfigValue(self::PRIVATE_KEY),
					$environment->getPlatformConfigValue(self::CERTIFICATE)
				);
		        
		        try
				{
		            $results = $EC2Client->DescribeInstances();
		            $results = $results->reservationSet;
				}
				catch(Exception $e)
				{
					throw new Exception(sprintf("Cannot get list of servers for platfrom ec2: %s", $e->getMessage()));
				}


				if ($results->item)
				{					
					if ($results->item->reservationId)
						$this->instancesListCache[$environment->id][$region][(string)$results->item->instancesSet->item->instanceId] = (string)$results->item->instancesSet->item->instanceState->name;
					else
					{
						foreach ($results->item as $item)
							$this->instancesListCache[$environment->id][$region][(string)$item->instancesSet->item->instanceId] = (string)$item->instancesSet->item->instanceState->name;
					}
				}
			}
	        
			return $this->instancesListCache[$environment->id][$region];
		}
		
		public function GetServerRealStatus(DBServer $DBServer)
		{
			$region = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION);
			
			$iid = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
			if (!$iid || !$region)
			{
				$status = 'not-found';
			}
			elseif (!$this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$iid])
			{
		        $EC2Client = Scalr_Service_Cloud_Aws::newEc2(
					$region, 
					$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
					$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
				);
		        
		        try {
		        	$iinfo = $EC2Client->DescribeInstances($iid);
			        $iinfo = $iinfo->reservationSet->item;
			        
			        if ($iinfo)
			        	$status = (string)$iinfo->instancesSet->item->instanceState->name;
			        else
			        	$status = 'not-found';
		        }
		        catch(Exception $e)
		        {
		        	if (stristr($e->getMessage(), "does not exist"))
		        		$status = 'not-found';
		        	else
		        		throw $e;
		        }
			}
			else
			{
				$status = $this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID)];
			}
			
			return Modules_Platforms_Ec2_Adapters_Status::load($status);
		}
		
		public function TerminateServer(DBServer $DBServer)
		{
			$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
				$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION), 
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
			);
	        
	        $EC2Client->TerminateInstances(array($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID)));
	        
	        return true;
		}
		
		public function RebootServer(DBServer $DBServer)
		{
			$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
				$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION), 
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
			);
	        
	        $EC2Client->RebootInstances(array($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID)));
	        
	        return true;
		}
		
		public function RemoveServerSnapshot(DBRole $DBRole)
		{
			foreach ($DBRole->getImageId(SERVER_PLATFORMS::EC2) as $location => $imageId) {
				try {
					$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
						$location, 
						$DBRole->getEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
						$DBRole->getEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
					);	
					
					try {
						$DescribeImagesType = new DescribeImagesType();
						$DescribeImagesType->imagesSet = new stdClass();
						$DescribeImagesType->imagesSet->item[] = array("imageId" => $imageId);
			        	$ami_info = $EC2Client->DescribeImages($DescribeImagesType);
					}
					catch(Exception $e)
					{
						if (stristr($e->getMessage(), "Failure Signing Data") || stristr($e->getMessage(), "is no longer available") || stristr($e->getMessage(), "does not exist") || stristr($e->getMessage(), "Not authorized for image"))
							return true;
						else
							throw $e;
					}
			        
			        $platfrom = (string)$ami_info->imagesSet->item->platform;
			        $rootDeviceType = (string)$ami_info->imagesSet->item->rootDeviceType;
			        
			        if ($rootDeviceType == 'ebs') {
			        	$EC2Client->DeregisterImage($imageId);
			        	
			        	$snapshotId = (string)$ami_info->imagesSet->item->blockDeviceMapping->item->ebs->snapshotId;
			        	if ($snapshotId)
			        		$EC2Client->DeleteSnapshot($snapshotId);
			        }
			        else {
			       		$image_path = (string)$ami_info->imagesSet->item->imageLocation;
	    		    	
	    		    	$chunks = explode("/", $image_path);
	    		    	
	    		    	$bucket_name = $chunks[0];
	    		    	if (count($chunks) == 3)
	    		    		$prefix = $chunks[1];
	    		    	else
	    		    		$prefix = str_replace(".manifest.xml", "", $chunks[1]);
	    		    	
	    		    	try {
	    		    		$bucket_not_exists = false;
	    		    		$S3Client = new AmazonS3(
	    		    			$DBRole->getEnvironmentObject()->getPlatformConfigValue(self::ACCESS_KEY), 
	    		    			$DBRole->getEnvironmentObject()->getPlatformConfigValue(self::SECRET_KEY)
	    		    		);
	    		    		$objects = $S3Client->ListBucket($bucket_name, $prefix);
	    		    	}
	    		    	catch(Exception $e) {
	    		    		if (stristr($e->getMessage(), "The specified bucket does not exist"))
	    		    			$bucket_not_exists = true;
	    		    	}	
	    		    			    			    	
	    		    	if ($ami_info) {
	    		    		if (!$bucket_not_exists) {
	    			    		foreach ($objects as $object)
	    			    			$S3Client->DeleteObject($object->Key, $bucket_name);
	    			    			
	    			    		$bucket_not_exists = true;
	    			    	}
	    		    		
	    		    		if ($bucket_not_exists)
	    			    		$EC2Client->DeregisterImage($imageId);
	    		    	}
			        }
				} catch(Exception $e) {
					if (stristr($e->getMessage(), "is no longer available") || stristr($e->getMessage(), "Not authorized for image"))
						continue;
					else
						throw $e;
				}
			}
		}
		
		public function CheckServerSnapshotStatus(BundleTask $BundleTask)
		{
			if ($BundleTask->bundleType == SERVER_SNAPSHOT_CREATION_TYPE::EC2_WIN2003) {
				
			}
			else if (in_array($BundleTask->bundleType, array(
				SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM, 
				SERVER_SNAPSHOT_CREATION_TYPE::EC2_WIN200X))
			) {
				try
				{
					$DBServer = DBServer::LoadByID($BundleTask->serverId);
					
			        $EC2Client = Scalr_Service_Cloud_Aws::newEc2(
						$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION), 
						$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
						$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
					);
			        
			        $DescribeImagesType = new DescribeImagesType();
					$DescribeImagesType->imagesSet->item[] = array("imageId" => $BundleTask->snapshotId);
			        $ami_info = $EC2Client->DescribeImages($DescribeImagesType);
			        $ami_info = $ami_info->imagesSet->item;
			        
			        $BundleTask->Log(sprintf("Checking snapshot creation status: %s", $ami_info->imageState));
			        
			        $metaData = $BundleTask->getSnapshotDetails();
			        
			        if ($ami_info->imageState == 'available') {
			        	$metaData['szr_version'] = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_VESION);
			        	
			        	 if ($ami_info->rootDeviceType == 'ebs')
							$tags[] = ROLE_TAGS::EC2_EBS;
										
						if ($ami_info->virtualizationType == 'hvm')
							$tags[] = ROLE_TAGS::EC2_HVM;
			        		
			        	$metaData['tags'] = $tags;
			        	
			        	$BundleTask->SnapshotCreationComplete($BundleTask->snapshotId, $metaData);
			        }
			        else {
			        	$BundleTask->Log("CheckServerSnapshotStatus: AMI status = {$ami_info->imageState}. Waiting...");
			        }
				}
				catch(Exception $e) {
					Logger::getLogger(__CLASS__)->fatal("CheckServerSnapshotStatus ({$BundleTask->id}): {$e->getMessage()}");
				}
			}
		}
		
		public function CreateServerSnapshot(BundleTask $BundleTask)
		{
			$DBServer = DBServer::LoadByID($BundleTask->serverId);
			
			$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
				$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION), 
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
			);
	        
	        if (!$BundleTask->prototypeRoleId)
	        {
	        	$proto_image_id = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AMIID);
	        }
	        else
	        {
	        	$protoRole = DBRole::loadById($BundleTask->prototypeRoleId);
	        	$proto_image_id = $protoRole->getImageId(
	        		SERVER_PLATFORMS::EC2, 
	        		$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)
	        	);
				
				$details = $protoRole->getImageDetails(
					SERVER_PLATFORMS::EC2, 
	        		$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)
				);
				
				if ($details['os_family'] == 'oel') {
					$BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
				}
	        }
	        
	        $DescribeImagesType = new DescribeImagesType();
			$DescribeImagesType->imagesSet->item[] = array("imageId" => $proto_image_id);
	        $ami_info = $EC2Client->DescribeImages($DescribeImagesType);
	        
	        $platfrom = (string)$ami_info->imagesSet->item->platform;
	        
	        if ($platfrom == 'windows')
	        {
	        	if ((string)$ami_info->imagesSet->item->rootDeviceType != 'ebs') {
	        		$BundleTask->SnapshotCreationFailed("Only EBS root filesystem supported for Windows servers.");
	        		return;
	        	}
	        	
	        	if ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::PENDING) {
		        	$BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_WIN200X;
		        	$BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));
		        	$BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::PREPARING;
		        	
		        	try {
			        	$msg = $DBServer->SendMessage(new Scalr_Messaging_Msg_Win_PrepareBundle($BundleTask->id));
			        	if ($msg)
			        		$BundleTask->Log(sprintf(_("PrepareBundle message sent. MessageID: %s. Bundle task status changed to: %s"), $msg->messageId, $BundleTask->status));
			        	else
			        		throw new Exception("Cannot send message");
		        	} catch (Exception $e) {
		        		$BundleTask->SnapshotCreationFailed("Cannot send PrepareBundle message to server.");
		        		return false;
		        	}
	        	} elseif ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::PREPARING) {
	        		
	        		$BundleTask->Log(sprintf(_("Selected platform snapshot type: %s"), $BundleTask->bundleType));
	        			
	        		try
		        	{
			        	$CreateImageType = new CreateImageType(
			        		$DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID),
			        		$BundleTask->roleName."-".date("YmdHi"),
			        		$BundleTask->roleName,
			        		false
			        	);
			        	
			        	$result = $EC2Client->CreateImage($CreateImageType);
									    	
			        	$BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
			        	$BundleTask->snapshotId = $result->imageId;
			        	
			        	$BundleTask->Log(sprintf(_("Snapshot creating initialized (AMIID: %s). Bundle task status changed to: %s"), 
			        		$BundleTask->snapshotId, $BundleTask->status
			        	));
		        	}
		        	catch(Exception $e)
		        	{
		        		$BundleTask->SnapshotCreationFailed($e->getMessage());
		        		return;
		        	}
	        	}
	        }
	        else
	        {
	        	$BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
				
				
				if (!$BundleTask->bundleType) {
		        	if ((string)$ami_info->imagesSet->item->rootDeviceType == 'ebs') {
		        		if ((string)$ami_info->imagesSet->item->virtualizationType == 'hvm')
		        			$BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
		        		else
		        			$BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS;
		        	} else {
		        		$BundleTask->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_S3I;
		        	}
				}
	        	
	        	$BundleTask->Save();
				
	        	$BundleTask->Log(sprintf(_("Selected platfrom snapshoting type: %s"), $BundleTask->bundleType));
	        	
	        	if ($BundleTask->bundleType == SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM)
	        	{
		        	try
		        	{
			        	$CreateImageType = new CreateImageType(
			        		$DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID),
			        		$BundleTask->roleName."-".date("YmdHi"),
			        		$BundleTask->roleName,
			        		false
			        	);
			        	
			        	$result = $EC2Client->CreateImage($CreateImageType);
			        			        	
			        	$BundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
			        	$BundleTask->snapshotId = $result->imageId;
			        	
			        	$BundleTask->Log(sprintf(_("Snapshot creating initialized (AMIID: %s). Bundle task status changed to: %s"), 
			        		$BundleTask->snapshotId, $BundleTask->status
			        	));
		        	}
		        	catch(Exception $e)
		        	{
		        		$BundleTask->SnapshotCreationFailed($e->getMessage());
		        		return;
		        	}
	        	}
	        	else {
		        	$msg = new Scalr_Messaging_Msg_Rebundle(
		        		$BundleTask->id,
						$BundleTask->roleName,
						array()
		        	);
	
		        	$metaData = $BundleTask->getSnapshotDetails();
		        	if ($metaData['rootVolumeSize'])
		        		$msg->volumeSize = $metaData['rootVolumeSize']; 
	
	        		if (!$DBServer->SendMessage($msg))
	        		{
	        			$BundleTask->SnapshotCreationFailed("Cannot send rebundle message to server. Please check event log for more details.");
	        			return;
	        		}
		        	else
		        	{
			        	$BundleTask->Log(sprintf(_("Snapshot creation started (MessageID: %s). Bundle task status changed to: %s"), 
			        		$msg->messageId, $BundleTask->status
			        	));
		        	}
	        	}
	        }
	        
	        $BundleTask->setDate('started');
	        $BundleTask->Save();
		}
		
		private function ApplyAccessData(Scalr_Messaging_Msg $msg)
		{
			
			
		}
		
		public function GetServerConsoleOutput(DBServer $DBServer)
		{
			$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
				$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION), 
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
			);
	        
	        $c = $EC2Client->GetConsoleOutput($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));
	        
	        if ($c->output)
	        	return $c->output;
	        else
	        	return false;
		}
		
		public function GetServerExtendedInformation(DBServer $DBServer)
		{
			try
			{
				try {
		        	$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
						$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION), 
						$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
						$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
					);
		        
		        	$iinfo = $EC2Client->DescribeInstances($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));
		        	$iinfo = $iinfo->reservationSet->item;
				}
				catch(Exception $e) {}
		        
		        if ($iinfo && $iinfo->instancesSet->item)
		        {
			        $groups = array();
			        if ($iinfo->groupSet->item->groupId)
			        	$groups[] = $iinfo->groupSet->item->groupName . " ({$iinfo->groupSet->item->groupId})";
			        else
			        {
			        	foreach ($iinfo->groupSet->item as $item)
			        		$groups[] = $item->groupName . " (<a href='#/security/groups/{$item->groupId}/edit?cloudLocation={$DBServer->GetProperty(EC2_SERVER_PROPERTIES::REGION)}&platform=ec2'>{$item->groupId}</a>)";
			        }
			        
			        $monitoring = $iinfo->instancesSet->item->monitoring->state;
			        if ($monitoring == 'disabled')
			        {
			        	$monitoring = "Disabled
							&nbsp;(<a href='aws_ec2_cw_manage.php?action=Enable&server_id={$DBServer->serverId}'>Enable</a>)";
			        }
			        else 
			        {
			        	$monitoring = "<a href='/aws_cw_monitor.php?ObjectId=".$DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID)."&Object=InstanceId&NameSpace=AWS/EC2'>Enabled</a>
							&nbsp;(<a href='aws_ec2_cw_manage.php?action=Disable&server_id={$DBServer->serverId}'>Disable</a>)";
			        }
			        
					try {
						$status = $EC2Client->DescribeInstanceStatus($DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID));
						$statusInfo = $status->instanceStatusSet->item;
					} catch (Exception $e) {}
					
					if ($statusInfo) {
						if ($statusInfo->systemStatus->status == 'ok')
							$systemStatus = '<span style="color:green;">OK</span>';
						else {
							$txtDetails = "";
							$details = $statusInfo->systemStatus->details->item;
							if ($details->name)
								$details = array($details);
							
							foreach ($details as $d)
								$txtDetails .= " {$d->name} is {$d->status},";
							
							$txtDetails = trim($txtDetails, " ,");
							
							$systemStatus = "<span style='color:red;'>{$statusInfo->systemStatus->status}</span> ({$txtDetails})";
						}
						
						if ($statusInfo->instanceStatus->status == 'ok')
							$iStatus = '<span style="color:green;">OK</span>';
						else {
							$txtDetails = "";
							$details = $statusInfo->instanceStatus->details->item;
							if ($details->name)
								$details = array($details);
							
							foreach ($details as $d)
								$txtDetails .= " {$d->name} is {$d->status},";
							
							$txtDetails = trim($txtDetails, " ,");
							
							$iStatus = "<span style='color:red;'>{$statusInfo->instanceStatus->status}</span> ({$txtDetails})";
						}
					} else {
						$systemStatus = "Unknown";
						$iStatus = "Unknown";
					}
					
			       // var_dump($iinfo);
			        
			        $retval = array(
			        	'AWS System Status'   	=> $systemStatus,
			        	'AWS Instance Status' 	=> $iStatus,
			        	'Instance ID'			=> $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID),
			        	'Owner ID'				=> $iinfo->ownerId,
			        	'Image ID (AMI)'		=> $iinfo->instancesSet->item->imageId,
			        	'Public DNS name'		=> $iinfo->instancesSet->item->dnsName,			        
			        	'Private DNS name'		=> $iinfo->instancesSet->item->privateDnsName,
			        	'Public IP'				=> $iinfo->instancesSet->item->ipAddress,
			        	'Private IP'			=> $iinfo->instancesSet->item->privateIpAddress,			        
			        	'Key name'				=> $iinfo->instancesSet->item->keyName,
			        	//'AMI launch index'		=> $iinfo->instancesSet->item->amiLaunchIndex,
			        	'Instance type'			=> $iinfo->instancesSet->item->instanceType,
			        	'Launch time'			=> $iinfo->instancesSet->item->launchTime,
			        	'Architecture'			=> $iinfo->instancesSet->item->architecture,
			        	'Root device type'		=> $iinfo->instancesSet->item->rootDeviceType,
			        	'Instance state'		=> $iinfo->instancesSet->item->instanceState->name." ({$iinfo->instancesSet->item->instanceState->code})",
			        	'Placement'				=> $iinfo->instancesSet->item->placement->availabilityZone,
			        	'Tenancy'				=> $iinfo->instancesSet->item->placement->tenancy,
			        	'EBS Optimized'			=> $iinfo->instancesSet->item->ebsOptimized ? "Yes" : "No",
			        	'Monitoring (CloudWatch)'	=> $monitoring,
			        	'Security groups'		=> implode(', ', $groups)
			        );
			        
			        if ($iinfo->instancesSet->item->reason)
			        	$retval['Reason'] = $iinfo->instancesSet->item->reason;
			        
			        
			        return $retval;
		        }
			}
			catch(Exception $e)
			{
				
			}
			
			return false;
		}
		
		public function LaunchServer(DBServer $DBServer, Scalr_Server_LaunchOptions $launchOptions = null)
		{	        
			$RunInstancesType = new RunInstancesType();
	        
	        $RunInstancesType->ConfigureRootPartition();
			
			if (!$launchOptions)
			{
				$launchOptions = new Scalr_Server_LaunchOptions();
				$DBRole = DBRole::loadById($DBServer->roleId);
				
				// Set Cloudwatch monitoring
		        $RunInstancesType->SetCloudWatchMonitoring(
		        	$DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_ENABLE_CW_MONITORING)
		        );

		        $launchOptions->architecture = $DBRole->architecture;
		        
		        $launchOptions->imageId = $DBRole->getImageId(
		        	SERVER_PLATFORMS::EC2, 
		        	$DBServer->GetFarmRoleObject()->CloudLocation
		        );
		        
		        $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;
		        
		        $akiId = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AKIID);
		        if (!$akiId)
		        	$akiId = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_AKI_ID);
		        		
		        if ($akiId)
		        	$RunInstancesType->kernelId = $akiId;
		        	        
		        $ariId = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::ARIID);
		        if (!$ariId)
		        	$ariId = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_ARI_ID);
		        		
		        if ($ariId)
		        	$RunInstancesType->ramdiskId = $ariId;
		        	
				$i_type = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_INSTANCE_TYPE);
		        if (!$i_type)
		        {
		        	$DBRole = DBRole::loadById($DBServer->roleId);
		        	$i_type = $DBRole->getProperty(EC2_SERVER_PROPERTIES::INSTANCE_TYPE);
		        }
		        
		        $launchOptions->serverType = $i_type;
		        
		        if ($DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_EBS_OPTIMIZED) == 1)
		        	$RunInstancesType->ebsOptimized = 1;
		        else
		        	$RunInstancesType->ebsOptimized = 0;
		        
		        foreach ($DBServer->GetCloudUserData() as $k=>$v)
	        		$u_data .= "{$k}={$v};";
	        	
	        	$RunInstancesType->SetUserData(trim($u_data, ";"));
	        	
				$vpcPrivateIp = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_VPC_PRIVATE_IP);
		        $vpcSubnetId = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_VPC_SUBNET_ID);
		        if ($vpcSubnetId)
		        {
		        	$RunInstancesType->subnetId = $vpcSubnetId;
		        	
		        	if ($vpcPrivateIp)
		        		$RunInstancesType->privateIpAddress = $vpcPrivateIp;
		        }
			}
			else 
				$RunInstancesType->SetUserData(trim($launchOptions->userData));

			if ($launchOptions->serverType == 'hi1.4xlarge') {
				$itm = new stdClass();
				$itm->deviceName = '/dev/sdf';
				$itm->virtualName = 'ephemeral0';
				
				$RunInstancesType->blockDeviceMapping->item[] = $itm;
				
				$itm = new stdClass();
				$itm->deviceName = '/dev/sdg';
				$itm->virtualName = 'ephemeral1';
				
				$RunInstancesType->blockDeviceMapping->item[] = $itm;
			}
			
			$DBServer->SetProperty(SERVER_PROPERTIES::ARCHITECTURE, $launchOptions->architecture);
				
			$EC2Client = Scalr_Service_Cloud_Aws::newEc2(
				$launchOptions->cloudLocation, 
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::PRIVATE_KEY),
				$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::CERTIFICATE)
			);
			
	        // Set AMI, AKI and ARI ids
	        $RunInstancesType->imageId = $launchOptions->imageId;
	        
	        if (!$RunInstancesType->subnetId) {
	        	// Set Security groups
				foreach ($this->GetServerSecurityGroupsList($DBServer, $EC2Client) as $sgroup)
	        		$RunInstancesType->AddSecurityGroup($sgroup);
	        }
	        	
	        $RunInstancesType->minCount = 1;
	        $RunInstancesType->maxCount = 1;
	        	
	        // Set availability zone
	        if (!$launchOptions->availZone) {
		        $avail_zone = $this->GetServerAvailZone($DBServer, $EC2Client, $launchOptions);
		        if ($avail_zone)
		        	$RunInstancesType->SetAvailabilityZone($avail_zone);
	        } else 
	        	$RunInstancesType->SetAvailabilityZone($launchOptions->availZone);
	        
	        // Set instance type
	        $RunInstancesType->instanceType = $launchOptions->serverType;
	        
	        if (in_array($RunInstancesType->instanceType, array('cc1.4xlarge', 'cg1.4xlarge', 'cc2.8xlarge', 'hi1.4xlarge')))
	        {
	        	$placementGroup = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_CLUSTER_PG);
	        	if (!$placementGroup && $RunInstancesType->instanceType != 'hi1.4xlarge')
	        	{
	        		$placementGroup = "scalr-role-{$DBServer->farmRoleId}";
	        		if (!$EC2Client->CreatePlacementGroup($placementGroup))
	        			throw new Exception(sprintf(_("Cannot launch new instance. Unable to create placement group: %s"), $result->faultstring));
	        			
	        		$DBServer->GetFarmRoleObject()->SetSetting(DBFarmRole::SETTING_AWS_CLUSTER_PG, $placementGroup);
	        	}
	        	
				if ($placementGroup)
	        		$RunInstancesType->SetPlacementGroup($placementGroup);
	        }
	        
	        // Set additional info
	       	$RunInstancesType->additionalInfo = "";
	       	
	       	
	       	/////
	       	$sshKey = Scalr_SshKey::init();
	       	
	       	if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
				$keyName = "SCALR-ROLESBUILDER-".SCALR_ID;
				$farmId = 0;
	       	} else {
	       		$keyName = "FARM-{$DBServer->farmId}-".SCALR_ID;
	       		$farmId = $DBServer->farmId;
				$oldKeyName = "FARM-{$DBServer->farmId}";
				if ($sshKey->loadGlobalByName($oldKeyName, $launchOptions->cloudLocation, $DBServer->envId, SERVER_PLATFORMS::EC2)) {
					$keyName = $oldKeyName;
					$skipKeyValidation = true;
				}
	       	}
	       	
	       	if (!$skipKeyValidation && !$sshKey->loadGlobalByName($keyName, $launchOptions->cloudLocation, $DBServer->envId, SERVER_PLATFORMS::EC2)) {
	       		$result = $EC2Client->CreateKeyPair($keyName);
	       		if ($result->keyMaterial) {
	       			$sshKey->farmId = $farmId;
	       			$sshKey->clientId = $DBServer->clientId;
	       			$sshKey->envId = $DBServer->envId;
	       			$sshKey->type = Scalr_SshKey::TYPE_GLOBAL;
	       			$sshKey->cloudLocation = $launchOptions->cloudLocation;
	       			$sshKey->cloudKeyName = $keyName;
	       			$sshKey->platform = SERVER_PLATFORMS::EC2;
	       	
	       			$sshKey->setPrivate($result->keyMaterial);
	       	
	       			$sshKey->setPublic($sshKey->generatePublicKey());
	       	
	       			$sshKey->save();
	       		}
	       	}
	       	/////
	       	
	        $RunInstancesType->keyName = $keyName;
	        
	        try {
				$result = $EC2Client->RunInstances($RunInstancesType);
	        }
	        catch (Exception $e) {
	        	if (stristr($e->getMessage(), "The requested Availability Zone is no longer supported") || 
	        	stristr($e->getMessage(), "is not supported in your requested Availability Zone") ||
				stristr($e->getMessage(), "is currently constrained and we are no longer accepting new customer requests")) {
		        	$availZone = $RunInstancesType->placement->availabilityZone;
		        	$DBServer->GetEnvironmentObject()->setPlatformConfig(array(
		        		"aws.{$launchOptions->cloudLocation}.{$availZone}.unavailable" => time()
		        	), false);
		        	
		        	throw $e;
	        	} else {
	        		throw $e;
	        	}
	        }
	        
	        if ($result->instancesSet) {
	        	$DBServer->SetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE, (string)$result->instancesSet->item->placement->availabilityZone);
	        	$DBServer->SetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID, (string)$result->instancesSet->item->instanceId);
	        	$DBServer->SetProperty(EC2_SERVER_PROPERTIES::INSTANCE_TYPE, $RunInstancesType->instanceType);
	        	$DBServer->SetProperty(EC2_SERVER_PROPERTIES::AMIID, $RunInstancesType->imageId);
	        	$DBServer->SetProperty(EC2_SERVER_PROPERTIES::REGION, $launchOptions->cloudLocation);
	        	
	        	try {
	        		if ($DBServer->farmId != 0) {
		        		$CreateTagsType = new CreateTagsType(
		        			array((string)$result->instancesSet->item->instanceId),
		        			array(
		        				"scalr-farm-id"			=> $DBServer->farmId,
		        				"scalr-farm-name"		=> $DBServer->GetFarmObject()->Name,
		        				"scalr-farm-role-id"	=> $DBServer->farmRoleId,
		        				"scalr-role-name"		=> $DBServer->GetFarmRoleObject()->GetRoleObject()->name,
		        				"scalr-server-id"		=> $DBServer->serverId,
		        				"Name"					=> sprintf("%s → %s #%s", 
									$DBServer->GetFarmObject()->Name,
									$DBServer->GetFarmRoleObject()->GetRoleObject()->name,
									$DBServer->index
								)
		        			)
		        		);
		        		
		        		$EC2Client->CreateTags($CreateTagsType);
	        		}
	        	}
	        	catch(Exception $e){
	        		Logger::getLogger('EC2')->warn("Cannot add tags to server: {$e->getMessage()}");
	        	}
	        	
		        return $DBServer;
	        }
	        else 
	            throw new Exception(sprintf(_("Cannot launch new instance. %s"), serialize($result)));
		}
		
		/*********************************************************************/
		/*********************************************************************/
		/*********************************************************************/
		/*********************************************************************/
		/*********************************************************************/
		
		private function GetServerSecurityGroupsList(DBServer $DBServer, $EC2Client)
		{
			// Add default security group
			$retval = array('default');
			
			if ($DBServer->farmRoleId) {
				$dbFarmRole = $DBServer->GetFarmRoleObject();
				$sgList = trim($dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_SG_LIST));
				if ($sgList) {
					$sgList = explode(",", $sgList);
					foreach ($sgList as $sg)
						if ($sg != '')
							array_push($retval, trim($sg));
				}
			}
			
			try {
				$aws_sgroups_list_t = $EC2Client->DescribeSecurityGroups();
				$aws_sgroups_list_t = $aws_sgroups_list_t->securityGroupInfo->item;
		        if ($aws_sgroups_list_t instanceof stdClass)
		        	$aws_sgroups_list_t = array($aws_sgroups_list_t);
	
		        $aws_sgroups = array();
		        foreach ($aws_sgroups_list_t as $sg)
		        	$aws_sgroups[strtolower($sg->groupName)] = $sg;
		        	
		        unset($aws_sgroups_list_t);
			}
			catch(Exception $e) {
				throw new Exception("GetServerSecurityGroupsList failed: {$e->getMessage()}");
			}
			
			/**** Security group for role builder ****/
			if ($DBServer->status == SERVER_STATUS::TEMPORARY) {
				if (!$aws_sgroups['scalr-rb-system']) {
					try {
						$EC2Client->CreateSecurityGroup('scalr-rb-system', _("Security group for Roles Builder"));
					}
					catch(Exception $e) {
						throw new Exception("GetServerSecurityGroupsList failed: {$e->getMessage()}");
					}					
				
			    	$IpPermissionSet = new IpPermissionSetType();
					
			    	$group_rules = array(
						array('rule' => 'tcp:22:22:0.0.0.0/0'),
						array('rule' => 'tcp:8013:8013:0.0.0.0/0'), // For Scalarizr
						array('rule' => 'udp:8014:8014:0.0.0.0/0'), // For Scalarizr
						array('rule' => 'icmp:-1:-1:0.0.0.0/0')
					); 
					
					foreach ($group_rules as $rule) {
		            	$group_rule = explode(":", $rule["rule"]);
		                $IpPermissionSet->AddItem($group_rule[0], $group_rule[1], $group_rule[2], null, array($group_rule[3]));
		            }
		
		            // Create security group
		            $EC2Client->AuthorizeSecurityGroupIngress(
		            	$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID), 
		            	'scalr-rb-system', 
		            	$IpPermissionSet
		            );
				}

				array_push($retval, 'scalr-rb-system');
				
				return $retval;
			}
			/**********************************/
			
			// Add Role security group
			$role_sec_group = CONFIG::$SECGROUP_PREFIX.$DBServer->GetFarmRoleObject()->GetRoleObject()->name;
			$partent_sec_group = CONFIG::$SECGROUP_PREFIX.$DBServer->GetFarmRoleObject()->GetRoleObject()->getRoleHistory();
			
			$new_role_sec_group = "scalr-role.{$DBServer->farmRoleId}";
			$farm_security_group = "scalr-farm.{$DBServer->farmId}";
			
			
			/****
			 * SCALR IP POOL SECURITY GROUP
			 */
			$scalrSecuritySettings = @parse_ini_file(APPPATH.'/etc/security.ini', true); 
			if (!$aws_sgroups[$scalrSecuritySettings['ec2']['security_group_name']]) {
				try {
					$EC2Client->CreateSecurityGroup($scalrSecuritySettings['ec2']['security_group_name'], "Security rules needed by Scalr");
				}
				catch(Exception $e) {
					throw new Exception("GetServerSecurityGroupsList failed on scalr.ip-pool: {$e->getMessage()}");
				}
				
				$sRules = array(
					array('rule' => 'tcp:8008:8013:0.0.0.0/0'), // For Scalarizr
					array('rule' => 'udp:8014:8014:0.0.0.0/0'), // For Scalarizr
					array('rule' => 'tcp:3306:3306:0.0.0.0/0') // For Replication check
				);
				
				$IpPermissionSet = new IpPermissionSetType();
				
				foreach ($scalrSecuritySettings['ip-pool'] as $name=>$ip) {
					foreach ($sRules as $rule) {
		            	$group_rule = explode(":", $rule["rule"]);
		                $IpPermissionSet->AddItem($group_rule[0], $group_rule[1], $group_rule[2], null, array($ip));
		            }
				}
				
				// Create security group
	            $EC2Client->AuthorizeSecurityGroupIngress(
	            	$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID), 
	            	$scalrSecuritySettings['ec2']['security_group_name'], 
	            	$IpPermissionSet
	            );
			}
			array_push($retval, $scalrSecuritySettings['ec2']['security_group_name']);
			/**********************************************/
			
			if (!$aws_sgroups[strtolower($farm_security_group)])
			{
				try {
					$EC2Client->CreateSecurityGroup($farm_security_group, sprintf("Security group for FarmID #%s",
						$DBServer->farmId
					));
					
					$IpPermissionSet = new IpPermissionSetType();
					
					//Create SG rule to enable communication between instances inside the same farm
					$IpPermissionSet->AddItem('tcp', 0, 65535, array('userId' => $DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID), 'groupName' => $farm_security_group));
					$IpPermissionSet->AddItem('udp', 0, 65535, array('userId' => $DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID), 'groupName' => $farm_security_group));
					
					// Create security group
					$EC2Client->AuthorizeSecurityGroupIngress(
							$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID),
							$farm_security_group,
							$IpPermissionSet
					);
				}
				catch(Exception $e) {
					throw new Exception("GetServerSecurityGroupsList failed: {$e->getMessage()}");
				}
			}
			
			array_push($retval, $farm_security_group);
			
			if ($aws_sgroups[strtolower($role_sec_group)]) {
				// OLD System. scalr.%ROLENAME% . Nothing to do
				array_push($retval, $role_sec_group);
			}
			else 
			{
				if ($aws_sgroups[strtolower($new_role_sec_group)]) {
					// NEW System. scalr-role.%FARM_ROLE_ID% . Nothing to do
					array_push($retval, $new_role_sec_group);
				}
				else 
				{
					try {
						$EC2Client->CreateSecurityGroup($new_role_sec_group, sprintf("Security group for FarmRoleID #%s on FarmID #%s", 
							$DBServer->GetFarmRoleObject()->ID, $DBServer->farmId
						));
					}
					catch(Exception $e) {
						throw new Exception("GetServerSecurityGroupsList failed: {$e->getMessage()}");
					}					
				
			    	$IpPermissionSet = new IpPermissionSetType();
					
			    	$dbRules = $DBServer->GetFarmRoleObject()->GetRoleObject()->getSecurityRules();
			    	$group_rules = array();
			    	//
					// Check parent security group
					//
					if (count($dbRules) == 0) {
						$group_rules = array(
							md5('tcp:22:22:0.0.0.0/0') => array('rule' => 'tcp:22:22:0.0.0.0/0'),
							md5('icmp:-1:-1:0.0.0.0/0') => array('rule' => 'icmp:-1:-1:0.0.0.0/0')
						); 
							
						if ($DBServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
							$rule = 'tcp:3306:3306:0.0.0.0/0';
							$group_rules[md5($rule)] = array('rule' => $rule);
						}
					} else {
						foreach ($dbRules as $rule)
							$group_rules[md5($rule['rule'])] = $rule;
					}
					
					foreach (Scalr_Role_Behavior::getListForFarmRole($DBServer->GetFarmRoleObject()) as $bObj) {
						$bRules = $bObj->getSecurityRules();
						foreach ($bRules as $r) {
							if ($r)
								$group_rules[md5($r)] = array('rule' => $r);
						}
					}
					
		            foreach ($group_rules as $rule) {
		            	$group_rule = explode(":", $rule["rule"]);
		                $IpPermissionSet->AddItem($group_rule[0], $group_rule[1], $group_rule[2], null, array($group_rule[3]));
		            }
		            
		            //Create SG rule to enable communication between instances inside this role
		            $IpPermissionSet->AddItem('tcp', 0, 65535, array('userId' => $DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID), 'groupName' => $new_role_sec_group));
		            $IpPermissionSet->AddItem('udp', 0, 65535, array('userId' => $DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID), 'groupName' => $new_role_sec_group));
		
		            // Create security group
		            $EC2Client->AuthorizeSecurityGroupIngress(
		            	$DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::ACCOUNT_ID), 
		            	$new_role_sec_group, 
		            	$IpPermissionSet
		            );	
		            
		            $DBServer->GetFarmRoleObject()->SetSetting(DBFarmRole::SETTING_AWS_SECURITY_GROUP, $new_role_sec_group);
		            
		            array_push($retval, $new_role_sec_group);
				}
			}
	         
			return $retval;
		}
		
		private function GetServerAvailZone(DBServer $DBServer, $EC2Client, Scalr_Server_LaunchOptions $launchOptions)
		{
			if ($DBServer->status == SERVER_STATUS::TEMPORARY)
				return false;
			
			$server_avail_zone = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE);
			
			if ($DBServer->replaceServerID && !$server_avail_zone) {
				try {
					$rDbServer = DBServer::LoadByID($DBServer->replaceServerID);
					$server_avail_zone = $rDbServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE);
				} catch(Exception $e) {}
			}
			
			$role_avail_zone = $this->db->GetOne("SELECT ec2_avail_zone FROM ec2_ebs WHERE server_index=? AND farm_roleid=?",
        		array($DBServer->index, $DBServer->farmRoleId)
        	);
			
        	if (!$role_avail_zone)
        	{
        		$DBServer->SetProperty("tmp.ec2.avail_zone.algo1", "[S={$server_avail_zone}][R1:{$role_avail_zone}]");
        		
        		if ($server_avail_zone && $server_avail_zone != 'x-scalr-diff' && !stristr($server_avail_zone, "x-scalr-custom"))
					return $server_avail_zone; 
				
        		$role_avail_zone = $DBServer->GetFarmRoleObject()->GetSetting(DBFarmRole::SETTING_AWS_AVAIL_ZONE);
        	}
        		
        	$DBServer->SetProperty("tmp.ec2.avail_zone.algo2", "[S={$server_avail_zone}][R2:{$role_avail_zone}]");
        	
        	if (!$role_avail_zone)
			    return false;
        		
        	if ($role_avail_zone == "x-scalr-diff" || stristr($role_avail_zone, "x-scalr-custom"))
        	{
        		//TODO: Elastic Load Balancer
        		$avail_zones = array();
        		if (stristr($role_avail_zone, "x-scalr-custom")) {
        			$zones = explode("=", $role_avail_zone);
        			foreach (explode(":", $zones[1]) as $zone)
        				if ($zone != "")
        				array_push($avail_zones, $zone);
        				
        		} else {
	        		// Get list of all available zones
	        		$avail_zones_resp = $EC2Client->DescribeAvailabilityZones();
				    foreach ($avail_zones_resp->availabilityZoneInfo->item as $zone)
				    {
				    	$zoneName = (string)$zone->zoneName;
				    	
				    	if (strstr($zone->zoneState,'available')) {
				    		$isUnavailable = $DBServer->GetEnvironmentObject()->getPlatformConfigValue("aws.{$launchOptions->cloudLocation}.{$zoneName}.unavailable", false);
				    		if ($isUnavailable && $isUnavailable+3600 < time()) {
				    			$DBServer->GetEnvironmentObject()->setPlatformConfig(array(
					        		"aws.{$launchOptions->cloudLocation}.{$zoneName}.unavailable" => false
					        	), false);	
					        	$isUnavailable = false;
				    		}
				    		
				    		if (!$isUnavailable)
				    			array_push($avail_zones, $zoneName);
				    	}
				    }
        		}
        		
        		sort($avail_zones);
				$avail_zones = array_reverse($avail_zones);
        		
        		$servers = $DBServer->GetFarmRoleObject()->GetServersByFilter(array("status" => array(
        			SERVER_STATUS::RUNNING, 
        			SERVER_STATUS::INIT, 
        			SERVER_STATUS::PENDING
        		)));
        		$availZoneDistribution = array();
        		foreach ($servers as $cDbServer) {
        			if ($cDbServer->serverId != $DBServer->serverId)
        				$availZoneDistribution[$cDbServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE)]++;
        		}
        		
        		$sCount = 1000000;
        		foreach ($avail_zones as $zone) {
        			if ((int)$availZoneDistribution[$zone] <= $sCount) {
        				$sCount = (int)$availZoneDistribution[$zone];
        				$availZone = $zone;
        			}
        		}
        		
        		$aZones = implode(",", $avail_zones);
        		$dZones = "";
        		foreach ($availZoneDistribution as $zone => $num)
        			$dZones .= "({$zone}:{$num})";
        		
        		$DBServer->SetProperty("tmp.ec2.avail_zone.algo2", "[A:{$aZones}][D:{$dZones}][S:{$availZone}]");
        		
        		/*** OLD Algorithm ***/
        		/*
			    // Get count of curently running instances
	        	$instance_count = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_roleid=? AND status NOT IN (?,?)", 
	        		array($DBServer->farmRoleId, SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::TERMINATED)
	        	);
	        		
	        	// Get zone index.
	        	$zone_index = ($instance_count) % count($avail_zones);
        		
        		$availZone = $avail_zones[$zone_index];
        		*/
        		/***** OLD Algorithm END *****/
        		
        		return $availZone;
        	}
        	else
        		return $role_avail_zone;
		}
		
		public function PutAccessData(DBServer $DBServer, Scalr_Messaging_Msg $message)
		{
			$put = false;
			$put |= $message instanceof Scalr_Messaging_Msg_Rebundle;
			$put |= $message instanceof Scalr_Messaging_Msg_BeforeHostUp;
			$put |= $message instanceof Scalr_Messaging_Msg_HostInitResponse;
			$put |= $message instanceof Scalr_Messaging_Msg_Mysql_PromoteToMaster;
			$put |= $message instanceof Scalr_Messaging_Msg_Mysql_CreateDataBundle;
			$put |= $message instanceof Scalr_Messaging_Msg_Mysql_CreateBackup;
			$put |= $message instanceof Scalr_Messaging_Msg_BeforeHostTerminate;
			
			$put |= $message instanceof Scalr_Messaging_Msg_DbMsr_PromoteToMaster;
			$put |= $message instanceof Scalr_Messaging_Msg_DbMsr_CreateDataBundle;
			$put |= $message instanceof Scalr_Messaging_Msg_DbMsr_CreateBackup;
			$put |= $message instanceof Scalr_Messaging_Msg_DbMsr_NewMasterUp;
			
			
			if ($put) {
				$environment = $DBServer->GetEnvironmentObject();
	        	$accessData = new stdClass();
	        	$accessData->accountId = $environment->getPlatformConfigValue(self::ACCOUNT_ID);
	        	$accessData->keyId = $environment->getPlatformConfigValue(self::ACCESS_KEY);
	        	$accessData->key = $environment->getPlatformConfigValue(self::SECRET_KEY);
	        	$accessData->cert = $environment->getPlatformConfigValue(self::CERTIFICATE);
	        	$accessData->pk = $environment->getPlatformConfigValue(self::PRIVATE_KEY);
	        	
	        	$message->platformAccessData = $accessData;
			}
		}
		
		public function ClearCache ()
		{
			$this->instancesListCache = array();
		}
	}

	
	
?>
