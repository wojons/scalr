<?php
namespace Scalr\Service\Aws\Iam\DataType;

use DateTime;
use Scalr\Service\Aws\Iam\AbstractIamDataType;
use Scalr\Service\Aws\IamException;

/**
 * RoleData
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     4.5.1 (13.12.2013)
 */
class RoleData extends AbstractIamDataType
{

    /**
     * List of external identifier names.
     *
     * @var array
     */
    protected $_externalKeys = array();

    /**
     * The stable and unique string identifying the role
     *
     * Length constraints: Minimum length of 16. Maximum length of 32.
     *
     * @var string
     */
    public $roleId;

    /**
     * The name identifying the role.
     *
     * Length constraints: Minimum length of 1. Maximum length of 64.
     *
     * @var string
     */
    public $roleName;

    /**
     * The Amazon Resource Name (ARN) specifying the user.
     *
     * Length constraints: Minimum length of 20. Maximum length of 2048.
     *
     * @var string
     */
    public $arn;

    /**
     * The policy that grants an entity permission to assume the role.
     *
     * Length constraints: Minimum length of 1. Maximum length of 131072.
     *
     * @var string
     */
    public $assumeRolePolicyDocument;

    /**
     * The date when the role was created
     *
     * @var DateTime
     */
    public $createDate;

    /**
     * Path to the role
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
        if ($this->roleName === null) {
            throw new IamException(sprintf(
                'roleName has not been initialized for the object %s yet.', get_class($this)
            ));
        }
        if ($this->roleId === null) {
            throw new IamException(sprintf(
                'roleId has not been initialized for the object %s yet.', get_class($this)
            ));
        }
    }

    /**
     * Deletes role.
     *
     * The role must not have any policies attached.
     *
     * @return  boolean    Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function delete()
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getIam()->role->delete($this->roleName);
    }

    /**
     * Updates the policy that grants an entity permission to assume a role
     *
     * @param   string   $policyDocument The policy that grants an entity permission to assume the role.
     * @return  boolean  Returns true on success or throws an exception
     * @throws  IamException
     * @throws  ClientException
     */
    public function updateAssumePolicy($policyDocument)
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getIam()->role->updateAssumePolicy($this->roleName, $policyDocument);
    }
}