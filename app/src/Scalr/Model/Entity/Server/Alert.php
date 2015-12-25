<?php

namespace Scalr\Model\Entity\Server;

use Scalr\Model\AbstractEntity;

/**
 * Alerts
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 *
 * @Entity
 * @Table(name="server_alerts")
 */
class Alert extends AbstractEntity
{

    const METRIC_SCALARIZR_CONNECTIVITY            = 10001;
    const METRIC_SCALARIZR_UPD_CLIENT_CONNECTIVITY = 10002;

    const METRIC_AWS_SYSTEM_STATUS   = 20003;
    const METRIC_AWS_INSTANCE_STATUS = 20004;

    const METRIC_SERVICE_MYSQL_BACKUP_FAILED      = 30001;
    const METRIC_SERVICE_MYSQL_BUNDLE_FAILED      = 30002;
    const METRIC_SERVICE_MYSQL_REPLICATION_FAILED = 30003;

    //TODO: Add metrics for other services

    const STATUS_FAILED   = 'failed';
    const STATUS_RESOLVED = 'resolved';

    /**
     * The identifier of an alert
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * Environment identifier
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $envId;

    /**
     * Farm identifier
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $farmId;

    /**
     * Farm role identifier
     *
     * @Column(type="integer",name="farm_roleid",nullable=true)
     * @var int
     */
    public $farmRoleId;

    /**
     * Index of a server
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $serverIndex;

    /**
     * UUID of a server
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $serverId;

    /**
     * Metric
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $metric;

    /**
     * When checked
     *
     * @Column(type="datetime",name="dtlastcheck",nullable=true)
     * @var \DateTime
     */
    public $lastCheck;

    /**
     * When resolved
     *
     * @Column(type="datetime",name="dtsolved",nullable=true)
     * @var \DateTime
     */
    public $resolved;

    /**
     * Details
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $details;

    /**
     * Status (resolved/failed)
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $status;

    /**
     * Check for presence of alerts of specified type
     *
     * @param array|string $serverId          List of server UUIDs to check against
     * @param string $status         optional Filter status
     * @return array List of servers with count of alerts
     * @throws \InvalidArgumentException
     */
    public static function checkPresenceOfAlerts($serverId, $status = self::STATUS_FAILED)
    {
        $sql = "SELECT server_id, COUNT(id) AS alerts FROM server_alerts WHERE server_id ";
        if (is_array($serverId)) {
            $sql .= "IN (" . implode(",", array_fill(0, count($serverId), "?")) . ")";
            $bind = $serverId;
        } elseif (is_string($serverId)) {
            $sql .= " = ?";
            $bind = [$serverId];
        } else {
            throw new \InvalidArgumentException("You must specify at least one server");
        }
        $bind[] = $status;
        $ret = \Scalr::getDb()->Execute($sql . " AND `status` = ? GROUP BY server_id", $bind);
        if (empty($ret)) {
            $ret = [];
        }
        return $ret;
    }
}
