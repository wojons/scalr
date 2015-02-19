<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\ImageSoftware;
use Scalr_Environment;
use SERVER_PLATFORMS;
use Exception;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\GoogleCE\GoogleCEPlatformModule;
use Scalr\Modules\Platforms\Rackspace\RackspacePlatformModule;

/**
 * Image entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (23.05.2014)
 *
 * @Entity
 * @Table(name="images")
 */
class Image extends AbstractEntity
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DELETE = 'delete';
    const STATUS_FAILED = 'failed';

    const SOURCE_MANUAL = 'Manual';
    const SOURCE_BUNDLE_TASK = 'BundleTask';

    /**
     * Hash (primary key)
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $hash;

    /**
     * Image ID
     *
     * @Column(type="string")
     * @var string
     */
    public $id;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $envId;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $bundleTaskId;

    /**
     * @Column(type="string")
     * @var string
     */
    public $platform;

    /**
     * @Column(type="string")
     * @var string
     */
    public $cloudLocation;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $name;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $os;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $osFamily;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $osGeneration;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $osVersion;

    /**
     * @Column(type="datetime",nullable=true)
     * @var \DateTime
     */
    public $dtAdded;
    
    /**
     * @Column(type="datetime",nullable=true)
     * @var \DateTime
     */
    public $dtLastUsed;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $createdById;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $createdByEmail;

    /**
     * @Column(type="string")
     * @var string
     */
    public $architecture;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $size;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $isDeprecated;

    /**
     * @Column(type="string")
     * @var string
     */
    public $source;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $type;

    /**
     * @Column(type="string")
     * @var string
     */
    public $status;

    /**
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $statusError;

    /**
     * @Column(type="string")
     * @var string
     */
    public $agentVersion;

    /**
     * @var Scalr_Environment
     */
    protected $_environment = null;

    public function __construct()
    {
        // first records don't have dtAdded, we keep it null
        $this->dtAdded = new \DateTime();
        $this->isDeprecated = 0;
    }

    public function save()
    {
        if ($this->platform == \SERVER_PLATFORMS::GCE || $this->platform == \SERVER_PLATFORMS::ECS)
            $this->cloudLocation = ''; // image on GCE and ECS doesn't require cloudLocation

        $hash = self::calculateHash($this->envId, $this->id, $this->platform, $this->cloudLocation);
        if (! $this->hash) {
            $this->hash = $hash;
        } else if ($this->hash != $hash) {
            throw new Exception('Hash are mismatched in entity Image');
        }

        parent::save();
    }

    /**
     * Calculates uuid for the specified entity
     *
     * @param   int       $envId
     * @param   string    $id
     * @param   string    $platform       Cloud platform
     * @param   string    $cloudLocation  Cloud location
     * @return  string    Returns UUID
     */
    public static function calculateHash($envId, $id, $platform, $cloudLocation)
    {
        $hash = sha1(sprintf("%s;%s;%s;%s", $envId ? $envId : 0, $id, $platform, $cloudLocation));

        return sprintf(
            "%s-%s-%s-%s-%s",
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    /**
     * Get image's usage in this environment (servers, roles)
     *
     * @return array|false
     * @throws \Scalr\Exception\ModelException
     */
    public function getUsed()
    {
        $status = [];
        $status['rolesCount'] = $this->db()->GetOne('SELECT count(*) FROM role_images ri JOIN roles r ON r.id = ri.role_id ' .
            'WHERE ri.image_id = ? AND ri.platform = ? AND ri.cloud_location = ? AND r.env_id = ?',
            [$this->id, $this->platform, $this->cloudLocation, $this->envId == NULL ? 0 : $this->envId]
        );

        if ($status['rolesCount'] == 1) {
            $status['roleName'] = $this->db()->GetOne('SELECT r.name FROM role_images ri JOIN roles r ON r.id = ri.role_id ' .
                'WHERE ri.image_id = ? AND ri.platform = ? AND ri.cloud_location = ? AND r.env_id = ?',
                [$this->id, $this->platform, $this->cloudLocation, $this->envId == NULL ? 0 : $this->envId]
            );
        }

        if ($this->platform == \SERVER_PLATFORMS::GCE || $this->platform == \SERVER_PLATFORMS::ECS) {
            $status['serversCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM servers WHERE image_id = ? AND platform = ? AND env_id = ?',
                    [$this->id, $this->platform, $this->envId]);
        } else {
            $status['serversCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM servers WHERE image_id = ? AND platform = ? AND cloud_location = ? AND env_id = ?',
                    [$this->id, $this->platform, $this->cloudLocation, $this->envId]);
        }

        return $status['rolesCount'] == 0 && $status['serversCount'] == 0 ? false : $status;
    }

    /**
     * If image is used in any environment (servers, farmRoles) or had duplicates in another environments
     *
     * @return bool
     * @throws \Scalr\Exception\ModelException
     */
    public function isUsedGlobal()
    {
        $status = !!$this->db()->GetOne('SELECT EXISTS(SELECT 1 FROM role_images WHERE image_id = ? AND platform = ? AND cloud_location = ?)', [$this->id, $this->platform, $this->cloudLocation]);

        if ($this->platform == \SERVER_PLATFORMS::GCE || $this->platform == \SERVER_PLATFORMS::ECS) {
            $status = $status || !!$this->db()->GetOne('SELECT EXISTS(SELECT 1 FROM servers WHERE image_id = ? AND platform = ? AND env_id = ?)',
                    [$this->id, $this->platform, $this->envId]);
        } else {
            $status = $status || !!$this->db()->GetOne('SELECT EXISTS(SELECT 1 FROM servers WHERE image_id = ? AND platform = ? AND cloud_location = ? AND env_id = ?)',
                    [$this->id, $this->platform, $this->cloudLocation, $this->envId]);
        }

        $status = $status || (Image::find([['id' => $this->id], ['platform' => $this->platform], ['cloudLocation' => $this->cloudLocation]])->count() > 1);

        return $status;

    }

    /**
     * @return string
     */
    public function getSoftwareAsString()
    {
        $result = [];
        foreach ($this->getSoftware() as $name => $version) {
            $result[] = $name . ($version ? ' ' . $version : '');
        }

        return join(', ', $result);
    }

    /**
     * @return array
     */
    public function getSoftware()
    {
        $result = [];
        foreach (ImageSoftware::find([['imageHash' => $this->hash]]) as $rec) {
            /* @var ImageSoftware $rec */
            $result[$rec->name] = $rec->version;
        }

        return $result;
    }

    /**
     * @param array $props
     */
    public function setSoftware($props)
    {
        foreach (ImageSoftware::find([['imageHash' => $this->hash]]) as $rec) {
            /* @var ImageSoftware $rec */
            $rec->delete();
        }

        foreach ($props as $name => $version) {
            if ($name) {
                $rec = new ImageSoftware();
                $rec->imageHash = $this->hash;
                $rec->name = $name;
                $rec->version = $version;
                $rec->save();
            }
        }
    }

    /**
     * Return NULL, if image is owned by admin
     *
     * @return null|Scalr_Environment
     * @throws Exception
     */
    public function getEnvironment()
    {
        if (! $this->envId)
            return NULL;

        if (! $this->_environment) {
            $this->_environment = new Scalr_Environment();
            $this->_environment->loadById($this->envId);
        }

        return $this->_environment;
    }

    /**
     * @return bool
     */
    public function isEc2EbsImage()
    {
        return ($this->platform == SERVER_PLATFORMS::EC2) && !!strstr($this->type, 'ebs');
    }

    /**
     * @return bool
     */
    public function isEc2HvmImage()
    {
        return ($this->platform == SERVER_PLATFORMS::EC2) && !!strstr($this->type, 'hvm');
    }

    /**
     * @param $envId
     * @return array
     */
    public static function getEnvironmentPlatforms($envId)
    {
        return \Scalr::getDb()->GetCol('SELECT DISTINCT platform FROM `images` WHERE env_id = ?', [$envId]);
    }

    /**
     * Check if image exists
     *
     * @param bool $update on true update name, size, status from cloud information (you should call save manually)
     * @return bool
     */
    public function checkImage($update = true)
    {
        if (! $this->envId)
            return true;

        $env = Scalr_Environment::init()->loadById($this->envId);

        switch ($this->platform) {
            case SERVER_PLATFORMS::EC2:
                try {
                    $snap = $env->aws($this->cloudLocation)->ec2->image->describe($this->id);
                    if ($snap->count() == 0) {
                        return false;
                    }

                    if ($update) {
                        $sn = $snap->get(0)->toArray();
                        $this->name = $sn['name'];
                        $this->architecture = $sn['architecture'];

                        if ($sn['rootDeviceType'] == 'ebs') {
                            $this->type = 'ebs';
                        } else if ($sn['rootDeviceType'] == 'instance-store') {
                            $this->type = 'instance-store';
                        }

                        if ($sn['virtualizationType'] == 'hvm') {
                            $this->type = $this->type . '-hvm';
                        }

                        foreach ($sn['blockDeviceMapping'] as $b) {
                            if (($b['deviceName'] == $sn['rootDeviceName']) && $b['ebs']) {
                                $this->size = $b['ebs']['volumeSize'];
                            }
                        }
                    }
                } catch (Exception $e) {
                    return false;
                }
                break;

            case SERVER_PLATFORMS::GCE:
                try {
                    $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::GCE);
                    /* @var $platform GoogleCEPlatformModule */
                    $client = $platform->getClient($env);
                    /* @var $client \Google_Service_Compute */

                    // for global images we use another projectId
                    $ind = strpos($this->id, '/global/');
                    if ($ind !== FALSE) {
                        $projectId = substr($this->id, 0, $ind);
                        $id = str_replace("$projectId/global/images/", '', $this->id);
                    } else {
                        $projectId = $env->getPlatformConfigValue(GoogleCEPlatformModule::PROJECT_ID);
                        $id = str_replace("$projectId/images/", '', $this->id);
                    }

                    $snap = $client->images->get($projectId, $id);

                    if ($update) {
                        $this->name = $snap->name;
                        $this->size = $snap->diskSizeGb;
                        $this->architecture = 'x86_64';
                    }

                } catch (Exception $e) {
                    return false;
                }
                break;

            case SERVER_PLATFORMS::RACKSPACE:
                try {
                    $client = \Scalr_Service_Cloud_Rackspace::newRackspaceCS(
                        $env->getPlatformConfigValue(RackspacePlatformModule::USERNAME, true, $this->cloudLocation),
                        $env->getPlatformConfigValue(RackspacePlatformModule::API_KEY, true, $this->cloudLocation),
                        $this->cloudLocation
                    );

                    $snap = $client->getImageDetails($this->id);
                    if ($snap) {
                        if ($update) {
                            $this->name = $snap->image->name;
                        }
                    } else {
                        return false;
                    }
                } catch (\Exception $e) {
                    return false;
                }
                break;

            default:
                if (PlatformFactory::isOpenstack($this->platform)) {
                    try {
                        $snap = $env->openstack($this->platform, $this->cloudLocation)->servers->getImage($this->id);
                        if ($snap) {
                            if ($update) {
                                $this->name = $snap->name;
                                $this->size = $snap->metadata->instance_type_root_gb;
                            }
                        } else {
                            return false;
                        }

                    } catch (\Exception $e) {
                        return false;
                    }
                } else if (PlatformFactory::isCloudstack($this->platform)) {
                    try {
                        $snap = $env->cloudstack($this->platform)->template->describe(['templatefilter' => 'executable', 'id' => $this->id, 'zoneid' => $this->cloudLocation]);
                        if ($snap && isset($snap[0])) {
                            if ($update) {
                                $this->name = $snap[0]->name;
                                $this->size = ceil($snap[0]->size / (1024*1024*1024));
                            }
                        } else {
                            return false;
                        }
                    } catch (\Exception $e) {
                        return false;
                    }
                } else {
                    return false;
                }
        }

        return true;
    }

    public function deleteCloudImage()
    {
        return PlatformFactory::NewPlatform($this->platform)->RemoveServerSnapshot($this);
    }
}
