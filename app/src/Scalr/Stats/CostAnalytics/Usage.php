<?php
namespace Scalr\Stats\CostAnalytics;

use DateTime,
    DateTimeZone,
    DBFarm,
    Exception,
    SERVER_PROPERTIES,
    InvalidArgumentException,
    OutOfRangeException;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;
use Scalr\Stats\CostAnalytics\Entity\UsageHourlyEntity;
use Scalr\DataType\AggregationCollection;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Iterator\ChartPeriodIterator;
use Scalr\DataType\AggregationCollectionSet;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Stats\CostAnalytics\Entity\AccountTagEntity;
use Scalr\Stats\CostAnalytics\Entity\QuarterlyBudgetEntity;
use Scalr\Exception\AnalyticsException;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;

/**
 * Cost analytics usage
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (21.03.2014)
 */
class Usage
{
    use \Scalr\Stats\CostAnalytics\Forecast;

    const DEFAULT_CC_ID = '3f54770e-bf1a-11e3-92c5-000feae9c516';

    const DEFAULT_PROJECT_ID = '30c59dba-fc9b-4d0f-83ec-4b5043b12f72';

    const EVERYTHING_ELSE = 'everything else';

    const EVERYTHING_ELSE_CAPTION = 'Other farms (%d)';

    /**
     * Cost Analytics database connection
     *
     * @var \ADODB_mysqli
     */
    protected $cadb;

    /**
     * Database instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     * The number of others farms
     *
     * @var   int
     */
    private $otherFarmsQuantity;

    /**
     * Constructor
     *
     * @param \ADODB_mysqli $cadb Analytics database connection instance
     */
    public function __construct($cadb)
    {
        $this->cadb = $cadb;
        $this->db = \Scalr::getDb();
    }

    /**
     * Gets DI container
     *
     * @return \Scalr\DependencyInjection\Container
     */
    public function getContainer()
    {
        return \Scalr::getContainer();
    }

    /**
     * Defines default cost center and project
     *
     * @return array Returns the fixture of the cost centres
     */
    public function fixture()
    {
        return [[self::DEFAULT_CC_ID => [self::DEFAULT_PROJECT_ID]]];
    }

    /**
     * Initializes default cost centres and projects according to fixtures
     *
     * @return void
     */
    public function initDefault()
    {
        $fixture = $this->fixture();

        foreach ($fixture as $i => $c) {
            $ccId = key($c);

            $cc = CostCentreEntity::findPk($ccId);

            if (!$cc) {
                $cc = new CostCentreEntity();
                $cc->ccId = $ccId;
                $cc->name = sprintf('Cost center %02d', $i + 1);
                $cc->save();

                $cc->saveProperty(CostCentrePropertyEntity::NAME_DESCRIPTION, 'This is the automatically added cost center');
                $cc->saveProperty(CostCentrePropertyEntity::NAME_BILLING_CODE, sprintf('CC-%02d', $i));
                $cc->saveProperty(CostCentrePropertyEntity::NAME_LEAD_EMAIL, '');

                $this->cadb->Execute("
                    INSERT IGNORE `account_tag_values` (`account_id`, `tag_id`, `value_id`, `value_name`)
                    VALUES (0, ?, ?, ?)
                ", [
                    TagEntity::TAG_ID_COST_CENTRE,
                    $cc->ccId,
                    $cc->name
                ]);
            }

            foreach ($fixture[$i][$ccId] as $j => $projectId) {
                $pr = ProjectEntity::findPk($projectId);

                if (!$pr) {
                    $pr = new ProjectEntity();
                    $pr->projectId = $projectId;
                    $pr->ccId = $ccId;
                    $pr->name = sprintf('Project %d%d', $i, $j + 1);
                    $pr->save();

                    $pr->saveProperty(ProjectPropertyEntity::NAME_DESCRIPTION, 'This is the automatically generated project');
                    $pr->saveProperty(ProjectPropertyEntity::NAME_BILLING_CODE, sprintf('PR-%02d-%02d', $i, $j));
                    $pr->saveProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL, '');

                    $this->cadb->Execute("
                        INSERT IGNORE `account_tag_values` (`account_id`, `tag_id`, `value_id`, `value_name`)
                        VALUES (0, ?, ?, ?)
                    ", [
                        TagEntity::TAG_ID_PROJECT,
                        $pr->projectId,
                        $pr->name
                    ]);
                }
            }
        }

        //Assigns cost centre to each environment
        $res = $this->db->Execute("SELECT id FROM client_environments");

        while ($rec = $res->FetchRow()) {
            try {
                $environment = \Scalr_Environment::init()->loadById($rec['id']);
            } catch (Exception $e) {
                continue;
            }

            $environment->setPlatformConfig(array(\Scalr_Environment::SETTING_CC_ID => $this->autoCostCentre($environment->id)));
        }

        //Assigns project to each farm
        $res = $this->db->Execute("SELECT id, env_id, clientid FROM farms");

        while ($rec = $res->FetchRow()) {
            try {
                $dbFarm = DBFarm::LoadByID($rec['id']);
            } catch (Exception $e) {
                continue;
            }

            $dbFarm->SetSetting(DBFarm::SETTING_PROJECT_ID, $this->autoProject($dbFarm->EnvID, $dbFarm->ID));
        }

        //Initializes servers properties
        $res = $this->db->Execute("
            SELECT DISTINCT s.server_id, s.env_id, s.farm_id
            FROM servers s
            LEFT JOIN server_properties p ON p.server_id = s.server_id AND p.name = ?
            LEFT JOIN server_properties p2 ON p2.server_id = s.server_id AND p2.name = ?
            WHERE p.server_id IS NULL OR p.`value` IS NULL
            OR (s.farm_id IS NOT NULL AND (p2.server_id IS NULL OR p2.`value` IS NULL))
        ", [SERVER_PROPERTIES::ENV_CC_ID, SERVER_PROPERTIES::FARM_PROJECT_ID]);

        while ($rec = $res->FetchRow()) {
            $ccid = $this->autoCostCentre($rec['env_id']);

            $this->db->Execute("
                INSERT `server_properties` (`server_id`, `name`, `value`)
                VALUE (?, ?, ?)
                ON DUPLICATE KEY UPDATE `value` = IFNULL(`value`, ?)
            ", [
                $rec['server_id'],
                SERVER_PROPERTIES::ENV_CC_ID,
                $ccid,
                $ccid
            ]);

            //Farm may not exist for bundle task servers
            if ($rec['farm_id']) {
                $projectid = $this->autoProject($rec['env_id'], $rec['farm_id']);

                $this->db->Execute("
                    INSERT `server_properties` (`server_id`, `name`, `value`)
                    VALUE (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `value` = IFNULL(`value`, ?)
                ", [
                    $rec['server_id'],
                    SERVER_PROPERTIES::FARM_PROJECT_ID,
                    $projectid,
                    $projectid
                ]);
            }
        }
    }

    /**
     * Gets an identifier of the cost centre using fixture
     *
     * @param   int    $envId  The environment id
     * @return  string Returns UUID of the cost centre
     */
    public function autoCostCentre($envId)
    {
        $fixture = $this->fixture();

        $count = count($fixture);

        if ($count > 1) {
            $ccId = key($fixture[intval($envId) % $count]);
        } else {
            $ccId = key($fixture[0]);
        }

        return $ccId;
    }

    /**
     * Gets an identifier of the project using fixture
     *
     * @param   int    $envId   The identifier of the environment
     * @param   int    $farmId  The identifier of the farm
     * @return  string Returns UUID of the project
     */
    public function autoProject($envId, $farmId)
    {
        $fixture = $this->fixture();

        $cnt = count($fixture);

        $i = $cnt > 1 ? intval($envId) % $cnt : 0;

        reset($fixture[$i]);

        list($ccId, $v) = each($fixture[$i]);

        $cnt = count($v);

        return $cnt > 1 ? $v[intval($farmId) % $cnt] : $v[0];
    }

    /**
     * Gets the usage for specified cost centre breakdown by specified tags
     *
     * @param   array        $criteria  The list of the criterias ['ccId' => [], 'projectId' => []]
     * @param   DateTime     $begin     Begin date
     * @param   DateTime     $end       End date
     * @param   array|string $breakdown optional The identifier of the tag or list
     *                                  looks like ['day', TagEntity::TAG_ID_PROJECT, TagEntity::TAG_ID_FARM ...]
     *                                  The inteval to group data [12 hours, day, week, month]
     * @param   bool         $rawResult optional Whether it should return raw result
     * @return  AggregationCollection|array   Returns collection or array with raw result
     */
    public function get($criteria, DateTime $begin, DateTime $end, $breakdown = null, $rawResult = false)
    {
        //We do not take into cosideration not managed cost by default. It should not be shown on CC or Projects pages.
        $ignorenmusage = true;

        $now = new DateTime("now", new DateTimeZone('UTC'));
        if ($end > $now) {
            $end = $now;
        }

        if (!($begin instanceof DateTime) || !($end instanceof DateTime)) {
            throw new InvalidArgumentException(sprintf("Both Start end End time should be instance of DateTime."));
        }

        $obj = new UsageHourlyEntity();

        if ($breakdown !== null) {
            if (!is_array($breakdown)) {
                $breakdown = [$breakdown];
            }
        }

        $ccId = null;
        $projectId = null;

        $aFields = ['ccId', 'projectId', 'cost'];
        $selectFields = "`u`.`cc_id`, `u`.`project_id`, SUM(`u`.`cost`) AS `cost`";
        $selectNmFields = "`s`.`cc_id`, NULL AS `project_id`, SUM(`nu`.`cost`) AS `cost`";
        $selectUnion = "`cc_id`, `project_id`, SUM(`cost`) AS `cost`";

        if (isset($criteria) && array_key_exists('ccId', $criteria)) {
            $ccId = $criteria['ccId'];
        }

        if (isset($criteria) && array_key_exists('projectId', $criteria)) {
            $projectId = $criteria['projectId'];
            if ($projectId === '') {
                $projectId = null;
            } elseif (is_array($projectId)) {
                $projectId = array_map(function($v) {
                    return $v === '' ? null : $v;
                }, $projectId);
            }
        }

        $it = $obj->getIterator();

        //Group rules according to ChartPeriodIterator
        $groupFields = [
            'hour'                           => [true, "`u`.`dtime` `period`", null],
            'day'                            => [true, "DATE(`u`.`dtime`) `period`", null],
            //Calendar weeks. Week 1 is the first week with the Sunday in this year. (WEEK mode 0)
            'week'                           => [true, "YEARWEEK(`u`.`dtime`, 0) `period`", null],
            'month'                          => [true, "DATE_FORMAT(`u`.`dtime`, '%Y-%m') `period`", null],
            'year'                           => [true, "YEAR(`u`.`dtime`) `period`", null],
            TagEntity::TAG_ID_ENVIRONMENT    => ['envId', 's'],
            TagEntity::TAG_ID_PLATFORM       => ['platform', 'nu'],
            TagEntity::TAG_ID_FARM           => ['farmId', null],
            TagEntity::TAG_ID_FARM_ROLE      => ['farmRoleId', null],
            TagEntity::TAG_ID_CLOUD_LOCATION => ['cloudLocation', 'nu'],
            TagEntity::TAG_ID_PROJECT        => ['projectId', null],
            TagEntity::TAG_ID_COST_CENTRE    => ['ccId', 's'],
        ];

        $notSupportDaily = ['hour', TagEntity::TAG_ID_FARM_ROLE, TagEntity::TAG_ID_CLOUD_LOCATION];

        $group = '';
        $groupNm = '';
        $groupUnion = '';
        $subtotals = [];

        if (!empty($breakdown)) {
            //We are intrested in value of the environment's identifier if farm breakdown is selected
            if (in_array(TagEntity::TAG_ID_FARM, $breakdown) && !in_array(TagEntity::TAG_ID_ENVIRONMENT, $breakdown)) {
                $selectFields = "u.`env_id`, " . $selectFields;
                $selectNmFields = "s.`env_id`, " . $selectNmFields;
                $selectUnion = "`env_id`, " . $selectUnion;
                $aFields[] = 'envId';
            }

            foreach ($breakdown as $t) {
                if (!isset($groupFields[$t])) {
                    throw new InvalidArgumentException(sprintf(
                        "Tag %d is not supported as breakdown in %s call.", $t, __FUNCTION__
                    ));
                }

                if ($groupFields[$t][0] === true) {
                    $field = $it->getField('dtime');

                    $subtotals[] = 'period';

                    $selectFields = $groupFields[$t][1] . ', ' . $selectFields;

                    $selectNmFields = str_replace('`u`.', '`nu`.', $groupFields[$t][1]) . ', ' . $selectNmFields;

                    $selectUnion = "`period`, " . $selectUnion;

                    $group .= ($groupFields[$t][2] ? : "`period`") . ', ';

                    $groupNm .= str_replace('`u`.', '`nu`.', ($groupFields[$t][2] ? : "`period`")) . ', ';

                    $groupUnion .= "`period`, ";

                } else {
                    $field = $it->getField($groupFields[$t][0]);

                    $subtotals[] = $field->name;

                    if ($field->name != 'ccId' && $field->name != 'projectId') {
                        $selectFields = $field->getColumnName('u') . ', ' . $selectFields;

                        $selectNmFields = (isset($groupFields[$t][1]) ?
                            $field->getColumnName($groupFields[$t][1]) : 'NULL ' . $field->column->name) . ', ' . $selectNmFields;

                        $selectUnion = $field->column->name . ', ' . $selectUnion;
                    }

                    $group .= $field->getColumnName('u') . ', ';

                    //To avoid data duplication by environment we should exclude it from grouping.
                    //It will return only first available identifier of the environment for the specified cost centre.
                    if ($t != TagEntity::TAG_ID_ENVIRONMENT) {
                        $groupNm .= isset($groupFields[$t][1]) ? $field->getColumnName($groupFields[$t][1]) . ', ' : '';
                    }

                    $groupUnion .= $field->column->name . ', ';
                }
            }

            $group = 'GROUP BY ' . substr($group, 0, -2);

            $groupUnion = 'GROUP BY ' . substr($groupUnion, 0, -2);

            if (!empty($groupNm)) {
               $groupNm = 'GROUP BY ' . substr($groupNm, 0, -2);
            }
        }

        $orderUnion = in_array('period', $subtotals) ? 'ORDER BY `period`' : '';

        if ($rawResult) {
            $ret = [];
        } else {
            $ret = new AggregationCollection($subtotals, ['cost' => 'sum']);
            if (!is_array($ccId)) {
                $ret->setId($ccId);
            }
        }

        $uwherestmt = 'TRUE';
        $suwherestmt = 'TRUE';

        if (isset($ccId)) {
            $uwherestmt .= ' AND ' . $obj->_buildQuery([['ccId' => (is_array($ccId) ? ['$in' => $ccId] : $ccId)]], 'AND', 'u')['where'];
            $suwherestmt = preg_replace('/`u`\./', '`su`.', $uwherestmt);
        }

        if (isset($criteria) && array_key_exists('projectId', $criteria)) {
            if (is_string($projectId) && $projectId !== null || is_array($projectId) && !in_array(null, $projectId, true)) {
                $ignorenmusage = true;
            }
            $uwherestmt .= ' AND ' . $obj->_buildQuery([['projectId' => (is_array($projectId) ? ['$in' => $projectId] : $projectId)]], 'AND', 'u')['where'];
        }

        if (empty($breakdown) || count(array_intersect($notSupportDaily, array_values($breakdown))) == 0) {
            //Selects from daily usage table
            $statement = "
                SELECT " . str_replace('`dtime`', '`date`', $selectFields) . "
                FROM `usage_d` u
                WHERE " . $uwherestmt . "
                AND u.`date` >= ? AND u.`date` <= ?
                " . $groupUnion . "
                " . $orderUnion . "
            ";
            $res = $obj->db()->Execute($statement, array(
                $begin->format('Y-m-d'),
                $end->format('Y-m-d'),
            ));
        } else {
            $dtimeType = $it->getField('dtime')->type;

            //Selects from hourly usage table
            $statement = "
                SELECT " . $selectUnion . " FROM (
                    SELECT " . $selectFields . "
                    FROM `usage_h` u
                    WHERE " . $uwherestmt . "
                    AND u.`dtime` >= ? AND u.`dtime` <= ?
                    " . $group . "
                " . (isset($ignorenmusage) ? "" : "
                    UNION ALL

                    SELECT " . $selectNmFields . "
                    FROM `nm_usage_h` nu
                    JOIN (
                        SELECT us.usage_id, su.cc_id, su.env_id
                        FROM `nm_subjects_h` su
                        JOIN `nm_usage_subjects_h` us ON us.subject_id = su.subject_id
                        WHERE " . $suwherestmt . "
                        GROUP BY us.usage_id
                    ) s ON nu.usage_id = s.usage_id
                    WHERE nu.`dtime` >= ? AND nu.`dtime` <= ?
                    " . $groupNm ) . "
                ) t
                " . $groupUnion . "
                " . $orderUnion . "
            ";

            $res = $obj->db()->Execute($statement, array(
                $dtimeType->toDb($begin),
                $dtimeType->toDb($end),
                $dtimeType->toDb($begin),
                $dtimeType->toDb($end),
            ));
        }

        $aFields = array_diff(array_merge($aFields, $subtotals), ['period']);

        while ($rec = $res->FetchRow()) {
            $item = new UsageHourlyEntity();
            $item->load($rec);

            $arr = [];

            foreach ($aFields as $col) {
                $arr[$col] = $item->$col;
            }

            $arr['period'] = (string)$rec['period'];

            if ($rawResult) {
                $ret[] = $arr;
            } else {
                $ret->append($arr);
            }
        }

        //Calculates percentage
        if (!$rawResult && !empty($subtotals)) {
            $ret->calculatePercentage();
        }

        return $ret;
    }

    /**
     * Gets cost analytics for dashboard
     *
     * @param   string    $mode      The mode (week, month, quarter, year)
     * @param   string    $startDate The start date of the period in UTC ('Y-m-d')
     * @param   string    $endDate   The end date of the period in UTC ('Y-m-d')
     * @return  array     Returns cost analytics data for dashboard
     */
    public function getDashboardPeriodData($mode, $startDate, $endDate)
    {
        $analytics = $this->getContainer()->analytics;

        $utcTz = new DateTimeZone('UTC');

        $currentDate = new DateTime('now', $utcTz);

        //Period iterator
        $iterator = new ChartPeriodIterator($mode, new DateTime($startDate, $utcTz), new DateTime($endDate, $utcTz));

        //Interval which is used in the database query for grouping
        $queryInterval = preg_replace('/^1 /', '', $iterator->getInterval());

        $rawData = $this->get(
            null, $iterator->getStart(), $iterator->getEnd(),
            [$queryInterval, TagEntity::TAG_ID_COST_CENTRE, TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_PROJECT], true
        );

        //current period usage
        $collectionSet = (new AggregationCollectionSet([
            'byPlatformDetailed' => new AggregationCollection(['period', 'platform'], ['cost' => 'sum']),
            'byPlatform'         => new AggregationCollection(['platform'], ['cost' => 'sum']),
            'byCc'               => new AggregationCollection(['ccId', 'platform'], ['cost' => 'sum']),
            'byProject'          => new AggregationCollection(['projectId'], ['cost' => 'sum']),
        ]))->load($rawData)->calculatePercentage();

        //previous same period usage
        $prevCollectionSet = (new AggregationCollectionSet([
            'byPlatform'         => new AggregationCollection(['platform'], ['cost' => 'sum']),
            'byCc'               => new AggregationCollection(['ccId'], ['cost' => 'sum']),
            'byProject'          => new AggregationCollection(['projectId'], ['cost' => 'sum']),
        ]))->load($this->get(
            null, $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
            [TagEntity::TAG_ID_COST_CENTRE, TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_PROJECT], true
        ))->calculatePercentage();

        //Fills point for chart
        $fnCalculatePoint = function ($currentUsage) {
            return [
               'cost'    => isset($currentUsage['cost']) ? round($currentUsage['cost'], 2) : 0,
               'costPct' => isset($currentUsage['cost_percentage']) ? $currentUsage['cost_percentage'] : 0,
            ];
        };

        $cloudsDetailedData = [];

        $timeline = [];

        $prevPointKey = null;

        //Iterates over the period
        foreach ($iterator as $chartPoint) {
            /* @var $chartPoint \Scalr\Stats\CostAnalytics\ChartPointInfo */
            $i = $chartPoint->i;

            $timeline[] = array(
                'datetime' => $chartPoint->dt->format('Y-m-d H:00'),
                'label'    => $chartPoint->label,
                'onchart'  => $chartPoint->show,
                'cost'     => round((isset($collectionSet['byPlatformDetailed']['data'][$chartPoint->key]['cost']) ?
                              $collectionSet['byPlatformDetailed']['data'][$chartPoint->key]['cost'] : 0), 2),
            );

            //Period - Platform subtotals
            if (!isset($collectionSet['byPlatformDetailed']['data'][$chartPoint->key]['data'])) {
                foreach ($cloudsDetailedData as $platform => $v) {
                    if (!$iterator->isFuture()) {
                        $r = $fnCalculatePoint(null);

                        $cloudsDetailedData[$platform]['data'][] = $r;
                    } else {
                        $cloudsDetailedData[$platform]['data'][] = null;
                    }
                }
            } else {
                foreach ($collectionSet['byPlatformDetailed']['data'][$chartPoint->key]['data'] as $platform => $v) {
                    if (!isset($cloudsDetailedData[$platform]) && $i > 0) {
                        $cloudsDetailedData[$platform]['name'] = $platform;

                        //initializes platfrorm legend for the previous points
                        $cloudsDetailedData[$platform]['data'] = array_fill(0, $i, null);
                    }

                    if (!$iterator->isFuture()) {
                        $r = $fnCalculatePoint($v);

                        $cloudsDetailedData[$platform]['name'] = $platform;
                        $cloudsDetailedData[$platform]['data'][] = $r;

                    } else {
                        $cloudsDetailedData[$platform]['data'][] = null;
                    }
                }

                //Total
                if (!isset($cloudsDetailedData['total']) && $i > 0) {
                    $cloudsDetailedData['total']['name'] = 'total';
                    //initializes platfrorm legend for the previous points
                    $cloudsDetailedData['total']['data'] = array_fill(0, $i, null);
                }

                if (!$iterator->isFuture()) {
                    $r = $fnCalculatePoint($collectionSet['byPlatformDetailed']['data'][$chartPoint->key]);

                    $cloudsDetailedData['total']['name'] = 'total';
                    $cloudsDetailedData['total']['data'][] = $r;

                } else {
                    $cloudsDetailedData['total']['data'][] = null;
                }
            }

            $prevPointKey = $chartPoint->key;
        }

        $cloudsData = [];

        //Iterates over each platform of the current period
        $it = $collectionSet['byPlatform']->getIterator();

        foreach ($it as $platform => $p) {
            $pp = isset($prevCollectionSet['byPlatform']['data'][$platform]) ? $prevCollectionSet['byPlatform']['data'][$platform] : null;

            $cloudsData[] = $this->getTotalDataArray($platform, $platform, $p, $pp, null, null, $iterator, true);
        }

        $costCentresData = [];
        $projectsData = [];

        $budget = ['costcenters' => [], 'projects' => []];

        //Unassigned environments without cost center accosiation
        $unassignedEnvironments = [];

        foreach ($analytics->ccs->getUnassignedEnvironments() as $key => $val) {
            $unassignedEnvironments[] = ['id' => $key, 'name' => $val, 'cost' => 0];
        }

        //Iterates over each cost centre of the current period
        $it = $collectionSet['byCc']->getIterator();

        foreach ($it as $ccId => $p) {
            $pp = isset($prevCollectionSet['byCc']['data'][$ccId]) ? $prevCollectionSet['byCc']['data'][$ccId] : null;

            $r = $this->getTotalDataArray(
                $ccId, AccountTagEntity::fetchName($ccId, TagEntity::TAG_ID_COST_CENTRE), $p, $pp, null, null, $iterator, true
            );

            if (!$ccId && $it->hasChildren()) {
                //Adds expense with platforms breakdown for unassigned resources with no CC
                foreach ($it->getChildren() as $platform => $c) {
                    $r['platforms'][$platform] = [
                        'id'      => $platform,
                        'name'    => $platform,
                        'cost'    => isset($c['cost']) ? round($c['cost'], 2) : 0,
                        'costPct' => isset($c['cost_percentage']) ? $c['cost_percentage'] : 0,
                    ];
                }

                if (!empty($unassignedEnvironments)) {
                    //Trying to fetch usage for unassigned environments
                    $envUsage = (new AggregationCollection(['ccId', 'envId'], ['cost' => 'sum']))->load($rawData)->calculatePercentage();
                    if (!empty($envUsage['data']['']['data'])) {
                        foreach ($unassignedEnvironments as $v) {
                            //Each environment usage
                            if (!isset($envUsage['data']['']['data'][$v['id']])) continue;

                            $v['cost'] = round($envUsage['data']['']['data'][$v['id']]['cost']);
                        }
                    }
                }
            }

            $costCentresData[] = $r;

            if (!$ccId) continue;

            $budget['costcenters'][] = $this->getBudgetUsedPercentage(['ccId' => $ccId, 'usage' => $r['cost']]) + [
                'id'    => $ccId,
                'name'  => AccountTagEntity::fetchName($ccId, TagEntity::TAG_ID_COST_CENTRE),
            ];
        }

        //Iterates over each project of the current period
        $it = $collectionSet['byProject']->getIterator();

        foreach ($it as $projectId => $p) {
            $pp = isset($prevCollectionSet['byProject']['data'][$projectId]) ? $prevCollectionSet['byProject']['data'][$projectId] : null;

            $r = $this->getTotalDataArray(
                $projectId, AccountTagEntity::fetchName($projectId, TagEntity::TAG_ID_PROJECT), $p, $pp, null, null, $iterator, true
            );

            $projectsData[] = $r;

            if (!$projectId) continue;

            $budget['projects'][] = $this->getBudgetUsedPercentage(['projectId' => $projectId, 'usage' => $r['cost']]) + [
                'id'   => $projectId,
                'name' => AccountTagEntity::fetchName($projectId, TagEntity::TAG_ID_PROJECT),
            ];
        }

        $trends = $this->calculateSpendingTrends([], $timeline, $queryInterval, $iterator->getEnd());

        $totals = [
            'cost'         => round($collectionSet['byPlatform']['cost'], 2),
            'prevCost'     => round($prevCollectionSet['byPlatform']['cost'], 2),
            'forecastCost' => null
        ];

        $totals['growth']    = $totals['cost'] - $totals['prevCost'];
        $totals['growthPct'] = $totals['prevCost'] == 0 ? null : round(abs($totals['growth'] / $totals['prevCost'] * 100), 0);
        $totals['clouds']    = $cloudsData;
        $totals['trends']    = $trends;

        if ($iterator->getTodayDate() < $iterator->getEnd()) {
            $totals['forecastCost'] = self::calculateForecast(
                $totals['cost'], $iterator->getStart(), $iterator->getEnd(), null, null,
                (isset($totals['trends']['rollingAverageDaily']) ? $totals['trends']['rollingAverageDaily'] : null)
            );
        }

        $data = [
            'reportVersion'     => '0.1.0',
            'totals'            => $totals,
            'costcenters'       => $costCentresData,
            'projects'          => $projectsData,
            'timeline'          => $timeline,
            'clouds'            => $cloudsDetailedData,
            'budget'            => $budget,
            'startDate'         => $iterator->getStart()->format('Y-m-d'),
            'endDate'           => $iterator->getEnd()->format('Y-m-d'),
            'previousStartDate' => $iterator->getPreviousStart()->format('Y-m-d'),
            'previousEndDate'   => $iterator->getPreviousEnd()->format('Y-m-d'),
            'interval'          => $queryInterval
        ];

        if (!empty($unassignedEnvironments)) {
            $data['unassignedEnvironments'] = $unassignedEnvironments;
        }

        return $data;
    }

    /**
     * Gets analytics data for the cost center
     *
     * @param   string   $ccId      The identifier of the cost center (UUID)
     * @param   string   $mode      Mode (week, month, quarter, year, custom)
     * @param   string   $startDate Start date in UTC (Y-m-d)
     * @param   string   $endDate   End date in UTC (Y-m-d)
     * @return  array    Returns analytics data for the specified cost center
     */
    public function getCostCenterPeriodData($ccId, $mode, $startDate, $endDate)
    {
        $analytics = $this->getContainer()->analytics;

        $utcTz = new DateTimeZone('UTC');

        $currentDate = new DateTime('now', $utcTz);

        $iterator = new ChartPeriodIterator($mode, new DateTime($startDate, $utcTz), new DateTime($endDate, $utcTz), 'UTC');

        $timelineEvents = $analytics->events->count($iterator->getInterval(), $iterator->getStart(), $iterator->getEnd(), $ccId);

        //Interval which is used in the database query for grouping
        $queryInterval = preg_replace('/^1 /', '', $iterator->getInterval());

        //Current period data
        $collectionSet = (new AggregationCollectionSet([
            'byPlatformDetailed' => new AggregationCollection(['period', 'platform', 'projectId'], ['cost' => 'sum']),
            'byProjectDetailed'  => new AggregationCollection(['period', 'projectId', 'platform'], ['cost' => 'sum']),
            'byPlatform'         => new AggregationCollection(['platform', 'projectId'], ['cost' => 'sum']),
            'byProject'          => new AggregationCollection(['projectId', 'platform'], ['cost' => 'sum'])
        ]))->load($this->get(
            ['ccId' => $ccId], $iterator->getStart(), $iterator->getEnd(),
            [$queryInterval, TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_PROJECT], true
        ))->calculatePercentage();

        //Previous period data
        $collectionSetPrev = (new AggregationCollectionSet([
            'byPlatformDetailed' => new AggregationCollection(['period', 'platform', 'projectId'], ['cost' => 'sum']),
            'byProjectDetailed'  => new AggregationCollection(['period', 'projectId', 'platform'], ['cost' => 'sum']),
            'byPlatform'         => new AggregationCollection(['platform', 'projectId'], ['cost' => 'sum']),
            'byProject'          => new AggregationCollection(['projectId', 'platform'], ['cost' => 'sum'])
        ]))->load($this->get(
            ['ccId' => $ccId], $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
            [$queryInterval, TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_PROJECT], true
        ))->calculatePercentage();

        //Gets the list of the projects which is assigned to cost center at current moment to optimize
        //retrieving its names with one query
        $projects = new ArrayCollection();
        foreach (ProjectEntity::findByCcId($ccId) as $i) {
            $projects[$i->projectId] = $i;
        }

        //Function retrieves the name of the project by specified identifier
        $fnGetProjectName = function($projectId) use ($projects) {
            if (empty($projectId)) {
                $projectName = 'Unassigned resources';
            } else if (!isset($projects[$projectId])) {
                //Trying to find the name of the project in the tag values history
                if (null === ($pe = AccountTagEntity::findOne([['tagId' => TagEntity::TAG_ID_PROJECT], ['valueId' => $projectId]]))) {
                    $projectName = $projectId;
                } else {
                    $projectName = $pe->valueName;
                    unset($pe);
                }
            } else {
                $projectName = $projects[$projectId]->name;
            }

            return $projectName;
        };

        $cloudsData = [];
        $projectsData = [];
        $timeline = [];
        $prevPointKey = null;

        foreach ($iterator as $chartPoint) {
            /* @var $chartPoint \Scalr\Stats\CostAnalytics\ChartPointInfo */
            $i = $chartPoint->i;

            $timeline[] = array(
                'datetime' => $chartPoint->dt->format('Y-m-d H:00'),
                'label'    => $chartPoint->label,
                'onchart'  => $chartPoint->show,
                'cost'     => round((isset($collectionSet['byPlatformDetailed']['data'][$chartPoint->key]['cost']) ?
                              $collectionSet['byPlatformDetailed']['data'][$chartPoint->key]['cost'] : 0), 2),
                'events'   => isset($timelineEvents[$chartPoint->key]) ? $timelineEvents[$chartPoint->key] : null
            );

            //Period - Platform - Projects subtotals
            if (!isset($collectionSet['byPlatformDetailed']['data'][$chartPoint->key]['data'])) {
                foreach ($cloudsData as $platform => $v) {
                    if (!$iterator->isFuture()) {
                        //Previous period details
                        if (isset($collectionSetPrev['byPlatformDetailed']['data'][$chartPoint->previousPeriodKey]['data'][$platform])) {
                            $pp = $collectionSetPrev['byPlatformDetailed']['data'][$chartPoint->previousPeriodKey]['data'][$platform];
                        } else {
                            $pp = null;
                        }

                        //Previous point details
                        if (isset($collectionSet['byPlatformDetailed']['data'][$prevPointKey]['data'][$platform])) {
                            $ppt = $collectionSet['byPlatformDetailed']['data'][$prevPointKey]['data'][$platform];
                        } else {
                            $ppt = null;
                        }

                        $r = $this->getPointDataArray(null, $pp, $ppt);

                        //Projects data is empty
                        $r['projects'] = [];

                        $cloudsData[$platform]['data'][] = $r;

                    } else {
                        $cloudsData[$platform]['data'][] = null;
                    }
                }
            } else {
                foreach ($collectionSet['byPlatformDetailed']['data'][$chartPoint->key]['data'] as $platform => $v) {
                    //Previous period details
                    if (isset($collectionSetPrev['byPlatformDetailed']['data'][$chartPoint->previousPeriodKey]['data'][$platform])) {
                        $pp = $collectionSetPrev['byPlatformDetailed']['data'][$chartPoint->previousPeriodKey]['data'][$platform];
                    } else {
                        $pp = null;
                    }

                    //Previous point details
                    if (isset($collectionSet['byPlatformDetailed']['data'][$prevPointKey]['data'][$platform])) {
                        $ppt = $collectionSet['byPlatformDetailed']['data'][$prevPointKey]['data'][$platform];
                    } else {
                        $ppt = null;
                    }

                    if (!isset($cloudsData[$platform]) && $i > 0) {
                        $cloudsData[$platform]['name'] = $platform;

                        //initializes platfrorm legend for the not filled period
                        $cloudsData[$platform]['data'] = array_fill(0, $i, null);
                    }

                    if (!$iterator->isFuture()) {
                        $r = $this->getPointDataArray($v, $pp, $ppt);

                        // projects data
                        $cloudProjectData = [];
                        if (!empty($v['data'])) {
                            foreach ($v['data'] as $projectId => $pv) {
                                if (isset($pp['data'][$projectId])) {
                                    $ppp = $pp['data'][$projectId];
                                } else {
                                    $ppp = null;
                                }

                                if (isset($ppt['data'][$projectId])) {
                                    $pppt = $ppt['data'][$projectId];
                                } else {
                                    $pppt = null;
                                }

                                $cloudProjectData[] = $this->getDetailedPointDataArray(
                                    $projectId, $fnGetProjectName($projectId), $pv, $ppp, $pppt
                                );
                            }
                        }

                        $r['projects'] = $cloudProjectData;

                        $cloudsData[$platform]['name'] = $platform;
                        $cloudsData[$platform]['data'][] = $r;
                    } else {
                        $cloudsData[$platform]['data'][] = null;
                    }

                }
            }

            //Period - Project - Platform subtotal
            if (!isset($collectionSet['byProjectDetailed']['data'][$chartPoint->key]['data'])) {
                foreach ($projectsData as $projectId => $v) {
                    if (!$iterator->isFuture()) {
                        //Previous period details
                        if (isset($collectionSetPrev['byProjectDetailed']['data'][$chartPoint->previousPeriodKey]['data'][$projectId])) {
                            $pp = $collectionSetPrev['byProjectDetailed']['data'][$chartPoint->previousPeriodKey]['data'][$projectId];
                        } else {
                            $pp = null;
                        }

                        //Previous point details
                        if (isset($collectionSet['byProjectDetailed']['data'][$prevPointKey]['data'][$projectId])) {
                            $ppt = $collectionSet['byProjectDetailed']['data'][$prevPointKey]['data'][$projectId];
                        } else {
                            $ppt = null;
                        }

                        $r = $this->getPointDataArray(null, $pp, $ppt);

                        //Projects data is empty
                        $r['clouds'] = [];

                        $projectsData[$projectId]['data'][] = $r;
                    } else {
                        $projectsData[$projectId]['data'][] = null;
                    }
                }
            } else {
                foreach ($collectionSet['byProjectDetailed']['data'][$chartPoint->key]['data'] as $projectId => $v) {
                    //Previous period details
                    if (isset($collectionSetPrev['byProjectDetailed']['data'][$chartPoint->previousPeriodKey]['data'][$projectId])) {
                        $pp = $collectionSetPrev['byProjectDetailed']['data'][$chartPoint->previousPeriodKey]['data'][$projectId];
                    } else {
                        $pp = null;
                    }

                    //Previous point details
                    if (isset($collectionSet['byProjectDetailed']['data'][$prevPointKey]['data'][$projectId])) {
                        $ppt = $collectionSet['byProjectDetailed']['data'][$prevPointKey]['data'][$projectId];
                    } else {
                        $ppt = null;
                    }

                    if (!isset($projectsData[$projectId]) && $i > 0) {
                        $projectsData[$projectId]['name'] = $fnGetProjectName($projectId);
                        //initializes project legend for the not filled period
                        $projectsData[$projectId]['data'] = array_fill(0, $i, null);
                    }

                    if (!$iterator->isFuture()) {
                        $r = $this->getPointDataArray($v, $pp, $ppt);

                        // platform data
                        $cloudPlatformData = [];
                        if (!empty($v['data'])) {
                            foreach ($v['data'] as $platform => $pv) {
                                if (isset($pp['data'][$platform])) {
                                    $ppp = $pp['data'][$platform];
                                } else {
                                    $ppp = null;
                                }

                                if (isset($ppt['data'][$platform])) {
                                    $pppt = $ppt['data'][$platform];
                                } else {
                                    $pppt = null;
                                }

                                $cloudPlatformData[] = $this->getDetailedPointDataArray($platform, $platform, $pv, $ppp, $pppt);
                            }
                        }

                        $r['clouds'] = $cloudPlatformData;

                        $projectsData[$projectId]['name'] = $fnGetProjectName($projectId);
                        $projectsData[$projectId]['data'][] = $r;
                    } else {
                        $projectsData[$projectId]['data'][] = null;
                    }

                }
            }

            $prevPointKey = $chartPoint->key;
        }

        //complete arrays for cloud data and project data

        $cntpoints = count($timeline);

        foreach ($cloudsData as $platform => $v) {
            if (($j = count($v['data'])) < $cntpoints) {
                while ($j < $cntpoints) {
                   $cloudsData[$platform]['data'][] = null;
                   $j++;
                }
            }
        }

        foreach ($projectsData as $projectId => $v) {
            if (($j = count($v['data'])) < $cntpoints) {
                while ($j < $cntpoints) {
                   $projectsData[$projectId]['data'][] = null;
                   $j++;
                }
            }
        }

        //Spending trends uses daily usage precalculated data
        $trends = $this->calculateSpendingTrends(['ccId' => $ccId], $timeline, $queryInterval, $iterator->getEnd());

        if ($iterator->getWholePreviousPeriodEnd() != $iterator->getPreviousEnd()) {
            $rawPrevUsageWhole = $this->get(
                ['ccId' => $ccId], $iterator->getPreviousStart(), $iterator->getWholePreviousPeriodEnd(),
                [TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_PROJECT], true
            );

            //Previous whole period usage subtotals by platform
            $prevUsageWhole = (new AggregationCollection(['platform'], ['cost' => 'sum']))->load($rawPrevUsageWhole);

            //Previous whole period usage subtotals by project
            $prevUsageWhole2 = (new AggregationCollection(['projectId'], ['cost' => 'sum']))->load($rawPrevUsageWhole);
        } else {
            $prevUsageWhole  = $collectionSetPrev['byPlatform'];
            $prevUsageWhole2 = $collectionSetPrev['byProject'];
        }

        //Build cloud platforms total
        $cloudsTotal = [];

        $it = $collectionSet['byPlatform']->getIterator();

        foreach ($it as $platform => $p) {
            $pp = isset($collectionSetPrev['byPlatform']['data'][$platform]) ? $collectionSetPrev['byPlatform']['data'][$platform] : null;

            $pw = isset($prevUsageWhole['data'][$platform]) ? $prevUsageWhole['data'][$platform] : null;

            $cl = $this->getTotalDataArray($platform, $platform, $p, $pp, $pw, $cloudsData, $iterator);

            if ($it->hasChildren()) {
                $clProjects = [];

                foreach ($it->getChildren() as $projectId => $c) {
                    $cp = isset($collectionSetPrev['byPlatform']['data'][$platform]['data'][$projectId]) ?
                          $collectionSetPrev['byPlatform']['data'][$platform]['data'][$projectId] : null;

                    $clProjects[] = $this->getTotalDataArray($projectId, $fnGetProjectName($projectId), $c, $cp, null, $projectsData, $iterator, true);
                }

                $cl['projects'] = $clProjects;
            } else {
                $cl['projects'] = [];
            }

            $cloudsTotal[] = $cl;
        }

        //Build projects total
        $projectsTotal = [];

        $it = $collectionSet['byProject']->getIterator();

        //For each assigned project wich is not archived we should display
        //zero dollar spend even if there are not any spend for
        //the selected period.

        $projectsWithoutSpend = [];

        foreach ($projects as $projectEntity) {
            /* @var $projectEntity \Scalr\Stats\CostAnalytics\Entity\ProjectEntity */
            if ($projectEntity->archived) {
                continue;
            }

            if (!isset($collectionSet['byProject']['data'][$projectEntity->projectId])) {
                $projectsWithoutSpend[$projectEntity->projectId] = [
                    'cost'            => 0,
                    'cost_percentage' => 0,
                    'id'              => $projectEntity->projectId,
                ];
            }
        }

        //Passing projects with spend and then assigned projects without spend for the selected period
        foreach ([$it, $projectsWithoutSpend] as $internalIterator) {
            foreach ($internalIterator as $projectId => $p) {
                $pp = isset($collectionSetPrev['byProject']['data'][$projectId]) ? $collectionSetPrev['byProject']['data'][$projectId] : null;

                $pw = isset($prevUsageWhole2['data'][$projectId]) ? $prevUsageWhole2['data'][$projectId] : null;

                $cl = $this->getTotalDataArray($projectId, $fnGetProjectName($projectId), $p, $pp, $pw, $projectsData, $iterator);

                if ($internalIterator instanceof AggregationCollection && $internalIterator->hasChildren()) {
                    $clPlatforms = [];

                    foreach ($internalIterator->getChildren() as $platform => $c) {
                        $cp = isset($collectionSetPrev['byProject']['data'][$projectId]['data'][$platform]) ?
                              $collectionSetPrev['byProject']['data'][$projectId]['data'][$platform] : null;

                        $clPlatforms[] = $this->getTotalDataArray($platform, $platform, $c, $cp, null, $cloudsData, $iterator, true);
                    }

                    $cl['clouds'] = $clPlatforms;
                } else {
                    $cl['clouds'] = [];
                }

                $projectsTotal[] = $cl;
            }
        }

        $data = [
            'reportVersion'    => '0.1.0',
            'totals' => [
                'cost'         => round($collectionSet['byPlatform']['cost'], 2),
                'prevCost'     => round($collectionSetPrev['byPlatform']['cost'], 2),
                'growth'       => round($collectionSet['byPlatform']['cost'] - $collectionSetPrev['byPlatform']['cost'], 2),
                'growthPct'    => $collectionSetPrev['byPlatform']['cost'] == 0 ? null : round(abs((($collectionSet['byPlatform']['cost'] - $collectionSetPrev['byPlatform']['cost']) / $collectionSetPrev['byPlatform']['cost']) * 100), 0),
                'clouds'       => $cloudsTotal,
                'projects'     => $projectsTotal,
                'trends'       => $trends,
                'forecastCost' => null,
            ],
            'timeline'          => $timeline,
            'clouds'            => $cloudsData,
            'projects'          => $projectsData,
            'interval'          => $queryInterval,
            'startDate'         => $iterator->getStart()->format('Y-m-d'),
            'endDate'           => $iterator->getEnd()->format('Y-m-d'),
            'previousStartDate' => $iterator->getPreviousStart()->format('Y-m-d'),
            'previousEndDate'   => $iterator->getPreviousEnd()->format('Y-m-d'),
        ];

        if ($iterator->getTodayDate() < $iterator->getEnd()) {
            $data['totals']['forecastCost'] = self::calculateForecast(
                $data['totals']['cost'], $iterator->getStart(), $iterator->getEnd(), $prevUsageWhole['cost'],
                ($data['totals']['growth'] >= 0 ? 1 : -1) * $data['totals']['growthPct'],
                (isset($data['totals']['trends']['rollingAverageDaily']) ? $data['totals']['trends']['rollingAverageDaily'] : null)
            );
        }

        $budgetRequest = ['ccId' => $ccId, 'usage' => $data['totals']['cost']];
        if ($mode != 'custom') {
            //We need to get budget for the appropriate quarter
            $budgetRequest['period'] = $iterator->getQuarterPeriod();
        }
        $budget = $this->getBudgetUsedPercentage($budgetRequest);
        $this->calculateBudgetEstimateOverspend($budget);

        $data['totals']['budget'] = $budget;

        return $data;
    }

    /**
     * Gets cost center moving average to date
     *
     * @param    string    $ccId       The identifier of the Cost center
     * @param    string    $mode       The mode
     * @param    string    $date       The date within specified period 'Y-m-d H:00'
     * @param    string    $startDate  The start date of the period 'Y-m-d'
     * @param    string    $endDate    The end date of the period 'Y-m-d'
     * @return   array     Returns cost center moving average to date
     */
    public function getCostCenterMovingAverageToDate($ccId, $mode, $date, $startDate, $endDate)
    {
        $iterator = new ChartPeriodIterator($mode, $startDate, ($endDate ?: null), 'UTC');

        if (!preg_match('/^[\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:00$/', $date)) {
            throw new InvalidArgumentException(sprintf("Invalid date:%s. 'YYYY-MM-DD HH:00' is expected.", strip_tags($date)));
        }

        if (!preg_match('/^[[:xdigit:]-]{36}$/', $ccId)) {
            throw new InvalidArgumentException(sprintf("Invalid identifier of the Cost center:%s. UUID is expected.", strip_tags($ccId)));
        }

        //Interval which is used in the database query for grouping
        $queryInterval = preg_replace('/^1 /', '', $iterator->getInterval());

        $data = $this->getRollingAvg(['ccId' => $ccId], $queryInterval, $date);

        $data['budgetUseToDate'] = null;
        $data['budgetUseToDatePct'] = null;

        //Does not calculate budget use to date for those cases
        if ($mode != 'custom' && $mode != 'year') {
            //FIXME rewrite search a point
            foreach ($iterator as $chartPoint) {
                if ($chartPoint->dt->format('Y-m-d H:00') == $date) {
                    break;
                }
            }
            if ($chartPoint instanceof ChartPointInfo) {
                //Gets the end date of the selected interval
                $end = clone $chartPoint->dt;

                if ($chartPoint->interval != '1 day') {
                    $end->modify("+" . $chartPoint->interval . " -1 day");
                }

                //Gets quarters config
                $quarters = new Quarters(SettingEntity::getQuarters());

                //Gets start and end of the quarter for the end date of the current interval
                $period = $quarters->getPeriodForDate($end);

                $data['year'] = $period->year;
                $data['quarter'] = $period->quarter;
                $data['quarterStartDate'] = $period->start->format('Y-m-d');
                $data['quarterEndDate'] = $period->end->format('Y-m-d');

                //Gets budgeted cost
                $budget = current(QuarterlyBudgetEntity::getCcBudget($period->year, $ccId)->filterByQuarter($period->quarter));

                //If budget has not been set we should not calculate anything
                if ($budget instanceof QuarterlyBudgetEntity && round($budget->budget) > 0) {
                    $data['budget'] = round($budget->budget);

                    //Calculates usage from the start date of the quarter to date
                    $usage = $this->get(
                        ['ccId' => $ccId],
                        $period->start,
                        $end
                    );

                    $data['budgetUseToDate'] = $usage['cost'];

                    $data['budgetUseToDatePct'] = $data['budget'] == 0 ? null :
                        min(100, round($usage['cost'] / $data['budget'] * 100));
                }
            }
        }

        return ['data' => $data];
    }

    /**
     * Fetches farm's name
     *
     * @param   mixed    $farmId  The identifier of the farm
     * @return  string   Returns display name of the farm
     */
    public function fetchFarmName($farmId)
    {
        return $farmId == self::EVERYTHING_ELSE ? sprintf(self::EVERYTHING_ELSE_CAPTION, $this->otherFarmsQuantity) :
               AccountTagEntity::fetchName($farmId, TagEntity::TAG_ID_FARM);
    }

    /**
     * Gets analytics data for the specified project and period
     *
     * @param   string   $projectId The identifier of the Project (UUID)
     * @param   string   $mode      Mode (week, month, quarter, year, custom)
     * @param   string   $startDate Start date in UTC (Y-m-d)
     * @param   string   $endDate   End date in UTC (Y-m-d)
     * @return  array    Returns analytics data for the specified project and period
     */
    public function getProjectPeriodData($projectId, $mode, $startDate, $endDate)
    {
        $analytics = $this->getContainer()->analytics;

        /* @var $projectEntity ProjectEntity */
        $projectEntity = ProjectEntity::findPk($projectId);

        $ccId = $projectEntity->ccId;

        $utcTz = new DateTimeZone('UTC');

        $currentDate = new DateTime('now', $utcTz);

        $iterator = new ChartPeriodIterator($mode, new DateTime($startDate, $utcTz), new DateTime($endDate, $utcTz), 'UTC');

        $start = $iterator->getStart();

        $end = $iterator->getEnd();

        $timelineEvents = $analytics->events->count($iterator->getInterval(), $iterator->getStart(), $iterator->getEnd(), null, $projectId);

        //Interval which is used in the database query for grouping
        $queryInterval = preg_replace('/^1 /', '', $iterator->getInterval());

        //Requests data for the specified period
        $rawUsage = $this->get(
            ['projectId' => $projectId], $start, $end,
            [$queryInterval, TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_FARM], true
        );

        //Requests data for the previous period
        $rawPrevUsage = $this->get(
            ['projectId' => $projectId], $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
            [$queryInterval, TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_FARM], true
        );

        $max = 5;

        //Calculates top five farms for the specified period
        $top5farms = [];
        $this->otherFarmsQuantity = 0;
        $arr = (new AggregationCollection(['farmId'], ['cost' => 'sum']))->load($rawUsage)->getArrayCopy();
        if (!empty($arr['data']) && count($arr['data']) > $max + 1) {
            $this->otherFarmsQuantity = count($arr['data']) - $max;
            uasort($arr['data'], function ($a, $b) {
                if ($a['cost'] == $b['cost']) return 0;
                return $a['cost'] < $b['cost'] ? 1 : -1;
            });
            $i = 0;
            foreach ($arr['data'] as $farmId => $v) {
                $top5farms[$farmId] = $farmId;
                if (++$i >= 5) break;
            }
        }

        $usgByPlatformDetailed = (new AggregationCollection(['period', 'platform'], ['cost' => 'sum']))
            ->load($rawUsage)->calculatePercentage();

        $usgByPlatformPrevDetailed = (new AggregationCollection(['period', 'platform'], ['cost' => 'sum']))
            ->load($rawPrevUsage)->calculatePercentage();

        if (empty($top5farms)) {
            $usgByFarmDetailed = (new AggregationCollection(['period', 'farmId', 'platform'], ['cost' => 'sum']))
                ->load($rawUsage)->calculatePercentage();

            $usgByFarmPrevDetailed = (new AggregationCollection(['period', 'farmId', 'platform'], ['cost' => 'sum']))
                ->load($rawPrevUsage)->calculatePercentage();
        } else {
            $usgByFarmDetailed = new AggregationCollection(['period', 'farmId', 'platform'], ['cost' => 'sum']);
            foreach ($rawUsage as $d) {
                if (!array_key_exists($d['farmId'], $top5farms)) {
                    $d['farmId'] = self::EVERYTHING_ELSE;
                }
                $usgByFarmDetailed->append($d);
            }
            $usgByFarmDetailed->calculatePercentage();

            $usgByFarmPrevDetailed = new AggregationCollection(['period', 'farmId', 'platform'], ['cost' => 'sum']);
            foreach ($rawPrevUsage as $d) {
                if (!array_key_exists($d['farmId'], $top5farms)) {
                    $d['farmId'] = self::EVERYTHING_ELSE;
                }
                $usgByFarmPrevDetailed->append($d);
            }
            $usgByFarmPrevDetailed->calculatePercentage();
        }

        $cloudsData = [];

        $farmsData = [];

        $timeline = [];

        $prevPointKey = null;

        foreach ($iterator as $chartPoint) {
            /* @var $chartPoint \Scalr\Stats\CostAnalytics\ChartPointInfo */
            $i = $chartPoint->i;

            $timeline[] = [
                'datetime' => $chartPoint->dt->format('Y-m-d H:00'),
                'label'    => $chartPoint->label,
                'onchart'  => $chartPoint->show,
                'cost'     => round((isset($usgByPlatformDetailed['data'][$chartPoint->key]['cost']) ?
                              $usgByPlatformDetailed['data'][$chartPoint->key]['cost'] : 0), 2),
                'events'   => isset($timelineEvents[$chartPoint->key]) ? $timelineEvents[$chartPoint->key] : null
            ];

            //Period - Platform - Farms subtotals
            if (!isset($usgByPlatformDetailed['data'][$chartPoint->key]['data'])) {
                foreach ($cloudsData as $platform => $v) {
                    if (!$iterator->isFuture()) {
                        //Previous period details
                        if (isset($usgByPlatformPrevDetailed['data'][$chartPoint->previousPeriodKey]['data'][$platform])) {
                            $pp = $usgByPlatformPrevDetailed['data'][$chartPoint->previousPeriodKey]['data'][$platform];
                        } else {
                            $pp = null;
                        }

                        //Previous point details
                        if (isset($usgByPlatformDetailed['data'][$prevPointKey]['data'][$platform])) {
                            $ppt = $usgByPlatformDetailed['data'][$prevPointKey]['data'][$platform];
                        } else {
                            $ppt = null;
                        }

                        $r = $this->getPointDataArray(null, $pp, $ppt);

                        //Projects data is empty
                        $r['farms'] = [];

                        $cloudsData[$platform]['data'][] = $r;

                    } else {
                        $cloudsData[$platform]['data'][] = null;
                    }
                }
            } else {
                foreach ($usgByPlatformDetailed['data'][$chartPoint->key]['data'] as $platform => $v) {
                    //Previous period details
                    if (isset($usgByPlatformPrevDetailed['data'][$chartPoint->previousPeriodKey]['data'][$platform])) {
                        $pp = $usgByPlatformPrevDetailed['data'][$chartPoint->previousPeriodKey]['data'][$platform];
                    } else {
                        $pp = null;
                    }

                    //Previous point details
                    if (isset($usgByPlatformDetailed['data'][$prevPointKey]['data'][$platform])) {
                        $ppt = $usgByPlatformDetailed['data'][$prevPointKey]['data'][$platform];
                    } else {
                        $ppt = null;
                    }

                    if (!isset($cloudsData[$platform]) && $i > 0) {
                        $cloudsData[$platform]['name'] = $platform;

                        //initializes platfrorm legend for the not filled period
                        $cloudsData[$platform]['data'] = array_fill(0, $i, null);
                    }

                    if (!$iterator->isFuture()) {
                        $r = $this->getPointDataArray($v, $pp, $ppt);

                        // Farms data is accessible by clicking on a point
                        $r['farms'] = [];

                        $cloudsData[$platform]['name'] = $platform;
                        $cloudsData[$platform]['data'][] = $r;

                    } else {
                        $cloudsData[$platform]['data'][] = null;
                    }

                }
            }


            //Period - Farm - Platform subtotal
            if (!isset($usgByFarmDetailed['data'][$chartPoint->key]['data'])) {
                foreach ($farmsData as $farmId => $v) {
                    if (!$iterator->isFuture()) {
                        //Previous period details
                        if (isset($usgByFarmPrevDetailed['data'][$chartPoint->previousPeriodKey]['data'][$farmId])) {
                            $pp = $usgByFarmPrevDetailed['data'][$chartPoint->previousPeriodKey]['data'][$farmId];
                        } else {
                            $pp = null;
                        }

                        //Previous point details
                        if (isset($usgByFarmDetailed['data'][$prevPointKey]['data'][$farmId])) {
                            $ppt = $usgByFarmDetailed['data'][$prevPointKey]['data'][$farmId];
                        } else {
                            $ppt = null;
                        }

                        $r = $this->getPointDataArray(null, $pp, $ppt);

                        //Projects data is empty
                        $r['clouds'] = [];

                        $farmsData[$farmId]['data'][] = $r;

                    } else {
                        $farmsData[$farmId]['data'][] = null;
                    }
                }
            } else {
                foreach ($usgByFarmDetailed['data'][$chartPoint->key]['data'] as $farmId => $v) {
                    //Previous period details
                    if (isset($usgByFarmPrevDetailed['data'][$chartPoint->previousPeriodKey]['data'][$farmId])) {
                        $pp = $usgByFarmPrevDetailed['data'][$chartPoint->previousPeriodKey]['data'][$farmId];
                    } else {
                        $pp = null;
                    }

                    //Previous point details
                    if (isset($usgByFarmDetailed['data'][$prevPointKey]['data'][$farmId])) {
                        $ppt = $usgByFarmDetailed['data'][$prevPointKey]['data'][$farmId];
                    } else {
                        $ppt = null;
                    }

                    if (!isset($farmsData[$farmId]) && $i > 0) {
                        $farmsData[$farmId]['name'] = $this->fetchFarmName($farmId);

                        //initializes project legend for the not filled period
                        $farmsData[$farmId]['data'] = array_fill(0, $i, null);
                    }

                    if (!$iterator->isFuture()) {
                        $r = $this->getPointDataArray($v, $pp, $ppt);

                        // platform data
                        $cloudPlatformData = [];
                        if (!empty($v['data'])) {
                            foreach ($v['data'] as $platform => $pv) {
                                $cloudPlatformData[] = $this->getDetailedPointDataArray(
                                    $platform, $platform, $pv,
                                    (isset($pp['data'][$platform]) ? $pp['data'][$platform] : null),
                                    (isset($ppt['data'][$platform]) ? $ppt['data'][$platform] : null)
                                );
                            }
                        }

                        $r['clouds'] = $cloudPlatformData;

                        $farmsData[$farmId]['name'] = $this->fetchFarmName($farmId);

                        $farmsData[$farmId]['data'][] = $r;

                    } else {
                        $farmsData[$farmId]['data'][] = null;
                    }

                }
            }

            $prevPointKey = $chartPoint->key;
        }

        //complete arrays for cloud data and project data

        $cntpoints = count($timeline);

        foreach ($cloudsData as $platform => $v) {
            if (($j = count($v['data'])) < $cntpoints) {
                while ($j < $cntpoints) {
                   $cloudsData[$platform]['data'][] = null;
                   $j++;
                }
            }
        }

        foreach ($farmsData as $farmId => $v) {
            if (($j = count($v['data'])) < $cntpoints) {
                while ($j < $cntpoints) {
                   $farmsData[$farmId]['data'][] = null;
                   $j++;
                }
            }
        }

        //Subtotals by platforms
        $usage = new AggregationCollection(['platform', 'farmId'], ['cost' => 'sum']);

        //Subtotals by farms
        $usage2 = new AggregationCollection(['farmId' => ['envId'], 'platform'], ['cost' => 'sum']);

        //Previous period subtotals by platforms
        $prevUsage = new AggregationCollection(['platform', 'farmId'], ['cost' => 'sum']);

        //Previous period subtotals by farms
        $prevUsage2 = new AggregationCollection(['farmId', 'platform'], ['cost' => 'sum']);

        if (empty($top5farms)) {
            //Loads current period
            foreach ($rawUsage as $item) {
                $usage->append($item);
                $usage2->append($item);
            }

            //Loads previous period
            foreach ($rawPrevUsage as $item) {
                $prevUsage->append($item);
                $prevUsage2->append($item);
            }
        } else {
            //Loads current period and aggregates top 5 farms
            foreach ($rawUsage as $item) {
                if (!array_key_exists($item['farmId'], $top5farms)) {
                    $item['farmId'] = self::EVERYTHING_ELSE;
                }
                $usage->append($item);
                $usage2->append($item);
            }

            //Loads previous period and aggregates top 5 farms
            foreach ($rawPrevUsage as $item) {
                if (!array_key_exists($item['farmId'], $top5farms)) {
                    $item['farmId'] = self::EVERYTHING_ELSE;
                }
                $prevUsage->append($item);
                $prevUsage2->append($item);
            }
        }

        //Calculates percentage
        $usage->calculatePercentage();
        $usage2->calculatePercentage();
        $prevUsage->calculatePercentage();
        $prevUsage2->calculatePercentage();

        if ($iterator->getWholePreviousPeriodEnd() != $iterator->getPreviousEnd()) {
            $rawPrevUsageWhole = $this->get(
                ['projectId' => $projectId], $iterator->getPreviousStart(), $iterator->getWholePreviousPeriodEnd(),
                [TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_FARM], true
            );

            //Previous whole period usage subtotals by platform
            $prevUsageWhole = (new AggregationCollection(['platform'], ['cost' => 'sum']))->load($rawPrevUsageWhole);

            //Previous whole period usage subtotals by farm
            $prevUsageWhole2 = (new AggregationCollection(['farmId'], ['cost' => 'sum']))->load($rawPrevUsageWhole);
        } else {
            $prevUsageWhole  = $prevUsage;
            $prevUsageWhole2 = $prevUsage2;
        }

        //Build cloud platforms total
        $cloudsTotal = [];

        $it = $usage->getIterator();

        foreach ($it as $platform => $p) {
            $pp = isset($prevUsage['data'][$platform]) ? $prevUsage['data'][$platform] : null;

            $pw = isset($prevUsageWhole['data'][$platform]) ? $prevUsageWhole['data'][$platform] : null;

            $cl = $this->getTotalDataArray($platform, $platform, $p, $pp, $pw, $cloudsData, $iterator);

            if ($it->hasChildren()) {
                $clFarms = [];

                foreach ($it->getChildren() as $farmId => $c) {
                    $cp = isset($prevUsage['data'][$platform]['data'][$farmId]) ?
                          $prevUsage['data'][$platform]['data'][$farmId] : null;

                    $clFarms[] = $this->getTotalDataArray(
                        $farmId,
                        $this->fetchFarmName($farmId),
                        $c, $cp, null, $farmsData, $iterator, true
                    );
                }

                $cl['farms'] = $clFarms;
            } else {
                $cl['farms'] = [];
            }

            $cloudsTotal[] = $cl;
        }

        //Build projects total
        $farmsTotal = [];

        $it = $usage2->getIterator();

        foreach ($it as $farmId => $p) {
            $pp = isset($prevUsage2['data'][$farmId]) ? $prevUsage2['data'][$farmId] : null;

            $pw = isset($prevUsageWhole2['data'][$farmId]) ? $prevUsageWhole2['data'][$farmId] : null;

            $cl = $this->getTotalDataArray(
                $farmId,
                $this->fetchFarmName($farmId),
                $p, $pp, $pw, $farmsData, $iterator
            );

            if ($farmId && $farmId != self::EVERYTHING_ELSE && !empty($p['envId'])) {
                $cl['environment'] = [
                   'id'    => (int) $p['envId'],
                   'name'  => AccountTagEntity::fetchName($p['envId'], TagEntity::TAG_ID_ENVIRONMENT),
                ];
            }

            if ($it->hasChildren()) {
                $clPlatforms = [];

                foreach ($it->getChildren() as $platform => $c) {
                    $cp = isset($prevUsage2['data'][$farmId]['data'][$platform]) ?
                          $prevUsage2['data'][$farmId]['data'][$platform] : null;

                    $clPlatforms[] = $this->getTotalDataArray($platform, $platform, $c, $cp, null, $cloudsData, $iterator, true);
                }

                $cl['clouds'] = $clPlatforms;
            } else {
                $cl['clouds'] = [];
            }

            $farmsTotal[] = $cl;
        }

        $data = [
            'reportVersion'    => '0.1.0',
            'totals' => [
                'cost'         => round($usage['cost'], 2),
                'prevCost'     => round($prevUsage['cost'], 2),
                'growth'       => round($usage['cost'] - $prevUsage['cost'], 2),
                'growthPct'    => $prevUsage['cost'] == 0 ? null : round(abs((($usage['cost'] - $prevUsage['cost']) / $prevUsage['cost']) * 100), 0),
                'clouds'       => $cloudsTotal,
                'farms'        => $farmsTotal,
                'trends'       => $this->calculateSpendingTrends(['projectId' => $projectId], $timeline, $queryInterval, $iterator->getEnd()),
                'forecastCost' => null,
            ],
            'timeline'          => $timeline,
            'clouds'            => $cloudsData,
            'farms'             => $farmsData,
            'interval'          => $queryInterval,
            'startDate'         => $iterator->getStart()->format('Y-m-d'),
            'endDate'           => $iterator->getEnd()->format('Y-m-d'),
            'previousStartDate' => $iterator->getPreviousStart()->format('Y-m-d'),
            'previousEndDate'   => $iterator->getPreviousEnd()->format('Y-m-d'),
        ];

        if ($iterator->getTodayDate() < $iterator->getEnd()) {
            //Today is in the selected period
            $data['totals']['forecastCost'] = self::calculateForecast(
                $data['totals']['cost'], $start, $end, $prevUsageWhole['cost'],
                ($data['totals']['growth'] >= 0 ? 1 : -1) * $data['totals']['growthPct'],
                (isset($data['totals']['trends']['rollingAverageDaily']) ? $data['totals']['trends']['rollingAverageDaily'] : null)
            );
        }

        $budgetRequest = ['projectId' => $projectId, 'usage' => $data['totals']['cost']];
        if ($mode != 'custom') {
            //We need to get budget for the appropriate quarter
            $budgetRequest['period'] = $iterator->getQuarterPeriod();
        }

        $budget = $this->getBudgetUsedPercentage($budgetRequest);
        $this->calculateBudgetEstimateOverspend($budget);

        $data['totals']['budget'] = $budget;

        return $data;
    }


    /**
     * Gets project moving average to date
     *
     * @param   string|null $projectId    The identifier of the project
     * @param   string      $mode         The mode
     * @param   string      $date         The UTC date within period ('Y-m-d H:00')
     * @param   string      $startDate    The start date of the period in UTC ('Y-m-d')
     * @param   string      $endDate      The end date of the period in UTC ('Y-m-d')
     * @param   string      $ccId         optional The identifier of the cost center (It is used only when project is null)
     * @return  array       Returns project moving average to date
     * @throws  InvalidArgumentException
     * @throws  AnalyticsException
     */
    public function getProjectMovingAverageToDate($projectId, $mode, $date, $startDate, $endDate, $ccId = null)
    {
        $projectId = (empty($projectId) ? null : $projectId);

        if (!preg_match('/^[\d]{4}-[\d]{2}-[\d]{2} [\d]{2}:00$/', $date)) {
            throw new InvalidArgumentException(sprintf("Invalid date:%s. 'YYYY-MM-DD HH:00' is expected.", strip_tags($date)));
        }

        if (!preg_match('/^[[:xdigit:]-]{36}$/', $projectId)) {
            throw new InvalidArgumentException(sprintf("Invalid identifier of the Project:%s. UUID is expected.", strip_tags($projectId)));
        }

        $iterator = new ChartPeriodIterator($mode, $startDate, ($endDate ?: null), 'UTC');

        //Interval which is used in the database query for grouping
        $queryInterval = preg_replace('/^1 /', '', $iterator->getInterval());

        if ($projectId !== null) {
            $project = ProjectEntity::findPk($projectId);

            if ($project === null) {
                if (empty($ccId)) {
                    throw new AnalyticsException(sprintf("Project %s does not exist. Please provide ccId.", $projectId));
                }
            } else {
                $ccId = $project->ccId;
            }
        }

        $data = $this->getRollingAvg(['projectId' => $projectId], $queryInterval, $date);

        $data['budgetUseToDate'] = null;
        $data['budgetUseToDatePct'] = null;

        //Does not calculate budget use to date for those cases
        if ($mode != 'custom' && $mode != 'year') {
            //FIXME rewrite search a point
            foreach ($iterator as $chartPoint) {
                if ($chartPoint->dt->format('Y-m-d H:00') == $date) {
                    break;
                }
            }

            if ($chartPoint instanceof ChartPointInfo) {
                //Gets the end date of the selected interval
                $end = clone $chartPoint->dt;

                if ($chartPoint->interval != '1 day') {
                    $end->modify("+" . $chartPoint->interval . " -1 day");
                }

                //Gets quarters config
                $quarters = new Quarters(SettingEntity::getQuarters());

                //Gets start and end of the quarter for the end date of the current interval
                $period = $quarters->getPeriodForDate($end);

                $data['year'] = $period->year;
                $data['quarter'] = $period->quarter;
                $data['quarterStartDate'] = $period->start->format('Y-m-d');
                $data['quarterEndDate'] = $period->end->format('Y-m-d');

                //Gets budgeted cost
                $budget = current(QuarterlyBudgetEntity::getProjectBudget($period->year, $projectId)->filterByQuarter($period->quarter));

                //If budget has not been set we should not calculate anything
                if ($budget instanceof QuarterlyBudgetEntity && round($budget->budget) > 0) {
                    $data['budget'] = round($budget->budget);

                    //Calculates usage from the start date of the quarter to date
                    $usage = $this->get(
                        ['projectId' => $projectId],
                        $period->start,
                        $end
                    );

                    $data['budgetUseToDate'] = $usage['cost'];

                    $data['budgetUseToDatePct'] = $data['budget'] == 0 ? null :
                        min(100, round($usage['cost'] / $data['budget'] * 100));
                }
            }
        }

        return ['data' => $data];
    }


    /**
     * Gets detailed top 5 usage by farms for specified project on date
     *
     * @param   string|null $projectId    The identifier of the project
     * @param   string      $platform     The cloud platform
     * @param   string      $mode         The mode
     * @param   string      $date         The UTC date within period ('Y-m-d H:00')
     * @param   string      $start        The start date of the period in UTC ('Y-m-d')
     * @param   string      $end          The end date of the period in UTC ('Y-m-d')
     * @param   string      $ccId         optional The identifier of the cost center (It is used only when project is null)
     * @return  array       Returns detailed top 5 usage by farms for specified project on date
     * @throws  AnalyticsException
     * @throws  OutOfBoundsException
     */
    public function getProjectFarmsTopUsageOnDate($projectId, $platform, $mode, $date, $start, $end, $ccId = null)
    {
        $projectId = empty($projectId) ? null : $projectId;

        $iterator = new ChartPeriodIterator($mode, $start, ($end ?: null), 'UTC');

        //Interval which is used in the database query for grouping
        $queryInterval = preg_replace('/^1 /', '', $iterator->getInterval());

        if ($projectId !== null) {
            $project = ProjectEntity::findPk($projectId);

            if ($project === null) {
                if (empty($ccId)) {
                    throw new AnalyticsException(sprintf("Project %s does not exist. Please provide ccId.", $projectId));
                }
            } else {
                $ccId = $project->ccId;
            }
        }

        //Requests data for the specified period
        $rawUsage = $this->get(
            ['projectId' => $projectId], $iterator->getStart(), $iterator->getEnd(),
            [$queryInterval, TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_FARM], true
        );

        //Requests data for the previous period
        $rawPrevUsage = $this->get(
            ['projectId' => $projectId], $iterator->getPreviousStart(), $iterator->getPreviousEnd(),
            [$queryInterval, TagEntity::TAG_ID_PLATFORM, TagEntity::TAG_ID_FARM], true
        );

        //We do not need to calculate the percentage here
        $usg = (new AggregationCollection(['period', 'platform', 'farmId' => ['envId']], ['cost' => 'sum']))->load($rawUsage);

        $prevUsg = (new AggregationCollection(['period', 'platform', 'farmId'], ['cost' => 'sum']))->load($rawPrevUsage)->calculatePercentage();

        //Previous chart point
        $prevcp = null;
        //Finds the key for current label
        foreach ($iterator as $chartPoint) {
            //FIXME rewrite search a point
            if ($chartPoint->dt->format('Y-m-d H:00') !== $date) {
                $prevcp = $chartPoint;
                continue;
            }

            $cp = $chartPoint;
            break;
        }

        if (!isset($cp)) {
            throw new OutOfRangeException(sprintf(
                'Requested date (%s) is out of the range. Last point date is %s',
                $date, isset($prevcp->dt) ? $prevcp->dt->format('Y-m-d H:00') : 'undefined'
            ));
        }

        $result = [];

        //Maximum number of the farms without grouping
        $max = 5;
        $sEverything = self::EVERYTHING_ELSE;

        if (!empty($usg['data'][$cp->key]['data'][$platform]['data'])) {
            $usgFarms = new AggregationCollection(['farmId' => ['envId']], ['cost' => 'sum']);

            $ptr = $usg['data'][$cp->key]['data'][$platform]['data'];

            uasort($ptr, function ($a, $b) {
                if ($a['cost'] == $b['cost']) return 0;
                return ($a['cost'] > $b['cost']) ? -1 : 1;
            });

            //Aggregates farms if its number more then max + 1
            if (count($ptr) > ($max + 1)) {
                $this->otherFarmsQuantity = count($ptr) - $max;
                $new = [];
                $i = 0;
                foreach ($ptr as $farmId => $v) {
                    $v['cost_percentage'] = round(($usg['data'][$cp->key]['data'][$platform]['cost'] == 0 ? 0 : $v['cost'] * 100 / $usg['data'][$cp->key]['data'][$platform]['cost']), 0);
                    if ($i < $max) {
                        $new[$farmId] = $v;
                    } elseif (!isset($new[self::EVERYTHING_ELSE])) {
                        $v['id'] = self::EVERYTHING_ELSE;
                        $new[self::EVERYTHING_ELSE] = $v;
                    } else {
                        $new[self::EVERYTHING_ELSE]['cost'] += $v['cost'];
                    }
                    $i++;
                }
                $new[self::EVERYTHING_ELSE]['cost_percentage'] = round(($usg['data'][$cp->key]['data'][$platform]['cost'] == 0 ? 0 : $new[self::EVERYTHING_ELSE]['cost'] * 100 / $usg['data'][$cp->key]['data'][$platform]['cost']), 0);

                $usgFarms->setArray(['data' => $new]);
            } else {
                $usgFarms->setArray($usg['data'][$cp->key]['data'][$platform])->calculatePercentage();
            }

            //Forms result data array
            foreach ($usgFarms->getIterator() as $farmId => $pv) {
                $record = $this->getDetailedPointDataArray(
                    $farmId,
                    $this->fetchFarmName($farmId),
                    $pv,
                    isset($prevUsg['data'][$cp->previousPeriodKey]['data'][$platform]['data'][$farmId]) ? $prevUsg['data'][$cp->previousPeriodKey]['data'][$platform]['data'][$farmId] : null,
                    isset($usg['data'][$cp->key]['data'][$platform]['data'][$farmId]) ? $usg['data'][$cp->key]['data'][$platform]['data'][$farmId] : null
                );

                if ($farmId && $farmId != self::EVERYTHING_ELSE && !empty($pv['envId'])) {
                    $record['environment'] = [
                       'id'    => (int) $pv['envId'],
                       'name'  => AccountTagEntity::fetchName($pv['envId'], TagEntity::TAG_ID_ENVIRONMENT),
                    ];
                }

                $result[] = $record;
            }
        }

        return ['data' => $result];
    }
}