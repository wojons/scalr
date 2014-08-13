<?php
namespace Scalr\Service\Aws\Iam\DataType;

use DateTime;
use Scalr\Service\Aws\Iam\AbstractIamDataType;
use Scalr\Service\Aws\IamException;

/**
 * InstanceProfileData
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     4.6 (17.12.2013)
 *
 * @method   \Scalr\Service\Aws\Iam\DataType\RoleList getRoles()
 *           getRoles()
 *           Gets the list of the roles which are assiciated with the instance profile.
 *
 * @method   \Scalr\Service\Aws\Iam\DataType\InstanceProfileData setRoles()
 *           setRoles(\Scalr\Service\Aws\Iam\DataType\RoleList $roles = null)
 *           Sets the list of the roles which are assiciated with the instance profile.
 */
class InstanceProfileData extends AbstractIamDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array();

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array(
        'roles',
    );

    /**
     * The stable and unique string identifying the instance profile
     *
     * Length constraints: Minimum length of 16. Maximum length of 32.
     *
     * @var string
     */
    public $instanceProfileId;

    /**
     * The name identifying the instance profile.
     *
     * Length constraints: Minimum length of 1. Maximum length of 128.
     *
     * @var string
     */
    public $instanceProfileName;

    /**
     * The Amazon Resource Name (ARN) specifying the instance profile.
     *
     * Length constraints: Minimum length of 20. Maximum length of 2048.
     *
     * @var string
     */
    public $arn;

    /**
     * The date when the instance profile was created
     *
     * @var DateTime
     */
    public $createDate;

    /**
     * Path to the instance profile
     *
     * Length constraints: Minimum length of 1. Maximum length of 512.
     *
     * @var string
     */
    public $path;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Iam.AbstractIamDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();
        if ($this->instanceProfileName === null) {
            throw new IamException(sprintf(
                'instanceProfileName has not been initialized for the object %s yet.', get_class($this)
            ));
        }
        if ($this->instanceProfileId === null) {
            throw new IamException(sprintf(
                'instanceProfileId has not been initialized for the object %s yet.', get_class($this)
            ));
        }
    }

    /**
     * Adds the specified role to this instance profile
     *
     * @param   string   $roleName The name of the role to add
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function addRole($roleName)
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getIam()->instanceProfile->addRole($this->instanceProfileName, $roleName);
    }

    /**
     * Removes the specified role from this instance profile
     *
     * @param   string   $roleName The name of the role to remove
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function removeRole($roleName)
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getIam()->instanceProfile->removeRole($this->instanceProfileName, $roleName);
    }

    /**
     * Deletes an instance profile
     *
     * Important!
     * Make sure you do not have any Amazon EC2 instances running with the instance profile you are about to delete.
     * Deleting a role or instance profile that is associated with a running instance will break any applications
     * running on the instance.
     *
     * @return  boolean Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function delete()
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getIam()->instanceProfile->delete($this->instanceProfileName);
    }
}