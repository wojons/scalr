<?php
namespace Scalr\Stats\CostAnalytics;

use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr_Environment;
use Scalr_Account;

/**
 * Cost centres service
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (11.02.2014)
 */
class CostCentres
{

    /**
     * Database connection instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     * Collection of the cost centres
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
     * Gets all cost centres with all theirs properties
     *
     * @param    bool   $addHasProjects      optional Should it provide result with hasProjects flag
     * @param    bool   $ignoreCache         optional Should it ignore cache or not
     * @return   ArrayCollection  Returns collection of the CostCentreEntity objects
     */
    public function all($addHasProjects = false, $ignoreCache = false)
    {
        if ($this->collection === null || $ignoreCache) {
            $this->collection = new ArrayCollection();

            $ccEntity = new CostCentreEntity();

            $idField = $ccEntity->getIterator()->getField('ccId');

            $rs = $this->db->Execute("
                SELECT " . $ccEntity->fields('c') . ", pp.name as property_name, pp.value as property_value
                " . ($addHasProjects ? ", EXISTS(SELECT 1 FROM projects pr WHERE pr.cc_id = c.cc_id) AS `hasProjects`" : "") . "
                FROM " . $ccEntity->table('c') . "
                LEFT JOIN `cc_properties` pp ON pp.cc_id = c.cc_id
                ORDER BY c.cc_id
            ");

            while ($rec = $rs->FetchRow()) {
                $id = $idField->type->toPhp($rec['cc_id']);

                if (!isset($this->collection[$id])) {
                    $entity = new CostCentreEntity();
                    $entity->load($rec);

                    if ($addHasProjects) {
                        $entity->setHasProjects($rec['hasProjects']);
                    }

                    $entity->setProperty($rec['property_name'], $rec['property_value']);

                    $this->collection[$entity->ccId] = $entity;
                } else {
                    $this->collection[$id]->setProperty($rec['property_name'], $rec['property_value']);
                }
            }
        }

        return $this->collection;
    }

    /**
     * Finds cost centres by key
     * It searches by name or billing number
     *
     * @param   string    $key  optional Search key
     * @return  ArrayCollection Returns collection of the CostCentreEntity objects
     */
    public function findByKey($key = null)
    {
        if (is_null($key) || $key === '') {
            return $this->all();
        }

        $collection = new ArrayCollection();

        $ccEntity = new CostCentreEntity();

        $rs = $this->db->Execute("
            SELECT " . $ccEntity->fields('c') . "
            FROM " . $ccEntity->table('c') . "
            WHERE c.`name` LIKE ?
            OR EXISTS (
                SELECT 1 FROM cc_properties cp
                WHERE `cp`.cc_id = `c`.`cc_id`
                AND `cp`.`name` = ? AND `cp`.`value` LIKE ?
            )
        ", [
            '%' . $key . '%',
            CostCentrePropertyEntity::NAME_BILLING_CODE,
            '%' . $key . '%'
        ]);

        while ($rec = $rs->FetchRow()) {
            $item = new CostCentreEntity();
            $item->load($rec);
            $collection->append($item);
        }

        return $collection;
    }

    /**
     * Gets a specified cost centre
     *
     * @param   string    $ccId         The identifier of the cost centre
     * @param   bool      $ignoreCache  optional Should it ignore cache or not
     * @return  CostCentreEntity Returns the CostCentreEntity on success or null if it does not exist.
     */
    public function get($ccId, $ignoreCache = false)
    {
        $all = $this->all(true, $ignoreCache);
        return isset($all[$ccId]) ? $all[$ccId] : null;
    }

    /**
     * Get the list of the environment which are assigned to specified cost centre
     *
     * @param   string                   $ccId  The UUID of the cost centre
     * @return  array     Returns array looks like [env_id => name]
     */
    public function getEnvironmentsList($ccId)
    {
        $ret = [];
        $res = $this->db->Execute("
            SELECT e.id, e.name FROM client_environments e
            JOIN client_environment_properties p ON p.env_id = e.id
            WHERE p.name = ? AND p.value = ?
        ", [
            Scalr_Environment::SETTING_CC_ID,
            $ccId
        ]);

        while ($rec = $res->FetchRow()) {
            $ret[$rec['id']] = $rec['name'];
        }

        return $ret;
    }

    /**
     * Gets the list of the environments which have no association with any cost center.
     *
     * @return   array Returns array looks like [env_id => name]
     */
    public function getUnassignedEnvironments()
    {
        $ret = [];

        //Selects only active environments of active accounts with no cc_id defined
        $rs = $this->db->Execute("
            SELECT ce.id, ce.name
            FROM client_environments ce
            JOIN clients c ON c.id = ce.client_id
            LEFT JOIN client_environment_properties cep ON ce.id = cep.env_id AND cep.name = ?
            WHERE c.status = ? AND ce.status = ?
            AND (cep.`value` IS NULL OR cep.`value` = '')
        ", [
            Scalr_Environment::SETTING_CC_ID,
            Scalr_Account::STATUS_ACTIVE,
            Scalr_Environment::STATUS_ACTIVE,
        ]);

        while ($rec = $rs->FetchRow()) {
            $ret[$rec['id']] = $rec['name'];
        }

        return $ret;
    }
}