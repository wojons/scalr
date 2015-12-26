<?php

namespace Scalr\Service\Aws\Rds\Handler;

use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Rds\AbstractRdsHandler;
use Scalr\Service\Aws\Rds\DataType\CreateDBClusterRequestData;
use Scalr\Service\Aws\Rds\DataType\DBClusterData;
use Scalr\Service\Aws\Rds\DataType\DBClusterList;
use Scalr\Service\Aws\Rds\DataType\ModifyDBClusterRequestData;
use Scalr\Service\Aws\Rds\DataType\RestoreDBClusterFromSnapshotRequestData;
use Scalr\Service\Aws\RdsException;

/**
 * Amazon RDS DbClusterHandler
 *
 * @author N.V.
 */
class DbClusterHandler extends AbstractRdsHandler
{

    /**
     * Gets ClusterData object from the EntityManager.
     *
     * You should be aware of the fact that the entity manager is turned off by default.
     *
     * @param   string  $dBClusterIdentifier
     *
     * @return  DBClusterData|null Returns DBClusterData if it does exist in the cache or NULL otherwise.
     */
    public function get($dBClusterIdentifier)
    {
        return $this->getRds()->getEntityManager()->getRepository('Rds:DBCluster')->find($dBClusterIdentifier);
    }

    /**
     * CreateDBCluster action
     *
     * Creates a new DB cluster.
     *
     * @param   CreateDBClusterRequestData $request Create DB Cluster request object
     *
     * @return  DBClusterData  Returns created DBInstance
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function create(CreateDBClusterRequestData $request)
    {
        return $this->getRds()->getApiHandler()->createDBCluster($request);
    }

    /**
     * DescribeDBClusters action
     *
     * Returns information about provisioned RDS instances. This API supports pagination
     *
     * @param   string          $dbClusterIdentifier  optional The user-specified cluster identifier.
     * @param   string          $marker               optional The response includes only records beyond the marker.
     * @param   int             $maxRecords           optional The maximum number of records to include in the response.
     *
     * @return  DBClusterList  Returns the list of DB Clusters
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describe($dbClusterIdentifier = null, $marker = null, $maxRecords = null)
    {
        return $this->getRds()->getApiHandler()->describeDBClusters($dbClusterIdentifier, $marker, $maxRecords);
    }

    /**
     * ModifyDBCluster action
     *
     * Modify settings for a DB Cluster.
     *
     * @param   ModifyDBClusterRequestData  $request    Modify DB Cluster request object
     *
     * @return  DBClusterData  Returns modified DBCluster
     *
     * @throws  RdsException
     */
    public function modify(ModifyDBClusterRequestData $request)
    {
        return $this->getRds()->getApiHandler()->modifyDBCluster($request);
    }

    /**
     * DeleteDBCluster action
     *
     * The DeleteDBCluster action deletes a previously provisioned DB cluster.
     * A successful response from the web service indicates the request was
     * received correctly. When you delete a DB cluster, all automated backups
     * for that DB cluster are deleted and cannot be recovered. Manual DB cluster
     * snapshots of the DB cluster to be deleted are not deleted.
     *
     * @param   string       $dBClusterIdentifier                The DB Cluster identifier for the DB Instance to be deleted.
     * @param   bool         $skipFinalSnapshot         optional Determines whether a final DB Snapshot is created before the DB Cluster is deleted
     * @param   string       $finalDBSnapshotIdentifier optional The DBSnapshotIdentifier of the new DBSnapshot created when SkipFinalSnapshot is set to false
     *
     * @return  DBClusterData  Returns deleted DBCluster
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function delete($dBClusterIdentifier, $skipFinalSnapshot = true, $finalDBSnapshotIdentifier = null)
    {
        return $this->getRds()->getApiHandler()->deleteDBCluster(
            $dBClusterIdentifier, $skipFinalSnapshot, $finalDBSnapshotIdentifier
        );
    }

    /**
     * RestoreDBClusterFromSnapshot action
     *
     * Creates a new DB cluster from a DB cluster snapshot.
     * The target DB cluster is created from the source DB cluster restore point with the same configuration as the original source DB cluster,
     * except that the new DB cluster is created with the default security group.
     *
     * @param   RestoreDBClusterFromSnapshotRequestData $request The request object.
     * @return  DBClusterData Returns DBClusterData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function restoreFromSnapshot(RestoreDBClusterFromSnapshotRequestData $request)
    {
        return $this->getRds()->getApiHandler()->restoreDBClusterFromSnapshot($request);
    }

}