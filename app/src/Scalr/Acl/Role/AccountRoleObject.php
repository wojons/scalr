<?php

namespace Scalr\Acl\Role;

use Scalr\Acl\Resource\ResourceObject;
use Scalr\Acl\Exception;

/**
 * AccountRoleObject class
 *
 * Describes ACL Role of Account level.
 * Account level Role must be inherited from from the one of global roles which are predefined for all server.
 * Account level role overrides access permissions defined at upper level.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    31.07.2013
 */
class AccountRoleObject extends RoleObject
{
    /**
     * The ID of the account
     * @var int
     */
    private $accountId;

    /**
     * The global role which this account role is based on
     *
     * @var RoleObject
     */
    private $baseRole;

    /**
     * The color associated with the object
     *
     * @var int
     */
    private $color;

    /**
     * Whether it is default team role
     *
     * @var bool;
     */
    private $teamRole = null;

    /**
     * Whether this role is created automatically
     * during initialization
     *
     * @var bool
     */
    private $isAutomatic = false;

    /**
     * Constructor
     *
     * @param   RoleObject $baseRole        The base role object
     * @param   int        $accountId       The ID of the account
     * @param   string     $accountRoleId   optional The ID of the ACL role of account level
     * @param   string     $accountRoleName optional The name of the ACL role of account level
     * @param   int        $color           optional The color that is associated with the object
     * @param   bool       $isAutomatic     optional Wheter this role is created automatically during initialization
     */
    public function __construct(RoleObject $baseRole, $accountId, $accountRoleId = null, $accountRoleName = null, $color = null, $isAutomatic = false)
    {
        $this->baseRole = $baseRole;
        $this->accountId = $accountId;
        $this->color = (int)$color;
        $this->isAutomatic = (bool) $isAutomatic;
        parent::__construct($accountRoleId, $accountRoleName);
    }

    /**
     * Checks wheter this role has been created automatically
     *
     * @return  boolean Returns true if role has been created automatically
     */
    public function isAutomatic()
    {
        return $this->isAutomatic;
    }

	/**
     * Gets the ID of related account
     *
     * @return  int     Returns ID of the related account
     */
    public function getAccountId()
    {
        return $this->accountId;
    }

    /**
     * Gets color as integer
     *
     * @return  int Returns color as integer
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Gets color as hexadecimal
     *
     * @return  string Returns color as hexadecimal string.
     */
    public function getColorHex()
    {
        return sprintf('%06X', $this->color);
    }

    /**
     * Sets color as integer
     *
     * @param   int    $color  Color
     * @return  AccountRoleObject
     */
    public function setColor($color)
    {
        $this->color = intval($color);
        return $this;
    }

    /**
     * Sets color as hexadecimal
     *
     * @param   string   $color Hexadecimal color
     * @return  AccountRoleObject
     */
    public function setColorHex($color)
    {
        $this->color = hexdec($color);
        return $this;
    }

	/**
     * Gets the role which this account role is based on.
     *
     * @return  RoleObject Returns the role which this account role is based on
     */
    public function getBaseRole()
    {
        return $this->baseRole;
    }

	/**
     * Sets the ID of related account
     *
     * @param   int    $accountId The ID of the related account
     * @return  AccountRoleObject
     */
    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
        return $this;
    }

	/**
     * Sets the role which this account role is based on
     *
     * @param   RoleObject $baseRole The base role.
     * @return  AccountRoleObject
     */
    public function setBaseRole(RoleObject $baseRole = null)
    {
        $this->baseRole = $baseRole;
        return $this;
    }

    /**
     * Checks if given object is allowed
     *
     * This method takes into account base role in view the inheritance
     * of the access permissions.
     *
     * {@inheritdoc}
     * @see Scalr\Acl\Role.RoleObject::isAllowed()
     */
    public function isAllowed($resourceId, $permissionId = null)
    {
        $allowed = parent::isAllowed($resourceId, $permissionId);

        if ($allowed === null) {
            //The permission has not been overridden on account level
            //so we need to return the value from the base role.
            $allowed = $this->baseRole->isAllowed($resourceId, $permissionId);
        }

        return $allowed;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Acl\Role.RoleObject::isOverridden()
     */
    public function isOverridden($resourceId, $permissionId = null)
    {
        $overridden = parent::isAllowed($resourceId, $permissionId) !== null;
        return $overridden;
    }

	/**
     * Check whether current ACL role comes from default Team role
     *
     * @return  bool|null Returns true if it comes from default Team role.
     */
    public function isTeamRole()
    {
        return $this->teamRole;
    }

	/**
     * Set isTeamRole property
     *
     * @param   bool  $isTeamRole Whether this role comes from default Team role
     * @return  AccountRoleObject
     */
    public function setTeamRole($isTeamRole)
    {
        $this->teamRole = $isTeamRole;
        return $this;
    }
}