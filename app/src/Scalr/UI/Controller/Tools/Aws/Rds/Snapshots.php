<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Tools_Aws_Rds_Snapshots extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'instanceId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_RDS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/rds/snapshots.js', []);
    }

    public function xListSnapshotsAction()
    {
        $this->request->defineParams([
            'cloudLocation', 'dbinstance',
            'sort' => ['type' => 'json', 'default' => ['property' => 'id', 'direction' => 'ASC']]
        ]);

        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $rows = $aws->rds->dbSnapshot->describe($this->getParam('dbinstance'));
        $rowz = [];

        foreach ($rows as $pv) {
            /* @var $pv \Scalr\Service\Aws\Rds\DataType\DBSnapshotData */
            $rowz[] = [
                "dtcreated"     => $pv->snapshotCreateTime,
                "port"          => $pv->port,
                "status"        => $pv->status,
                "engine"        => $pv->engine,
                "avail_zone"    => $pv->availabilityZone,
                "idtcreated"    => $pv->instanceCreateTime,
                "storage"       => $pv->allocatedStorage,
                "name"          => $pv->dBSnapshotIdentifier,
                "id"            => $pv->dBSnapshotIdentifier,
                "type"          => $pv->snapshotType,
            ];
        }

        $response = $this->buildResponseFromData($rowz);

        foreach ($response['data'] as &$row) {
            $row['dtcreated'] = $row['dtcreated'] ? Scalr_Util_DateTime::convertTz($row['dtcreated']) : '';
            $row['idtcreated'] = $row['idtcreated'] ? Scalr_Util_DateTime::convertTz($row['idtcreated']) : '';
        }

        $this->response->data($response);
    }

    public function xCreateSnapshotAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $snapId = "scalr-manual-" . dechex(microtime(true)*10000) . rand(0,9);

        try {
            $aws->rds->dbInstance->createSnapshot($this->getParam('dbinstance'), $snapId);
            $this->db->Execute("
                INSERT INTO rds_snaps_info SET snapid=?, comment=?, dtcreated=NOW(), region=?
            ", [
                $snapId,
                "manual RDS instance snapshot",
                $this->getParam('cloudLocation')
            ]);
        } catch (Exception $e) {
            throw new Exception (sprintf(_("Can't create db snapshot: %s"), $e->getMessage()));
        }

        $this->response->success(sprintf(_("DB snapshot '%s' successfully initiated"), $snapId));
    }

    public function xDeleteSnapshotsAction()
    {
        $this->request->defineParams([
            'snapshots' => ['type' => 'json']
        ]);

        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $i = 0;
        $errors = [];

        foreach ($this->getParam('snapshots') as $snapName) {
            try {
                $aws->rds->dbSnapshot->delete($snapName);
                $this->db->Execute("DELETE FROM rds_snaps_info WHERE snapid=? ", [$snapName]);
                $i++;
            } catch (Exception $e) {
                $errors[] = sprintf(_("Can't delete db snapshot %s: %s"), $snapName, $e->getMessage());
            }
        }

        $message = sprintf(_("%s db snapshot(s) successfully removed"), $i);

        if (count($errors)) {
            $this->response->warning(implode("\n", (array_merge([$message], $errors))));
        } else {
            $this->response->success($message);
        }
    }

}
