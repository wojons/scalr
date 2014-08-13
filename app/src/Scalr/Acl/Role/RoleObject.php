<?php

namespace Scalr\Acl\Role;

use Scalr\Acl\Acl;
use Scalr\Acl\Resource;
use Scalr\Acl\Exception;

/**
 * RoleObject class
 *
 * Describes ACL Role object
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    31.07.2013
 */
class RoleObject
{
    /**
     * ID of the role
     *
     * @var int
     */
    private $roleId;

    /**
     * The name of the role
     *
     * @var string
     */
    private $name;

    /**
     * The list of the resources
     *
     * @var \ArrayObject
     */
    private $resources;

    /**
     * Constructor
     *
     * @param   int        $roleId The ID of the ACL role
     * @param   string     $name   The name of the ACL role
     */
    public function __construct($roleId, $name)
    {
        $this->roleId = $roleId;
        $this->name = $name;
        $this->resources = new \ArrayObject(array());
    }

    /**
     * Appends access rule to resource
     *
     * @param   RoleResourceObject $resource The role resource object.
     * @return  RoleObject
     */
    public function appendResource(RoleResourceObject $resource)
    {
        $this->resources[$resource->getResourceId()] = $resource;
        return $this;
    }

    /**
     * Gets the Id of the ACL role
     *
     * @return  int   Returns the ID of the ACL role
     */
    public function getRoleId()
    {
        return $this->roleId;
    }

    /**
     * Gets the name of the ACL role
     *
     * @return  string Returns the name of the ACL role
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the list of access rules to resources associated with the role.
     *
     * @return  \ArrayObject    Returns the list of the access rules.
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * Gets specified resource
     *
     * @param   int     $resourceId The ID of the ACL resource
     * @return  RoleResourceObject  Returns resource object if access has been set for it.
     */
    public function getResource($resourceId)
    {
        return isset($this->resources[$resourceId]) ? $this->resources[$resourceId] : null;
    }

    /**
     * Sets the ID of the Role
     *
     * @param   int    $roleId The ID of the role
     * @return  RoleObject
     */
    public function setRoleId($roleId)
    {
        $this->roleId = $roleId;
        return $this;
    }

    /**
     * Sets the name of the role
     *
     * @param   string   $name  The name of the role
     * @return  RoleObject
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Checks if specified resource is allowed
     *
     * @param   int              $resourceId   The ID of the resource.
     * @param   string           $permissionId optional The ID of the permission associated with resource.
     * @return  bool|null        Returns true if access is allowed.
     *                           If resource or permission isn't overridden it returns null.
     * @throws  Exception\RoleObjectException
     */
    public function isAllowed($resourceId, $permissionId = null)
    {
        $allowed = null;

        $resourceDefinition = Resource\Definition::get($resourceId);
        if ($resourceDefinition === null) {
            throw new Exception\RoleObjectException(sprintf(
                "%s ACL resource (0x%x).",
                in_array($resourceId, Acl::getDisabledResources()) ? 'Disabled' : 'Unknown',
                intval($resourceId)
            ));
        }

        if (!empty($permissionId) && !$resourceDefinition->hasPermission($permissionId)) {
            throw new Exception\RoleObjectException(sprintf(
                "Unknown permission (%s) for resource '%s' (0x%x).",
                $permissionId, $resourceDefinition->getName(), intval($resourceId)
            ));
        }

        //Checks if resource is defined for the role
        $resource = $this->getResource($resourceId);
        if ($permissionId !== null && $resource !== null) {
            //If resource is defined we can check unique permission.
            //Checks if permission is defined
            $permission = $resource->getPermission($permissionId);
            //Checks access to unuque permission of the specified resource for the role.
            //If resource isn't allowed it automatically forbids all related permissions.
            $allowed = $permission !== null && $resource->isGranted() !== null ?
                $resource->isGranted() && $permission->isGranted() : null;
        } else {
            //Checks access to the resource for the role
            $allowed = $resource !== null ? $resource->isGranted() : null;
        }

        return $allowed;
    }

    /**
     * Checks if this resource has been overridden in this role
     *
     * @param   int              $resourceId   The ID of the resource.
     * @param   string           $permissionId optional The ID of the permission associated with resource.
     * @return  boolean          Returns true if permission is overriden
     */
    public function isOverridden($resourceId, $permissionId = null)
    {
        return false;
    }

    /**
     * Gets iterator of all predefined resources with unique permissions
     *
     * @return  \ArrayIterator
     */
    public function getIteratorResources()
    {
        return Resource\Definition::getAll()->getIterator();
    }
}