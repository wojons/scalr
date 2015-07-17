<?php

namespace Scalr\Model\Entity;

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
}