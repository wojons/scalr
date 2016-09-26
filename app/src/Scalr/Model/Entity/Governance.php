<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Governance entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="governance")
 */
class Governance extends AbstractEntity
{

    const CATEGORY_GENERAL = 'general';

    const GENERAL_LEASE = 'general.lease';

    /**
     * Environment id
     *
     * @Id
     * @Column(type="integer")
     * @var int
     */
    public $envId;

    /**
     * Category name
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $category;

    /**
     * Name
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Enabled flag
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $enabled;

    /**
     * Value
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $value;
}