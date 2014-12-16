<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\Image;
use Scalr\UI\Request\JsonData;
use Scalr\Modules\PlatformFactory;

class Scalr_UI_Controller_Images extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return !!$this->user;
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES);

        $this->response->page('ui/images/view.js', [
            'os' => $this->db->GetCol("SELECT DISTINCT os_family FROM `images` WHERE env_id IS NULL OR env_id IN (?) AND os_family <> '' ORDER BY os_family", array($this->user->isScalrAdmin() ? NULL : $this->getEnvironmentId())),
            'platforms' => Image::getEnvironmentPlatforms($this->getEnvironmentId(true))
        ]);
    }

    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_CREATE);
        $this->response->page('ui/images/create.js');
    }

    /**
     * @param string $query
     * @param string $platform
     * @param string $cloudLocation
     * @param string $scope
     * @param string $osFamily
     * @param string $id
     * @param JsonData $sort
     * @param int $start
     * @param int $limit
     * @param JsonData $os
     * @param JsonData $hideLocation
     * @param bool $hideNotActive
     * @throws Exception
     */
    public function xListAction($query = null, $platform = null, $cloudLocation = null, $scope = null, $osFamily = null, $id = null, JsonData $sort, $start = 0, $limit = 20, JsonData $os, JsonData $hideLocation, $hideNotActive = false)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES);

        $criteria = [];

        if ($query) {
            $querySql = '%' . $query . '%';
            $criteria[] = ['name' => [ '$like' => $querySql ]];
        }

        if ($platform) {
            $criteria[] = ['platform' => $platform];
        }

        if ($cloudLocation) {
            $criteria[] = ['cloudLocation' => $cloudLocation];
        }

        if ($this->getEnvironment()) {
            $enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();
            if ($scope) {
                if ($scope == 'env') {
                    $criteria[] = ['envId' => $this->getEnvironmentId(true)];
                } else {
                    // hide shared images, which platforms are not configured
                    if (empty($enabledPlatforms)) {
                        $criteria[] = ['platform' => NULL];
                    } else {
                        $criteria[] = ['envId' => NULL];
                        $criteria[] = ['platform' => ['$in' => $enabledPlatforms]];
                    }
                }
            } else {
                $criteria[] = ['$or' => [
                    ['envId' => $this->getEnvironmentId(true)],
                    ['$and' => [['envId' => NULL], ['platform' => empty($enabledPlatforms) ? NULL : ['$in' => $enabledPlatforms]]]]
                ]];
            }
        } else {
            $criteria[] = ['envId' => NULL];
        }

        if ($osFamily) {
            $criteria[] = ['osFamily' => $osFamily];
        }

        if ($os['osFamily'] && $os['osVersion']) {
            $criteria[] = ['osFamily' => $os['osFamily']];
            
            if (in_array($os['osFamily'], array('centos', 'oel', 'redhat', 'scientific', 'debian'))) {
                $criteria[] = ['osGeneration' => (int)substr($os['osVersion'], 0, 1)];
            } else {
                $criteria[] = ['osVersion' => $os['osVersion']];
            }
        }

        if ($id) {
            $criteria[] = ['id' => [ '$like' => $id . '%']];
        }

        if ($hideLocation) {
            foreach ($hideLocation as $platform => $locations) {
                foreach ($locations as $loc) {
                    if ($loc) {
                        $criteria[] = ['$or' => [
                            ['platform' => ['$ne' => $platform]],
                            ['cloudLocation' => ['$ne' => $loc]]
                        ]];
                    } else {
                        $criteria[] = ['platform' => ['$ne' => $platform]];
                    }
                }
            }
        }

        if ($hideNotActive) {
            $criteria[] = ['status' => Image::STATUS_ACTIVE];
        }

        $result = Image::find($criteria, \Scalr\UI\Utils::convertOrder($sort, ['id' => 'ASC'], ['id', 'platform', 'cloudLocation', 'name', 'os', 'dtAdded']), $limit, $start, true);
        $data = [];
        foreach ($result as $image) {
            /* @var Image $image */
            $s = get_object_vars($image);
            $s['dtAdded'] = $image->dtAdded ? Scalr_Util_DateTime::convertTz($image->dtAdded) : '';
            $s['used'] = $image->getUsed();
            $s['software'] = $image->getSoftwareAsString();
            $data[] = $s;
        }

        $this->response->data([
            'total' => $result->totalNumber,
            'data' => $data
        ]);
    }

    /**
     * @param $osFamily
     * @param $osVersion
     */
    public function xGetRoleImagesAction($osFamily, $osVersion)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);

        $data = [];

        foreach (Image::find([
            ['$or' => [['envId' => $this->getEnvironmentId(true)], ['envId' => NULL]]],
            ['osFamily' => $osFamily],
            ['osVersion' => $osVersion],
            ['status' => Image::STATUS_ACTIVE]
        ]) as $image) {
            /* @var Image $image */
            $data[] = [
                'platform' => $image->platform,
                'cloudLocation' => $image->cloudLocation,
                'id' => $image->id,
                'architecture' => $image->architecture,
                'source' => $image->source,
                'createdByEmail' => $image->createdByEmail
            ];
        }

        $this->response->data(['images' => $data]);
    }

    /**
     * @param   string  $id
     * @param   string  $cloudLocation
     * @throws  Exception
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xGetEc2MigrateDetailsAction($id, $cloudLocation)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_MANAGE);

        /* @var Image $image */
        $image = Image::findOne([['platform' => SERVER_PLATFORMS::EC2], ['id' => $id], ['cloudLocation' => $cloudLocation], ['envId' => $this->getEnvironmentId()]]);
        if (! $image)
            throw new Exception('Image not found');

        $this->user->getPermissions()->validate($image);

        if (!$this->getEnvironment()->isPlatformEnabled(SERVER_PLATFORMS::EC2))
            throw new Exception('You can migrate image between regions only on EC2 cloud');

        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
        $locationsList = $platform->getLocations($this->environment);

        $availableDestinations = [];
        $cloudLocation = $image->cloudLocation;
        foreach ($locationsList as $location => $name) {
            if ($location != $image->cloudLocation)
                $availableDestinations[] = array('cloudLocation' => $location, 'name' => $name);
            else
                $cloudLocation = $name;
        }

        $this->response->data(array(
            'availableDestinations' => $availableDestinations,
            'name' => $image->name,
            'cloudLocation' => $cloudLocation
        ));
    }

    /**
     * @param $id
     * @param $cloudLocation
     * @param $destinationRegion
     * @throws Exception
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xEc2MigrateAction($id, $cloudLocation, $destinationRegion)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_MANAGE);

        /* @var Image $image */
        $image = Image::findOne([['platform' => SERVER_PLATFORMS::EC2], ['id' => $id], ['cloudLocation' => $cloudLocation], ['envId' => $this->getEnvironmentId()]]);
        if (! $image)
            throw new Exception('Image not found');

        $this->user->getPermissions()->validate($image);

        if (!$this->getEnvironment()->isPlatformEnabled(SERVER_PLATFORMS::EC2))
            throw new Exception('You can migrate image between regions only on EC2 cloud');

        $image->checkImage(); // re-check properties
        $aws = $this->request->getEnvironment()->aws($cloudLocation);
        $newImageId = $aws->ec2->image->copy(
            $image->cloudLocation,
            $image->id,
            $image->name,
            "Image was copied by Scalr from image: {$image->name}, cloudLocation: {$image->cloudLocation}, id: {$image->id}",
            null,
            $destinationRegion
        );

        $newImage = new Image();
        $newImage->platform = $image->platform;
        $newImage->cloudLocation = $destinationRegion;
        $newImage->id = $newImageId;
        $newImage->name = $image->name;
        $newImage->architecture = $image->architecture;
        $newImage->size = $image->size;
        $newImage->envId = $this->getEnvironmentId();
        $newImage->os = $image->os;
        $newImage->osFamily = $image->osFamily;
        $newImage->osVersion = $image->osVersion;
        $newImage->osGeneration = $image->osGeneration;
        $newImage->source = Image::SOURCE_MANUAL;
        $newImage->type = $image->type;
        $newImage->agentVersion = $image->agentVersion;
        $newImage->createdById = $this->user->getId();
        $newImage->createdByEmail = $this->user->getEmail();
        $newImage->status = Image::STATUS_ACTIVE;
        $newImage->save();
        $newImage->setSoftware($image->getSoftware());

        $this->response->data(['id' => $newImageId]);
        $this->response->success('Image successfully migrated');
    }

    /**
     * @param $imageId
     * @param $platform
     * @param $cloudLocation
     */
    public function xCheckAction($imageId, $platform, $cloudLocation = '')
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_CREATE);

        $image = Image::findOne([['id' => $imageId], ['envId' => $this->getEnvironmentId()], ['platform' => $platform], ['cloudLocation' => $cloudLocation]]);
        if ($image) {
            $this->response->failure('This Image has already been registered in Scalr.');
            return;
        }

        if (Image::findOne([['id' => $imageId], ['envId' => NULL], ['platform' => $platform], ['cloudLocation' => $cloudLocation]])) {
            $this->response->failure('This Image has already been registered in the Scalr Scope.');
            return;
        }

        $image = new Image();
        $image->envId = $this->getEnvironmentId();
        $image->id = $imageId;
        $image->platform = $platform;
        $image->cloudLocation = $cloudLocation;
        if ($image->checkImage()) {
            $this->response->data(['data' => [
                'name' => $image->name ? $image->name : $image->id,
                'size' => $image->size,
                'architecture' => $image->architecture
            ]]);
        } else {
            $this->response->failure("This Image does not exist, or isn't usable by your account");
        }
    }

    /**
     * @param   string  $imageId
     * @param   string  $platform
     * @param   string  $cloudLocation
     * @param   string  $name
     * @param   string  $architecture
     * @param   int     $size
     * @param   string  $os
     * @param   string  $osFamily
     * @param   string  $osGeneration
     * @param   string  $osVersion
     * @param   string  $ec2Type
     * @param   bool    $ec2Hvm
     * @param   array   $software
     */
    public function xSaveAction($imageId, $platform, $cloudLocation = '', $name, $architecture = '', $size = NULL, $os = NULL, $osFamily = NULL, $osGeneration = NULL, $osVersion = NULL, $ec2Type = NULL, $ec2Hvm = NULL, $software = [])
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_CREATE);

        $image = Image::findOne([['id' => $imageId], ['envId' => $this->getEnvironmentId(true)], ['platform' => $platform], ['cloudLocation' => $cloudLocation]]);
        if ($image) {
            $this->response->failure('This Image has already been registered in Scalr.');
            return;
        }

        if (Image::findOne([['id' => $imageId], ['envId' => NULL], ['platform' => $platform], ['cloudLocation' => $cloudLocation]])) {
            $this->response->failure('This Image has already been registered in the Scalr Scope.');
            return;
        }

        $image = new Image();
        $image->envId = $this->getEnvironmentId(true);
        $image->id = $imageId;
        $image->platform = $platform;
        $image->cloudLocation = $cloudLocation;
        $image->architecture = '';

        if ($this->user->isScalrAdmin()) {
            $image->architecture = $architecture;
            $image->size = $size;
            if ($platform == SERVER_PLATFORMS::EC2) {
                if ($ec2Type == 'ebs' || $ec2Type == 'instance-store') {
                    $image->type = $ec2Type;
                    if ($ec2Hvm) {
                        $image->type = $image->type . '-hvm';
                    }
                }
            }
        } else {
            if (!$image->checkImage(true)) {
                $this->response->failure("This Image does not exist, or isn't usable by your account");
                return;
            }
        }

        $image->name = $name;
        $image->source = Image::SOURCE_MANUAL;
        $image->os = $os;
        $image->osFamily = $osFamily;
        $image->osGeneration = $osGeneration;
        $image->osVersion = $osVersion;
        $image->createdById = $this->user->getId();
        $image->createdByEmail = $this->user->getEmail();
        $image->status = Image::STATUS_ACTIVE;

        $image->save();

        if (count($software)) {
            $props = [];
            foreach ($software as $value)
                $props[$value] = null;

            $image->setSoftware($props);
        }

        $this->response->success('Image has been added');
    }

    /**
     * @param $id
     * @param $platform
     * @param $cloudLocation
     * @param $name
     */
    public function xUpdateNameAction($id, $platform, $cloudLocation, $name)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_MANAGE);

        if (! \Scalr\Role\Role::validateName($name)) {
            $this->response->failure('Invalid name for image');
        }

        /* @var Image $image */
        $image = Image::findOne([['id' => $id], ['envId' => $this->getEnvironmentId(true)], ['platform' => $platform], ['cloudLocation' => $cloudLocation]]);
        if (!$image) {
            $this->response->failure('Image not found');
            return;
        }

        $image->name = $name;
        $image->save();

        $this->response->success('Image\'s name was updated');
    }

    /**
     * @param JsonData $images
     * @param bool $removeFromCloud
     * @throws Scalr_Exception_Core
     * @throws \Scalr\Exception\ModelException
     */
    public function xRemoveAction(JsonData $images, $removeFromCloud = false)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS_IMAGES, Acl::PERM_FARMS_IMAGES_MANAGE);

        foreach ($images as $i) {
            /* @var Image $im */
            $im = Image::findOne([['id' => $i['id']], ['envId' => $this->getEnvironmentId(true)], ['platform' => $i['platform']], ['cloudLocation' => $i['cloudLocation']]]);
            if ($im) {
                if (! $im->getUsed()) {
                    if ($removeFromCloud && $this->user->isUser()) {
                        if ($im->isUsedGlobal()) {
                            throw new Exception(sprintf("Unable to delete %s, this Image may be:\n- Still registered in another Environment or Account\n- Currently in-use by a Server in another Environment", $im->id));
                        }

                        $im->status = Image::STATUS_DELETE;
                        $im->save();
                    } else {
                        $im->delete();
                    }
                }
            }
        }

        $this->response->success('Selected images were removed');
    }
}
