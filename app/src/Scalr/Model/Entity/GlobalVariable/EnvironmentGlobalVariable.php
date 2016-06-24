<?php

namespace Scalr\Model\Entity\GlobalVariable;

use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\GlobalVariable;

/**
 * Environment Global Variable entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="client_environment_variables")
 */
class EnvironmentGlobalVariable extends GlobalVariable
{
    /**
     * Environment ID
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $envId;

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        return ScopeInterface::SCOPE_ENVIRONMENT;
    }
}