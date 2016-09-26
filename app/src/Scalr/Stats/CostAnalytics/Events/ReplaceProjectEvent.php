<?php

namespace Scalr\Stats\CostAnalytics\Events;

use DBFarm;
use Scalr\Stats\CostAnalytics\Entity\AccountTagEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;

/**
 * ReplacingProjectEvent
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 */
class ReplaceProjectEvent extends AbstractEvent
{

    /**
     * Constructor
     *
     * @param   \DBFarm    $farm              The DBFarm instance
     * @param   string     $projectId         The identifier of the project to assign
     * @param   string     $oldProjectId      The identifier of the old project which is replaced
     */
    public function __construct(DBFarm $farm, $projectId, $oldProjectId)
    {
        parent::__construct();

        array_push($this->projects, $projectId, $oldProjectId);

        $projectEntity = ProjectEntity::findPk($projectId);

        if ($projectEntity) {
            $this->ccs[$projectEntity->ccId] = $projectEntity->ccId;
            $projectName = $projectEntity->name;
        } else {
            $projectName = AccountTagEntity::fetchName($projectId, TagEntity::TAG_ID_PROJECT);
        }

        $oldProjectEntity = ProjectEntity::findPk($oldProjectId);

        if ($oldProjectEntity) {
            $this->ccs[$oldProjectEntity->ccId] = $oldProjectEntity->ccId;
            $oldProjectName = $oldProjectEntity->name;
        } else {
            $oldProjectName = AccountTagEntity::fetchName($oldProjectId, TagEntity::TAG_ID_PROJECT);
        }

        $this->message = sprintf("User %s replaced project '%s' with project '%s' in the farm '%s' id:%d",
            strip_tags($this->getUserEmail()), strip_tags($oldProjectName), strip_tags($projectName), strip_tags($farm->Name), $farm->ID
        );

        $this->messageToHash = sprintf('%s|%s|%s|%s|%s', $this->timelineEvent->dtime->format('Y-m-d'), $this->getUserEmail(), $oldProjectId, $projectId, $farm->ID);
    }
}