<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\Image;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;

/**
 * Orphaned servers management
 *
 * @author Constantine Karnacevych <c.karnacevych@scalr.com>
 */
class Scalr_UI_Controller_Servers_Orphaned extends Scalr_UI_Controller
{
    /**
     * List of allowed platforms
     *
     * @var array
     */
    protected $allowedPlatforms = [
        'ec2',
    ];

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_ORPHANED_SERVERS);
    }

    /**
     * Forwards the controller to the default action
     */
    public function defaultAction()
    {
        $this->response->page(['ui/servers/orphaned/view.js', 'ui/security/groups/sgeditor.js'], [
            "allowedPlatforms" => $this->allowedPlatforms,
            'accountId'        => $this->environment->cloudCredentials(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
            'remoteAddress'    => $this->request->getRemoteAddr()
        ]);
    }

    /**
     * List orphaned servers
     *
     * @param string $platform               Cloud platform
     * @param string $cloudLocation optional Cloud location
     */
    public function xListAction($platform, $cloudLocation = null)
    {
        $orphans = $lookup = [];

        $p = PlatformFactory::NewPlatform($platform);

        if (!$this->environment->isPlatformEnabled($platform)) {
            return $this->response->failure(sprintf("Platform '%s' is not enabled", $platform));
        }

        $orphans = $this->buildResponseFromData(
            $p->getOrphanedServers($this->environment, $cloudLocation),
            ["cloudServerId", "privateIp", "publicIp"]
        );

        foreach ($orphans["data"] as $i => &$orphan) {
            $orphan["launchTime"]       = \Scalr_Util_DateTime::convertTz($orphan["launchTime"]);
            $orphan["imageHash"]        = null;
            $orphan["imageName"]        = null;
            $lookup[$orphan["imageId"]] = $i;
        }

        if (!empty($lookup)) {
            /* @var $image Scalr\Model\Entity\Image */
            foreach (Image::find([
                ["status" => Image::STATUS_ACTIVE],
                ["id"     => ['$in' => array_keys($lookup)]]
            ]) as $image) {
                $orphans["data"][$lookup[$image->id]]["imageHash"] = $image->hash;
                $orphans["data"][$lookup[$image->id]]["imageName"] = $image->name;
            }
        }

        $this->response->data($orphans);
    }
}
