<?php
namespace Scalr\Stats\CostAnalytics;

use Scalr\Stats\CostAnalytics\Entity\TimelineEventEntity;
use BadFunctionCallException;
use ReflectionClass;
use DateTime;
use DateTimeZone;
use Scalr\Model\Collections\ArrayCollection;

/**
 * Cost analytics events
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.0
 *
 * @method   bool fireAssignCostCenterEvent()
 *           fireAssignCostCenterEvent(\Scalr_Environment $env, string $ccId)
 *           Fires AssignCostCenter event
 *
 * @method   bool fireReplaceCostCenterEvent()
 *           fireReplaceCostCenterEvent(\Scalr_Environment $env, string $ccId, string $oldCcId)
 *           Fires ReplaceCostCenter event
 *
 * @method   bool fireAssignProjectEvent()
 *           fireAssignProjectEvent(\DBFarm $farm, string $projectId)
 *           Fires AssignProject event
 *
 * @method   bool fireChangeCloudPricingEvent()
 *           fireChangeCloudPricingEvent(string $platform, string $url = null)
 *           Fires ChangeCloudPricing event
 *
 * @method   bool fireReplaceProjectEvent()
 *           fireReplaceProjectEvent(\DBFarm $farm, string $projectId, string $oldProjectId)
 *           Fires ReplaceProject event
 */
class Events
{

    /**
     * Database connection instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     * Constructor
     *
     * @param \ADODB_mysqli $db Database connection instance
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function __call($name, $args)
    {
        if (strpos(basename($name), 'fire') === 0) {
            $class = __NAMESPACE__ . '\\Events\\' . substr(basename($name), 4);
            $ref = new ReflectionClass($class);
            $event = $ref->newInstanceArgs($args);

            return $event->fire();
        }

        throw new BadFunctionCallException(sprintf("The method '%s' does not exist for class %s", $name, get_class($this)));
    }

    /**
     * Gets formated event list
     *
     * @param $interval              $interval   Interval of the each point on the chart
     * @param \DateTime              $begin      Start date of the period on the chart
     * @param \DateTime              $end        The end date of the period on the chart
     * @param array                  $criteria   optional Filter array ['filterId' => 'value']
     * @return array    Returns array of events amount sorted by datetime
     */
    public function count($interval, $begin, $end, array $criteria = null)
    {
        if (!($begin instanceof DateTime) || !($end instanceof DateTime)) {
            throw new \InvalidArgumentException(sprintf("Both Start end End time should be instance of DateTime."));
        }

        $groupFields = [
            '1 hour'   => "DATE_FORMAT(`dtime`, '%Y-%m-%d %H:00:00')",
            '1 day'    => "DATE(`dtime`)",
            '1 week'   => "YEARWEEK(`dtime`, 0)",
            '1 month'  => "DATE_FORMAT(`dtime`, '%Y-%m')",
            '1 year'   => "YEAR(`dtime`)",
        ];

        $eventEntity = new TimelineEventEntity();
        $joinData = $this->buildJoin($criteria);
        $and = '';

        if (!empty($criteria['envId'])) {
            $and = 'AND e.env_id =' . $criteria['envId'];
        } else if (!empty($criteria['accountId'])) {
            $and = 'AND e.account_id =' . $criteria['accountId'];
        }


        $dtimeType = $eventEntity->type('dtime');

        $result = $this->db->Execute("
            SELECT COUNT(*) as count, ". $groupFields[$interval] . " as dtime
            FROM " . $eventEntity->table() . " e " .
            (isset($joinData['join']) ? $joinData['join'] : ''). "
            WHERE e.dtime BETWEEN ? AND ? "
            . $and . "
            GROUP BY " . $groupFields[$interval] . "
        ", [
            $dtimeType->toDb($begin),
            $dtimeType->toDb($end),
        ]);

        $events = [];

        while ($record = $result->FetchRow()) {
            $events[$record['dtime']] = $record['count'];
        }

        //Change cloud pricing events should be included into both projects and cost centers filter
        if (isset($joinData['join'])) {
            $result = $this->db->Execute("
                SELECT COUNT(*) as count, ". $groupFields[$interval] . " as dtime
                FROM " . $eventEntity->table() . " e
                WHERE e.dtime BETWEEN ? AND ?
                AND e.event_type = ?
                GROUP BY " . $groupFields[$interval] . "
            ", [
                $dtimeType->toDb($begin),
                $dtimeType->toDb($end),
                $eventEntity::EVENT_TYPE_CHANGE_CLOUD_PRICING,
            ]);
        }

        while ($record = $result->FetchRow()) {
            if (!isset($events[$record['dtime']])) {
                $events[$record['dtime']] = $record['count'];
            } else {
                $events[$record['dtime']] += $record['count'];
            }
        }

        return $events;
    }

    /**
     * Gets event list
     *
     * @param  \DateTime              $start      Start date of the period
     * @param  \DateTime              $end        End date of the period
     * @param  array                  $criteria   optional Filter array ['filterId' => 'value']
     * @return ArrayCollection        Returns collection of the TimelineEventEntity objects
     */
    public function get($start, $end, array $criteria = null)
    {
        $eventEntity = new TimelineEventEntity();

        $joinData = $this->buildJoin($criteria);
        $and = '';

        if (!empty($criteria['envId'])) {
            $and = 'AND e.env_id =' . $criteria['envId'];
        } else if (!empty($criteria['accountId'])) {
            $and = 'AND e.account_id =' . $criteria['accountId'];
        }

        $fields = '';
        foreach ($eventEntity->getIterator()->fields() as $field) {
            $fields .= ',`' . $field->column->name . '`';
        }

        $result = $this->db->Execute("
            SELECT " . ltrim($fields, ',') . "
            FROM (
                SELECT " . $eventEntity->fields('e') . "
                FROM " . $eventEntity->table('e') .
                (isset($joinData['join']) ? $joinData['join'] : '') . "
                WHERE e.dtime BETWEEN " . $eventEntity->qstr('dtime', $start) . " AND " . $eventEntity->qstr('dtime', $end) . " "
                . $and . "
                " . (isset($joinData['join']) ? "
                UNION
                SELECT " . $eventEntity->fields('e2') . "
                FROM " . $eventEntity->table('e2') . "
                WHERE e2.event_type = " . $eventEntity::EVENT_TYPE_CHANGE_CLOUD_PRICING . "
                AND e2.dtime BETWEEN " . $eventEntity->qstr('dtime', $start) . " AND " . $eventEntity->qstr('dtime', $end) : "") . "
            ) p
            ORDER BY p.dtime DESC
        ");

        $events = new ArrayCollection();

        while ($record = $result->FetchRow()) {
            $item = new TimelineEventEntity();

            $item->load($record);

            $events->append($item);
        }

        return $events;
    }

    /**
     * Buld join sql statement part
     *
     * @param array $criteria   optional Filter array ['filterId' => 'value']
     * @return array            Returns array of prepared data for join sql statement
     */
    private function buildJoin(array $criteria = null)
    {
        $join = [];

        if (!empty($criteria['projectId'])) {
            $eventTable = 'projects';
            $field = 'project_id';
            $value = $criteria['projectId'];
        } else if (!empty($criteria['ccId'])) {
            $eventTable = 'ccs';
            $field = 'cc_id';
            $value = $criteria['ccId'];
        }

        if (!empty($eventTable)) {
            $eventEntity = new TimelineEventEntity();
            $eventTable = 'timeline_event_' . $eventTable;
            $join['join'] = " JOIN " . $eventTable . " t ON e.uuid = t.`event_id` AND t." . $field . " = " . $eventEntity->qstr('uuid', $value);
        }

        return $join;
    }

}
