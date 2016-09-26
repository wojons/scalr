<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentialsAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use SERVER_PLATFORMS;

/**
 * User/CloudCredentials API Controller
 *
 * @author N.V.
 */
class CloudCredentials extends ApiController
{

    /**
     * Internal alias for rackspacengus rackspacenguk platforms.
     * It's used to assign appropriate adapter class.
     */
    const PLATFORM_CLASS_RACKSPACE = 'rackspace';

    /**
     * The name of data field in charge of inheritance
     *
     * @var string
     */
    protected static $objectDiscriminator = 'cloudCredentialsType';

    /**
     * The name of entity field in charge of inheritance
     *
     * @var string
     */
    protected static $entityDescriminator = 'cloud';

    /**
     * Namespace for inherited adapters
     *
     * @var string
     */
    protected static $inheritedNamespace = 'CloudCredentials';

    protected static $inheritanceMap = [
        SERVER_PLATFORMS::EC2               => 'AwsCloudCredentials',
        SERVER_PLATFORMS::GCE               => 'GceCloudCredentials',
        SERVER_PLATFORMS::AZURE             => 'AzureCloudCredentials',
        SERVER_PLATFORMS::CLOUDSTACK        => 'CloudstackCloudCredentials',
        SERVER_PLATFORMS::OPENSTACK         => 'OpenstackCloudCredentials',
        self::PLATFORM_CLASS_RACKSPACE      => 'RackspaceCloudCredentials',
    ];

    protected $entityClass = 'Scalr\Model\Entity\CloudCredentials';

    /**
     * Gets a new Instance of the adapter
     *
     * @param   string|CloudCredentials|object  $name                The name of the adapter, or CloudCredentials entity, or cloud credentials data
     * @param   string                          $scope      optional The scope of the adapter
     * @param   string                          $version    optional The version of the adapter
     * @param   array                           $transform  optional The transformation rules for the type of the cloud credentials
     *
     * @return  ApiEntityAdapter    Returns the instance of cloud credentials adapter
     *
     * @throws  ApiErrorException
     */
    public function adapter($name, $scope = null, $version = null, array $transform = null)
    {
        if (is_object($name)) {
            $property = $name instanceof $this->entityClass
                ? static::$entityDescriminator
                : static::$objectDiscriminator;

            $value = empty($transform) ? $name->$property : $transform[$name->$property];

            switch (true) {
                case PlatformFactory::isOpenstack($value, true):
                    $value = SERVER_PLATFORMS::OPENSTACK;
                    break;

                case PlatformFactory::isCloudstack($value):
                    $value = SERVER_PLATFORMS::CLOUDSTACK;
                    break;

                case PlatformFactory::isRackspace($value):
                    $value = self::PLATFORM_CLASS_RACKSPACE;
                    break;
            }

            if (!isset(static::$inheritanceMap[$value])) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unknown cloud '{$value}'");
            }

            $class = empty(static::$inheritanceMap) ? $value : static::$inheritanceMap[$value];

            $name = empty(static::$inheritedNamespace) ? $class : (static::$inheritedNamespace . "\\{$class}");
        }

        return parent::adapter($name, $scope, $version);
    }

    /**
     * Gets default search criteria according request scope
     *
     * @return array Returns array of the search criteria
     */
    public function getDefaultCriteria()
    {
        return $this->getScopeCriteria();
    }

    /**
     * Gets CloudCredentials entity
     *
     * @param   string  $cloudCredentialsId Unique identifier of the CloudCredentials
     * @param   bool    $modify             Obtaining CloudCredentials in modification purposes
     *
     * @return Entity\CloudCredentials
     *
     * @throws ApiErrorException
     */
    public function getCloudCredentials($cloudCredentialsId, $modify = false)
    {
        $cloudCredentials = Entity\CloudCredentials::findPk($cloudCredentialsId);

        if (empty($cloudCredentials) || !$this->hasPermissions($cloudCredentials, $modify)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Cloud Credentials either does not exist or is not owned by you.");
        }

        return $cloudCredentials;
    }

    /**
     * Retrieves the list of the cloud credentials
     *
     * @return  ListResultEnvelope   Returns describe result
     */
    public function describeAction()
    {
        $this->checkPermissions(Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT);

        return $this->adapter('cloudCredentials')->getDescribeResult($this->getDefaultCriteria());
    }

    public function fetchAction($cloudCredentialsId)
    {
        $this->checkScopedPermissions('CLOUD_CREDENTIALS');

        $cloudCredentials = $this->getCloudCredentials($cloudCredentialsId);

        return $this->result($this->adapter($cloudCredentials)->toData($cloudCredentials));
    }

    public function createAction()
    {
        $this->checkScopedPermissions('CLOUD_CREDENTIALS');

        $object = $this->request->getJsonBody();

        if (!is_object($object)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Invalid body");
        }

        /* @var $adapter CloudCredentialsAdapter */
        $adapter = $this->adapter($object, null, null, array_flip(static::$inheritanceMap));

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_POST);

        $cloudCredentials = $adapter->toEntity($object);

        $user = $this->getUser();

        $cloudCredentials->id = null;

        switch ($this->getScope()) {
            case ScopeInterface::SCOPE_ENVIRONMENT:
                $cloudCredentials->accountId = $user->accountId;
                $cloudCredentials->envId = $this->getEnvironment()->id;
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                $cloudCredentials->accountId = $user->accountId;
                $cloudCredentials->envId = null;
                break;

            case ScopeInterface::SCOPE_SCALR:
                $cloudCredentials->accountId = null;
                $cloudCredentials->envId = null;
                break;
        }

        $cloudCredentials->status = Entity\CloudCredentials::STATUS_DISABLED;

        $adapter->validateEntity($cloudCredentials);

        //Saves entity
        $cloudCredentials->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($adapter->toData($cloudCredentials));
    }

    public function modifyAction($cloudCredentialsId)
    {
        $this->checkScopedPermissions('CLOUD_CREDENTIALS');

        $object = $this->request->getJsonBody();

        $cloudCredentials = $this->getCloudCredentials($cloudCredentialsId, true);

        /* @var $adapter CloudCredentialsAdapter */
        $adapter = $this->adapter($cloudCredentials);

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_PATCH);

        //We keep current configuration to compare with further changes to determine
        //whether there is a need to re-validate credentials on cloud
        /* @var $ccProps SettingsCollection */
        $ccProps = $cloudCredentials->properties;
        $ccProps->load();
        $prevConfiguration = clone $cloudCredentials;

        //Copies all alterable properties to fetched Role Entity
        $adapter->copyAlterableProperties($object, $cloudCredentials);

        //Re-validates an Entity
        $adapter->validateEntity($cloudCredentials, $prevConfiguration);

        //Saves verified results
        $cloudCredentials->save();

        return $this->result($adapter->toData($cloudCredentials));
    }

    public function deleteAction($cloudCredentialsId)
    {
        $this->checkScopedPermissions('CLOUD_CREDENTIALS');

        $cloudCredentials = $this->getCloudCredentials($cloudCredentialsId, true);

        if ($cloudCredentials->isUsed()) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, "Cloud Credentials that are in use can not be removed. Please disassociate them from the Environments first.");
        }

        $cloudCredentials->delete();

        return $this->result(null);
    }
}