<?php

namespace Scalr\Tests\Fixtures\Model\Entity;

use Scalr\Model\Entity\Setting;

/**
 * TestEntity Settings entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="test_abstract_entity_settings")
 */
class TestEntitySetting extends Setting
{

    /**
     * TestEntity identifier
     *
     * @Id
     * @Column(name="test_entity_id",type="integer")
     * @var int
     */
    public $testEntityId;
}