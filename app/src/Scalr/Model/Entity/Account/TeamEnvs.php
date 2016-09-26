<?php

namespace Scalr\Model\Entity\Account;

use Scalr\Model\AbstractEntity;

/**
 * TeamEnvs entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="account_team_envs")
 */
class TeamEnvs extends AbstractEntity
{

    /**
     * Environment ID
     *
     * @Id
     * @Column(type="integer")
     *
     * @var int
     */
    public $envId;

    /**
     * Team ID
     *
     * @Id
     * @Column(type="integer")
     *
     * @var int
     */
    public $teamId;

    /**
     * Team entity
     *
     * @var Team
     */
    public $_team;

    /**
     * Gets Team entity
     *
     * @return Team|null
     */
    public function getTeam()
    {
        if (!empty($this->teamId) && empty($this->_team)) {
            $this->_team = Team::findPk($this->teamId);
        }

        return $this->_team;
    }
}