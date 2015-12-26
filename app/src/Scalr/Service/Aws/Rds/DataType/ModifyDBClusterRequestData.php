<?php

namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * ModifyDBClusterRequestData
 *
 * @author N.V.
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $vpcSecurityGroupIds
 *           A list of VPC security groups that the DB cluster will belong to.
 */
class ModifyDBClusterRequestData extends AbstractRdsDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('vpcSecurityGroupIds');

    /**
     * A value that specifies whether the modifications in this request
     * and any pending modifications are asynchronously applied as soon as possible,
     * regardless of the PreferredMaintenanceWindow setting for the DB cluster.
     *
     * If this parameter is set to false, changes to the DB cluster are applied
     * during the next maintenance window.
     *
     * @var bool
     */
    public $applyImmediately = false;

    /**
     * The number of days for which automated backups are retained. Setting this parameter to a positive number enables backups. Setting this parameter to 0 disables automated backups.
     *
     * Constraints:
     *  Must be a value from 0 to 35
     *
     * @var int
     */
    public $backupRetentionPeriod = 1;

    /**
     * The DB cluster identifier for the cluster being modified. This parameter is not case-sensitive.
     *
     * Constraints:
     *   Must be the identifier for an existing DB cluster.
     *   Must contain from 1 to 63 alphanumeric characters or hyphens.
     *   First character must be a letter.
     *   Cannot end with a hyphen or contain two consecutive hyphens.
     *
     * @var string
     */
    public $dBClusterIdentifier;

    /**
     * The name of the DB cluster parameter group to use for the DB cluster.
     *
     * @var string
     */
    public $dBClusterParameterGroupName;

    /**
     * The new password for the master database user.
     *
     * Constraints:
     *  Must contain from 8 to 41 characters.
     *  This password can contain any printable ASCII character except "/", """, or "@".
     *
     * @var string
     */
    public $masterUserPassword;

    /**
     * The new DB cluster identifier for the DB cluster when renaming a DB cluster. This value is stored as a lowercase string.
     *
     * Constraints:
     *  Must contain from 1 to 63 alphanumeric characters or hyphens
     *  First character must be a letter
     *  Cannot end with a hyphen or contain two consecutive hyphens
     *
     * @var string
     */
    public $newDBClusterIdentifier;

    /**
     * A value that indicates that the DB cluster should be associated with
     * the specified option group. Changing this parameter does not result
     * in an outage except in the following case, and the change is applied
     * during the next maintenance window unless the ApplyImmediately parameter
     * is set to true for this request. If the parameter change results in an
     * option group that enables OEM, this change can cause a brief (sub-second)
     * period during which new connections are rejected but existing connections
     * are not interrupted.
     *
     * Permanent options cannot be removed from an option group.
     * The option group cannot be removed from a DB cluster once it is associated
     * with a DB cluster.
     *
     * @var string
     */
    public $optionGroupName;

    /**
     * The port number on which the DB cluster accepts connections.
     *
     * Constraints: Value must be 1150-65535
     *
     * @var int
     */
    public $port;

    /**
     * The daily time range during which automated backups are created if automated backups
     * are enabled, using the BackupRetentionPeriod parameter.
     *
     * Default:
     *  A 30-minute window selected at random from an 8-hour block of time per region.
     *  To see the time blocks available, see Adjusting the Preferred Maintenance Window
     *  in the Amazon RDS User Guide.
     *
     * Constraints:
     *  Must be in the format hh24:mi-hh24:mi.
     *  Times should be in Universal Coordinated Time (UTC).
     *  Must not conflict with the preferred maintenance window.
     *  Must be at least 30 minutes.
     *
     * @var string
     */
    public $preferredBackupWindow;

    /**
     * The weekly time range during which system maintenance can occur, in Universal Coordinated Time (UTC).
     *
     * Format: ddd:hh24:mi-ddd:hh24:mi
     *
     * Default: A 30-minute window selected at random from an 8-hour block of time per region, occurring on a random day of the week. To see the time blocks available, see Adjusting the Preferred Maintenance Window in the Amazon RDS User Guide.
     *
     * Valid Days: Mon, Tue, Wed, Thu, Fri, Sat, Sun
     *
     * Constraints: Minimum 30-minute window.
     *
     * @var string
     */
    public $preferredMaintenanceWindow;

    /**
     * Constructor
     *
     * @param   string  $dBClusterIdentifier A user-supplied database identifier
     */
    public function __construct($dBClusterIdentifier)
    {
        parent::__construct();
        $this->dBClusterIdentifier = (string) $dBClusterIdentifier;
    }

    /**
     * Sets VpcSecurityGroupIds list
     *
     * @param   ListDataType|array|string   $vpcSecurityGroupIds A list of EC2 VPC Security Groups to associate with this DB Cluster.
     *
     * @return  ModifyDBClusterRequestData
     */
    public function setVpcSecurityGroupIds($vpcSecurityGroupIds = null)
    {
        if ($vpcSecurityGroupIds !== null && !($vpcSecurityGroupIds instanceof ListDataType)) {
            $vpcSecurityGroupIds = new ListDataType($vpcSecurityGroupIds);
        }
        return $this->__call(__FUNCTION__, array($vpcSecurityGroupIds));
    }
}