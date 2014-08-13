<?php

namespace Scalr\Stats\CostAnalytics\Events;

use Scalr_Environment;
use Scalr\Stats\CostAnalytics\Entity\AccountTagEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;

/**
 * AssignCostCenterEvent
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 */
class AssignCostCenterEvent extends AbstractEvent
{

    /**
     * Constructor
     *
     * @param   \Scalr_Environment $env    The environment
     * @param   string             $ccId   The uuid of the cost center
     */
    public function __construct(Scalr_Environment $env, $ccId)
    {
        parent::__construct();

        $this->ccs[] = $ccId;

        $ccName = AccountTagEntity::fetchName($ccId, TagEntity::TAG_ID_COST_CENTRE);

        $this->message = sprintf("User %s assigned a new enviroment '%s' id:%d to the cost center '%s'",
            strip_tags($this->getUserEmail()), strip_tags($env->name), $env->id, strip_tags($ccName)
        );

        $this->messageToHash = sprintf('%s|%s|%s|%s', $this->timelineEvent->dtime->format('Y-m-d'), $this->getUserEmail(), $ccId, $env->id);
    }
}