<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Tools_Gce_Snapshots extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'snapshotId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_GCE_SNAPSHOTS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $locations = self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::GCE, false);

        $this->response->page('ui/tools/gce/snapshots/view.js', array(
            'locations'	=> $locations
        ));
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_GCE_SNAPSHOTS, Acl::PERM_GCE_SNAPSHOTS_MANAGE);

        $this->request->defineParams(array(
            'snapshotId' => array('type' => 'json')
        ));

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        foreach ($this->getParam('snapshotId') as $snapId) {
            $client->snapshots->delete(
                $this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $snapId
            );
        }

        $this->response->success('Snapshot(s) successfully removed');
    }

    /**
     * @param   string  $snapshotId  optional
     */
    public function xListAction($snapshotId = '')
    {
        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
        $client = $platform->getClient($this->environment);
        /* @var $client Google_Service_Compute */

        $retval = array();

        $snaps = $client->snapshots->listSnapshots(
            $this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
            $snapshotId ? ['filter' => "name eq {$snapshotId}"] : []
        );

        foreach ($snaps as $snap) {
            /* @var $snap Google_Service_Compute_Snapshot */
            $item = array(
                'id'            => $snap->name,
                'description'   => $snap->description,
                'createdAt'     => strtotime($snap->creationTimestamp),
                'size'          => (int) $snap->diskSizeGb,
                'status'        => $snap->status/*,
                'details'       => (array)$snap->toSimpleObject()*/
            );

            $retval[] = $item;
        }

        $response = $this->buildResponseFromData($retval, array('id', 'description', 'createdAt', 'size'));
        foreach ($response['data'] as &$row) {
            $row['createdAt'] = Scalr_Util_DateTime::convertTz($row['createdAt']);
        }

        $this->response->data($response);
    }
}
