<?php

namespace Scalr\Stats\CostAnalytics;

use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Model\Entity;

/**
 * Cost analytics projects
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (11.02.2014)
 */
class Projects
{

    /**
     * Database connection instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     *
     * @var  ArrayCollection
     */
    protected $collection;

    /**
     * Constructor
     *
     * @param \ADODB_mysqli $db Database connection instance
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Gets all projects
     *
     * It returns projects with all properties by one query
     *
     * @param    bool   $ignoreCache  optional Should it ignore cache or not
     * @param    array  $criteria     optional Search criteria ['envId' => '', 'accountId' => '', 'accountId' => '']
     * @return   ArrayCollection  Returns collection of all ProjectEntity objects
     */
    public function all($ignoreCache = false, $criteria = null)
    {
        if ($this->collection === null || $ignoreCache || !is_null($criteria)) {
            $this->collection = new ArrayCollection();

            $projectEntity = new ProjectEntity();

            $ccEntity = new CostCentreEntity();

            $projectPropertyEntity = new ProjectPropertyEntity();

            $idField = $projectEntity->getIterator()->getField('projectId');

            $where = 'WHERE 1=1';
            $join = '';

            $this->parseFindCriteria($criteria, $join, $where);

            $rs = $this->db->Execute("
                SELECT " . $projectEntity->fields('p') . ", " . $ccEntity->fields('c', true) . ",
                    pp.name as property_name,
                    pp.value as property_value
                FROM " . $projectEntity->table('p') . "
                JOIN " . $ccEntity->table('c') . " ON c.`cc_id` = p.`cc_id`
                LEFT JOIN " . $projectPropertyEntity->table('pp') . " ON pp.project_id = p.project_id " .
                $join . "
                " . $where . "
                ORDER BY p.project_id
            ");

            while ($rec = $rs->FetchRow()) {
                $id = $idField->type->toPhp($rec['project_id']);

                if (!isset($this->collection[$id])) {
                    $entity = new ProjectEntity();
                    $entity->load($rec);

                    if ($rec['c__cc_id']) {
                        $cc = new CostCentreEntity();
                        $cc->load($rec, 'c');
                        $entity->setCostCenter($cc);
                    }

                    $entity->setProperty($rec['property_name'], $rec['property_value']);

                    $this->collection[$entity->projectId] = $entity;
                } else {
                    $this->collection[$id]->setProperty($rec['property_name'], $rec['property_value']);
                }
            }
        }
        return $this->collection;
    }

    /**
     * Gets a specified project
     *
     * @param   string    $projectId    The identifier of the project
     * @param   bool      $ignoreCache  optional Should it ignore cache or not
     * @param   array     $criteria     optional Search criteria ['envId' => '', 'accountId' => '']
     * @return  ProjectEntity Returns the ProjectEntity on success or null if it does not exist.
     */
    public function get($projectId, $ignoreCache = false, $criteria = null)
    {
        $all = $this->all($ignoreCache, $criteria);
        return isset($all[$projectId]) ? $all[$projectId] : null;
    }

    /**
     * Parses common criteria
     *
     * @param     array    $criteria array of the query search criteria
     * @param     string   $join     joins
     * @param     string   $where    where part of the statement
     */
    private function parseFindCriteria($criteria, &$join, &$where)
    {
        

        if (isset($criteria['accountId'])) {
            $join .= " JOIN account_ccs ac ON ac.cc_id = c.cc_id ";
            $where .= "
                AND ac.account_id = " . $this->db->escape($criteria['accountId']) . "
                AND p.shared = " . ProjectEntity::SHARED_WITHIN_ACCOUNT. "
                AND p.account_id = " . $this->db->escape($criteria['accountId']) . "
            ";
        }
    }

    /**
     * Finds projects by key
     * It searches by name or billing number
     *
     * @param   string    $key       optional Search key
     * @param   array     $criteria  optional Search criteria ['envId' => '', 'accountId' => '', 'accountId' => '']
     * @return  ArrayCollection Returns collection of the ProjectEntity objects
     */
    public function findByKey($key = null, $criteria = null, $ignoreCache = false)
    {
        if (is_null($key) || $key === '') {
            return $this->all($ignoreCache, $criteria);
        }

        $collection = new ArrayCollection();

        $projectEntity = new ProjectEntity();

        //Includes archived projects
        $projectPropertyEntity = new ProjectPropertyEntity();

        //Cost center entity
        $ccEntity = new CostCentreEntity();

        $where = '';
        $join = '';

        $this->parseFindCriteria($criteria, $join, $where);

        $rs = $this->db->Execute("
            SELECT " . $projectEntity->fields('p') . ", " . $ccEntity->fields('c', true) . "
            FROM " . $projectEntity->table('p') . "
            JOIN " . $ccEntity->table('c') . " ON c.`cc_id` = p.`cc_id` " .
            $join . "
            WHERE (p.`name` LIKE ?
            OR EXISTS (
                SELECT 1 FROM " . $projectPropertyEntity->table('pp') . "
                WHERE `pp`.project_id = `p`.`project_id`
                AND `pp`.`name` = ? AND `pp`.`value` LIKE ?
            )) " .
            $where . "
        ", [
            '%' . $key . '%',
            ProjectPropertyEntity::NAME_BILLING_CODE,
            '%' . $key . '%'
        ]);

        while ($rec = $rs->FetchRow()) {
            $item = new ProjectEntity();
            $item->load($rec);

            if ($rec['c_cc_id']) {
                $cc = new CostCentreEntity();
                $cc->load($rec, 'c');

                $item->setCostCenter($cc);
            }

            $collection->append($item);
        }

        return $collection;
    }

    /**
     * Get the list of the farms which are assigned to specified project
     *
     * @param   string                   $projectId  The UUID of the project
     * @return  array     Returns the array looks like [farm_id => name]
     */
    public function getFarmsList($projectId)
    {
        $ret = [];
        $res = $this->db->Execute("
            SELECT f.id, f.name FROM farms f
            JOIN farm_settings s ON s.farmid = f.id
            WHERE s.name = ? AND s.value = ?
        ", [
            Entity\FarmSetting::PROJECT_ID,
            $projectId
        ]);

        while ($rec = $res->FetchRow()) {
            $ret[$rec['id']] = $rec['name'];
        }

        return $ret;
    }

    /**
     * Checks if user has permissions to project in environment or account scope
     *
     * @param string $projectId     Identifier of the project
     * @param array $criteria       ['envId' => '', 'clientid' => '']
     * @return bool|mixed
     */
    public function checkPermission($projectId, array $criteria)
    {
        $and = '';

        foreach ($criteria as $name => $value) {
            $field = 'f.' . \Scalr::decamelize($name);
            $and .= " AND " . $field . "=" . $this->db->escape($value);
        }

        $projectEntity = new ProjectEntity();

        $projectId = $projectEntity->type('projectId')->toDb($projectId);

        $where = " WHERE p.project_id = UNHEX('". $projectId . "') AND EXISTS (
                SELECT * FROM farms f
                LEFT JOIN farm_settings fs ON f.id = fs.farmid
                WHERE fs.name = '". Entity\FarmSetting::PROJECT_ID . "'
                AND REPLACE(fs.value, '-', '') = HEX(p.project_id)
                $and)";

        $sql = "SELECT " . $projectEntity->fields('p') . "
                FROM " . $projectEntity->table('p') .
                $where;

        return $this->db->GetOne($sql);
    }

}