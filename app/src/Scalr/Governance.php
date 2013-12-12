<?php

class Scalr_Governance
{
    private
        $envId,
        $db;

    const AWS_KEYPAIR = 'aws.ssh_key_pair';
    const AWS_SECURITY_GROUPS = 'aws.additional_security_groups';

    const EUCALYPTUS_KEYPAIR = 'euca.ssh_key_pair';
    const EUCALYPTUS_SECURITY_GROUPS = 'euca.additional_security_groups';

    const GENERAL_LEASE = 'general.lease';
    const GENERAL_HOSTNAME_FORMAT = 'general.hostname_format';

    public function __construct($envId)
    {
        $this->envId = $envId;
        $this->db = \Scalr::getDb();
    }

    public function setValue($name, $data)
    {
        $value = json_encode($data['limits']);
        $this->db->Execute("INSERT INTO `governance` SET
            `env_id` = ?,
            `name` = ?,
            `value` = ?,
            `enabled` = ?
            ON DUPLICATE KEY UPDATE `value` = ?, `enabled` = ?", array(
            $this->envId,
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
            'SELECT name, value, enabled FROM `governance` WHERE env_id = ?' . ($enabledOnly ? ' AND enabled = 1' : ''),
            array(
                $this->envId
            )
        );
        foreach ($list as $var) {
            if ($enabledOnly) {
                $result[$var['name']] = json_decode($var['value']);
            } else {
                $result[$var['name']] = array(
                    'enabled' => $var['enabled'],
                    'limits' => json_decode($var['value'])
                );
            }
        }
        return $result;
    }

    public function isEnabled($name)
    {
        $value = $this->db->GetOne(
            "SELECT enabled FROM `governance` WHERE env_id = ? AND name = ? LIMIT 1",
            array(
                $this->envId,
                $name
            )
        );

        return ($value == 1);
    }

    //$governance = new Scalr_Governance($this->getEnvironmentId());
    //$governance->getValue('aws.ssh_key_pair')
    public function getValue($name, $option = 'value')
    {
        $result = null;

        $value = $this->db->GetOne(
            "SELECT value FROM `governance` WHERE env_id = ? AND name = ? AND enabled = 1",
            array(
                $this->envId,
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
