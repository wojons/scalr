<?php

namespace Scalr\Service\Azure\DataType;

/**
 * PermissionData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class PermissionData extends AbstractDataType
{
    /**
     * Collection of actions that the user can do at this scope.
     *
     * @var array
     */
    public $actions;

    /**
     * Collection of actions that the user cannot do at this scope.
     *
     * @var array
     */
    public $notActions;

}