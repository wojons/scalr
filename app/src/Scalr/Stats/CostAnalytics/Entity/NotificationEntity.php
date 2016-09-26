<?php

namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\DataType\AccessPermissionsInterface;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\AbstractEntity;

/**
 * NotificationEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (29.05.2014)
 * @Entity
 * @Table(name="notifications",service="cadb")
 */
class NotificationEntity extends AbstractEntity implements AccessPermissionsInterface, ScopeInterface
{
    const SUBJECT_TYPE_CC = 1;

    const SUBJECT_TYPE_PROJECT = 2;

    const NOTIFICATION_TYPE_USAGE = 1;

    const NOTIFICATION_TYPE_PROJECTED_OVERSPEND = 2;

    const NOTIFICATION_TYPE_SPEND_RATE = 3;

    const RECIPIENT_TYPE_LEADS = 1;

    const RECIPIENT_TYPE_EMAILS = 2;

    const STATUS_ENABLED = 1;

    const STATUS_DISABLED = 0;

    /**
     * identifier (UUID)
     *
     * @Id
     * @GeneratedValue("CUSTOM")
     * @Column(type="uuid")
     * @var string
     */
    public $uuid;

    /**
     * The type of the subject
     * 1 - CC , 2 - Project
     *
     * @Column(type="integer")
     * @var int
     */
    public $subjectType;

    /**
     * Identifier of the CC or Project
     *
     * @Column(type="uuid",nullable=true)
     * @var string
     */
    public $subjectId;

    /**
     * The account which is associated with the notification.
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * The type of the notification
     *
     * @Column(type="integer")
     * @var int
     */
    public $notificationType;

    /**
     * The Threshold
     *
     * @Column(type="decimal", precision=12, scale=2)
     * @var float
     */
    public $threshold;

    /**
     * The type of the recipient
     *
     * @Column(type="integer")
     * @var int
     */
    public $recipientType;

    /**
     * Recipients
     *
     * Comma separated list of the emails is allowed
     *
     * @var string
     */
    public $emails;

    /**
     * Status
     *
     * @Column(type="integer")
     * @var int
     */
    public $status;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->recipientType = 1;
        $this->emails = '';
    }

    /**
     * {@inheritdoc}
     * @see ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->accountId) ? static::SCOPE_ACCOUNT : static::SCOPE_SCALR;
    }

    /**
     * {@inheritdoc}
     * @see AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        switch ($this->getScope()) {
            case static::SCOPE_ACCOUNT:
                return $this->accountId == $user->accountId;

            case static::SCOPE_SCALR:
                return $this->accountId === null;

            default:
                return false;
        }
    }

}
