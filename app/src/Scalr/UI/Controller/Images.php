<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\Os;
use Scalr\UI\Request\JsonData;
use Scalr\Modules\PlatformFactory;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Role;

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
        $this->restrictAccess('IMAGES');

        $this->response->page('ui/images/view.js', [
            'platforms' => Image::getPlatforms($this->user->getAccountId() ?: null, $this->getEnvironmentId(true))
        ]);
    }

    public function createAction()
    {
        if ($this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE) ||
            $this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_BUILD) ||
            $this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_IMPORT)
        ) {
            $this->response->page('ui/images/create.js');
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    public function registerAction()
    {
        $this->restrictAccess('IMAGES', 'MANAGE');
        $this->response->page('ui/images/register.js');
    }

    /**
     * @param string $query
     * @param string $platform
     * @param string $cloudLocation
     * @param string $scope
     * @param string $osFamily
     * @param string $osId
     * @param string $id
     * @param string $hash
     * @param JsonData $sort
     * @param int $start
     * @param int $limit
     * @param JsonData $hideLocation
     * @param bool $hideNotActive
     * @throws Exception
     */
    public function xListAction($query = null, $platform = null, $cloudLocation = null, $scope = null, $osFamily = null, $osId = null, $id = null, $hash = null, JsonData $sort, $start = 0, $limit = 20, JsonData $hideLocation, $hideNotActive = false)
    {
        $this->restrictAccess('IMAGES');

        $osIds = $criteria = [];
        $accountId = $this->user->getAccountId() ?: NULL;
        $envId = $this->getEnvironmentId(true);

        if ($this->request->getScope() == ScopeInterface::SCOPE_SCALR) {
            $criteria[] = ['accountId' => NULL];
        } else if ($this->request->getScope() == ScopeInterface::SCOPE_ACCOUNT) {
            $criteria[] = ['$or' => [['accountId' => $accountId], ['accountId' => NULL]]];
            $criteria[] = ['envId' => NULL];
        } else {
            $enabledPlatforms = $this->getEnvironment()->getEnabledPlatforms();
            $criteria[] = ['$or' => [
                ['$and' => [['accountId' => NULL], ['platform' => empty($enabledPlatforms) ? NULL : ['$in' => $enabledPlatforms]]]],
                ['$and' => [['accountId' => $accountId], ['envId' => NULL]]],
                ['envId' => $envId]
            ]];
        }

        if ($hash) {
            $criteria[] = ['hash' => $hash];
        } else {
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

            if ($scope == ScopeInterface::SCOPE_SCALR) {
                $criteria[] = ['accountId' => NULL];
            } else if ($scope == ScopeInterface::SCOPE_ACCOUNT) {
                $criteria[] = ['accountId' => $accountId];
                $criteria[] = ['envId' => NULL];
            } else if ($scope == ScopeInterface::SCOPE_ENVIRONMENT) {
                $criteria[] = ['envId' => $envId];
            }

            if ($osFamily) {
                $osIds = Os::findIdsBy($osFamily);
            }

            if ($osId) {
                $os = Os::find([['id' => $osId]]);
                $osIds = [];
                foreach ($os as $i) {
                    /* @var $i Os */
                    array_push($osIds, $i->id);
                }
            }

            if (!empty($osIds)) {
                $criteria[] = ['osId' => ['$in' => $osIds]];
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

        }

        $image = new Image();
        $os = new Os();

        $order = \Scalr\UI\Utils::convertOrder($sort, ['dtAdded' => false], ['id', 'platform', 'cloudLocation', 'name', 'osId', 'dtAdded', 'architecture', 'createdByEmail', 'source', 'type']);
        if (!empty($order)) {
            $sOrder = '';
            foreach ($order as $k => $v) {
                if ($k == 'osId') {
                    $sOrder .= ', IF(os.family IS NOT NULL, os.family, images.os_id)' . ($v ? '' : ' DESC') . ", CAST(os.version AS DECIMAL(10,2))" . ($v ? '' : ' DESC');
                } else {
                    $field = $image->getIterator()->getField($k);
                    if (!$field) {
                        throw new InvalidArgumentException(sprintf(
                            "Property %s does not exist in %s",
                            $k, get_class($image)
                        ));
                    }
                    $sOrder .= ', ' . $field->getColumnName() . ($v ? '' : ' DESC');
                }
            }
            $sOrder = ($sOrder != '' ? 'ORDER BY ' . substr($sOrder, 2) : '');
        }

        $result = $this->db->Execute("
            SELECT " . (isset($limit) ? 'SQL_CALC_FOUND_ROWS ' : '') . $image->fields('images') . "
            FROM " . $image->table('images') . "
            LEFT JOIN " . $os->table('os') . " ON {$os->columnId} = {$image->columnOsId}
            WHERE " . $image->_buildQuery($criteria, 'AND', 'images')['where'] . "
            " . (!empty($sOrder) ? $sOrder : "") . "
            " . (isset($limit) ? "LIMIT " . ($start ? intval($start) . ',' : '') . intval($limit) : "") . "
        ");

        if (isset($limit)) {
            $totalNumber = $this->db->getOne('SELECT FOUND_ROWS()');
        } else {
            $totalNumber = $result->RowCount();
        }

        $data = [];
        while ($rec = $result->FetchRow()) {
            $image = new Image();
            $image->load($rec);

            $s = get_object_vars($image);
            $dtAdded = $image->getDtAdded();

            $s['dtAdded'] = $dtAdded ? Scalr_Util_DateTime::convertTz($dtAdded) : '';
            $s['dtLastUsed'] = $image->dtLastUsed ? Scalr_Util_DateTime::convertTz($image->dtLastUsed) : '';
            $s['used'] = $image->getUsed($accountId, $envId);
            $s['software'] = $image->getSoftwareAsString();
            $s['osFamily'] = $image->getOs()->family;
            $s['osGeneration'] = $image->getOs()->generation;
            $s['osVersion'] = $image->getOs()->version;
            $s['os'] = $image->getOs()->name;
            $s['scope'] = $image->getScope();
            $data[] = $s;
        }

        $this->response->data([
            'total' => $totalNumber,
            'data' => $data
        ]);
    }

    /**
     * @param string $osFamily
     * @param string $osVersion
     */
    public function xGetRoleImagesAction($osFamily, $osVersion)
    {
        $this->restrictAccess('IMAGES', 'MANAGE');

        $data = [];

        $osIds = Os::findIdsBy($osFamily, null, $osVersion);

        foreach (Image::find([
            ['$or' => [['envId' => $this->getEnvironmentId(true)], ['envId' => NULL]]],
            ['osId' => ['$in' => $osIds]],
            ['status' => Image::STATUS_ACTIVE]
        ]) as $image) {
            /* @var $image Image */
            $data[] = [
                'platform' => $image->platform,
                'cloudLocation' => $image->cloudLocation,
                'id' => $image->id,
                'architecture' => $image->architecture,
                'source' => $image->source,
                'createdByEmail' => $image->createdByEmail,
                'os_family' => $image->getOs()->family,
                'os_generation' => $image->getOs()->generation,
                'os_version' => $image->getOs()->version,
                'os_id' => $image->getOs()->id,
                'os' => $image->getOs()->name
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
        $this->request->restrictAccess(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);

        /* @var $image Image */
        $image = Image::findOne([['platform' => SERVER_PLATFORMS::EC2], ['id' => $id], ['cloudLocation' => $cloudLocation], ['envId' => $this->getEnvironmentId()]]);
        if (! $image)
            throw new Exception('Image not found');

        $this->checkPermissions($image, true);

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
     * @param string $id
     * @param string $cloudLocation
     * @param string $destinationRegion
     * @throws Exception
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xEc2MigrateAction($id, $cloudLocation, $destinationRegion)
    {
        $this->request->restrictAccess(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);

        /* @var $image Image */
        $image = Image::findOne([['platform' => SERVER_PLATFORMS::EC2], ['id' => $id], ['cloudLocation' => $cloudLocation], ['envId' => $this->getEnvironmentId()]]);
        if (! $image)
            throw new Exception('Image not found');

        $this->checkPermissions($image, true);

        $newImage = $image->migrateEc2Location($destinationRegion, $this->request->getUser());

        $this->response->data(['hash' => $newImage->hash]);
        $this->response->success('Image successfully migrated');
    }

    /**
     * @param   string  $imageId
     * @param   string  $platform
     * @param   string  $cloudLocation
     * @throws  Scalr_Exception_Core
     */
    public function xCheckAction($imageId, $platform, $cloudLocation = '')
    {
        $this->request->restrictAccess(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);

        if ($platform == SERVER_PLATFORMS::GCE || $platform == SERVER_PLATFORMS::AZURE) {
            $cloudLocation = '';
        }

        if (($accountId = $this->user->getAccountId())) {
            if (($envId = $this->getEnvironmentId(true))) {
                if (Image::findOne([['id' => $imageId], ['envId' => $envId], ['platform' => $platform], ['cloudLocation' => $cloudLocation]])) {
                    throw new Scalr_Exception_Core('This Image has already been registered in the Environment Scope.');
                }
            }

            if (Image::findOne([['id' => $imageId], ['accountId' => $this->user->getAccountId()], ['envId' => null], ['platform' => $platform], ['cloudLocation' => $cloudLocation]])) {
                throw new Scalr_Exception_Core('This Image has already been registered in the Account Scope.');
            }
        }

        if (Image::findOne([['id' => $imageId], ['accountId' => null], ['platform' => $platform], ['cloudLocation' => $cloudLocation]])) {
            $this->response->failure('This Image has already been registered in the Scalr Scope.');
            return;
        }

        $image = new Image();
        $image->envId = $this->getEnvironmentId();
        $image->id = $imageId;
        $image->platform = $platform;
        $image->cloudLocation = $cloudLocation;
        if (($data = $image->checkImage()) !== false) {
            $data['name'] = $image->name ? $image->name : $image->id;
            $data['size'] = $image->size;
            $data['architecture'] = $image->architecture ? $image->architecture : 'x86_64';

            if ($image->platform == SERVER_PLATFORMS::EC2) {
                $data['ec2Type'] = strstr($image->type, 'ebs') ? 'ebs' : 'instance-store';
                $data['ec2Hvm']  = strstr($image->type, 'hvm') !== false;
            }

            $this->response->data(['data' => $data]);
        } else {
            $this->response->failure("This Image does not exist, or isn't usable by your account");
        }
    }

    /**
     * @param   string   $imageId
     * @param   string   $platform
     * @param   string   $osId
     * @param   string   $name
     * @param   string   $cloudLocation
     * @param   string   $architecture
     * @param   int      $size
     * @param   string   $ec2Type
     * @param   bool     $ec2Hvm
     * @param   JsonData $software
     * @throws  Scalr_Exception_Core
     */
    public function xSaveAction($imageId, $platform, $osId, $name, $cloudLocation = '', $architecture = '', $size = null, $ec2Type = null, $ec2Hvm = null, JsonData $software = null)
    {
        $this->restrictAccess('IMAGES', 'MANAGE');

        if ($platform == SERVER_PLATFORMS::GCE || $platform == SERVER_PLATFORMS::AZURE) {
            $cloudLocation = '';
        }

        if (($accountId = $this->user->getAccountId())) {
            if (($envId = $this->getEnvironmentId(true))) {
                if (Image::findOne([['id' => $imageId], ['envId' => $envId], ['platform' => $platform], ['cloudLocation' => $cloudLocation]])) {
                    throw new Scalr_Exception_Core('This Image has already been registered in the Environment Scope.');
                }
            }

            if (Image::findOne([['id' => $imageId], ['accountId' => $this->user->getAccountId()], ['envId' => null], ['platform' => $platform], ['cloudLocation' => $cloudLocation]])) {
                throw new Scalr_Exception_Core('This Image has already been registered in the Account Scope.');
            }
        }

        if (Image::findOne([['id' => $imageId], ['accountId' => null], ['platform' => $platform], ['cloudLocation' => $cloudLocation]])) {
            $this->response->failure('This Image has already been registered in the Scalr Scope.');
            return;
        }

        if (!Role::isValidName($name)) {
            $this->response->failure('Name should start and end with letter or number and contain only letters, numbers and dashes.');
            return;
        }

        $image = new Image();
        $image->accountId = $this->user->getAccountId() ?: null;
        $image->envId = $this->getEnvironmentId(true);
        $image->id = $imageId;
        $image->platform = $platform;
        $image->cloudLocation = $cloudLocation;
        $image->architecture = 'x86_64';

        if ($this->request->getScope() == ScopeInterface::SCOPE_ENVIRONMENT) {
            if ($image->checkImage() === false) {
                $this->response->failure("This Image does not exist, or isn't usable by your account");
                return;
            }
        } else {
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
        }

        $image->name = $name;
        $image->source = Image::SOURCE_MANUAL;
        $image->osId = $osId;
        $image->createdById = $this->user->getId();
        $image->createdByEmail = $this->user->getEmail();
        $image->status = Image::STATUS_ACTIVE;

        $image->save();

        $props = [];

        foreach ($software as $value) {
            $props[$value] = null;
        }

        $image->setSoftware($props);

        $this->response->data(['hash' => $image->hash]);
        $this->response->success('Image has been added');
    }

    /**
     * @param   string  $hash
     * @param   string  $name
     */
    public function xUpdateNameAction($hash, $name)
    {
        $this->restrictAccess('IMAGES', 'MANAGE');

        if (! \Scalr\Model\Entity\Role::isValidName($name)) {
            $this->response->failure('Invalid name for image');
            return;
        }

        /* @var $image Image */
        $image = Image::findPk($hash);
        if (!$image) {
            $this->response->failure('Image not found');
            return;
        }
        $this->checkPermissions($image, true);

        $image->name = $name;
        $image->save();

        $this->response->data(['name' => $name]);
        $this->response->success('Image\'s name was updated');
    }

    /**
     * @param JsonData $images
     * @param bool     $removeFromCloud
     * @throws Scalr_Exception_Core
     * @throws \Scalr\Exception\ModelException
     */
    public function xRemoveAction(JsonData $images, $removeFromCloud = false)
    {
        $this->restrictAccess('IMAGES', 'MANAGE');
        $errors = [];
        $processed = [];
        $pending = [];

        foreach ($images as $hash) {
            try {
                /* @var $im Image */
                $im = Image::findPk($hash);
                if ($im) {
                    $this->checkPermissions($im, true);

                    if (! $im->getUsed()) {
                        if ($removeFromCloud && $this->user->isUser()) {
                            if ($im->isUsedGlobal()) {
                                throw new Exception(sprintf("Unable to delete %s, this Image may be:\n- Still registered in another Environment or Account\n- Currently in-use by a Server in another Environment", $im->id));
                            }

                            $im->status = Image::STATUS_DELETE;
                            $im->save();
                            $pending[] = $im->hash;
                        } else {
                            if ($this->user->isScalrAdmin() && $im->isUsedGlobal())
                                throw new Exception(sprintf("Unable to delete %s, this Image may be:\n- Still registered in another Environment or Account\n- Currently in-use by a Server in another Environment", $im->id));

                            $im->delete();
                            $processed[] = $im->hash;
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->response->data(['processed' => $processed, 'pending' => $pending]);
        if (count($errors))
            $this->response->warning("Images(s) successfully removed, but some errors occurred:\n" . implode("\n", $errors));
        else
            $this->response->success('Images(s) successfully removed');
    }
}
