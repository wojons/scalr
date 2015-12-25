<?php

namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Farm Role Scripting Target entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="farm_role_scripting_targets")
 */
class FarmRoleScriptingTarget extends AbstractEntity
{

    /**
     * Target id
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     *
     * @var int
     */
    public $id;

    /**
     * Farm role orchestration rule identifier
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $farmRoleScriptId;

    /**
     * Target type
     *
     * @see Script::TARGET_FARMROLES
     * @see Script::TARGET_BEHAVIORS
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $targetType;

    /**
     * Target identifier
     *
     * @column(type="string")
     *
     * @var string
     */
    public $target;
}