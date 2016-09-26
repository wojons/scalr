<?php

namespace Scalr\Model\Entity;

use InvalidArgumentException;
use Scalr\DataType\ScopeInterface;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Model\Entity\Account\User;
use Scalr\Util\CryptoTool;

/**
 * RoleScript entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="role_scripts")
 */
class RoleScript extends OrchestrationRule
{

    /**
     * Role Id
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $roleId;

    /**
     * Rule hash
     * Calculated as CryptoTool::sault(12) => substr(md5(uniqid(rand(), true)), 0, 12)
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $hash;

    public function save()
    {
        $this->hash = CryptoTool::sault(12);

        parent::save();
    }

    /**
     * Check whether the user has access permissions to the specified object.
     *
     * It should check only Entity level access permissions, NOT ACL
     *
     * @param   AbstractEntity  $entity                  Object that defines permissions
     * @param   User            $user                    The User Entity
     * @param   Environment     $environment    optional The Environment Entity if request is from Environment scope
     * @param   bool            $modify         optional Whether it should check MODIFY permission. By default it checks READ permission.
     *
     * @return  bool    Returns TRUE if the user has access to the specified object
     *
     * @see AccessPermissionsInterface::hasAccessPermissions()
     */
    public function checkInheritedPermissions(AbstractEntity $entity, User $user, Environment $environment = null, $modify = null)
    {
        if (!$entity instanceof ScopeInterface) {
            throw new InvalidArgumentException("Entity must implements ScopeInterface!");
        }

        switch ($entity->getScope()) {
            case static::SCOPE_ACCOUNT:
                return $entity->accountId == $user->accountId && (empty($environment) || !$modify);

            case static::SCOPE_ENVIRONMENT:
                return $environment
                     ? $entity->envId == $environment->id
                     : $user->hasAccessToEnvironment($entity->envId);

            case static::SCOPE_SCALR:
                return !$modify;

            default:
                return false;
        }
    }

    /**
     * {@inheritdoc}
     * @see AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        return $this->checkInheritedPermissions(Role::findPk($this->roleId), $user, $environment, $modify) &&
               (empty($this->scriptId) ||
               $this->checkInheritedPermissions(Script::findPk($this->scriptId), $user, $environment));
    }

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        /* @var $role Role */
        $role = Role::findPk($this->roleId);

        return $role->getScope();
    }
}