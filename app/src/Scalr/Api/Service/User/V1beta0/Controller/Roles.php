<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Api\Rest\ApiApplication;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\RoleGlobalVariableAdapter;
use Scalr\Model\Entity;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\DataType\ScopeInterface;
use Scalr\Api\Service\User\V1beta0\Adapter\RoleAdapter;
use Scalr\Api\Rest\Http\Request;
use Scalr\Acl\Acl;
use Scalr\Exception\ValidationErrorException;
use Scalr\Exception\Model\Entity\Os\OsMismatchException;
use Scalr\Exception\Model\Entity\Image\ImageNotFoundException;
use Scalr\Exception\Model\Entity\Image\NotAcceptableImageStatusException;
use Scalr\Exception\Model\Entity\Image\ImageInUseException;

/**
 * User/Version-1/Roles API Controller
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (04.03.2015)
 */
class Roles extends ApiController
{
    /**
     * Gets Default search criteria for the Environment scope
     *
     * @return  array  Returns array of the default search criteria for the Environment scope
     */
    private function getDefaultCriteria()
    {
        return $this->getScopeCriteria();
    }

    /**
     * Retrieves the list of the roles that are available on the Environment
     */
    public function describeAction()
    {
        $this->checkScopedPermissions('ROLES');

        $r = new Entity\Role();
        $re = new Entity\RoleEnvironment();
        $ri = new Entity\RoleImage();
        $criteria = [];

        $criteria[Entity\Role::STMT_DISTINCT] = true;
        $criteria[Entity\Role::STMT_FROM] = $r->table() . " LEFT JOIN " . $re->table() . " ON {$r->columnId} = {$re->columnRoleId}
             LEFT JOIN " . $ri->table() . " ON {$r->columnId} = {$ri->columnRoleId}";

        switch ($this->getScope()) {
            case ScopeInterface::SCOPE_ENVIRONMENT:
                $criteria[Entity\Role::STMT_WHERE] = "({$r->columnAccountId} IS NULL AND {$ri->columnRoleId} IS NOT NULL
                     OR {$r->columnAccountId} = '" . $this->getUser()->accountId . "' AND {$r->columnEnvId} IS NULL
                         AND ({$re->columnEnvId} IS NULL OR {$re->columnEnvId} = '" . $this->getEnvironment()->id . "')
                     OR {$r->columnEnvId} = '" . $this->getEnvironment()->id . "'
                    ) AND {$r->columnGeneration} = 2";
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                $criteria[Entity\Role::STMT_WHERE] = "({$r->columnAccountId} IS NULL AND {$ri->columnRoleId} IS NOT NULL OR " .
                    "{$r->columnAccountId} = '" . $this->getUser()->accountId . "' AND {$r->columnEnvId} IS NULL) AND {$r->columnGeneration} = 2";
                break;

            case ScopeInterface::SCOPE_SCALR:
                $criteria = [['envId' => null], ['accountId' => null]];
                break;
        }

        return $this->adapter('role')->getDescribeResult($criteria);
    }

    /**
     * Retrieves the list of the Images associated with this Role
     *
     * @param  int $roleId The identifier of the role
     * @return array
     * @throws ApiErrorException
     */
    public function describeImagesAction($roleId)
    {
        $this->checkScopedPermissions('IMAGES');

        //Finds out the Role object
        $role = $this->getRole($roleId);

        $criteria = [];
        $requestQuery = $this->params();

        if (isset($requestQuery['image'])) {
            $criteria[] = ['hash' => static::getBareId($requestQuery, 'image')];
        }

        $requestQuery = array_diff_key($requestQuery, array_flip(['image', 'role', ApiController::QUERY_PARAM_MAX_RESULTS, ApiController::QUERY_PARAM_PAGE_NUM]));

        if (!empty($requestQuery)) {
            throw new ApiErrorException(
                400,
                ErrorMessage::ERR_INVALID_STRUCTURE,
                sprintf("Unsupported filter(s) [%s]. Fields which are available for filtering: [role, image]", implode(', ', array_keys($requestQuery)))
            );
        }

        $images = $role->getImages($criteria, null, null, $this->getMaxResults(), $this->getPageOffset(), true);

        $roleImages = [];

        foreach ($images as $image) {
            /* @var $image Entity\Image */
            $roleImages[] = [
                'image' => ['id' => $image->hash],
                'role'  => ['id' => $roleId]
            ];
        }

        return $this->resultList($roleImages, $images->totalNumber);
    }

    /**
     * Gets role from database using User's Environment
     *
     * @param    int     $roleId                             The identifier of the Role
     * @param    bool    $restrictToCurrentScope    optional Whether it should additionally check that role corresponds to current scope
     *
     * @throws   ApiErrorException
     * @return   \Scalr\Model\Entity\Role|null Returns Role entity on success or NULL otherwise
     */
    public function getRole($roleId, $restrictToCurrentScope = false)
    {
        $criteria = $this->getDefaultCriteria();
        $criteria[] = ['id' => $roleId];

        $role = Entity\Role::findOne($criteria);
        /* @var $role Entity\Role */

        if (!$role) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "The Role either does not exist or isn't in scope for the current Environment.");
        }

        //To be over-suspicious check READ access to Role object
        $this->checkPermissions($role);

        if ($restrictToCurrentScope && $role->getScope() !== $this->getScope()) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION,
                "The Role is not either from the {$this->getScope()} scope or owned by your {$this->getScope()}."
            );
        }

        return $role;
    }

    /**
     * Gets the Image that is associated with the specified Role using User's Environment
     *
     * @param    int     $roleId    The identifier of the Role
     * @param    string  $imageId   The identifier of the Image which is expected to be associated with the Role
     * @throws   ApiErrorException
     * @throws   \UnexpectedValueException
     * @return   \Scalr\Model\Entity\Image  Returns the Image which corresponds the specified Role
     */
    public function getImage($roleId, $imageId)
    {
        $role = $this->getRole($roleId);

        $list = $role->getImages([['hash' => $imageId]]);

        if (!count($list)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "The Image either does not exist or isn't in scope for the current Environment.");
        }

        /* @var $image Entity\Image */
        $image = $list->current();

        if (!$image instanceof Entity\Image) {
            throw new \UnexpectedValueException("Unexpected result from the query");
        }

        $this->checkPermissions($image);

        return $image;
    }

    /**
     * Retrieves a specified role
     *
     * @param  int $roleId The identifier of the role
     * @return \Scalr\Api\DataType\ResultEnvelope
     */
    public function fetchAction($roleId)
    {
        $this->checkScopedPermissions('ROLES');

        return $this->result($this->adapter('role')->toData($this->getRole($roleId)));
    }

    /**
     * Fetches specified Image. It actually checks relations and redirects to Image
     *
     * @param     int    $roleId    The identifier of the Role
     * @param     string $imageId   The identifier of the Image (uuid)
     * @return    mixed
     */
    public function fetchImageAction($roleId, $imageId)
    {
        $this->checkScopedPermissions('IMAGES');

        $this->getImage($roleId, $imageId);

        return $this->app->invokeRoute('User_Images:fetch', $imageId);
    }

    /**
     * Disassociates the specified Image.
     *
     * It actually checks relations before deletion.
     *
     * @param     int $roleId The identifier of the Role
     * @param     string $imageId The identifier of the Image (uuid)
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws    ApiNotImplementedErrorException
     */
    public function deregisterImageAction($roleId, $imageId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $image = $this->getImage($roleId, $imageId);

        $roleImage = Entity\RoleImage::findOne([
            ['platform'      => $image->platform],
            ['cloudLocation' => $image->cloudLocation],
            ['imageId'       => $image->id],
            ['roleId'        => $roleId]
        ]);

        if ($roleImage) {
            $role = $this->getRole($roleId);
            $this->setImage($role, $image->platform, $image->cloudLocation, null, $this->getUser()->id, $this->getUser()->email);
        }

        return $this->result(null);
    }

    /**
     * Add, replace or remove image in role
     *
     * It wraps into separate call to avoid code duplicates on catching Exceptions
     *
     * @param   Entity\Role $role           The role object
     * @param   string      $platform       The cloud platform
     * @param   string      $cloudLocation  The cloud location
     * @param   string      $imageId        optional Either Identifier of the Image to add or NULL to remove
     * @param   integer     $userId         The identifier of the User who adds the Image
     * @param   string      $userEmail      The email address of the User who adds the Image
     * @throws  \Exception
     */
    protected function setImage()
    {
        $args = func_get_args();

        /* @var $role Entity\Role */
        $role = array_shift($args);

        try {
            call_user_func_array([$role, 'setImage'], $args);
        } catch (OsMismatchException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OS_MISMATCH, $e->getMessage());
        } catch (ImageNotFoundException $e) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, $e->getMessage());
        } catch (NotAcceptableImageStatusException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNACCEPTABLE_IMAGE_STATUS, $e->getMessage());
        } catch (ImageInUseException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, $e->getMessage());
        }
    }

    /**
     * Creates a new Role in this Environment
     */
    public function createAction()
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);

        $object = $this->request->getJsonBody();

        $roleAdapter = $this->adapter('role');

        //Pre validates the request object
        $roleAdapter->validateObject($object, Request::METHOD_POST);

        //Read only property. It is needed before toEntity() call to set envId and accountId properties properly
        $object->scope = $this->getScope();

        /* @var $role Entity\Role */
        //Converts object into Role entity
        $role = $roleAdapter->toEntity($object);

        $role->id = null;

        $roleAdapter->validateEntity($role);

        //Saves entity
        $role->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($roleAdapter->toData($role));
    }

    /**
     * Modifies role attributes
     *
     * @param  int $roleId The identifier of the role
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function modifyAction($roleId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $object = $this->request->getJsonBody();

        /* @var $roleAdapter RoleAdapter */
        $roleAdapter = $this->adapter('role');

        //Pre validates the request object
        $roleAdapter->validateObject($object, Request::METHOD_PATCH);

        $role = $this->getRole($roleId, true);

        //Copies all alterable properties to fetched Role Entity
        $roleAdapter->copyAlterableProperties($object, $role);

        //Re-validates an Entity
        $roleAdapter->validateEntity($role);

        //Saves verified results
        $role->save();

        return $this->result($roleAdapter->toData($role));
    }

    /**
     * Deletes the role from the environment
     *
     * @param  int $roleId The identifier of the role
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     * @throws \Scalr\Exception\ModelException
     */
    public function deleteAction($roleId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $role = $this->getRole($roleId, true);

        if ($role->isUsed()) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE,
                "It's strictly forbidden to remove a Role currently in use as the Farms become broken."
            );
        }

        $role->delete();

        return $this->result(null);
    }

    /**
     * Associates a new Image with the Role
     *
     * @param  int      $roleId     The Identifier of the role
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function registerImageAction($roleId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        //Gets role checking Environment scope
        $role = $this->getRole($roleId, true);

        $object = $this->request->getJsonBody();

        $objectImageId = static::getBareId($object, 'image');
        $objectRoleId = static::getBareId($object, 'role');

        if (empty($objectImageId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Invalid body");
        }

        if (!preg_match('/' . ApiApplication::REGEXP_UUID . '/', $objectImageId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid image identifier");
        }

        if (!empty($objectRoleId) && $roleId != $objectRoleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the role");
        }

        if (is_object($object->image)) {
            $imageAdapter = $this->adapter('image');
            //Pre validates the request object
            $imageAdapter->validateObject($object->image);
        }

        $criteria = $this->getScopeCriteria();
        $criteria[] = ['hash' => $objectImageId];
        /* @var $image Entity\Image */
        $image = Entity\Image::findOne($criteria);

        if (empty($image)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_INVALID_VALUE, "The Image either does not exist or isn't in scope for the current Environment.");
        }

        $roleImage = Entity\RoleImage::findOne([
            ['roleId'        => $roleId],
            ['platform'      => $image->platform],
            ['cloudLocation' => $image->cloudLocation]
        ]);

        if (!empty($roleImage)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, sprintf("Image with cloud location %s has already been registered", $image->cloudLocation));
        }

        $this->setImage($role, $image->platform, $image->cloudLocation, $image->id, $this->getUser()->id, $this->getUser()->email);

        $this->response->setStatus(201);

        return $this->result([
            'image' => ['id' => $image->hash],
            'role'  => ['id' => $role->id]
        ]);
    }

    /**
     * Associates a new Image with the Role
     *
     * @param  int      $roleId     The Identifier of the role
     * @param  string   $imageId    The Identifier of the image
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function replaceImageAction($roleId, $imageId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $role = $this->getRole($roleId, true);

        $oldImage = $this->getImage($roleId, $imageId);

        $object = $this->request->getJsonBody();

        $objectImageId = static::getBareId($object, 'image');
        $objectRoleId = static::getBareId($object, 'role');

        if (empty($objectImageId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Invalid body");
        }

        if (!preg_match('/' . ApiApplication::REGEXP_UUID . '/', $objectImageId) || $oldImage->hash == $objectImageId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid image identifier");
        }

        if (!empty($objectRoleId) && $roleId != $objectRoleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the role");
        }

        if (is_object($object->image)) {
            $imageAdapter = $this->adapter('image');
            //Pre validates the request object
            $imageAdapter->validateObject($object->image);
        }

        $criteria = $this->getScopeCriteria();
        $criteria[] = ['hash' => $objectImageId];
        /* @var $image Entity\Image */
        $image = Entity\Image::findOne($criteria);

        if (empty($image)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_INVALID_VALUE, "The Image either does not exist or isn't in scope for the current Environment.");
        }

        if ($image->cloudLocation !== $oldImage->cloudLocation) {
            throw new ApiErrorException(400, ErrorMessage::ERR_BAD_REQUEST, "You can only replace images with equal cloud locations");
        }

        $this->setImage($role, $image->platform, $image->cloudLocation, $image->id, $this->getUser()->id, $this->getUser()->email);

        $this->response->setStatus(200);

        return $this->result([
            'image' => ['id' => $image->hash],
            'role'  => ['id' => $role->id]
        ]);
    }

    /**
     * Gets the list of the available Global Variables of the role
     *
     * @param int $roleId
     * @return array
     */
    public function describeVariablesAction($roleId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $this->getRole($roleId, true);

        $globalVar = $this->getVariableInstance();

        $list = $globalVar->getValues($roleId);
        $foundRows = count($list);

        $adapter = $this->adapter('roleGlobalVariable');

        $data = [];

        $list = array_slice($list, $this->getPageOffset(), $this->getMaxResults());

        foreach ($list as $var) {
            $item = $adapter->convertData($var);
            $data[] = $item;
        }

        return $this->resultList($data, $foundRows);
    }

    /**
     * Gets specific global var of the role
     *
     * @param int $roleId
     * @param string $name
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function fetchVariableAction($roleId, $name)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $this->getRole($roleId, true);

        $globalVar = $this->getVariableInstance();

        $fetch = $this->getGlobalVariable($roleId, $name, $globalVar);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        return $this->result($this->adapter('roleGlobalVariable')->convertData($fetch));
    }

    /**
     * Creates role's global var
     *
     * @param int $roleId
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function createVariableAction($roleId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $this->getRole($roleId, true);

        $object = $this->request->getJsonBody();

        /* @var  $adapter RoleGlobalVariableAdapter */
        $adapter = $this->adapter('roleGlobalVariable');

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_POST);

        $globalVar = $this->getVariableInstance();

        $variable = [
            'name'      => $object->name,
            'default'   => '',
            'locked'    => '',
            'current'   => [
                'name'          => $object->name,
                'value'         => !empty($object->value) ? $object->value : '',
                'category'      => !empty($object->category) ? strtolower($object->category) : '',
                'flagFinal'     => !empty($object->locked) ? 1 : 0,
                'flagRequired'  => !empty($object->requiredIn) ? $object->requiredIn : '',
                'flagHidden'    => !empty($object->hidden) ? 1 : 0,
                'format'        => !empty($object->outputFormat) ? $object->outputFormat : '',
                'validator'     => !empty($object->validationPattern) ? $object->validationPattern : '',
                'description'   => !empty($object->description) ? $object->description : '',
                'scope'         => ScopeInterface::SCOPE_ROLE,
            ],
            'flagDelete' => '',
            'scopes'     => [ScopeInterface::SCOPE_ROLE]
        ];

        $checkVar = $this->getGlobalVariable($roleId, $object->name, $globalVar);

        if (!empty($checkVar)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf('Variable with name %s already exists', $object->name));
        }

        try {
            $globalVar->setValues([$variable], $roleId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }

        $data = $this->getGlobalVariable($roleId, $variable['name'], $globalVar);

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($adapter->convertData($data));
    }

    /**
     * Modifies role's global variable
     *
     * @param int       $roleId
     * @param string    $name
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function modifyVariableAction($roleId, $name)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $this->getRole($roleId, true);

        $object = $this->request->getJsonBody();

        /* @var  $adapter RoleGlobalVariableAdapter */
        $adapter = $this->adapter('roleGlobalVariable');

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_POST);

        $globalVar = $this->getVariableInstance();

        $entity = new Entity\RoleGlobalVariable();

        $adapter->copyAlterableProperties($object, $entity);

        $variable = $this->getGlobalVariable($roleId, $name, $globalVar);

        if (empty($variable)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        if (!empty($variable['locked']) && (!isset($object->value) || count(get_object_vars($object)) > 1)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, sprintf("This variable was declared in the %s Scope, you can only modify its 'value' field in the Role Scope", ucfirst($variable['locked']['scope'])));
        }

        $variable['flagDelete'] = '';

        if (!empty($variable['locked'])) {
            $variable['current']['name'] = $name;
            $variable['current']['value'] = $object->value;
            $variable['current']['scope'] = ScopeInterface::SCOPE_ROLE;
        } else {
            $variable['current'] = [
                'name'          => $name,
                'value'         => !empty($object->value) ? $object->value : '',
                'category'      => !empty($object->category) ? strtolower($object->category) : '',
                'flagFinal'     => !empty($object->locked) ? 1 : $variable['current']['flagFinal'],
                'flagRequired'  => !empty($object->requiredIn) ? $object->requiredIn : $variable['current']['flagRequired'],
                'flagHidden'    => !empty($object->hidden) ? 1 : $variable['current']['flagHidden'],
                'format'        => !empty($object->outputFormat) ? $object->outputFormat : $variable['current']['format'],
                'validator'     => !empty($object->validationPattern) ? $object->validationPattern : $variable['current']['validator'],
                'description'   => !empty($object->description) ? $object->description : '',
                'scope'         => ScopeInterface::SCOPE_ROLE,
            ];
        }

        try {
            $globalVar->setValues([$variable], $roleId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }

        $data = $this->getGlobalVariable($roleId, $name, $globalVar);

        return $this->result($adapter->convertData($data));
    }

    /**
     * Deletes role's global variable
     *
     * @param int $roleId
     * @param string $name
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     * @throws \Scalr\Exception\ModelException
     */
    public function deleteVariableAction($roleId, $name)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $this->getRole($roleId, true);

        $fetch = $this->getGlobalVariable($roleId, $name, $this->getVariableInstance());

        $roleVariable = Entity\RoleGlobalVariable::findPk($roleId, $name);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        } else if (empty($roleVariable)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, "You can only delete Global Variables declared in Role scope.");
        }

        $roleVariable->delete();

        return $this->result(null);
    }

    /**
     * Gets a specific global variable data
     *
     * @param int                              $roleId
     * @param string                           $name
     * @param \Scalr_Scripting_GlobalVariables $globalVar
     * @return mixed
     * @throws ApiErrorException
     */
    private function getGlobalVariable($roleId, $name, \Scalr_Scripting_GlobalVariables $globalVar)
    {
        $list = $globalVar->getValues($roleId);
        $fetch = [];

        foreach ($list as $var) {
            if ((!empty($var['current']['name']) && $var['current']['name'] == $name)
                || (!empty($var['default']['name']) && $var['default']['name'] == $name)) {

                $fetch = $var;
                break;
            }
        }

        return $fetch;
    }

    /**
     * Gets global variable object
     *
     * @return \Scalr_Scripting_GlobalVariables
     */
    private function getVariableInstance()
    {
        return new \Scalr_Scripting_GlobalVariables(
            $this->getUser()->getAccountId(),
            $this->getEnvironment() ? $this->getEnvironment()->id : 0,
            ScopeInterface::SCOPE_ROLE
        );
    }
}
