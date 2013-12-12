<?php

class Scalr_Scripting_GlobalVariables
{
    const SCOPE_ENVIRONMENT = 'env';
    const SCOPE_ROLE = 'role';
    const SCOPE_FARM = 'farm';
    const SCOPE_FARMROLE = 'farmrole';
    const SCOPE_SERVER = 'server';

    private $envId,
        $scope,
        $db,
        $crypto,
        $cryptoKey;

    public function __construct($envId, $scope = Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT)
    {
        $this->crypto = new Scalr_Util_CryptoTool(
            MCRYPT_RIJNDAEL_256,
            MCRYPT_MODE_CFB,
            @mcrypt_get_key_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CFB),
            @mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CFB)
        );

        $this->cryptoKey = @file_get_contents(APPPATH."/etc/.cryptokey");

        $this->envId = $envId;
        $this->scope = $scope;
        $this->db = \Scalr::getDb();
    }

    private function resetGlobalFlags(&$variable)
    {
        // clear values if they are inherited
        if ($variable['flagRequiredGlobal'])
            $variable['flagRequired'] = '';

        if ($variable['flagFinalGlobal'])
            $variable['flagFinal'] = '';

        if ($variable['flagHiddenGlobal'])
            $variable['flagHidden'] = '';

        if ($variable['lockConfigure']) {
            $variable['format'] = '';
            $variable['validator'] = '';
        }
    }

    public function getErrorMessage($result)
    {
        $field = array_shift($result);
        return array_shift($field);
    }

    /**
     * @param $variables
     * @param int $roleId
     * @param int $farmId
     * @param int $farmRoleId
     * @param string $serverId
     * @return array|bool
     * @throws Scalr_Exception_Core
     */
    public function validateValues($variables, $roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $errors = array();

        // info about variable from upper levels, make copy of arguments for query
        $valFarmId = $farmId;
        $valRoleId = $roleId;
        $valFarmRoleId = $farmRoleId;
        if ($this->scope == self::SCOPE_FARMROLE) {
            $valFarmRoleId = 0;
        } else if ($this->scope == self::SCOPE_FARM) {
            $valFarmId = 0;
        } else if ($this->scope == self::SCOPE_ROLE) {
            $valRoleId = 0;
        }

        $usedNames = array();
        if (is_array($variables)) {
            foreach ($variables as $variable) {
                $name = $variable['name'];
                if (empty($name))
                    continue;

                $errors[$name] = array();
                if (! preg_match('/^[A-Za-z]{1,1}[A-Za-z0-9_]{1,49}$/', $name)) {
                    $errors[$name]['name'] = 'Invalid name';
                } else if (in_array($variable['name'], $usedNames)) {
                    $errors[$name]['name'] = 'Duplicate name';
                } else {
                    $usedNames[] = $variable['name'];
                }

                $this->resetGlobalFlags($variable);

                // check required flag only on FarmRole level
                // we don't check scope of var, because it could be not defined on FarmRole level, exclude var from FarmRole level with nonempty value
                if ($this->scope == self::SCOPE_FARMROLE && !($variable['scope'] == $this->scope && $variable['value'])) {
                    $doNotCheckFarmLevel = $variable['defaultScope'] == 'farm';

                    $values = $this->db->GetAll("SELECT * FROM global_variables
                            WHERE name = ? AND env_id = ? AND (role_id = ? or role_id = 0) AND (farm_id = ? OR farm_id = 0) AND farm_role_id = 0", array(
                        $name,
                        $this->envId,
                        $roleId,
                        $doNotCheckFarmLevel ? 0 : $farmId
                    ));

                    $flag = false; $em = false;
                    if ($doNotCheckFarmLevel && $variable['defaultValue']) {
                        $em = true;
                    }

                    foreach ($values as $vr) {
                        if ($vr['flag_required'])
                            $flag = true;
                        if ($vr['value'])
                            $em = true;
                    }

                    if ($flag && !$em)
                        $errors[$name]['value'] = sprintf('%s is required variable', $name);
                }


                if ($variable['scope'] != $this->scope) {
                    continue;
                }

                if ($variable['flagFinal'] && $variable['flagRequired']) {
                    $errors[$name]['flagFinal'] = $errors[$name]['flagRequired'] = 'You can\'t set final and required flags both';
                }

                if ($variable['flagFinal'] || $variable['flagRequired'] || $variable['flagHidden'] || $variable['format'] || $variable['validator']) {
                    $parents = $this->db->GetAll("SELECT scope, format, validator, flag_final AS flagFinal, flag_required AS flagRequired, flag_hidden AS flagHidden FROM global_variables WHERE name = ? AND env_id = ?", array($variable['name'], $this->envId));
                    foreach ($parents as $p) {
                        if (($p['flagFinal'] || $p['flagRequired'] || $p['flagHidden'] || $p['format'] || $p['validator']) && $p['scope'] != $this->scope) {
                            $errors[$name]['flagFinal'] = $errors[$name]['flagRequired'] = $errors[$name]['format'] = $errors[$name]['validator'] =
                                'You can\'t redefine advanced settings (flags, format, validator)';
                        }
                    }
                }

                if ($this->scope != self::SCOPE_ENVIRONMENT) {
                    // check final flag
                    $values = $this->db->GetAll("SELECT * FROM global_variables
                    WHERE name = ? AND flag_final = 1 AND env_id = ? AND (role_id = ? or role_id = 0) AND (farm_id = ? OR farm_id = 0) AND (farm_role_id = ? OR farm_role_id = 0)", array(
                        $name,
                        $this->envId,
                        $valRoleId,
                        $valFarmId,
                        $valFarmRoleId
                    ));

                    if (count($values) && $values[0]['scope'] != $this->scope)
                        $errors[$name]['value'] = sprintf('You can\'t change final variable locked on %s level', $values[0]['scope']);
                }

                $variable['value'] = trim($variable['value']);
                if ($variable['value'] && !($variable['value'] == '*******' && $variable['flagHiddenGlobal'])) {
                    $validator = $this->db->GetOne("SELECT validator FROM global_variables WHERE name = ? AND env_id = ? AND validator != '' LIMIT 1", array($variable['name'], $this->envId));
                    if ($validator) {
                        if ($validator[0] != '/')
                            $validator = '/' . $validator . '/';

                        if (preg_match($validator, $variable['value']) != 1)
                            throw new Scalr_Exception_Core(sprintf('Value "%s" isn\'t valid for variable "%s" at scope "%s"', $variable['value'], $variable['name'], $this->scope));
                    }
                }
            }
        }

        foreach ($errors as $k => $v) {
            if (empty($v))
                unset($errors[$k]);
        }

        return count($errors) ? $errors : true;
    }

    /**
     * @param $variables
     * @param int $roleId
     * @param int $farmId
     * @param int $farmRoleId
     * @param string $serverId
     * @param bool|string $validation = true (return errors), false (skip), exception (throw)
     * @throws Scalr_Exception_Core
     * @return array|bool
     */
    public function setValues($variables, $roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '', $validation = 'exception')
    {
        if ($validation !== false) {
            $validResult = $this->validateValues($variables, $roleId, $farmId, $farmRoleId, $serverId);
            if ($validResult !== TRUE) {
                if ($validation == 'exception')
                    throw new Scalr_Exception_Core($this->getErrorMessage($validResult));
                else
                    return $validResult;
            }
        }

        if (is_array($variables)) {
            foreach ($variables as $variable) {
                $name = $variable['name'];
                if (empty($name))
                    continue;

                if ($variable['scope'] != $this->scope) {
                    if ($variable['scope'] == $variable['defaultScope'])
                        $variable['flagDelete'] = true; // value is empty, delete variable from that scope
                    else
                        continue;
                }

                $this->resetGlobalFlags($variable);

                if ($variable['flagDelete']) {
                    $this->db->Execute("DELETE FROM `global_variables` WHERE name = ? AND env_id = ? AND role_id = ? AND farm_id = ? AND farm_role_id = ?", array(
                        $variable['name'],
                        $this->envId,
                        $roleId,
                        $farmId,
                        $farmRoleId
                    ));
                    continue;
                }

                $updateValue = true;
                $variable['value'] = trim($variable['value']);
                if ($variable['value']) {
                    if ($variable['flagHiddenGlobal'] && $variable['value'] == '******')
                        $updateValue = false;
                    else
                        $variable['value'] = $this->crypto->encrypt($variable['value'], $this->cryptoKey);
                }

                $sql = array(
                    '`env_id` = ?',
                    '`role_id` = ?',
                    '`farm_id` = ?',
                    '`farm_role_id` = ?',
                    '`server_id` = ?',
                    '`name` = ?',
                    '`flag_final` = ?',
                    '`flag_required` = ?',
                    '`flag_hidden` = ?',
                    '`scope` = ?',
                    '`validator` = ?',
                    '`format` = ?'
                );

                $params = array(
                    $this->envId,
                    $roleId,
                    $farmId,
                    $farmRoleId,
                    $serverId,
                    $variable['name'],
                    $variable['flagFinal'],
                    $variable['flagRequired'],
                    $variable['flagHidden'],
                    $variable['scope'],
                    $variable['validator'],
                    $variable['format']
                );

                if ($updateValue) {
                    $sql[] = '`value` = ?';
                    $params[] = $variable['value'];
                }

                $sqlUpdate = array(
                    '`flag_final` = ?', '`flag_required` = ?', '`flag_hidden` = ?', '`validator` = ?', '`format` = ?'
                );
                $params = array_merge($params, array(
                    $variable['flagFinal'],
                    $variable['flagRequired'],
                    $variable['flagHidden'],
                    $variable['validator'],
                    $variable['format']
                ));

                if ($updateValue) {
                    $sqlUpdate[] = '`value` = ?';
                    $params[] = $variable['value'];
                }

                $this->db->Execute("INSERT INTO `global_variables` SET " . implode(',', $sql) . " ON DUPLICATE KEY UPDATE " . implode(',', $sqlUpdate), $params);
            }
        }

        return true;
    }

    public function getValues($roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $sql = "SELECT name, value, scope, flag_final AS flagFinal, flag_required AS flagRequired, flag_hidden AS flagHidden, `validator`, `format` FROM `global_variables` WHERE env_id = ?";
        $vars = $this->db->GetAll($sql . " AND (role_id = '0' OR role_id = ?) AND (farm_id = '0' OR farm_id = ?) AND farm_role_id = '0' AND server_id = ''", array(
            $this->envId,
            $roleId,
            $farmId
        ));

        // snapshot of role changes roleId of FarmRole
        if ($farmRoleId) {
            $vars = array_merge($vars, $this->db->GetAll($sql . " AND farm_role_id = ? AND server_id = ''", array($this->envId, $farmRoleId)));
        }

        if ($serverId) {
            $vars = array_merge($vars, $this->db->GetAll($sql . " AND server_id = ?", array($this->envId, $serverId)));
        }

        $groupByName = array();
        foreach ($vars as $value) {
            $groupByName[$value['name']][$value['scope']] = $value;
        }

        $result = array();
        foreach ($groupByName as $name => $value) {
            if ($value[$this->scope])
                $current = $value[$this->scope];
            else
                $current = array('name' => $name);

            if ($current['value'])
                $current['value'] = $this->crypto->decrypt($current['value'], $this->cryptoKey);

            $order = array(self::SCOPE_SERVER, self::SCOPE_FARMROLE, self::SCOPE_FARM, self::SCOPE_ROLE, self::SCOPE_ENVIRONMENT);
            $index = array_search($this->scope, $order);

            if ($index)
                $order = array_slice($order, $index + 1);

            foreach ($order as $scope) {
                if ($value[$scope]) {
                    if (!$current['scope'])
                        $current['scope'] = $value[$scope]['scope'];

                    if (!$current['defaultValue'] || $current['defaultScope'] == $this->scope) {
                        // if we have other scope value, replace defaultValue with it (only once)
                        $current['defaultValue'] = $value[$scope]['value'] ? $this->crypto->decrypt($value[$scope]['value'], $this->cryptoKey) : '';
                        $current['defaultScope'] = $scope;
                    }

                    if ($value[$scope]['flagRequired'] == 1)
                        $current['flagRequiredGlobal'] = 1;

                    if ($value[$scope]['flagFinal'] == 1)
                        $current['flagFinalGlobal'] = 1;

                    if ($value[$scope]['flagHidden'] == 1)
                        $current['flagHiddenGlobal'] = 1;

                    if ($value[$scope]['validator'] || $value[$scope]['format']) {
                        $current['validator'] = $value[$scope]['validator'];
                        $current['format'] = $value[$scope]['format'];
                        $current['lockConfigure'] = true;
                    }
                }
            }

            if ($current['flagHiddenGlobal'] == 1) {
                if ($current['value'])
                    $current['value'] = '******';
                if ($current['defaultValue'])
                    $current['defaultValue'] = '******';
            }

            $result[] = $current;
        }

        return $result;
    }

    public function listVariables($roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $envVars = $this->db->GetAll("SELECT `name`, `value`, `format` FROM global_variables WHERE env_id = ? AND role_id = '0' AND farm_id = '0' AND farm_role_id = '0'", array($this->envId));

        if ($roleId)
            $roleVars = $this->db->GetAll("SELECT `name`, `value`, `format` FROM global_variables WHERE env_id = ? AND role_id = ? AND farm_id = '0' AND farm_role_id = '0'", array($this->envId, $roleId));

        if ($farmId)
            $farmVars = $this->db->GetAll("SELECT `name`, `value`, `format` FROM global_variables WHERE env_id = ? AND role_id = '0' AND farm_id = ? AND farm_role_id = '0'", array($this->envId, $farmId));

        if ($farmRoleId)
            $farmRoleVars = $this->db->GetAll("SELECT `name`, `value`, `format` FROM global_variables WHERE env_id = ? AND farm_role_id = ?", array($this->envId, $farmRoleId));

        if ($serverId)
            $serverVars = $this->db->GetAll("SELECT `name`, `value`, `format` FROM global_variables WHERE env_id = ? AND server_id = ?", array($this->envId, $serverId));

        $retval = array();
        foreach ($envVars as $var) {
            $retval[$var['name']] = $var['value'] ? $this->crypto->decrypt($var['value'], $this->cryptoKey) : '';
            if ($var['format'])
                $retval[$var['name']] = @sprintf($var['format'], $retval[$var['name']]);
        }

        if ($roleVars)
            foreach ($roleVars as $var) {
                $retval[$var['name']] = $var['value'] ? $this->crypto->decrypt($var['value'], $this->cryptoKey) : '';
                if ($var['format'])
                    $retval[$var['name']] = @sprintf($var['format'], $retval[$var['name']]);
            }

        if ($farmVars)
            foreach ($farmVars as $var) {
                $retval[$var['name']] = $var['value'] ? $this->crypto->decrypt($var['value'], $this->cryptoKey) : '';
                if ($var['format'])
                    $retval[$var['name']] = @sprintf($var['format'], $retval[$var['name']]);
            }

        if ($farmRoleVars)
            foreach ($farmRoleVars as $var) {
                $retval[$var['name']] = $var['value'] ? $this->crypto->decrypt($var['value'], $this->cryptoKey) : '';
                if ($var['format'])
                    $retval[$var['name']] = @sprintf($var['format'], $retval[$var['name']]);
            }

        if ($serverVars)
            foreach ($serverVars as $var) {
                $retval[$var['name']] = $var['value'] ? $this->crypto->decrypt($var['value'], $this->cryptoKey) : '';
                if ($var['format'])
                    $retval[$var['name']] = @sprintf($var['format'], $retval[$var['name']]);
            }

        return $retval;
    }
}
