<?php
namespace Scalr\Model\Entity\InformationSchema;

use Scalr\Model\AbstractEntity;

/**
 * Information Schema table entity
 *
 * @author   Vlad Dobrovolskiy <v.dobrovolskiy@scalr.com>
 * @since    5.0.0 (23.02.2015)
 *
 * @Entity
 * @Table(name="information_schema.tables")
 */
class TableEntity extends AbstractEntity
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
    public $tableType;

    /**
     * @var string
     */
    public $engine;

    /**
     * @var string
     */
    public $version;

    /**
     * @var string
     */
    public $rowFormat;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $tableRows;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $avgRowLength;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $dataLength;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $maxDataLength;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $indexLength;

    /**
     * @var string
     */
    public $dataFree;

    /**
     * @var string
     */
    public $autoIncrement;

    /**
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $createTime;

    /**
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $updateTime;

    /**
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $checkTime;

    /**
     * @var string
     */
    public $tableCollation;

    /**
     * @var string
     */
    public $checksum;

    /**
     * @var string
     */
    public $createOptions;

    /**
     * @var string
     */
    public $tableComment;

}