<?php

class Scalr_Scripting_GlobalVariables
{
    const SCOPE_SCALR = 'scalr';
    const SCOPE_ACCOUNT = 'account';
    const SCOPE_ENVIRONMENT = 'env';
    const SCOPE_ROLE = 'role';
    const SCOPE_FARM = 'farm';
    const SCOPE_FARMROLE = 'farmrole';
    const SCOPE_SERVER = 'server';

    private
        $accountId,
        $envId,
        $scope,
        $db,
        $crypto,
        $cryptoKey,
        $listScopes;

    /**
     * @param int $accountId
     * @param int $envId
     * @param string $scope
     */
    public function __construct($accountId = 0, $envId = 0, $scope = Scalr_Scripting_GlobalVariables::SCOPE_SCALR)
    {
        $this->crypto = \Scalr::getContainer()->crypto;

        $this->accountId = $accountId;
        $this->envId = $envId;
        $this->scope = $scope;
        $this->listScopes = [self::SCOPE_SCALR, self::SCOPE_ACCOUNT, self::SCOPE_ENVIRONMENT, self::SCOPE_ROLE, self::SCOPE_FARM, self::SCOPE_FARMROLE, self::SCOPE_SERVER];

        $this->db = \Scalr::getDb();
    }

    /**
     * @param array $result
     * @return mixed
     */
    public function getErrorMessage(array $result)
    {
        $field = array_shift($result);
        return array_shift($field);
    }

    /**
     * @param int $roleId
     * @param int $farmId
     * @param int $farmRoleId
     * @param string $serverId
     * @return array
     */
    public function _getValues($roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $selectSql = 'SELECT `name`, `value`, `flag_final` AS `flagFinal`, `flag_required` AS `flagRequired`, `flag_hidden` AS `flagHidden`, `format`, `validator`';
        $sql = array($selectSql . ', "scalr" AS scope FROM variables');
        $args = array();
        $result = array();

        if ($this->accountId) {
            $sql[] = $selectSql . ', "account" AS scope FROM account_variables WHERE account_id = ?';
            $args[] = $this->accountId;
        }

        if ($this->envId) {
            $sql[] = $selectSql . ', "env" AS scope FROM client_environment_variables WHERE env_id = ?';
            $args[] = $this->envId;
        }

        if ($roleId) {
            $sql[] = $selectSql . ', "role" AS scope FROM role_variables WHERE role_id = ?';
            $args[] = $roleId;
        }

        if ($farmId) {
            $sql[] = $selectSql . ', "farm" AS scope FROM farm_variables WHERE farm_id = ?';
            $args[] = $farmId;
        }

        if ($farmRoleId) {
            $sql[] = $selectSql . ', "farmrole" AS scope FROM farm_role_variables WHERE farm_role_id = ?';
            $args[] = $farmRoleId;
        }

        if ($serverId) {
            $sql[] = $selectSql . ', "server" AS scope FROM server_variables WHERE server_id = ?';
            $args[] = $serverId;
        }

        $variables = $this->db->GetAll(implode(' UNION ', $sql), $args);
        $scopes = array_flip($this->listScopes);
        $groupByName = array();
        foreach ($variables as $variable) {
            $groupByName[$variable['name']][$scopes[$variable['scope']]] = $variable;
        }

        foreach ($groupByName as $name => $values) {
            ksort($values);
            $variable = array(
                'name' => $name,
                'scopes' => [],
                'lastValue' => ''
            );

            foreach ($values as $val) {
                if ($val['value']) {
                    $val['value'] = $this->crypto->decrypt($val['value']);
                    // to avoid empty value in higher scopes, save last not empty value
                    $variable['lastValue'] = $val['value'];
                }

                $variable['scopes'][] = $val['scope'];

                if ($val['scope'] != $this->scope) {
                    if ($val['flagFinal'] == 1 || $val['flagRequired'] || $val['flagHidden'] == 1 || $val['format'] || $val['validator']) {
                        // don't override info if it was set on upper level
                        if (! $variable['locked']) {
                            $variable['locked'] = array(
                                'flagFinal' => $val['flagFinal'],
                                'flagRequired' => $val['flagRequired'],
                                'flagHidden' => $val['flagHidden'],
                                'format' => $val['format'],
                                'validator' => $val['validator'],
                                'scope' => $val['scope'],
                                'value' => $val['value']
                            );
                        }
                    }

                    $variable['default'] = array(
                        'name' => $val['name'],
                        'value' => $val['value'],
                        'scope' => $val['scope']
                    );
                } else {
                    $variable['current'] = $val;
                }
            }

            $result[$name] = $variable;
        }

        return $result;
    }

    /**
     * @param array|ArrayObject $variables
     * @param int $roleId
     * @param int $farmId
     * @param int $farmRoleId
     * @param string $serverId
     * @return array|bool
     */
    public function validateValues($variables, $roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $errors = array();
        $usedNames = array();
        $currentValues = $this->_getValues($roleId, $farmId, $farmRoleId, $serverId);

        foreach ($variables as $variable) {
            $deleteFlag = ($variable['flagDelete'] == 1) ? true : false;

            $name = $variable['name'];
            if (empty($name))
                continue;

            // check for required variable, because if it doesn't have value, it won't have current section
            if ($variable['default'] && $currentValues[$name]['locked'] && $currentValues[$name]['locked']['flagRequired'] == $this->scope) {
                if (! (
                    $variable['default']['value'] != '' ||
                    $variable['current'] && trim($variable['current']['value']) != ''
                )) {
                    $errors[$name]['value'] = sprintf('%s is required variable', $name);
                }
            }

            if ($variable['current'])
                $variable = $variable['current'];

            $variable['value'] = trim($variable['value']);
            if ($variable['value'] == '' && isset($currentValues[$name]['default']))
                $deleteFlag = true;

            if ($deleteFlag)
                continue;

            $errors[$name] = array();
            if (! preg_match('/^[A-Za-z]{1,1}[A-Za-z0-9_]{1,49}$/', $name)) {
                $errors[$name]['name'] = 'Invalid name';
            } else if (in_array($name, $usedNames)) {
                $errors[$name]['name'] = 'Duplicate name';
            } else {
                $usedNames[] = $name;
            }

            // set advanced flags only on first level
            if ($currentValues['name']['default']) {
                $msg = 'You can\'t redefine advanced settings (flags, format, validator)';
                if ($variable['flagRequired'])
                    $errors[$name]['flagRequired'] = $msg;

                if ($variable['flagFinal'] == 1)
                    $errors[$name]['flagFinal'] = $msg;

                if ($variable['flagHidden'] == 1)
                    $errors[$name]['flagHidden'] = $msg;

                if ($variable['format'])
                    $errors[$name]['format'] = $msg;

                if ($variable['validator'])
                    $errors[$name]['validator'] = $msg;
            } else {
                if ($variable['flagRequired']) {
                    if ($this->scope == self::SCOPE_FARMROLE || $this->scope == self::SCOPE_SERVER) {
                        $errors[$name]['flagRequired'] = 'You can\'t set required flag on farmrole or server level';
                    } else {
                        $sc = $this->listScopes;
                        array_pop($sc); // exclude SERVER scope
                        $sc = array_slice($sc, array_search($this->scope, $sc) + 1);

                        if (! in_array($variable['flagRequired'], $sc))
                            $errors[$name]['flagRequired'] = 'Wrong required scope';
                    }
                }

                if ($variable['flagFinal'] == 1 && $variable['flagRequired']) {
                    $errors[$name]['flagFinal'] = $errors[$name]['flagRequired'] = 'You can\'t set final and required flags both';
                }

                if ($variable['validator'] && $variable['value'] != '') {
                    $validator = $variable['validator'];
                    if ($validator[0] != '/')
                        $validator = '/' . $validator . '/';

                    if (preg_match($validator, $variable['value']) != 1)
                        $errors[$name]['value'] = 'Value isn\'t valid because of validation pattern';
                }

                if ($variable['format']) {
                    $cnt = count_chars($variable['format']);
                    if ($cnt[ord('%')] != 1)
                        $errors[$name]['format'] = 'Format isn\'t valid';
                }
            }

            if ($currentValues[$name]['locked']) {
                if ($currentValues[$name]['locked']['flagFinal'] && $variable['value']) {
                    $errors[$name]['value'] = sprintf('You can\'t change final variable locked on %s level', $currentValues[$name]['locked']['scope']);
                }

                if ($currentValues[$name]['locked']['validator'] && $variable['value']) {
                    $validator = $currentValues[$name]['locked']['validator'];
                    if ($validator[0] != '/')
                        $validator = '/' . $validator . '/';

                    if (preg_match($validator, $variable['value']) != 1)
                        $errors[$name]['value'] = 'Value isn\'t valid because of validation pattern';
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
     * @param array|ArrayObject $variables
     * @param int $roleId
     * @param int $farmId
     * @param int $farmRoleId
     * @param string $serverId
     * @param bool $throwException
     * @param bool $skipValidation
     * @throws Scalr_Exception_Core
     * @return array|bool
     */
    public function setValues($variables, $roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '', $throwException = true, $skipValidation = false)
    {
        if (!$skipValidation) {
            $validResult = $this->validateValues($variables, $roleId, $farmId, $farmRoleId, $serverId);
            if ($validResult !== true) {
                if ($throwException)
                    throw new Scalr_Exception_Core($this->getErrorMessage($validResult));
                else
                    return $validResult;
            }
        }

        $currentValues = $this->_getValues($roleId, $farmId, $farmRoleId, $serverId);

        foreach ($variables as $variable) {
            $deleteFlag = ($variable['flagDelete'] == 1) ? true : false;
            $updateValue = true;

            $name = $variable['name'];
            if (empty($name))
                continue;

            if (! $variable['current'] && $variable['default']) {
                if ($currentValues[$name]['current']) {
                    $deleteFlag = true;
                } else if (! $deleteFlag) {
                    continue;
                }

                $variable = $variable['default'];
            } else if ($variable['current']) {
                $variable = $variable['current'];
            }

            $variable['value'] = trim($variable['value']);
            if ($variable['value'] != '') {
                $variable['value'] = $this->crypto->encrypt($variable['value']);
            } else {
                if (isset($currentValues[$name]['default']))
                    $deleteFlag = true;
            }

            if ($deleteFlag) {
                $sql = array('`name` = ?');
                $params = array($name);
            } else {
                $sql = array(
                    '`name` = ?',
                    '`flag_final` = ?',
                    '`flag_required` = ?',
                    '`flag_hidden` = ?',
                    '`validator` = ?',
                    '`format` = ?'
                );
                $sqlUpdate = array(
                    '`flag_final` = ?',
                    '`flag_required` = ?',
                    '`flag_hidden` = ?',
                    '`validator` = ?',
                    '`format` = ?'
                );

                $params = array(
                    $name,
                    $variable['flagFinal'] == 1 ? 1 : 0,
                    $variable['flagRequired'] ? $variable['flagRequired'] : '',
                    $variable['flagHidden'] == 1 ? 1 : 0,
                    $variable['validator'] ? $variable['validator'] : '',
                    $variable['format'] ? $variable['format'] : ''
                );

                $sqlUpdateParams = $params;
                array_shift($sqlUpdateParams);

                if ($updateValue) {
                    $sql[] = '`value` = ?';
                    $params[] = $variable['value'];
                    $sqlUpdate[] = '`value` = ?';
                    $sqlUpdateParams[] = $variable['value'];
                }
            }

            switch ($this->scope) {
                case self::SCOPE_SCALR:
                    $table = 'variables';
                    break;
                case self::SCOPE_ACCOUNT:
                    $table = 'account_variables';
                    $sql[] = 'account_id = ?';
                    $params[] = $this->accountId;
                    break;
                case self::SCOPE_ENVIRONMENT:
                    $table = 'client_environment_variables';
                    $sql[] = 'env_id = ?';
                    $params[] = $this->envId;
                    break;
                case self::SCOPE_ROLE:
                    $table = 'role_variables';
                    $sql[] = 'role_id = ?';
                    $params[] = $roleId;
                    break;
                case self::SCOPE_FARM:
                    $table = 'farm_variables';
                    $sql[] = 'farm_id = ?';
                    $params[] = $farmId;
                    break;
                case self::SCOPE_FARMROLE:
                    $table = 'farm_role_variables';
                    $sql[] = 'farm_role_id = ?';
                    $params[] = $farmRoleId;
                    break;
                case self::SCOPE_SERVER:
                    $table = 'server_variables';
                    $sql[] = 'server_id = ?';
                    $params[] = $serverId;
                    break;
            }
            if ($deleteFlag) {
                $this->db->Execute("DELETE FROM `{$table}` WHERE " . implode(' AND ', $sql), $params);
            } else {
                $this->db->Execute("INSERT INTO `{$table}` SET " . implode(',', $sql) . " ON DUPLICATE KEY UPDATE " . implode(',', $sqlUpdate), array_merge($params, $sqlUpdateParams));
            }
        }

        return true;
    }

    public function getValues($roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $values = array_values($this->_getValues($roleId, $farmId, $farmRoleId, $serverId));
        foreach ($values as &$value) {
            if ($value['locked'] && ($value['locked']['flagHidden'] == 1)) {
                if ($value['default'] && $value['default']['value'])
                    $value['default']['value'] = '******';

                if ($value['locked']['value'])
                    $value['locked']['value'] = '******';
            }
            unset($value['lastValue']);
        }

        return $values;
    }

    public function listVariables($roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $retval = array();
        foreach ($this->_getValues($roleId, $farmId, $farmRoleId, $serverId) as $name => $var) {
            $value = $var['current'] ? $var['current']['value'] : $var['default']['value'];
            if ($value == '')
                $value = $var['lastValue'];

            if ($var['locked'] && $var['locked']['flagFinal'] == 1) {
                $value = $var['locked']['value'];
            }

            if ($var['locked'] && $var['locked']['format']) {
                $value = @sprintf($var['locked']['format'], $value);
            } else if ($var['current'] && $var['current']['format']) {
                $value = @sprintf($var['current']['format'], $value);
            }

            $retval[] = array(
                'name' => $name,
                'value' => $value,
                'private' => ($var['locked'] && ($var['locked']['flagHidden'] == 1) || $var['current'] && ($var['current']['flagHidden'] == 1)) ? 1 : 0
            );
        }

        return $retval;
    }

    public static function listServerGlobalVariables(DBServer $dbServer, $includeSystem = false, Event $event = null)
    {
        $retval = array();

        if ($includeSystem) {
            $variables = $dbServer->GetScriptingVars();

            if ($event) {
                if ($event->DBServer)
                foreach ($event->DBServer->GetScriptingVars() as $k => $v)
                    $variables["event_{$k}"] = $v;

                foreach ($event->GetScriptingVars() as $k=>$v)
                    $variables[$k] = $event->{$v};

                if (isset($event->params) && is_array($event->params))
                foreach ($event->params as $k=>$v)
                    $variables[$k] = $v;

                $variables['event_name'] = $event->GetName();
            }

            $formats = \Scalr::config("scalr.system.global_variables.format");
            foreach ($variables as $name => $value) {
                $name = "SCALR_".strtoupper($name);
                $value = trim($value);

                if (isset($formats[$name]))
                    $value = @sprintf($formats[$name], $value);

                $private = (strpos($name, 'SCALR_EVENT_') === 0) ? 1 : 0;

                $retval[] = (object)array('name' => $name, 'value' => $value, 'private' => $private, 'system' => 1);
            }
        }

        try {
            $globalVariables = new Scalr_Scripting_GlobalVariables($dbServer->GetEnvironmentObject()->clientId, $dbServer->envId, Scalr_Scripting_GlobalVariables::SCOPE_SERVER);
            $vars = $globalVariables->listVariables($dbServer->GetFarmRoleObject()->RoleID, $dbServer->farmId, $dbServer->farmRoleId, $dbServer->serverId);
            foreach ($vars as $v)
                $retval[] = (object)$v;
        } catch (Exception $e) {}

        return $retval;
    }
}
