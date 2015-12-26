<?php

use Scalr\Modules\PlatformFactory;
use Scalr\UI\Request\JsonData;
use Scalr\Farm\Role\FarmRoleStorageConfig;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Platforms extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return true;
    }

    public function getCloudLocations($platforms, $allowAll = true)
    {
        $locations = array();

        if (is_string($platforms))
            $platforms = explode(',', $platforms);

        if ($allowAll)
            $locations[''] = 'All';

        $ePlatforms = !empty($this->getEnvironment())
                        ? (in_array('all', (array) $platforms)
                            ? $this->getEnvironment()->getEnabledPlatforms(true)
                            : $this->getEnvironment()->getEnabledPlatforms(true, $platforms))
                        : array_keys(SERVER_PLATFORMS::GetList());

        foreach ($ePlatforms as $platform) {
            foreach (PlatformFactory::NewPlatform($platform)->getLocations($this->environment) as $key => $loc)
                $locations[$key] = $loc;
        }

        return $locations;
    }

    public function getEnabledPlatforms($addLocations = false, $includeGCELocations = true)
    {
        $ePlatforms = $this->request->getScope() != ScopeInterface::SCOPE_ENVIRONMENT ? array_keys(SERVER_PLATFORMS::GetList()) : $this->getEnvironment()->getEnabledPlatforms();
        $lPlatforms = SERVER_PLATFORMS::GetList();
        $platforms = array();

        foreach ($ePlatforms as $platform)
            $platforms[$platform] = $addLocations ?
                array(
                    'id' => $platform,
                    'name' => $lPlatforms[$platform],
                    'locations' => (!in_array($platform, array(SERVER_PLATFORMS::GCE))) || $includeGCELocations ? PlatformFactory::NewPlatform($platform)->getLocations($this->environment) : array()
                ) :
                $lPlatforms[$platform];

        return $platforms;
    }

    /**
     * @param  string    $platform
     * @param  string    $cloudLocation
     * @param  int    $roleId
     * @throws Exception
     */
    public function xGetRootDeviceInfoAction($platform, $cloudLocation, $roleId)
    {
        if (!in_array($platform, $this->getEnvironment()->getEnabledPlatforms())) {
            throw new Exception(sprintf('Platform "%s" is not enabled', $platform));
        }

        $role = DBRole::loadById($roleId)->__getNewRoleObject();
        $image = $role->getImage($platform, $cloudLocation)->getImage();

        $data = ['readOnly' => true, 'mountPoint' => ($role->getOs()->family == 'windows') ? 'C:\ (ROOT)' : '/ (ROOT)'];
        $settings = [];

        switch ($platform) {
            case SERVER_PLATFORMS::CLOUDSTACK:
                $data['type'] = 'csvol';
                $data['readOnly'] = true;
            break;

            case SERVER_PLATFORMS::GCE:
                // Get default size
                $p = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
                $gceClient = $p->getClient($this->environment);

                /* @var $gceClient Google_Service_Compute */

                $ind = strpos($image->id, '/global/');
                if ($ind !== FALSE) {
                    $projectId = substr($image->id, 0, $ind);
                    $id = str_replace("{$projectId}/global/images/", '', $image->id);
                } else {
                    $ind = strpos($image->id, '/images/');
                    if ($ind !== false) {
                        $projectId = substr($image->id, 0, $ind);
                    } else
                        $projectId = $this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

                    $id = str_replace("{$projectId}/images/", '', $image->id);
                }

                $imageInfo = $gceClient->images->get($projectId, $id);
                $settings = [
                    FarmRoleStorageConfig::SETTING_GCE_PD_TYPE => 'pd-standard',
                    FarmRoleStorageConfig::SETTING_GCE_PD_SIZE => $imageInfo->diskSizeGb
                ];

                $data['type'] = 'gce_persistent';
                $data['readOnly'] = false;
            break;
            case SERVER_PLATFORMS::EC2:
                if ($image->isEc2EbsImage()) {
                    $data['type'] = 'ebs';
                    $aws = $this->getEnvironment()->aws($cloudLocation);
                    $cloudImageInfo = $aws->ec2->image->describe($image->id)[0];
                    if ($cloudImageInfo->blockDeviceMapping) {
                        foreach ($cloudImageInfo->blockDeviceMapping as $blockDeviceMapping) {
                            if (stristr($blockDeviceMapping->deviceName, $cloudImageInfo->rootDeviceName)) {
                                $data['readOnly'] = false;
                                $settings[FarmRoleStorageConfig::SETTING_EBS_TYPE] = $blockDeviceMapping->ebs->volumeType;
                                $settings[FarmRoleStorageConfig::SETTING_EBS_SIZE] = $blockDeviceMapping->ebs->volumeSize;
                                $settings[FarmRoleStorageConfig::SETTING_EBS_SNAPSHOT] = $blockDeviceMapping->ebs->snapshotId;
                                $settings[FarmRoleStorageConfig::SETTING_EBS_IOPS] = $blockDeviceMapping->ebs->iops;
                                $settings[FarmRoleStorageConfig::SETTING_EBS_DEVICE_NAME] = $blockDeviceMapping->deviceName;
                            }
                        }
                    }
                } else {
                    $data['type'] = 'instance-store';
                    $settings['size'] = '10';
                }
            break;
        }

        $data['settings'] = $settings;

        $this->response->data(array('data' => $data));
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
                throw new Exception(sprintf(
                    "Unable to retrieve the list of cloud locations for platform %s; "
                  . "the cloud API may be down or unreachable, or the credentials provided to Scalr are invalid.",
                    $platform
                ));
            }

            $keys = array_keys($locations);
            $cloudLocation = array_pop($keys);
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
        $allPlatforms = $this->request->getScope() != ScopeInterface::SCOPE_ENVIRONMENT ? array_keys(SERVER_PLATFORMS::GetList()) : $this->getEnvironment()->getEnabledPlatforms();
        $result = array();

        foreach ($platforms as $platform) {
            if (in_array($platform, $allPlatforms)) {
                $result[$platform] = (!in_array($platform, array(SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::AZURE))) ? PlatformFactory::NewPlatform($platform)->getLocations($this->environment) : array();
            }
        }

        $this->response->data(array('locations' => $result));

    }

}
