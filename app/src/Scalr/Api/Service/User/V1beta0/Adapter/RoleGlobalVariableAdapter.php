<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Model\Entity\GlobalVariable\RoleGlobalVariable;

/**
 * RoleGlobalVariableAdapter V1
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (18.03.2015)
 */
class RoleGlobalVariableAdapter extends GlobalVariableAdapter
{

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = RoleGlobalVariable::class;
}