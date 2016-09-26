<?php
namespace Scalr\Model\Entity;

use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Model\AbstractEntity;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Account\User;

/**
 * Announcement entity
 *
 * @author   Sergy Goncharov  <s.honcharov@scalr.com>
 * @since    5.11 (03.10.2016)
 *
 * @Entity
 * @Table(name="announcements")
 */
class Announcement extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{
    /**
     * ID
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * The identifier of Account
     * If not set - announcement were visible for every accounts
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $accountId;

    /**
     * User's Id, last modified announcement
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $createdById;

    /**
     * User's email, last modified announcement. Maximum length 100.
     *
     * @Column(type="string")
     * @var string
     */
    public $createdByEmail;

    /**
     * Announcement's title.  Maximum length 100.
     *
     * @Column(type="string")
     * @var string
     */
    public $title;

    /**
     * Announcement's text
     *
     * @Column(type="string")
     * @var string
     */
    public $msg;

    /**
     * Date and time when announcement was created
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $added;

    /**
     * {@inheritDoc}
     * @see \Scalr\DataType\AccessPermissionsInterface:hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        switch ($this->getScope()) {
            case static::SCOPE_ACCOUNT:
                return $this->accountId == $user->accountId;

            case static::SCOPE_SCALR:
                return $user->type == User::TYPE_SCALR_ADMIN || !$modify;

            default:
                return false;
        }
    }

    /**
     * {@inheritDoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->accountId) ? static::SCOPE_ACCOUNT : static::SCOPE_SCALR;
    }
}
