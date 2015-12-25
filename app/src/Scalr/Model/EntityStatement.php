<?php

namespace Scalr\Model;

use ADODB_mysqli;
use DomainException;
use InvalidArgumentException;
use Scalr\Exception\ModelException;
use Scalr\Model\Loader\Field;
use Scalr\Model\Mapping\GeneratedValue;
use Scalr\Model\Type\GeneratedValueTypeInterface;

/**
 * Entity Statement
 *
 * Statement to processing multiple queries related to entity.
 * Allows aggregate and perform multiple queries to the database through a prepared queries.
 *
 * @author N.V.
 */
class EntityStatement
{

    const TYPE_INSERT = 'insert';

    const TYPE_UPDATE = 'update';

    const TYPE_DELETE = 'delete';

    /**
     * Static cache for the fields with custom generated values
     *
     * @var Field[][][]
     */
    private static $customs = [];

    /**
     * Database connection handler
     *
     * @var ADODB_mysqli
     */
    private $db;

    /**
     * Internal statement for INSERT queries
     *
     * @var mixed
     */
    private $insertStmt;

    /**
     * Ordered list of fields are INSERT query parameters
     *
     * @var Field[]
     */
    private $insertParams;

    /**
     * Internal statement for UPDATE queries
     *
     * @var mixed
     */
    private $updateStmt;

    /**
     * Ordered list of fields are UPDATE query parameters
     *
     * @var Field[]
     */
    private $updateParams;

    private $deleteStmt;

    private $deleteParams;

    /**
     * Full class name of processed entities
     *
     * @var string
     */
    private $entityClass;

    /**
     * @param   ADODB_mysqli $db          ADODB database handler instance
     * @param   string       $entityClass Full entity class name
     */
    public function __construct($db, $entityClass)
    {
        $this->db = $db;
        $this->entityClass = $entityClass;
    }

    /**
     * Sets internal statement for INSERT queries
     *
     * @param   mixed   $statement  ADODB prepared statement representation
     * @param   Field[] $params     Ordered list of fields are query parameters
     * @return  EntityStatement
     */
    public function setInsertStatement($statement, array $params)
    {
        $this->insertStmt = $statement;
        $this->insertParams = $params;

        return $this;
    }

    /**
     * Sets internal statement for UPDATE queries
     *
     * @param   mixed   $statement  ADODB prepared statement representation
     * @param   Field[] $params     Ordered list of fields are query parameters
     * @return  EntityStatement
     */
    public function setUpdateStatement($statement, array $params)
    {
        $this->updateStmt = $statement;
        $this->updateParams = $params;

        return $this;
    }

    /**
     * Sets internal statement for DELETE queries
     *
     * @param   mixed   $statement  ADODB prepared statement representation
     * @param   Field[] $params     Ordered list of fields are query parameters
     *
     * @return EntityStatement
     */
    public function setDeleteStatement($statement, array $params)
    {
        $this->deleteStmt = $statement;
        $this->deleteParams = $params;

        return $this;
    }

    /**
     * @return bool
     */
    public function inTransaction()
    {
        return (bool) $this->db->transCnt;
    }

    /**
     * Gets database connection handler
     *
     * @return ADODB_mysqli
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Prepares cached data for given operation type.
     * Keeps information about fields with custom generated values in static cache.
     *
     * @param   string  $type   Operation type
     * @param   Field[] $fields Operation fields
     * @return  Field[]
     * @throws  ModelException
     */
    private function prepare($type, array $fields)
    {
        if (!isset(static::$customs[$this->entityClass][$type])) {
            $customs = [];

            foreach ($fields as $field) {
                if (isset($field->generatedValue->strategy) && $field->generatedValue->strategy == GeneratedValue::STRATEGY_CUSTOM) {
                    if (!($field->type instanceof GeneratedValueTypeInterface)) {
                        throw new ModelException(sprintf(
                            "Unable to generate value for {$field->name} field. Type %s should implement Scalr\\Model\\Type\\GeneratedValueTypeInterface",
                            get_class($field->type)
                        ));
                    }

                    $customs[] = $field;
                }
            }

            static::$customs[$this->entityClass][$type] = array_unique($customs, SORT_REGULAR);
        }

        return static::$customs[$this->entityClass][$type];
    }

    /**
     * Execute statement
     *
     * @param   AbstractEntity  $entity          Next entity to saving in database
     * @param   string          $type   optional The statement type (see EntityStatement::TYPE_* const)
     *
     * @return  EntityStatement
     * @throws  ModelException
     */
    public function execute(AbstractEntity $entity , $type = null)
    {
        if (!$entity instanceof $this->entityClass) {
            throw new InvalidArgumentException("This statement processes only '{$this->entityClass}' entities!");
        }

        /* @var $entity AbstractEntity */

        $params = [];

        $auto = $entity->getIterator()->getAutogenerated();

        if (empty($type)) {
            $type = (isset($auto) && !empty($entity->{$auto->name})) ? static::TYPE_UPDATE : static::TYPE_INSERT;
        }

        switch ($type) {
            case static::TYPE_UPDATE:
                $stmt = $this->updateStmt;
                $fields = $this->updateParams;
                break;

            case static::TYPE_INSERT:
                $stmt = $this->insertStmt;
                $fields = $this->insertParams;
                break;

            case static::TYPE_DELETE:
                $stmt = $this->deleteStmt;
                $fields = $this->deleteParams;
                break;

            default:
                throw new DomainException("Unknown statement type {$type}");
        }

        foreach ($this->prepare($type, $fields) as $field) {
            if (empty($entity->{$field->name})) {
                $entity->{$field->name} = $field->type->generateValue($entity);
            }
        }

        foreach ($fields as $field) {
            $params[] = $field->type->toDb($entity->{$field->name});
        }

        $this->db->Execute($stmt, $params);

        if (isset($auto)) {
            $entity->{$auto->name} = $auto->type->toPhp($this->db->Insert_ID());
        }

        return $this;
    }

    /**
     * Execute statement and create appropriate entity
     *
     * @param array $data Entity data
     *
     * @return AbstractEntity
     */
    public function executeRaw(array $data)
    {
        /* @var $entity AbstractEntity */
        $entity = new $this->entityClass();

        /* @var $field Field */
        foreach ($entity->getIterator() as $field) {
            $name = $field->name;

            if (isset($data[$name])) {
                $entity->{$name} = $data[$name];
            }
        }

        $this->execute($entity);

        return $entity;
    }

    /**
     * Starts transaction if database session currently not in transaction
     *
     * @return EntityStatement
     */
    public function start()
    {
        if (!$this->inTransaction()) {
            $this->db->BeginTrans();
        }

        return $this;
    }

    /**
     * Commits transaction if database session in transaction
     *
     * @return EntityStatement
     */
    public function commit()
    {
        if ($this->inTransaction()) {
            $this->db->CommitTrans();
        }

        return $this;
    }

    /**
     * Rollbacks transaction if database session in transaction
     *
     * @return EntityStatement
     */
    public function rollback()
    {
        if ($this->inTransaction()) {
            $this->db->RollbackTrans();
        }

        return $this;
    }
}