<?php

use Scalr\Exception\ValidationErrorException;
use Scalr\DataType\ScopeInterface;

class Scalr_Scripting_GlobalVariables
{
    const FORMAT_JSON = 'json';

    private
        $accountId,
        $envId,
        $scope,
        $db,
        $crypto,
        $listScopes,
        $errors,
        $errorHandlerLastError;

    /**
     * This flag was developed for old API. If it's true and CaseSensitivity validation was failed,
     * we log message to SystemLog instead of Exception. If should be removed in next major version of Scalr.
     * @added v5.9.16
     *
     * @var bool
     */
    public $doNotValidateNameCaseSensitivity = false;

    /**
     * Array of predefined constants, which change default UI behavior
     * This data should be synchronized with /core/variablefield.js
     *
     * @var array
     */
    public $configurationVars = [
        'SCALR_UI_DEFAULT_STORAGE_RE_USE' => [
            'description' => 'Reuse block storage device if an instance is replaced.',
        ],
        'SCALR_UI_DEFAULT_REBOOT_AFTER_HOST_INIT' => [
            'description' => 'Reboot after HostInit Scripts have executed.'
        ],
        'SCALR_UI_DEFAULT_AUTO_SCALING' => [
            'description' => 'Auto-scaling is disabled (0) or enabled (1).'
        ],
        'SCALR_UI_DEFAULT_AWS_INSTANCE_INITIATED_SHUTDOWN_BEHAVIOR' => [
            'description' => 'AWS EC2 instance initiated shutdown behavior is suspend ("stop") or terminate ("terminate").',
            'validator'   => '/^(stop|terminate)$/'
        ]
    ];

    /**
     * Default values for UI config vars
     *
     * @var array
     */
    public $configurationVarsDefaults = [
        'category'      => 'SCALR_UI_DEFAULTS',
        'validator'     => '/^[01]$/',
        'flagRequired'  => 0,
        'flagHidden'    => 0,
        'format'        => ''
    ];

    /**
     * @param int $accountId
     * @param int $envId
     * @param string $scope
     */
    public function __construct($accountId = 0, $envId = 0, $scope = ScopeInterface::SCOPE_SCALR)
    {
        $this->crypto = \Scalr::getContainer()->crypto;

        $this->accountId = $accountId;
        $this->envId = $envId;
        $this->scope = $scope;
        $this->listScopes = [ScopeInterface::SCOPE_SCALR, ScopeInterface::SCOPE_ACCOUNT, ScopeInterface::SCOPE_ENVIRONMENT, ScopeInterface::SCOPE_ROLE,
            ScopeInterface::SCOPE_FARM, ScopeInterface::SCOPE_FARMROLE, ScopeInterface::SCOPE_SERVER];

        $this->db = \Scalr::getDb();
    }

    /**
     * Callback function for set_error_handler
     *
     * @param   int     $errno
     * @param   string  $errstr
     */
    private function errorHandler($errno, $errstr)
    {
        $this->errorHandlerLastError = str_replace("preg_match(): ", "", $errstr);
    }

    /**
     * Return first error message from set of validation errors
     *
     * @return  string
     */
    public function getErrorMessage()
    {
        $field = array_shift($this->errors);
        $errors = array_pop($field);
        return array_pop($errors);
    }

    /**
     * @param   int     $roleId
     * @param   int     $farmId
     * @param   int     $farmRoleId
     * @param   string  $serverId
     * @return  array   Array of variables [name of variable => data]
     */
    public function _getValues($roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $selectSql = "SELECT `name`, `value`, `category`, `flag_final` AS `flagFinal`, `flag_required` AS `flagRequired`, `flag_hidden` AS `flagHidden`, `format`, `validator`, `description`";
        $sql = array($selectSql . ", '" . ScopeInterface::SCOPE_SCALR . "' AS scope FROM variables");
        $args = array();
        $result = array();

        if ($this->accountId) {
            $sql[] = $selectSql . ", '" . ScopeInterface::SCOPE_ACCOUNT . "' AS scope FROM account_variables WHERE account_id = ?";
            $args[] = $this->accountId;
        }

        if ($this->envId) {
            $sql[] = $selectSql . ", '" . ScopeInterface::SCOPE_ENVIRONMENT . "' AS scope FROM client_environment_variables WHERE env_id = ?";
            $args[] = $this->envId;
        }

        if ($roleId) {
            $sql[] = $selectSql . ", '" . ScopeInterface::SCOPE_ROLE . "' AS scope FROM role_variables WHERE role_id = ?";
            $args[] = $roleId;
        }

        if ($farmId) {
            $sql[] = $selectSql . ", '" . ScopeInterface::SCOPE_FARM . "' AS scope FROM farm_variables WHERE farm_id = ?";
            $args[] = $farmId;
        }

        if ($farmRoleId) {
            $sql[] = $selectSql . ", '" . ScopeInterface::SCOPE_FARMROLE . "' AS scope FROM farm_role_variables WHERE farm_role_id = ?";
            $args[] = $farmRoleId;
        }

        if ($serverId) {
            $sql[] = $selectSql . ", '" . ScopeInterface::SCOPE_SERVER . "' AS scope FROM server_variables WHERE server_id = ?";
            $args[] = $serverId;
        }

        $variables = $this->db->GetAll(implode(" UNION ", $sql), $args);
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
                'category' => '',
                'lastValue' => ''
            );

            foreach ($values as $val) {
                if (!empty($val['value'])) {
                    $val['value'] = $this->crypto->decrypt($val['value']);
                    // to avoid empty value in higher scopes, save last not empty value
                    $variable['lastValue'] = $val['value'];
                }

                $variable['scopes'][] = $val['scope'];
                if ($val['category'] && !$variable['category']) {
                    $variable['category'] = $val['category'];
                }

                if ($val['flagRequired'] == 'off') {
                    $val['flagRequired'] = '';
                }

                if ($val['scope'] != $this->scope) {
                    if ($val['flagFinal'] == 1 || $val['flagRequired'] || $val['flagHidden'] == 1 || $val['format'] || $val['validator'] || $val['description'] || $val['category']) {
                        // don't override info if it was set on upper level
                        if (empty($variable['locked'])) {
                            $variable['locked'] = array(
                                'flagFinal' => $val['flagFinal'],
                                'flagRequired' => $val['flagRequired'],
                                'flagHidden' => $val['flagHidden'],
                                'format' => $val['format'],
                                'validator' => $val['validator'],
                                'description' => $val['description'],
                                'scope' => $val['scope'],
                                'value' => $val['value'],
                                'category' => $val['category']
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
     * Set error (errors are saved to this->errors)
     *
     * @param   string  $name       Name of variable
     * @param   string  $property   Property of variable
     * @param   string  $msg        Error message
     */
    protected function setError($name, $property, $msg)
    {
        if (empty($this->errors[$name])) {
            $this->errors[$name] = [];
        }

        if (empty($this->errors[$name][$property])) {
            $this->errors[$name][$property] = [];
        }

        $this->errors[$name][$property][] = $msg;
    }

    /**
     * Validate values
     *
     * @param   array|ArrayObject   $variables
     * @param   int                 $roleId
     * @param   int                 $farmId
     * @param   int                 $farmRoleId
     * @param   string              $serverId
     * @return  array|bool          Returns true if no errors or array of errors [name of variable => [name of property => [errors]]
     */
    public function validateValues($variables, $roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = "")
    {
        $this->errors = [];
        $usedNames = [];
        $existedNames = [];
        $currentValues = $this->_getValues($roleId, $farmId, $farmRoleId, $serverId);

        foreach ($currentValues as $v) {
            $existedNames[strtolower($v['name'])] = $v['name'];
        }

        foreach ($variables as $variable) {
            $deleteFlag = isset($variable['flagDelete']) && $variable['flagDelete'] == 1;

            $name = $variable['name'];
            if (empty($name)) {
                continue;
            }

            $lowerName = strtolower($name);

            if (!empty($variable['current'])) {
                $variable = $variable['current'];
            }

            // check for required variable
            if (!empty($currentValues[$name]['locked']) && $currentValues[$name]['locked']['flagRequired'] == $this->scope) {
                if (!(
                    $currentValues[$name]['default']['value'] != '' ||
                    isset($variable['value']) && trim($variable['value']) != ''
                )) {
                    $this->setError($name, 'value', sprintf('%s is required variable', $name));
                }
            }

            $variable['value'] = isset($variable['value']) ? trim($variable['value']) : "";
            if ($variable['value'] == '' && isset($currentValues[$name]['default'])) {
                $deleteFlag = true;
            }

            if ($deleteFlag) {
                continue;
            }

            $errorByteMessage = "Variable " . $variable['name'] . " contains invalid non-printable characters (e.g. NULL characters).
                You might have copied it from another application that submitted invalid characters.
                To solve this issue, you can type in the variable manually.";

            if (strpos($variable['value'], chr(0)) !== false) {
                $this->setError($name, 'value', $errorByteMessage);
            }

            if (!preg_match('/^[A-Za-z]{1,1}[A-Za-z0-9_]{1,127}$/', $name)) {
                $this->setError($name, 'name', "Name should contain only letters, numbers and underscores, start with letter and be from 2 to 128 chars long.");
            } else if (in_array($name, $usedNames)) {
                $this->setError($name, 'name', "This variable name is already in use.");
            } else {
                $usedNames[] = $lowerName;
            }

            if (array_key_exists($lowerName, $existedNames) && $existedNames[$lowerName] != $name) {
                if ($this->doNotValidateNameCaseSensitivity) {
                    \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->warn(new \FarmLogMessage(
                        !empty($farmId) ? $farmId : null,
                        sprintf('Variable "%s" has been already defined as "%s"',
                            !empty($name) ? $name : null,
                            !empty($existedNames[$lowerName]) ? $existedNames[$lowerName] : null
                        ),
                        !empty($serverId) ? $serverId : null,
                        null,
                        !empty($farmRoleId) ? $farmRoleId : null
                    ));
                } else {
                    $this->setError($name, 'name', sprintf("Name has been already defined as \"%s\"", $existedNames[$lowerName]));
                }
            }

            if ((substr($lowerName, 0, 6)) == 'scalr_' &&
                !array_key_exists($lowerName, $existedNames) &&
                !array_key_exists($name, $this->configurationVars) ||
                array_key_exists($name, $this->configurationVars) && !in_array($this->scope, [ScopeInterface::SCOPE_SCALR, ScopeInterface::SCOPE_ACCOUNT, ScopeInterface::SCOPE_ENVIRONMENT])
            ) {
                $this->setError($name, 'name', "Prefix 'SCALR_' is reserved and cannot be used for user GVs");
            }

            if (array_key_exists($name, $this->configurationVars) && empty($currentValues[$name]['default'])) {
                $variable = array_merge($variable, $this->configurationVarsDefaults, $this->configurationVars[$name]);
            }

            // set advanced flags only on first level
            if (!empty($currentValues[$name]['default'])) {
                $msg = "You can't redefine advanced settings (flags, format, validator, category)";
                if (!empty($variable['flagRequired'])) {
                    $this->setError($name, 'flagRequired', $msg);
                }

                if (!empty($variable['flagFinal']) && $variable['flagFinal'] == 1) {
                    $this->setError($name, 'flagFinal', $msg);
                }

                if (!empty($variable['flagHidden']) && $variable['flagHidden'] == 1) {
                    $this->setError($name, 'flagHidden', $msg);
                }

                if (!empty($variable['format'])) {
                    $this->setError($name, 'format', $msg);
                }

                if (!empty($variable['validator'])) {
                    $this->setError($name, 'validator', $msg);
                }

                if (!empty($variable['category'])) {
                    $this->setError($name, 'category', $msg);
                }

            } else {
                if (!empty($variable['flagRequired'])) {
                    if ($this->scope == ScopeInterface::SCOPE_FARMROLE || $this->scope == ScopeInterface::SCOPE_SERVER) {
                        $this->setError($name, 'flagRequired', "You can't set required flag on farmrole or server level");
                    } else {
                        $sc = $this->listScopes;
                        array_pop($sc); // exclude SERVER scope
                        $sc = array_slice($sc, array_search($this->scope, $sc) + 1);

                        if (! in_array($variable['flagRequired'], $sc)) {
                            $this->setError($name, 'flagRequired', 'Wrong required scope');
                        }
                    }
                }

                if (!empty($variable['flagFinal']) && $variable['flagFinal'] == 1 && !empty($variable['flagRequired'])) {
                    $this->setError($name, 'flagFinal', "You can't set final and required flags both");
                    $this->setError($name, 'flagRequired', "You can't set final and required flags both");
                }

                if (!empty($variable['validator'])) {
                    if (strpos($variable['validator'], chr(0)) !== false) {
                        $this->setError($name, 'validator', "Validation pattern is not valid (NULL byte)");
                    } else {
                        $this->errorHandlerLastError = '';
                        if (preg_match('/^\/(.*)\/[imsxADSUXu]*$/', $variable['validator']) == 1) {
                            set_error_handler([$this, 'errorHandler']);
                            preg_match($variable['validator'], 'test');
                            restore_error_handler();
                        } else {
                            $this->errorHandlerLastError = 'invalid structure';
                        }

                        if ($this->errorHandlerLastError) {
                            $this->setError($name, 'validator', sprintf("Validation pattern is not valid: %s", $this->errorHandlerLastError));
                        } else if ($variable['value'] != '') {
                            if (preg_match($variable['validator'], $variable['value']) != 1) {
                                $this->setError($name, 'value', "Value isn't valid because of validation pattern");
                            }
                        }
                    }
                }

                if (!empty($variable['format'])) {
                    if ($variable['format'] == self::FORMAT_JSON) {
                        json_decode($variable['value']);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $this->setError($name, 'value', "The value is not valid JSON");
                        }
                    } else {
                        $cnt = count_chars($variable['format']);
                        if ($cnt[ord('%')] != 1) {
                            $this->setError($name, 'format', "Format isn't valid");
                        }
                    }
                }

                if (!empty($variable['category'])) {
                    if (preg_match('/^[A-Za-z0-9]+[A-Za-z0-9-_]*[A-Za-z0-9]+$/i', $variable['category']) != 1 || strlen($variable['category']) > 31) {
                        $this->setError($name, 'category', "Category should contain only letters, numbers, dashes and underscores, start and end with letter and be from 2 to 32 chars long");
                    }

                    if (substr(strtolower($variable['category']), 0, 6) == 'scalr_' && !array_key_exists($name, $this->configurationVars)) {
                        $this->setError($name, 'category', "Prefix 'SCALR_' is reserved and cannot be used for user GVs");
                    }
                }
            }

            if (!empty($currentValues[$name]['locked'])) {
                if ($currentValues[$name]['locked']['flagFinal'] && ($variable['value'] != '')) {
                    $this->setError($name, 'value', sprintf('You can\'t change final variable locked on %s level', $currentValues[$name]['locked']['scope']));
                }

                if ($currentValues[$name]['locked']['validator'] && ($variable['value'] != '')) {
                    $validator = $currentValues[$name]['locked']['validator'];
                    if (preg_match($validator, $variable['value']) != 1) {
                        $this->setError($name, 'value', "Value isn't valid because of validation pattern");
                    }
                }

                if ($currentValues[$name]['locked']['format'] == self::FORMAT_JSON) {
                    json_decode($variable['value']);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->setError($name, 'value', "The value is not valid JSON");
                    }
                }
            }
        }

        return count($this->errors) ? $this->errors : true;
    }

    /**
     * @param   array|ArrayObject $variables
     * @param   int               $roleId          optional
     * @param   int               $farmId          optional
     * @param   int               $farmRoleId      optional
     * @param   string            $serverId        optional
     * @param   bool              $throwException  optional
     * @param   bool              $skipValidation  optional
     * @return  array|bool
     */
    public function setValues($variables, $roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '', $throwException = true, $skipValidation = false)
    {
        if (!$skipValidation) {
            $validResult = $this->validateValues($variables, $roleId, $farmId, $farmRoleId, $serverId);
            if ($validResult !== true) {
                if ($throwException)
                    throw new ValidationErrorException($this->getErrorMessage());
                else
                    return $validResult;
            }
        }

        $currentValues = $this->_getValues($roleId, $farmId, $farmRoleId, $serverId);

        foreach ($variables as $variable) {
            $deleteFlag = (!empty($variable['flagDelete']) && $variable['flagDelete'] == 1) ? true : false;
            $updateValue = true;

            $name = $variable['name'];

            if (empty($name))
                continue;

            if (empty($variable['current']) && !empty($variable['default'])) {
                if (!empty($currentValues[$name]['current'])) {
                    $deleteFlag = true;
                } else if (!$deleteFlag) {
                    continue;
                }

                $variable = $variable['default'];
            } else if (!empty($variable['current'])) {
                $variable = $variable['current'];
            }

            if (array_key_exists($name, $this->configurationVars) && empty($currentValues[$name]['default'])) {
                $variable = array_merge($variable, $this->configurationVarsDefaults, $this->configurationVars[$name]);
            }

            $variable['value'] = isset($variable['value']) ? trim($variable['value']) : '';
            if ($variable['value'] != '') {
                $variable['value'] = $this->crypto->encrypt($variable['value']);
            } else {
                if (isset($currentValues[$name]['default']))
                    $deleteFlag = true;
            }

            if ($deleteFlag) {
                $sql = array("`name` = ?");
                $params = array($name);
            } else {
                $sql = $sqlUpdate = [
                    "`flag_final` = ?",
                    "`flag_required` = ?",
                    "`flag_hidden` = ?",
                    "`validator` = ?",
                    "`format` = ?",
                    "`description` = ?",
                    "`category` = ?"
                ];

                $sql[] = "`name` = ?";

                $params = array(
                    !empty($variable['flagFinal']) && $variable['flagFinal'] == 1 ? 1 : 0,
                    !empty($variable['flagRequired']) ? $variable['flagRequired'] : 'off',
                    !empty($variable['flagHidden']) && $variable['flagHidden'] == 1 ? 1 : 0,
                    !empty($variable['validator']) ? $variable['validator'] : '',
                    !empty($variable['format']) ? $variable['format'] : '',
                    !empty($variable['description']) ? $variable['description'] : '',
                    !empty($variable['category']) ? strtolower($variable['category']) : '',
                    $name
                );

                $sqlUpdateParams = $params;
                array_pop($sqlUpdateParams);

                if ($updateValue) {
                    $sql[] = "`value` = ?";
                    $params[] = $variable["value"];
                    $sqlUpdate[] = "`value` = ?";
                    $sqlUpdateParams[] = $variable["value"];
                }
            }

            switch ($this->scope) {
                case ScopeInterface::SCOPE_SCALR:
                    $table = "variables";
                    break;
                case ScopeInterface::SCOPE_ACCOUNT:
                    $table = "account_variables";
                    $sql[] = "account_id = ?";
                    $params[] = $this->accountId;
                    break;
                case ScopeInterface::SCOPE_ENVIRONMENT:
                    $table = "client_environment_variables";
                    $sql[] = "env_id = ?";
                    $params[] = $this->envId;
                    break;
                case ScopeInterface::SCOPE_ROLE:
                    $table = "role_variables";
                    $sql[] = "role_id = ?";
                    $params[] = $roleId;
                    break;
                case ScopeInterface::SCOPE_FARM:
                    $table = "farm_variables";
                    $sql[] = "farm_id = ?";
                    $params[] = $farmId;
                    break;
                case ScopeInterface::SCOPE_FARMROLE:
                    $table = "farm_role_variables";
                    $sql[] = "farm_role_id = ?";
                    $params[] = $farmRoleId;
                    break;
                case ScopeInterface::SCOPE_SERVER:
                    $table = "server_variables";
                    $sql[] = "server_id = ?";
                    $params[] = $serverId;
                    break;
            }
            if ($deleteFlag) {
                $this->db->Execute("DELETE FROM `{$table}` WHERE " . implode(" AND ", $sql), $params);
            } else {
                $this->db->Execute("INSERT INTO `{$table}` SET " . implode(",", $sql) . " ON DUPLICATE KEY UPDATE " . implode(",", $sqlUpdate), array_merge($params, $sqlUpdateParams));
            }
        }

        return true;
    }

    public function getValues($roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $values = array_values($this->_getValues($roleId, $farmId, $farmRoleId, $serverId));
        foreach ($values as &$value) {
            if (!empty($value['locked']) && ($value['locked']['flagHidden'] == 1)) {
                if ($value['default'] && $value['default']['value'])
                    $value['default']['value'] = '******';

                if ($value['locked']['value'])
                    $value['locked']['value'] = '******';
            }
            unset($value['lastValue']);
        }

        return $values;
    }

    /**
     * Return default configuration vars for UI as name => value
     *
     * @return array
     */
    public function getUiDefaults()
    {
        $data = [];
        foreach ($this->getValues() as $var) {
            if (array_key_exists($var['name'], $this->configurationVars)) {
                $data[$var['name']] = !empty($var['current']) ? $var['current']['value'] : $var['default']['value'];
            }
        }

        return $data;
    }

    public function listVariables($roleId = 0, $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $retval = array();
        foreach ($this->_getValues($roleId, $farmId, $farmRoleId, $serverId) as $name => $var) {
            if (strtolower(substr($name, 0, 9)) == 'scalr_ui_') {
                continue;
            }

            $value = !empty($var['current']) ? $var['current']['value'] : $var['default']['value'];
            if ($value == '')
                $value = $var['lastValue'];

            if (!empty($var['locked']) && $var['locked']['flagFinal'] == 1) {
                $value = $var['locked']['value'];
            }

            if (!empty($var['locked']) && $var['locked']['format']) {
                $value = @sprintf($var['locked']['format'], $value);
            } else if (!empty($var['current']) && $var['current']['format']) {
                $value = @sprintf($var['current']['format'], $value);
            }

            $retval[] = array(
                'name' => $name,
                'value' => $value,
                'private' => (!empty($var['locked']) && ($var['locked']['flagHidden'] == 1) || !empty($var['current']) && ($var['current']['flagHidden'] == 1)) ? 1 : 0
            );
        }

        return $retval;
    }

    public static function listServerGlobalVariables(DBServer $dbServer, $includeSystem = false, AbstractServerEvent $event = null)
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
            $globalVariables = new Scalr_Scripting_GlobalVariables($dbServer->GetEnvironmentObject()->clientId, $dbServer->envId, ScopeInterface::SCOPE_SERVER);
            $vars = $globalVariables->listVariables($dbServer->GetFarmRoleObject()->RoleID, $dbServer->farmId, $dbServer->farmRoleId, $dbServer->serverId);
            foreach ($vars as $v)
                $retval[] = (object)$v;
        } catch (Exception $e) {}

        return $retval;
    }
}
