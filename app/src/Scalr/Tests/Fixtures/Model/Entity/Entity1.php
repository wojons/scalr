<?php
namespace Scalr\Tests\Fixtures\Model\Entity;

/**
 * Entity1
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (07.03.2014)
 * @Entity
 * @Table(name="prices")
 */
class Entity1 extends \Scalr\Model\AbstractEntity
{

    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     * @var integer
     */
    public $priceId;

    /**
     * @Id
     * @var string
     */
    public $instanceType;

    /**
     * @Id
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $os;

    /**
     * @var string
     */
    public $name;

    /**
     * @Column(type="decimal", precision=9, scale=6)
     * @var float
     */
    public $cost;
}