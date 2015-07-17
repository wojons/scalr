<?php

namespace Scalr\Model\Entity;

use Scalr\DataType\AccessPermissionsInterface;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\AbstractEntity;

/**
 * Abstract Orchestration Rule
 *
 * @author N.V.
 */
abstract class OrchestrationRule extends AbstractEntity
{

    /**
     * Identifier
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     *
     * @var int
     */
    public $id;

    /**
     * Event name
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $eventName;

    /**
     * Target
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $target;

    /**
     * Script Id
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $scriptId;

    /**
     * Script version
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $version;

    /**
     * How long should Scalr wait before aborting the execution of this Orchestration Rule.
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $timeout;

    /**
     * Blocking flag
     *
     * @Column(type="boolean")
     *
     * @var bool
     */
    public $issync;

    /**
     * Script parameters
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $params;

    /**
     * When should this Orchestration Rule execute relative to other Orchestration Rules that use the same triggeringEvent.
     * Default is relative to existing Rules.
     *
     * @Column(type="integer")
     *
     * @var int
     */
    public $orderIndex;

    /**
     * Script path
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $scriptPath;

    /**
     * Run as ...
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $runAs;

    /**
     * Script type
     *
     * @Column(type="string")
     *
     * @var string
     */
    public $scriptType;
}