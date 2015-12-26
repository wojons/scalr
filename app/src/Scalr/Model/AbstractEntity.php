<?php

namespace Scalr\Model;

use ADORecordSet_mysqli;
use InvalidArgumentException;
use Scalr\Exception\ModelException;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Model\Collections\EntityIterator;
use Scalr\Model\Loader\Entity;
use Scalr\Model\Loader\Field;
use BadMethodCallException;
use IteratorAggregate;
use Scalr\Model\Type\GeneratedValueTypeInterface;
use Scalr\Model\Mapping\GeneratedValue;

/**
 * AbstractEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (10.10.2013)
 *
 * @property-read string $column{FieldName} Returns column like `table`.`column_name`
 *
 * @method  \Scalr\Model\AbstractEntity findPk()
 *          findPk($keys, $_)
 *          Find record in database by primary key. Loads the record into self.
 *
 * @method  \Scalr\Model\Collections\EntityIterator find()
 *          find(array $criteria = null, array $group = null, array $order = null, int $limit = null, int $offset = null, bool $countRecords = null)
 *          Searches by criteria
 *
 * @method  \Scalr\Model\Collections\EntityIterator all()
 *          all()
 *          Gets all entities stored in DB
 *
 * @method  \Scalr\Model\AbstractEntity findOne()
 *          findOne(array $criteria = null, array $group = null, array $order = null)
 *          Searches one record by given criteria
 *
 * @method  \Scalr\Model\Collections\EntityIterator findBy{FieldName}()
 *          findBy{FieldName}(mixed $fieldValue)
 *          Searches by the specified field
 *
 * @method  \Scalr\Model\AbstractEntity  findOneBy{FieldName}()
 *          findOneBy{FieldName}(mixed $fieldValue)
 *          Searches by the specified field
 *
 * @method  int deleteBy()
 *          deleteBy($keys, $_)
 *          Removes records from database by criteria
 *
 * @method  int deletePk()
 *          deletePk($keys, $_)
 *          Removes record from database by primary key
 *
 * @method  int deleteByFieldName()
 *          deleteByFieldName($keys, $_)
 *          Removes record from database by specified field value
 *
 * @method  int deleteOneBy{FieldName}()
 *          deleteBy{FieldName}($keys, $_)
 *          Removes one record from database by specified field value
 *
 * @method  \Scalr\Model\EntityStatement prepareSaveStatement()
 *          prepareSaveStatement()
 *          Prepares statement to store data in database
 *
 * @method  bool hasUniqueIndex()
 *          hasUniqueIndex()
 *          Checks whether the table has at least one unique index
 *          Returns true if table has unique index
 *
 * @method  string column()
 *          column(string $fieldName, string $tableAlias = null, string $alias = null)
 *          Returns the column as `table`.`column_name`[ AS `alias`] for the specified field.
 *          If the tableAlias is specified it will return `table_alias`.`column_name`[ AS `alias`].
 *          It is also possible to invoke this method as
 *          column{FieldName}(string $tableAlias = null, string $alias = null)
 *
 */
abstract class AbstractEntity extends AbstractGetter implements IteratorAggregate
{
    /**
     * EntityIterator result type
     */
    const RESULT_ENTITY_ITERATOR = 1;

    /**
     * ArrayCollection result type
     */
    const RESULT_ENTITY_COLLECTION = 2;

    /**
     * ADORecordSet_mysqli result type
     */
    const RESULT_RAW = 3;

    /**
     * Default result type
     */
    const DEFAULT_RESULT_TYPE = self::RESULT_ENTITY_ITERATOR;

    /**
     * Query magic property that can be used in the search criteria
     */
    const STMT_FROM = '__FROM__';

    /**
     * Query magic property that can be used in the search criteria
     */
    const STMT_WHERE = '__WHERE__';

    /**
     * Query magic property that adds DISCINCT to statement
     */
    const STMT_DISTINCT = '__DISTINCT__';

    /**
     * @var int
     */
    private $resultType = self::DEFAULT_RESULT_TYPE;

    /**
     * Properties iterator
     *
     * @var EntityPropertiesIterator
     */
    private $iterator;

    /**
     * Database instance
     *
     * @var \ADODB_mysqli
     */
    private $db;

    /**
     * The table name
     *
     * @var string
     */
    private $table;

    /**
     * Misc. cache
     *
     * @var array
     */
    private static $cache = array();

    /**
     * {@inheritdoc}
     * @see IteratorAggregate::getIterator()
     * @return EntityPropertiesIterator Gets EntityPropertiesIterator
     */
    public function getIterator()
    {
        if (!$this->iterator) {
            $class = get_class($this);
            if (!isset(self::$cache[$class]['iterator'])) {
                self::$cache[$class]['iterator'] = new EntityPropertiesIterator($this);
                $this->iterator = self::$cache[$class]['iterator'];
            } else {
                $this->iterator = clone self::$cache[$class]['iterator'];
                $this->iterator->setEntity($this);
            }
        }
        return $this->iterator;
    }

    /**
     * Loads an entity from array or object
     *
     * @param   array|object  $obj        Source data
     * @param   string        $tableAlias optional The table alias which should be threated as column name prefix
     */
    public function load($obj, $tableAlias = null)
    {
        $isObject = is_object($obj);

        $iterator = $this->getIterator()->fields();

        $prefix = $tableAlias ? $tableAlias . '__' : '';

        if ($prefix !== '') {
            //Input comes from the array retrieved from the database query
            foreach ($iterator as $field) {
                /* @var $field \Scalr\Model\Loader\Field */
                $name = $prefix . $field->column->name;

                if (isset($obj[$name])) {
                    $value = $obj[$name];
                } else {
                    $value = null;
                }

                $this->{$field->name} = $field->type->toPhp($value);
            }
        } else {
            foreach ($iterator as $field) {
                $name = $field->name;

                if ($isObject) {
                    $value = isset($obj->$name) ? $obj->$name : null;
                } else if (isset($obj[$field->column->name])) {
                    $value = $obj[$field->column->name];
                } else if (isset($obj[$name])) {
                    $value = $obj[$name];
                } else {
                    $value = null;
                }

                $this->$name = $field->type->toPhp($value);
            }
        }
    }

    /**
     * Finds record by primary key
     *
     * @param   array   $args  The values which is a part of the primary key
     * @return  AbstractEntity|null Loads the entity to intself on success or returns null if nothing found
     * @throws  ModelException
     */
    private function _findPk(array $args = [])
    {
        $iterator = $this->getIterator();
        $pk = $iterator->getPrimaryKey();

        if (empty($pk)) {
            throw new ModelException(sprintf(
                "Primary key has not been defined with @Id tag for %s",
                get_class($this)
            ));
        }

        if (count($args) != count($pk)) {
            throw new InvalidArgumentException(sprintf(
                "The number of arguments passed does not match the primary key fields (%s)",
                join(', ', $pk)
            ));
        }

        $pkValues = array_combine($pk, $args);

        $stmtFields = '';
        $stmtWhere = '';
        $arguments = array();
        foreach ($iterator->fields() as $field) {
            $stmtFields .= ',' . $field->getColumnName();

            if (isset($field->id)) {
                if (!isset($pkValues[$field->name]) && $field->column->nullable) {
                    $stmtWhere .= 'AND ' . $field->getColumnName() . ' IS NULL ';
                } else {
                    $stmtWhere .= 'AND ' . $field->getColumnName() . ' = ' . $field->type->wh() . ' ';
                    $arguments[] = $field->type->toDb($pkValues[$field->name]);
                }
            }
        }

        $stmtFields = substr($stmtFields, 1);
        $stmtWhere = substr($stmtWhere, 4);

        $item = $this->db()->GetRow("
            SELECT {$stmtFields} FROM {$this->table()}
            WHERE {$stmtWhere} LIMIT 1
        ", $arguments);

        if ($item) {
            $this->load($item);
            return $this;
        }

        return null;
    }

    /**
     * Finds one record by given criteria
     *
     * @param    array        $criteria     optional The search criteria.
     * @param    array        $group        optional The group by parameter.
     * @param    array        $order        optional The results order
     * @return   AbstractEntity|null Gets found entity or null if nothing found
     */
    private function _findOne(array $criteria = null, array $group = null, array $order = null)
    {
        $list = $this->_find($criteria, $group, $order, 1);

        switch (true) {
            case $list instanceof EntityIterator:
                if (count($list)) {
                    $list->rewind();
                    $ret = $list->current();
                } else {
                    $ret = null;
                }
                break;

            case $list instanceof ArrayCollection:
                $ret = count($list) ? reset($list) : null;
                break;

            case $list instanceof \ADORecordSet:
                if (($ret = $list->FetchRow()) === false) {
                    $ret = null;
                }
        }

        return $ret;
    }

    /**
     * Gets column name for the specified field
     *
     * @param    string   $fieldName  The name of the field in the Entity
     * @param    string   $tableAlias optional The table alias
     * @param    string   $alias      optional The column alias
     * @return   string   Returns the column as (`table` | `table_alias`).`column_name`[ AS `alias`]
     */
    private function _column($fieldName, $tableAlias = null, $alias = null)
    {
        return $this->getIterator()->getField($fieldName)->getColumnName($tableAlias, $alias);
    }

    /**
     * Finds collection of the values by any key
     *
     * @param    array        $criteria     optional The search criteria.
     * @param    array        $group        optional The group by looks like [property1, ...]
     * @param    array        $order        optional The results order looks like [[property1 => true|false], ...]
     * @param    int          $limit        optional The records limit
     * @param    int          $offset       optional The offset
     * @param    bool         $countRecords optional True to calculate total number of the records without limit
     * @return   ArrayCollection|EntityIterator|ADORecordSet_mysqli Returns collection of the entities. The type
     *           of the result depends on resultType property of the class which can be set as
     *           FooEntity::result(FooEntity::RESULT_ARRAY_COLLECTION)->find()
     */
    private function _find(array $criteria = null, array $group = null, array $order = null, $limit = null, $offset = null, $countRecords = null)
    {
        $class = get_class($this);
        $iterator = $this->getIterator();

        if (is_array($criteria) && array_key_exists(static::STMT_FROM, $criteria)) {
            $stmtFrom = "FROM " . $criteria[static::STMT_FROM];
            unset($criteria[static::STMT_FROM]);
        } else {
            $stmtFrom = "FROM {$this->table()}";
        }

        if (is_array($criteria) && array_key_exists(static::STMT_WHERE, $criteria)) {
            $stmtWhere = "WHERE " . $criteria[static::STMT_WHERE];
            $rawWhere = !empty($criteria[static::STMT_WHERE]);
            unset($criteria[static::STMT_WHERE]);
        } else {
            $stmtWhere = "WHERE";
        }

        if (!empty($criteria)) {
            $built = $this->_buildQuery($criteria);
        }

        if (!empty($group)) {
            $sGroup = '';

            foreach ($group as $col) {
                $field = $iterator->getField($col);

                if (!$field) {
                    throw new InvalidArgumentException(sprintf(
                        "Property %s does not exist in %s",
                        $col, $class
                    ));
                }

                $sGroup .= ', ' . $field->getColumnName();
            }

            $sGroup = $sGroup != '' ? 'GROUP BY ' . substr($sGroup, 2) : '';
        }

        if (!empty($order)) {
            $sOrder = '';

            foreach ($order as $k => $v) {
                $field = $iterator->getField($k);

                if (!$field) {
                    throw new InvalidArgumentException(sprintf(
                        "Property %s does not exist in %s",
                        $k, $class
                    ));
                }

                $sOrder .= ', ' . $field->getColumnName() . ($v ? '' : ' DESC');
            }

            $sOrder = $sOrder != '' ? 'ORDER BY ' . substr($sOrder, 2) : '';
        }

        $bcnt = $countRecords && isset($limit);

        $builtWhere = (!empty($built['where']) ? $built['where'] : '1=1');

        $stmt = "
            SELECT " . ($bcnt ? 'SQL_CALC_FOUND_ROWS ' : '') . (!empty($criteria[static::STMT_DISTINCT]) ? 'DISTINCT ' : '') . $this->fields() . " {$stmtFrom}
            {$stmtWhere} " . (!empty($rawWhere) ? "AND (" . $builtWhere . ")" : $builtWhere) . "
            " . (!empty($sOrder) ? $sOrder : "") . "
            " . (isset($limit) ? "LIMIT " . ($offset ? intval($offset) . ',' : '') . intval($limit) : "") . "
        ";

        $res = $this->db()->Execute($stmt);

        if ($this->resultType === self::RESULT_ENTITY_COLLECTION) {
            $ret = new ArrayCollection();

            while ($item = $res->FetchRow()) {
                $obj = new $class();
                $obj->load($item);
                $ret->append($obj);
                unset($obj);
            }
        } else if ($this->resultType === self::RESULT_ENTITY_ITERATOR) {
            $ret = new EntityIterator($class, $res);
        } else if ($this->resultType === self::RESULT_RAW) {
            $this->resultType = self::DEFAULT_RESULT_TYPE;
            return $res;
        }

        if ($bcnt) {
            $ret->totalNumber = $this->db()->getOne('SELECT FOUND_ROWS()');
        } else if ($countRecords) {
            $ret->totalNumber = $res->RowCount();
        }

        //Restores default result type
        $this->resultType = self::DEFAULT_RESULT_TYPE;

        return $ret;
    }

    /**
     * Removes records from database by criteria
     *
     * @param   array   $criteria       optional The search criteria.
     * @param   int     $limit          optional The records limit
     *
     * @return  int Returns number of affected rows
     */
    private function _delete(array $criteria = null, $limit = null)
    {
        if (is_array($criteria) && array_key_exists(static::STMT_FROM, $criteria)) {
            $stmtFrom = "FROM " . $criteria[static::STMT_FROM];
            unset($criteria[static::STMT_FROM]);
        } else {
            $stmtFrom = "FROM {$this->table()}";
        }

        if (is_array($criteria) && array_key_exists(static::STMT_WHERE, $criteria)) {
            $stmtWhere = "WHERE " . $criteria[static::STMT_WHERE];
            $rawWhere = !empty($criteria[static::STMT_WHERE]);
            unset($criteria[static::STMT_WHERE]);
        } else {
            $stmtWhere = "WHERE";
        }

        if (!empty($criteria)) {
            $built = $this->_buildQuery($criteria);
        }

        $builtWhere = (!empty($built['where']) ? $built['where'] : '');

        $stmt = "
            DELETE {$stmtFrom}
            {$stmtWhere} " . (!empty($rawWhere) ? "AND (" . $builtWhere . ")" : $builtWhere) . "
            " . (isset($limit) ? "LIMIT " . intval($limit) : "") . "
        ";

        $this->db()->Execute($stmt);

        return $this->db()->_affectedrows();
    }

    /**
     * Removes record from database by primary key
     *
     * @param   array   $args   The values which is a part of the primary key
     *
     * @return  int Returns number of affected rows
     *
     * @throws  ModelException
     */
    private function _deletePk(array $args = [])
    {
        $iterator = $this->getIterator();
        $pk = $iterator->getPrimaryKey();

        if (empty($pk)) {
            throw new ModelException(sprintf(
                "Primary key has not been defined with @Id tag for %s",
                get_class($this)
            ));
        }

        if (count($args) != count($pk)) {
            throw new InvalidArgumentException(sprintf(
                "The number of arguments passed does not match the primary key fields (%s)",
                join(', ', $pk)
            ));
        }

        $stmtWhere = [];
        $arguments = [];
        foreach ($pk as $pos => $fieldName) {
            $field = $iterator->getField($fieldName);
            $value = $args[$pos];

            if (!isset($value) && $field->column->nullable) {
                $stmtWhere[] = "{$field->getColumnName()} IS NULL";
            } else {
                $stmtWhere[] = "{$field->getColumnName()} = {$field->type->wh()}";
                $arguments[] = $field->type->toDb($value);
            }
        }

        $stmtWhere = implode(' AND ', $stmtWhere);

        $this->db()->Execute("
            DELETE FROM {$this->table()}
            WHERE {$stmtWhere} LIMIT 1
        ", $arguments);

        return $this->db()->_affectedrows();
    }

    /**
     * Builds query statement
     *
     * @param  array  $criteria     Criteria array
     * @param  string $conjunction  optional Conjucntion
     * @param  string $tableAlias   optional Table alias
     * @return array
     * @throws \InvalidArgumentException
     */
    public function _buildQuery(array $criteria, $conjunction = 'AND', $tableAlias = null)
    {
        $iterator = $this->getIterator();
        $class = get_class($this);

        foreach ([static::STMT_FROM, static::STMT_WHERE, static::STMT_DISTINCT] as $uk) {
            if (array_key_exists($uk, $criteria)) unset($criteria[$uk]);
        }

        $built = ['where' => []];

        $conj = [
            '$and' => 'AND',
            '$or'  => 'OR',
        ];

        $cmp = [
            '$lt'  => '<',
            '$gt'  => '>',
            '$gte' => '>=',
            '$lte' => '<=',
            '$ne'  => '<>',
        ];

        $func = [
            '$in'   => 'IN(%)',
            '$nin'  => 'NOT IN(%)',
            '$like' => 'LIKE(%)',
        ];

        foreach ($criteria as $k => $v) {
            if (!is_array($v)) {
                throw new InvalidArgumentException(sprintf("Array is expected as argument for %s in %s", $k, $class));
            }

            if (isset($conj[$k])) {
                $b = $this->_buildQuery($v, $conj[$k], $tableAlias);
                $built['where'][] = $b['where'];
                continue;
            }

            if (is_numeric($k)) {
                list($k, $v) = each($v);
                if (isset($conj[$k])) {
                    $b = $this->_buildQuery($v, $conj[$k], $tableAlias);
                    $built['where'][] = $b['where'];
                    continue;
                }
            }

            $field = $iterator->getField($k);

            if (empty($field)) {
                throw new InvalidArgumentException(sprintf(
                    'Field "%s" is not defined for object %s', $k, $class
                ));
            };

            if (is_array($v)) {
                foreach ($v as $t => $vv) {
                    if ($t == '$exists') {
                        $built['where'][] = $field->getColumnName($tableAlias) . ((bool)$vv ? ' IS NOT NULL' : ' IS NULL') . ' ';
                    } else if (isset($cmp[$t])) {
                        $built['where'][] = $field->getColumnName($tableAlias) . ' '
                            . (!isset($vv) ? ($t == '$ne' || $t == '$lt' || $t == '$gt' ? 'IS NOT ' : 'IS ') . 'NULL' :
                               $cmp[$t] . ' ' . $this->qstr($field, $vv));
                    } else if (isset($func[$t])) {
                        $tmp = '';
                        foreach ((array) $vv as $inVal) {
                            $tmp .= ', ' . (!isset($inVal) ? 'NULL' : $this->qstr($field, $inVal));
                        }
                        if ($tmp != '') {
                            $built['where'][] = $field->getColumnName($tableAlias) . ' '
                              . str_replace('%', substr($tmp, 2), $func[$t]);
                        }
                    } else {
                        throw new InvalidArgumentException(sprintf(
                            "Comparison function '%s' is not defined", $t
                        ));
                    }
                }
            } else {
                $built['where'][] = $field->getColumnName($tableAlias) . " "
                    . (!isset($v) ? 'IS NULL ' : '= ' . $this->qstr($field, $v));
            }
        }

        if (!empty($built['where'])) {
            $built['where'] = join(" {$conjunction} ", $built['where']);
            if ($conjunction == 'OR') {
                $built['where'] = "(" . $built['where'] . ")";
            }
        } else {
            $built['where'] = '1=1';
        }

        return $built;
    }

    /**
     * Prepares statement to store data in database
     *
     * @return EntityStatement
     *
     * @throws ModelException
     */
    private function _prepareSaveStatement()
    {
        $stmt = new EntityStatement($this->db(), get_class($this));

        /* @var $iterator EntityPropertiesIterator */
        $iterator = $this->getIterator();

        $pk = $iterator->getPrimaryKey();

        if (empty($pk)) {
            throw new ModelException("Primary key has not been defined with @Id tag for " . get_class($this));
        }

        $stmtPk      = [];
        $stmtFields  = [];
        $stmtUpdate  = [];

        $args  = [];
        $pkArgs = [];
        $onDuplicateArgs  = [];

        /* @var $field Field */
        foreach ($iterator->fields() as $field) {
            $stmtFields[] = "{$field->getColumnName()} = {$field->type->wh()}";
            $args[] = $field;

            if (isset($field->id)) {
                //Field takes a part in primary key
                $stmtPk[] = "{$field->getColumnName()} = {$field->type->wh()}";
                $pkArgs[] = $field;
            } else {
                $stmtUpdate[] = "{$field->getColumnName()} = {$field->type->wh()}";
                $onDuplicateArgs[] = $field;
            }
        }

        $stmtFields = implode(", ", $stmtFields);

        $table = static::table();

        if ($this->_hasUniqueIndex()) {
            $stmt->setInsertStatement(
                static::db()->Prepare("INSERT INTO {$table} SET {$stmtFields} "),
                $args
            );
        } else {
            $stmt->setInsertStatement(
                static::db()->Prepare("INSERT INTO {$table} SET {$stmtFields}" . (empty($stmtUpdate) ? '' : (" ON DUPLICATE KEY UPDATE " . implode(", ", $stmtUpdate)))),
                array_merge($args, $onDuplicateArgs)
            );
        }

        if (!empty($stmtPk)) {
            $stmtPk = implode(" AND ", $stmtPk);

            $stmt->setUpdateStatement(
                static::db()->Prepare("UPDATE {$table} SET {$stmtFields} WHERE {$stmtPk} LIMIT 1"),
                array_merge($args, $pkArgs)
            );

            $stmt->setDeleteStatement(
                static::db()->Prepare("DELETE FROM {$table} WHERE {$stmtPk} LIMIT 1"),
                $pkArgs
            );
        }

        return $stmt;
    }

    /**
     * Saves current entity to database
     *
     * @throws  ModelException
     */
    public function save()
    {
        $iterator = $this->getIterator();

        $pk = $iterator->getPrimaryKey();

        if (empty($pk)) {
            throw new ModelException(sprintf("Primary key has not been defined with @Id tag for %s", get_class($this)));
        }

        $stmtPk      = '';
        $stmtFields  = '';
        $stmtUpdate  = '';

        $argumentsPk = [];
        $arguments1  = [];
        $arguments2  = [];

        foreach ($iterator->fields() as $field) {
            if ($this->{$field->name} === null && isset($field->generatedValue)) {
                if ($field->generatedValue->strategy == GeneratedValue::STRATEGY_CUSTOM) {
                    if (!($field->type instanceof GeneratedValueTypeInterface)) {
                        throw new ModelException(sprintf(
                            "Unable to generate value for %s field. Type %s should implement "
                          . "Scalr\\Model\\Type\\GeneratedValueTypeInterface",
                            $field->name, get_class($field->type)
                        ));
                    }
                    $this->{$field->name} = $field->getType()->generateValue($this);
                } else if ($field->generatedValue->strategy == GeneratedValue::STRATEGY_AUTO) {
                    //Generated automatically by mysql
                    if (isset($field->id)) {
                        $postInsertField = $field;
                    }
                    continue;
                } else {
                    throw new ModelException(sprintf(
                        "Type %s has not been implemented for GeneratedValue behaviour.",
                        get_class($field->type)
                    ));
                }
            }

            if (isset($field->id)) {
                //Field takes a part in primary key
                $stmtFields .= ', ' . $field->getColumnName() . ' = ' . $field->type->wh();
                $arguments1[] = $field->type->toDb($this->{$field->name});

                $stmtPk .= ' AND ' . $field->getColumnName() . ' = ' . $field->type->wh();
                $argumentsPk[] = $field->type->toDb($this->{$field->name});
            } else {
                //Field does not take a part in primary key
                if (!isset($this->{$field->name}) && $field->column->nullable) {
                    $stmtFields .= ', ' . $field->getColumnName() . ' = NULL';
                    $stmtUpdate .= ', ' . $field->getColumnName() . ' = NULL';
                } else {
                    $stmtFields .= ', ' . $field->getColumnName() . ' = ' . $field->type->wh();
                    $arguments1[] = $field->type->toDb($this->{$field->name});

                    $stmtUpdate .= ', ' . $field->getColumnName() . ' = ' . $field->type->wh();
                    $arguments2[] = $field->type->toDb($this->{$field->name});
                }
            }
        }

        $stmtFields = substr($stmtFields, 1);

        if ($stmtPk != '') {
            $stmtPk = substr($stmtPk, 4);
        }

        if ($stmtUpdate != '') {
            $stmtUpdate = substr($stmtUpdate, 1);
        }

        if ($this->_hasUniqueIndex() && $stmtPk !== '') {
            //If table has some unique index it should not perform ON DUPLICATE KEY UPDATE clause.
            //We need to do INSERT if the record does not exist or UPDATE otherwise by primary key.

            //If postInsertField is set it indicates that INSERT statement is expected then
            if (!isset($postInsertField)) {
                //Checks if the record with such primary key already exists in database
                $exists = $this->db()->GetOne("SELECT 1 FROM {$this->table()} WHERE " . $stmtPk . " LIMIT 1", $argumentsPk);
            } else {
                //INSERT statement must be used as it's a new record
                $exists = false;
            }

            //Saves record making insert or update
            $this->db()->Execute(
                ($exists ? "UPDATE" : "INSERT") . " "
                . $this->table() . " "
                . "SET " . $stmtFields . " "
                . ($exists ? "WHERE " . $stmtPk . " LIMIT 1" : ""),
                ($exists ? array_merge($arguments1, $argumentsPk) : $arguments1)
            );
        } else {
            $this->db()->Execute("
                INSERT " . $this->table() . "
                SET " . $stmtFields . "
                " . ($stmtUpdate != '' ? "ON DUPLICATE KEY UPDATE " . $stmtUpdate : '') . "
            ", array_merge($arguments1, $arguments2));
        }

        if (isset($postInsertField)) {
            //Set a value of the auto incrementing field into entity
            $this->{$postInsertField->name} = $postInsertField->type->toPhp($this->db()->Insert_ID());
        }
    }


    /**
     * Checks whether the table has at least one unique index
     *
     * @return    boolean Returns true if table has unique index
     */
    private function _hasUniqueIndex()
    {
        $this->_fetchIndexes();

        return self::$cache[get_class($this)]['has_unique'];
    }

    /**
     * Retrieves show indexes info and stores it in the cache
     */
    private function _fetchIndexes()
    {
        $class = get_class($this);

        if (!isset(self::$cache[$class]['show_index'])) {
            //Cols: Table, Non_unique, Key_name, Seq_in_index, Column_name, Collation, Cardinality, Sub_part,
            //      Packed, Null, Index_type, Comment, Index_comment
            self::$cache[$class]['show_index'] = $this->db()->GetAll("SHOW INDEX FROM " . $this->table());

            //Whether table has any unique key
            self::$cache[$class]['has_unique'] = false;

            foreach (self::$cache[$class]['show_index'] as $v) {
                if (!$v['Non_unique'] && $v['Key_name'] !== 'PRIMARY') {
                    self::$cache[$class]['has_unique'] = true;

                    break;
                }
            }
        }
    }

    /**
     * Removes current record from database by primary key
     *
     * @throws ModelException
     */
    public function delete()
    {
        $iterator = $this->getIterator();
        $pk = $iterator->getPrimaryKey();
        if (empty($pk)) {
            throw new ModelException(sprintf("Primary key has not been defined with @Id tag for %s", get_class($this)));
        }

        $stmtWhere = '';
        $arguments = array();
        foreach ($pk as $name) {
            $field = $iterator->getField($name);
            if (!isset($this->$name) && $field->column->nullable) {
                $stmtWhere .= ' AND ' . $field->getColumnName() . ' IS NULL ';
            } else {
                $stmtWhere .= ' AND ' . $field->getColumnName() . ' = ' . $field->type->wh();
                $arguments[] = $field->type->toDb($this->$name);
            }
        }

        if ($stmtWhere != '') {
            $stmtWhere = substr($stmtWhere, 4);
        }

        $this->db()->Execute("DELETE FROM {$this->table()} WHERE " . $stmtWhere . " LIMIT 1", $arguments);
    }

    /**
     * Gets comma separated list of the columns prefixed either with the table name
     * or the specified table alias
     *
     * @param   string    $tableAlias  optional An table alias
     * @param   bool      $prefixed    optional Should the column names be provided with alias prefixed by the tableAlias
     * @return  string    Returns comma separated list of the columns
     */
    public function fields($tableAlias = null, $prefixed = false)
    {
        $columns = '';

        foreach ($this->getIterator()->fields() as $field) {
            $columns .= $field->getColumnName(
                $tableAlias,
                ($tableAlias && $prefixed ? $tableAlias . '__' . $field->column->name : null)
            ) . ', ';
        }

        return $columns !== '' ? substr($columns, 0, -2) : '';
    }

    /**
     * Parses phpdoc comment for entity class
     */
    private function _parseClassDocComment()
    {
        $class = get_class($this);

        $refl = $this->_getReflectionClass();

        $loader = \Scalr::getContainer()->get('model.loader');
        $loader->load($refl);

        if (empty($refl->annotation->table)) {
            throw new ModelException(sprintf('Invalid @Table definition for %s', $class));
        }

        self::$cache[$class]['entity'] = $refl->annotation;
    }

    /**
     * Gets table name which is associated with the entity
     *
     * @param  string  $alias optional The table alias
     * @return string  The name of the database table
     */
    public function table($alias = null)
    {
        if ($this->table === null) {
            $class = get_class($this);
            if (!isset(self::$cache[$class]['entity'])) {
                $this->_parseClassDocComment();
            }
            $this->table = self::$cache[$class]['entity']->table->name;
        }
        return '`' . str_replace('.', '`.`', $this->table) . '`' . (!empty($alias) ? ' `' . $alias . '`' : '');
    }

    /**
     * Gets entity annotation for the class
     *
     * @return Entity Returns entity annotation for the class
     */
    public function getEntityAnnotation()
    {
        if (!isset(self::$cache[get_class($this)]['entity'])) {
            $this->_parseClassDocComment();
        }
        return self::$cache[get_class($this)]['entity'];
    }

    /**
     * Gets database instance associated with the entity
     *
     * @return \ADODB_mysqli  Database connection instance
     * @throws ModelException
     */
    public function db()
    {
        if ($this->db === null) {
            $class = get_class($this);
            if (!isset(self::$cache[$class]['entity'])) {
                $this->_parseClassDocComment();
            }
            $service = self::$cache[$class]['entity']->table->service;

            //Validates service
            if (!in_array($service, array('adodb', 'cadb'))) {
                throw new ModelException(sprintf("Service %s is not allowed", $service));
            }

            $this->db = \Scalr::getContainer()->$service;
        }

        return $this->db;
    }

    public function __get($prop)
    {
        if (strpos($prop, 'column') === 0) {
            $field = lcfirst(substr($prop, 6));

            if (!property_exists($this, $field)) {
                throw new BadMethodCallException(sprintf(
                    'Property "%s" does not exist in the class "%s".',
                    $prop, get_class($this)
                ));
            }

            return $this->_column($field);
        }

        return parent::__get($prop);
    }

    public function __set($prop, $value)
    {
        if (property_exists($this, $prop)) {
            $this->$prop = $value;
            return $this;
        }
        throw new BadMethodCallException(sprintf(
            'Property "%s" does not exist for the class "%s".',
            $prop, get_class($this)
        ));
    }

    public function __unset($prop)
    {
        if (property_exists($this, $prop)) {
            if (isset($this->$prop)) {
                $this->$prop = null;
            }
        }
        throw new BadMethodCallException(sprintf(
            'Property "%s" does not exist for the class "%s".',
            $prop, get_class($this)
        ));
    }

    public static function __callStatic($name, $arguments)
    {
        $entity = new static;

        if (in_array($name, ['findPk', 'find', 'findOne', 'all', 'deletePk', 'prepareSaveStatement', 'hasUniqueIndex'])) {
            return $entity->__call($name, $arguments);
        } else if (in_array($name, ['delete', 'deleteBy'])) {
            return call_user_func_array([$entity, '_delete'], $arguments);
        } else if (strpos($name, 'findBy') === 0 || strpos($name, 'findOneBy') === 0 ||
                   strpos($name, 'deleteBy') === 0 || strpos($name, 'deleteOneBy') === 0) {
            return call_user_func_array([$entity, '__call'], [$name, $arguments]);
        }

        throw new BadMethodCallException(sprintf(
            'Could not find method "%s" for the class "%s".',
            $name, get_called_class()
        ));
    }

    public function __call($method, $args)
    {
        $prefix = substr($method, 0, 3);
        if ($method == 'findPk') {
            return $this->_findPk($args);
        } else if ($method == 'find') {
            return call_user_func_array(array($this, '_find'), $args);
        } else if ($method == 'findOne') {
            return call_user_func_array(array($this, '_findOne'), $args);
        } else if ($method == 'all') {
            return $this->_find();
        } else if ($method == 'deleteBy') {
            return call_user_func_array(array($this, '_delete'), $args);
        } else if ($method == 'deletePk') {
            return $this->_deletePk($args);
        } else if (strpos($method, 'deleteBy') === 0) {
            $field = lcfirst(substr($method, 8));
            return empty($field) ? call_user_func_array([$this, '_delete'], $args) :
                $this->_delete([["{$field}" => (isset($args[0]) ? $args[0] : null)]]);
        } else if (strpos($method, 'deleteOneBy') === 0) {
            $field = lcfirst(substr($method, 8));
            return empty($field) ? call_user_func_array([$this, '_delete'], array_merge($args, [1])) :
                $this->_delete([["{$field}" => (isset($args[0]) ? $args[0] : null)]], 1);
        } else if ($method == 'prepareSaveStatement') {
            return $this->_prepareSaveStatement();
        } else if ($method == 'hasUniqueIndex') {
            return $this->_hasUniqueIndex();
        } else if ($prefix == 'get') {
            $prop = lcfirst(substr($method, 3));
            if (property_exists($this, $prop)) {
                return $this->$prop;
            }
        } else if ($prefix == 'set') {
            $prop = lcfirst(substr($method, 3));
            if (property_exists($this, $prop)) {
                $this->$prop = isset($args[0]) ? $args[0] : null;
                return $this;
            }
        } else if (strpos($method, 'findBy') === 0) {
            $field = lcfirst(substr($method, 6));
            return empty($field) ? call_user_func_array(array($this, '_find'), $args) :
                   $this->_find([["$field" => (isset($args[0]) ? $args[0] : null)]]);
        } else if (strpos($method, 'findOneBy') === 0) {
            $field = lcfirst(substr($method, 9));
            return empty($field) ? call_user_func_array(array($this, '_findOne'), $args) :
                $this->_findOne([["$field" => (isset($args[0]) ? $args[0] : null)]]);
        } else if (strpos($method, 'column') === 0) {
            $field = lcfirst(substr($method, 6));
            if (!empty($field)) array_unshift($args, $field);
            return call_user_func_array([$this, '_column'], $args);
        } else {
            throw new BadMethodCallException(sprintf(
                'Could not find method "%s" for the class "%s".',
                $method, get_class($this)
            ));
        }

        throw new BadMethodCallException(sprintf(
            'Property "%s" does not exist for the class "%s".',
            $prop, get_class($this)
        ));
    }

    /**
     * Escapes value for the field
     *
     * @param   string|\Scalr\Model\Loader\Field  $field The name of the field or the Field object
     * @param   mixed     $value                  optional The value to escape or it will take field's value from the object
     * @return  string    Returns escaped value for the fiel
     */
    public function qstr($field, $value = null)
    {
        if (!($field instanceof Field)) {
            $field = $this->getIterator()->getField((string) $field);
        }

        if (func_num_args() == 1) {
            $value = $this->{$field->name};
        }

        return str_replace('?', $this->db()->qstr($field->type->toDb($value)), $field->type->wh());
    }

    /**
     * Gets the type for the specified field
     *
     * Alias of the $entity->getIterator()->getField('name')->type;
     *
     * @param   string    $field  The name of the field
     * @return  \Scalr\Model\Type\TypeInterface Returns field's type
     */
    public function type($field)
    {
        return $this->getIterator()->getField($field)->type;
    }

    /**
     * Instantiate an object and sets the type of the result
     *
     * @param    int    $resultType  The type of the result
     * @return   \Scalr\Model\AbstractEntity
     */
    public static function result($resultType)
    {
        $entity = new static;

        $resultProp = new \ReflectionProperty(__CLASS__, 'resultType');
        $resultProp->setAccessible(true);
        $resultProp->setValue($entity, $resultType);

        return $entity;
    }
}