<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use SERVER_STATUS;

/**
 * Limit entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="account_limits")
 */
class Limit extends AbstractEntity
{

    const TYPE_HARD = 'hard';
    const TYPE_SOFT = 'soft';

    const ACCOUNT_FARMS = 'account.farms';
    const ACCOUNT_ENVIRONMENTS = 'account.environments';
    const ACCOUNT_USERS = 'account.users';
    const ACCOUNT_SERVERS = 'account.servers';

    /**
     * Identifier
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * Account id
     *
     * @Column(type="integer")
     * @var int
     */
    public $accountId;

    /**
     * Limit name
     *
     * @Column(name="limit_name",type="string",nullable=true)
     * @var string
     */
    public $name;

    /**
     * Limit value
     *
     * @Column(name="limit_value",type="integer")
     * @var int
     */
    public $value;

    /**
     * Limit type
     *
     * @Column(name="limit_type",type="string")
     * @var string
     */
    public $type;

    /**
     * Type value
     *
     * @Column(name="limit_type_value",type="integer")
     * @var int
     */
    public $typeValue;

    /**
     * Billing enabled flag
     *
     * @var bool
     */
    protected $_isBillingEnabled;

    public function __construct()
    {
        $this->_isBillingEnabled = \Scalr::config('scalr.billing.enabled');
    }

    /**
     * Checks this limit
     *
     * @param int $value Checked value
     *
     * @return bool
     */
    public function check($value)
    {
        if (!$this->_isBillingEnabled) {
            return true;
        }

        if (is_null($this->value) || $this->value == -1) {
            return true;
        }

        switch ($this->type) {
            case self::TYPE_HARD:
                return ($this->getCurrentUsage() + $value <= $this->value);

            case self::TYPE_SOFT:
                $limitValue = $this->value + ($this->value / 100 * $this->typeValue);
                return ($this->getCurrentUsage() + $value <= $limitValue);

            default:
                return false;
        }
    }

    /**
     * Gets current usage of this limit
     *
     * @return int
     */
    public function getCurrentUsage()
    {
        switch($this->name) {
            case self::ACCOUNT_FARMS:
                return (int) $this->db()->GetOne("SELECT COUNT(*) FROM farms WHERE clientid = ?", [$this->accountId]);

            case self::ACCOUNT_ENVIRONMENTS:
                return (int) $this->db()->GetOne("SELECT COUNT(*) FROM client_environments WHERE client_id = ?", [$this->accountId]);

            case self::ACCOUNT_SERVERS:
                return (int) $this->db()->GetOne("SELECT COUNT(*) FROM servers WHERE client_id= ? AND status IN (?, ?, ?)", [
                    $this->accountId,
                    SERVER_STATUS::PENDING,
                    SERVER_STATUS::INIT,
                    SERVER_STATUS::RUNNING
                ]);

            case self::ACCOUNT_USERS:
                return (int) $this->db()->GetOne("SELECT COUNT(*) FROM account_users WHERE account_id=?", [$this->accountId]);
        }

        return 0;
    }
}