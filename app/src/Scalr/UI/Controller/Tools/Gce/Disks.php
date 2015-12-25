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
        $client = $platform->getClient($this->environment, $this->getParam('cloudLocation'));
        /* @var $client Google_Service_Compute */

        foreach ($this->getParam('diskId') as $diskId) {
            $client->disks->delete(
                $this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $this->getParam('cloudLocation'),
                $diskId
            );
        }

        $this->response->success('Persistent disk(s) successfully removed');
    }

    /**
     * @param   string  $cloudLocation
     * @param   string  $diskId optional
     */
    public function xListAction($cloudLocation, $diskId = '')
    {
        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment, $cloudLocation);
        /* @var $client Google_Service_Compute */

        $retval = array();
        $disks = $client->disks->listDisks(
            $this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
            $cloudLocation,
            $diskId ? ['filter' => "name eq {$diskId}"] : []
        );

        foreach ($disks as $disk) {
            /* @var $disk Google_Service_Compute_Disk */
            $item = array(
                'id'            => $disk->name,
                'description'   => $disk->description,
                'createdAt'     => strtotime($disk->creationTimestamp),
                'size'          => (int) $disk->sizeGb,
                'status'        => $disk->status,
                'snapshotId'    => $disk->sourceSnapshotId
            );

            $retval[] = $item;
        }

        $response = $this->buildResponseFromData($retval, array('id', 'name', 'description', 'snapshotId', 'createdAt', 'size'));
        foreach ($response['data'] as &$row) {
            $row['createdAt'] = Scalr_Util_DateTime::convertTz($row['createdAt']);
        }

        $this->response->data($response);
    }
}
