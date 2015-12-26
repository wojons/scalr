<?php

namespace Scalr\Model\Entity\Server;

use Scalr\Model\AbstractEntity;

/**
 * Elastic IPs
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 *
 * @Entity
 * @Table(name="elastic_ips")
 */
class ElasticIp extends AbstractEntity
{
    /**
     * The identifier of an IP
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * Farm identifier
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $farmId;

    /**
     * Role name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $roleName;

    /**
     * IP address
     *
     * @Column(type="string",name="ipaddress",nullable=true)
     * @var string
     */
    public $ipAddress;

    /**
     * State of an address
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $state = false;

    /**
     * ID of an instance
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $instanceId;

    /**
     * Account identifier
     *
     * @Column(type="integer",name="clientid",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * Environment identifier
     *
     * @Column(type="integer")
     * @var int
     */
    public $envId;

    /**
     * Index of an IP
     *
     * @Column(type="integer")
     * @var int
     */
    public $instanceIndex = 0;

    /**
     * Farm role identifier
     *
     * @Column(type="integer",name="farm_roleid",nullable=true)
     * @var int
     */
    public $farmRoleId;

    /**
     * UUID of a server
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $serverId;

    /**
     * Allocation identifier
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $allocationId;

    /**
     * Check for presence of public IPs
     *
     * @param string|array $serverId List of server UUIDs to check against
     * @return array List of servers with count of public IPs
     * @throws \InvalidArgumentException
     */
    public static function checkPresenceOfPublicIP($serverId)
    {
        $sql = "SELECT server_id, COUNT(id) AS ipc FROM elastic_ips WHERE ipaddress IS NOT NULL AND server_id ";
        if (is_array($serverId)) {
            $sql .= "IN (" . implode(",", array_fill(0, count($serverId), "?")) . ")";
        } elseif (is_string($serverId)) {
            $sql .= " = ?";
            $serverId = [$serverId];
        } else {
            throw new \InvalidArgumentException("You must specify at least one server");
        }
        $ret = \Scalr::getDb()->Execute($sql . " GROUP BY server_id", $serverId);
        if (empty($ret)) {
            $ret = [];
        }
        return $ret;
    }
}
