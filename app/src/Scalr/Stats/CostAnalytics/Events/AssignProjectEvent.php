<?php

namespace Scalr\Stats\CostAnalytics\Events;

use DBFarm;
use Scalr\Stats\CostAnalytics\Entity\AccountTagEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

/**
 * AssignProjectEvent
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 */
class AssignProjectEvent extends AbstractEvent
{

    /**
     * Constructor
     *
     * @param   \DBFarm    $farm          The DBFarm instance
     * @param   string     $projectId     The uuid of the project
     */
    public function __construct(DBFarm $farm, $projectId)
    {
        parent::__construct();

        $projectEntity = ProjectEntity::findPk($projectId);

        $this->projects[] = $projectId;

        if ($projectEntity) {
            $this->ccs[] = $projectEntity->ccId;
            $projectName = $projectEntity->name;
        } else {
            $projectName = AccountTagEntity::fetchName($projectId, TagEntity::TAG_ID_PROJECT);
        }

        $this->message = sprintf("User %s assigned a new farm '%s' id:%d to the project '%s'",
            strip_tags($this->getUserEmail()), strip_tags($farm->Name), $farm->ID, strip_tags($projectName)
        );

        $this->messageToHash = sprintf('%s|%s|%s|%s', $this->timelineEvent->dtime->format('Y-m-d'), $this->getUserEmail(), $farm->ID, $projectId);
    }
}
