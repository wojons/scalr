<?php

namespace Scalr\Upgrade;

use Scalr\Upgrade\Entity\AbstractUpgradeEntity;
use Scalr\Upgrade\Entity\MysqlUpgradeEntity;
use Scalr\Upgrade\Entity\FilesystemUpgradeEntity;
use Scalr\DependencyInjection\Container;
use Scalr\Exception;
use \DateTime;
use \DateTimeZone;

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
     * Database instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

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
    protected $console;

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
     * Constructor
     *
     * @param   \SplFileInfo          $fileInfo   The SplFileInfo object of upgrade class
     * @param   \ArrayObject          $collection The collection of the upgrade entities
     * @throws  \Scalr\Exception\UpgradeException
     */
    public function __construct(\SplFileInfo $fileInfo, \ArrayObject $collection)
    {
        $this->container = \Scalr::getContainer();
        $this->db = $this->container->adodb;
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

        if (empty($this->uuid) || !preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/i', $this->uuid)) {
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
            'Bad usage. Method %s() must be overridden.',
            $method, get_class($this), __FUNCTION__
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
     * @see Scalr\Upgrade.UpdateInterface::hasTableForeignKey()
     */
    public function hasTableForeignKey($constraintName, $table, $schema = null)
    {
        $schema = $schema ?: $this->container->config('scalr.connections.mysql.name');
        $row = $this->db->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE INFORMATION_SCHEMA.TABLE_CONSTRAINTS.CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_SCHEMA = ?
            AND INFORMATION_SCHEMA.TABLE_CONSTRAINTS.TABLE_NAME = ?
            AND CONSTRAINT_NAME = ?
            LIMIT 1
        ", array(
            $schema, $table, $constraintName
        ));

        return isset($row['CONSTRAINT_NAME']) ? true : false;
    }

    /**
     * (non-PHPdoc)
     * @see \Scalr\Upgrade\UpdateInterface::hasTableReferencedColumn()
     */
    public function hasTableReferencedColumn($referencedTable, $referencedColumn, $referencedSchema = null)
    {
        $referencedSchema = $referencedSchema ?: $this->container->config('scalr.connections.mysql.name');
        $row = $this->db->GetRow("
            SELECT * FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = ?
            AND REFERENCED_TABLE_NAME = ?
            AND REFERENCED_COLUMN_NAME = ?
            LIMIT 1
        ", array(
            $referencedSchema, $referencedTable, $referencedColumn
        ));

        return isset($row['CONSTRAINT_NAME']) ? true : false;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.UpdateInterface::hasTable()
     */
    public function hasTable($table)
    {
        $ret = $this->db->getOne("SHOW TABLES LIKE ?", array($table));
        return $ret ? true : false;
    }
}
