<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\Image;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

/**
 * Discovery servers management
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 */
class Scalr_UI_Controller_Discoverymanager_Servers extends Scalr_UI_Controller
{
    /**
     * List of allowed platforms
     *
     * @var array
     */
    protected $allowedPlatforms = [
        SERVER_PLATFORMS::EC2,
    ];

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_DISCOVERY_SERVERS);
    }

    /**
     * Forwards the controller to the default action
     */
    public function defaultAction()
    {
        $allowedPlatforms = [];
        foreach ($this->allowedPlatforms as $platform) {
            if ($this->environment->isPlatformEnabled($platform)) {
                $allowedPlatforms[] = $platform;
            }
        }

        if (empty($allowedPlatforms)) {
            throw new Exception(sprintf("Discovery manager for servers works only on the following platforms: %s.",
                join(", ", array_map(['SERVER_PLATFORMS', 'GetName'], $this->allowedPlatforms))));
        }

        $this->response->page(['ui/discoverymanager/servers/view.js', 'ui/security/groups/sgeditor.js'], [
            "allowedPlatforms" => $allowedPlatforms,
            'accountId'        => $this->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
            'remoteAddress'    => $this->request->getRemoteAddr()
        ]);
    }

    /**
     * List orphaned servers
     *
     * @param   string  $platform                   Cloud platform
     * @param   string  $cloudLocation  optional    Cloud location
     * @param   string  $query          optional    Filter parameter
     * @param   string  $imageId        optional    Filter parameter
     * @param   string  $vpcId          optional    Filter parameter
     * @param   string  $subnetId       optional    Filter paramerer
     */
    public function xListAction($platform, $cloudLocation = null, $query = null, $imageId = null, $vpcId = null, $subnetId = null)
    {
        $lookup = [];

        $p = PlatformFactory::NewPlatform($platform);

        if (!$this->environment->isPlatformEnabled($platform)) {
            $this->response->failure(sprintf("Platform '%s' is not enabled", $platform));
            return;
        }

        $filterFields = [];
        if ($query) {
            $filterFields[join(',', ['cloudServerId', 'privateIp', 'publicIp'])] = $query;
        }

        if ($imageId) {
            $filterFields['imageId'] = $imageId;
        }

        if ($vpcId) {
            $filterFields['vpcId'] = $vpcId;
        }

        if ($subnetId) {
            $filterFields['subnetId'] = $subnetId;
        }

        $orphans = $this->buildResponseFromData(
            $p->getOrphanedServers($this->getEnvironmentEntity(), $cloudLocation),
            $filterFields
        );

        foreach ($orphans["data"] as $i => &$orphan) {
            $orphan["launchTime"]       = \Scalr_Util_DateTime::convertTz($orphan["launchTime"]);
            $orphan["imageHash"]        = null;
            $orphan["imageName"]        = null;
            if (!is_array($lookup[$orphan["imageId"]])) {
                $lookup[$orphan["imageId"]] = [];
            }
            $lookup[$orphan["imageId"]][] = $orphan;
        }

        if (!empty($lookup)) {
            /* @var $image Scalr\Model\Entity\Image */
            foreach (Image::find([
                ["status" => Image::STATUS_ACTIVE],
                ["id"     => ['$in' => array_keys($lookup)]],
                ['$or' => [
                    ['accountId' => null],
                    ['$and' => [
                        ['accountId' => $this->getUser()->accountId],
                        ['$or' => [
                            ['envId' => null],
                            ['envId' => $this->getEnvironment()->id]
                        ]]
                    ]]
                ]]
            ]) as $image) {
                foreach ($lookup[$image->id] as &$orphan) {
                    $orphan['imageHash'] = $image->hash;
                    $orphan['imageName'] = $image->name;
                }
            }
        }

        $this->response->data($orphans);
    }
}
