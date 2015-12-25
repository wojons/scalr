<?php
namespace Scalr\Modules\Platforms;

use Scalr\Modules\AbstractPlatformModule;

abstract class AbstractCloudstackPlatformModule extends AbstractPlatformModule
{
    /**
     * Constructor
     *
     * @param    string     $platform   optional  the name of the platform
     */
    public function __construct($platform = null)
    {
        parent::__construct();
        $this->platform = $platform;
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getImageInfo()
     */
    public function getImageInfo(\Scalr_Environment $environment, $cloudLocation, $imageId)
    {
        $snap = $environment->cloudstack($this->platform)->template->describe([
            "templatefilter" => "executable",
            "id"             => $imageId,
            "zoneid"         => $cloudLocation
        ]);

        return $snap && isset($snap[0]) ? [
            "name" => $snap[0]->name,
            "size" => ceil($snap[0]->size / pow(1024, 3))
        ] : [];
    }
}
