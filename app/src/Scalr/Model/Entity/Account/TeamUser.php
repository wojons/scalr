<?php

namespace Scalr\Model\Entity\Account;

use Scalr\Model\AbstractEntity;

/**
 * TeamUser
 *
 * @author  Andrii Penchuk <a.penchuk@scalr.com>
 * @since   5.11.12 (03.03.2016)
 *
 * @Entity
 * @Table(name="account_team_users")
 */
class TeamUser extends AbstractEntity
{
    /**
     * The identifier of the Team User
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * The identifier of the Team
     *
     * @Column(type="integer")
     * @var int
     */
    public $teamId;

    /**
     * The identifier of the User
     *
     * @Column(type="integer")
     * @var int
     */
    public $userId;
}