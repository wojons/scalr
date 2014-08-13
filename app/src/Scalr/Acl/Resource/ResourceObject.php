<?php

namespace Scalr\Acl\Resource;

use Scalr\Acl\Exception;

/**
 * ResourceObject
 *
 * Contains all information about existing resource
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    30.07.2013
 */
class ResourceObject
{
    /**
     * Resource ID
     *
     * @var int
     */
    private $resourceId;

    /**
     * Resource name
     *
     * @var string
     */
    private $name;

    /**
     * Description
     *
     * @var string
     */
    private $description;

    /**
     * Available unique permissions
     *
     * @var array
     */
    private $permissions;

    /**
     * Associative group
     *
     * @var string
     */
    private $group;

    /**
     * Constructor
     *
     * @param   int        $resourceId     The ID of the ACL resource
     * @param   array      $definitionItem Array that describes resource
     * @throws  Exception\ResourceObjectException
     */
    public function __construct($resourceId, $definitionItem)
    {
        $this->resourceId = $resourceId;

        $this->name = $definitionItem[0];

        $this->description = $definitionItem[1];

        $this->group = $definitionItem[2];

        $this->permissions = array();

        if (isset($definitionItem[3])) {
            if (!is_array($definitionItem[3])) {
                throw new Exception\ResourceObjectException(sprintf(
                    "Third value of definition array must be array of the unique permissions and should look like "
                  . "array(permission_id => description), %s given", gettype($definitionItem[3])
                ));
            }
            foreach ($definitionItem[3] as $permissionid => $description) {
                if (!is_string($description)) {
                    throw new Exception\ResourceObjectException(sprintf(
                        "String is expected for description value, %s given. "
                      . "Array of unique permissions should look like array(permission_id => description)",
                        gettype($description)
                    ));
                }
                $this->permissions[strtolower($permissionid)] = $description;
            }
        }
    }

    /**
     * Gets ID of the resource
     *
     * @return  int     returns ID of the resource
     */
    public function getResourceId()
    {
        return $this->resourceId;
    }

    /**
     * Gets ACL resource name
     *
     * @return  string Returns the name of the resource
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets description of the ACL resource
     *
     * @return  string The description of the resource
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Gets available permissions definition for the resource
     *
     * @return  array Returns permissions array that looks like array(permissionid => description)
     */
    public function getPermissions()
    {
        return $this->permissions;
    }

    /**
     * Gets associative group which the resource belongs to.
     *
     * @return string Returns associative group which the resource belongs to.
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Checks whether this resource has unique permission with specified ID.
     *
     * @param   string   $permissionId  The ID of the permission
     */
    public function hasPermission($permissionId)
    {
        return isset($this->permissions[$permissionId]);
    }
}