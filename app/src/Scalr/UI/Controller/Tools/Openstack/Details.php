<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\OpenStack\OpenStack;

class Scalr_UI_Controller_Tools_Openstack_Details extends Scalr_UI_Controller
{

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess();
    }

    /**
     * @param string $platform
     */
    public function defaultAction($platform)
    {
        $this->viewAction($platform);
    }

    /**
     * @param string $platform
     */
    public function viewAction($platform)
    {
        $locations = PlatformFactory::NewPlatform($platform)->getLocations($this->environment);

        $this->response->page('ui/tools/openstack/details/view.js', array(
            'platform' => $platform,
            'locations'	=> $locations
        ));
    }

    /**
     * @param string $platform
     * @param string $cloudLocation
     */
    public function xListDetailsAction($platform, $cloudLocation)
    {
        $client = $this->environment->openstack($platform, $cloudLocation);

        $services = $client->listServices();

        $services = array_combine($services, array_fill(0, count($services), []));

        if ($client->hasService(OpenStack::SERVICE_COMPUTE)) {
            foreach ($client->servers->listExtensions() as $name => $ext) {
                $key = !empty($ext->alias) ? $ext->alias : $name;
                $services[OpenStack::SERVICE_COMPUTE][$key] = $ext;
            }
        }

        foreach ([OpenStack::SERVICE_NETWORK, OpenStack::SERVICE_VOLUME, OpenStack::SERVICE_CONTRAIL] as $service) {
            if ($client->hasService($service)) {
                foreach ($client->$service->listExtensions() as $name => $ext) {
                    $key = !empty($ext->alias) ? $ext->alias : $name;
                    $services[$service][$key] = $ext;
                }
            }
        }

        $details = [];
        foreach ($services as $service => $extensions) {
            if (!empty($extensions)) {
                foreach ($extensions as $extension) {
                    $extension->service = $service;
                    $details[] = $extension;
                }
            } else {
                $details[] = [
                    'service' => $service,
                    'name'    => 'No extended information about this service'
                ];
            }
        }

        $this->response->data(['details' => $details]);
    }

}
