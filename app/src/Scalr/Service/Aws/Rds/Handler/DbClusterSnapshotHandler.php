<?php

namespace Scalr\Service\Aws\Rds\Handler;

use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Rds\AbstractRdsHandler;
use Scalr\Service\Aws\Rds\DataType\DBClusterSnapshotData;
use Scalr\Service\Aws\Rds\DataType\DBClusterSnapshotList;
use Scalr\Service\Aws\Rds\DataType\TagsList;
use Scalr\Service\Aws\RdsException;

/**
 * Amazon RDS DbClusterSnapshotHandler
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     07.10.2015
 */
class DbClusterSnapshotHandler extends AbstractRdsHandler
{
    /**
     * CreateDBClusterSnapshot action
     *
     * Creates a new DB cluster snapshot.
     *
     * @param   string          $dbClusterIdentifier            The identifier of the DB cluster to create a snapshot for. This parameter is not case-sensitive.
     * @param   string          $dbClusterSnapshotIdentifier    The identifier of the DB cluster snapshot. This parameter is stored as a lowercase string.
     * @param   TagsList|array  $tags                           optional The tags to be assigned to the DB cluster snapshot.
     *
     * @return  DBClusterSnapshotData  Returns created DB cluster snapshot
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function create($dbClusterIdentifier, $dbClusterSnapshotIdentifier, $tags = null)
    {
        if (!($tags instanceof TagsList)) {
            $tags = new TagsList($tags);
        }

        return $this->getRds()->getApiHandler()->createDBClusterSnapshot($dbClusterIdentifier, $dbClusterSnapshotIdentifier, $tags);
    }

    /**
     * CopyDBClusterSnapshot action
     *
     * Creates a new DB cluster snapshot.
     *
     * @param   string          $sourceDbClusterSnapshotIdentifier  The identifier of the DB cluster snapshot to copy. This parameter is not case-sensitive.
     * @param   string          $targetDbClusterSnapshotIdentifier  The identifier of the new DB cluster snapshot to create from the source DB cluster snapshot. This parameter is not case-sensitive.
     * @param   TagsList|array  $tags                               optional A list of tags.
     *
     * @return  DBClusterSnapshotData  Returns created DB cluster snapshot
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function copy($sourceDbClusterSnapshotIdentifier, $targetDbClusterSnapshotIdentifier, $tags = null)
    {
        if (!($tags instanceof TagsList)) {
            $tags = new TagsList($tags);
        }

        return $this->getRds()->getApiHandler()->copyDBClusterSnapshot($sourceDbClusterSnapshotIdentifier, $targetDbClusterSnapshotIdentifier, $tags);
    }

    /**
     * DescribeDBClusterSnapshots action
     *
     * Returns information about DB cluster snapshots. This API supports pagination
     *
     * @param   string          $dbClusterIdentifier            optional A DB cluster identifier to retrieve the list of DB cluster snapshots for.
     *                                                          This parameter cannot be used in conjunction with the DBClusterSnapshotIdentifier parameter.
     *                                                          This parameter is not case-sensitive.
     *
     * @param   string          $dbClusterSnapshotIdentifier    optional A specific DB cluster snapshot identifier to describe.
     *                                                          This parameter cannot be used in conjunction with the DBClusterIdentifier parameter.
     *                                                          This value is stored as a lowercase string.
     *
     * @param   string          $snapshotType                   optional The type of DB cluster snapshots that will be returned.
     *                                                          Values can be automated or manual.
     *                                                          If this parameter is not specified, the returned results will include all snapshot types.
     *
     * @param   string          $marker                         optional The response includes only records beyond the marker.
     * @param   int             $maxRecords                     optional The maximum number of records to include in the response.
     *
     * @return  DBClusterSnapshotList  Returns the list of DB Cluster Snapshots
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describe($dbClusterIdentifier = null, $dbClusterSnapshotIdentifier = null, $snapshotType = null, $marker = null, $maxRecords = null)
    {
        return $this->getRds()->getApiHandler()->describeDBClusterSnapshots($dbClusterIdentifier, $dbClusterSnapshotIdentifier, $snapshotType, $marker, $maxRecords);
    }

    /**
     * DeleteDBClusterSnapshot action
     *
     * Deletes a DB cluster snapshot. If the snapshot is being copied, the copy operation is terminated.
     * The DB cluster snapshot must be in the available state to be deleted.
     *
     * @param   string     $dbClusterSnapshotIdentifier     The identifier of the DB cluster snapshot to delete.
     *
     * @return  DBClusterSnapshotData  Returns deleted DB Cluster Snapshot
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function delete($dbClusterSnapshotIdentifier)
    {
        return $this->getRds()->getApiHandler()->deleteDBClusterSnapshot($dbClusterSnapshotIdentifier);
    }

}