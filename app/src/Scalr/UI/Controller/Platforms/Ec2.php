<?php

class Scalr_UI_Controller_Platforms_Ec2 extends Scalr_UI_Controller
{
	public function xGetAvailZonesAction()
	{
		$amazonEC2Client = Scalr_Service_Cloud_Aws::newEc2(
			$this->getParam('cloudLocation'),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE)
		);

		// Get Avail zones
		$response = $amazonEC2Client->DescribeAvailabilityZones();
		
		if ($response->availabilityZoneInfo->item instanceOf stdClass)
			$response->availabilityZoneInfo->item = array($response->availabilityZoneInfo->item);

		$data = array();
		foreach ($response->availabilityZoneInfo->item as $zone) {
			$data[] = array(
				'id' => (string)$zone->zoneName,
				'name' => (string)$zone->zoneName,
				'state' => (string)$zone->zoneState
			);
		}
		
		/*
		if ($this->getParam('roleId')) {
			$dbRole = DBRole::loadById($this->getParam('roleId'));
			$locations = $dbRole->getCloudLocations($this->getParam('platform'));
			$data['locations'] = $locations;
		}
		*/

		$this->response->data(array('data' => $data));
	}

	public function xGetFarmRoleElasicIpsAction()
	{
		$amazonEC2Client = Scalr_Service_Cloud_Aws::newEc2(
			$this->getParam('cloudLocation'),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE)
		);

		$map = array();
		if ($this->getParam('farmRoleId')) {
			$dbFarmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
			$this->user->getPermissions()->validate($dbFarmRole);

			$maxInstances = $dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MAX_INSTANCES);
			for ($i = 1; $i <= $maxInstances; $i++) {
				$map[] = array('serverIndex' => $i);
			}

			$servers = $dbFarmRole->GetServersByFilter();
			for ($i = 0; $i < count($servers); $i++) {
				if ($servers[$i]->status != SERVER_STATUS::TERMINATED && $servers[$i]->index) {
					$map[$servers[$i]->index - 1]['serverIndex'] = $servers[$i]->index;
					$map[$servers[$i]->index - 1]['serverId'] = $servers[$i]->serverId;
					$map[$servers[$i]->index - 1]['remoteIp'] = $servers[$i]->remoteIp;
					$map[$servers[$i]->index - 1]['instanceId'] = $servers[$i]->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
				}
			}

			$ips = $this->db->GetAll('SELECT ipaddress, instance_index FROM elastic_ips WHERE farm_roleid = ?', array($dbFarmRole->ID));
			for ($i = 0; $i < count($ips); $i++) {
				$map[$ips[$i]['instance_index'] - 1]['elasticIp'] = $ips[$i]['ipaddress'];
			}
		}

		$response = $amazonEC2Client->DescribeAddresses();
		
		if ($response->addressesSet->item instanceof stdClass)
			$response->addressesSet->item = array($response->addressesSet->item);

		$ips = array();
		foreach($response->addressesSet->item as $ip) {
			$itm = array(
				'ipAddress' => $ip->publicIp,
				'instanceId' => $ip->instanceId
			);
			
			$info = $this->db->GetRow("SELECT * FROM elastic_ips WHERE ipaddress = ?", array($itm['ipAddress']));
			if ($info) {
				try {
					if ($info['server_id'] && $itm['instanceId']) {
						$dbServer = DBServer::LoadByID($info['server_id']);
						if ($dbServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID) != $itm['instanceId']) {
							for ($i = 0; $i < count($map); $i++) {
								if ($map[$i]['elasticIp'] == $itm['ipAddress'])
									$map[$i]['warningInstanceIdDoesntMatch'] = true;
							}
						}
					}

					$farmRole = DBFarmRole::LoadByID($info['farm_roleid']);
					$this->user->getPermissions()->validate($farmRole);

					$itm['roleName'] = $farmRole->GetRoleObject()->name;
					$itm['farmName'] = $farmRole->GetFarmObject()->Name;
					$itm['serverIndex'] = $info['instance_index'];
				} catch (Exception $e) {}
			}
			$ips[] = $itm;
		}
		
		$this->response->data(array('data' => array(
			'map' => $map,
			'ips' => $ips
		)));
	}

	public function xGetFarmRoleEBSSettingsAction()
	{
		$amazonEC2Client = Scalr_Service_Cloud_Aws::newEc2(
			$this->getParam('cloudLocation'),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE)
		);

		$response = $amazonEC2Client->DescribeSnapshots();
		if ($response->snapshotSet->item instanceOf stdClass)
			$response->snapshotSet->item = array($response->snapshotSet->item);

		$data = array();
		
		// Snapshots list
		foreach ($response->snapshotSet->item as $pk => $pv) {
			if ($pv->ownerId != $this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID))
				continue;

			if ($pv->status == 'completed')
				$data[] = array(
					'snapid' 	=> (string)$pv->snapshotId,
					'createdat'	=> Scalr_Util_DateTime::convertTz($pv->startTime),
					'size'		=> (string)$pv->volumeSize
				);
		}
		
		//Current EBS mapping
		
		//Current EBS usage
		$usage = array();
		$ebs = $this->db->GetAll("SELECT * FROM ec2_ebs WHERE farm_roleid = ? AND ismanual = '0' ORDER BY id ASC", array($this->getParam('farmRoleId')));
		foreach ($ebs as $volume) {
			
			if (!$usage[$volume['server_index']]) {
				$usage[$volume['server_index']] = array(
					'volumes' => array(),
					'server' => $this->db->GetOne("SELECT server_id FROM servers WHERE farm_roleid = ? AND `index` = ?", array(
						$this->getParam('farmRoleId'),
						$volume['server_index']
					))
				);
			}
			
			$usage[$volume['server_index']]['volumes'][] = array(
				'volume_id' => $volume['volume_id']
			);
		}
		

		$this->response->data(array('data' => array(
			'usage' => $usage
		)));
	}
	
	public function xGetSnapshotsAction()
	{
		$amazonEC2Client = Scalr_Service_Cloud_Aws::newEc2(
			$this->getParam('cloudLocation'),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY),
			$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE)
		);

		$response = $amazonEC2Client->DescribeSnapshots();
		if ($response->snapshotSet->item instanceOf stdClass)
			$response->snapshotSet->item = array($response->snapshotSet->item);

		$data = array();
		foreach ($response->snapshotSet->item as $pk => $pv) {
			if ($pv->ownerId != $this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID))
				continue;

			if ($pv->status == 'completed')
				$data[] = array(
					'snapid' 	=> (string)$pv->snapshotId,
					'createdat'	=> Scalr_Util_DateTime::convertTz($pv->startTime),
					'size'		=> (string)$pv->volumeSize
				);
		}

		$this->response->data(array('data' => $data));
	}
}
