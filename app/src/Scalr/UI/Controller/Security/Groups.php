<?php
use Scalr\Acl\Acl;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupData;
use Scalr\Service\Aws\Ec2\DataType\IpPermissionList;
use Scalr\Service\Aws\Ec2\DataType\IpPermissionData;
use Scalr\Service\Aws\Ec2\DataType\IpRangeList;
use Scalr\Service\Aws\Ec2\DataType\IpRangeData;
use Scalr\Service\Aws\Ec2\DataType\UserIdGroupPairList;
use Scalr\Service\Aws\Ec2\DataType\UserIdGroupPairData;

class Scalr_UI_Controller_Security_Groups extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'securityGroupId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_SECURITY_AWS_SECURITY_GROUPS);
    }

    /**
    * View roles listView with filters
    */
    public function viewAction()
    {
        if (!$this->getParam('platform')) {
            throw new Exception ('Platform should be specified');
        }
        
        $this->response->page('ui/security/groups/view.js', array(
            'locations' => self::loadController('Platforms')->getCloudLocations(array($this->getParam('platform')), false)
        ));
    }

    public function createAction()
    {
        $this->response->page('ui/security/groups/edit.js', array(
            'platform'          => $this->getParam('platform'),
            'cloudLocation'     => $this->getParam('cloudLocation'),
            'cloudLocationName' => $this->getCloudLocationName($this->getParam('platform'), $this->getParam('cloudLocation')),
            'accountId'         => $this->environment->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID)
        ),array('ui/security/groups/sgeditor.js'));
    }

    public function editAction()
    {
        $data = $this->getGroupData($this->getParam('platform'), $this->getParam('cloudLocation'), $this->getParam('securityGroupId'));

        $data['cloudLocationName'] = $this->getCloudLocationName($this->getParam('platform'), $this->getParam('cloudLocation'));
        $data['accountId'] = $this->environment->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID);

        $this->response->page('ui/security/groups/edit.js', $data, array('ui/security/groups/sgeditor.js'));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'rules' => array('type' => 'json'),
            'sgRules' => array('type' => 'json')
        ));

        $securityGroupId = $this->getParam('securityGroupId');
        $securityGroupName = trim($this->getParam('name'));
        $vpcId = $this->getParam('vpcId');
        $vpcId = !empty($vpcId) ? $vpcId : null;

        $newRules = $this->getParam('rules');
        $notValid = array();
        foreach ($newRules as $r) {
            if (! Scalr_Util_Network::isValidCidr($r['cidrIp']))
                $notValid[] = strip_tags($r['cidrIp']);
        }

        if (count($notValid)) {
            throw new Scalr_Exception_Core(sprintf('Not valid CIDR(s): "%s"', join(', ', $notValid)));
        }

        if (!$securityGroupId) {
            $securityGroupId = $this->getCloudInstance($this->getParam('platform'), $this->getParam('cloudLocation'))->ec2->securityGroup->create($securityGroupName, $this->getParam('description'), $vpcId);

            sleep(5);

            $groupData = array(
                'id'            => $securityGroupId,
                'platform'      => $this->getParam('platform'),
                'cloudLocation' => $this->getParam('cloudLocation'),
                'name'          => $securityGroupName,
                'rules'         => array(),
                'sgRules'       => array()
            );
        } else {
            $groupData = $this->getGroupData($this->getParam('platform'), $this->getParam('cloudLocation'), $securityGroupId);
        }

        $this->saveRules($groupData, array('rules' => $this->getParam('rules'), 'sgRules' => $this->getParam('sgRules')));
        
        if ($this->getParam('returnData')) {
            $this->response->data(array('group' => $this->getGroupData($this->getParam('platform'), $this->getParam('cloudLocation'), $securityGroupId)));
        }
        
        $this->response->success("Security group successfully saved");
    }

    public function xRemoveAction()
    {
        $cloudInstance = $this->getCloudInstance($this->getParam('platform'), $this->getParam('cloudLocation'));
        $this->request->defineParams(array(
            'groups' => array('type' => 'json')
        ));

        $cnt = 0;
        foreach ($this->getParam('groups') as $groupId) {
            $cnt++;
            $cloudInstance->ec2->securityGroup->delete($groupId);
        }

        $this->response->success('Selected security group' . ($cnt > 1 ? 's have' : ' has') . ' been successfully removed');
    }

    public function xGetGroupInfoAction()
    {
        $data = array();

        if ($this->getParam('securityGroupId')) {
            $securityGroupId = $this->getParam('securityGroupId');
        } elseif ($this->getParam('securityGroupName')) {
            $securityGroupId = $this->getGroupIdByName($this->getParam('platform'), $this->getParam('cloudLocation'), $this->getParam('securityGroupName'), $this->getParam('vpcId'));
        }
        
        if ($securityGroupId) {
            $data = $this->getGroupData($this->getParam('platform'), $this->getParam('cloudLocation'), $securityGroupId);
            $data['cloudLocationName'] = $this->getCloudLocationName($this->getParam('platform'), $this->getParam('cloudLocation'));
            $data['accountId'] = $this->environment->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID);
        }
        
        $this->response->data($data);
    }

    public function xListGroupsAction()
    {
        $sgFilter = null;

        $this->request->defineParams(array(
            'filters' => array('type' => 'json')
        ));

        $filters = $this->getParam('filters');
        if (!empty($filters['sgIds'])) {
            $sgFilter = is_null($sgFilter) ? array() : $sgFilter;
            $sgFilter[] = array(
                'name' => SecurityGroupFilterNameType::groupId(),
                'value' => $filters['sgIds']
            );
        }

        if (!empty($filters['vpcId'])) {
            $sgFilter = is_null($sgFilter) ? array() : $sgFilter;
            $sgFilter[] = array(
                'name' => SecurityGroupFilterNameType::vpcId(),
                'value' => $filters['vpcId']
            );
        }

        $sgList = $this->getCloudInstance($this->getParam('platform'), $this->getParam('cloudLocation'))->ec2->securityGroup->describe(null, null, $sgFilter);
        $rowz = array();
        /* @var $sg SecurityGroupData */
        foreach ($sgList as $sg) {
            $rowz[] = array(
                'id'          => $sg->groupId,
                'name'        => $sg->groupName,
                'description' => $sg->groupDescription,
                'vpcId'       => $sg->vpcId,
                'owner'       => $sg->ownerId
            );
        }

        $response = $this->buildResponseFromData($rowz, array('id', 'name', 'description', 'vpcId'));

        if (!empty($response['data'])) {
            $cache = array();
            foreach ($response['data'] as &$row) {
                preg_match_all('/^scalr-(role|farm)\.([0-9]+)$/si', $row['name'], $matches);
                if (isset($matches[1][0]) && $matches[1][0] == 'role') {
                    $id = $matches[2][0];
                    try {
                        $dbFarmRole = DBFarmRole::LoadByID($id);
                        $row['farm_id'] = $dbFarmRole->FarmID;
                        $row['farm_roleid'] = $dbFarmRole->ID;

                        if (!isset($cache['farms'][$dbFarmRole->FarmID])) {
                            $cache['farms'][$dbFarmRole->FarmID] = $dbFarmRole->GetFarmObject()->Name;
                        }
                        $row['farm_name'] = $cache['farms'][$dbFarmRole->FarmID];

                        if (!isset($cache['roles'][$dbFarmRole->RoleID])) {
                            $cache['roles'][$dbFarmRole->RoleID] = $dbFarmRole->GetRoleObject()->name;
                        }
                        $row['role_name'] = $cache['roles'][$dbFarmRole->RoleID];
                    } catch (Exception $e) {
                    }
                }

                if (isset($matches[1][0]) && $matches[1][0] == 'farm') {
                    $id = $matches[2][0];

                    try {
                        $dbFarm = DBFarm::LoadByID($id);
                        $row['farm_id'] = $dbFarm->ID;

                        if (!isset($cache['farms'][$dbFarm->ID])) {
                            $cache['farms'][$dbFarm->ID] = $dbFarm->Name;
                        }
                        $row['farm_name'] = $cache['farms'][$dbFarm->ID];

                    } catch (Exception $e) {}
                }
            }
        }

        $this->response->data($response);
    }

    private function getCloudLocationName($platform, $cloudLocation)
    {
        $cloudLocationName = $cloudLocation;
        $locations = PlatformFactory::NewPlatform($platform)->getLocations();
        if (isset($locations[$cloudLocation])) {
            $cloudLocationName = $locations[$cloudLocation];
        }

        return $cloudLocationName;
    }

    private function getGroupData($platform, $cloudLocation, $securityGroupId)
    {
        $rules = array();
        $sgRules = array();

        /* @var $sgInfo SecurityGroupData */
        $sgInfo = $this->getCloudInstance($platform, $cloudLocation)->ec2->securityGroup->describe(null, $securityGroupId)->get(0);

        /* @var $rule IpPermissionData */
        foreach ($sgInfo->ipPermissions as $rule) {
            /* @var $ipRange IpRangeData */
            foreach ($rule->ipRanges as $ipRange) {
                $r = array(
                    'ipProtocol' => $rule->ipProtocol,
                    'fromPort'   => $rule->fromPort,
                    'toPort'     => $rule->toPort,
                );
                $r['cidrIp'] = $ipRange->cidrIp;
                $r['rule'] = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['cidrIp']}";
                $r['id'] = Scalr_Util_CryptoTool::hash($r['rule']);
                $r['comment'] = $this->getRuleComment($sgInfo->groupName, $r['rule']);
                
                if (!isset($rules[$r['id']])) {
                    $rules[$r['id']] = $r;
                }
            }
            /* @var $group UserIdGroupPairData */
            foreach ($rule->groups as $group) {
                $r = array(
                    'ipProtocol' => $rule->ipProtocol,
                    'fromPort'   => $rule->fromPort,
                    'toPort'     => $rule->toPort
                );
                $r['sg'] =  $group->userId . '/' . $group->groupName;
                $r['rule'] = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['sg']}";
                $r['id'] = Scalr_Util_CryptoTool::hash($r['rule']);
                $r['comment'] = $this->getRuleComment($sgInfo->groupName, $r['rule']);

                if (!isset($sgRules[$r['id']])) {
                    $sgRules[$r['id']] = $r;
                }
            }
        }

        return array(
            'platform'        => $platform,
            'cloudLocation'   => $cloudLocation,
            'id'              => $sgInfo->groupId,
            'name'            => $sgInfo->groupName,
            'description'     => $sgInfo->groupDescription,
            'rules'           => $rules,
            'sgRules'         => $sgRules
        );
    }

    private function getRuleComment($groupName, $rule)
    {
        $comment = $this->db->GetOne("SELECT `comment` FROM `comments` WHERE `env_id` = ? AND `rule` = ? AND `sg_name` = ? LIMIT 1", array(
            $this->getEnvironmentId(), $rule, $groupName
        ));
        return !empty($comment) ? $comment : '';
    }

    private function saveRules($groupData, $newRules) {
        $ruleTypes = array('rules', 'sgRules');
        $addRulesSet = array();
        $rmRulesSet = array();
        
        foreach ($ruleTypes as $ruleType) {
            $addRulesSet[$ruleType] = array();
            $rmRulesSet[$ruleType] = array();
            
            foreach ($newRules[$ruleType] as $r) {
                if (!$r['id']) {
                    if ($ruleType == 'rules') {
                        $rule = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['cidrIp']}";
                    } elseif ($ruleType == 'sgRules') {
                        $rule = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['sg']}";
                    }

                    $id = Scalr_Util_CryptoTool::hash($rule);
                    if (!$groupData[$ruleType][$id]) {
                        $addRulesSet[$ruleType][] = $r;
                        if ($r['comment']) {
                            //UNIQUE KEY `main` (`env_id`,`sg_name`,`rule`)
                            $this->db->Execute("
                                INSERT `comments`
                                SET `env_id` = ?,
                                    `sg_name` = ?,
                                    `rule` = ?,
                                    `comment` = ?
                                ON DUPLICATE KEY UPDATE
                                    `comment` = ?
                                ", array(
                                $this->getEnvironmentId(), $groupData['name'], $rule, $r['comment'], $r['comment']
                            ));
                        }
                    }
                }
            }

            foreach ($groupData[$ruleType] as $r) {
                $found = false;
                foreach ($newRules[$ruleType] as $nR) {
                    if ($nR['id'] == $r['id']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $rmRulesSet[$ruleType][] = $r;
                }
            }

        }

        if (count($addRulesSet['rules']) > 0 || count($addRulesSet['sgRules']) > 0) {
            $this->updateRules($groupData['platform'], $groupData['cloudLocation'], $groupData['id'], $addRulesSet, 'add');
        }

        if (count($rmRulesSet['rules']) > 0 || count($rmRulesSet['sgRules']) > 0) {
            $this->updateRules($groupData['platform'], $groupData['cloudLocation'], $groupData['id'], $rmRulesSet, 'remove');
        }

    }

    private function updateRules($platform, $cloudLocation, $securityGroupId, $rules, $method)
    {
        $cloudInstance = $this->getCloudInstance($platform, $cloudLocation);

        $ipPermissionList = new IpPermissionList();
        foreach ($rules['rules'] as $rule) {
            $ipPermissionList->append(new IpPermissionData(
                $rule['ipProtocol'],
                $rule['fromPort'],
                $rule['toPort'],
                new IpRangeList(new IpRangeData($rule['cidrIp'])),
                null
            ));
        }
        foreach ($rules['sgRules'] as $rule) {
            $chunks = explode("/", $rule['sg']);
            $userId = $chunks[0];
            $name = $chunks[1];
            $ipPermissionList->append(new IpPermissionData(
                $rule['ipProtocol'],
                $rule['fromPort'],
                $rule['toPort'],
                null,
                new UserIdGroupPairList(new UserIdGroupPairData($userId, null, $name))
            ));
        }
        if ($method == 'add') {
            $cloudInstance->ec2->securityGroup->authorizeIngress($ipPermissionList, $securityGroupId);
        } else {
            $cloudInstance->ec2->securityGroup->revokeIngress($ipPermissionList, $securityGroupId);
        }
    }

    private function getGroupIdByName($platform, $cloudLocation, $securityGroupName, $vpcId = null)
    {
        $result = null;
        
        $filter = array(
            array(
                'name'  => SecurityGroupFilterNameType::groupName(),
                'value' => $securityGroupName
            )
        );
        if ($vpcId) {
            $filter[] = array(
                'name'  => SecurityGroupFilterNameType::vpcId(),
                'value' => $vpcId
            );
        }
        
        /* @var $sgInfo SecurityGroupData */
        $list = $this->getCloudInstance($platform, $cloudLocation)->ec2->securityGroup->describe(null, null, $filter);
        
        if (count($list) > 0) {
            foreach ($list as $v) {
                if (!empty($vpcId) && $v->vpcId == $vpcId || empty($vpcId) && empty($v->vpcId)) {
                    $result = $v->groupId;
                    break;
                }
            }
        }

        return $result;
    }

    private function getCloudInstance($platform, $cloudLocation)
    {
        if ($platform == 'ec2') {
            $method = 'aws';
        } elseif ($platform == 'eucalyptus') {
            $method = 'eucalyptus';
        } else {
            throw new Exception ('Platform is not supported');
        }

        return $this->getEnvironment()->$method($cloudLocation);
    }
}
