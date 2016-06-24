<?php

namespace Scalr\Api\Rest;

use ErrorException;
use Exception;
use Scalr\Api\Limiter;
use Scalr\Api\Rest\Exception\StopException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Rest\Http\Response;
use Scalr\Api\Rest\Routing\Route;
use Scalr\DataType\ScopeInterface;
use Scalr\LogCollector\AuditLoggerConfiguration;
use Scalr\LogCollector\AuditLoggerRetrieveConfigurationInterface;
use Scalr\Model\Entity;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorEnvelope;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\Meta;
use Scalr\Api\DataType\Warnings;
use Scalr\Api\Rest\Exception\ApiInsufficientPermissionsException;
use Scalr\Model\Entity\Account\User\ApiKeyEntity;
use Scalr\Net\Ldap\Exception\LdapException;
use Scalr\Net\Ldap\LdapClient;
use Scalr\LogCollector\AuditLogger;
use Scalr;
use RuntimeException;

/**
 * ApiApplication
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (21.02.2015)
 *
 * @property  \Scalr\Api\DataType\Meta $meta
 *            Gets the Meta object which is the part of the API response
 *
 * @property  \Scalr\Api\DataType\Warnings $warnings
 *            Gets Warnings object which is the part of the body API response
 */
class ApiApplication extends Application implements AuditLoggerRetrieveConfigurationInterface
{

    const SETTING_SCALR_ENVIRONMENT = 'api.scalr-environment';

    const REGEXP_UUID = '[[:xdigit:]]{8}-([[:xdigit:]]{4}-){3}[[:xdigit:]]{12}';

    const REGEXP_SHORT_UUID = '[[:xdigit:]]{12}';

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
     * API rate limiter
     *
     * @var Limiter
     */
    protected $limiter;

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\Rest\Application::__construct()
     */
    public function __construct(array $settings = [])
    {
        parent::__construct($settings);

        $cont = $this->getContainer();

        $cont->api->setShared('meta', function () {
            return new Meta();
        });

        $cont->api->setShared('warnings', function () {
            return new Warnings();
        });

        $this->pathPreprocessor = function ($method, $pathInfo) {
            if (preg_match("#^/api/(user|admin|account)/#", $pathInfo)) {
                $pathInfo = preg_replace("#/(user|admin|account)/(v\d.*?)/#", '/$2/$1/', $pathInfo);
                $this->warnings->appendWarnings(Response::getCodeMessage(301), sprintf('Location %s', $pathInfo));
            }
            return [$method, $pathInfo];
        };

        $this->limiter = new Limiter(\Scalr::getContainer()->config->{'scalr.system.api.limits'});
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
    public function setEnvironment(Entity\Account\Environment $environment)
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
        $this->group('/api', [$this, 'handleApiVersion'], [$this, 'preflightRequestHandlerMiddleware'], [$this, 'authenticationMiddleware'], function() {
            $this->group('/v:apiversion', function () {
                $roleRequirements = ['roleId' => '\d+'];
                $imageRequirements = ['imageId' => ApiApplication::REGEXP_UUID];
                $eventRequirements = ['eventId' => Entity\EventDefinition::NAME_REGEXP];
                $osRequirements = ['osId' => Entity\Os::ID_REGEXP];
                $ccRequirements = ['ccId' => ApiApplication::REGEXP_UUID];
                $cloudCredsRequirements = ['cloudCredentialsId' => static::REGEXP_SHORT_UUID];
                $orchestrationRuleRequirements = ['ruleId' => '\d+'];
                $scriptRequirements = ['scriptId' => '\d+'];
                $scriptVersionReqs = array_merge($scriptRequirements, ['versionNumber' => '\d+']);

                $roleImageReqs = array_merge($roleRequirements, $imageRequirements);
                $roleCategoryReqs = ['roleCategoryId' => '\d+'];
                $roleVariableReqs = array_merge($roleRequirements, ['variableName' => '\w+']);
                $roleScriptReqs = array_merge($roleRequirements, $orchestrationRuleRequirements);

                $this->group('/admin', function () {
                    //All Admin API routes are here
                    $this->get('/users/:userId', 'Admin_Users:get', ['userId' => '\d+']);
                });

                $this->group('/account', function () use (
                    $eventRequirements,
                    $roleRequirements,
                    $imageRequirements,
                    $roleImageReqs,
                    $roleCategoryReqs,
                    $roleVariableReqs,
                    $roleScriptReqs,
                    $osRequirements,
                    $ccRequirements,
                    $cloudCredsRequirements,
                    $orchestrationRuleRequirements,
                    $scriptRequirements,
                    $scriptVersionReqs
                ) {
                    //All Account API routes are here
                    $envRequirements = ['envId' => '\d+'];
                    $teamsReqs = ['teamId' => '\d+'];
                    $envCloudsReqs = array_merge($envRequirements, ['cloud' => '\w+']);
                    $envTeamsReqs = array_merge($envRequirements, $teamsReqs);

                    $this->get('/os', ['controller' => 'User_Os:get']);
                    $this->get('/os/:osId', ['controller' => 'User_Os:fetch'], $osRequirements);

                    $this->get('/teams', 'Account_Teams:describe');
                    $this->post('/teams', 'Account_Teams:create');

                    $this->get('/teams/:teamId', 'Account_Teams:fetch', $teamsReqs);
                    $this->patch('/teams/:teamId', 'Account_Teams:modify', $teamsReqs);
                    $this->delete('/teams/:teamId', 'Account_Teams:delete', $teamsReqs);

                    $this->get('/acl-roles', 'Account_AclRoles:describe');

                    $this->get('/environments', 'Account_Environments:describe');
                    $this->post('/environments', 'Account_Environments:create');

                    $this->get('/environments/:envId', 'Account_Environments:fetch', $envRequirements);
                    $this->patch('/environments/:envId', 'Account_Environments:modify', $envRequirements);
                    $this->delete('/environments/:envId', 'Account_Environments:delete', $envRequirements);

                    $this->get('/environments/:envId/clouds', 'Account_Environments:describeClouds', $envRequirements);

                    $this->get('/environments/:envId/clouds/:cloud', 'Account_Environments:fetchCloudCredentials', $envCloudsReqs);
                    $this->post('/environments/:envId/clouds/:cloud', 'Account_Environments:attachCredentials', $envCloudsReqs);
                    $this->delete('/environments/:envId/clouds/:cloud', 'Account_Environments:detachCredentials', $envCloudsReqs);

                    $this->get('/environments/:envId/teams', 'Account_Environments:describeTeams', $envRequirements);
                    $this->post('/environments/:envId/teams', 'Account_Environments:allowTeam', $envRequirements);

                    $this->delete('/environments/:envId/teams/:teamId', 'Account_Environments:denyTeam', $envTeamsReqs);

                    //We explicitly set array of the options to exclude the Route Name as both environment and
                    //account levels have the same controllers and all registered Route Names must be unique withing the router.
                    $this->get('/events', ['controller' => 'User_Events:describe']);
                    $this->get('/events/:eventId', ['controller' => 'User_Events:fetch'], $eventRequirements);
                    $this->post('/events', ['controller' => 'User_Events:create']);
                    $this->patch('/events/:eventId', ['controller' => 'User_Events:modify'], $eventRequirements);
                    $this->delete('/events/:eventId', ['controller' => 'User_Events:delete'], $eventRequirements);

                    $this->get('/images', ['controller' => 'User_Images:describe']);
                    $this->post('/images', ['controller' => 'User_Images:register']);

                    $this->get('/images/:imageId', ['controller' => 'User_Images:fetch'], $imageRequirements);
                    $this->patch('/images/:imageId', ['controller' => 'User_Images:modify'], $imageRequirements);
                    $this->delete('/images/:imageId', ['controller' => 'User_Images:deregister'], $imageRequirements);

                    $this->post('/images/:imageId/actions/:action/', ['controller' => 'User_Images:copy'], array_merge($imageRequirements, ['action' => 'copy']));

                    $this->get('/roles', ['controller' => 'User_Roles:describe']);
                    $this->post('/roles', ['controller' => 'User_Roles:create']);

                    $this->get('/roles/:roleId', ['controller' => 'User_Roles:fetch'], $roleRequirements);
                    $this->patch('/roles/:roleId', ['controller' => 'User_Roles:modify'], $roleRequirements);
                    $this->delete('/roles/:roleId', ['controller' => 'User_Roles:delete'], $roleRequirements);

                    $this->get('/roles/:roleId/images/', ['controller' => 'User_Roles:describeImages'], $roleRequirements);
                    $this->post('/roles/:roleId/images/', ['controller' => 'User_Roles:registerImage'], $roleRequirements);

                    $this->get('/roles/:roleId/images/:imageId', ['controller' => 'User_Roles:fetchImage'], $roleImageReqs);
                    $this->delete('/roles/:roleId/images/:imageId', ['controller' => 'User_Roles:deregisterImage'], $roleImageReqs);

                    $this->post('/roles/:roleId/images/:imageId/actions/:action/', ['controller' => 'User_Roles:replaceImage'], array_merge($roleImageReqs, ['action' => 'replace']));

                    $this->get('/role-categories', ['controller' => 'User_RoleCategories:describe']);
                    $this->post('/role-categories', ['controller' => 'User_RoleCategories:create']);

                    $this->get('/role-categories/:roleCategoryId', ['controller' => 'User_RoleCategories:fetch'], $roleCategoryReqs);
                    $this->patch('/role-categories/:roleCategoryId', ['controller' => 'User_RoleCategories:modify'], $roleCategoryReqs);
                    $this->delete('/role-categories/:roleCategoryId', ['controller' => 'User_RoleCategories:delete'], $roleCategoryReqs);

                    $this->get('/roles/:roleId/global-variables', ['controller' => 'User_Roles:describeVariables'], $roleRequirements);
                    $this->post('/roles/:roleId/global-variables', ['controller' => 'User_Roles:createVariable'], $roleRequirements);

                    $this->get('/roles/:roleId/global-variables/:variableName', ['controller' => 'User_Roles:fetchVariable'], $roleVariableReqs);
                    $this->patch('/roles/:roleId/global-variables/:variableName', ['controller' => 'User_Roles:modifyVariable'], $roleVariableReqs);
                    $this->delete('/roles/:roleId/global-variables/:variableName', ['controller' => 'User_Roles:deleteVariable'], $roleVariableReqs);

                    $this->get('/roles/:roleId/orchestration-rules', ['controller' => 'User_RoleScripts:describe'], $roleRequirements);
                    $this->post('/roles/:roleId/orchestration-rules', ['controller' => 'User_RoleScripts:create'], $roleRequirements);

                    $this->get('/roles/:roleId/orchestration-rules/:ruleId', ['controller' => 'User_RoleScripts:fetch'], $roleScriptReqs);
                    $this->patch('/roles/:roleId/orchestration-rules/:ruleId', ['controller' => 'User_RoleScripts:modify'], $roleScriptReqs);
                    $this->delete('/roles/:roleId/orchestration-rules/:ruleId', ['controller' => 'User_RoleScripts:delete'], $roleScriptReqs);

                    $this->get('/orchestration-rules', 'User_AccountScripts:describe');
                    $this->post('/orchestration-rules', 'User_AccountScripts:create');

                    $this->get('/orchestration-rules/:ruleId', 'User_AccountScripts:fetch', $orchestrationRuleRequirements);
                    $this->patch('/orchestration-rules/:ruleId', 'User_AccountScripts:modify', $orchestrationRuleRequirements);
                    $this->delete('/orchestration-rules/:ruleId', 'User_AccountScripts:delete', $orchestrationRuleRequirements);

                    $this->get('/scripts', ['controller' => 'User_Scripts:describe']);
                    $this->post('/scripts', ['controller' => 'User_Scripts:create']);

                    $this->get('/scripts/:scriptId', ['controller' => 'User_Scripts:fetch'], $scriptRequirements);
                    $this->patch('/scripts/:scriptId', ['controller' => 'User_Scripts:modify'], $scriptRequirements);
                    $this->delete('/scripts/:scriptId', ['controller' => 'User_Scripts:delete'], $scriptRequirements);

                    $this->get('/scripts/:scriptId/script-versions', ['controller' => 'User_ScriptVersions:describe'], $scriptRequirements);
                    $this->post('/scripts/:scriptId/script-versions', ['controller' => 'User_ScriptVersions:create'], $scriptRequirements);

                    $this->get('/scripts/:scriptId/script-versions/:versionNumber', ['controller' => 'User_ScriptVersions:fetch'], $scriptVersionReqs);
                    $this->patch('/scripts/:scriptId/script-versions/:versionNumber', ['controller' => 'User_ScriptVersions:modify'], $scriptVersionReqs);
                    $this->delete('/scripts/:scriptId/script-versions/:versionNumber', ['controller' => 'User_ScriptVersions:delete'], $scriptVersionReqs);

                    $this->get("/cloud-credentials", ['controller' => 'User_CloudCredentials:describe']);
                    $this->post("/cloud-credentials", ['controller' => 'User_CloudCredentials:create']);

                    $this->get("/cloud-credentials/:cloudCredentialsId", ['controller' => 'User_CloudCredentials:fetch'], $cloudCredsRequirements);
                    $this->patch("/cloud-credentials/:cloudCredentialsId", ['controller' => 'User_CloudCredentials:modify'], $cloudCredsRequirements);
                    $this->delete("/cloud-credentials/:cloudCredentialsId", ['controller' => 'User_CloudCredentials:delete'], $cloudCredsRequirements);

                    if ($this->getContainer()->config->{"scalr.analytics.enabled"}) {
                        $this->get('/cost-centers/', ['controller' => 'User_CostCenters:describe']);
                        $this->get('/cost-centers/:ccId', ['controller' => 'User_CostCenters:fetch'], $ccRequirements);
                    }
                });

                $this->group('/user/:environment', [$this, 'handleEnvironment'], [$this, 'environmentAuthenticationMiddleware'], function () use (
                    $eventRequirements,
                    $roleRequirements,
                    $imageRequirements,
                    $roleImageReqs,
                    $roleCategoryReqs,
                    $roleVariableReqs,
                    $roleScriptReqs,
                    $osRequirements,
                    $ccRequirements,
                    $cloudCredsRequirements,
                    $orchestrationRuleRequirements,
                    $scriptRequirements,
                    $scriptVersionReqs
                ) {
                    //All User API Environment level routes are here
                    $farmRequirements = ['farmId' => '\d+'];
                    $farmRoleRequirements = ['farmRoleId' => '\d+'];

                    $farmVariableReqs = array_merge($farmRequirements, ['variableName' => '\w+']);
                    $farmRoleVariableReqs = array_merge($farmRoleRequirements, ['variableName' => '\w+']);
                    $farmRoleScriptReqs = array_merge($farmRoleRequirements, $orchestrationRuleRequirements);
                    $scalingMetricsReqs = ['metricName' => Entity\ScalingMetric::NAME_REGEXP];
                    $farmRoleScalingRuleReqs = array_merge($farmRoleRequirements, ['scalingRuleName' => Entity\ScalingMetric::NAME_REGEXP]);
                    $serverRequirements = ['serverId' => ApiApplication::REGEXP_UUID];

                    $this->get('/os', 'User_Os:get');
                    $this->get('/os/:osId', 'User_Os:fetch', $osRequirements);

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

                    $this->get('/role-categories', 'User_RoleCategories:describe');
                    $this->post('/role-categories', 'User_RoleCategories:create');

                    $this->get('/role-categories/:roleCategoryId', 'User_RoleCategories:fetch', $roleCategoryReqs);
                    $this->patch('/role-categories/:roleCategoryId', 'User_RoleCategories:modify', $roleCategoryReqs);
                    $this->delete('/role-categories/:roleCategoryId', 'User_RoleCategories:delete', $roleCategoryReqs);

                    $this->get('/roles/:roleId/global-variables', 'User_Roles:describeVariables', $roleRequirements);
                    $this->post('/roles/:roleId/global-variables', 'User_Roles:createVariable', $roleRequirements);

                    $this->get('/roles/:roleId/global-variables/:variableName', 'User_Roles:fetchVariable', $roleVariableReqs);
                    $this->patch('/roles/:roleId/global-variables/:variableName', 'User_Roles:modifyVariable', $roleVariableReqs);
                    $this->delete('/roles/:roleId/global-variables/:variableName', 'User_Roles:deleteVariable', $roleVariableReqs);

                    $this->get('/roles/:roleId/orchestration-rules', 'User_RoleScripts:describe', $roleRequirements);
                    $this->post('/roles/:roleId/orchestration-rules', 'User_RoleScripts:create', $roleRequirements);

                    $this->get('/roles/:roleId/orchestration-rules/:ruleId', 'User_RoleScripts:fetch', $roleScriptReqs);
                    $this->patch('/roles/:roleId/orchestration-rules/:ruleId', 'User_RoleScripts:modify', $roleScriptReqs);
                    $this->delete('/roles/:roleId/orchestration-rules/:ruleId', 'User_RoleScripts:delete', $roleScriptReqs);

                    $this->get('/farms', 'User_Farms:describe');
                    $this->post('/farms', 'User_Farms:create');

                    $this->get('/farms/:farmId', 'User_Farms:fetch', $farmRequirements);
                    $this->patch('/farms/:farmId', 'User_Farms:modify', $farmRequirements);
                    $this->delete('/farms/:farmId', 'User_Farms:delete', $farmRequirements);

                    $this->get('/farms/:farmId/servers', 'User_Farms:describeServers', $farmRequirements);

                    $this->get('/farms/:farmId/global-variables', 'User_Farms:describeVariables', $farmRequirements);
                    $this->post('/farms/:farmId/global-variables', 'User_Farms:createVariable', $farmRequirements);

                    $this->get('/farms/:farmId/global-variables/:variableName', 'User_Farms:fetchVariable', $farmVariableReqs);
                    $this->patch('/farms/:farmId/global-variables/:variableName', 'User_Farms:modifyVariable', $farmVariableReqs);
                    $this->delete('/farms/:farmId/global-variables/:variableName', 'User_Farms:deleteVariable', $farmVariableReqs);

                    $this->post('/farms/:farmId/actions/launch', 'User_Farms:launch', $farmRequirements);
                    $this->post('/farms/:farmId/actions/terminate', 'User_Farms:terminate', $farmRequirements);
                    $this->post('/farms/:farmId/actions/clone', 'User_Farms:clone', $farmRequirements);

                    $this->get('/farms/:farmId/farm-roles', 'User_FarmRoles:describe', $farmRequirements);
                    $this->post('/farms/:farmId/farm-roles', 'User_FarmRoles:create', $farmRequirements);

                    $this->get('/farm-roles/:farmRoleId', 'User_FarmRoles:fetch', $farmRoleRequirements);
                    $this->patch('/farm-roles/:farmRoleId', 'User_FarmRoles:modify', $farmRoleRequirements);
                    $this->delete('/farm-roles/:farmRoleId', 'User_FarmRoles:delete', $farmRoleRequirements);

                    $this->get('/farm-roles/:farmRoleId/servers', 'User_FarmRoles:describeServers', $farmRoleRequirements);
                    $this->post('/farm-roles/:farmRoleId/actions/import-server', 'User_FarmRoles:importServer', $farmRoleRequirements);

                    $this->get('/farm-roles/:farmRoleId/placement', 'User_FarmRoles:describePlacement', $farmRoleRequirements);
                    $this->patch('/farm-roles/:farmRoleId/placement', 'User_FarmRoles:modifyPlacement', $farmRoleRequirements);

                    $this->get('/farm-roles/:farmRoleId/instance', 'User_FarmRoles:describeInstance', $farmRoleRequirements);
                    $this->patch('/farm-roles/:farmRoleId/instance', 'User_FarmRoles:modifyInstance', $farmRoleRequirements);

                    $this->get('/farm-roles/:farmRoleId/scaling', 'User_FarmRoles:describeScaling', $farmRoleRequirements);
                    $this->post('/farm-roles/:farmRoleId/scaling', 'User_FarmRoles:createScalingRule', $farmRoleRequirements);
                    $this->patch('/farm-roles/:farmRoleId/scaling', 'User_FarmRoles:modifyScaling', $farmRoleRequirements);

                    $this->get('/farm-roles/:farmRoleId/scaling/:scalingRuleName', 'User_FarmRoles:fetchScalingRule', $farmRoleScalingRuleReqs);
                    $this->patch('/farm-roles/:farmRoleId/scaling/:scalingRuleName', 'User_FarmRoles:modifyScalingRule', $farmRoleScalingRuleReqs);
                    $this->delete('/farm-roles/:farmRoleId/scaling/:scalingRuleName', 'User_FarmRoles:deleteScalingRule', $farmRoleScalingRuleReqs);

                    $this->get('/farm-roles/:farmRoleId/global-variables', 'User_FarmRoles:describeVariables', $farmRoleRequirements);
                    $this->post('/farm-roles/:farmRoleId/global-variables', 'User_FarmRoles:createVariable', $farmRoleRequirements);

                    $this->get('/farm-roles/:farmRoleId/global-variables/:variableName', 'User_FarmRoles:fetchVariable', $farmRoleVariableReqs);
                    $this->patch('/farm-roles/:farmRoleId/global-variables/:variableName', 'User_FarmRoles:modifyVariable', $farmRoleVariableReqs);
                    $this->delete('/farm-roles/:farmRoleId/global-variables/:variableName', 'User_FarmRoles:deleteVariable', $farmRoleVariableReqs);

                    $this->get('/farm-roles/:farmRoleId/orchestration-rules', 'User_FarmRoleScripts:describe', $farmRoleRequirements);
                    $this->post('/farm-roles/:farmRoleId/orchestration-rules', 'User_FarmRoleScripts:create', $farmRoleRequirements);

                    $this->get('/farm-roles/:farmRoleId/orchestration-rules/:ruleId', 'User_FarmRoleScripts:fetch', $farmRoleScriptReqs);
                    $this->patch('/farm-roles/:farmRoleId/orchestration-rules/:ruleId', 'User_FarmRoleScripts:modify', $farmRoleScriptReqs);
                    $this->delete('/farm-roles/:farmRoleId/orchestration-rules/:ruleId', 'User_FarmRoleScripts:delete', $farmRoleScriptReqs);

                    $this->get('/servers', 'User_Servers:describe');
                    $this->get('/servers/:serverId', 'User_Servers:fetch', $serverRequirements);
                    $this->post('/servers/:serverId/actions/suspend', 'User_Servers:suspend', $serverRequirements);
                    $this->post('/servers/:serverId/actions/terminate', 'User_Servers:terminate', $serverRequirements);
                    $this->post('/servers/:serverId/actions/resume', 'User_Servers:resume', $serverRequirements);
                    $this->post('/servers/:serverId/actions/reboot', 'User_Servers:reboot', $serverRequirements);

                    $this->get('/scaling-metrics', 'User_ScalingMetrics:describe');
                    $this->post('/scaling-metrics', 'User_ScalingMetrics:create');

                    $this->get('/scaling-metrics/:metricName', 'User_ScalingMetrics:fetch', $scalingMetricsReqs);
                    $this->patch('/scaling-metrics/:metricName', 'User_ScalingMetrics:modify', $scalingMetricsReqs);
                    $this->delete('/scaling-metrics/:metricName', 'User_ScalingMetrics:delete', $scalingMetricsReqs);

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

                    $this->get("/cloud-credentials", 'User_CloudCredentials:describe');
                    $this->post("/cloud-credentials", 'User_CloudCredentials:create');

                    $this->get("/cloud-credentials/:cloudCredentialsId", 'User_CloudCredentials:fetch', $cloudCredsRequirements);
                    $this->patch("/cloud-credentials/:cloudCredentialsId", 'User_CloudCredentials:modify', $cloudCredsRequirements);
                    $this->delete("/cloud-credentials/:cloudCredentialsId", 'User_CloudCredentials:delete', $cloudCredsRequirements);

                    if ($this->getContainer()->config->{"scalr.analytics.enabled"}) {
                        $projectRequirements = ['projectId' => ApiApplication::REGEXP_UUID];

                        $this->get('/cost-centers/', 'User_CostCenters:describe');
                        $this->get('/cost-centers/:ccId', 'User_CostCenters:fetch', $ccRequirements);

                        $this->get('/projects/', 'User_Projects:describe');
                        $this->post('/projects/', 'User_Projects:create');

                        $this->get('/projects/:projectId', 'User_Projects:fetch', $projectRequirements);
                    }
                });
            });
        });

        return $this;
    }

    /**
     * Preflight request middleware handler
     *
     * @throws StopException
     */
    public function preflightRequestHandlerMiddleware()
    {
        $origin = $this->request->getOrigin();
        $requestMethod = $this->request->getMethod();
        
        if (!(empty($this->request->getUserAgent()) || empty($origin))) {
            $allowedOrigins = (array) Scalr::getContainer()->config('scalr.system.api.allowed_origins');

            if (!empty($allowedOrigins)) {
                $this->response->setHeader('Access-Control-Allow-Origin', array_intersect(['*', $origin], $allowedOrigins) ? $origin : implode(' ', $allowedOrigins));
                $this->response->setHeader('Vary', 'Origin, User-Agent');
            }

            if ($requestMethod === Request::METHOD_OPTIONS) {
                $this->response->setHeader('Access-Control-Allow-Methods', 'GET, HEAD, POST, PATCH, PUT, DELETE, OPTIONS');
                $this->response->setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Scalr-Date, X-Scalr-Key-Id, X-Scalr-Signature, X-Scalr-Debug');
                $this->stop();
            }
        } else if ($requestMethod === Request::METHOD_OPTIONS) {
            $this->response->setHeader('Allow', 'GET, HEAD, POST, PATCH, PUT, DELETE, OPTIONS');
            $this->stop();
        }
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
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $this->limiter->checkAccountRateLimit($this->apiKey->keyId);

        //Validates API version
        if ($this->settings[ApiApplication::SETTING_API_VERSION] != 1) {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, 'Invalid API version');
        }

        if ($this->request->getBody() !== '' && strtolower($this->request->getMediaType()) !== 'application/json') {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, 'Invalid Content-Type');
        }

        $this->setUser($user);

        $container = $this->getContainer();

        //Releases auditloger to ensure it will be updated
        $container->release('auditlogger');

        $container->set('auditlogger.request', function () {
            return $this;
        });
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
        //Sets Environment to Audit Logger
        $this->getContainer()->auditlogger->setEnvironmentId($environment->id);
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
                (isset($args[1]) ? $args[1] : null)
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
    public function checkPermissions(...$args)
    {
        if (count($args) == 3) {
            $message = array_pop($args);
        } else {
            $message = null;
        }

        $result = $this->hasPermissions(...$args);

        if (!$result) {
            throw new ApiInsufficientPermissionsException($message);
        }
    }

    /**
     * Gets current API request scope
     *
     * @return string Returns scope
     */
    public function getScope()
    {
        return isset($this->env) ? ScopeInterface::SCOPE_ENVIRONMENT : (isset($this->user) ? ScopeInterface::SCOPE_ACCOUNT : ScopeInterface::SCOPE_SCALR);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\Rest\Application::error()
     */
    public function error($e = null)
    {
        try {
            parent::error($e);
        } catch (StopException $e) {
            $this->getContainer()->apilogger->log('api.error', $this->request, $this->response);
            $this->stop();
        }

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
        $config->envId = $this->env ? $this->env->id : null;
        $config->remoteAddr = $this->request->getIp();

        return $config;
    }

    /**
     * {@inheritdoc}
     * @see Application::get()
     */
    public function get($path, $options, $requirements = [])
    {
        return parent::get($path, $options, $requirements)->addMethod(Request::METHOD_OPTIONS);
    }

    /**
     * {@inheritdoc}
     * @see Application::post()
     */
    public function post($path, $options, $requirements = [])
    {
        return parent::post($path, $options, $requirements)->addMethod(Request::METHOD_OPTIONS);
    }

    /**
     * {@inheritdoc}
     * @see Application::put()
     */
    public function put($path, $options, $requirements = [])
    {
        return parent::put($path, $options, $requirements)->addMethod(Request::METHOD_OPTIONS);
    }

    /**
     * {@inheritdoc}
     * @see Application::patch()
     */
    public function patch($path, $options, $requirements = [])
    {
        return parent::patch($path, $options, $requirements)->addMethod(Request::METHOD_OPTIONS);
    }

    /**
     * {@inheritdoc}
     * @see Application::delete()
     */
    public function delete($path, $options, $requirements = [])
    {
        return parent::delete($path, $options, $requirements)->addMethod(Request::METHOD_OPTIONS);
    }
}
