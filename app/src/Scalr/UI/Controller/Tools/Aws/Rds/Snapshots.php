<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws\Rds\DataType\AvailabilityZoneData;
use Scalr\Service\Aws\Rds\DataType\DBClusterSnapshotData;
use Scalr\Service\Aws\Rds\DataType\DBSnapshotData;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Tools_Aws_Rds_Snapshots extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'instanceId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_RDS);
    }

    /**
     * Forwards the controller to the default action
     */
    public function defaultAction()
    {
        $this->viewAction();
    }

    /**
     * Gets AWS Client for the current environment
     *
     * @param  string $cloudLocation Cloud location
     * @return \Scalr\Service\Aws Returns Aws client for current environment
     */
    protected function getAwsClient($cloudLocation)
    {
        return $this->environment->aws($cloudLocation);
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/rds/snapshots.js', []);
    }

    /**
     * List snapshots
     *
     * @param string $cloudLocation                 Cloud location
     * @param string $type                          optional Snapshot type (manual, automated)
     * @param string $dbInstanceId                  optional Instance identifier
     * @param string $dbClusterId                   optional Cluster identifier
     */
    public function xListSnapshotsAction($cloudLocation, $type = null, $dbInstanceId = null, $dbClusterId = null)
    {
        $aws = $this->getAwsClient($cloudLocation);

        $rows = [];

        if (!isset($dbClusterId)) {
            $marker = null;

            do {
                if (isset($snapshots)) {
                    $marker = $snapshots->getMarker();
                }

                $snapshots = $aws->rds->dbSnapshot->describe($dbInstanceId, null, $type, $marker);

                foreach ($snapshots as $pv) {
                    /* @var $pv DBSnapshotData */
                    $rows[] = [
                        "dtcreated"     => isset($pv->snapshotCreateTime) ? $pv->snapshotCreateTime->getTimestamp() : 0,
                        "port"          => $pv->port,
                        "status"        => $pv->status,
                        "engine"        => $pv->engine,
                        "avail_zone"    => $pv->availabilityZone,
                        "idtcreated"    => isset($pv->instanceCreateTime) ? $pv->instanceCreateTime->getTimestamp() : 0,
                        "storage"       => $pv->allocatedStorage,
                        "name"          => $pv->dBSnapshotIdentifier,
                        "id"            => $pv->dBSnapshotIdentifier,
                        "type"          => $pv->snapshotType,
                    ];
                }
            } while ($snapshots->getMarker() !== null);
        }

        if (!isset($dbInstanceId)) {
            $marker = null;

            do {
                if (isset($clusterSnapshots)) {
                    $marker = $clusterSnapshots->getMarker();
                }

                $clusterSnapshots = $aws->rds->dbClusterSnapshot->describe($dbClusterId, null, $type, $marker);

                foreach ($clusterSnapshots as $clusterSnapshot) {
                    /* @var $clusterSnapshot DBClusterSnapshotData */
                    $zones = [];

                    foreach ($clusterSnapshot->availabilityZones as $zone) {
                        /* @var $zone AvailabilityZoneData */
                        $zones[] = $zone->name;
                    }

                    $rows[] = [
                        "dtcreated"  => isset($clusterSnapshot->snapshotCreateTime) ? $clusterSnapshot->snapshotCreateTime->getTimestamp() : 0,
                        "port"       => $clusterSnapshot->port ? $clusterSnapshot->port : null,
                        "status"     => $clusterSnapshot->status,
                        "engine"     => $clusterSnapshot->engine,
                        "avail_zone" => $zones,
                        "idtcreated" => isset($clusterSnapshot->clusterCreateTime) ? $clusterSnapshot->clusterCreateTime->getTimestamp() : 0,
                        "storage"    => $clusterSnapshot->allocatedStorage,
                        "name"       => $clusterSnapshot->dBClusterSnapshotIdentifier,
                        "id"         => $clusterSnapshot->dBClusterSnapshotIdentifier,
                        "type"       => $clusterSnapshot->snapshotType,
                    ];
                }
            } while ($clusterSnapshots->getMarker() !== null);
        }

        $response = $this->buildResponseFromData($rows, ['name']);

        foreach ($response['data'] as &$row) {
            $row['dtcreated']  = $row['dtcreated']  ? Scalr_Util_DateTime::convertTz($row['dtcreated'])  : '';
            $row['idtcreated'] = $row['idtcreated'] ? Scalr_Util_DateTime::convertTz($row['idtcreated']) : '';
        }

        $this->response->data($response);
    }

    /**
     * Creates a snapshot
     *
     * @param string $cloudLocation Cloud location
     * @param string $dbInstanceId  optional Instance identifier
     * @param string $dbClusterId   optional Cluster identifier
     * @throws Exception
     */
    public function xCreateSnapshotAction($cloudLocation, $dbInstanceId = null, $dbClusterId = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $snapId = "scalr-manual-" . dechex(microtime(true)*10000) . rand(0,9);

        try {
            if (empty($dbClusterId)) {
                $aws->rds->dbInstance->createSnapshot($dbInstanceId, $snapId);
                $item = 'instance';
            } else {
                $aws->rds->dbClusterSnapshot->create($dbClusterId, $snapId);
                $item = 'cluster';
            }

            $this->db->Execute("
                INSERT INTO rds_snaps_info SET snapid=?, comment=?, dtcreated=NOW(), region=?
            ", [
                $snapId,
                sprintf("manual RDS %s snapshot", $item),
                $cloudLocation
            ]);
        } catch (Exception $e) {
            throw new Exception (sprintf(_("Can't create db snapshot: %s"), $e->getMessage()));
        }

        $this->response->success(sprintf(_("DB snapshot '%s' successfully initiated"), $snapId));
    }

    /**
     * Deletes snapshot(s)
     *
     * @param string   $cloudLocation   Cloud location
     * @param JsonData $snapshots       optional List of snapshots to delete
     */
    public function xDeleteSnapshotsAction($cloudLocation, JsonData $snapshots = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $i = 0;
        $errors = [];

        foreach ($snapshots as $snapshot) {
            try {
                if ($snapshot['engine'] != 'aurora') {
                    $aws->rds->dbSnapshot->delete($snapshot['name']);
                } else {
                    $aws->rds->dbClusterSnapshot->delete($snapshot['name']);
                }

                $this->db->Execute("DELETE FROM rds_snaps_info WHERE snapid=? ", [$snapshot['name']]);
                $i++;
            } catch (Exception $e) {
                $errors[] = sprintf(_("Can't delete db snapshot %s: %s"), $snapshot['name'], $e->getMessage());
            }
        }

        $message = sprintf(_("%s db snapshot(s) successfully removed"), $i);

        if (empty($errors)) {
            $this->response->success($message);
        } else {
            $this->response->warning(implode("\n", (array_merge([$message], $errors))));
        }
    }

}
