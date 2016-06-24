<?php

namespace Scalr\Model\Entity\GlobalVariable;

use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\GlobalVariable;

/**
 * Farm Global Variable entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="farm_variables")
 */
class FarmGlobalVariable extends GlobalVariable
{

    /**
     * Farm id
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $farmId;

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        return ScopeInterface::SCOPE_FARM;
    }
}