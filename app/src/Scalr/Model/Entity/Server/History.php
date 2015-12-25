<?php

namespace Scalr\Model\Entity\Server;

use DateTime;
use Scalr\Model\AbstractEntity;

/**
 * Server History entity
 *
 * @author      Vlad Dobrovolskiy    <v.dobrovolskiy@scalr.com>
 *
 * @Entity
 * @Table(name="servers_history")
 */
class History extends AbstractEntity
{
    /**
     * Account identifier
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $clientId;

    /**
     * Server UUID
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $serverId;

    /**
     * Server cloud id
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $cloudServerId;

    /**
     * Server cloud location
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $cloudLocation;

    /**
     * Project identifier (UUID)
     *
     * @Column(type="uuid")
     * @var string
     */
    public $projectId;

    /**
     * Cost centre identifier (UUID)
     *
     * @Column(type="uuid")
     * @var string
     */
    public $ccId;

    /**
     * Instance type name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $instanceTypeName;

    /**
     * Launch time
     *
     * @Column(type="datetime",name="dtlaunched",nullable=true)
     * @var DateTime
     */
    public $launched;

    /**
     * Termination time
     *
     * @Column(type="datetime",name="dtterminated",nullable=true)
     * @var DateTime
     */
    public $terminated;

    /**
     * Launch reason id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $launchReasonId;

    /**
     * Launch reason
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $launchReason;

    /**
     * Termination reason id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $terminateReasonId;

    /**
     * Terminate reason
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $terminateReason;

    /**
     * Server platform
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $platform;

    /**
     * Server OS type
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $osType;

    /**
     * Type
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $type;

    /**
     * Environment identifier
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

    /**
     * Role id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $roleId;

    /**
     * Farm identifier
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $farmId;

    /**
     * Identifier for the role in a farm
     *
     * @Column(type="integer",name="farm_roleid",nullable=true)
     * @var int
     */
    public $farmRoleId;

    /**
     * User id who created the farm
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $farmCreatedById;

    /**
     * Server index
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $serverIndex;

    /**
     * Scu used
     *
     * @Column(type="decimal",precision=11,scale=2,nullable=true)
     * @var float
     */
    public $scuUsed = .0;

    /**
     * Scu reported
     *
     * @Column(type="decimal",precision=11,scale=2,nullable=true)
     * @var float
     */
    public $scuReported = .0;

    /**
     * Scu updated
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $scuUpdated = 0;

    /**
     * Scu collecting
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $scuCollecting = 0;

    /**
     * Marks server as launched.
     *
     * @param   string    $reason  The reason
     * @param   integer   $reasonId
     */
    public function markAsLaunched($reason, $reasonId)
    {
        $this->launchReason = $reason;
        $this->launchReasonId = $reasonId;
        $this->launched = new DateTime();
        $this->save();
    }

    /**
     * Marks server as terminated
     *
     * @param   string      $reason            The reason
     * @param   integer     $reasonId
     */
    public function markAsTerminated($reason, $reasonId)
    {
        $this->terminateReason = $reason;
        $this->terminateReasonId = $reasonId;
        $this->save();
    }

    /**
     * Set the date when server is said to have been terminated in the cloud.
     */
    public function setTerminated()
    {
        if (empty($this->terminated)) {
            $this->terminated = new DateTime();
            $this->save();
        }
    }

}
