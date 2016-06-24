<?php
namespace Scalr\Tests\Fixtures\Api\V2\Acl;

use Scalr\Acl\Acl;
use Scalr\Acl\Exception\AclException;
use Scalr\Acl\Role\AccountRoleSuperposition;
use Scalr\Model\Entity\Account\User;
use ADODB_Exception;

/**
 * Class ApiTestAcl
 * Create stub method getUserRolesByEnvironment and getUserRoles for test
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (08.01.2016)
 */
class ApiTestAcl extends Acl
{
    /**
     * Auto-generated ID for account role usage
     *
     * @var string
     */
    protected $accountRoleId;

    /**
     * Acl type for test
     *
     * @var string
     */
    public $aclType;

    /**
     * ApiTestAcl constructor. Generate base accountRoleId
     */
    public function __construct()
    {
        $this->accountRoleId = static::generateAccountRoleId();
    }

    /**
     * Create test account role
     *
     * @param int    $accountId  The identifier of the client's account
     * @param string $name       Role name
     * @param int    $roleId     Default Acl role identifier
     */
    public function createTestAccountRole($accountId, $name, $roleId)
    {
        $this->deleteTestAccountRole($accountId, $name);
        $this->getDb()->Execute("
            INSERT `acl_account_roles` (account_role_id, account_id, role_id, name, color, is_automatic)
            SELECT  ?, ?, role_id, ?, 0, 1
            FROM    `acl_roles`
            WHERE   `role_id` = ?
        ", [
            $this->accountRoleId,
            $accountId,
            $name,
            $roleId
        ]);
    }

    /**
     * Delete test Account Role
     *
     * @param int    $accountId The identifier of the client's account
     * @param string $name      Role name
     * @throws ADODB_Exception
     * @throws AclException
     */
    public function deleteTestAccountRole($accountId, $name)
    {
        $rec = $this->getDb()->GetRow("
            SELECT `account_role_id`
            FROM   `acl_account_roles`
            WHERE  `account_id` = ? AND name = ?
        ", [
            $accountId,
            $name
        ]);

        if (isset($rec['account_role_id'])) {
            $this->deleteAccountRole($rec['account_role_id'], $accountId);
        }
    }

    /**
     * Gets account roles by default base role (full or readOnly access)
     *
     * @param int|User $user      User identifier or user object
     * @param int      $envId     Environment identifier
     * @param int      $accountId Identifier of the client's account
     * @return AccountRoleSuperposition
     */
    public function getUserRolesByEnvironment($user, $envId, $accountId)
    {
        return $this->getUserRoles($user);
    }

    /**
     * Gets user roles by default base role (full or readOnly access)
     *
     * @param int|User $user User identifier or user object
     * @return AccountRoleSuperposition
     */
    public function getUserRoles($user)
    {
        $ret = new AccountRoleSuperposition([]);
        $ret->setUser($user);
        $role = $this->getAccountRole($this->accountRoleId);
        $ret[$role->getRoleId()] = $role;
        return $ret;
    }
}