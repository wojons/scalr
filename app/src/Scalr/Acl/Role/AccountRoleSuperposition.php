<?php

namespace Scalr\Acl\Role;

use Scalr\Acl\Resource;
use Scalr\Acl\Acl;
use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Resource\ModeInterface;

/**
 * AccountRoleSuperposition class
 *
 * Allows to calculate access permissions using mix of ACLs
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    01.08.2013
 */
class AccountRoleSuperposition extends \ArrayObject
{
    /**
     * Exclude filter
     *
     * @var array
     */
    private $exclude = array();

    /**
     * User which is associated with
     * the role superposition object
     *
     * @var \Scalr_Account_User
     */
    private $user;

    /**
     * Sets user which is asssociated with the role superposition object
     *
     * @param   \Scalr_Account_User|int $user User object or ID of the user
     * @return  AccountRoleSuperposition
     * @throws  \InvalidArgumentException
     */
    public function setUser($user)
    {
        if ($user === null || $user instanceof \Scalr_Account_User) {
            $this->user = $user;
        } else {
            $userId = intval($user);
            if (empty($userId)) {
                throw new \InvalidArgumentException("Invalid ID of the user.");
            }
            $this->user = \Scalr_Account_User::init();
            $this->user->loadById($userId);
        }
        return $this;
    }

    /**
     * Gets user
     *
     * @return \Scalr_Account_User|null Return user which is associated with the role superposition object
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * {@inheritdoc}
     * @see ArrayObject::append()
     */
    public function append($value)
    {
        if (!($value instanceof AccountRoleObject)) {
            throw new \InvalidArgumentException(sprintf(
                'AccountRoleObject is expected for append() method.'
            ));
        }
        parent::append($value);
    }

    /**
     * Checks if specified resource is allowed for superposition of the roles.
     *
     * If access permission is allowed at least in one role it is considered to be allowed.
     * Current exclude filter will be applied
     *
     * @param   int              $resourceId   The ID of the resource.
     * @param   string           $permissionId optional The ID of the permission associated with resource.
     * @return  bool|null        Returns true if access is allowed.
     *                           If resource or permission isn't overridden it returns null.
     * @throws  \Scalr\Acl\Exception\RoleObjectException
     */
    public function isAllowed($resourceId, $permissionId = null)
    {
        $allowed = false;

        if ($this->user) {
            if ($this->user->isAccountOwner() || $this->user->isScalrAdmin()) {
                //Scalr Admin and Account Owner is allowed for everything, without any ACL defined for them.
                return true;
            } else if ($resourceId === Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT && $permissionId === null &&
                       $this->user->canManageAcl()) {
                //Account Admin should be able to manage all relatings between environments and teams
                return true;
            }
        }

        $iterator = $this->getIterator();
        while ($iterator->valid() && !$allowed) {
            //If access permission is allowed at least in one role it is considered to be allowed.
            $allowed = ($allowed || (bool) $iterator->current()->isAllowed($resourceId, $permissionId));
            $iterator->next();
        }

        return $allowed;
    }

    /**
     * Gets the Mode for the specified ACL Resource
     *
     * @param     int       $resouceId  Identifier of the ACL Resource
     * @return    int|null  Returns the Mode for the specified ACL Resource
     */
    public function getResourceMode($resouceId)
    {
        $mode = null;

        foreach ($this->getIterator() as $role) {
            /* @var $role AccountRoleObject */
            $resource = $role->getResource($resouceId);

            //If ACL Resource is turned off we should disregard its mode because it can be set to the default value.
            if ($role->isAllowed($resouceId)) {
                //If there are no resource than default mode is applied
                $m = $resource ? $resource->getMode() : null;

                //NULL is considered to be the most priority value
                if ($m === null) break;

                //Lesser value has more priority
                $mode = $mode === null ? $m : min($mode, $m);
            }
        }

        if ($mode === null) {
            //Check if default value is defined for the specified ACL Resource
            $modeDefinition = Definition::get($resouceId)->getMode();
            if ($modeDefinition instanceof ModeInterface) {
                $mode = $modeDefinition->getDefault();
            }
        }

        return $mode;
    }

    /**
     * Gets allowed resources
     *
     * Current exclude filters will be applied
     *
     * @param   bool    $mnemonicIndexes  Should it use mnemonic indexes for key or integer ids.
     * @return  array   Returns array looks like array((mnemonicIndex|resource_id) => (null | array(permission_id => 1))
     */
    public function getAllowedArray($mnemonicIndexes = false)
    {
        $ret = [];

        $resourceNames = Acl::getResourcesMnemonic();

        foreach (Resource\Definition::getAll() as $resource) {
            /* @var $resource Resource\ResourceObject */
            if (!$this->isAllowed($resource->getResourceId())) continue;

            $rec = [];

            $aPerm = [];

            foreach ($resource->getPermissions() as $permissionId => $description) {
                if ($this->isAllowed($resource->getResourceId(), $permissionId)) {
                    $aPerm[$permissionId] = 1;
                }
            }

            if (!empty($aPerm)) {
                $rec['permissions'] = $aPerm;
            }

            if ($mode = $resource->getMode()) {
                $modeMapping = $mode->getMapping();
                $rec['mode'] = $this->getResourceMode($resource->getResourceId());
                if (isset($modeMapping[(int)$rec['mode']])) {
                    $rec['mode'] = $modeMapping[(int)$rec['mode']]->constName;
                }
            }

            $id = $mnemonicIndexes ? $resourceNames[$resource->getResourceId()] : $resource->getResourceId();

            $ret[$id] = empty($rec) ? 1 : $rec;
        }

        return $ret;
    }

    /**
     * Gets all resources
     *
     * Current exclude filters will be applied.
     * This method will return all predefined resources with its names
     *
     * @return  array   Returns array looks like
     *                 array(array(
     *                     'id'         => resource_id,
     *                     'name'       => resource_name,
     *                     'group'      => associative_group,
     *                     'granted'    => [1|0] is resource allowed,
     *                     'permissions' => array(
     *                         permissionId => [1|0] is permission allowed
     *                     ),
     *                     'mode' => valueId
     *                 ))
     */
    public function getArray()
    {
        $groupOrder = Acl::getGroups();

        $ret = array();

        foreach (Resource\Definition::getAll() as $resource) {
            /* @var $resource Resource\ResourceObject */
            $rec = array(
                'id'         => $resource->getResourceId(),
                'name'       => $resource->getName(),
                'group'      => $resource->getGroup(),
                'groupOrder' => (isset($groupOrder[$resource->getGroup()]) ? $groupOrder[$resource->getGroup()] : 0),
                'granted'    => $this->isAllowed($resource->getResourceId()) ? 1 : 0
            );

            if ($resource->getMode() !== null) {
                $rec['mode'] = $this->getResourceMode($resource->getResourceId());
            }

            $permissions = $resource->getPermissions();

            if (!empty($permissions)) {
                $rec['permissions'] = array();
                foreach ($permissions as $permissionId => $description) {
                    $rec['permissions'][$permissionId] = $this->isAllowed($resource->getResourceId(), $permissionId) ? 1 : 0;
                }
            }

            $ret[] = $rec;
        }

        return $ret;
    }

    /**
     * Excludes team roles
     *
     * @param  bool $exclude True value will exclude team roles from the calculation
     * @return AccountRoleSuperposition
     */
    public function excludeTeamRoles($exclude = true)
    {
        $this->exclude['teamRoles'] = (bool) $exclude;
        return $this;
    }

    /**
     * Excludes not team roles
     *
     * @param  bool $exclude True value will exclude not team roles from the calculation
     * @return AccountRoleSuperposition
     */
    public function excludeRoles($exclude = true)
    {
        $this->exclude['roles'] = (bool) $exclude;
        return $this;
    }

    /**
     * Overrides getIterator method
     *
     * Current exclude filter will be applied
     *
     * {@inheritdoc}
     * @see ArrayObject::getIterator()
     */
    public function getIterator()
    {
        $iterator = parent::getIterator();
        if (!empty($this->exclude['teamRoles']) || !empty($this->exclude['roles'])) {
            $iterator = new AccountRoleFilterIterator($iterator, $this->exclude);
        }
        return $iterator;
    }

    /**
     * Gets the list of the Role Ids
     *
     * Current exclude filter will be applied
     *
     * @return  array Returns the list of the Role IDs
     */
    public function getIdentifiers()
    {
        $ids = array();
        foreach ($this->getIterator() as $role) {
            $ids[$role->getRoleId()] = true;
        }
        return array_keys($ids);
    }
}