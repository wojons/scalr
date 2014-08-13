<?php
namespace Scalr\Service\Aws\Iam\Handler;

use Scalr\Service\Aws\IamException;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Iam\AbstractIamHandler;
use Scalr\Service\Aws\Iam\DataType\RoleData;
use Scalr\Service\Aws\Iam\DataType\RoleList;

/**
 * RoleHandler
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     13.12.2013
 */
class RoleHandler extends AbstractIamHandler
{
    /**
     * Creates a new role for your AWS account.
     *
     * The policy grants permission to an EC2 instance to assume the role.
     * Currently, only EC2 instances can assume roles.
     *
     * @param   string     $roleName                 Name of the role to create. (1 - 64 characters)
     * @param   string     $assumeRolePolicyDocument The policy that grants an entity permission to assume the role.
     *                                               Length constraints: Minimum length of 1. Maximum length of 131072.
     * @param   string     $path                     optional
     * @return  RoleData   Returns RoleData on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function create($roleName, $assumeRolePolicyDocument, $path = null)
    {
        return $this->getIam()->getApiHandler()->createRole($roleName, $assumeRolePolicyDocument, $path);
    }

    /**
     * Deletes the specified role.
     *
     * The role must not have any policies attached.
     *
     * @param   string     $roleName Name of the role to remove. (1 - 64 characters)
     * @return  boolean    Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function delete($roleName)
    {
        return $this->getIam()->getApiHandler()->deleteRole($roleName);
    }

    /**
     * Lists the roles that have the specified path prefix
     *
     * @param   string   $pathPrefix optional The path prefix for filtering the results
     * @param   string   $marker     optional Set this parameter to the value of the Marker element in the response you just received.
     * @param   string   $maxItems   optional Maximum number of the records you want in the response
     * @return  RoleList Returns RoleList object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function describe($pathPrefix = null, $marker = null, $maxItems = null)
    {
        return $this->getIam()->getApiHandler()->listRoles($pathPrefix, $marker, $maxItems);
    }

    /**
     * Updates the policy that grants an entity permission to assume a role
     *
     * @param   string   $roleName       The name of the role to update
     * @param   string   $policyDocument The policy that grants an entity permission to assume the role.
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function updateAssumePolicy($roleName, $policyDocument)
    {
        return $this->getIam()->getApiHandler()->updateAssumeRolePolicy($roleName, $policyDocument);
    }
}