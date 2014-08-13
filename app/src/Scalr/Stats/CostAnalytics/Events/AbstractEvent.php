<?php

namespace Scalr\Stats\CostAnalytics\Events;

use Scalr\Stats\CostAnalytics\Entity\TimelineEventEntity;
use Scalr_UI_Request;
use Scalr\Exception\AnalyticsException;
use Scalr\Stats\CostAnalytics\Entity\TimelineEventCostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\TimelineEventProjectEntity;

/**
 * AbstractEvent
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 */
abstract class AbstractEvent
{
    /**
     * Generated event
     *
     * @var \Scalr\Stats\CostAnalytics\Entity\TimelineEventEntity
     */
    protected $timelineEvent;

    /**
     * User who fires an event
     *
     * @var \Scalr_Account_User
     */
    protected $user;

    /**
     * Event message
     *
     * @var string
     */
    protected $message;

    /**
     * Message for hashing
     *
     * @var string
     */
    protected $messageToHash;

    /**
     * Cost Centers array
     *
     * @var array
     */
    protected $ccs = [];

    /**
     * Projects array
     *
     * @var array
     */
    protected $projects = [];

    /**
     * Constructor
     *
     * @throws  \Scalr\Exception\AnalyticsException
     */
    public function __construct()
    {
        $this->timelineEvent = new TimelineEventEntity();

        $request = \Scalr::getContainer()->request;

        $this->user = $request instanceof Scalr_UI_Request ? $request->getUser() : null;

        $this->timelineEvent->userId = $this->user->id;

        $constName = 'Scalr\Stats\CostAnalytics\Entity\TimelineEventEntity::EVENT_TYPE_' . strtoupper(substr(\Scalr::decamelize(preg_replace('/^.+\\\\([\w]+)$/', '\\1', get_class($this))), 0, -6));

        if (!defined($constName)) {
            throw new AnalyticsException(sprintf("Constant '%s' is not defined.", $constName));
        }

        $this->timelineEvent->eventType = constant($constName);
    }

    /**
     * Gets email address of the user who fires an event
     *
     * @return string
     */
    public function getUserEmail()
    {
        return $this->user ? $this->user->getEmail() : '[scalr]';
    }

    /**
     * Filter identifiers callback
     *
     * @param   mixed    $value
     * @return  bool
     */
    private function callbackFilter($value)
    {
        return !empty($value);
    }

    /**
     * Fires an event
     *
     * @return boolean Returns true if a new record has been added
     */
    public function fire()
    {
        $this->timelineEvent->uuid = $this->timelineEvent->type('uuid')->toPhp(substr(hash('sha1', $this->messageToHash, true), 0, 16));

        if (!$this->timelineEvent->findPk($this->timelineEvent->uuid)) {
            $this->timelineEvent->description = $this->message;
            $this->timelineEvent->save();

            //Creates timeline event records for events which affect cost centers
            foreach (array_filter($this->ccs, [$this, 'callbackFilter']) as $ccId) {
                $entity = new TimelineEventCostCentreEntity($this->timelineEvent->uuid, $ccId);
                $entity->save();
            }

            //Creates timeline event records for events which affect projects
            foreach (array_filter($this->projects, [$this, 'callbackFilter']) as $projectId) {
                $entity = new TimelineEventProjectEntity($this->timelineEvent->uuid, $projectId);
                $entity->save();
            }

            return true;
        }

        return false;
    }
}