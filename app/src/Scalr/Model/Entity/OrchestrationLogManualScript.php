<?php

namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;

/**
 * Orchestration Log manual scripts extended info
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 *
 * @Entity
 * @Table(name="orchestration_log_manual_scripts")
 */
class OrchestrationLogManualScript extends AbstractEntity
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
     * Orchestration log id
     *
     * @Column(type="integer",nullable=true)
     *
     * @var int
     */
    public $orchestrationLogId;

    /**
     * Log type
     *
     * @Column(type="string")
     * @var string
     */
    public $executionId;

    /**
     * Server id
     *
     * @Column(type="string")
     * @var string
     */
    public $serverId;

    /**
     * The identifier of the User
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $userId;

    /**
     * The email address of the user
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $userEmail;

    /**
     * Date added
     *
     * @Column(type="datetime",nullable=true)
     * @var DateTime
     */
    public $added;

    /**
     * Constructor
     *
     * @param string    $executionId            Execution id
     * @param string    $serverId               Server id
     */
    public function __construct($executionId = null, $serverId = null)
    {
        $this->executionId = $executionId;
        $this->serverId = $serverId;
    }

}