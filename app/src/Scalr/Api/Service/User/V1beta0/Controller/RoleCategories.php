<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Model\Entity;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Acl\Acl;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;

/**
 * User/Version-1/RoleCategories API Controller
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
        return [['$or' => [['envId' => null], ['envId' => $this->getEnvironment()->id]]]];
    }

    /**
     * Gets RoleCategory from database using User's Environement
     *
     * @param    int     $roleCategoryId  An identifier of the Role Category
     * @return   \Scalr\Model\Entity\RoleCategory Returns RoleCategory entity on success or NULL otherwise
     * @throws   ApiErrorException
     */
    private function getRoleCategory($roleCategoryId)
    {
        $criteria = $this->getDefaultCriteria();
        $criteria[] = ['id' => $roleCategoryId];

        $roleCategory = Entity\RoleCategory::findOne($criteria);

        if (!$roleCategory) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "The Role Category either does not exist or isn't in scope for the current Environment");
        }

        //To be over-suspicious checking READ access to RoleCategory Entity
        $this->checkPermissions($roleCategory);

        return $roleCategory;
    }

    /**
     * Gets the list of the available Role Categories
     */
    public function describeAction()
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT);

        return $this->adapter('roleCategory')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Gets the specified Role Category
     *
     * @param    int    $roleCategoryId  The identifier of the Role Category
     * @throws   ApiNotImplementedErrorException
     */
    public function fetchAction($roleCategoryId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT);

        $roleCategory = $this->getRoleCategory($roleCategoryId);

        return $this->result($this->adapter('roleCategory')->toData($roleCategory));
    }
}