<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws;
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
use Scalr\Modules\Platforms\Azure\AzurePlatformModule;
use Scalr\Service\Azure\Services\Network\DataType\SecurityGroupProperties;
use Scalr\Service\Azure\Services\Network\DataType\SecurityRuleProperties;
use Scalr\Service\Azure\Services\Network\DataType\SecurityRuleData;
use Scalr\Service\Azure\Services\Network\DataType\CreateSecurityGroup;
use Scalr\Service\Azure\Services\Network\DataType\CreateSecurityRule;
use Scalr\Model\Entity;
use Scalr\UI\Request\JsonData;


class Scalr_UI_Controller_Security_Groups extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'securityGroupId';

    const INBOUND_RULE = 'inbound';
    const OUTBOUND_RULE = 'outbound';

    public function hasAccess()
    {
        return $this->request->isAllowed(Acl::RESOURCE_SECURITY_SECURITY_GROUPS);
    }

    /**
     * @param string   $platform    Platform
     * @throws Exception
     */
    public function defaultAction($platform)
    {
        $this->viewAction($platform);
    }

    /**
     * @param string   $platform    Platform
     * @throws Exception
     */
    public function viewAction($platform)
    {
        if (empty($platform)) {
            throw new Exception ('Platform should be specified');
        }

        $this->response->page('ui/security/groups/view.js');
    }

    /**
     * @param string   $platform               Platform
     * @param string   $cloudLocation optional Cloud location
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function createAction($platform, $cloudLocation = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SECURITY_SECURITY_GROUPS, Acl::PERM_SECURITY_SECURITY_GROUPS_MANAGE);

        $this->response->page('ui/security/groups/edit.js', array(
            'platform'          => $platform,
            'cloudLocation'     => $cloudLocation,
            'cloudLocationName' => $this->getCloudLocationName($platform, $cloudLocation),
            'accountId'         => $this->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
            'remoteAddress'     => $this->request->getRemoteAddr()
        ),array('ui/security/groups/sgeditor.js'));
    }

    /**
     * @param string   $platform                  Platform
     * @param string   $cloudLocation    optional Cloud location
     * @param string   $securityGroupId           Security group ID
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function editAction($platform, $cloudLocation = null, $securityGroupId)
    {
        //user can see readonly edit form with readonly acl
        $data = $this->getGroup($platform, $cloudLocation, $securityGroupId);

        $data['cloudLocationName'] = $this->getCloudLocationName($platform, $cloudLocation);
        if ($platform == SERVER_PLATFORMS::EC2) {
            $data['accountId'] = $this->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID];
        }
        $data['remoteAddress'] = $this->request->getRemoteAddr();

        $this->response->page('ui/security/groups/edit.js', $data, array('ui/security/groups/sgeditor.js'));
    }

    /**
     * Updates security group
     *
     * @param string $platform          Platform
     * @param string $cloudLocation     Cloud location
     * @param string $securityGroupId   SG id
     * @param string $name              SG name
     * @param string $description       SG description
     * @param string $vpcId             SG vpcId
     * @param JsonData $rules
     * @param JsonData $sgRules
     * @param string $resourceGroup     SG resourceGroup(AZURE only)
     * @param bool $returnData
     * @throws Exception
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xSaveAction($platform, $cloudLocation = null, $securityGroupId = null, $name, $description, $vpcId = null, JsonData $rules, JsonData $sgRules, $resourceGroup = null, $returnData = false)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SECURITY_SECURITY_GROUPS, Acl::PERM_SECURITY_SECURITY_GROUPS_MANAGE);

        $extraParams = [];

        $groupData = array(
            'id'            => $securityGroupId,
            'name'          => trim($name),
            'description'   => trim($description),
            'rules'         => (array)$rules,
            'sgRules'       => (array)$sgRules,
            'vpcId'         => !empty($vpcId) ? $vpcId : null
        );

        if ($platform == SERVER_PLATFORMS::AZURE) {
            $extraParams['resourceGroup'] = $resourceGroup;
            $groupData['resourceGroup'] = $resourceGroup;
        }

        $invalidRules = array();
        foreach ($groupData['rules'] as $r) {
            if ($r['cidrIp'] != '*' && ! Scalr_Util_Network::isValidCidr($r['cidrIp'])) {
                $invalidRules[] = $this->request->stripValue($r['cidrIp']);
            }
        }

        if (count($invalidRules)) {
            throw new Scalr_Exception_Core(sprintf('Not valid CIDR(s): "%s"', join(', ', $invalidRules)));
        }

        foreach ($groupData['rules'] as &$rule) {
            $rule['comment'] = $this->request->stripValue($rule['comment']);
        }

        foreach ($groupData['sgRules'] as &$rule) {
            $rule['comment'] = $this->request->stripValue($rule['comment']);
        }

        if (!$groupData['id']) {
            $groupData['id'] = $this->callPlatformMethod($platform, 'createGroup', array($platform, $cloudLocation, $groupData));
            sleep(2);
        }

        $warning = null;

        if ($platform !== 'rds') {
            $currentGroupData = $this->getGroup($platform, $cloudLocation, $groupData['id'], $extraParams);

            try {
                $this->saveGroupRules($platform, $cloudLocation, $currentGroupData, array('rules' => $groupData['rules'], 'sgRules' => $groupData['sgRules']), $extraParams);
            } catch (QueryClientException $e) {
                $warning = $e->getErrorData()->getMessage();
            }
        }

        if ($returnData && $groupData['id']) {
            $this->response->data(array('group' => $this->getGroup($platform, $cloudLocation, $groupData['id'], $extraParams)));
        }

        if ($warning) {
            $this->response->warning('Security group saved with warning: ' . $warning);
        } else {
            $this->response->success("Security group successfully saved");
        }
    }

    /**
     * Removes security groups
     *
     * @param string   $platform                Platform
     * @param string   $cloudLocation  optional Cloud location
     * @param JsonData $groups
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xRemoveAction($platform, $cloudLocation = null, JsonData $groups)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SECURITY_SECURITY_GROUPS, Acl::PERM_SECURITY_SECURITY_GROUPS_MANAGE);

        $cnt = 0;
        foreach ((array)$groups as $securityGroupId) {
            if (empty($securityGroupId)) continue;
            $cnt++;
            $this->deleteGroup($platform, $cloudLocation, $securityGroupId);
        }

        $this->response->success('Selected security group' . ($cnt > 1 ? 's have' : ' has') . ' been successfully removed');
    }

    public function xGetGroupInfoAction($platform, $cloudLocation, $securityGroupId = null, $securityGroupName = null, $vpcId = null, $resourceGroup = null)
    {
        $data = [];

        $defaultVpcId = $this->environment->getPlatformConfigValue(Ec2PlatformModule::DEFAULT_VPC_ID . "." . $cloudLocation);
        if (empty($vpcId) && !empty($defaultVpcId)) {
            $vpcId = $defaultVpcId;
        }

        if (empty($securityGroupId) && !empty($securityGroupName)) {
            $securityGroupIds = $this->callPlatformMethod($platform, 'getGroupIdsByName',  array($platform, $cloudLocation, $securityGroupName, ['vpcId' => $vpcId, 'resourceGroup' => $resourceGroup]));
        } else {
            $securityGroupIds = [$securityGroupId];
        }


        if (!empty($securityGroupIds)) {
            if (count($securityGroupIds) == 1) {
                $data = $this->getGroup($platform, $cloudLocation, $securityGroupIds[0], ['resourceGroup' => $resourceGroup]);
                $data['cloudLocationName'] = $this->getCloudLocationName($platform, $cloudLocation);
                $data['accountId'] = $this->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID];
                $data['remoteAddress'] = $this->request->getRemoteAddr();
            } else {
                $this->response->failure(sprintf(_("There are more than one Security group matched to pattern '%s' found."), $securityGroupName));
                return;
            }
        }

        $this->response->data($data);
    }

    /**
     * Lists security groups
     *
     * @param string   $platform               Platform
     * @param string   $cloudLocation optional Cloud location
     * @param JsonData $filters
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xListGroupsAction($platform, $cloudLocation = null, JsonData $filters = null)
    {
        $result = $this->listGroups($platform, $cloudLocation, (array)$filters);
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

    private function getGroup($platform, $cloudLocation, $securityGroupId, $extraParams = null)
    {
        if (!$securityGroupId) throw new Exception ('Security group ID is required');
        return $this->callPlatformMethod($platform, __FUNCTION__, func_get_args());
    }

    private function getGroupEc2($platform, $cloudLocation, $securityGroupId)
    {
        /* @var $sgInfo SecurityGroupData */
        $sgInfo = $this->getPlatformService($platform, $cloudLocation)->describe(null, $securityGroupId)->get(0);

        $getRules = function ($ipPermissions, $type) use ($platform, $cloudLocation, $sgInfo) {
            $rules = [];
            $sgRules = [];

            foreach ($ipPermissions as $rule) {
                /* @var $rule IpPermissionData */
                foreach ($rule->ipRanges as $ipRange) {
                    /* @var $ipRange IpRangeData */
                    $r = [
                        'ipProtocol' => $rule->ipProtocol == '-1' ? "ANY" : $rule->ipProtocol,
                        'fromPort'   => $rule->fromPort,
                        'toPort'     => $rule->toPort,
                    ];

                    $r['type'] = $type;
                    $r['cidrIp'] = $ipRange->cidrIp;
                    $r['rule'] = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['cidrIp']}";

                    if ($r['type'] == self::OUTBOUND_RULE) {
                        $r['rule'] .= ":{$r['type']}";
                    }

                    $r['id'] = CryptoTool::hash($r['rule']);
                    $r['comment'] = $this->getRuleComment($platform, $cloudLocation, $sgInfo->vpcId, $sgInfo->groupName, $r['rule']);

                    if (!isset($rules[$r['id']])) {
                        $rules[$r['id']] = $r;
                    }
                }
                /* @var $group UserIdGroupPairData */
                foreach ($rule->groups as $group) {
                    $r = [
                        'ipProtocol' => $rule->ipProtocol == '-1' ? "ANY" : $rule->ipProtocol,
                        'fromPort'   => $rule->fromPort,
                        'toPort'     => $rule->toPort
                    ];

                    $name = $group->groupName ? $group->groupName : $group->groupId;

                    $r['type'] = $type;
                    $r['sg'] =  $group->userId . '/' . $name;
                    $r['rule'] = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['sg']}";

                    if ($r['type'] == self::OUTBOUND_RULE) {
                        $r['rule'] .= ":{$r['type']}";
                    }

                    $r['id'] = CryptoTool::hash($r['rule']);
                    $r['comment'] = $this->getRuleComment($platform, $cloudLocation, $sgInfo->vpcId, $sgInfo->groupName, $r['rule']);

                    if (!isset($sgRules[$r['id']])) {
                        $sgRules[$r['id']] = $r;
                    }
                }
            }

            return ['rules' => $rules, 'sgRules' => $sgRules];
        };

        $inboundRules = $getRules($sgInfo->ipPermissions, self::INBOUND_RULE);
        $outboundRules = $getRules($sgInfo->ipPermissionsEgress, self::OUTBOUND_RULE);

        return [
            'platform'        => $platform,
            'cloudLocation'   => $cloudLocation,
            'id'              => $sgInfo->groupId,
            'vpcId'           => $sgInfo->vpcId,
            'name'            => $sgInfo->groupName,
            'description'     => $sgInfo->groupDescription,
            'rules'           => $inboundRules['rules'] + $outboundRules['rules'],
            'sgRules'         => $inboundRules['sgRules'] + $outboundRules['sgRules'],
            'raw_data'        => $sgInfo->toArray()
        ];
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

    private function getGroupAzure($platform, $cloudLocation, $securityGroupId, $extraParams)
    {
        $rules = [];

        $azure = $this->environment->azure();
        $sgInfo = $azure->network
                        ->securityGroup
                        ->getInfo(
                            $this->environment
                                 ->keychain(SERVER_PLATFORMS::AZURE)
                                 ->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                            $extraParams['resourceGroup'], $securityGroupId
                        );

        if (!empty($sgInfo->properties->securityRules)) {
            foreach ($sgInfo->properties->securityRules as $rule) {
                    if ($rule->properties->direction !== SecurityRuleProperties::DIRECTION_INBOUND) {
                        continue;
                    }
                    $portRange = explode('-', $rule->properties->destinationPortRange);
                    $r = array(
                        'id'         => $rule->name,
                        'ipProtocol' => $rule->properties->protocol,
                        'fromPort'   => $portRange[0],
                        'toPort'     => isset($portRange[1]) ? $portRange[1] : $portRange[0],
                        'cidrIp'     => $rule->properties->sourceAddressPrefix,
                        'comment'    => $rule->properties->description,
                        'priority'    => $rule->properties->priority
                    );

                    $rules[$rule->name] = $r;
            }
        }

        return [
            'platform'        => $platform,
            'cloudLocation'   => $cloudLocation,
            'id'              => $sgInfo->name,
            'name'            => $sgInfo->name,
            'rules'           => $rules,
            'sgRules'         => []
        ];
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

    private function saveGroupRules($platform, $cloudLocation, $groupData, $newRules, $extraParams)
    {
        if ($platform != SERVER_PLATFORMS::AZURE) {
            $ruleTypes = array('rules', 'sgRules');
            $addRulesSet = array();
            $rmRulesSet = array();

            foreach ($ruleTypes as $ruleType) {
                $addRulesSet[$ruleType] = array();
                $rmRulesSet[$ruleType] = array();

                foreach ($newRules[$ruleType] as $r) {
                    if (empty($r['id'])) {
                        if ($ruleType == 'rules') {
                            $rule = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['cidrIp']}";
                        } elseif ($ruleType == 'sgRules') {
                            $rule = "{$r['ipProtocol']}:{$r['fromPort']}:{$r['toPort']}:{$r['sg']}";
                        }

                        if ($platform == SERVER_PLATFORMS::EC2 && $r['type'] == self::OUTBOUND_RULE) {
                            $rule .= ":{$r['type']}";
                        }

                        $id = CryptoTool::hash($rule);
                        if (empty($groupData[$ruleType][$id])) {
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
                $this->callPlatformMethod($platform, __FUNCTION__, array($platform, $cloudLocation, $groupData, $addRulesSet, 'add'));
            }

            if (count($rmRulesSet['rules']) > 0 || count($rmRulesSet['sgRules']) > 0) {
                $this->callPlatformMethod($platform, __FUNCTION__, array($platform, $cloudLocation, $groupData, $rmRulesSet, 'remove'));
            }
        } else {
            $addRulesSet = [];
            $rmRulesSet = [];
            foreach ($newRules['rules'] as $r) {
                if (!$r['id']) {
                    $addRulesSet['rules'][] = $r;
                }
            }
            foreach ($groupData['rules'] as $r) {
                $found = false;
                foreach ($newRules['rules'] as $nR) {
                    if ($nR['id'] == $r['id']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $rmRulesSet['rules'][] = $r;
                }
            }
            if (count($rmRulesSet['rules']) > 0) {
                $this->callPlatformMethod($platform, __FUNCTION__, array($platform, $cloudLocation, $groupData, $rmRulesSet, 'remove', $extraParams));
            }

            if (count($addRulesSet['rules']) > 0) {
                $this->callPlatformMethod($platform, __FUNCTION__, array($platform, $cloudLocation, $groupData, $addRulesSet, 'add', $extraParams));
            }
        }
    }

    private function saveGroupRulesEc2($platform, $cloudLocation, $groupData, $rules, $action)
    {
        $securityGroupId = $groupData['id'];
        $sgService = $this->getPlatformService($platform, $cloudLocation);
        $ipPermissionListIngress = new IpPermissionList();
        $ipPermissionListEgress = new IpPermissionList();

        foreach ($rules['rules'] as $rule) {
            $item = new IpPermissionData(
                $rule['ipProtocol'] == 'ANY' ? '-1' : $rule['ipProtocol'],
                $rule['fromPort'],
                $rule['toPort'],
                new IpRangeList(new IpRangeData($rule['cidrIp'])),
                null
            );

            if ($rule['type'] == self::OUTBOUND_RULE) {
                $ipPermissionListEgress->append($item);
            } else {
                $ipPermissionListIngress->append($item);
            }
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

            $item = new IpPermissionData(
                $rule['ipProtocol'] == 'ANY' ? '-1' : $rule['ipProtocol'],
                $rule['fromPort'],
                $rule['toPort'],
                null,
                new UserIdGroupPairList(new UserIdGroupPairData($userId, $sgId, $name))
            );

            if ($rule['type'] == self::OUTBOUND_RULE) {
                $ipPermissionListEgress->append($item);
            } else {
                $ipPermissionListIngress->append($item);
            }
        }

        if ($action == 'add') {
            if (count($ipPermissionListIngress)) {
                $sgService->authorizeIngress($ipPermissionListIngress, $securityGroupId);
            }

            if (count($ipPermissionListEgress)) {
                $sgService->authorizeEgress($ipPermissionListEgress, $securityGroupId);
            }
        } else {
            if (count($ipPermissionListIngress)) {
                $sgService->revokeIngress($ipPermissionListIngress, $securityGroupId);
            }

            if (count($ipPermissionListEgress)) {
                $sgService->revokeEgress($ipPermissionListEgress, $securityGroupId);
            }
        }
    }

    private function saveGroupRulesOpenstack($platform, $cloudLocation, $groupData, $rules, $action)
    {
        $securityGroupId = $groupData['id'];
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

    private function saveGroupRulesCloudstack($platform, $cloudLocation, $groupData, $rules, $action)
    {
        $securityGroupId = $groupData['id'];
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

    private function saveGroupRulesAzure($platform, $cloudLocation, $groupData, $rules, $action, $extraParams)
    {
        $azure = $this->environment->azure();
        foreach ($rules['rules'] as $rule) {
            if ($action == 'add') {
                $createSecurityRule = new CreateSecurityRule($rule['ipProtocol'] == '*' ? $rule['ipProtocol'] : ucfirst($rule['ipProtocol']), '*', $rule['fromPort'] == $rule['toPort'] ? $rule['fromPort'] : $rule['fromPort'].'-'.$rule['toPort'], $rule['cidrIp'], '*', 'Allow', $rule['priority'], SecurityRuleProperties::DIRECTION_INBOUND, $rule['comment']);
                $azure->network
                      ->securityRule
                      ->create(
                          $this->environment
                               ->keychain(SERVER_PLATFORMS::AZURE)
                               ->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                          $extraParams['resourceGroup'],
                          $groupData['id'],
                          'scalr-' . \Scalr::GenerateRandomKey(20),
                          $createSecurityRule
                      );
            } else {
                $azure->network
                      ->securityRule
                      ->delete(
                          $this->environment
                               ->keychain(SERVER_PLATFORMS::AZURE)
                               ->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                          $extraParams['resourceGroup'],
                          $groupData['id'],
                          $rule['id']
                      );
            }
        }
    }


    private function getGroupIdsByNameEc2($platform, $cloudLocation, $securityGroupName, $extraParams = [])
    {
        $vpcId = isset($extraParams['vpcId']) ? $extraParams['vpcId'] : null;

        $result = [];
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
                    $result[] = $v->groupId;
                }
            }
        }

        return $result;
    }

    private function getGroupIdsByNameOpenstack($platform, $cloudLocation, $securityGroupName, $extraParams = [])
    {
        $result = [];

        $openstack = $this->getPlatformService($platform, $cloudLocation);
        $list = $openstack->listSecurityGroups();

        foreach ($list as $v) {
            if ($v->name === $securityGroupName) {
                $result[] = $v->id;
            }
        }

        return $result;
    }

    private function getGroupIdsByNameCloudstack($platform, $cloudLocation, $securityGroupName, $extraParams = [])
    {
        $result = [];
        $list = $this->getPlatformService($platform, $cloudLocation)->describe();
        foreach ($list as $v) {
            if ($v->name === $securityGroupName) {
                $result[] = $v->id;
            }
        }

        return $result;
    }

    private function getGroupIdsByNameAzure($platform, $cloudLocation, $securityGroupName, $extraParams = []) {
        return [$securityGroupName];
    }

    public function listGroups($platform, $cloudLocation, $filters)
    {
        $rows = $this->callPlatformMethod($platform, __FUNCTION__, func_get_args());
        $response = $this->buildResponseFromData($rows, array('id', 'name', 'description', 'vpcId'));
        return $response;
    }

    private function listGroupsEc2($platform, $cloudLocation, $filters)
    {
        $sgFilter = null;
        $result = [];

        if (!is_array($filters)) {
            $filters = [];
        }

        if (empty($filters['vpcId']) && array_key_exists('vpcId', $filters)) {
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

        $sgIdsList = !empty($filters['sgIds']) ? (array)$filters['sgIds'] : null;
        $sgNamesList = !empty($filters['sgNames']) ? (array)$filters['sgNames'] : null;

        /* @var $sg SecurityGroupData */
        foreach ($sgList as $sg) {
            if (array_key_exists('vpcId', $filters) && $filters['vpcId'] == null && $sg->vpcId) {
                //we don't want to see VPC Security groups when $filters['vpcId'] == null
                continue;
            }

            if (!$this->isSecurityGroupsListed($sg->groupId, $sg->groupName, $sgIdsList, $sgNamesList)) {
                continue;
            }

            $result[] = [
                'id'          => $sg->groupId,
                'name'        => $sg->groupName,
                'description' => $sg->groupDescription,
                'vpcId'       => $sg->vpcId,
                'owner'       => $sg->ownerId
            ];
        }


        return $this->applyGovernanceToSgList($result, $platform, $cloudLocation, $filters);
    }


    /**
     * Returns true if security group listed in one of two arrays(ids, names)
     *
     * @param string   $securityGroupId    SG id
     * @param string   $securityGroupName  SG name
     * @param array    $sgIds              List of ids
     * @param array    $sgNames            List of names
     * @return bool
     */
    private function isSecurityGroupsListed($securityGroupId, $securityGroupName, $sgIds, $sgNames)
    {
        return empty($sgIds) && empty($sgNames) ||
               !empty($sgIds) && in_array($securityGroupId, $sgIds) ||
               !empty($sgNames) && in_array($securityGroupName, $sgNames);
    }

    /**
     * Applies governance to security groups list
     *
     * @param string   $list            SG list
     * @param string   $platform        Platform
     * @param string   $cloudLocation   Cloud location
     * @param array    $options         options
     * @return array
     */
    private function applyGovernanceToSgList($list, $platform, $cloudLocation, $options)
    {
        if (isset($options['considerGovernance']) && $options['considerGovernance']) {
            $filteredSg = [];
            $allowedSgNames = [];

            $governance = new Scalr_Governance($this->getEnvironmentId());
            if ($platform == SERVER_PLATFORMS::EC2) {
                $governanceSecurityGroups = $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::getEc2SecurityGroupPolicyNameForService($options['serviceName']), null);
            } elseif (PlatformFactory::isOpenstack($platform)) {
                $governanceSecurityGroups = $governance->getValue($platform, Scalr_Governance::OPENSTACK_SECURITY_GROUPS, null);
            } elseif (PlatformFactory::isCloudstack($platform)) {
                $governanceSecurityGroups = $governance->getValue($platform, Scalr_Governance::CLOUDSTACK_SECURITY_GROUPS, null);
            }

            if ($governanceSecurityGroups) {
                $sgRequiredPatterns = \Scalr_Governance::prepareSecurityGroupsPatterns($options['osFamily'] == 'windows' && $governanceSecurityGroups['windows'] ? $governanceSecurityGroups['windows'] : $governanceSecurityGroups['value']);
                $sgOptionalPatterns = $governanceSecurityGroups['allow_additional_sec_groups'] ? \Scalr_Governance::prepareSecurityGroupsPatterns($governanceSecurityGroups['additional_sec_groups_list']) : [];
                foreach ($list as $sg) {
                    $sgNameLowerCase = strtolower($sg['name']);
                    $sgAllowed = false;
                    if ($governanceSecurityGroups['allow_additional_sec_groups']) {
                        if (!empty($sgOptionalPatterns)) {
                            if (isset($sgOptionalPatterns[$sgNameLowerCase])) {
                                $sgAllowed = true;
                            } else {
                                foreach ($sgOptionalPatterns as &$sgOptionalPattern) {
                                    if (isset($sgOptionalPattern['regexp']) && preg_match($sgOptionalPattern['regexp'], $sg['name']) === 1) {
                                        $sgAllowed = true;
                                        break;
                                    }
                                }
                            }
                        } else {
                            $sgAllowed = true;
                        }
                    }


                    if (isset($sgRequiredPatterns[$sgNameLowerCase])) {
                        $sgAllowed = true;
                        $sg['addedByGovernance'] = true;
                        $sg['ignoreOnSave'] = true;
                        $sgRequiredPatterns[$sgNameLowerCase]['found'] = true;
                    } else {
                        foreach ($sgRequiredPatterns as &$sgRequiredPattern) {
                            if (isset($sgRequiredPattern['regexp']) && preg_match($sgRequiredPattern['regexp'], $sg['name']) === 1) {
                                $sgRequiredPattern['matches'][] = $sg;
                                break;
                            }
                        }
                    }

                    if ($sgAllowed) {
                        $allowedSgNames[] = $sgNameLowerCase;
                        $filteredSg[$sg['id']] = $sg;
                    }
                }
                foreach ($sgRequiredPatterns as &$sgRequiredPattern) {
                    if (isset($sgRequiredPattern['matches']) && count($sgRequiredPattern['matches']) == 1) {
                        $sg = $sgRequiredPattern['matches'][0];
                        if (!isset($filteredSg[$sg['id']])) {
                            $filteredSg[$sg['id']] = $sg;
                        }
                        $filteredSg[$sg['id']]['addedByGovernance'] = true;
                        $filteredSg[$sg['id']]['ignoreOnSave'] = true;
                        $sgRequiredPattern['found'] = true;
                    }
                }

                $list = $filteredSg;
                if (!$options['existingGroupsOnly']) {
                    foreach ($sgRequiredPatterns as $sgRequiredPattern) {
                        if (!$sgRequiredPattern['found']) {
                            $list[] = [
                                'id'          => null,
                                'name'        => $sgRequiredPattern['value'],
                                'description' => null,
                                'vpcId'       => null,
                                'owner'       => null,
                                'addedByGovernance' => true,
                                'ignoreOnSave' => true
                            ];
                        }
                    }
                }
            }
        }

        return $list;
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
            if (!$this->isSecurityGroupsListed($sg->id, $sg->name, $filters['sgIds'], $filters['sgNames'])) {
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
            if (!$this->isSecurityGroupsListed($sg->id, $sg->name, $filters['sgIds'], $filters['sgNames'])) {
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

    private function listGroupsAzure($platform, $cloudLocation, $filters)
    {
        $result = [];

        $azure = $this->environment->azure();
        $sgList = $azure->network
                        ->securityGroup
                        ->getList(
                            $this->environment
                                 ->keychain(SERVER_PLATFORMS::AZURE)
                                 ->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                            $filters['resourceGroup']
                        );

        foreach ($sgList as $sg) {
            if (!$this->isSecurityGroupsListed($sg->name, $sg->name, $filters['sgIds'], $filters['sgNames'])) {
                continue;
            }
            $result[] = array(
                'id'          => $sg->name,
                'name'        => $sg->name,
                //'description' => $sg->description
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

    private function createGroupAzure($platform, $cloudLocation, $groupData)
    {
        $azure = $this->environment->azure();

        if (str_replace(' ', '', $groupData['name']) != $groupData['name']) {
            throw new Exception(sprintf("Azure error. Invalid Security Group's name %s. Spaces are not allowed.", $groupData['name']), 400);
        }

        $securityGroup = $azure->network->securityGroup->create(
            $this->environment->keychain(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
            $groupData['resourceGroup'],
            $groupData['name'],
            new CreateSecurityGroup($cloudLocation)
        );

        return $securityGroup->name;
    }

    private function deleteGroup($platform, $cloudLocation, $securityGroupId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_SECURITY_SECURITY_GROUPS, Acl::PERM_SECURITY_SECURITY_GROUPS_MANAGE);

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
        if ($platform === SERVER_PLATFORMS::EC2) {
            $method .= ucfirst(SERVER_PLATFORMS::EC2);
        } elseif (PlatformFactory::isOpenstack($platform)) {
            $method .= ucfirst(SERVER_PLATFORMS::OPENSTACK);
        } elseif (PlatformFactory::isCloudstack($platform)) {
            $method .= ucfirst(SERVER_PLATFORMS::CLOUDSTACK);
        } elseif ($platform === 'rds') {
            $method .= ucfirst($platform);
        } elseif ($platform === SERVER_PLATFORMS::AZURE) {
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
