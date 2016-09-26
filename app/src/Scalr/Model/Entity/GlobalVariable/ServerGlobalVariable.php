<?php

namespace Scalr\Model\Entity\GlobalVariable;

use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\GlobalVariable;

/**
 * Server Global Variable entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="server_variables")
 */
class ServerGlobalVariable extends GlobalVariable
{
    /**
     * Server ID
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $serverId;

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        return ScopeInterface::SCOPE_SERVER;
    }
}