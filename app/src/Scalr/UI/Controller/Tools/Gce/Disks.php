<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Tools_Gce_Disks extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'diskId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GCE_PERSISTENT_DISKS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $locations = self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::GCE, false);

        $this->response->page('ui/tools/gce/disks/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_GCE_PERSISTENT_DISKS, Acl::PERM_GCE_PERSISTENT_DISKS_MANAGE);

        $this->request->defineParams(array(
            'diskId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        foreach ($this->getParam('diskId') as $diskId) {
            $client->disks->delete(
                $this->environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $this->getParam('cloudLocation'),
                $diskId
            );
        }

        $this->response->success('Persistent disk(s) successfully removed');
    }

    /**
     * Fill information about Farm/FarmRole for each object based on cloudServerId.
     * cloudServerId could be empty or didn't exist in our database.
     *
     * @param   array[]   $data   Array of arrays
     */
    private function applyFarmRoleInfo(&$data)
    {
        $cloudServerIds = [];
        foreach ($data as $row) {
            if ($row['cloudServerId']) {
                $cloudServerIds[] = $row['cloudServerId'];
            }
        }

        if (empty($cloudServerIds)) {
            return;
        }

        $server = new Entity\Server();
        $history = new Entity\Server\History();
        $farm = new Entity\Farm();
        $farmRole = new Entity\FarmRole();

        $cloudServerIds = join(",", (array_map(function($serverId) {
            return $this->db->qstr($serverId);
        }, $cloudServerIds)));

        $sql = "
            SELECT {$farm->columnId} AS farmId, {$farm->columnName} AS farmName, {$farmRole->columnId} AS farmRoleId,
                {$farmRole->columnAlias} AS farmRoleName, {$server->columnServerId} AS serverId, {$server->columnIndex} AS serverIndex,
                {$history->columnCloudServerId} AS cloudServerId FROM {$server->table()}
            JOIN {$history->table()} ON {$server->columnServerId} = {$history->columnServerId}
            JOIN {$farm->table()} ON {$server->columnFarmId} = {$farm->columnId}
            JOIN {$farmRole->table()} ON {$server->columnFarmRoleId} = {$farmRole->columnId}
            WHERE {$server->columnEnvId} = ? AND {$history->columnCloudServerId} IN ({$cloudServerIds})
        ";

        $result = [];
        foreach ($this->db->Execute($sql, [$this->getEnvironmentId()]) as $row) {
            $result[$row['cloudServerId']] = $row;
        }

        foreach ($data as &$row) {
            if (!empty($row['cloudServerId']) && !empty($result[$row['cloudServerId']])) {
                $row = array_merge($row, $result[$row['cloudServerId']]);
            }
        }
    }

    /**
     * @param   string  $cloudLocation
     * @param   string  $diskId optional
     */
    public function xListAction($cloudLocation, $diskId = '')
    {
        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        $retval = array();
        $disks = $client->disks->listDisks(
            $this->environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
            $cloudLocation,
            $diskId ? ['filter' => "name eq {$diskId}"] : []
        );

        foreach ($disks as $disk) {
            /* @var $disk Google_Service_Compute_Disk */
            $item = [
                'id'            => $disk->name,
                'description'   => $disk->description,
                'createdAt'     => strtotime($disk->creationTimestamp),
                'size'          => (int) $disk->sizeGb,
                'status'        => $disk->status,
                'snapshotId'    => $disk->sourceSnapshotId,
                'cloudServerId' => !empty($disk->users[0]) ? substr($disk->users[0], strrpos($disk->users[0], "/") + 1) : null
            ];

            $retval[] = $item;
        }

        $response = $this->buildResponseFromData($retval, array('id', 'name', 'description', 'snapshotId', 'createdAt', 'size'));
        foreach ($response['data'] as &$row) {
            $row['createdAt'] = Scalr_Util_DateTime::convertTz($row['createdAt']);
        }

        $this->applyFarmRoleInfo($response['data']);

        $this->response->data($response);
    }
}
