<?php

namespace Scalr\DependencyInjection;


/**
 * Analytics sub container.
 *
 * @author   Vitaliy Demidov    <vitaliy@scalr.com>
 * @since    19.10.2012
 *
 * @property \Scalr\Stats\CostAnalytics\Tags $tags
 *           Gets Tags service
 *
 * @property \Scalr\Stats\CostAnalytics\Prices $prices
 *           Gets Cloud Prices service
 *
 * @property \Scalr\Stats\CostAnalytics\CostCentres $ccs
 *           Gets the Cost centres service
 *
 * @property \Scalr\Stats\CostAnalytics\Projects $projects
 *           Gets the Projects service
 *
 * @property \Scalr\Stats\CostAnalytics\Usage $usage
 *           Gets the usage service
 *
 * @property \Scalr\Stats\CostAnalytics\Notifications $notifications
 *           Gets notifications service
 *
 * @property \Scalr\Stats\CostAnalytics\Events $events
 *           Gets the Events service
 *
 * @property bool $enabled
 *           Verifies whether Cost Analytics is enabled in the config.
 */
class AnalyticsContainer extends BaseContainer
{
    /**
     * Parent container
     *
     * @var Container
     */
    private $cont;

    /**
     * Sets main DI container
     *
     * @param   Container   $cont
     */
    public function setContainer(Container $cont)
    {
        $this->cont = $cont;
    }

    /**
     * Gets main DI container
     *
     * @return \Scalr\DependencyInjection\Container
     */
    public function getContainer()
    {
        return $this->cont;
    }
}