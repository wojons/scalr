<?php

use Scalr\Modules\PlatformFactory;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Platforms extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return true;
    }

    public function getCloudLocations($platforms, $allowAll = true)
    {
        $ePlatforms = array();
        $locations = array();

        if (is_string($platforms))
            $platforms = explode(',', $platforms);

        if ($allowAll)
            $locations[''] = 'All';

        if ($this->getEnvironment())
            $ePlatforms = $this->getEnvironment()->getEnabledPlatforms();
        else
            $ePlatforms = array_keys(SERVER_PLATFORMS::GetList());

        if (implode('', $platforms) != 'all')
            $ePlatforms = array_intersect($ePlatforms, $platforms);

        foreach ($ePlatforms as $platform) {
            foreach (PlatformFactory::NewPlatform($platform)->getLocations($this->environment) as $key => $loc)
                $locations[$key] = $loc;
        }

        return $locations;
    }

    public function getEnabledPlatforms($addLocations = false, $includeGCELocations = true)
    {
        $ePlatforms = $this->user->isScalrAdmin() ? array_keys(SERVER_PLATFORMS::GetList()) : $this->getEnvironment()->getEnabledPlatforms();
        $lPlatforms = SERVER_PLATFORMS::GetList();
        $platforms = array();

        foreach ($ePlatforms as $platform)
            $platforms[$platform] = $addLocations ?
                array(
                    'id' => $platform,
                    'name' => $lPlatforms[$platform],
                    'locations' => (!in_array($platform, array(SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::ECS))) || $includeGCELocations ? PlatformFactory::NewPlatform($platform)->getLocations($this->environment) : array()
                ) :
                $lPlatforms[$platform];

        return $platforms;
    }

    /**
     * @param  string    $platform
     * @param  string    $cloudLocation
     * @throws Exception
     */
    public function xGetInstanceTypesAction($platform, $cloudLocation = null)
    {
        if (!in_array($platform, $this->getEnvironment()->getEnabledPlatforms())) {
            throw new Exception(sprintf('Platform "%s" is not enabled', $platform));
        }

        $p = PlatformFactory::NewPlatform($platform);

        if (PlatformFactory::isOpenstack($platform) && !$cloudLocation) {
            $locations = $p->getLocations($this->getEnvironment());
            if (empty($locations)) {
                throw new Exception(sprintf("Unable to retrieve the list of cloud locations for platform %s; the cloud API may be down or unreachable, or the credentials provided to Scalr are invalid.", $platform));
            }
            $cloudLocation = array_pop(array_keys($locations));
        }

        $data = [];
        foreach ($p->getInstanceTypes($this->getEnvironment(), $cloudLocation, true) as $id => $value) {
            $data[] = array_merge(['id' => (string)$id], $value);
        }

        $this->response->data(array('data' => $data));
    }

    /**
     * @param jsonData $platforms
     * @throws Exception
     */
    public function xGetLocationsAction(JsonData $platforms)
    {
        $allPlatforms = $this->user->isScalrAdmin() ? array_keys(SERVER_PLATFORMS::GetList()) : $this->getEnvironment()->getEnabledPlatforms();
        $result = array();

        foreach ($platforms as $platform) {
            if (in_array($platform, $allPlatforms)) {
                $result[$platform] = (!in_array($platform, array(SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::ECS))) ? PlatformFactory::NewPlatform($platform)->getLocations($this->environment) : array();
            }
        }

        $this->response->data(array('locations' => $result));

    }

}
