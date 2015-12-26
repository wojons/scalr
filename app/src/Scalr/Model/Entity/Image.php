<?php
namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;
use Scalr_Environment;
use SERVER_PLATFORMS;
use Exception;
use DomainException;
use Scalr\Modules\PlatformFactory;
use Scalr\DataType\ScopeInterface;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Exception\NotEnabledPlatformException;
use Scalr\Model\Entity;

/**
 * Image entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (23.05.2014)
 *
 * @Entity
 * @Table(name="images")
 */
class Image extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DELETE = 'delete';
    const STATUS_FAILED = 'failed';

    const SOURCE_MANUAL = 'Manual';
    const SOURCE_BUNDLE_TASK = 'BundleTask';

    const NULL_YEAR = '1971';

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
    public $accountId;

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
     * @Column(type="string")
     * @var string
     */
    public $osId;

    /**
     * @Column(type="datetime",nullable=true)
     * @var DateTime
     */
    public $dtAdded;

    /**
     * @Column(type="datetime",nullable=true)
     * @var DateTime
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
     * @Column(type="boolean")
     * @var bool
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

    /**
     * @var Os
     */
    private $_os;

    public function __construct()
    {
        // first records don't have dtAdded, we keep it null
        $this->dtAdded = new DateTime();
        $this->isDeprecated = false;
    }

    /**
     * Gets normalized dtAdded
     *
     * @return DateTime|null
     */
    public function getDtAdded()
    {
        return ($this->dtAdded !== null && $this->dtAdded->format('Y') == static::NULL_YEAR) ? null : $this->dtAdded;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->envId) ? self::SCOPE_ENVIRONMENT : (!empty($this->accountId) ? self::SCOPE_ACCOUNT : self::SCOPE_SCALR);
    }

    /**
     * @return Os
     * @throws \Exception
     */
    public function getOs()
    {
        if (!$this->_os) {
            $this->_os = Os::findOne([['id' => $this->osId]]);
        }

        return $this->_os;
    }

    public function save()
    {
        if ($this->platform == \SERVER_PLATFORMS::GCE || $this->platform == \SERVER_PLATFORMS::AZURE)
            $this->cloudLocation = ''; // image on GCE or Azure doesn't require cloudLocation

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
     * @param   int          $accountId   optional
     * @param   int          $envId       optional
     * @return  array|false  Return array of [rolesCount, serversCount] or FALSE on failure
     * @throws  \Scalr\Exception\ModelException
     */
    public function getUsed($accountId = null, $envId = null)
    {
        $status = [];
        $sql = "
            SELECT (
                SELECT COUNT(*)
                FROM servers r
                WHERE r.image_id = ? AND r.platform = ? {CLOUD_LOCATION} {SERVERS}
            ) AS serversEnvironment, (
                SELECT r.name
                FROM role_images ri
                JOIN roles r ON r.id = ri.role_id
                WHERE ri.image_id = ? AND ri.platform = ? AND ri.cloud_location = ? {ROLES}
                LIMIT 1
            ) AS roleName,
            SUM(IF(r.client_id IS NULL, 1, 0)) AS rolesScalr,
            SUM(IF(r.client_id IS NOT NULL AND r.env_id IS NULL, 1, 0)) AS rolesAccount,
            SUM(IF(r.client_id IS NOT NULL AND r.env_id IS NOT NULL, 1, 0)) AS rolesEnvironment
            FROM role_images ri
            JOIN roles r ON r.id = ri.role_id
            WHERE ri.image_id = ? AND ri.platform = ? AND ri.cloud_location = ? {IMAGES}
        ";
        $args = [$this->id, $this->platform, $this->id, $this->platform, $this->cloudLocation, $this->id, $this->platform, $this->cloudLocation];
        $sql = str_replace("{CLOUD_LOCATION}", $this->platform != \SERVER_PLATFORMS::GCE ? " AND r.cloud_location = " . $this->db()->qstr($this->cloudLocation) : "", $sql);

        if ($this->accountId && $this->envId || $this->accountId && !$this->envId && $envId || !$this->accountId && $accountId && $envId) {
            if ($this->accountId && $this->envId) {
                // environment image
                $id = $this->envId;
            } else {
                // account image in this environment
                // scalr image in this environment
                $id = $envId;
            }

            $status = $this->db()->GetRow(str_replace([
                    "{SERVERS}",
                    "{ROLES}",
                    "{IMAGES}"
                ], [
                    " AND r.env_id = " . $this->db()->qstr($id),
                    " AND r.env_id = " . $this->db()->qstr($id),
                    " AND (r.env_id = " . $this->db()->qstr($id) . " OR r.client_id = " . $this->db()->qstr($this->accountId) . " AND r.env_id IS NULL)"
                ],
                $sql
            ), $args);

        } else if ($this->accountId && !$this->envId && !$envId || !$this->accountId && $accountId && !$envId) {
            if ($this->accountId && !$this->envId && !$envId) {
                // account image
                $id = $this->accountId;
            } else {
                // scalr image on account scope
                $id = $accountId;
            }

            $status = $this->db()->GetRow(str_replace([
                    "{SERVERS}",
                    "{ROLES}",
                    "{IMAGES}"
                ], [
                    " AND r.client_id = " . $this->db()->qstr($id),
                    " AND r.env_id IS NULL AND r.client_id = " . $this->db()->qstr($id),
                    " AND r.client_id = " . $this->db()->qstr($id)
                ],
                $sql
            ), $args);
        } else if (!$this->accountId && !$accountId) {
            // scalr image
            $status = $this->db()->GetRow(str_replace([
                    "{SERVERS}",
                    "{ROLES}",
                    "{IMAGES}"
                ], [
                    "",
                    " AND r.client_id IS NULL",
                    ""
                ],
                $sql
            ), $args);
        }

        $emptyValue = true;
        foreach ($status as $name => &$cnt) {
            if (is_numeric($cnt) || is_null($cnt)) {
                $cnt = (int) $cnt;
            }

            if (is_int($cnt) && $cnt > 0) {
                $emptyValue = false;
            }
        }

        return $emptyValue ? false : $status;
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

        if ($this->platform == \SERVER_PLATFORMS::GCE) {
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
            /* @var $rec ImageSoftware */
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
            /* @var $rec ImageSoftware */
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
     * @return  bool  Return TRUE if image is ebs based
     */
    public function isEc2EbsImage()
    {
        return ($this->platform == SERVER_PLATFORMS::EC2) && !!strstr($this->type, 'ebs');
    }

    /**
     * @return  bool  Return TRUE if image is hvm
     */
    public function isEc2HvmImage()
    {
        return ($this->platform == SERVER_PLATFORMS::EC2) && !!strstr($this->type, 'hvm');
    }

    /**
     * @return  bool  Return TRUE if image is instance-store based
     */
    public function isEc2InstanceStoreImage()
    {
        return ($this->platform == SERVER_PLATFORMS::EC2) && !!strstr($this->type, 'instance-store');
    }

    /**
     * Get array of image's platforms
     *
     * @param   int     $accountId
     * @param   int     $envId
     * @return  array   Array of platform's names
     */
    public static function getPlatforms($accountId, $envId)
    {
        $sql = "SELECT DISTINCT `platform` FROM `images` WHERE account_id = ?";
        $args[] = $accountId;

        if ($envId) {
            $sql .= " AND env_id = ?";
            $args[] = $envId;
        }

        return \Scalr::getDb()->GetCol($sql, $args);
    }

    /**
     * Check if image exists and return more info if special data exists
     *
     * @return bool|array Returns array of data if exists when update is required
     */
    public function checkImage()
    {
        $info = [];

        if (!empty($this->envId)) {
            try {
                $env = Scalr_Environment::init()->loadById($this->envId);

                try {
                    if (empty($info = PlatformFactory::NewPlatform($this->platform)->getImageInfo($env, $this->cloudLocation, $this->id))) {
                        return false;
                    }

                    foreach (["name", "size", "architecture", "type"] as $k) {
                        if (isset($info[$k])) {
                            $this->$k = $info[$k];
                            unset($info[$k]);
                        }
                    }
                } catch (\Exception $e) {
                    return false;
                }
            } catch (\Exception $e) {}
        }

        return $info;
    }

    /**
     * Migrates an Image to another Cloud Location
     *
     * @param  string $cloudLocation The cloud location
     * @param  \Scalr_Account_User|\Scalr\Model\Entity\Account\User $user The user object
     * @return Image
     * @throws Exception
     * @throws NotEnabledPlatformException
     * @throws DomainException
     */
    public function migrateEc2Location($cloudLocation, $user)
    {
        if (!$this->getEnvironment()->isPlatformEnabled(SERVER_PLATFORMS::EC2)) {
            throw new NotEnabledPlatformException("You can migrate image between regions only on EC2 cloud");
        }

        if ($this->cloudLocation == $cloudLocation) {
            throw new DomainException('Destination region is the same as source one');
        }

        $snap = $this->getEnvironment()->aws($this->cloudLocation)->ec2->image->describe($this->id);
        if ($snap->count() == 0) {
            throw new Exception("Image haven't been found on cloud.");
        }

        if ($snap->get(0)->toArray()['imageState'] != 'available') {
            throw new Exception('Image is not in "available" status on cloud and cannot be copied.');
        }

        $this->checkImage(); // re-check properties
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $newImageId = $aws->ec2->image->copy(
            $this->cloudLocation,
            $this->id,
            $this->name,
            "Image was copied by Scalr from image: {$this->name}, cloudLocation: {$this->cloudLocation}, id: {$this->id}",
            null,
            $cloudLocation
        );

        $newImage = new Image();
        $newImage->platform = $this->platform;
        $newImage->cloudLocation = $cloudLocation;
        $newImage->id = $newImageId;
        $newImage->name = $this->name;
        $newImage->architecture = $this->architecture;
        $newImage->size = $this->size;
        $newImage->accountId = $this->accountId;
        $newImage->envId = $this->envId;
        $newImage->osId = $this->osId;
        $newImage->source = Image::SOURCE_MANUAL;
        $newImage->type = $this->type;
        $newImage->agentVersion = $this->agentVersion;
        $newImage->createdById = $user->getId();
        $newImage->createdByEmail = $user->getEmail();
        $newImage->status = Image::STATUS_ACTIVE;
        $newImage->save();
        $newImage->setSoftware($this->getSoftware());

        return $newImage;
    }

    public function deleteCloudImage()
    {
        return PlatformFactory::NewPlatform($this->platform)->RemoveServerSnapshot($this);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        switch ($this->getScope()) {
            case static::SCOPE_ENVIRONMENT:
                return $environment
                     ? $this->envId == $environment->id
                     : $user->hasAccessToEnvironment($this->envId);

            case static::SCOPE_ACCOUNT:
                return $this->accountId == $user->accountId && (empty($environment) || !$modify);

            case static::SCOPE_SCALR:
                return !$modify || $user->isScalrAdmin();

            default:
                return false;
        }
    }
}
