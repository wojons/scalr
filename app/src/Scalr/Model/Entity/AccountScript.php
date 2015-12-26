<?php

namespace Scalr\Model\Entity;

use Scalr\DataType\ScopeInterface;

/**
 * AccountScript entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="account_scripts")
 */
class AccountScript extends OrchestrationRule
{

    /**
     * Account Id
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $accountId;


    /**
     * {@inheritdoc}
     * @see OrchestrationRule::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        return $this->accountId == $user->accountId;
    }

    /**
     * {@inheritdoc}
     * @see OrchestrationRule::getScope()
     */
    public function getScope()
    {
        return ScopeInterface::SCOPE_ACCOUNT;
    }
}