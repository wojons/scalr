<?php

namespace Scalr\Modules\Platforms\RackspaceNgUk;

use Scalr\Modules\Platforms\RackspaceNgUs\RackspaceNgUsPlatformModule;

class RackspaceNgUkPlatformModule extends RackspaceNgUsPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{

    public function __construct()
    {
        parent::__construct(\SERVER_PLATFORMS::RACKSPACENG_UK);
    }

    public function getLocations(\Scalr_Environment $environment = null)
    {
        return array(
            'LON' => 'Rackspace UK / LON'
        );
    }
}
