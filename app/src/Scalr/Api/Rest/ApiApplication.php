<?php

namespace Scalr\Api\Rest;

use ErrorException;
use Exception;
use Scalr\Api\Rest\Routing\Route;
use Scalr\Model\Entity;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorEnvelope;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\Meta;
use Scalr\Api\Rest\Exception\ApiInsufficientPermissionsException;
use Scalr\Model\Entity\Account\User\ApiKeyEntity;
use Scalr\Net\Ldap\Exception\LdapException;
use Scalr\Net\Ldap\LdapClient;

/**
 * ApiApplication
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (21.02.2015)
 *
 * @property  \Scalr\Api\DataType\Meta $meta
 *            Gets the Meta object which is the part of the API response
 */
class ApiApplication extends Application
{

    const SETTING_SCALR_ENVIRONMENT = 'api.scalr-environment';

    const REGEXP_UUID = '[[:xdigit:]]{8}-([[:xdigit:]]{4}-){3}[[:xdigit:]]{12}';

    /**
     * Error envelope
     *
     * @var ErrorEnvelope
     */
    private $errorEnvelope;

    /**
     * The ApiKey entity of the Authenticated user
     *
     * @var \Scalr\Model\Entity\Account\User\ApiKeyEntity
     */
    private $apiKey;

    /**
     * User who sent request
     *
     * @var \Scalr\Model\Entity\Account\User
     */
    private $user;

    /**
     * User's environment
     *
     * @var \Scalr\Model\Entity\Account\Environment
     */
    private $env;

	/**
	 * {@inheritdoc}
	 * @see \Scalr\Api\Rest\Application::__construct()
	 */
	public function __construct(array $settings = [])
	{
        parent::__construct($settings);

        $cont = $this->getContainer();

        $cont->api->setShared('meta', function ($cont) {
            return new Meta();
        });
	}

	/**
	 * Sets authorized user
	 *
	 * @param  \Scalr\Model\Entity\Account\User   $user  The user object
	 * @return ApiApplication
	 */
	public function setUser($user)
	{
	    $this->user = $user;

	    return $this;
	}

	/**
	 * Gets authorized user
	 *
	 * @return  \Scalr\Model\Entity\Account\User  Returns user object
	 */
	public function getUser()
	{
	    return $this->user;
	}

	/**
	 * Sets User's Environment
	 *
	 * @param  \Scalr\Model\Entity\Account\Environment  $environment  The Environment object
	 * @return ApiApplication
	 */
	public function setEnvironment(\Scalr\Model\Entity\Account\Environment $environment)
	{
	    $this->env = $environment;

	    return $this;
	}

	/**
	 * Gets User's Environment
	 *
	 * @return \Scalr\Model\Entity\Account\Environment  Returns Environment object
	 */
	public function getEnvironment()
	{
	    return $this->env;
	}

    /**
     * Gets default settings
     */
    public static function getDefaultSettings()
    {
        return array_merge(parent::getDefaultSettings(), [
            self::SETTING_SCALR_ENVIRONMENT => null,
        ]);
    }

    /**
     * Sets up all API routes
     *
     * @return  ApiApplication  Returns current instance
     */
    public function setupRoutes()
    {
        $this->group('/api', [$this, 'handleApiVersion'], [$this, 'authenticationMiddleware'], function() {
            $this->group('/admin/v:apiversion', function () {
                //All Admin API routes are here
                $this->get('/users/:userId', 'Admin_Users:get', ['userId' => '\d+']);
            });

            $this->group('/account/v:apiversion', function () {
                //All Account API routes are here
            });

            $this->group('/user/v:apiversion', function () {
                //All User API routes are here
                $eventRequirements = ['eventId' => Entity\EventDefinition::NAME_REGEXP];

                $this->get('/os', 'User_Os:get');
                $this->get('/os/:osId', 'User_Os:fetch', ['osId' => Entity\Os::ID_REGEXP]);

                //We explicitly set array of the options to exclude the Route Name as both environment and
                //account levels have the same controllers and all registered Route Names must be unique withing the router.
                $this->get('/events', ['controller' => 'User_Events:describe']);
                $this->get('/events/:eventId', ['controller' => 'User_Events:fetch'], $eventRequirements);
                $this->post('/events', ['controller' => 'User_Events:create']);
                $this->patch('/events/:eventId', ['controller' => 'User_Events:modify'], $eventRequirements);
                $this->delete('/events/:eventId', ['controller' => 'User_Events:delete'], $eventRequirements);
            });

            $this->group('/user/v:apiversion/:environment', [$this, 'handleEnvironment'], [$this, 'environmentAuthenticationMiddleware'], function () {
                //All User API Environment level routes are here
                $roleRequirements = ['roleId' => '\d+'];
                $imageRequirements = ['imageId' => ApiApplication::REGEXP_UUID];
                $roleImageReqs = array_merge($roleRequirements, $imageRequirements);
                $roleVariableReqs = array_merge($roleRequirements, ['variableName' => '\w+']);
                $roleCategoryReqs = ['roleCategoryId' => '\d+'];
                $scriptRequirements = ['scriptId' => '\d+'];
                $scriptVersionReqs = array_merge($scriptRequirements, ['versionNumber' => '\d+']);
                $orchestrationRuleReqs = array_merge($roleRequirements, ['ruleId' => '\d+']);
                $eventRequirements = ['eventId' => Entity\EventDefinition::NAME_REGEXP];

                $this->get('/events', 'User_Events:describe');
                $this->get('/events/:eventId', 'User_Events:fetch', $eventRequirements);
                $this->post('/events', 'User_Events:create');
                $this->patch('/events/:eventId', 'User_Events:modify', $eventRequirements);
                $this->delete('/events/:eventId', 'User_Events:delete', $eventRequirements);

                $this->get('/images', 'User_Images:describe');
                $this->post('/images', 'User_Images:register');

                $this->get('/images/:imageId', 'User_Images:fetch', $imageRequirements);
                $this->patch('/images/:imageId', 'User_Images:modify', $imageRequirements);
                $this->delete('/images/:imageId', 'User_Images:deregister', $imageRequirements);

                $this->post('/images/:imageId/actions/:action/', 'User_Images:copy', array_merge($imageRequirements, ['action' => 'copy']));

                $this->get('/roles', 'User_Roles:describe');
                $this->post('/roles', 'User_Roles:create');

                $this->get('/roles/:roleId', 'User_Roles:fetch', $roleRequirements);
                $this->patch('/roles/:roleId', 'User_Roles:modify', $roleRequirements);
                $this->delete('/roles/:roleId', 'User_Roles:delete', $roleRequirements);

                $this->get('/roles/:roleId/images/', 'User_Roles:describeImages', $roleRequirements);
                $this->post('/roles/:roleId/images/', 'User_Roles:registerImage', $roleRequirements);

                $this->get('/roles/:roleId/images/:imageId', 'User_Roles:fetchImage', $roleImageReqs);
                $this->delete('/roles/:roleId/images/:imageId', 'User_Roles:deregisterImage', $roleImageReqs);

                $this->post('/roles/:roleId/images/:imageId/actions/:action/', 'User_Roles:replaceImage', array_merge($roleImageReqs, ['action' => 'replace']));

                $this->get('/role-categories', 'User_RoleCategories:describe', $roleCategoryReqs);
                $this->get('/role-categories/:roleCategoryId', 'User_RoleCategories:fetch', $roleCategoryReqs);

                $this->get('/roles/:roleId/global-variables', 'User_Roles:describeVariables', $roleRequirements);
                $this->post('/roles/:roleId/global-variables', 'User_Roles:createVariable', $roleRequirements);

                $this->get('/roles/:roleId/global-variables/:variableName', 'User_Roles:fetchVariable', $roleVariableReqs);
                $this->patch('/roles/:roleId/global-variables/:variableName', 'User_Roles:modifyVariable', $roleVariableReqs);
                $this->delete('/roles/:roleId/global-variables/:variableName', 'User_Roles:deleteVariable', $roleVariableReqs);

                $this->get('/roles/:roleId/orchestration-rules', 'User_OrchestrationRules:describe', $roleRequirements);
                $this->post('/roles/:roleId/orchestration-rules', 'User_OrchestrationRules:create', $roleRequirements);

                $this->get('/roles/:roleId/orchestration-rules/:ruleId', 'User_OrchestrationRules:fetch', $orchestrationRuleReqs);
                $this->patch('/roles/:roleId/orchestration-rules/:ruleId', 'User_OrchestrationRules:modify', $orchestrationRuleReqs);
                $this->delete('/roles/:roleId/orchestration-rules/:ruleId', 'User_OrchestrationRules:delete', $orchestrationRuleReqs);

                $this->get('/scripts', 'User_Scripts:describe');
                $this->post('/scripts', 'User_Scripts:create');

                $this->get('/scripts/:scriptId', 'User_Scripts:fetch', $scriptRequirements);
                $this->patch('/scripts/:scriptId', 'User_Scripts:modify', $scriptRequirements);
                $this->delete('/scripts/:scriptId', 'User_Scripts:delete', $scriptRequirements);

                $this->get('/scripts/:scriptId/script-versions', 'User_ScriptVersions:describe', $scriptRequirements);
                $this->post('/scripts/:scriptId/script-versions', 'User_ScriptVersions:create', $scriptRequirements);

                $this->get('/scripts/:scriptId/script-versions/:versionNumber', 'User_ScriptVersions:fetch', $scriptVersionReqs);
                $this->patch('/scripts/:scriptId/script-versions/:versionNumber', 'User_ScriptVersions:modify', $scriptVersionReqs);
                $this->delete('/scripts/:scriptId/script-versions/:versionNumber', 'User_ScriptVersions:delete', $scriptVersionReqs);

                if ($this->getContainer()->config->{"scalr.analytics.enabled"}) {
                    $projectRequirements = ['projectId' => ApiApplication::REGEXP_UUID];
                    $ccRequirements = ['ccId' => ApiApplication::REGEXP_UUID];

                    $this->get('/cost-centers/', 'User_CostCenters:describe');
                    $this->get('/cost-centers/:ccId', 'User_CostCenters:fetch', $ccRequirements);

                    $this->get('/projects/', 'User_Projects:describe');
                    $this->post('/projects/', 'User_Projects:create');

                    $this->get('/projects/:projectId', 'User_Projects:fetch', $projectRequirements);
                }
            });
        });

        return $this;
    }


    /**
     * Authentication middleware
     */
    public function authenticationMiddleware()
    {
        $bDebug = $this->request->headers('x-scalr-debug', 0) == 1;

        //If API is not enabled
        if (!$this->getContainer()->config('scalr.system.api.enabled')) {
            $this->halt(403, 'API is not enabled. See scalr.system.api.enabled');
        }

        //Authentication
        $keyId = $this->request->headers('x-scalr-key-id');
        $signature = $this->request->headers('x-scalr-signature');
        //ISO-8601 formatted date
        $date = trim(preg_replace('/\s+/', '', $this->request->headers('x-scalr-date')));

        if (empty($keyId) || empty($signature)) {
            throw new ApiErrorException(401, ErrorMessage::ERR_BAD_AUTHENTICATION, 'Unsigned request');
        } elseif (empty($date) || ($time = strtotime($date)) === false) {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, 'Missing or invalid X-Scalr-Date header');
        }

        $sigparts = explode(' ', $signature, 2);

        if (empty($sigparts) || !in_array($sigparts[0], ['V1-HMAC-SHA256'])) {
            throw new ApiErrorException(401, ErrorMessage::ERR_BAD_AUTHENTICATION, 'Invalid signature');
        }

        $this->apiKey = ApiKeyEntity::findPk($keyId);

        if (!($this->apiKey instanceof ApiKeyEntity) || !$this->apiKey->active) {
            throw new ApiErrorException(401, ErrorMessage::ERR_BAD_AUTHENTICATION, 'Invalid API Key');
        }

        if (abs(time() - $time) > 300) {
            throw new ApiErrorException(401, ErrorMessage::ERR_BAD_AUTHENTICATION, 'Request is expired.' . ($bDebug ? ' Now is ' . gmdate('Y-m-d\TH:i:s\Z') : '') );
        }

        $now = new \DateTime('now');

        if (empty($this->apiKey->lastUsed) || ($now->getTimestamp() - $this->apiKey->lastUsed->getTimestamp()) > 10) {
            $this->apiKey->lastUsed = $now;
            $this->apiKey->save();
        }

        $qstr = $this->request->get();

        $canonicalStr = '';

        if (!empty($qstr)) {
            ksort($qstr);

            $canonicalStr = http_build_query($qstr, null, '&', PHP_QUERY_RFC3986);
        }

        $reqBody = $this->request->getBody();

        $stringToSign =
            $this->request->getMethod() . "\n"
          . $date . "\n"
          . $this->request->getPath() . "\n"
          . $canonicalStr . "\n"
          . (empty($reqBody) ? '' : $reqBody)
        ;

        if ($bDebug) {
            $this->meta->stringToSign = $stringToSign;
        }

        switch ($sigparts[0]) {
            default:
                throw new ApiErrorException(401, ErrorMessage::ERR_BAD_AUTHENTICATION, 'Invalid signature method. Please use "V1-HMAC-SHA256 [SIGNATURE]"');
                break;

            case 'V1-HMAC-SHA256':
                $algo = strtolower(substr($sigparts[0], 8));
        }

        $sig = base64_encode(hash_hmac($algo, $stringToSign, $this->apiKey->secretKey, 1));

        if ($sig !== $sigparts[1]) {
            throw new ApiErrorException(401, ErrorMessage::ERR_BAD_AUTHENTICATION, 'Signature does not match');
        }

        $user = Entity\Account\User::findPk($this->apiKey->userId);
        /* @var $user Entity\Account\User */

        if (!($user instanceof Entity\Account\User)) {
            throw new ApiErrorException(401, ErrorMessage::ERR_BAD_AUTHENTICATION, 'User does not exist');
        }

        if ($user->status != Entity\Account\User::STATUS_ACTIVE) {
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, 'Inactive user status');
        }

        if (\Scalr::config('scalr.auth_mode') == 'ldap') {
            try {
                $ldap = \Scalr::getContainer()->ldap($user->getLdapUsername(), null);

                if (!$ldap->isValidUsername()) {
                    if ($bDebug && $ldap->getConfig()->debug) {
                        $this->meta->ldapDebug = $ldap->getLog();
                    }
                    throw new ApiErrorException(401, ErrorMessage::ERR_BAD_AUTHENTICATION, 'User does not exist');
                }

                $user->applyLdapGroups($ldap->getUserGroups());
            } catch (LdapException $e) {
                if ($bDebug && $ldap instanceof LdapClient && $ldap->getConfig()->debug) {
                    $this->meta->ldapDebug = $ldap->getLog();
                }
                throw new \RuntimeException($e->getMessage());
            }
        }

        //Validates API version
        if ($this->settings[ApiApplication::SETTING_API_VERSION] != 1) {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, 'Invalid API version');
        }

        if ($this->request->getBody() !== '' && strtolower($this->request->getMediaType()) !== 'application/json') {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, 'Invalid Content-Type');
        }

        $this->setUser($user);
    }

    /**
     * Environment level authentication middleware
     */
    public function environmentAuthenticationMiddleware()
    {
        if (empty($this->settings[self::SETTING_SCALR_ENVIRONMENT]) || $this->settings[self::SETTING_SCALR_ENVIRONMENT] <= 0) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Environment must be specified");
        }

        $envId = $this->settings[self::SETTING_SCALR_ENVIRONMENT];

        $environment = Entity\Account\Environment::findPk($envId);

        if (!($environment instanceof Entity\Account\Environment)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Invalid environment");
        }

        $user = $this->getUser();

        if (!($user instanceof Entity\Account\User)) {
            throw new \Exception("User had to be set by the previous middleware, but he weren't");
        }

        if (!$user->hasAccessToEnvironment($environment->id)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "You don't have access to the environment.");
        }

        $this->setEnvironment($environment);
    }

    /**
     * Scalr-Evironment middleware handler
     *
     * It extracts :environment group parameter from the route
     * and sets application setting
     *
     * @param   Route $route A route
     * @throws  ApiErrorException
     */
    public function handleEnvironment($route)
    {
        $params = $route->getParams();

        if (!is_numeric($params['environment']) || $params['environment'] <= 0) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Environment has not been provided with the request");
        }

        $this->settings[self::SETTING_SCALR_ENVIRONMENT] = (int) $params['environment'];

        unset($params['environment']);

        $route->setParams($params);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\Rest\Application::defaultError()
     */
    protected function defaultError($e = null)
    {
        if ($e instanceof Exception) {
            $errorEnvelope = $this->getErrorEnvelope();

            if ($e instanceof ApiErrorException) {
                $errorEnvelope->errors[] = new ErrorMessage($e->getError(), $e->getMessage());
                $this->response->setStatus($e->getStatus());
            } else if (!$e instanceof ErrorException) {
                \Scalr::logException($e);

                $errorEnvelope->errors[] = new ErrorMessage(ErrorMessage::ERR_INTERNAL_SERVER_ERROR, "Server Error");

                $this->response->setStatus(500);
            }

            $this->response->setContentType("application/json", "utf-8");

            return @json_encode($errorEnvelope);
        }

        return '';
    }

    /**
     * Gets API error envelope
     *
     * @return \Scalr\Api\DataType\ErrorEnvelope
     */
    public function getErrorEnvelope()
    {
        if ($this->errorEnvelope === null) {
            $this->errorEnvelope = new ErrorEnvelope();
        }

        return $this->errorEnvelope;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\Rest\Application::handleApiVersion()
     */
    public function handleApiVersion($route)
    {
        $params = $route->getParams();

        if (!preg_match('/^\d+(beta\d*)?$/', $params['apiversion'])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, 'Invalid API version');
        }

        $this->settings[static::SETTING_API_VERSION] = $params['apiversion'];

        unset($params['apiversion']);

        $route->setParams($params);

        //This also parses the named route compound handler
        $options = $route->getDefaults();
        if (!is_callable($options['controller'])) {
            $route->setDefaults(['controller' => $this->getRouteHandler($options['controller'])]);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\Rest\Application::notFound()
     */
    public function notFound()
    {
        throw new ApiErrorException(404, ErrorMessage::ERR_ENDPOINT_NOT_FOUND, "The endpoint you are trying to access does not exist.");
    }

    /**
     * Redirects to the specified named route
     *
     * @param    string    $route  The name of the Route
     * @param    array     $params optional The list of the parameters
     * @param    number    $status optional The HTTP response status code.
     * @throws   \DomainException
     */
    public function redirectTo($route, $params = array(), $status = 302)
    {
        throw new \DomainException("Redirect feature will not work in the API because new URL requires a new X-Scalr-Signature header.");

        if (isset($this->settings[static::SETTING_API_VERSION])) {
            $params['apiversion'] = $this->settings[static::SETTING_API_VERSION];
        }

        if (isset($this->settings[static::SETTING_SCALR_ENVIRONMENT])) {
            $params['environment'] = $this->settings[static::SETTING_SCALR_ENVIRONMENT];
        }

        $url = $this->getRouteUrl($route, $params);

        $this->redirect($url, $status);
    }

    /**
     * Gets the callback for the handler of the specified Route
     *
     * @param     string     $name The description of the handler <API_NAME>_<CONTROLLER_CLASS>:<HANDLER_METHOD>
     * @return    callable   Returns the callable handler
     */
    public function getRouteHandler($name)
    {
        $m = [];
        if (preg_match('/^(\w+)_(\w+):(\w+)$/i', $name, $m)) {
            $version = (string) $this->settings[static::SETTING_API_VERSION];
            $controller = 'Scalr\\Api\\Service\\' . $m[1] . (empty($version) ? ''  : '\\V' . $version) . '\\Controller\\' . $m[2];
            $method = $m[3];
        } else {
            throw new \InvalidArgumentException(sprintf("Invalid controller handler '%s'", $name));
        }

        return [$this->getContainer()->api->controller($controller), $method];
    }

    /**
     * Invokes the named route with the list of the arguments and returns the result
     *
     * @param     string     $name The description of the handler <API_NAME>_<CONTROLLER_CLASS>:<HANDLER_METHOD>
     * @param     mixed      $arg,... unlimited optional The list of the arguments for the Route's handler
     * @return    mixed      Returns the result of the invoked handler of the specified Route
     */
    public function invokeRoute($name)
    {
        $args = func_get_args();

        array_shift($args);

        return empty($args) ?
            call_user_func($this->getRouteHandler($name)) :
            call_user_func_array($this->getRouteHandler($name), $args);
    }

    /**
     * Checks whether the authenticated user either is authorized to the specified object or has permission to ACL Role
     *
     * hasPermissions(object $obj, bool $modify = false)
     * hasPermissions(int $roleId, string $permissionId = null)
     *
     * @return bool            Returns TRUE if the authenticated user has access or FALSE otherwise
     * @throws \BadMethodCallException
     */
    public function hasPermissions()
    {
        $args = func_get_args();

        if (func_num_args() < 1 || func_num_args() > 2) {
            throw new \BadMethodCallException(sprintf(
                "%s expects mandatory arguments. Usage: either "
              . "hasPermission(object \$obj, bool \$modify = null) or "
              . "hasPermission(int \$roleId, string \$permissionId = null)", __METHOD__
            ));
        }

        if (is_object($args[0])) {
            //Check Entity level permission
            return $this->getContainer()->acl->hasAccessTo(
                $args[0], $this->getUser(), $this->getEnvironment(),
                (isset($args[1]) ? (bool) $args[1] : null)
            );
        } else {
            //Check ACL
            return $this->getContainer()->acl->isUserAllowedByEnvironment(
                $this->getUser(), $this->getEnvironment(), $args[0],
                (isset($args[1]) ? $args[1] : null)
            );
        }
    }

    /**
     * Checks whether the authenticated user either is authorized to the specified object or has permission to ACL Role
     *
     * checkPermissions(object $obj, bool $modify = false, $errorMessage='')
     * checkPermissions(int $roleId, string $permissionId = null, $errorMessage='')
     *
     * @throws ApiInsufficientPermissionsException
     */
    public function checkPermissions()
    {
        $args = func_get_args();

        if (count($args) == 3) {
            $message = array_pop($args);
        } else {
            $message = null;
        }

        $result = call_user_func_array([$this, 'hasPermissions'], $args);

        if (!$result) {
            throw new ApiInsufficientPermissionsException($message);
        }
    }
}