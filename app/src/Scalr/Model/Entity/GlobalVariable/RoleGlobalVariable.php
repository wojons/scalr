<?php

namespace Scalr\Model\Entity\GlobalVariable;

use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\GlobalVariable;

/**
 * Role global variable entity
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (01.04.2015)
 *
 * @Entity
 * @Table(name="role_variables")
 */
class RoleGlobalVariable extends GlobalVariable
{

    /**
     * Role id
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $roleId;

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        return ScopeInterface::SCOPE_ROLE;
    }
}