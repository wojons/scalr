<?php

namespace Scalr\Acl\Role;

use Scalr\Acl\Resource\Definition;

/**
 * RoleResourcePermissionObject class
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    31.07.2013
 */
class RoleResourcePermissionObject
{
    /**
     * ID of the role associated with permission
     *
     * @var int
     */
    private $roleId;

    /**
     * The ID of the resource associated with permission
     *
     * @var int
     */
    private $resourceId;

    /**
     * The ID of the permission
     *
     * @var string
     */
    private $permissionId;

    /**
     * Is access granted
     *
     * @var bool
     */
    private $granted;

    /**
     * Constructor
     *
     * @param   int        $roleId       The ID of the ACL role associated with the resource
     * @param   int        $resourceId   The ID of the ACL resource
     * @param   string     $permissionId The ID of the unique permission
     * @param   bool       $granted    optional
     */
    public function __construct($roleId, $resourceId, $permissionId, $granted = null)
    {
        $this->roleId = $roleId;
        $this->resourceId = $resourceId;
        $this->permissionId = $permissionId;
        $this->granted = $granted !== null ? (bool) $granted : null;
    }

    /**
     * Sets the ID of the role accosiated with the resource
     *
     * @param   int       $roleId   The ID of the role
     * @return  RoleResourcePermissionObject
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
     * @return  RoleResourcePermissionObject
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
     * @return  RoleResourcePermissionObject
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
     * @return boolean Returns true if access granted. If null - without changes.
     */
    public function isGranted()
    {
        return $this->granted;
    }

    /**
     * Gets the ID of the permission
     *
     * @return  string Returns the ID of the permission
     */
    public function getPermissionId()
    {
        return $this->permissionId;
    }

    /**
     * Sets the ID of the permission
     *
     * @param   string $permissionId The Id of the permission
     * @return  RoleResourcePermissionObject
     */
    public function setPermissionId($permissionId)
    {
        $this->permissionId = $permissionId;
        return $this;
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
}