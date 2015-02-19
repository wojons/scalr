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

}
