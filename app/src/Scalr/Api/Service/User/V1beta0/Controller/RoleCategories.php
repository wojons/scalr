<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\Entity;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Exception\ModelException;
use Scalr\Api\Service\User\V1beta0\Adapter\RoleCategoryAdapter;
use Scalr\Model\Entity\RoleCategory;

/**
 * User/RoleCategories API Controller
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (18.03.2015)
 */
class RoleCategories extends ApiController
{

    /**
     * Gets default search criteria for Role Categories
     *
     * @return  array Returns array of the default search criteria
     */
    private function getDefaultCriteria()
    {
        return $this->getScopeCriteria();
    }

    /**
     * Gets RoleCategory from database using User's Environement
     *
     * @param int  $roleCategoryId         An identifier of the Role Category
     * @param bool $restrictToCurrentScope optional Whether it should additionally check that role category corresponds to current scope
     * @return RoleCategory
     * @throws ApiErrorException
     */
    private function getRoleCategory($roleCategoryId, $restrictToCurrentScope = false)
    {
        $criteria = $this->getDefaultCriteria();
        $criteria[] = ['id' => $roleCategoryId];

        /* @var RoleCategory $roleCategory */
        $roleCategory = Entity\RoleCategory::findOne($criteria);

        if (!$roleCategory) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                "The Role Category either does not exist or it is out of %s scope." , ucfirst($this->getScope())
            ));
        }

        //To be over-suspicious checking READ access to RoleCategory Entity
        $this->checkPermissions($roleCategory);

        if ($restrictToCurrentScope && $roleCategory->getScope() !== $this->getScope()) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, sprintf(
                'The Role Category is not either from the %s scope or owned by your %s.', $this->getScope(), $this->getScope()
            ));
        }
        return $roleCategory;
    }

    /**
     * Gets the list of the available Role Categories
     */
    public function describeAction()
    {
        $this->checkScopedPermissions('ROLES');

        return $this->adapter('roleCategory')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Create a new Role Category in this Environment or account
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function createAction()
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $object = $this->request->getJsonBody();

        /* @var $roleCategoryAdapter RoleCategoryAdapter */
        $roleCategoryAdapter = $this->adapter('roleCategory');

        //Pre validates the request object
        $roleCategoryAdapter->validateObject($object, Request::METHOD_POST);
        $object->scope = $this->getScope();

        /* @var $roleCategory Entity\RoleCategory */
        //Converts object into RoleCategory entity
        $roleCategory = $roleCategoryAdapter->toEntity($object);
        $roleCategoryAdapter->validateEntity($roleCategory);
        //Saves entity
        $roleCategory->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($roleCategoryAdapter->toData($roleCategory));
    }

    /**
     * Gets the specified Role Category
     *
     * @param    int    $roleCategoryId  The identifier of the Role Category
     * @return   ResultEnvelope
     * @throws   ApiNotImplementedErrorException
     */
    public function fetchAction($roleCategoryId)
    {
        $this->checkScopedPermissions('ROLES');

        $roleCategory = $this->getRoleCategory($roleCategoryId);

        return $this->result($this->adapter('roleCategory')->toData($roleCategory));
    }

    /**
     * Modifies Role Category attributes
     *
     * @param  int $roleCategoryId  The identifier of the Role Category
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function modifyAction($roleCategoryId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        $object = $this->request->getJsonBody();

        /* @var $roleCategoryAdapter RoleCategoryAdapter */
        $roleCategoryAdapter = $this->adapter('roleCategory');

        //Pre validates the request object
        $roleCategoryAdapter->validateObject($object, Request::METHOD_PATCH);
        /* @var Entity\RoleCategory  $roleCategory */
        $roleCategory = $this->getRoleCategory($roleCategoryId, true);

        //Copies all alterable properties to fetched Role Entity
        $roleCategoryAdapter->copyAlterableProperties($object, $roleCategory);

        //Re-validates an Entity
        $roleCategoryAdapter->validateEntity($roleCategory);

        //Saves verified results
        $roleCategory->save();

        return $this->result($roleCategoryAdapter->toData($roleCategory));
    }

    /**
     * Deletes the Role Category from the environment or account
     *
     * @param  int $roleCategoryId  The identifier of the Role Category
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function deleteAction($roleCategoryId)
    {
        $this->checkScopedPermissions('ROLES', 'MANAGE');

        /* @var Entity\RoleCategory  $roleCategory */
        $roleCategory = $this->getRoleCategory($roleCategoryId, true);

        if ($roleCategory->getUsed()) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, 'Role Category is in use and can not be removed.');
        }

        $roleCategory->delete();

        return $this->result(null);
    }
}