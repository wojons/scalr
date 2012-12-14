<?php

class Scalr_UI_Controller_Tools_Aws_Vpc extends Scalr_UI_Controller
{
	public function hasAccess()
	{
		$enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();
		if (!in_array(SERVER_PLATFORMS::EC2, $enabledPlatforms))
			throw new Exception("You need to enable EC2 platform for current environment");

		return true;
	}
	
	public function xListViewSubnetsAction()
	{
		$amazonVPCClient = Scalr_Service_Cloud_Aws::newVpc(
			$this->getParam('cloudLocation'),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE)
		);
		
		$subnets = $amazonVPCClient->DescribeSubnets();
		$subnets = $subnets->subnetSet->item;
		if ($subnets instanceof stdClass) $subnets = array($subnets);
		$retval = array();
		foreach ($subnets as $subnet) {
			$retval[] = array(
				'id' => $subnet->subnetId,
				'description' => "{$subnet->subnetId} ({$subnet->cidrBlock} in {$subnet->availabilityZone})"
			);
		}
		
		$this->response->data(array('success' => true, 'data' => $retval));
	}
}
