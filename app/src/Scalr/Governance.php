<?php

use Scalr\Service\Aws;

class Scalr_Governance
{
    private
        $envId,
        $db;

    private static $cache = [];

    const SCALR_META_TAG_NAME = 'scalr-meta';
    const SCALR_META_TAG_VALUE = 'v1:{SCALR_ENV_ID}:{SCALR_FARM_ID}:{SCALR_FARM_ROLE_ID}:{SCALR_SERVER_ID}';

    const INSTANCE_TYPE = 'instance_type';

    const AWS_KEYPAIR = 'aws.ssh_key_pair';
    const AWS_SECURITY_GROUPS = 'aws.additional_security_groups';
    const AWS_RDS_SECURITY_GROUPS = 'aws.rds_additional_security_groups';
    const AWS_ELB_SECURITY_GROUPS = 'aws.elb_additional_security_groups';
    const AWS_IAM = 'aws.iam';
    const AWS_VPC = 'aws.vpc';
    const AWS_TAGS = 'aws.tags';
    const AWS_INSTANCE_NAME_FORMAT = 'aws.instance_name_format';
    const AWS_KMS_KEYS = 'aws.kms_keys';

    const OPENSTACK_SECURITY_GROUPS = 'openstack.additional_security_groups';
    const OPENSTACK_INSTANCE_NAME_FORMAT = 'openstack.instance_name_format';
    const OPENSTACK_TAGS = 'openstack.tags';

    const AZURE_TAGS = 'azure.tags';
    const AZURE_NETWORK = 'azure.network';

    const CLOUDSTACK_SECURITY_GROUPS = 'cloudstack.additional_security_groups';

    const CATEGORY_GENERAL = 'general';
    const GENERAL_LEASE = 'general.lease';
    const GENERAL_HOSTNAME_FORMAT = 'general.hostname_format';
    const GENERAL_CHEF = 'general.chef';

    public function __construct($envId)
    {
        $this->envId = $envId;
        $this->db = \Scalr::getDb();
    }

    /**
     * Sets policy settings.
     *
     * @param   string   $category  Governance category name
     * @param   string   $name  Governance policy name
     * @param   int      $enabled  Possible values: 1 - Enable policy, 0 - disable policy
     * @param   array    $value  Governance policy settings to set
     */
    public function setValue($category, $name, $enabled, $value)
    {
        $jsonValue = json_encode($value);
        $this->db->Execute("INSERT INTO `governance` SET
            `env_id` = ?,
            `category` = ?,
            `name` = ?,
            `value` = ?,
            `enabled` = ?
            ON DUPLICATE KEY UPDATE `value` = ?, `enabled` = ?", [
            $this->envId,
            $category,
            $name,
            $jsonValue,
            $enabled,

            $jsonValue,
            $enabled
        ]);

        self::$cache[$this->envId][$category][$name] = [
            'value' => $value,
            'enabled' => $enabled
        ];
    }

    /**
     * Returns all governace policies settings.
     *
     * @param   bool   $enabledOnly  If true - returns only enabled governance policies
     * @return  array  Returns array of policies
     */
    public function getValues($enabledOnly = false)
    {
        $result = [];
        $list = $this->db->GetAll(
            'SELECT category, name, value, enabled FROM `governance` WHERE env_id = ?' . ($enabledOnly ? ' AND enabled = 1' : ''),
            [
                $this->envId
            ]
        );
        foreach ($list as $var) {
            if ($enabledOnly) {
                if ($var['name'] == self::AWS_KEYPAIR) {//do not expose
                    continue;
                }
                $result[$var['category']][$var['name']] = json_decode($var['value']);
            } else {
                $result[$var['category']][$var['name']] = [
                    'enabled' => $var['enabled'],
                    'limits' => json_decode($var['value'])
                ];
            }
        }
        return $result;
    }

    /**
     * Returns policy status.
     *
     * @param   string   $category  Governance category name
     * @param   string   $name  Governance policy name
     * @return  bool  Returns true if policy is enabled
     */
    public function isEnabled($category, $name)
    {
        $policy = $this->loadValue($category, $name);

        return ($policy['enabled'] == 1);
    }

    /**
     * Returns enabled policy settings.
     *
     * @param   string   $category  Governance category name
     * @param   string   $name  Governance policy name
     * @param   string   $option   Default option is 'value'
     * @return  array    Policy settings
     */
    public function getValue($category, $name, $option = 'value')
    {
        $result = null;

        $policy = $this->loadValue($category, $name);

        if (!empty($policy['value']) && $policy['enabled'] == 1) {
            if (!empty($option)) {
                $result = isset($policy['value'][$option]) ? $policy['value'][$option] : null;
            } else {
                $result = $policy['value'];
            }
        }

        return $result;
    }

    /**
     * Returns policy settings with caching.
     *
     * @param   string   $category  Governance category name
     * @param   string   $name  Governance policy name
     * @return  array    Returns policy as array looks like ['enabled' => 0|1, 'value' => Key/value pairs of policy settings]
     */
    private function loadValue($category, $name)
    {
        if (!isset(self::$cache[$this->envId][$category][$name])) {
            $policy = $this->db->GetRow(
                "SELECT enabled, value FROM `governance` WHERE env_id = ? AND category = ? AND name = ?",
                [
                    $this->envId,
                    $category,
                    $name
                ]
            );
            self::$cache[$this->envId][$category][$name] = [
                'value' => !empty($policy['value']) ? json_decode($policy['value'], true) : null,
                'enabled' => !empty($policy['enabled']) ? $policy['enabled'] : 0
            ];
        }

        return self::$cache[$this->envId][$category][$name];
    }

    /**
     * Clears governance cache
     */
    public static function clearCache()
    {
        self::$cache = [];
    }

    /**
     * Converts astrisk(*) pattern to regular expression
     *
     * @param   string   $pattern Pattern with asterisk
     * @return  string   Regular expression
     */
    public static function convertAsteriskPatternToRegexp($pattern)
    {
        $pattern = explode('*', $pattern);
        $pattern = array_map('preg_quote', $pattern);
        return '/^' . implode('.*', $pattern) . '$/i';
    }

    /**
     * Prepare governance security groups patterns
     *
     * @param   string  $list List of security groups, separated by comma
     * @return  Array   Security groups patterns
     */
    public static function prepareSecurityGroupsPatterns($list)
    {
        $result = [];
        if(!empty($list)) {
            $sgs = explode(',', $list);
            foreach ($sgs as $sg) {
                $sg = trim($sg);
                if (!empty($sg)) {
                    $pattern = ['value' => $sg];
                    if (strpos($sg, '*') !== false) {
                        $pattern['regexp'] = \Scalr_Governance::convertAsteriskPatternToRegexp($sg);
                    }
                    $result[strtolower($sg)] = $pattern;
                }
            }
        }
        return $result;
    }
    
    /**
     * Checks if security group is allowed
     *
     * @param   string  $sgName Security group name
     * @param   Array   $patterns List of patterns
     * @return  bool    Returns true if security matches at list one pattern
     */
    public static function isSecurityGroupNameAllowed($sgName, $patterns)
    {
        $allowed = false;
        if (!isset($patterns[$sgName])) {
            $found = false;
            foreach ($patterns as $pattern) {
                if (isset($pattern['regexp']) && preg_match($pattern['regexp'], $sgName) === 1) {
                    $found = true;
                    break;
                }
            }
            $allowed = $found;
        } else {
            $allowed = true;
        }
        return $allowed;
    }
    
    /**
     * Returns Security group policy name for service
     *
     * @param string  $serviceName Service name (rds, elb ...)
     * @return string Policy name
     */
    public static function getEc2SecurityGroupPolicyNameForService($serviceName)
    {
        $policyName = Scalr_Governance::AWS_SECURITY_GROUPS;
        switch ($serviceName) {
            case Aws::SERVICE_INTERFACE_RDS:
                $policyName = Scalr_Governance::AWS_RDS_SECURITY_GROUPS;
                break;
            case Aws::SERVICE_INTERFACE_ELB:
                $policyName = Scalr_Governance::AWS_ELB_SECURITY_GROUPS;
                break;
        }
        return $policyName;
    }

    /**
     * Checks if instance type is allowed
     *
     * @param   string  $platform      Platform
     * @param   string  $cloudLocation Cloud location
     * @param   string  $instanceType  Instance type
     * @return  bool    Returns true if instance type is allowed
     */
    public function isInstanceTypeAllowed($platform, $cloudLocation, $instanceType)
    {
        $allowed = true;
        $allowGovernanceIns = $this->getValue($platform, Scalr_Governance::INSTANCE_TYPE);
        if (isset($allowGovernanceIns)) {
            if ($platform == \SERVER_PLATFORMS::AZURE) {//azure instance type policy is separated by region
                $allowGovernanceIns = isset($allowGovernanceIns[$cloudLocation]['value']) ? $allowGovernanceIns[$cloudLocation]['value'] : null;
            }
            $allowed = !isset($allowGovernanceIns) || in_array($instanceType, $allowGovernanceIns);
        }
        return $allowed;
    }
    
}
