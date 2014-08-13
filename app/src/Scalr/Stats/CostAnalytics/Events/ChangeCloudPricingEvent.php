<?php

namespace Scalr\Stats\CostAnalytics\Events;

/**
 * ChangeCloudPricingEvent
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 */
class ChangeCloudPricingEvent extends AbstractEvent
{

    /**
     * Constructor
     *
     * @param string $platform  Platform name
     * @param string $url       optional Endpoint url
     */
    public function __construct($platform, $url = null)
    {
        parent::__construct();

        $this->message = sprintf("User %s changed cloud pricing for '%s' platform%s",
            strip_tags($this->getUserEmail()), $platform,
            (!empty($url) ? ', url:' . strip_tags($url) : '')
        );

        $this->messageToHash = sprintf('%s|%s|%s', $this->timelineEvent->dtime->format('Y-m-d'), $platform, ($url ?: ''));
    }
}