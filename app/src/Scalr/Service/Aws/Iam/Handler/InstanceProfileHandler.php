<?php
namespace Scalr\Service\Aws\Iam\Handler;

use Scalr\Service\Aws\IamException;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Iam\AbstractIamHandler;
use Scalr\Service\Aws\Iam\DataType\InstanceProfileData;

/**
 * InstanceProfileHandler
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     4.6 (17.12.2013)
 */
class InstanceProfileHandler extends AbstractIamHandler
{
    /**
     * Lists the instance profiles that have the specified path prefix
     *
     * @param   string   $pathPrefix optional The path prefix for filtering the results
     * @param   string   $marker     optional Set this parameter to the value of the Marker element in the response you just received.
     * @param   string   $maxItems   optional Maximum number of the records you want in the response
     * @return  InstanceProfileList  Returns InstanceProfileList object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function describe($pathPrefix = null, $marker = null, $maxItems = null)
    {
        return $this->getIam()->getApiHandler()->listInstanceProfiles($pathPrefix, $marker, $maxItems);
    }

    /**
     * Retrieves information about the specified instance profile,
     * including the instance profile's path, GUID, ARN, and role
     *
     * @param   string   $instanceProfileName Name of the instance profile to get information about.
     * @return  InstanceProfileData Returns InstanceProfileData object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function fetch($instanceProfileName)
    {
        return $this->getIam()->getApiHandler()->getInstanceProfile($instanceProfileName);
    }

    /**
     * Adds the specified role to the specified instance profile
     *
     * @param   string   $instanceProfileName The name of the instance profile to update
     * @param   string   $roleName            The name of the role to add
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function addRole($instanceProfileName, $roleName)
    {
        return $this->getIam()->getApiHandler()->addRoleToInstanceProfile($instanceProfileName, $roleName);
    }

    /**
     * Removes the specified role from the specified instance profile
     *
     * @param   string   $instanceProfileName The name of the instance profile to update
     * @param   string   $roleName            The name of the role to remove
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function removeRole($instanceProfileName, $roleName)
    {
        return $this->getIam()->getApiHandler()->removeRoleFromInstanceProfile($instanceProfileName, $roleName);
    }

    /**
     * Lists the instance profiles that have the specified associated role
     *
     * @param   string   $roleName   The name of the role to list instance profiles for. (1-64 characters)
     * @param   string   $marker     optional Set this parameter to the value of the Marker element in the response you just received.
     * @param   string   $maxItems   optional Maximum number of the records you want in the response
     * @return  InstanceProfileList  Returns InstanceProfileList object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function listForRole($roleName, $marker = null, $maxItems = null)
    {
        return $this->getIam()->getApiHandler()->listInstanceProfilesForRole($roleName, $marker, $maxItems);
    }

    /**
     * Creates a new instance profile
     *
     * @param   string   $instanceProfileName Name of the instance profile.
     * @param   stirng   $path                optional The path to the instance profile.
     * @return  InstanceProfileData Returns InstanceProfileData object on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function create($instanceProfileName, $path = null)
    {
        return $this->getIam()->getApiHandler()->createInstanceProfile($instanceProfileName, $path);
    }

    /**
     * Deletes an instance profile
     *
     * Important!
     * Make sure you do not have any Amazon EC2 instances running with the instance profile you are about to delete.
     * Deleting a role or instance profile that is associated with a running instance will break any applications
     * running on the instance.
     *
     * @param   string   $instanceProfileName Name of the instance profile.
     * @return  boolean Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function delete($instanceProfileName)
    {
        return $this->getIam()->getApiHandler()->deleteInstanceProfile($instanceProfileName);
    }
}