<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Exception\ModelException;

/**
 * TimelineEventProjectEntity
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.0
 * @Entity
 * @Table(name="timeline_event_projects",service="cadb")
 */
class TimelineEventProjectEntity extends \Scalr\Model\AbstractEntity
{

    /**
     * Identifier of the timeline event
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $eventId;

    /**
     * Identifier of the Project
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $projectId;

    /**
     * Convenient costructor
     *
     * @param   string $eventId   optional The identifier of the timeline event
     * @param   string $projectId optional The identifier of the project
     */
    public function __construct($eventId = null, $projectId = null)
    {
        $this->eventId = $eventId;
        $this->projectId = $projectId;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::save()
     */
    public function save()
    {
        if (empty($this->eventId)) {
            throw new ModelException(sprintf("eventId must be set for %s before saving.", get_class($this)));
        } else if (empty($this->projectId)) {
            throw new ModelException(sprintf("projectId must be set for %s before saving.", get_class($this)));
        }

        parent::save();
    }
}