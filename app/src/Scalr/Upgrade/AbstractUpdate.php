<?php

namespace Scalr\Upgrade;

use DateTime;
use DateTimeZone;
use Scalr\DependencyInjection\Container;
use Scalr\Exception;
use Scalr\Model\Entity\InformationSchema\ColumnEntity;
use Scalr\Model\Entity\InformationSchema\TableEntity;
use Scalr\Upgrade\Entity\AbstractUpgradeEntity;
use Scalr\Upgrade\Entity\MysqlUpgradeEntity;

/**
 * UpdateInterface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (09.10.2013)
 *
 * @property-read string $uuid
 * @property-read string $released
 * @property-read string $description
 * @property-read array  $depends
 * @property-read string $version
 * @property-read string $type
 * @property-read \ADODB_mysqli $db
 * @property-read \Scalr\DependencyInjection\Container $container
 * @property-read \Scalr\Upgrade\Console $console
 * @property-read \SplFileInfo $fileInfo
 */
abstract class AbstractUpdate extends AbstractGetter implements UpdateInterface
{
    /**
     * A UUID is a 16-octet (128-bit) number.
     * UUID is represented by 32 hexadecimal digits, displayed in five groups separated by hyphens,
     * in the form 8-4-4-4-12 for a total of 36 characters (32 alphanumeric characters and four hyphens)
     *
     * For example: 270e9300-e83f-7283-a716-346edd410780
     *
     * @var string
     */
    protected $uuid;

    /**
     * The date and time when this update is issued.
     * This property defines the order which is taken into account when
     * upgrade script performs updates one by one.
     *
     * NOTE! The time is actual for the UTC timezone
     *
     * If this value is null the timestamp will be taken from the class name suffix.
     *
     * For example: 2013-10-09 19:00:12
     *
     * @var string
     */
    protected $released;

    /**
     * A short description of the upgrade
     *
     * @var string
     */
    protected $description;

    /**
     * The list of identifiers of updates this upgrade depends upon.
     * Current upgrade will perform only if all dependant upgrades have already been applied.
     *
     * For example:
     * array(
     *     '270e9300-e83f-7283-a716-346edd410780',
     *     '23dede00-e783-8893-ebba-176867838640'
     * );
     *
     * @var array
     */
    protected $depends = array();

    /**
     * The version of the Scalr which will include this upgrade out of box.
     * All versions of Scalr prior to specified should be affected by this upgrade.
     * Version must be php-standardized.
     *
     * For example: 4.5.0
     *
     * @var string
     */
    protected $version;

    /**
     * The source type of upgrade.
     * Can be either mysql or filesystem.
     * Default value: UpdateInterface::TYPE_MYSQL
     *
     * @var string
     */
    protected $type;

    /**
     * Should upgrade script ingore changing in upgrade content or not.
     *
     * This will prevent re-excecution of the script again if its content is changed.
     *
     * @var boolean
     */
    protected $ignoreChanges = false;

    /**
     * Database instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     * Database target service
     *
     * Scalr database - adodb
     * Analytics database - cadb
     *
     * @var string
     */
    protected $dbservice = 'adodb';

    /**
     * DI Container
     *
     * @var Container
     */
    protected $container;

    /**
     * Console Instance
     *
     * @var  Console
     */
    public $console;

    /**
     * File info of the update class
     *
     * @var \SplFileInfo
     */
    protected $fileInfo;

    /**
     * Upgrade status
     *
     * @var AbstractUpgradeEntity
     */
    private $entity;

    /**
     * Hex value of uuid
     *
     * @var string
     */
    private $uuidhex;

    /**
     * file hash of the update class
     *
     * @var string
     */
    private $hash;


    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::getIgnoreChanges()
     */
    public function getIgnoreChanges()
    {
        return $this->ignoreChanges;
    }

    /**
     * Constructor
     *
     * @param   \SplFileInfo          $fileInfo   The SplFileInfo object of upgrade class
     * @param   \ArrayObject          $collection The collection of the upgrade entities
     * @throws  \Scalr\Exception\UpgradeException
     */
    public function __construct(\SplFileInfo $fileInfo, \ArrayObject $collection)
    {
        $this->container = \Scalr::getContainer();
        $this->db = $this->container->{$this->dbservice};
        $this->console = new Console();
        $this->fileInfo = $fileInfo;
        $this->uuid = strtolower($this->uuid);
        if (empty($this->type)) {
            $this->type = UpdateInterface::TYPE_MYSQL;
        }

        if (!preg_match('/\\\\Update(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', get_class($this), $m)) {
            throw new Exception\UpgradeException(sprintf(
                'Upgrade class name must follow naming conventions and should be "UpdateYYYYMMDDHHiiss", "%s" given.',
                get_class($this)
            ));
        } else if (empty($this->released)) {
            $m[0] = '%s-%s-%s %s:%s:%s';
            $this->released = call_user_func_array('sprintf', $m);
        }

        if (empty($this->uuid) || !preg_match('/^[[:xdigit:]]{8}-([[:xdigit:]]{4}-){3}[[:xdigit:]]{12}$/i', $this->uuid)) {
            throw new Exception\UpgradeException(sprintf('Invalid UUID:"%s" for the update class', $this->uuid));
        }

        $entity = isset($collection[$this->getUuidHex()]) ? $collection[$this->getUuidHex()] : null;
        if (!($entity instanceof AbstractUpgradeEntity)) {
            //Initializes a new entity
            /* @var $entity AbstractUpgradeEntity */
            $entClass = __NAMESPACE__ . '\\Entity\\' . ucfirst($this->type) . 'UpgradeEntity';
            $entity = new $entClass;
            $entity->uuid = $this->getUuidHex();
            $entity->appears = $this->getAppearsDt()->format('Y-m-d H:i:s');
            $entity->applied = null;
            $entity->status = AbstractUpgradeEntity::STATUS_PENDING;
            $entity->hash = $this->getHash();
            $entity->released = $this->getReleaseDt()->format('Y-m-d H:i:s');
        }
        if ($entity instanceof MysqlUpgradeEntity) {
            $entity->setDb($this->db);
        }
        $this->entity = $entity;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::isApplied()
     */
    public function isApplied($stage = null)
    {
        // This method is expected to be overridden
        if ($this instanceof SequenceInterface) {
            $method = 'isApplied' . intval($stage);
            if (method_exists($this, $method)) {
                return $this->$method($stage);
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::validateBefore()
     */
    public function validateBefore($stage = null)
    {
        // This method is expected to be overridden
        if ($this instanceof SequenceInterface) {
            $method = 'validateBefore' . intval($stage);
            if (method_exists($this, $method)) {
                return $this->$method($stage);
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::run()
     */
    public function run($stage = null)
    {
        // This method is expected to be overridden
        if ($this instanceof SequenceInterface) {
            $method = 'run' . intval($stage);

            if (method_exists($this, $method)) {
                return $this->$method($stage);
            } else {
                throw new Exception\UpgradeException(sprintf(
                    'Bad usage. Either protected function %s() should be implemented for class %s '
                  . 'or method %s() should be overridden.',
                    $method, get_class($this), __FUNCTION__
                ));
            }
        }
        throw new Exception\UpgradeException(sprintf(
            'Bad usage. Method %s::%s() must be overridden.',
            get_class($this), __FUNCTION__
        ));
    }

    /**
     * Gets Upgrade Entity
     *
     * @return  \Scalr\Upgrade\Entity\AbstractUpgradeEntity Returns entity
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::getStatus()
     */
    public function getStatus()
    {
        return $this->entity->status;
    }

    /**
     * Gets upgrade script modification time
     *
     * @return   \DateTime Returns upgrade script modification time
     */
    public function getAppearsDt()
    {
        return new \DateTime("@" . $this->fileInfo->getMTime());
    }

    /**
     * Gets release date time
     *
     * @return   \DateTime Returns release data time
     */
    public function getReleaseDt()
    {
        return new \DateTime($this->released, new \DateTimeZone('UTC'));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::setStatus()
     */
    public function setStatus($status)
    {
        $this->entity->status = $status;
        return $this;
    }

    /**
     * Updates appears date to storage
     *
     * @return AbstractUpdate
     */
    public function updateAppears()
    {
        $this->entity->appears = $this->getAppearsDt()->format('Y-m-d H:i:s');

        return $this;
    }

    /**
     * Updates hash
     *
     * @return AbstractUpdate
     */
    public function updateHash()
    {
        $this->entity->hash = $this->getHash();

        return $this;
    }

    /**
     * Updates applied tiem
     *
     * @return AbstractUpdate
     */
    public function updateApplied()
    {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        $this->entity->applied = $dt->format('Y-m-d H:i:s');

        return $this;
    }

    /**
     * Gets UUID as hexadecimal string without hyphens
     *
     * @return   string  Returns UUID without hyphens
     */
    public function getUuidHex()
    {
        if (!$this->uuidhex) {
            $this->uuidhex = self::castUuid($this->uuid);
        }
        return $this->uuidhex;
    }

    /**
     * Gets SHA1 hash of the update file
     *
     * @return   string  Returns hash
     */
    public function getHash()
    {
        if (!$this->hash) {
            $this->hash = sha1_file($this->fileInfo->getRealPath());
        }
        return $this->hash;
    }

    /**
     * Casts UUID to hex
     * @param   string    $uuid  UUID with hyphens
     * @return  string    Returns UUID as hexadecimal string without hyphens
     */
    public static function castUuid($uuid)
    {
        return str_replace('-', '', $uuid);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::getName()
     */
    public function getName()
    {
        return preg_replace('/^.+\\\\(Update[\d]+)$/', '\\1', get_class($this));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::hasTableIndex()
     */
    public function hasTableIndex($table, $index)
    {
        $res = $this->db->getRow("SHOW INDEXES FROM `" . $table . "` WHERE `key_name` = ?", array(
            $index
        ));

        return $res ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see UpdateInterface::hasTableCompatibleIndex()
     */
    public function hasTableCompatibleIndex($table, array $columns, $unique = false)
    {
        $stmt = $this->db->Prepare("SHOW INDEX FROM `{$table}` WHERE `Column_name` = ? AND `Seq_in_index` = ? AND `Non_unique` = ?");

        foreach ($columns as $idx => $column) {
            $entries = $this->db->Execute($stmt, [
                $column,
                $idx,
                $unique ? 0 : 1
            ]);

            $indexes = [];

            foreach ($entries->GetAll() as $entry) {
                $indexes[] = $entry['Key_name'];
            }

            $compatible = empty($compatible) ? $indexes : array_intersect($compatible, $indexes);

            if (empty($compatible)) {
                return false;
            }
        }

        return empty($compatible) ? false : $compatible;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::hasTableColumn()
     */
    public function hasTableColumn($table, $column)
    {
        $res = $this->db->getRow("SHOW COLUMNS FROM `" . $table . "` WHERE `field` = ?", array(
            $column
        ));

        return $res ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::hasTableColumnType()
     */
    public function hasTableColumnType($table, $column, $type, $schema = null)
    {
        $ret = $this->db->GetOne("
            SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS` s
            WHERE s.`TABLE_SCHEMA` = " . (isset($schema) ? $this->db->qstr($schema) : "DATABASE()") . "
            AND s.`TABLE_NAME` = ?
            AND s.`COLUMN_NAME` = ?
            AND s.`COLUMN_TYPE` = ?
            LIMIT 1
        ", array(
            $table,
            $column,
            strtolower($type)
        ));

        return $ret ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::hasTableAutoIncrement()
     */
    public function hasTableAutoIncrement($table, $schema = null)
    {
        $ret = $this->db->GetOne("
            SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS` s
            WHERE s.`TABLE_SCHEMA` = " . (isset($schema) ? $this->db->qstr($schema) : "DATABASE()") . "
            AND s.`TABLE_NAME` = " . $this->db->qstr($table) . "
            AND s.`EXTRA` LIKE '%auto_increment%'
            LIMIT 1
        ");

        return $ret ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::hasTableColumnDefault()
     */
    public function hasTableColumnDefault($table, $column, $default, $schema = null)
    {
        $ret = $this->db->GetOne("
            SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS` s
            WHERE s.`TABLE_SCHEMA` = " . (isset($schema) ? $this->db->qstr($schema) : "DATABASE()") . "
            AND s.`TABLE_NAME` = " . $this->db->qstr($table) . "
            AND s.`COLUMN_NAME` = " . $this->db->qstr($column) . "
            AND s.`COLUMN_DEFAULT` " . ($default === null ? "IS NULL" : "=" . $this->db->qstr($default)) . "
            LIMIT 1
        ");

        return $ret ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::getTableColumnDefinition()
     */
    public function getTableColumnDefinition($table, $column, $schema = null)
    {
        if (!isset($schema)) {
            $schema = $this->db->GetOne("SELECT DATABASE()");
        }

        // Update with the service
        $entity = new ColumnEntity();
        $entity->db = $this->db;

        return $entity->findOne([['tableSchema' => $schema], ['tableName' => $table], ['columnName' => $column]]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::getTableIndex()
     */
    public function getTableIndex($table, $indexName, $schema = null)
    {
        if (!isset($schema)) {
            $schema = $this->db->GetOne("SELECT DATABASE()");
        }

        return $this->db->Execute("SHOW INDEX FROM `{$schema}`.`{$table}` WHERE Key_name = ?", [$indexName]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::getTableDefinition()
     */
    public function getTableDefinition($table, $schema = null)
    {
        if (!isset($schema)) {
            $schema = $this->db->GetOne("SELECT DATABASE()");
        }

        $entity = new TableEntity();
        $entity->db = $this->db;

        return $entity->findOne([['tableSchema' => $schema], ['tableName' => $table]]);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::hasTableForeignKey()
     */
    public function hasTableForeignKey($constraintName, $table, $schema = null)
    {
        $row = $this->db->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS s
            WHERE s.CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND s.TABLE_SCHEMA = " . (isset($schema) ? $this->db->qstr($schema) : "DATABASE()") . "
            AND s.TABLE_NAME = ?
            AND s.CONSTRAINT_NAME = ?
            LIMIT 1
        ", [$table, $constraintName]);

        return isset($row['CONSTRAINT_NAME']) ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::getTableConstraint()
     */
    public function getTableConstraint($constraintName, $table, $schema = null)
    {
        return $this->db->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS s
            WHERE s.CONSTRAINT_SCHEMA = " . (isset($schema) ? $this->db->qstr($schema) : "DATABASE()") . "
            AND s.TABLE_NAME = ?
            AND s.CONSTRAINT_NAME = ?
            LIMIT 1
        ", [$table, $constraintName]);
    }

    /**
     * (non-PHPdoc)
     * @see \Scalr\Upgrade\UpdateInterface::hasTableReferencedColumn()
     */
    public function hasTableReferencedColumn($referencedTable, $referencedColumn, $referencedSchema = null)
    {
        $row = $this->db->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE s
            WHERE s.REFERENCED_TABLE_SCHEMA = " . (isset($referencedSchema) ? $this->db->qstr($referencedSchema) : "DATABASE()") . "
            AND s.REFERENCED_TABLE_NAME = ?
            AND s.REFERENCED_COLUMN_NAME = ?
            LIMIT 1
        ", array(
            $referencedTable, $referencedColumn
        ));

        return isset($row['CONSTRAINT_NAME']) ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::hasDatabase()
     */
    public function hasDatabase($database)
    {
        $ret = $this->db->getOne("SHOW DATABASES LIKE ?", array($database));
        return $ret ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::hasTable()
     */
    public function hasTable($table, $database = null)
    {
        $ret = $this->db->getOne("SHOW TABLES " . ($database ? "FROM `" . $this->db->escape($database) . "` " : "") . "LIKE ?", array($table));
        return $ret ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\UpdateInterface::isRefused()
     */
    public function isRefused()
    {
        return false;
    }

    /**
     * Creates table named $new like $origin if $new is not exists
     *
     * @param string $origin      Source table name
     * @param string $new         New table name
     * @param bool   $dropIfExist optional Drop table named $temporary if it exists
     */
    public function createTableLike($origin, $new, $dropIfExist = false)
    {
        if ($dropIfExist && $this->hasTable($new)) {
            $this->dropTables((array) $new);
        }

        $this->db->Execute("CREATE TABLE IF NOT EXISTS `{$new}` LIKE `{$origin}`");
    }

    /**
     * Applies ALTER statements on specified table
     *
     * @param string   $table   Table name
     * @param string[] $sql     ALTER statements, without "ALTER TABLE table_name"
     */
    public function applyChanges($table, $sql)
    {
        $this->db->Execute("ALTER TABLE `{$table}`\n" . implode(",\n", (array) $sql));
    }

    /**
     * Renames $first table to $backup, $second table to $first
     *
     * @param string $origin Name of table that will be replaced
     * @param string $new    Name of replacement table
     * @param string $backup Backup name
     */
    public function replaceTableWithBackup($origin, $new, $backup)
    {
        $this->db->Execute("RENAME TABLE `{$origin}` TO `{$backup}`, `{$new}` TO `{$origin}`");
    }

    /**
     * Copies data from $source to $target, make and run "INSERT ... SELECT ..." statement
     *
     * @param string          $target               Target table name
     * @param string|string[] $sources              Source tables names
     * @param string[]        $fields      optional Fields names that need to copy
     * @param string          $where       optional WHERE statement
     * @param string          $onDuplicate optional ON DUPLICATE KEY UPDATE statement
     * @param array           $params      optional Query parameters
     */
    public function copyData($target, $sources, $fields = null, $where = '', $onDuplicate = '', array $params = [])
    {
        $fields = $fields === null ? '*' : ('`' . str_replace('.', '`.`', implode('`,`', $fields)) . '`');
        $sources = '`' . str_replace('.', '`.`', implode('`,`', (array) $sources)) . '`';

        $query = "
            INSERT INTO `{$target}`
            " . ($fields == '*' ? '' : "({$fields})") . "
              SELECT
                $fields
              FROM {$sources}
              {$where}
              {$onDuplicate}";

        $this->db->Execute($query, $params);
    }

    /**
     * Drops specified tables
     *
     * @param array $tables             Names of tables to drop
     * @param bool  $ifExists  optional Check that tables exists
     * @param bool  $temporary optional Drop TEMPORARY tables
     *
     * @see http://dev.mysql.com/doc/refman/5.0/en/drop-table.html
     */
    public function dropTables(array $tables, $ifExists = true, $temporary = false)
    {
        $sql = $temporary ? "DROP TEMPORARY TABLE" : "DROP TABLE";

        if ($ifExists) {
            $sql .= " IF EXISTS";
        }

        $names = '`' . implode('`,`', $tables) . '`';

        $this->db->Execute("{$sql} {$names}");
    }
}
