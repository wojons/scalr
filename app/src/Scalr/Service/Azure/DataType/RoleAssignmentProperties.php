<?php

namespace Scalr\Service\Azure\DataType;

/**
 * RoleAssignmentProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class RoleAssignmentProperties extends AbstractDataType
{
    /**
     * Specifies the role definition id used in the role assignment.
     *
     * @var string
     */
    public $roleDefinitionId;

    /**
     * Specifies the principal id.
     * This maps to the id inside the directory and can point to a user, service principal, or security group.
     *
     * @var string
     */
    public $principalId;

    /**
     * Specifies the scope at which this role assignment applies to.
     *
     * @var string
     */
    public $scope;

}