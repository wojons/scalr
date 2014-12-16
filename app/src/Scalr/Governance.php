<?php

class Scalr_Governance
{
    private
        $envId,
        $db;

    const AWS_KEYPAIR = 'aws.ssh_key_pair';
    const AWS_SECURITY_GROUPS = 'aws.additional_security_groups';
    const AWS_IAM = 'aws.iam';
    const AWS_VPC = 'aws.vpc';
    const AWS_TAGS = 'aws.tags';

    const OPENSTACK_SECURITY_GROUPS = 'openstack.additional_security_groups';
    
    const CLOUDSTACK_SECURITY_GROUPS = 'cloudstack.additional_security_groups';

    const EUCALYPTUS_KEYPAIR = 'euca.ssh_key_pair';
    const EUCALYPTUS_SECURITY_GROUPS = 'euca.additional_security_groups';

    const CATEGORY_GENERAL = 'general';
    const GENERAL_LEASE = 'general.lease';
    const GENERAL_HOSTNAME_FORMAT = 'general.hostname_format';
    const GENERAL_CHEF = 'general.chef';

    public function __construct($envId)
    {
        $this->envId = $envId;
        $this->db = \Scalr::getDb();
    }

    public function setValue($category, $name, $data)
    {
        $value = json_encode($data['limits']);
        $this->db->Execute("INSERT INTO `governance` SET
            `env_id` = ?,
            `category` = ?,
            `name` = ?,
            `value` = ?,
            `enabled` = ?
            ON DUPLICATE KEY UPDATE `value` = ?, `enabled` = ?", array(
            $this->envId,
            $category,
            $name,
            $value,
            $data['enabled'],

            $value,
            $data['enabled']
        ));
    }

    public function getValues($enabledOnly = false)
    {
        $result = array();
        $list = $this->db->GetAll(
            'SELECT category, name, value, enabled FROM `governance` WHERE env_id = ?' . ($enabledOnly ? ' AND enabled = 1' : ''),
            array(
                $this->envId
            )
        );
        foreach ($list as $var) {
            if ($enabledOnly) {
                $result[$var['category']][$var['name']] = json_decode($var['value']);
            } else {
                $result[$var['category']][$var['name']] = array(
                    'enabled' => $var['enabled'],
                    'limits' => json_decode($var['value'])
                );
            }
        }
        return $result;
    }

    public function isEnabled($category, $name)
    {
        $value = $this->db->GetOne(
            "SELECT enabled FROM `governance` WHERE env_id = ? AND category = ? AND name = ? LIMIT 1",
            array(
                $this->envId,
                $category,
                $name
            )
        );

        return ($value == 1);
    }

    //$governance = new Scalr_Governance($this->getEnvironmentId());
    //$governance->getValue('ec2', 'aws.ssh_key_pair')
    public function getValue($category, $name, $option = 'value')
    {
        $result = null;

        $value = $this->db->GetOne(
            "SELECT value FROM `governance` WHERE env_id = ? AND category = ? AND name = ? AND enabled = 1",
            array(
                $this->envId,
                $category,
                $name
            )
        );

        if (!empty($value)) {
            $value = json_decode($value, true);
            $result = !empty($option) ? $value[$option] : $value;
        }

        return $result;
    }
}
