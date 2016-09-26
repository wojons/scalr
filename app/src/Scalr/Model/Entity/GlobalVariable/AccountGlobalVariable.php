<?php

namespace Scalr\Model\Entity\GlobalVariable;

use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\GlobalVariable;

/**
 * Account Global Variable entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="account_variables")
 */
class AccountGlobalVariable extends GlobalVariable
{
    /**
     * Account ID
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $accountId;

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        return ScopeInterface::SCOPE_ACCOUNT;
    }
}