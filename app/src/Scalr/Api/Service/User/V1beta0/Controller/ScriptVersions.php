<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Exception;
use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\ScriptVersionAdapter;
use Scalr\Exception\ModelException;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;

/**
 * User/Version-1beta0/ScriptsVersion API Controller
 *
 * @author N.V.
 */
class ScriptVersions  extends ApiController
{

    /**
     * Retrieves the list of the script versions
     *
     * @param $scriptId
     *
     * @return array Returns describe result
     *
     * @throws ApiErrorException
     */
    public function describeAction($scriptId)
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT);

        if (!$this->hasPermissions(Script::findPk($scriptId))) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $this->adapter('scriptVersion')->getDescribeResult([['scriptId' => $scriptId]]);
    }

    /**
     * Gets specified Version for the Script taking into account both scope and authentication token
     *
     * @param string $scriptId               Numeric identifier of the Script
     * @param int    $versionNumber          Script version number
     *
     * @param bool   $modify        optional Flag checking write permissions
     *
     * @return ScriptVersion Returns the Script Entity on success
     *
     * @throws ApiErrorException
     */
    public function getVersion($scriptId, $versionNumber, $modify = false)
    {
        $version = ScriptVersion::findPk($scriptId, $versionNumber);

        if (!$version) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Version either does not exist or is not owned by your environment.");
        }

        if (!$this->hasPermissions($version, $modify)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $version;
    }

    /**
     * Fetches detailed info about one script
     *
     * @param    string $scriptId      Numeric identifier of the script
     * @param    int    $versionNumber Script version Number
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     */
    public function fetchAction($scriptId, $versionNumber)
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT);

        return $this->result($this->adapter('scriptVersion')->toData($this->getVersion($scriptId, $versionNumber)));
    }

    /**
     * Create a new version of script
     *
     * @param  int $scriptId Unique identifier of the script
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function createAction($scriptId)
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var $versionAdapter ScriptVersionAdapter */
        $versionAdapter = $this->adapter('scriptVersion');

        if (empty($object->body)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed version content");
        }

        $object->body = str_replace("\r\n", "\n", $object->body);

        $versionAdapter->validateObject($object, Request::METHOD_POST);

        $version = $versionAdapter->toEntity($object);

        $version->scriptId = $scriptId;
        $version->changedById = $this->getUser()->id;
        $version->changedByEmail = $this->getUser()->email;

        $versionAdapter->validateEntity($version);

        /* @var $script Script */
        $script = Script::findPk($version->scriptId);

        try {
            $version->version = $script->getLatestVersion()->version + 1;
        } catch (Exception $e) {
            $version->version = 1;
        }

        //Saves entity
        $version->save();

        if (empty($script->os)) {
            $script->os = (!strncmp($version->content, '#!cmd', strlen('#!cmd')) || !strncmp($version->content, '#!powershell', strlen('#!powershell'))) ? Script::OS_WINDOWS : Script::OS_LINUX;
            $script->save();
        }

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($versionAdapter->toData($version));
    }

    /**
     * Change version attributes.
     *
     * @param  int  $scriptId         Unique identifier of the script
     * @param  int  $versionNumber    Script version number
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     */
    public function modifyAction($scriptId, $versionNumber)
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var $versionAdapter ScriptVersionAdapter */
        $versionAdapter = $this->adapter('scriptVersion');

        //Pre validates the request object
        $versionAdapter->validateObject($object, Request::METHOD_PATCH);

        $version = $this->getVersion($scriptId, $versionNumber, true);

        $version->changedById = $this->getUser()->getId();
        $version->changedByEmail = $this->getUser()->getEmail();

        //Copies all alterable properties to fetched Role Entity
        $versionAdapter->copyAlterableProperties($object, $version);

        //Re-validates an Entity
        $versionAdapter->validateEntity($version);

        //Saves verified results
        $version->save();

        return $this->result($versionAdapter->toData($version));
    }

    /**
     * Remove script version
     *
     * @param int $scriptId      Script ID
     * @param int $versionNumber Version number
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function deleteAction($scriptId, $versionNumber)
    {
        $this->checkPermissions(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_MANAGE);

        $version = $this->getVersion($scriptId, $versionNumber, true);

        $version->delete();

        return $this->result(null);
    }
}