<?php

namespace Scalr\Modules\Platforms\UCloud;

use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;

class UCloudPlatformModule extends CloudstackPlatformModule
{

    public function __construct()
    {
        parent::__construct(\SERVER_PLATFORMS::UCLOUD);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule::getLocations()
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        if ($environment === null || !$environment->isPlatformEnabled($this->platform)) {
            return array();
        }
        try {
            $cs = $environment->cloudstack($this->platform);

            $products = $cs->listAvailableProductTypes();

            if (count($products) > 0) {
                foreach ($products as $product) {
                    $retval[$product->zoneid] = "KT uCloud / {$product->zonedesc} ({$product->zoneid})";
                }
            }
        } catch (\Exception $e) {
            return array();
        }

        return $retval;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule::TerminateServer()
     */
    public function TerminateServer(\DBServer $DBServer)
    {
        $cs = $DBServer->GetEnvironmentObject()->cloudstack($this->platform);

        if (!$DBServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::IS_STOPPED_BEFORE_TERMINATE)) {
            $cs->instance->stop($DBServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID), true);
            $DBServer->SetProperty(\CLOUDSTACK_SERVER_PROPERTIES::IS_STOPPED_BEFORE_TERMINATE, 1);
        }

        return parent::TerminateServer($DBServer);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceTypes()
     */
    public function getInstanceTypes(\Scalr_Environment $env = null, $cloudLocation = null, $details = false)
    {
        if (!($env instanceof \Scalr_Environment)) {
            throw new \InvalidArgumentException(sprintf(
                "Method %s requires environment to be specified.", __METHOD__
            ));
        }
        $ret = array();

        $cs = $env->cloudstack($this->platform);
        $products = $cs->listAvailableProductTypes();
        if (count($products) > 0) {
            foreach ($products as $product) {
                $ret[(string)$product->serviceofferingid] = (string)$product->serviceofferingdesc;
            }
        }

        return $ret;
    }
}
