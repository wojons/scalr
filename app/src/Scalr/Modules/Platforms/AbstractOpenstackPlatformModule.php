<?php
namespace Scalr\Modules\Platforms;

use Scalr\Modules\AbstractPlatformModule;

abstract class AbstractOpenstackPlatformModule extends AbstractPlatformModule
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
        $snap = $environment->openstack($this->platform, $cloudLocation)->servers->getImage($imageId);

        return $snap ? [
            "name" => $snap->name,
            "size" => $snap->metadata->instance_type_root_gb,
        ] : [];
    }
}
