<?php
namespace Scalr\Model\Entity;

/**
 * ServerTerminationError entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0.1 (19.11.2014)
 * @Entity
 * @Table(name="server_termination_errors")
 */
class ServerTerminationError extends AbstractSettingEntity
{

    /**
     * The identifier of the server
     *
     * @Id
     * @Column(type="uuidString")
     * @var string
     */
    public $serverId;

    /**
     * The time after which it should be revalidated again
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $retryAfter;

    /**
     * The number of attempts
     *
     * @Column(type="integer")
     * @var int
     */
    public $attempts;

    /**
     * Last error message
     *
     * @Column(type="string")
     * @var string
     */
    public $lastError;

    /**
     * Constructor
     *
     * @param   string $serverId  optional The identifier of the server
     * @param   int    $attempts  optional The number of unsuccessful attempts
     * @param   string $lastError optional The last error message
     */
    public function __construct($serverId = null, $attempts = null, $lastError = null)
    {
        $this->serverId = $serverId;
        $this->retryAfter = new \DateTime("+30 minutes");
        $this->attempts = $attempts === null ? 1 : $attempts;
        $this->lastError = $lastError !== null ? $lastError : '';
    }
}