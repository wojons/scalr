<?php

namespace Scalr\Model\Entity\GlobalVariable;

use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\GlobalVariable;

/**
 * Farm Global Variable entity
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 *
 * @Entity
 * @Table(name="farm_role_variables")
 */
class FarmRoleGlobalVariable extends GlobalVariable
{

    /**
     * Farm role id
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $farmRoleId;

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        return ScopeInterface::SCOPE_FARMROLE;
    }
}