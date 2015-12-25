<?php

namespace Scalr\Tests\Fixtures\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;

/**
 * Test entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="test_abstract_entity")
 */
class TestEntity extends AbstractEntity
{

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var int
     */
    public $id;

    /**
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $strId;

    /**
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $intField;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $stringField;

    /**
     * @Column(type="Datetime")
     * @var DateTime
     */
    public $dtField;

    /**
     * @Column(type="UTCDatetime")
     * @var DateTime
     */
    public $utcDtField;

    public function __construct()
    {
        $this->dtField = new DateTime();
        $this->utcDtField = new $this->dtField;
    }
}