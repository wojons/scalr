<?php

namespace Scalr\Model\Entity\Account;

use Scalr\Model\AbstractEntity;

/**
 * Environment entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4.0 (23.02.2015)
 *
 * @Entity
 * @Table(name="client_environments")
 */
class Environment extends AbstractEntity
{
    /**
     * The identifier of the Environment
     *
     * @Id
     * @GeteratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * The name of the environment
     *
     * @var string
     */
    public $name;

    /**
     * The identifier of the Account
     *
     * @Column(name="client_id",type="integer")
     * @var int
     */
    public $accountId;

    /**
     * The timestamp when this environment was added
     *
     * @Column(name="dt_added",type="datetime")
     * @var \DateTime
     */
    public $added;

    /**
     * The status of the environment
     * @var string
     */
    public $status;

    /**
     * Constructor
     *
     * @param   string $accountId optional The identifier of the account
     */
    public function __construct($accountId = null)
    {
        $this->accountId = $accountId;
        $this->added = new \DateTime();
    }

    /**
     * Gets identifier of the Account
     *
     * @return   int  Returns identifier of the Accoun
     */
    public function getAccountId()
    {
        return $this->accountId;
    }
}