<?php
namespace Scalr\Model\Entity\InformationSchema;

use Scalr\Model\AbstractEntity;

/**
 * Information Schema column entity
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0.0 (29.08.2014)
 *
 * @Entity
 * @Table(name="information_schema.columns")
 */
class ColumnEntity extends AbstractEntity
{

    /**
     * @var string
     */
    public $tableCatalog;

    /**
     * @var string
     */
    public $tableSchema;

    /**
     * @var string
     */
    public $tableName;

    /**
     * @var string
     */
    public $columnName;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $ordinalPosition;

    /**
     * @var string
     */
    public $columnDefault;

    /**
     * @var string
     */
    public $isNullable;

    /**
     * @var string
     */
    public $dataType;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $characterMaximumLength;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $characterOctetLength;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $numericPrecision;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $numericScale;

    /**
     * @var string
     */
    public $characterSetName;

    /**
     * @var string
     */
    public $collationName;

    /**
     * @var string
     */
    public $columnType;

    /**
     * @var string
     */
    public $columnKey;

    /**
     * @var string
     */
    public $extra;

    /**
     * @var string
     */
    public $privileges;

    /**
     * @var string
     */
    public $columnComment;
}