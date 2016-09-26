<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Exception\ModelException;

/**
 * TimelineEventCostCentreEntity
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.0
 * @Entity
 * @Table(name="timeline_event_ccs",service="cadb")
 */
class TimelineEventCostCentreEntity extends \Scalr\Model\AbstractEntity
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
     * Identifier of the Cost center
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $ccId;

    /**
     * Convenient costructor
     *
     * @param   string $eventId  optional The identifier of the timeline event
     * @param   string $ccId     optional The identifier of the cost center
     */
    public function __construct($eventId = null, $ccId = null)
    {
        $this->eventId = $eventId;
        $this->ccId = $ccId;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\AbstractEntity::save()
     */
    public function save()
    {
        if (empty($this->eventId)) {
            throw new ModelException(sprintf("eventId must be set for %s before saving.", get_class($this)));
        } else if (empty($this->ccId)) {
            throw new ModelException(sprintf("ccId must be set for %s before saving.", get_class($this)));
        }

        parent::save();
    }
}