<?php

use Scalr\Exception\Http\BadRequestException;
use Scalr\LogCollector\AuditLogger;
use Scalr\LogCollector\AuditLoggerConfiguration;
use Scalr\LogCollector\AuditLoggerRetrieveConfigurationInterface;
use Scalr\Model\Entity\Farm;

abstract class ScalrAPICore implements AuditLoggerRetrieveConfigurationInterface
{
    const HASH_ALGO = 'SHA256';

    protected $Request;

    /**
     * @var Scalr_Environment
     */
    protected $Environment;

    /**
     * @var \ADODB_mysqli
     */
    protected $DB;

    /**
     * @var Scalr_Account_User
     */
    protected $user;

    public $Version;

    protected $debug = array();

    protected $LastTransactionID;

    /**
     * Acl role superposition object for current user's session
     *
     * @var \Scalr\Acl\Role\AccountRoleSuperposition
     */
    protected $aclRoles;

    /**
     * DI Container instance
     *
     * @var \Scalr\DependencyInjection\Container
     */
    private $container;

    function __construct($version)
    {
        $this->container = \Scalr::getContainer();
        $this->DB = $this->container->adodb;
        $this->Version = $version;
    }

    /**
     * Gets DI Container
     *
     * @return \Scalr\DependencyInjection\Container
     */
    protected function getContainer()
    {
        return $this->container;
    }

    /**
     * Sets necessary stuff into DI container
     */
    private function setDiContainer()
    {
        $requestClass = 'Scalr_UI_Request';

        $this->getContainer()->environment = $this->Environment;

        //Creates a request object
        $request = new Scalr_UI_Request(Scalr_UI_Request::REQUEST_TYPE_API, [], $_SERVER, [], []);

        //Sets authenticated user object
        $puser = new ReflectionProperty($requestClass, 'user');
        $puser->setAccessible(true);
        $puser->setValue($request, $this->user);

        //Sets user's environment object
        $penvironment = new ReflectionProperty($requestClass, 'environment');
        $penvironment->setAccessible(true);
        $penvironment->setValue($request, $this->Environment);

        //Sets internal instance
        $pinstance = new ReflectionProperty($requestClass, '_instance');
        $pinstance->setAccessible(true);
        $pinstance->setValue($request, $request);

        $container = $this->getContainer();

        //Injects request into DI container
        $container->request = $request;

        //Releases auditloger to ensure it will be updated
        $container->release('auditlogger');

        $container->set('auditlogger.request', function () {
            return $this;
        });
    }

    /**
     * AuditLogger wrapper
     *
     * @param  string  $event      Event name, aka tag
     * @param  mixed   $extra      optional Array of additionally provided information
     * @param  mixed   $extra,...  optional
     * @return boolean Whether operation was successful
     */
    protected function auditLog($event, ...$extra)
    {
        return $this->getContainer()->auditlogger->log($event, ...$extra);
    }

    protected function insensitiveUksort($a,$b)
    {
        return strtolower($a) > strtolower($b);
    }

    private function AuthenticateLdap($request)
    {
        if (!isset($request['Login']) || empty($request['Login']))
            throw new Exception("Login is missing");

        if (!isset($request['Password']) || empty($request['Password']))
            throw new Exception("Password is missing");

        if (!isset($request['EnvID']) || empty($request['EnvID']))
            throw new Exception("Environment ID is missing");

        if (\Scalr::config('scalr.auth_mode') != 'ldap')
            throw new Exception("LDAP auth not enabled on Scalr");

        $ldap = \Scalr::getContainer()->ldap($request['Login'], $request['Password']);

        $tldap = 0;
        $start = microtime(true);
        $result = $ldap->isValidUser();
        $tldap = microtime(true) - $start;

        if ($result) {
            //Provides that login is always with domain suffix
            $request['Login'] = $ldap->getUsername();

            $this->debug['ldapUsername'] = $request['Login'];

            $this->Environment = Scalr_Environment::init()->loadById($request['EnvID']);

            $start = microtime(true);
            $groups = $ldap->getUserGroups();
            $tldap += microtime(true) - $start;

            header(sprintf('X-Scalr-LDAP-Query-Time: %0.4f sec', $tldap));

            $this->debug['ldapGroups'] = json_encode($groups);

            //Get User
            $this->user = Scalr_Account_User::init()->loadByEmail($request['Login'], $this->Environment->clientId);
            if (!$this->user) {
                $this->user = new Scalr_Account_User();
                $this->user->type = Scalr_Account_User::TYPE_TEAM_USER;
                $this->user->status = Scalr_Account_User::STATUS_ACTIVE;
                $this->user->create($request['Login'], $this->Environment->clientId);
            }

            $this->user->applyLdapGroups($groups);

            $this->debug['ldapEnvId'] = $this->Environment->id;

            $this->user->getPermissions()->setEnvironmentId($this->Environment->id)->validate($this->Environment);

            $this->debug['ldapAuth'] = 1;

            //We must set environment to DI Container.
            $this->setDiContainer();
        } else {
            throw new Exception("Incorrect login or password (1)");
        }
    }

    protected function stripValue($value)
    {
        $value = strip_tags($value);
        $value = str_replace(array('>', '<'), '', $value);
        $value = trim($value);

        return $value;
    }

    private function AuthenticateRESTv3($request)
    {
        if (empty($request['Signature'])) {
            throw new Exception("Signature is missing");
        }

        if (empty($request['KeyID'])) {
            throw new Exception("KeyID is missing");
        }

        if (empty($request['Timestamp']) && empty($request['TimeStamp'])) {
            throw new Exception("Timestamp is missing");
        }

        if (!empty($request['Timestamp'])) {
            $request['TimeStamp'] = $request['Timestamp'];
        }

        //You mustn't do urldecode here in the next API version!
        $string_to_sign = "{$request['Action']}:{$request['KeyID']}:".urldecode($request['TimeStamp']);

        $this->debug['stringToSign'] = $string_to_sign;

        try {
            $this->user = Scalr_Account_User::init()->loadByApiAccessKey($request['KeyID']);
        } catch (Exception $e) {}

        if (!$this->user) {
            throw new Exception("The specified KeyID does not exist");
        }

        $auth_key = $this->user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY);

        if ($this->user->getAccountId()) {
            if (\Scalr::config('scalr.auth_mode') == 'ldap') {
                $this->Environment = Scalr_Environment::init()->loadById($request['EnvID']);

                try {
                    $ldap = \Scalr::getContainer()->ldap($this->user->getLdapUsername(), null);
                    if (! $ldap->isValidUsername()) {
                        throw new Exception('Incorrect login or password (1)');
                    }

                    $this->user->applyLdapGroups($ldap->getUserGroups());
                } catch (Exception $e) {
                    throw new Exception("Incorrect login or password (1)" . "\n" . $ldap->getLog());
                }
            } else {
                if (empty($request['EnvID'])) {
                    $envs = $this->user->getEnvironments();
                    if (empty($envs[0]['id'])) {
                        throw new Exception("User has no access to any environments");
                    }

                    $this->Environment = Scalr_Environment::init()->loadById($envs[0]['id']);
                }
                else {
                    $this->Environment = Scalr_Environment::init()->loadById($request['EnvID']);
                }
            }


            $this->user->getPermissions()->setEnvironmentId($this->Environment->id)->validate($this->Environment);
            //We must set environment to DI Container.
            $this->setDiContainer();
        }

        $valid_sign = base64_encode(hash_hmac(self::HASH_ALGO, trim($string_to_sign), $auth_key, 1));

        //You mustn't do this in the next API version!
        $request['Signature'] = str_replace(" ", "+", urldecode($request['Signature']));

        //You mustn't do urldecode here in the next API version!
        $this->debug['reqSignature'] = urldecode($request['Signature']);
        $this->debug['validSignature'] = $valid_sign;
        $this->debug['usedAuthVersion'] = 3;
        $this->debug['sha256AccessKey'] = hash(self::HASH_ALGO, $auth_key);

        if ($valid_sign != $request['Signature']) {
            //This is workaround to bugfix SCALRCORE-400.
            //It needn't have made unnecessary urldecode operation with request parameters.
            $sts2 = "{$request['Action']}:{$request['KeyID']}:{$request['TimeStamp']}";
            $vs2 = base64_encode(hash_hmac(self::HASH_ALGO, trim($sts2), $auth_key, 1));

            if ($vs2 != $request['Signature'])
                throw new Exception("Signature doesn't match");
        }
    }

    private function AuthenticateRESTv2($request)
    {
        if (!$request['Signature'])
            throw new Exception("Signature is missing");

        if (!$request['KeyID'])
            throw new Exception("KeyID is missing");

        if (!$request['Timestamp'] && !$request['TimeStamp'])
            throw new Exception("Timestamp is missing");

        foreach ($request as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $kk => $vv) {
                    $request["{$k}[{$kk}]"] = $vv;
                }

                unset($request[$k]);
            }
        }

        uksort($request, array($this, 'insensitiveUksort'));

        $string_to_sign = "";
        foreach ($request as $k => $v) {
            if (!in_array($k, array("Signature", "SysDebug"))) {
                if (is_array($v)) {
                    foreach ($v as $kk => $vv) {
                        $string_to_sign .= "{$k}[{$kk}]{$vv}";
                    }
                } else {
                    $string_to_sign.= "{$k}{$v}";
                }
            }
        }

        $this->debug['stringToSign'] = $string_to_sign;

        $this->user = Scalr_Account_User::init()->loadByApiAccessKey($request['KeyID']);

        if (!$this->user)
            throw new Exception("API Key #{$request['KeyID']} not found in database");

        $auth_key = $this->user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY);

        if ($this->user->getAccountId()) {
            if (!$request['EnvID']) {
                $envs = $this->user->getEnvironments();
                if (!$envs[0]['id'])
                    throw new Exception("User has no access to any environemnts");

                $this->Environment = Scalr_Environment::init()->loadById($envs[0]['id']);
            } else {
                $this->Environment = Scalr_Environment::init()->loadById($request['EnvID']);
            }

            $this->user->getPermissions()->setEnvironmentId($this->Environment->id)->validate($this->Environment);
            //We must set environment to DI Container.
            $this->setDiContainer();
        }

        $valid_sign = base64_encode(hash_hmac(self::HASH_ALGO, trim($string_to_sign), $auth_key, 1));

        $request['Signature'] = str_replace(" ", "+", $request['Signature']);

        $this->debug['reqSignature'] = $request['Signature'];
        $this->debug['validSignature'] = $valid_sign;

        if ($valid_sign != $request['Signature'])
            throw new Exception("Signature doesn't match");
    }

    private function AuthenticateREST($request)
    {
        if (empty($request['Signature'])) {
            throw new Exception("Signature is missing");
        }

        if (empty($request['KeyID'])) {
            throw new Exception("KeyID is missing");
        }

        if (empty($request['Timestamp']) && empty($request['TimeStamp'])) {
            throw new Exception("Timestamp is missing");
        }

        ksort($request);

        $string_to_sign = "";
        foreach ($request as $k => $v) {
            if (!in_array($k, array("Signature"))) {
                if (is_array($v)) {
                    foreach ($v as $kk => $vv) {
                        $string_to_sign .= "{$k}[{$kk}]{$vv}";
                    }
                } else {
                    $string_to_sign .= "{$k}{$v}";
                }
            }
        }

        $this->debug['stringToSign'] = $string_to_sign;

        $this->user = Scalr_Account_User::init()->loadByApiAccessKey($request['KeyID']);

        if (!$this->user) {
            throw new Exception("API Key #{$request['KeyID']} not found in database");
        }

        $auth_key = $this->user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY);

        if ($this->user->getAccountId()) {

            if (empty($request['EnvID'])) {
                if (empty($env = $this->user->getEnvironments())) {
                    throw new Exception("User has no access to any environemnts");
                }
                $request['EnvID'] = array_shift($env)['id'];
            }

            $this->Environment = Scalr_Environment::init()->loadById($request['EnvID']);
            $this->user->getPermissions()->setEnvironmentId($this->Environment->id)->validate($this->Environment);
            //We must set environment to DI Container.
            $this->setDiContainer();
        }

        $valid_sign = base64_encode(hash_hmac(self::HASH_ALGO, trim($string_to_sign), $auth_key, 1));
        if ($valid_sign != $request['Signature']) {
            throw new Exception("Signature doesn't match");
        }
    }

    public function BuildRestServer($request)
    {
        try {
            $Reflect = new ReflectionObject($this);
            if ($Reflect->hasMethod($request['Action'])) {
                //Authenticate
                if (isset($request["AuthType"]) && $request['AuthType'] == 'ldap') {
                    $this->AuthenticateLdap($request);
                } else {

                    $authVersion = isset($request['AuthVersion']) ? intval($request['AuthVersion']) : 0;
                    switch ($authVersion) {
                        case 2:
                            $this->AuthenticateRESTv2($request);
                            break;
                        case 3:
                            $this->AuthenticateRESTv3($request);
                            break;
                        default:
                            $this->AuthenticateREST($request);
                    }

                    if ($this->user->getSetting(Scalr_Account_User::SETTING_API_ENABLED) != 1) {
                        throw new Exception(_("Your API keys are currently disabled. You can enable access at Settings > API access."));
                    }

                    //Check IP Addresses
                    if ($this->user->getVar(Scalr_Account_User::VAR_API_IP_WHITELIST)) {
                        $ips = explode(",", $this->user->getVar(Scalr_Account_User::VAR_API_IP_WHITELIST));
                        if (!$this->IPAccessCheck($ips)) {
                            throw new Exception(sprintf(_("Access to the API is not allowed from your IP '%s'"), $_SERVER['REMOTE_ADDR']));
                        }
                    }
                }

                //Check limit
                if ($this->Environment->getPlatformConfigValue(Scalr_Environment::SETTING_API_LIMIT_ENABLED, false) == 1) {
                    $hour = $this->Environment->getPlatformConfigValue(Scalr_Environment::SETTING_API_LIMIT_HOUR, false);
                    $limit = $this->Environment->getPlatformConfigValue(Scalr_Environment::SETTING_API_LIMIT_REQPERHOUR, false);
                    $usage = $this->Environment->getPlatformConfigValue(Scalr_Environment::SETTING_API_LIMIT_USAGE, false);
                    if ($usage >= $limit && $hour == date("YmdH")) {
                        //$reset = 60 - (int)date("i");

                        header("HTTP/1.0 429 Too Many Requests");
                        exit();

                        //throw new Exception(sprintf("Hourly API requests limit (%s) exceeded. Limit will be reset within %s minutes", $limit, $reset));
                    }

                    if (date("YmdH") > $hour) {
                        $hour = date("YmdH");
                        $usage = 0;
                    }

                    $this->Environment->setPlatformConfig(array(
                        Scalr_Environment::SETTING_API_LIMIT_USAGE => $usage + 1,
                        Scalr_Environment::SETTING_API_LIMIT_HOUR => $hour
                    ), false);
                }

                //Execute API call
                $ReflectMethod = $Reflect->getMethod($request['Action']);
                $args = [];
                foreach ($ReflectMethod->getParameters() as $param) {
                    $paramName = $param->getName();
                    if (isset($request[$paramName])) {
                        $args[$paramName] = $param->isArray() ? (array)$request[$paramName] : $request[$paramName];
                    } elseif (!$param->isOptional()) {
                        throw new BadRequestException(sprintf("Missing required parameter '%s'", $paramName));
                    } else {
                        $args[$paramName] = $param->isArray() ? [] : null;
                    }
                }

                $result = $ReflectMethod->invokeArgs($this, $args);
                $this->LastTransactionID = $result->TransactionID;

                // Create response
                $DOMDocument = new DOMDocument('1.0', 'UTF-8');
                $DOMDocument->loadXML("<{$request['Action']}Response></{$request['Action']}Response>");
                $this->ObjectToXML($result, $DOMDocument->documentElement, $DOMDocument);

                $retval = $DOMDocument->saveXML();
            } else {
                throw new Exception(sprintf("Action '%s' is not defined", $request['Action']));
            }
        } catch (Exception $e) {
            if (empty($this->LastTransactionID)) {
                $this->LastTransactionID = Scalr::GenerateUID();
            }

            $retval = "<?xml version=\"1.0\"?>\n" .
                "<Error>\n" .
                "\t<TransactionID>{$this->LastTransactionID}</TransactionID>\n" .
                "\t<Message>{$e->getMessage()}</Message>\n" .
                "</Error>\n";
        }
        if (isset($this->user)) {
            $this->LogRequest(
                $this->LastTransactionID,
                $request['Action'],
                $_SERVER['REMOTE_ADDR'],
                $request,
                $retval
            );
        }

        header("Content-type: text/xml");
        header("Content-length: " . strlen($retval));
        header("Access-Control-Allow-Origin: *");

        print $retval;
    }

    protected function LogRequest($trans_id, $action, $ipaddr, $request, $response)
    {
        $request = filter_var_array($request, [
            'debug' => [
                'filter' => FILTER_VALIDATE_INT,
                'flags'  => FILTER_REQUIRE_SCALAR,
            ],
            'Debug' => [
                'filter' => FILTER_VALIDATE_INT,
                'flags'  => FILTER_REQUIRE_SCALAR,
            ],
            'Action' => [
                'filter' => FILTER_DEFAULT,
                'flags'  => FILTER_REQUIRE_SCALAR,
            ]
        ], true);

        if ($request['debug'] === 1 || $request['Debug'] === 1 || $request['Action'] === 'DNSZoneRecordAdd') {
            try {
                $this->DB->Execute("
                    INSERT INTO api_log SET
                        transaction_id = ?,
                        dtadded        = ?,
                        action         = ?,
                        ipaddress      = ?,
                        request        = ?,
                        response       = ?,
                        clientid       = ?,
                        env_id         = ?
                ", [
                    $trans_id,
                    time(),
                    $action,
                    $ipaddr,
                    http_build_query($request),
                    $response,
                    $this->user instanceof Scalr_Account_User ? $this->user->getAccountId() : null,
                    !empty($this->Environment->id) ? $this->Environment->id : null,
                ]);
            } catch (Exception $ignore) {}
        }
    }

    protected function IPAccessCheck($allowed_ips)
    {
        $current_ip = $_SERVER['REMOTE_ADDR'];
        $current_ip_parts = explode(".", $current_ip);

        foreach ($allowed_ips as $allowed_ip) {
            $allowedhost = trim($allowed_ip);
            if ($allowedhost == '')
                continue;

            if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/si", $allowedhost)) {
                if (ip2long($allowedhost) == ip2long($current_ip))
                   return true;
            } elseif (stristr($allowedhost, "*")) {
                $ip_parts = explode(".", trim($allowedhost));
                if (($ip_parts[0] == "*" || $ip_parts[0] == $current_ip_parts[0]) &&
                    ($ip_parts[1] == "*" || $ip_parts[1] == $current_ip_parts[1]) &&
                    ($ip_parts[2] == "*" || $ip_parts[2] == $current_ip_parts[2]) &&
                    ($ip_parts[3] == "*" || $ip_parts[3] == $current_ip_parts[3])) {
                    return true;
                }
            } else {
                $ip = @gethostbyname($allowedhost);
                if ($ip != $allowedhost) {
                    if (ip2long($ip) == ip2long($current_ip))
                       return true;
                }
            }
        }

        return false;
    }

    protected function ObjectToXML($obj, $DOMElement, $DOMDocument)
    {
        if (is_object($obj) || is_array($obj)) {
            foreach ($obj as $k => $v) {
                if (is_object($v)) {
                    $this->ObjectToXML($v, $DOMElement->appendChild($DOMDocument->createElement($k)), $DOMDocument);
                } elseif (is_array($v)) {
                    foreach ($v as $vv) {
                        $e = $DOMElement->appendChild($DOMDocument->createElement($k));
                        $this->ObjectToXML($vv, $e, $DOMDocument);
                    }
                } else {
                    if (preg_match("/[\<\>\&]+/", $v)) {
                        $valueEl = $DOMDocument->createCDATASection($v);
                    } else {
                        $valueEl = $DOMDocument->createTextNode($v);
                    }

                    $el = $DOMDocument->createElement($k);
                    $el->appendChild($valueEl);
                    $DOMElement->appendChild($el);
                }
            }
        } else {
            $DOMElement->appendChild($DOMDocument->createTextNode($obj));
        }
    }

    protected function CreateInitialResponse()
    {
        $response = new stdClass();
        $response->TransactionID = Scalr::GenerateUID();

        return $response;
    }

    /**
     * Gets acl roles superposition for the request
     *
     * @return \Scalr\Acl\Role\AccountRoleSuperposition
     */
    protected function getAclRoles()
    {
        if (!$this->aclRoles) {
            $this->aclRoles = $this->user->getAclRolesByEnvironment($this->Environment->id);
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
     * $this->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);
     *
     * //or you can do something like that
     * $this->isAllowed('FARMS', 'manage');
     *
     * @param   int        $resourceId            The ID of the ACL resource or its symbolic name without "RESOURCE_" prefix.
     * @param   string     $permissionId optional The ID of the uniqure permission which is
     *                                            related to specified resource.
     * @return  bool       Returns TRUE if access is allowed
     */
    protected function isAllowed($resourceId, $permissionId = null)
    {
        return $this->getContainer()->acl->isUserAllowedByEnvironment($this->user, $this->Environment, $resourceId, $permissionId);
    }

    /**
     * @param   DBFarm $dbFarm
     * @param   string $permissionId
     * @return  bool
     */
    protected function isFarmAllowed(DBFarm $dbFarm = null, $permissionId = null)
    {
        $acl = \Scalr::getContainer()->acl;

        if (is_null($dbFarm)) {
            return  $acl->isUserAllowedByEnvironment($this->user, $this->Environment, \Scalr\Acl\Acl::RESOURCE_FARMS, $permissionId) ||
                    $acl->isUserAllowedByEnvironment($this->user, $this->Environment, \Scalr\Acl\Acl::RESOURCE_TEAM_FARMS, $permissionId) ||
                    $acl->isUserAllowedByEnvironment($this->user, $this->Environment, \Scalr\Acl\Acl::RESOURCE_OWN_FARMS, $permissionId);
        } else {
            if (!($dbFarm instanceof DBFarm))
                throw new \InvalidArgumentException(sprintf(
                    'First argument should be instance of DBFarm or null'
                ));

            $result = $acl->isUserAllowedByEnvironment($this->user, $this->Environment, \Scalr\Acl\Acl::RESOURCE_FARMS, $permissionId);

            if (!$result && $dbFarm->__getNewFarmObject()->hasUserTeamOwnership($this->user)) {
                $result = $acl->isUserAllowedByEnvironment($this->user, $this->Environment, \Scalr\Acl\Acl::RESOURCE_TEAM_FARMS, $permissionId);
            }

            if (!$result && $dbFarm->ownerId && $this->user->id == $dbFarm->ownerId) {
                $result = $acl->isUserAllowedByEnvironment($this->user, $this->Environment, \Scalr\Acl\Acl::RESOURCE_OWN_FARMS, $permissionId);
            }

            return $result;
        }
    }

    /**
     * Checks if access to ACL resource or unique permission is allowed
     * and throws an exception if negative.
     *
     * @param   int        $resourceId            The ID of the ACL resource or its symbolic name
     *                                            without "RESOURCE_" prefix.
     * @param   string     $permissionId optional The ID of the uniqure permission which is
     *                                            related to specified resource.
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    protected function restrictAccess($resourceId, $permissionId = null)
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
           throw new \Scalr_Exception_InsufficientPermissions();
        }
    }

    /**
     * @param DBFarm $dbFarm
     * @param null $permissionId
     * @throws Scalr_Exception_InsufficientPermissions
     */
    protected function restrictFarmAccess(DBFarm $dbFarm = null, $permissionId = null)
    {
        if (!$this->isFarmAllowed($dbFarm, $permissionId)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    /**
     * Generate conditions for sql query to limit access by only allowable farms.
     * Table `farms` should have alias `f`.
     *
     * @param   string  $permissionId   optional
     * @return  string
     */
    public function getFarmSqlQuery($permissionId = null)
    {
        if (!$this->isAllowed(\Scalr\Acl\Acl::RESOURCE_FARMS, $permissionId)) {
            $q = [];
            if ($this->isAllowed(\Scalr\Acl\Acl::RESOURCE_TEAM_FARMS, $permissionId)) {
                $q[] = Farm::getUserTeamOwnershipSql($this->user->id);
            }

            if ($this->isAllowed(\Scalr\Acl\Acl::RESOURCE_OWN_FARMS, $permissionId)) {
                $q[] = "f.created_by_id = '{$this->user->getId()}'";
            }

            if (count($q)) {
                $sql = '(' . join(' OR ', $q) . ')';
            } else {
                $sql = '0'; // no permissions
            }
        } else {
            $sql = '1'; // all farms in env
        }

        return $sql;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\LogCollector\AuditLoggerRetrieveConfigurationInterface::getAuditLoggerConfig()
     */
    public function getAuditLoggerConfig()
    {
        $config = new AuditLoggerConfiguration(AuditLogger::REQUEST_TYPE_API);

        $config->user = $this->user;
        $config->accountId = $this->user ? $this->user->getAccountId() : null;
        $config->envId = isset($this->Environment) ? $this->Environment->id : null;
        $config->remoteAddr = $this->getContainer()->request->getRemoteAddr();

        return $config;
    }

}
