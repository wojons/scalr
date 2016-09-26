<?php
namespace Scalr\Stats\CostAnalytics\Entity;

/**
 * TagEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (27.01.2014)
 * @Entity
 * @Table(name="tags",service="cadb")
 */
class TagEntity extends \Scalr\Model\AbstractEntity
{
    const TAG_ID_ENVIRONMENT = 1;

    const TAG_ID_PLATFORM = 2;

    const TAG_ID_ROLE = 3;
    const TAG_ID_ROLE_BEHAVIOR = 7;

    const TAG_ID_FARM = 4;
    const TAG_ID_FARM_OWNER = 10;
    const TAG_ID_FARM_ROLE = 5;

    const TAG_ID_USER = 6;

    const TAG_ID_COST_CENTRE = 8;

    const TAG_ID_PROJECT = 9;

    /**
     * Unique identifier of the tag
     *
     * @Id
     * @var int
     */
    public $tagId;

    /**
     * The name of the tag
     *
     * @var string
     */
    public $name;
}