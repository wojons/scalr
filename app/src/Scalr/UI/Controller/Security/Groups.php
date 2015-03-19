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
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\Aws\Client\QueryClientException;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\Services\Network\Type\NetworkExtension;
use Scalr\Service\Aws\Rds\DataType\DBSecurityGroupData;
use Scalr\Util\CryptoTool;

class Scalr_UI_Controller_Security_Groups extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'securityGroupId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_SECURITY_SECURITY_GROUPS);
    }

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
        $governance = new Scalr_Governance($this->getEnvironmentId());

        $this->response->page('ui/security/groups/edit.js', array(
            'platform'          => $this->getParam('platform'),
            'cloudLocation'     => $this->getParam('cloudLocation'),
            'cloudLocationName' => $this->getCloudLocationName($this->getParam('platform'), $this->getParam('cloudLocation')),
            'accountId'         => $this->environment->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID),
            'vpcLimits'         => $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, null),
            'remoteAddress'     => $this->request->getRemoteAddr(true)
        ),array('ui/security/groups/sgeditor.js'));
    }

    public function editAction()
    {
        $data = $this->getGroup($this->getParam('platform'), $this->getParam('cloudLocation'), $this->getParam('securityGroupId'));

        $data['cloudLocationName'] = $this->getCloudLocationName($this->getParam('platform'), $this->getParam('cloudLocation'));
        $data['accountId'] = $this->environment->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID);
        $data['remoteAddress'] = $this->request->getRemoteAddr(true);

        $this->response->page('ui/security/groups/edit.js', $data, array('ui/security/groups/sgeditor.js'));
    }

    public function xSaveAction()
    {
        $platform = $this->getParam('platform');
        $cloudLocation = $this->getParam('cloudLocation');

        $this->request->defineParams(array(
            'rules' => array('type' => 'json'),
            'sgRules' => array('type' => 'json')
        ));

        $groupData = array(
            'id'            => $this->getParam('securityGroupId'),
            'name'          => trim($this->getParam('name')),
            'description'   => trim($this->getParam('description')),
            'rules'         => $this->getParam('rules'),
            'sgRules'       => $this->getParam('sgRules'),
            'vpcId'         => $this->getParam('vpcId') ? $this->getParam('vpcId') : null
        );

        $invalidRules = array();
        foreach ($groupData['rules'] as $r) {
            if (! Scalr_Util_Network::isValidCidr($r['cidrIp'])) {
                $invalidRules[] = $this->request->stripValue($r['cidrIp']);
            }
        }

        if (count($invalidRules)) {
            throw new Scalr_Exception_Core(sprintf('Not valid CIDR(s): "%s"', join(', ', $invalidRules)));
        }

        if (!$groupData['id']) {
            $groupData['id'] = $this->callPlatformMethod($platform, 'createGroup', array($platform, $cloudLocation, $groupData));
            sleep(2);
        }

        $warning = null;

        if ($platform !== 'rds') {
            $currentGroupData = $this->getGroup($platform, $cloudLocation, $groupData['id']);

            try {
                $this->saveGroupRules($platform, $cloudLocation, $currentGroupData, array('rules' => $groupData['rules'], 'sgRules' => $groupData['sgRules']));
            } catch (QueryClientException $e) {
                $warning = $e->getErrorData()->getMessage();
            }
        }

        if ($this->getParam('returnData') && $groupData['id']) {
            $this->response->data(array('group' => $this->getGroup($this->getParam('platform'), $this->getParam('cloudLocation'), $groupData['id'])));
        }

        if ($warning) {
            $this->response->warning('Security group saved with warning: ' . $warning);
        } else {
            $this->response->success("Security group successfully saved");
        }
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'groups' => array('type' => 'json')
        ));

        $cnt = 0;
        foreach ($this->getParam('groups') as $securityGroupId) {
            if (empty($securityGroupId)) continue;
            $cnt++;
            $this->deleteGroup($this->getParam('platform'), $this->getParam('cloudLocation'), $securityGroupId);
        }

        $this->response->success('Selected security group' . ($cnt > 1 ? 's have' : ' has') . ' been successfully removed');
    }

    public function xGetGroupInfoAction()
    {
        $data = array();

        $vpcId = $this->environment->getPlatformConfigValue(Ec2PlatformModule::DEFAULT_VPC_ID . "." . $this->getParam('cloudLocation'));
        if ($this->getParam('vpcId') != '')
            $vpcId = $this->getParam('vpcId');

        if ($this->getParam('securityGroupId')) {
            $securityGroupId = $this->getParam('securityGroupId');
        } elseif ($this->getParam('securityGroupName')) {
            $securityGroupId = $this->callPlatformMethod($this->getParam('platform'), 'getGroupIdByName',  array($this->getParam('platform'), $this->getParam('cloudLocation'), $this->getParam('securityGroupName'), $vpcId));
        }

        if ($securityGroupId) {
            $data = $this->getGroup($this->getParam('platform'), $this->getParam('cloudLocation'), $securityGroupId);
            $data['cloudLocationName'] = $this->getCloudLocationName($this->getParam('platform'), $this->getParam('cloudLocation'));
            $data['accountId'] = $this->environment->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID);
            $data['remoteAddress'] = $this->request->getRemoteAddr();
        }

        $this->response->data($data);
    }

    public function xListGroupsAction()
    {
        $this->request->defineParams(array(
            'filters' => array('type' => 'json')
        ));

        $result = $this->listGroups($this->getParam('platform'), $this->getParam('cloudLocation'), $this->getParam('filters'));
        $this->response->data($result);
    }

    private function getCloudLocationName($platform, $cloudLocation)
    {
        $cloudLocationName = $cloudLocation;
        $locations = PlatformFactory::NewPlatform($platform)->getLocations($this->environment);
        if (isset($locations[$cloudLocation])) {
            $cloudLocationName = $locations[$cloudLocation];
        }

        return $cloudLocationName;
    }

    private function getGroup($platform, $cloudLocation, $securityGroupId)
    {
        if (!$securityGroupId) throw new Exception ('Security group ID is required');
        return $this->callPlatformMethod($platform, __FUNCTION__, func_get_args());
    }

    private function getGroupEc2($platform, $cloudLocation, $securityGroupId)
    {
        $rules = array();
        $sgRules = array();

        /* @var $sgInfo SecurityGroupData */
        $sgInfo = $this->getPlatformService($platform, $cloudLocation)->describe(null, $securityGroupId)->get(0);

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
                $r['id'] = CryptoTool::hash($r['rule']);
                $r['comment'] = $this->getRuleComment($platform, $cloudLocation, $sgInfo->vpcId, $sgInfo->groupName, $r['rule']);

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
                $name = $group->groupName ? $group->groupName : $group->groupId;

                $r['sg'] =  $group->userId . '/' . $name;
                $r['rule'] = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['sg']}";
                $r['id'] = CryptoTool::hash($r['rule']);
                $r['comment'] = $this->getRuleComment($platform, $cloudLocation, $sgInfo->vpcId, $sgInfo->groupName, $r['rule']);

                if (!isset($sgRules[$r['id']])) {
                    $sgRules[$r['id']] = $r;
                }
            }
        }

        return array(
            'platform'        => $platform,
            'cloudLocation'   => $cloudLocation,
            'id'              => $sgInfo->groupId,
            'vpcId'           => $sgInfo->vpcId,
            'name'            => $sgInfo->groupName,
            'description'     => $sgInfo->groupDescription,
            'rules'           => $rules,
            'sgRules'         => $sgRules,
            'raw_data'        => $sgInfo->toArray()
        );
    }

    private function getGroupRds($platform, $cloudLocation, $securityGroupId)
    {
        /* @var $sgInfo DBSecurityGroupData */
        $sgInfo = $this->getEnvironment()->aws($cloudLocation)->rds->dbSecurityGroup->describe($securityGroupId)->get(0);
        $result = [];

        if ($sgInfo instanceof DBSecurityGroupData) {
            $result = [
                'platform'        => $platform,
                'cloudLocation'   => $cloudLocation,
                'name'            => $sgInfo->dBSecurityGroupName,
                'description'     => $sgInfo->dBSecurityGroupDescription,
                'raw_data'        => $sgInfo->toArray()
            ];
        }

        return $result;
    }

    private function getGroupOpenstack($platform, $cloudLocation, $securityGroupId)
    {
        $rules = array();
        $sgRules = array();

        $openstack = $this->getPlatformService($platform, $cloudLocation);
        /* @var $openstack \Scalr\Service\OpenStack\OpenStack */

        $sgInfo = $openstack->listSecurityGroups($securityGroupId);
        $allGroups = $this->listGroupsOpenstack($platform, $cloudLocation, array());

        $list = array();

        foreach ($allGroups as $s) {
            $list[$s['id']] = $s['name'];
        }

        foreach ($sgInfo->security_group_rules as $rule) {
            $r = array(
                'id'         => $rule->id,
                'ipProtocol' => $rule->protocol,
                'fromPort'   => $rule->port_range_min,
                'toPort'     => $rule->port_range_max
            );

            if (property_exists($rule, 'remote_ip_prefix') || property_exists($rule, 'remote_group_id')) {
                $key = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:" . (!empty($rule->remote_ip_prefix) ? $rule->remote_ip_prefix : $rule->remote_group_id);

            } else {
                $key = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:" . ($rule->ip_range->cidr ? $rule->ip_range->cidr : $rule->group->name);
            }

            if (property_exists($rule, 'direction') && property_exists($rule, 'ethertype')) {
                $r['direction'] = $rule->direction;
                $r['type'] = $rule->ethertype;
                $key .= ":{$r['direction']}:{$r['type']}";
            }

            $r['comment'] = $this->getRuleComment($platform, $cloudLocation, '', $sgInfo->name, $key);

            if (property_exists($rule, 'remote_ip_prefix')) {
                if ($rule->remote_ip_prefix) {
                    $r['cidrIp'] = $rule->remote_ip_prefix;
                    $rules[$r['id']] = $r;
                } else {
                    $r['sg'] = $list[$rule->remote_group_id];
                    $sgRules[$r['id']] = $r;
                }
                $advanced = true;
            } else if (property_exists($rule, 'ip_range')) {
                if ($rule->ip_range->cidr) {
                    $r['cidrIp'] = $rule->ip_range->cidr;
                    $rules[$r['id']] = $r;
                } else {
                    $r['sg'] = $rule->group->name;
                    $sgRules[$r['id']] = $r;
                }
                $advanced = false;
            }
        }

        return array(
            'advanced'        => $advanced,
            'platform'        => $platform,
            'cloudLocation'   => $cloudLocation,
            'id'              => $sgInfo->id,
            'name'            => $sgInfo->name,
            'description'     => $sgInfo->description,
            'rules'           => $rules,
            'sgRules'         => $sgRules
        );
    }

    private function getGroupCloudstack($platform, $cloudLocation, $securityGroupId)
    {
        $rules = array();

        /* @var $sgInfo SecurityGroupData */
        $sgInfo = $this->getPlatformService($platform, $cloudLocation)->describe(array('id' => $securityGroupId))[0];

        if ($sgInfo->ingressrule) {
            foreach ($sgInfo->ingressrule as $rule) {
                $r = array(
                    'id'         => $rule->ruleid,
                    'ipProtocol' => $rule->protocol,
                    'fromPort'   => $rule->startport,
                    'toPort'     => $rule->endport,
                    'cidrIp'     => $rule->cidr
                );

                $key = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['cidrIp']}";
                $r['comment'] = $this->getRuleComment($platform, '', '', $sgInfo->name, $key);
                $rules[$r['id']] = $r;
            }
        }

        return array(
            'platform'        => $platform,
            'id'              => $sgInfo->id,
            'name'            => $sgInfo->name,
            'description'     => $sgInfo->description,
            'rules'           => $rules,
            'sgRules'         => array()
        );
    }


    private function getRuleComment($platform, $cloudLocation, $vpcId, $groupName, $rule)
    {
        if ($this->db->GetRow("SHOW TABLES LIKE 'security_group_rules_comments'")) {
            $comment = $this->db->GetOne("
                SELECT `comment`
                FROM `security_group_rules_comments`
                WHERE `env_id` = ?
                AND `platform` = ?
                AND `cloud_location` = ?
                AND `vpc_id` = ?
                AND `group_name` = ?
                AND `rule` = ?
                LIMIT 1",
                array(
                    $this->getEnvironmentId(),
                    $platform,
                    $cloudLocation,
                    $vpcId ? $vpcId : '',
                    $groupName,
                    $rule
                )
            );
        } else {
            $comment = $this->db->GetOne("SELECT `comment` FROM `comments` WHERE `env_id` = ? AND `rule` = ? AND `sg_name` = ? LIMIT 1", array(
                $this->getEnvironmentId(), $rule, $groupName
            ));
        }
        return !empty($comment) ? $comment : '';
    }

    private function saveGroupRules($platform, $cloudLocation, $groupData, $newRules)
    {
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

                    $id = CryptoTool::hash($rule);
                    if (!$groupData[$ruleType][$id]) {
                        $addRulesSet[$ruleType][] = $r;
                        if ($r['comment']) {
                            if ($this->db->GetRow("SHOW TABLES LIKE 'security_group_rules_comments'")) {
                                $this->db->Execute("
                                    INSERT `security_group_rules_comments`
                                    SET `env_id` = ?,
                                        `platform` = ?,
                                        `cloud_location` = ?,
                                        `vpc_id` = ?,
                                        `group_name` = ?,
                                        `rule` = ?,
                                        `comment` = ?
                                    ON DUPLICATE KEY UPDATE
                                        `comment` = ?
                                    ", array(
                                        $this->getEnvironmentId(),
                                        $platform,
                                        PlatformFactory::isCloudstack($platform) ? '' : $cloudLocation,
                                        $groupData['vpcId'] ? $groupData['vpcId'] : '',
                                        $groupData['name'],
                                        $rule,
                                        $r['comment'],
                                        $r['comment']
                                ));
                            } else {
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
            $this->callPlatformMethod($platform, __FUNCTION__, array($platform, $cloudLocation, $groupData['id'], $addRulesSet, 'add'));
        }

        if (count($rmRulesSet['rules']) > 0 || count($rmRulesSet['sgRules']) > 0) {
            $this->callPlatformMethod($platform, __FUNCTION__, array($platform, $cloudLocation, $groupData['id'], $rmRulesSet, 'remove'));
        }

    }

    private function saveGroupRulesEc2($platform, $cloudLocation, $securityGroupId, $rules, $action)
    {
        $sgService = $this->getPlatformService($platform, $cloudLocation);
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
            $sgId = null;

            if (substr($name, 0, 3) == 'sg-') {
                $sgId = $name;
                $name = null;
            }

            $ipPermissionList->append(new IpPermissionData(
                $rule['ipProtocol'],
                $rule['fromPort'],
                $rule['toPort'],
                null,
                new UserIdGroupPairList(new UserIdGroupPairData($userId, $sgId, $name))
            ));
        }
        if ($action == 'add') {
            $sgService->authorizeIngress($ipPermissionList, $securityGroupId);
        } else {
            $sgService->revokeIngress($ipPermissionList, $securityGroupId);
        }
    }

    private function saveGroupRulesOpenstack($platform, $cloudLocation, $securityGroupId, $rules, $action)
    {
        $openstack = $this->getPlatformService($platform, $cloudLocation);
        $allGroups = $this->listGroupsOpenstack($platform, $cloudLocation, array());

        $list = array();

        foreach ($allGroups as $s) {
            $list[$s['name']] = $s['id'];
        }

        foreach ($rules['rules'] as $rule) {
            if ($action == 'add') {
                $request = array(
                    'security_group_id'  => $securityGroupId,
                    'protocol'           => $rule['ipProtocol'],
                    "direction"          => $rule['direction'] ? $rule['direction'] : "ingress",
                    'port_range_min'     => $rule['fromPort'] ? $rule['fromPort'] : null,
                    'port_range_max'     => $rule['toPort'] ? $rule['toPort'] : null,
                    'remote_ip_prefix'   => $rule['cidrIp'],
                    'remote_group_id'    => null
                );
                $openstack->createSecurityGroupRule($request);
            } else {
                $openstack->deleteSecurityGroupRule($rule['id']);
            }
        }

        foreach ($rules['sgRules'] as $rule) {
            if ($action == 'add') {
                $request = array(
                    'security_group_id' => $securityGroupId,
                    'protocol'          => $rule['ipProtocol'],
                    "direction"         => $rule['direction'] ? $rule['direction'] : "ingress",
                    'port_range_min'    => $rule['fromPort'],
                    'port_range_max'    => $rule['toPort'],
                    'remote_group_id'   => !empty($list[$rule['sg']]) ? $list[$rule['sg']] : $rule['sg'],
                    'remote_ip_prefix'  => null
                );
                $openstack->createSecurityGroupRule($request);
            } else {
                $openstack->deleteSecurityGroupRule($rule['id']);
            }
        }

    }

    private function saveGroupRulesCloudstack($platform, $cloudLocation, $securityGroupId, $rules, $action)
    {
        $sgService = $this->getPlatformService($platform, $cloudLocation);

        foreach ($rules['rules'] as $rule) {
            if ($action == 'add') {
                $sgService->authorizeIngress(array(
                    'securitygroupid'  => $securityGroupId,
                    'protocol'         => $rule['ipProtocol'],
                    'startport'        => $rule['fromPort'],
                    'endport'          => $rule['toPort'],
                    'cidrlist'         => $rule['cidrIp']
                ));
            } else {
                $sgService->revokeIngress($rule['id']);
            }
        }
    }


    private function getGroupIdByNameEc2($platform, $cloudLocation, $securityGroupName, $vpcId = null)
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
        $list = $this->getPlatformService($platform, $cloudLocation)->describe(null, null, $filter);

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

    private function getGroupIdByNameOpenstack($platform, $cloudLocation, $securityGroupName, $vpcId = null)
    {
        $result = null;

        $openstack = $this->getPlatformService($platform, $cloudLocation);
        $list = $openstack->listSecurityGroups();

        foreach ($list as $v) {
            if ($v->name === $securityGroupName) {
                $result = $v->id;
                break;
            }
        }

        return $result;
    }

    private function getGroupIdByNameCloudstack($platform, $cloudLocation, $securityGroupName, $vpcId = null)
    {
        $result = null;
        $list = $this->getPlatformService($platform, $cloudLocation)->describe();
        foreach ($list as $v) {
            if ($v->name === $securityGroupName) {
                $result = $v->id;
                break;
            }
        }

        return $result;
    }

    private function listGroups($platform, $cloudLocation, $filters)
    {
        $rows = $this->callPlatformMethod($platform, __FUNCTION__, func_get_args());
        $response = $this->buildResponseFromData($rows, array('id', 'name', 'description', 'vpcId'));

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
        return $response;
    }

    private function listGroupsEc2($platform, $cloudLocation, $filters)
    {
        $sgFilter = null;
        $result = array();

        if (!empty($filters['sgIds'])) {
            $sgFilter = is_null($sgFilter) ? array() : $sgFilter;
            $sgFilter[] = array(
                'name' => SecurityGroupFilterNameType::groupId(),
                'value' => $filters['sgIds']
            );
        }

        if (empty($filters['vpcId'])) {
            $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
            $defaultVpc = $p->getDefaultVpc($this->environment, $cloudLocation);
            if ($defaultVpc)
                $filters['vpcId'] = $defaultVpc;
        }
        
        if (!empty($filters['vpcId'])) {
            $sgFilter = is_null($sgFilter) ? array() : $sgFilter;
            $sgFilter[] = array(
                'name' => SecurityGroupFilterNameType::vpcId(),
                'value' => $filters['vpcId']
            );
        }

        $sgList = $this->getPlatformService($platform, $cloudLocation)->describe(null, null, $sgFilter);
        /* @var $sg SecurityGroupData */
        foreach ($sgList as $sg) {
            
            if (is_array($filters) && array_key_exists('vpcId', $filters) && $filters['vpcId'] == null && $sg->vpcId)
                continue;

            $result[] = array(
                'id'          => $sg->groupId,
                'name'        => $sg->groupName,
                'description' => $sg->groupDescription,
                'vpcId'       => $sg->vpcId,
                'owner'       => $sg->ownerId
            );
        }

        return $result;

    }

    private function listGroupsRds($platform, $cloudLocation, $filters)
    {
        $result = [];

        $sgList = $this->getEnvironment()->aws($cloudLocation)->rds->dbSecurityGroup->describe();

        /* @var $sg DBSecurityGroupData */
        foreach ($sgList as $sg) {
            if (!empty($filters['vpcId']) && $filters['vpcId'] != $sg->vpcId) {
                continue;
            }

            $result[] = array(
                'name'        => $sg->dBSecurityGroupName,
                'description' => $sg->dBSecurityGroupDescription,
                'vpcId'       => $sg->vpcId,
                'owner'       => $sg->ownerId
            );
        }

        return $result;

    }

    private function listGroupsOpenstack($platform, $cloudLocation, $filters)
    {
        $result = array();

        $openstack = $this->getPlatformService($platform, $cloudLocation);
        /* @var $openstack \Scalr\Service\OpenStack\OpenStack */

        $sgList = $openstack->listSecurityGroups()->toArray();

        foreach ($sgList as $sg) {
            if (!empty($filters['sgIds']) && !in_array($sg->id, $filters['sgIds'])) {
                continue;
            }

            if ($openstack->hasNetworkSecurityGroupExtension() && $sg->tenant_id != $openstack->getConfig()->getAuthToken()->getTenantId()) {
                continue;
            }

            $result[] = array(
                'id'          => $sg->id,
                'name'        => $sg->name,
                'description' => $sg->description
            );
        }

        return $result;
    }

    private function listGroupsCloudstack($platform, $cloudLocation, $filters)
    {
        $result = array();
        $sgList = $this->getPlatformService($platform, $cloudLocation)->describe();
        foreach ($sgList as $sg) {
            if (!empty($filters['sgIds']) && !in_array($sg->id, $filters['sgIds'])) {
                continue;
            }
            $result[] = array(
                'id'          => $sg->id,
                'name'        => $sg->name,
                'description' => $sg->description
            );
        }
        return $result;
    }

    private function createGroupEc2($platform, $cloudLocation, $groupData)
    {
        $securityGroupId = $this->getPlatformService($platform, $cloudLocation)->create($groupData['name'], $groupData['description'], $groupData['vpcId']);
        sleep(5);
        return $securityGroupId;
    }

    private function createGroupRds($platform, $cloudLocation, $groupData)
    {
        $securityGroup = $this->getEnvironment()->aws($cloudLocation)->rds->dbSecurityGroup->create($groupData['name'], $groupData['description']);
        sleep(5);
        return $securityGroup->dBSecurityGroupName;
    }

    private function createGroupOpenstack($platform, $cloudLocation, $groupData)
    {
        $openstack = $this->getPlatformService($platform, $cloudLocation);
        $securityGroup = $openstack->createSecurityGroup($groupData['name'], $groupData['description']);

        return $securityGroup->id;
    }

    private function createGroupCloudstack($platform, $cloudLocation, $groupData)
    {
        $securityGroup = $this->getPlatformService($platform, $cloudLocation)->create(array('name' => $groupData['name'], 'description' => $groupData['description']));
        return $securityGroup->id;
    }

    private function deleteGroup($platform, $cloudLocation, $securityGroupId)
    {
        return $this->callPlatformMethod($platform, __FUNCTION__, func_get_args());
    }

    private function deleteGroupEc2($platform, $cloudLocation, $securityGroupId)
    {
        return $this->getPlatformService($platform, $cloudLocation)->delete($securityGroupId);
    }

    private function deleteGroupOpenstack($platform, $cloudLocation, $securityGroupId)
    {
        $openstack = $this->getPlatformService($platform, $cloudLocation);
        $result = $openstack->deleteSecurityGroup($securityGroupId);

        return $result;
    }

    private function deleteGroupCloudstack($platform, $cloudLocation, $securityGroupId)
    {
        return $this->getPlatformService($platform, $cloudLocation)->delete(array('id' => $securityGroupId));
    }

    private function callPlatformMethod($platform, $method, $arguments)
    {
        if ($platform === SERVER_PLATFORMS::EC2 || $platform === SERVER_PLATFORMS::EUCALYPTUS) {
            $method .= ucfirst(SERVER_PLATFORMS::EC2);
        } elseif (PlatformFactory::isOpenstack($platform)) {
            $method .= ucfirst(SERVER_PLATFORMS::OPENSTACK);
        } elseif (PlatformFactory::isCloudstack($platform)) {
            $method .= ucfirst(SERVER_PLATFORMS::CLOUDSTACK);
        } elseif ($platform === 'rds') {
            $method .= ucfirst($platform);
        } else {
            throw new Exception('Security groups are not supported for this cloud.');
        }

        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $arguments);
        } else {
            throw new Exception('Under construction...');
        }
    }

    private function getPlatformService($platform, $cloudLocation)
    {
        if ($platform == SERVER_PLATFORMS::EC2) {
            return $this->getEnvironment()->aws($cloudLocation)->ec2->securityGroup;
        } elseif (PlatformFactory::isOpenstack($platform)) {
            $openstack = $this->getEnvironment()->openstack($platform, $cloudLocation);
            return $openstack;
        } elseif (PlatformFactory::isCloudstack($platform)) {
            return $this->getEnvironment()->cloudstack($platform)->securityGroup;
        } else {
            throw new Exception ('Platform is not supported');
        }
    }
}
