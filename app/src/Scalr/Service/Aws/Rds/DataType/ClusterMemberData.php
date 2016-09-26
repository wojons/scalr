<?php

namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * ClusterMember Data
 *
 * @author N.V.
 */
class ClusterMemberData extends AbstractRdsDataType
{

    /**
     * The instance identifier for this member of the DB cluster.
     *
     * @var string
     */
    public $dBInstanceIdentifier;

    /**
     * Value that is true if the cluster member is the primary instance for the DB cluster and false otherwise.
     *
     * @var bool
     */
    public $isClusterWriter;

    /**
     * The status of the DB cluster parameter group for this member of the DB cluster.
     *
     * @var string
     */
    public $dBClusterParameterGroupStatus;

    /**
     * ClusterMemberData
     *
     * @param   string  $dBInstanceIdentifier           optional The instance identifier for this member of the DB cluster.
     * @param   bool    $isClusterWriter                optional Is the primary instance for the DB cluster.
     * @param   string  $dBClusterParameterGroupStatus  optional The status of the DB cluster parameter group for this member of the DB cluster.
     */
    public function __construct($dBInstanceIdentifier = null, $isClusterWriter = null, $dBClusterParameterGroupStatus = null)
    {
        parent::__construct();

        $this->dBInstanceIdentifier = $dBInstanceIdentifier;
        $this->isClusterWriter = $isClusterWriter;
        $this->dBClusterParameterGroupStatus = $dBClusterParameterGroupStatus;
    }
}