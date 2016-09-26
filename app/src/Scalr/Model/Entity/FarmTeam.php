<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Farm Team entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.11.12 (09.03.2016)
 *
 * @Entity
 * @Table(name="farm_teams")
 */
class FarmTeam extends AbstractEntity
{

    /**
     * Identifier of Farm
     *
     * @Id
     * @Column(name="farm_id",type="integer")
     * @var int
     */
    public $farmId;

    /**
     * Identifier of Team
     *
     * @Id
     * @Column(name="team_id",type="integer")
     * @var int
     */
    public $teamId;

    /**
     * Get Team's IDs which are linked with farm
     *
     * @param   int         $farmId     Identifer of Farm
     * @return  array|null  Array of Team's ID or null if no teams were linked with farm
     */
    public static function getTeamIdsByFarmId($farmId)
    {
        $teams = self::findByFarmId($farmId)->map(function($ent) { return $ent->teamId; });

        return !empty($teams) ? $teams : null;
    }
}
