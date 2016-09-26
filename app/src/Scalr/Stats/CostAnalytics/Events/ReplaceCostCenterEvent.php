<?php

namespace Scalr\Stats\CostAnalytics\Events;

use Scalr_Environment;
use Scalr\Stats\CostAnalytics\Entity\AccountTagEntity;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;

/**
 * ReplacingCostCenterEvent
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 */
class ReplaceCostCenterEvent extends AbstractEvent
{

    /**
     * Constructor
     *
     * @param   \Scalr_Environment  $env           The environment
     * @param   string              $ccId          The identifier of the cost center which is assigned
     * @param   string              $oldCcId       The identifier of the old cost center which is replaced
     */
    public function __construct(Scalr_Environment $env, $ccId, $oldCcId)
    {
        parent::__construct();

        array_push($this->ccs, $ccId, $oldCcId);

        $ccName = AccountTagEntity::fetchName($ccId, TagEntity::TAG_ID_COST_CENTRE);
        $oldCcName = AccountTagEntity::fetchName($oldCcId, TagEntity::TAG_ID_COST_CENTRE);

        $this->message = sprintf("User %s replaced cost center '%s' with '%s' in the enviroment '%s' id:%d",
            strip_tags($this->getUserEmail()), strip_tags($oldCcName), strip_tags($ccName), strip_tags($env->name), $env->id
        );

        $this->messageToHash = sprintf('%s|%s|%s|%s|%s', $this->timelineEvent->dtime->format('Y-m-d'), $oldCcId, $ccId, $env->id, $this->getUserEmail());
    }
}