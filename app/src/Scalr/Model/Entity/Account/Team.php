<?php

namespace Scalr\Model\Entity\Account;

use Scalr\Model\AbstractEntity;

/**
 * Team entity
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 *
 * @Entity
 * @Table(name="account_teams")
 */
class Team extends AbstractEntity
{
    /**
     * The identifier of the Team
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * Identifier of the account which Team corresponds to
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * The name of the Team
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $name;

    /**
     * The description of the Team
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $description;

    /**
     * Default Acl role for Team users
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $accountRoleId;

    /**
     * {@inheritdoc}
     * @see AbstractEntity::delete()
     */
    public function delete()
    {
        parent::delete();
        TeamEnvs::deleteByTeamId($this->id);
        TeamUser::deleteByTeamId($this->id);
    }
}