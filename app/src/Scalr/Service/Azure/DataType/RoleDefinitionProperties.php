<?php

namespace Scalr\Service\Azure\DataType;

/**
 * RoleDefinitionProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\DataType\PermissionList  $permissions
 *
 */
class RoleDefinitionProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['permissions'];

    /**
     * @var string
     */
    public $roleName;

    /**
     * @var string
     */
    public $description;

    /**
     * Specifies the scope at which this role assignment applies to.
     *
     * @var string
     */
    public $scope;

    /**
     * Sets permissions
     *
     * @param   array|PermissionList $permissions
     * @return  RoleDefinitionProperties
     */
    public function setPermissions($permissions = null)
    {
        if (!($permissions instanceof PermissionList)) {
            $permissionList = new PermissionList();

            foreach ($permissions as $permission) {
                if (!($permission instanceof PermissionData)) {
                    $permissionData = PermissionData::initArray($permission);
                } else {
                    $permissionData = $permission;
                }

                $permissionList->append($permissionData);
            }
        } else {
            $permissionList = $permissions;
        }

        return $this->__call(__FUNCTION__, [$permissionList]);
    }

}