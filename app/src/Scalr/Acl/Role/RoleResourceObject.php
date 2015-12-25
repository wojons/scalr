<?php

namespace Scalr\Acl\Role;

use Scalr\Acl\Resource\Definition;

/**
 * RoleResourceObject class
 *
 * Describes ACL RoleResource object
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    31.07.2013
 */
class RoleResourceObject
{
    /**
     * ID of the role
     *
     * @var int
     */
    private $roleId;

    /**
     * The ID of the resource
     *
     * @var int
     */
    private $resourceId;

    /**
     * Is access granted
     *
     * @var bool
     */
    private $granted;

    /**
     * The list of the unique permissions for
     * the associated resource.
     *
     * @var \ArrayObject
     */
    private $permissions;

    /**
     * Resource Mode value
     *
     * @var int
     */
    private $mode;

    /**
     * Constructor
     *
     * @param   int        $roleId     The ID of the ACL role associated with the resource
     * @param   int        $resourceId The ID of the ACL resource
     * @param   bool       $granted    optional Whether resource is allowed for the Role
     * @param   int        $mode       optional Resouce Mode value
     */
    public function __construct($roleId, $resourceId, $granted = null, $mode = null)
    {
        $this->roleId = $roleId;
        $this->resourceId = $resourceId;
        $this->granted = $granted !== null ? (bool) $granted : null;
        $this->permissions = new \ArrayObject(array());
        $this->mode = $mode;
    }

    /**
     * Appends permission object which associated with the resource
     *
     * @param   RoleResourcePermissionObject $permission The resource permission object
     * @return  RoleResourceObject
     */
    public function appendPermission(RoleResourcePermissionObject $permission)
    {
        $this->permissions[$permission->getPermissionId()] = $permission;
        return $this;
    }

    /**
     * Gets the list of the unique permissions which are associated with the ACL resource
     *
     * @return \ArrayObject Returns the list of the unique permissions which are associated with the ACL resource
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * Gets specified permission
     *
     * @param   string   $permissionId  Permission Id
     * @return  RoleResourcePermissionObject Returns unique permission of resource if it is set.
     */
    public function getPermission($permissionId)
    {
        return isset($this->permissions[$permissionId]) ?  $this->permissions[$permissionId] : null;
    }

    /**
     * Sets the ID of the role accosiated with the resource
     *
     * @param   int       $roleId   The ID of the role
     * @return  RoleResourceObject
     */
    public function setRoleId($roleId)
    {
        $this->roleId = $roleId;
        return $this;
    }

    /**
     * Sets the ID of the resource
     *
     * @param   int     $resourceId The ID of the resource
     * @return  RoleResourceObject
     */
    public function setResourceId($resourceId)
    {
        $this->resourceId = $resourceId;
        return $this;
    }

    /**
     * Sets the ID of the resource
     *
     * @param   bool     $granted Grants access to resource
     * @return  RoleResourceObject
     */
    public function setGranted($granted)
    {
        $this->granted = ($granted !== null ? (bool) $granted : null);
        return $this;
    }

    /**
     * Gets the ID of the role associated with the resource
     *
     * @return int Returns the ID of the role
     */
    public function getRoleId()
    {
        return $this->roleId;
    }

    /**
     * Gets the ID of the role associated with the resource
     *
     * @return int Returns the ID of the role
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * Checks whether access is granted.
     *
     * @return boolean|null Returns true if access granted. If null - without changes.
     */
    public function isGranted()
    {
        return $this->granted;
    }

    /**
     * Gets associative group which the resource belongs to.
     *
     * @return string
     */
    public function getGroup()
    {
        return Definition::get($this->resourceId)->getGroup();
    }

    /**
     * Gets Resource Mode
     *
     * @return  int Returns Resource Mode value
     */
    public function getMode()
    {
        return $this->mode;
    }
}