<?php

class Scalr_UI_Request
{
    protected
        $params = [],
        $definitions = [],
        $requestParams = [],
        $requestHeaders = [],
        $requestServer = [],
        $requestFiles = [],
        $user,
        $environment,
        $requestType,
        $paramErrors = [],
        $paramsIsValid = true,
        $clientIp = null;

    /**
     * Acl roles for this user and environment
     *
     * @var \Scalr\Acl\Role\AccountRoleSuperposition
     */
    protected $aclRoles;

    public $requestApiVersion;

    const REQUEST_TYPE_UI = 'ui';
    const REQUEST_TYPE_API = 'api';

    /**
     *
     * @var Scalr_UI_Request
     */
    private static $_instance = null;

    /**
     * @return Scalr_UI_Request
     * @throws Scalr_Exception_Core
     */
    public static function getInstance()
    {
        if (self::$_instance === null)
            throw new Scalr_Exception_Core('Scalr_UI_Request not initialized');

        return self::$_instance;
    }

    public function __construct($type, $headers, $server, $params, $files)
    {
        $this->requestType = $type;

        if (is_array($headers)) {
            foreach ($headers as $key => $value)
                $this->requestHeaders[strtolower($key)] = $value;
        }

        // TODO: replace $_SERVER usage with class method
        $this->requestServer = $server;
        $this->requestParams = $params;
        $this->requestFiles = $files;
    }

    /**
     * @param $type
     * @param $headers
     * @param $server
     * @param $params
     * @param $files
     * @param $userId
     * @param $envId
     * @return Scalr_UI_Request
     * @throws Scalr_Exception_Core
     * @throws Exception
     */
    public static function initializeInstance($type, $headers, $server, $params, $files, $userId, $envId)
    {
        if (self::$_instance)
            self::$_instance = null;

        $class = get_called_class();

        $instance = new $class($type, $headers, $server, $params, $files);

        if ($userId) {
            try {
                $user = Scalr_Account_User::init();
                $user->loadById($userId);
            } catch (Exception $e) {
                throw new Exception('User account is no longer available.');
            }

            if ($user->status != Scalr_Account_User::STATUS_ACTIVE)
                throw new Exception('User account has been deactivated. Please contact your account owner.');

            if (! $user->isAdmin()) {
                $environment = $user->getDefaultEnvironment($envId);
                $user->getPermissions()->setEnvironmentId($environment->id);
            }

            if ($user->getAccountId()) {
                if ($user->getAccount()->status == Scalr_Account::STATUS_INACIVE) {
                    if ($user->getType() == Scalr_Account_User::TYPE_TEAM_USER)
                        throw new Exception('Scalr account has been deactivated. Please contact scalr team.');
                } else if ($user->getAccount()->status == Scalr_Account::STATUS_SUSPENDED) {
                    if ($user->getType() == Scalr_Account_User::TYPE_TEAM_USER)
                        throw new Exception('Account was suspended. Please contact your account owner to solve this situation.');
                }
            }

            $ipWhitelist = $user->getVar(Scalr_Account_User::VAR_SECURITY_IP_WHITELIST);
            if ($ipWhitelist) {
                $ipWhitelist = unserialize($ipWhitelist);
                if (! Scalr_Util_Network::isIpInSubnets($instance->getRemoteAddr(), $ipWhitelist))
                    throw new Exception('The IP address isn\'t authorized.');
            }

            // check header's variables
            $headerUserId = !is_null($instance->getHeaderVar('UserId')) ? intval($instance->getHeaderVar('UserId')) : null;
            $headerEnvId = !is_null($instance->getHeaderVar('EnvId')) ? intval($instance->getHeaderVar('EnvId')) : null;

            if (!empty($headerUserId) && $headerUserId != $user->getId())
                throw new Scalr_Exception_Core('Session expired. Please refresh page.', 1);

            if (!empty($headerEnvId) && !empty($environment) && $headerEnvId != $environment->id)
                throw new Scalr_Exception_Core('Session expired. Please refresh page.', 1);

            $instance->user = $user;
            $instance->environment = isset($environment) ? $environment : null;
        }

        $container = \Scalr::getContainer();
        $container->request = $instance;
        $container->environment = isset($instance->environment) ? $instance->environment : null;

        self::$_instance = $instance;
        return $instance;
    }

    /**
     *
     * @return Scalr_Account_User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Gets an environment instance which is associated with the request
     *
     * @return \Scalr_Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    public function getRequestType()
    {
        return $this->requestType;
    }

    public function getHeaderVar($name)
    {
        $name = strtolower("X-Scalr-{$name}");
        return isset($this->requestHeaders[$name]) ? $this->requestHeaders[$name] : NULL;
    }

    public function defineParams($defs)
    {
        foreach ($defs as $key => $value) {
            if (is_array($value))
                $this->definitions[$key] = $value;

            if (is_string($value))
                $this->definitions[$value] = array();
        }

        $this->params = array();
    }

    public function getRequestParam($key)
    {
        $key = str_replace('.', '_', $key);

        if (isset($this->requestParams[$key]))
            return $this->requestParams[$key];
        else
            return NULL;
    }

    public function hasParam($key)
    {
        return isset($this->requestParams[$key]);
    }

    public function getRemoteAddr()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    }

    public function setParam($key, $value)
    {
        $this->requestParams[$key] = $value;
        $this->params[$key] = $value;
    }

    public function setParams($params)
    {
        $this->requestParams = array_merge($this->requestParams, $params);
    }

    /**
     * clear string from prohibited symbols
     *
     * @param $value string
     * @return string
     */
    public function stripValue($value)
    {
        $value = strip_tags($value);
        $value = str_replace(array('>', '<'), '', $value);
        $value = trim($value);

        return $value;
    }

    /**
     * @deprecated all parameters should be defined in function's arguments
     * @param $key
     * @param bool $rawValue if true returns rawValue (not stripped) only once, don't save in cache
     * @return mixed
     */
    public function getParam($key, $rawValue = false)
    {
        $value = null;

        if (isset($this->params[$key]) && !$rawValue)
            return $this->params[$key];

        if (isset($this->definitions[$key])) {
            $value = $this->getRequestParam($key);
            $rule = $this->definitions[$key];

            if ($value == NULL && isset($rule['default'])) {
                $value = $rule['default'];
            } else {
                switch (isset($rule['type']) ? $rule['type'] : null) {
                    case 'integer':
                    case 'int':
                        $value = intval($value);
                        break;

                    case 'bool':
                        $value = ($value == 'true' || $value == 'false') ?
                            ($value == 'true' ? true : false) : (bool) $value;
                        break;

                    case 'json':
                        $value = is_array($value) ? $value : json_decode($value, true);
                        break;

                    case 'array':
                        settype($value, 'array');
                        break;

                    case 'string':
                    default:
                        $value = strval($value);

                        if ($rawValue)
                            return $value;

                        if (! (isset($rule['rawValue']) && $rule['rawValue']))
                            $value = $this->stripValue($value);

                        break;
                }
            }

            $this->params[$key] = $value;
        } else {
            $value = $this->getRequestParam($key);

            if ($rawValue)
                return $value;

            $value = strval($value);
            $value = $this->stripValue($value);
            $this->params[$key] = $value;
        }

        return $this->params[$key];
    }

    public function getParams()
    {
        foreach ($this->definitions as $key => $value) {
            $this->getParam($key);
        }

        return $this->params;
    }

    public function getFileName($name)
    {
        return (isset($this->requestFiles[$name]) && is_readable($this->requestFiles[$name]['tmp_name'])) ? $this->requestFiles[$name]['tmp_name'] : NULL;
    }

    /**
     *
     * @return Scalr_UI_Request
     */
    public function validate()
    {
        $this->paramErrors = array();
        $this->paramsIsValid = true;
        $validator = new Scalr_Validator();

        foreach ($this->definitions as $key => $value) {
            if (isset($value['validator'])) {
                $result = $validator->validate($this->getParam($key), $value['validator']);
                if ($result !== true)
                    $this->addValidationErrors($key, $result);
            }
        }

        if (count($this->paramErrors))
            $this->paramsIsValid = false;

        return $this;
    }

    public function isValid()
    {
        return $this->paramsIsValid;
    }

    /**
     * @param $field string
     * @param $errors array|string
     */
    public function addValidationErrors($field, $errors)
    {
        $this->paramsIsValid = false;
        if (! isset($this->paramErrors[$field]))
            $this->paramErrors[$field] = array();

        if (is_string($errors))
            $errors = array($errors);

        $this->paramErrors[$field] = array_merge($this->paramErrors[$field], $errors);
    }

    public function getValidationErrors()
    {
        return array('errors' => $this->paramErrors);
    }

    public function getValidationErrorsMessage()
    {
        $message = '';
        foreach ($this->paramErrors as $key => $value) {
            $message .= "Field '{$key}' has following errors: <ul>";
            foreach ($value as $error)
                $message .= "<li>{$error}</li>";
            $message .= "</ul>";
        }

        return $message;
    }

    /**
     * Gets client ip address
     *
     * @return string Returns client ip address.
     */
    public static function getClientIpAddress()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Gets client ip address for the current request
     *
     * @returns string Returns client ip address for the current request.
     */
    public function getClientIp()
    {
        if ($this->clientIp === null) {
            $this->clientIp = self::getClientIpAddress();
        }
        return $this->clientIp;
    }

    /**
     * Gets acl roles superposition for the request
     *
     * @return \Scalr\Acl\Role\AccountRoleSuperposition
     */
    public function getAclRoles()
    {
        if (!$this->aclRoles) {
            $this->aclRoles = $this->getUser()->getAclRolesByEnvironment($this->getEnvironment()->id);
        }
        return $this->aclRoles;
    }

    /**
     * Checks if access to ACL resource or unique permission is allowed
     *
     * Usage:
     * --
     * use \Scalr\Acl\Acl;
     *
     * //it is somewhere inside controller action method
     * $this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_EDIT);
     *
     * //or you can do something like that
     * $this->request->isAllowed('FARMS', 'edit');
     *
     * @param   int        $resourceId            The ID of the ACL resource or its symbolic name without "RESOURCE_" prefix.
     * @param   string     $permissionId optional The ID of the uniqure permission which is
     *                                            related to specified resource.
     * @return  bool       Returns TRUE if access is allowed
     */
    public function isAllowed($resourceId, $permissionId = null)
    {
        return \Scalr::getContainer()->acl->isUserAllowedByEnvironment($this->getUser(), $this->getEnvironment(), $resourceId, $permissionId);
    }

    /**
     * Checks if access to ACL resource or unique permission is allowed
     * and throws an exception if negative.
     *
     * Usage:
     * --
     * use \Scalr\Acl\Acl;
     *
     * //it is somewhere inside controller action method
     * $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_EDIT);
     *
     * //or you can do something like that
     * $this->request->restrictAccess('FARMS', 'edit');
     *
     *
     * @param   int        $resourceId            The ID of the ACL resource or its symbolic name
     *                                            without "RESOURCE_" prefix.
     * @param   string     $permissionId optional The ID of the uniqure permission which is
     *                                            related to specified resource.
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function restrictAccess($resourceId, $permissionId = null)
    {
        if (is_string($resourceId)) {
            $sName = 'Scalr\\Acl\\Acl::RESOURCE_' . strtoupper($resourceId);
            if (defined($sName)) {
                $resourceId = constant($sName);
            } else {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot find ACL resource %s by specified symbolic name %s.',
                    $sName, $resourceId
                ));
            }
        }

        if (!$this->isAllowed($resourceId, $permissionId)) {
           throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    /**
     * Checks whether either it is beta version of interface or not hosted scalr install
     *
     * @return boolean Returns true if it is either a beta version of interface or it isn't hosted scalr install
     */
    public function isInterfaceBetaOrNotHostedScalr()
    {
        return $this->getHeaderVar('Interface-Beta') || Scalr::isAllowedAnalyticsOnHostedScalrAccount($this->getEnvironment()->clientId);
    }

    public function isInterfaceBeta()
    {
        return !!$this->getHeaderVar('Interface-Beta');
    }
}
