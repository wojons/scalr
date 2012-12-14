<?php
class Scalr_UI_Controller_Security_Groups extends Scalr_UI_Controller
{
	const CALL_PARAM_NAME = 'securityGroupId';

	public static function getPermissionDefinitions()
	{
		return array();
	}

	/**
	* View roles listView with filters
	*/
	public function viewAction()
	{		
		if (!$this->getParam('platform'))
			throw new Exception ('Platform should be specified');
		
		$this->response->page('ui/security/groups/view.js', array(
			'locations' => self::loadController('Platforms')->getCloudLocations(array($this->getParam('platform')), false)
		));
	}
	
	public function xSaveAction()
	{
		$this->request->defineParams(array(
			'rules' => array('type' => 'json'),
			'sgRules' => array('type' => 'json')
		));
		
		if ($this->getParam('farmRoleId'))
			$securityGroupName = "scalr-role.{$this->getParam('farmRoleId')}";
		else
			$securityGroupId = $this->getParam('securityGroupId');
		
		$info = $this->getRules($securityGroupId, $securityGroupName);
		$securityGroupName = $info['name'];
		$securityGroupId = $info['id'];
		
		$newRules = $this->getParam('rules');
		foreach ($newRules as $r) {
			if (!$r['id']) {
				$rule = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['cidrIp']}";
				$id = md5($rule);
				if (!$info['rules'][$id]) {
					$addRulesSet[] = $r;
					if ($r['comment']) {
						$this->db->Execute("REPLACE INTO `comments` SET `env_id` = ?, `comment` = ?, `sg_name` = ?, `rule` = ?", array(
							$this->getEnvironmentId(), $r['comment'], $securityGroupName, $rule
						));
					}
				}
			}
		}
		
		$sgNewRules = $this->getParam('sgRules');
		foreach ($sgNewRules as $r) {
			if (!$r['id']) {
				$rule = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['sg']}";
				$id = md5($rule);
				if (!$info['sgRules'][$id]) {
					$addSgRulesSet[] = $r;
					if ($r['comment']) {
						$this->db->Execute("REPLACE INTO `comments` SET `env_id` = ?, `comment` = ?, `sg_name` = ?, `rule` = ?", array(
								$this->getEnvironmentId(), $r['comment'], $securityGroupName, $rule
						));
					}
				}
			}
		}
		
		foreach ($info['rules'] as $r) {
			$found = false;
			foreach ($newRules as $nR) {
				if ($nR['id'] == $r['id'])
					$found = true;
			}
			
			if (!$found)
				$remRulesSet[] = $r;
		}
		
		foreach ($info['sgRules'] as $r) {
			$found = false;
			foreach ($sgNewRules as $nR) {
				if ($nR['id'] == $r['id'])
					$found = true;
			}
				
			if (!$found)
				$remSgRulesSet[] = $r;
		}
		
		if (count($addRulesSet) > 0 || count($addSgRulesSet) > 0)
			$this->updateRules(array('ip' => $addRulesSet, 'sg' => $addSgRulesSet), 'add', $securityGroupId);
		
		if (count($remRulesSet) > 0 || count($remSgRulesSet) > 0)
			$this->updateRules(array('ip' => $remRulesSet, 'sg' => $remSgRulesSet), 'remove', $securityGroupId);
		
		$this->response->success("Security group successfully saved");
	}
	
	public function editAction()
	{		
		if ($this->getParam('farmRoleId'))
			$securityGroupName = "scalr-role.{$this->getParam('farmRoleId')}";
		else
			$securityGroupId = $this->getParam('securityGroupId');
			
		$this->request->setParams(array('securityGroupId' => $securityGroupId));
		
		$info = $this->getRules($securityGroupId, $securityGroupName);
		$securityGroupName = $info['name'];
		foreach ($info['rules'] as &$rule) {
			$rule['comment'] = $this->db->GetOne("SELECT `comment` FROM `comments` WHERE `env_id` = ? AND `rule` = ? AND `sg_name` = ?", array(
				$this->getEnvironmentId(), $rule['rule'], $securityGroupName
			));
			if (!$rule['comment'])
				$rule['comment'] = "";
		}
		
		foreach ($info['sgRules'] as &$rule) {
			$rule['comment'] = $this->db->GetOne("SELECT `comment` FROM `comments` WHERE `env_id` = ? AND `rule` = ? AND `sg_name` = ?", array(
					$this->getEnvironmentId(), $rule['rule'], $securityGroupName
			));
			if (!$rule['comment'])
				$rule['comment'] = "";
		}
		
		$this->response->page('ui/security/groups/edit.js', array(
			'securityGroupId' => $securityGroupId,
			'rules' => $info['rules'],
			'sgRules' => $info['sgRules'],
			'accountId' => $this->environment->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID)
		));
	}
	
	public function xRemoveAction()
	{
		$this->request->defineParams(array(
			'groups' => array('type' => 'json')
		));

		$platformClient = $this->getPlatformClient();
		
		foreach ($this->getParam('groups') as $groupId) {
			try {
				//TODO: Multiplatform
				$platformClient->DeleteSecurityGroup(null, $groupId);
			} catch (Exception $e){}
		}

		$this->response->success('Selected security groups successfully removed');
	}
	
	public function xListGroupsAction()
	{
		if (!$this->getParam('platform'))
			throw new Exception ('Platform should be specified');
		
		switch ($this->getParam('platform')) {
			case SERVER_PLATFORMS::EC2:
				$platformClient = $this->getPlatformClient();
				
				$aws_response = $platformClient->DescribeSecurityGroups();
				
				$rows = $aws_response->securityGroupInfo->item;
				foreach ($rows as $row)
				{					
					// Show only scalr security groups
					if (stristr($row->groupName, CONFIG::$SECGROUP_PREFIX) || stristr($row->groupName, "scalr-farm.") || stristr($row->groupName, "scalr-role.") || $this->getParam('showAll'))
						$rowz[] = array('id' => $row->groupId, 'name' => $row->groupName, 'description' => $row->groupDescription);
				}
				
				break;
		}
		
		foreach ($rowz as &$row) {
			preg_match_all("/^scalr-(role|farm)\.([0-9]+)$/si", $row['name'], $matches);
			if ($matches[1][0] == 'role') {
				$id = $matches[2][0];
				try {
					$dbFarmRole = DBFarmRole::LoadByID($id);
					$row['farm_id'] = $dbFarmRole->FarmID;
					$row['farm_roleid'] = $dbFarmRole->ID;
					
					if (!$cache['farms'][$dbFarmRole->FarmID])
						$cache['farms'][$dbFarmRole->FarmID] = $dbFarmRole->GetFarmObject()->Name;
					$row['farm_name'] = $cache['farms'][$dbFarmRole->FarmID];
					
					if (!$cache['roles'][$dbFarmRole->RoleID])
						$cache['roles'][$dbFarmRole->RoleID] = $dbFarmRole->GetRoleObject()->name;
					$row['role_name'] = $cache['roles'][$dbFarmRole->RoleID];
				} catch (Exception $e) {}
			}
			
			if ($matches[1][0] == 'farm') {
				$id = $matches[2][0];
				
				try {
					$dbFarm = DBFarm::LoadByID($id);
					$row['farm_id'] = $dbFarm->ID;
					
					if (!$cache['farms'][$dbFarm->ID])
						$cache['farms'][$dbFarm->ID] = $dbFarm->Name;
					$row['farm_name'] = $cache['farms'][$dbFarm->ID];
					
				} catch (Exception $e) {}
			}
		}
		
		$response = $this->buildResponseFromData($rowz, array('name', 'description'));

		$this->response->data($response);
	}
	
	private function getRules($securityGroupId = null, $securityGroupName = null)
	{
		$platformClient = $this->getPlatformClient();
		$rules = array();
		$sgRules = array();
		switch ($this->getParam('platform')) {
			case SERVER_PLATFORMS::EC2:
				$sgInfo = $platformClient->DescribeSecurityGroups($securityGroupName, $securityGroupId);
				$sgInfo = $sgInfo->securityGroupInfo->item;
				if (!is_array($sgInfo->ipPermissions->item))
					$sgInfo->ipPermissions->item = array($sgInfo->ipPermissions->item);
				
				$ipPermissions = $sgInfo->ipPermissions;
				foreach ($ipPermissions->item as $rule) {
					if (!is_array($rule->ipRanges->item))
						$rule->ipRanges->item = array($rule->ipRanges->item);
					
					if (!is_array($rule->groups->item))
						$rule->groups->item = array($rule->groups->item);
					
					foreach ($rule->ipRanges->item as $ipRange) {
						if ($ipRange) {
							$r = array(
								'ipProtocol' => $rule->ipProtocol,
								'fromPort'	=> $rule->fromPort,
								'toPort' => $rule->toPort
							);
							
							$r['cidrIp'] = $ipRange->cidrIp;
							$r['rule'] = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['cidrIp']}";
							$r['id'] = md5($r['rule']);
							
							if (!$rules[$r['id']]) {
								$rules[$r['id']] = $r;
							}
						}
					}
					
					foreach ($rule->groups->item as $group) {
						if ($group) {
							$r = array(
									'ipProtocol' => $rule->ipProtocol,
									'fromPort'	=> $rule->fromPort,
									'toPort' => $rule->toPort
							);
								
							$r['sg'] = "{$group->userId}/{$group->groupName}";
							$r['rule'] = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['sg']}";
							$r['id'] = md5($r['rule']);
								
							if (!$sgRules[$r['id']]) {
								$sgRules[$r['id']] = $r;
							}
						}
					}
				}		
			break;
		}
		
		return array("id" => $sgInfo->groupId, "name" => $sgInfo->groupName, "rules" => $rules, "sgRules" => $sgRules);
	}
	
	private function updateRules(array $rules, $method, $securityGroupId)
	{
		$platformClient = $this->getPlatformClient();
		
		switch ($this->getParam('platform'))
		{
			case SERVER_PLATFORMS::EC2:
				
				$ipPermissionSet = new IpPermissionSetType();
				foreach ($rules['ip'] as $rule) {
					$ipPermissionSet->AddItem(
						$rule['ipProtocol'], 
						$rule['fromPort'], 
						$rule['toPort'], 
						null, 
						array($rule['cidrIp'])
					);
				}
				
				foreach ($rules['sg'] as $rule) {
					
					$chunks = explode("/", $rule['sg']);
					$userId = $chunks[0];
					$name = $chunks[1];
					
					$ipPermissionSet->AddItem(
							$rule['ipProtocol'],
							$rule['fromPort'],
							$rule['toPort'],
							array('userId' => $userId, 'groupName' => $name),
							null
					);
				}
				
				$accountId = $this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID);
				
				if ($method == 'add')
					$platformClient->AuthorizeSecurityGroupIngress($accountId, null, $ipPermissionSet, $securityGroupId);
				else
					$platformClient->RevokeSecurityGroupIngress($accountId, null, $ipPermissionSet, $securityGroupId);
				
				break;
		}
	}
	
	private function getPlatformClient()
	{
		if (!$this->getParam('platform'))
			throw new Exception ('Platform should be specified');
		
		switch ($this->getParam('platform')) {
			case SERVER_PLATFORMS::EC2:
				return Scalr_Service_Cloud_Aws::newEc2(
					$this->getParam('cloudLocation'),
					$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY),
					$this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE)
				);
			break;
			
			default:
				throw new Exception("Platfrom not suppored");
				break;
		}
	}
}
