<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\ScriptAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\ModelException;
use Scalr\Exception\ObjectInUseException;
use Scalr\Model\Entity\Script;
use Scalr\UI\Request\Validator;
use Scalr_Exception_Core;

/**
 * User/Scripts API Controller
 *
 * @author N.V.
 */
class Scripts extends ApiController
{

    /**
     * Retrieves the list of the scripts
     *
     * @return  array   Returns describe result
     */
    public function describeAction()
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT);

        return $this->adapter('script')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Gets default search criteria according environment scope
     *
     * @return array Returns array of the search criteria
     */
    private function getDefaultCriteria()
    {
        return $this->getScopeCriteria();
    }

    /**
     * Gets specified Script taking into account both scope and authentication token
     *
     * @param      string $scriptId          Numeric identifier of the Script
     * @param      bool   $modify   optional Flag checking write permissions
     *
     * @return Script Returns the Script Entity on success
     *
     * @throws ApiErrorException
     *
     */
    public function getScript($scriptId, $modify = false)
    {
        /* @var $script Script */
        $script = Script::findPk($scriptId);

        if (!$script) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Script either does not exist or is not owned by your environment.");
        }

        if (!$this->hasPermissions($script, $modify)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $script;
    }

    /**
     * Fetches detailed info about one script
     *
     * @param    string $scriptId Numeric identifier of the script
     *
     * @return   ResultEnvelope
     *
     * @throws   ApiErrorException
     */
    public function fetchAction($scriptId)
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT);

        return $this->result($this->adapter('script')->toData($this->getScript($scriptId)));
    }

    /**
     * Create a new Script in this Environment
     */
    public function createAction()
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var $scriptAdapter ScriptAdapter */
        $scriptAdapter = $this->adapter('script');

        //Pre validates the request object
        $scriptAdapter->validateObject($object, Request::METHOD_POST);

        //Read only property. It is needed before toEntity() call to set envId and accountId properties properly
        $object->scope = $this->getScope();

        $script = $scriptAdapter->toEntity($object);

        $script->id = null;

        $user = $this->getUser();

        $script->createdByEmail = $user->getEmail();
        $script->createdById = $user->getId();

        $scriptAdapter->validateEntity($script);

        //Saves entity
        $script->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($scriptAdapter->toData($script));
    }

    /**
     * Change script attributes.
     *
     * @param  int $scriptId Unique identifier of the script
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     */
    public function modifyAction($scriptId)
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var $scriptAdapter ScriptAdapter */
        $scriptAdapter = $this->adapter('script');

        //Pre validates the request object
        $scriptAdapter->validateObject($object, Request::METHOD_PATCH);

        $script = $this->getScript($scriptId, true);

        //Copies all alterable properties to fetched Role Entity
        $scriptAdapter->copyAlterableProperties($object, $script);

        //Re-validates an Entity
        $scriptAdapter->validateEntity($script);

        //Saves verified results
        $script->save();

        return $this->result($scriptAdapter->toData($script));
    }

    /**
     * Delete an Script from this Environment
     *
     * @param   string $scriptId Unique identifier of the script
     *
     * @return ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function deleteAction($scriptId)
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_MANAGE);

        $script = $this->getScript($scriptId, true);

        try {
            $script->delete();
        } catch (ObjectInUseException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, $e->getMessage(), $e->getCode(), $e);
        }

        return $this->result(null);
    }
}