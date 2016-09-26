<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws\Ec2\DataType as Ec2DataType;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Tools_Aws_Ec2_Ebs_Snapshots extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'snapshotId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_SNAPSHOTS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/ec2/ebs/snapshots/view.js', []);
    }

    public function xGetMigrateDetailsAction()
    {
        if (!$this->request->getEnvironment()->isPlatformEnabled(SERVER_PLATFORMS::EC2)) {
            throw new Exception('You can migrate image between regions only on EC2 cloud');
        }

        $availableDestinations = [];

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
        $locationsList = $platform->getLocations($this->environment);

        foreach ($locationsList as $location => $name) {
            if ($location != $this->getParam('cloudLocation'))
                $availableDestinations[] = array('cloudLocation' => $location, 'name' => $name);
        }

        $this->response->data(array(
            'sourceRegion'          => $this->getParam('cloudLocation'),
            'availableDestinations' => $availableDestinations,
            'snapshotId'            => $this->getParam('snapshotId')
        ));
    }

    public function xMigrateAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_SNAPSHOTS, Acl::PERM_AWS_SNAPSHOTS_MANAGE);

        $aws = $this->request->getEnvironment()->aws($this->getParam('sourceRegion'));
        $newSnapshotId = $aws->ec2->snapshot->copy(
            $this->getParam('sourceRegion'),
            $this->getParam('snapshotId'),
            sprintf(_("Copy of %s from %s"), $this->getParam('snapshotId'), $this->getParam('sourceRegion')),
            $this->getParam('destinationRegion')
        );

        $this->response->data(array('data' => array('snapshotId' => $newSnapshotId, 'cloudLocation' => $this->getParam('destinationRegion'))));
    }

    public function xCreateAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_SNAPSHOTS, Acl::PERM_AWS_SNAPSHOTS_MANAGE);

        $this->request->defineParams(array(
            'volumeId',
            'cloudLocation',
            'description'
        ));

        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));
        $snapshot = $aws->ec2->snapshot->create($this->getParam('volumeId'), $this->getParam('description'));

        if (isset($snapshot->snapshotId)) {
            /* @var $volume \Scalr\Service\Aws\Ec2\DataType\VolumeData */
            $volume = $aws->ec2->volume->describe($snapshot->volumeId)->get(0);

            if (!empty($volume->tagSet) && $volume->tagSet->count()) {
                try {
                    //We need to do sleep due to eventual consistency on EC2
                    sleep(2);
                    //Set tags (copy them from the original EBS volume)
                    $snapshot->createTags($volume->tagSet);
                } catch (Exception $e) {
                    //We want to hear from the cases when it cannot set tag to snapshot
                    trigger_error(sprintf("Cound not set tag to snapshot: %s", $e->getMessage()), E_USER_WARNING);
                }
            }

            if (count($volume->attachmentSet) && !empty($volume->attachmentSet[0]->instanceId)) {
                $instanceId = $volume->attachmentSet[0]->instanceId;
                try {
                    $dBServer = DBServer::LoadByPropertyValue(EC2_SERVER_PROPERTIES::INSTANCE_ID, $instanceId);
                    $dBFarm = $dBServer->GetFarmObject();
                } catch (Exception $e) {
                }

                if (isset($dBServer) && isset($dBFarm)) {
                    $comment = sprintf(_("Created on farm '%s', server '%s' (Instance ID: %s)"),
                        $dBFarm->Name, $dBServer->serverId, $instanceId
                    );
                }
            } else {
                $comment = '';
            }

            $this->db->Execute("
                INSERT INTO ebs_snaps_info
                SET snapid = ?,
                    comment = ?,
                    dtcreated = NOW(),
                    region = ?
            ", array(
                $snapshot->snapshotId, $comment, $this->getParam('cloudLocation')
            ));

            $this->response->data(array('data' => array('snapshotId' => $snapshot->snapshotId)));
        } else {
            throw new Exception("Unable to create snapshot. Please try again later.");
        }
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_SNAPSHOTS, Acl::PERM_AWS_SNAPSHOTS_MANAGE);

        $this->request->defineParams(array(
            'snapshotId' => array('type' => 'json'),
            'cloudLocation'
        ));
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $cnt = 0;
        $errcnt = 0;
        $errmsg = null;

        foreach ($this->getParam('snapshotId') as $snapshotId) {
            try {
                $aws->ec2->snapshot->delete($snapshotId);
                $cnt++;
            } catch (Exception $e) {
                $errcnt++;
                $errmsg = $e->getMessage();
            }
        }

        $msg = 'Snapshot' . ($cnt > 1 ? 's have' : ' has') . ' been successfully removed.';

        if ($errcnt != 0) {
            $msg .= " {$errcnt} snapshots was not removed due to error: {$errmsg}";
        }

        $this->response->success($msg);
    }

    public function xListSnapshotsAction($cloudLocation, $snapshotId = null, $volumeId = null, $showPublicSnapshots = null)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $filter = [];

        if (!empty($snapshotId)) {
            $filter[] = array(
                'name'  => Ec2DataType\SnapshotFilterNameType::snapshotId(),
                'value' => $snapshotId,
            );
        }

        if (!empty($volumeId)) {
            $filter[] = array(
                'name'  => Ec2DataType\SnapshotFilterNameType::volumeId(),
                'value' => $volumeId,
            );
        }

        $snaps = [];
        $snapList = $nextToken = null;

        do {
            if (isset($snapList)) {
                $nextToken = $snapList->getNextToken();
            }

            $snapList = $aws->ec2->snapshot->describe(null, null, (empty($filter) ? null : $filter), null, $nextToken);

            /* @var $snapshot Ec2DataType\SnapshotData */
            foreach ($snapList as $snapshot) {
                $item = [
                    'snapshotId' => $snapshot->snapshotId,
                    'volumeId'   => $snapshot->volumeId,
                    'volumeSize' => (int) $snapshot->volumeSize,
                    'status'     => $snapshot->status,
                    'startTime'  => $snapshot->startTime->format('c'),
                    'progress'   => $snapshot->progress
                ];

                if ($snapshot->ownerId != $this->getEnvironment()->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID]) {
                    $item['comment'] = $snapshot->description;
                    $item['owner'] = $snapshot->ownerId;

                    if (!$showPublicSnapshots) {
                        continue;
                    }
                } else {
                    if ($snapshot->description) {
                        $item['comment'] = $snapshot->description;
                    }

                    $item['owner'] = 'Me';
                }

                $item['progress'] = (int) preg_replace("/[^0-9]+/", "", $item['progress']);
                unset($item['description']);
                $snaps[] = $item;
            }

        } while ($snapList->getNextToken() !== null);

        $response = $this->buildResponseFromData($snaps, ['snapshotId', 'volumeId', 'comment', 'owner']);

        foreach ($response['data'] as &$row) {
            $row['startTime'] = Scalr_Util_DateTime::convertTz($row['startTime']);

            if (empty($row['comment'])) {
                $row['comment'] = $this->db->GetOne("SELECT comment FROM ebs_snaps_info WHERE snapid=? LIMIT 1", [
                    $row['snapshotId']
                ]);
            }
        }

        $this->response->data($response);
    }
}
