<?php


namespace Scalr\Model\Entity;

/**
 * FarmRoleScript entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="farm_role_scripts")
 */
class FarmRoleScript extends OrchestrationRule
{

    /**
     * Farm Id
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $farmid;

    /**
     * AMI Id
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $amiId;

    /**
     * Menu item flag
     *
     * @Column(type="boolean")
     *
     * @var bool
     */
    public $ismenuitem;

    /**
     * Farm Role Id
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $farmRoleId;

    /**
     * Debug
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $debug;
}