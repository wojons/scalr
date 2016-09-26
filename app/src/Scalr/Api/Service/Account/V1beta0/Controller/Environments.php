<?php

namespace Scalr\Api\Service\Account\V1beta0\Controller;

use Exception;
use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Exception\ApiInsufficientPermissionsException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\Account\V1beta0\Adapter\EnvironmentAdapter;
use Scalr\Api\Service\Account\V1beta0\Adapter\EnvironmentTeamAdapter;
use Scalr\Api\Service\User\V1beta0\Controller\CloudCredentials;
use Scalr\Api\Service\User\V1beta0\Controller\CostCenters;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\ModelException;
use Scalr\Model\AbstractEntity;
use Scalr\Exception\ObjectInUseException;
use Scalr\Model\Entity\Account;
use Scalr\Model\Entity;
use Scalr\Net\Ldap\Exception\LdapException;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\System\Config\Exception\YamlException;
use SERVER_PLATFORMS;

/**
 * Account/Environments API Controller
 *
 * @author N.V.
 */
class Environments extends ApiController
{
    /**
     * @var CloudCredentials
     */
    private $cloudCredsController;

    /**
     * @var CostCenters
     */
    private $costCentersController;

    /**
     * @var Teams
     */
    private $teamsController;

    /**
     * Gets CloudCredentials controller
     *
     * @return CloudCredentials
     */
    public function getCloudCredsController()
    {
        if (empty($this->cloudCredsController)) {
            $this->cloudCredsController = $this->getContainer()->api->controller(CloudCredentials::class);
        }

        return $this->cloudCredsController;
    }

    /**
     * Gets CloudCredentials entity
     *
     * @param   string  $cloudCredentialsId Unique identifier of the CloudCredentials
     *
     * @return Entity\CloudCredentials
     *
     * @throws ApiErrorException
     */
    public function getCloudCredentials($cloudCredentialsId)
    {
        /* @var $cloudCredentials Entity\CloudCredentials */
        $cloudCredentials = Entity\CloudCredentials::findPk($cloudCredentialsId);

        if (empty($cloudCredentials)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Cloud Credentials either does not exist or is not owned by you.");
        }

        switch ($cloudCredentials->getScope()) {
            case ScopeInterface::SCOPE_SCALR:
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                if ($cloudCredentials->accountId != $this->getUser()->getAccountId()) {
                    throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Cloud Credentials either does not exist or is not owned by you.");
                }
                break;

            case ScopeInterface::SCOPE_ENVIRONMENT:
                if (!($this->getUser()->canManageAcl() || $this->getUser()->hasAccessToEnvironment($cloudCredentials->envId))) {
                    throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
                }
        }

        return $cloudCredentials;
    }

    /**
     * Gets CostCenters controller
     *
     * @return CostCenters
     */
    public function getCostCentersController()
    {
        if (empty($this->costCentersController)) {
            $this->costCentersController = $this->getContainer()->api->controller(CostCenters::class);
        }

        return $this->costCentersController;
    }

    /**
     * Gets CostCentre entity
     *
     * @param   string  $ccId   Unique identifier of the CostCentre
     *
     * @return CostCentreEntity
     *
     * @throws ApiErrorException
     */
    public function getCostCenter($ccId)
    {
        return $this->getCostCentersController()->getCostCenter($ccId);
    }

    /**
     * Gets Teams controller
     *
     * @return Teams
     */
    public function getTeamsController()
    {
        if (empty($this->teamsController)) {
            $this->teamsController = $this->getContainer()->api->controller(Teams::class);
        }

        return $this->teamsController;
    }

    /**
     * Gets Team entity
     *
     * @param   string  $teamId Unique identifier of the Team
     *
     * @return  Account\Team
     *
     * @throws  ApiErrorException
     */
    public function getTeam($teamId)
    {
        return $this->getTeamsController()->getTeam($teamId);
    }

    /**
     * Retrieves the list of the Environments
     *
     * @return  array   Returns describe result
     */
    public function describeAction()
    {
        if (!$this->getUser()->canManageAcl()) {
            $this->checkPermissions(Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT);
        }

        return $this->adapter('environment')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Gets default search criteria according current account
     *
     * @return  array   Returns array of the search criteria
     */
    private function getDefaultCriteria()
    {
        $criteria = [['accountId' => $this->getUser()->getAccountId()]];
        $user = $this->getUser();
        if (!$user->canManageAcl()) {
            $env      = new Account\Environment();
            $teamEnv  = new Account\TeamEnvs();
            $team     = new Account\Team();
            $teamUser = new Account\TeamUser();

            $criteria = array_merge($criteria, [
                AbstractEntity::STMT_DISTINCT => true,
                AbstractEntity::STMT_FROM   => " {$env->table()}
                    JOIN  {$teamEnv->table('te')} ON  {$teamEnv->columnEnvId('te')}  =  {$env->columnId()}
                    JOIN  {$team->table('at')} ON  {$team->columnId('at')}  = {$teamEnv->columnTeamId('te')}
                    JOIN  {$teamUser->table('tu')}  ON  {$teamUser->columnTeamId('tu')} = {$team->columnId('at')}
                ",
                AbstractEntity::STMT_WHERE  => "{$teamUser->columnUserId('tu')}  = " . $teamUser->qstr('userId', $user->id)
                   . " AND  {$team->columnAccountId('at')}  = " . $team->qstr('accountId', $user->getAccountId())
            ]);
        }

        return $criteria;
    }

    /**
     * Gets specified Environment
     *
     * @param      string $envId  Numeric identifier of the Environment
     *
     * @return  Account\Environment Returns the Environment Entity on success
     *
     * @throws ApiErrorException
     *
     */
    public function getEnv($envId)
    {
        /* @var $env Account\Environment */
        $env = Account\Environment::findOne(array_merge($this->getDefaultCriteria(), [['id' => $envId]]));

        if (!$env) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Environment either does not exist or is not owned by your account.");
        }

        if (!$this->getUser()->hasAccessToEnvironment($envId)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $env;
    }

    /**
     * Fetches detailed info about one environment
     *
     * @param    string $envId Numeric identifier of the environment
     *
     * @return   ResultEnvelope
     *
     * @throws   ApiErrorException
     */
    public function fetchAction($envId)
    {
        $this->checkPermissions(Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT);

        return $this->result($this->adapter('environment')->toData($this->getEnv($envId)));
    }

    /**
     * Create a new Environment in this Account
     */
    public function createAction()
    {
        if (!$this->getUser()->isAccountSuperAdmin() && !$this->getUser()->isAccountOwner()) {
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient Permissions");
        }

        $object = $this->request->getJsonBody();

        /* @var $envAdapter EnvironmentAdapter */
        $envAdapter = $this->adapter('environment');

        //Pre validates the request object
        $envAdapter->validateObject($object, Request::METHOD_POST);

        $env = $envAdapter->toEntity($object);

        $env->id = null;
        $env->accountId = $this->getUser()->getAccountId();

        if (!isset($env->status)) {
            $env->status = Account\Environment::STATUS_ACTIVE;
        }

        $envAdapter->validateEntity($env);

        //Saves entity
        $env->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($envAdapter->toData($env));
    }

    /**
     * Change environment attributes.
     *
     * @param   int $envId  Unique identifier of the environment
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     */
    public function modifyAction($envId)
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient Permissions");
        }

        $object = $this->request->getJsonBody();

        /* @var $envAdapter EnvironmentAdapter */
        $envAdapter = $this->adapter('environment');

        //Pre validates the request object
        $envAdapter->validateObject($object, Request::METHOD_PATCH);

        $env = $this->getEnv($envId);

        //Copies all alterable properties to fetched Role Entity
        $envAdapter->copyAlterableProperties($object, $env);

        //Re-validates an Entity
        $envAdapter->validateEntity($env);

        //Saves verified results
        $env->save();

        return $this->result($envAdapter->toData($env));
    }

    /**
     * Delete an Environment from this Account
     *
     * @param   string  $envId  Unique identifier of the Environment
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function deleteAction($envId)
    {
        if (!$this->getUser()->isAccountSuperAdmin() && !$this->getUser()->isAccountOwner()) {
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient Permissions");
        }

        $env = $this->getEnv($envId);

        try {
            $env->delete();
        } catch (ObjectInUseException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, $e->getMessage(), $e->getCode(), $e);
        }

        return $this->result(null);
    }

    /**
     * Lists configured clouds for specified Environment
     *
     * @param   string  $envId  Unique identifier of the Environment
     *
     * @return  ListResultEnvelope
     * @throws  ApiErrorException
     */
    public function describeCloudsAction($envId)
    {
        $env = $this->getEnv($envId);

        $clouds = [];

        foreach ($env->cloudCredentialsList() as $credentials) {
            $clouds[] = [
                'cloud' => $credentials->cloud,
                'credentials' => [
                    'id' => $credentials->id
                ]
            ];
        }

        return $this->resultList($clouds, count($clouds));
    }

    /**
     * Configures cloud credentials
     *
     * @param   int     $envId  Environment ID
     * @param   string  $cloud  Cloud platform name
     *
     * @return  ResultEnvelope
     *
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function attachCredentialsAction($envId, $cloud)
    {
        if (!$this->getUser()->canManageAcl()) {
            $this->checkPermissions(Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT);
        }

        $env = $this->getEnv($envId);

        $object = $this->request->getJsonBody();

        $cloudCredentialsId = ApiController::getBareId($object, 'id');

        if (empty($cloudCredentialsId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property cloudCredentials.id");
        }

        $cloudCredentials = $this->getCloudCredentials($cloudCredentialsId);

        if ($cloudCredentials->envId != $envId && $cloudCredentials->getScope() == ScopeInterface::SCOPE_ENVIRONMENT) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Cloud credentials '{$cloudCredentialsId}' not found!");
        }

        if ($cloud != $cloudCredentials->cloud) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Cloud Credentials mismatch");
        }

        $prevCloudCredentials = $env->keychain($cloud);

        if (isset($prevCloudCredentials->id)) {
            if ($prevCloudCredentials->id == $cloudCredentialsId) {
                return $this->result($this->getCloudCredsController()->adapter($prevCloudCredentials)->toData($prevCloudCredentials));
            }

            switch ($cloud) {
                case SERVER_PLATFORMS::EC2:
                    $checkEnvIsEmpty = $cloudCredentials->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] != $prevCloudCredentials->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID];
                    break;

                case SERVER_PLATFORMS::GCE:
                    $checkEnvIsEmpty = $cloudCredentials->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID] != $prevCloudCredentials->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];
                    break;

                default:
                    $checkEnvIsEmpty = false;
                    break;
            }

            if ($checkEnvIsEmpty &&
                (count(Entity\Server::find([['envId' => $envId], ['platform' => $cloud]])) ||
                 count(Entity\Image::find([['envId' => $envId], ['platform' => $cloud]])))) {
                throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, "Cloud Credentials are used");
            }
        }

        $cloudCredentials->bindEnvironment($envId)->save();

        return $this->result($this->getCloudCredsController()->adapter($cloudCredentials)->toData($cloudCredentials));
    }

    /**
     * Fetch detailed info about specified cloud configuration
     *
     * @param   int     $envId  Environment ID
     * @param   string  $cloud  Cloud platform name
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     */
    public function fetchCloudCredentialsAction($envId, $cloud)
    {
        if (!$this->getUser()->canManageAcl()) {
            $this->checkPermissions(Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT);
        }

        $env = $this->getEnv($envId);

        $cloudCredentials = $env->keychain($cloud);

        if (empty($cloudCredentials->id)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Cloud '{$cloud}' not configured for this environment");
        }

        return $this->result($this->getCloudCredsController()->adapter($cloudCredentials)->toData($cloudCredentials));
    }

    /**
     * Detach configured cloud configuration from specified environment
     *
     * @param   int     $envId  Environment ID
     * @param   string  $cloud  Cloud platform name
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function detachCredentialsAction($envId, $cloud)
    {
        if (!$this->getUser()->canManageAcl()) {
            $this->checkPermissions(Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT);
        }

        $env = $this->getEnv($envId);

        $cloudCredentials = $env->keychain($cloud);

        if (empty($cloudCredentials->id)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Cloud '{$cloud}' not configured for this environment");
        }

        if (in_array($cloudCredentials->cloud, [SERVER_PLATFORMS::EC2, SERVER_PLATFORMS::GCE]) &&
            (count(Entity\Server::find([['envId' => $envId], ['platform' => $cloudCredentials->cloud]])) ||
             count(Entity\Image::find([['envId' => $envId], ['platform' => $cloudCredentials->cloud]])))) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, "Cloud Credentials are used");
        }

        $cloudCredentials->environments[$envId]->delete();

        return $this->result(null);
    }

    /**
     * Describes teams that have access to specified environment
     *
     * @param   int $envId  Environment ID
     *
     * @return  ListResultEnvelope
     *
     * @throws  ApiErrorException
     */
    public function describeTeamsAction($envId)
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiInsufficientPermissionsException();
        }

        $this->getEnv($envId);

        return $this->adapter('environmentTeam')->getDescribeResult([['envId' => $envId]]);
    }

    /**
     * Allows team access to environment
     *
     * @param   int $envId Environment ID
     * @return  ResultEnvelope
     *
     * @throws ApiErrorException
     * @throws Exception
     * @throws LdapException
     * @throws YamlException
     */
    public function allowTeamAction($envId)
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiInsufficientPermissionsException();
        }

        /* @var  $envTeamAdapter EnvironmentTeamAdapter */
        $envTeamAdapter = $this->adapter('environmentTeam');

        $this->getEnv($envId);

        $object = $this->request->getJsonBody();

        $envTeamAdapter->validateObject($object, Request::METHOD_POST);

        $envTeam = $envTeamAdapter->toEntity($object);

        $envTeam->envId = $envId;

        $envTeamAdapter->validateEntity($envTeam);

        //Saves entity
        $envTeam->save();

        $this->response->setStatus(201);

        return $this->result($envTeamAdapter->toData($envTeam));
    }

    /**
     * Revoke team access to specified environment
     *
     * @param   int $envId  Environment ID
     * @param   int $teamId Team ID
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function denyTeamAction($envId, $teamId)
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiInsufficientPermissionsException();
        }

        $this->getEnv($envId);

        $team = Account\TeamEnvs::findOne([['envId' => $envId], ['teamId' => $teamId]]);

        if (empty($team)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Team '{$teamId}' has no access to environment '{$envId}'");
        }

        $team->delete();

        return $this->result(null);
    }
}
