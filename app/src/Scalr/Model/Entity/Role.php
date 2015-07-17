<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\ImageHistory;
use Scalr\Model\Entity\RoleImage;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\Os;
use Scalr\DataType\ScopeInterface;
use Scalr\DataType\AccessPermissionsInterface;
use SERVER_PLATFORMS;
use Scalr\Exception\Model\Entity\Os\OsMismatchException;
use Scalr\Exception\Model\Entity\Image\ImageNotFoundException;
use Scalr\Exception\Model\Entity\Image\NotAcceptableImageStatusException;
use Scalr\Exception\Model\Entity\Image\ImageInUseException;

/**
 * Role entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (23.07.2014)
 *
 * @Entity
 * @Table(name="roles")
 */
class Role extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{
    /**
     * Scalr scope Role
     * @deprecated
     */
    const ORIGIN_SHARED = 'SHARED';

    /**
     * Either environment or account scope Role
     * @deprecated
     */
    const ORIGIN_CUSTOM = 'CUSTOM';

    /**
     * The identifier of the Role
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * The name of the role
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * @deprecated
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $origin = self::ORIGIN_SHARED;

    /**
     * The description
     *
     * @Column(type="string")
     * @var string
     */
    public $description;

    /**
     * The identifier of the client's account
     *
     * It is set when the Role is from account scope
     *
     * @Column(type="integer",name="client_id",nullable=true)
     * @var integer
     */
    public $accountId;

    /**
     * The identifier of the environment
     *
     * It is set when the Role is from environment scope
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $envId;

    /**
     * Identifier of the category
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $catId;

    /**
     * The list of the supported behaviors
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $behaviors;

    /**
     * Whether the role is deprecated
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $isDeprecated = false;

    /**
     * Whether it is developent Role
     *
     * @Column(name="is_devel",type="boolean")
     * @var bool
     */
    public $isDevelopment = false;

    /**
     * The generation
     *
     * @deprecated
     * @Column(type="integer")
     * @var int
     */
    public $generation = 2;

    /**
     * The timestamp when the Role was added
     *
     * @Column(name="dtadded",type="datetime",nullable=true)
     * @var \DateTime
     */
    public $added;

    /**
     * The timestamp when the Role was used last time
     *
     * @Column(name="dt_last_used",type="datetime",nullable=true)
     * @var \DateTime
     */
    public $lastUsed;

    /**
     * The identifier of the User who added the Role
     *
     * @Column(name="added_by_userid",type="integer",nullable=true)
     * @var int
     */
    public $addedByUserId;

    /**
     * The email address of the User who added the Role
     *
     * @Column(name="added_by_email",type="string",nullable=true)
     * @var int
     */
    public $addedByEmail;

    /**
     * The identifier of the OS
     *
     * @Column(type="string")
     * @var string
     */
    public $osId;

    /**
     * @var Os
     */
    private $_os;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->added = new \DateTime();
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
     * Validates the name
     *
     * @param    string $name  The name of the Role
     * @return   bool   Returns TRUE when the name is valid or FALSE otherwise
     */
    public static function validateName($name)
    {
        return !!preg_match('/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/i', $name);
    }

    /**
     * Gets the Os entity which corresponds to the Role
     *
     * @return  Os          Returns the Os entity which corresponds to the Role.
     *                      If OS has not been defined it will return NULL.
     * @throws  \Exception
     */
    public function getOs()
    {
        if (!$this->_os) {
            $this->_os = Os::findPk($this->osId);
        }

        return $this->_os;
    }

    /**
     * Finds out the Image that corresponds to the Role by the specified criteria
     *
     * @param    $platform        string The cloud platform
     * @param    $cloudLocation   string The cloud location
     * @return   RoleImage        Returns the RoleImage object.
     *                            It throws an exception when the RoleImage does not exist
     * @throws   \Exception
     */
    public function getImage($platform, $cloudLocation)
    {
        if (in_array($platform, [SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::ECS]))
            $cloudLocation = '';

        $image = RoleImage::findOne([
            [ 'roleId' => $this->id ],
            [ 'platform' => $platform ],
            [ 'cloudLocation' => $cloudLocation ]
        ]);

        if (! $image)
            throw new \Exception(sprintf(
                "No valid role image found for roleId: %d, platform: %s, cloudLocation: %s",
                $this->id, $platform, $cloudLocation
            ));

        return $image;
    }

    /**
     * Gets the list of the Images which correspond to the Role
     *
     * @return   array   Return array of all role's images in format: [platform][cloudLocation] = [id, architecture, type]
     * @throws   \Exception
     */
    public function fetchImagesArray()
    {
        $result = [];

        foreach (RoleImage::find([['roleId' => $this->id]]) as $image) {
            /* @var $image RoleImage */
            if (!$result[$image->platform])
                $result[$image->platform] = [];

            $i = ['id' => $image->imageId, 'architecture' => '', 'type' => ''];

            /* @var $im Image */
            $im = Image::findOne([
                ['platform' => $image->platform],
                ['cloudLocation' => $image->cloudLocation],
                ['id' => $image->imageId],
                ['$or' => [['envId' => ($this->envId == 0 ? NULL : $this->envId)], ['envId' => NULL]]]
            ]);

            if ($im) {
                $i['architecture'] = $im->architecture;
                $i['type'] = $im->type;
            }

            $result[$image->platform][$image->cloudLocation] = $i;
        }

        return $result;
    }

    /**
     * Gets Images which are associated with the Role
     *
     * @param    array        $criteria     optional The search criteria on the Image result set.
     * @param    array        $order        optional The results order looks like [[property1 => true|false], ...]
     * @param    int          $limit        optional The records limit
     * @param    int          $offset       optional The offset
     * @param    bool         $countRecords optional True to calculate total number of the records without limit
     * @return   \Scalr\Model\Collections\EntityIterator Returns Images which are associated with the role
     */
    public function getImages(array $criteria = null, array $order = null, $limit = null, $offset = null, $countRecords = null)
    {
        $image = new Image();
        $roleImage = new RoleImage();

        $criteria = $criteria ?: [];

        $criteria[self::STMT_FROM] = $image->table() . "
            JOIN " . $roleImage->table() . " ON {$roleImage->columnImageId} = {$image->columnId}
                AND {$roleImage->columnPlatform} = {$image->columnPlatform}
                AND {$roleImage->columnCloudLocation} = {$image->columnCloudLocation}
        ";

        $criteria[static::STMT_WHERE] = "{$roleImage->columnRoleId} = " . intval($this->id);

        if ($this->envId) {
            $criteria[] = ['$or' => [['envId' => $this->envId], ['envId' => null]]];
        } else {
            $criteria[] = ['envId' => null];
        }

        return $image->find($criteria, $order, $limit, $offset, $countRecords);
    }

    /**
     * Add, replace or remove image in role
     *
     * @param   string    $platform       The cloud platform
     * @param   string    $cloudLocation  The cloud location
     * @param   string    $imageId        optional Either Identifier of the Image to add or NULL to remove
     * @param   integer   $userId         The identifier of the User who adds the Image
     * @param   string    $userEmail      The email address of the User who adds the Image
     * @throws  ImageNotFoundException
     * @throws  NotAcceptableImageStatusException
     * @throws  OsMismatchException
     * @throws  ImageNotFoundException
     */
    public function setImage($platform, $cloudLocation, $imageId, $userId, $userEmail)
    {
        if (in_array($platform, [SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::ECS]))
            $cloudLocation = '';

        $history = new ImageHistory();
        $history->roleId = $this->id;
        $history->platform = $platform;
        $history->cloudLocation = $cloudLocation;
        $history->addedById = $userId;
        $history->addedByEmail = $userEmail;

        $oldImage = null;
        try {
            $oldImage = $this->getImage($platform, $cloudLocation);
            $history->oldImageId = $oldImage->imageId;

            if ($imageId) {
                if ($oldImage->imageId == $imageId) {
                    return;
                }
            }
        } catch (\Exception $e) {}

        if ($imageId) {
            /* @var $newImage Image */
            $newImage = Image::findOne([
                ['id' => $imageId],
                ['platform' => $platform],
                ['cloudLocation' => $cloudLocation],
                ['$or' => [['envId' => $this->envId == 0 ? NULL : $this->envId], ['envId' => NULL]]]
            ]);

            if (!$newImage) {
                throw new ImageNotFoundException(sprintf(
                    "The Image does not exist, or isn't owned by your account: %s, %s, %s",
                    $platform, $cloudLocation, $imageId
                ));
            }

            if ($newImage->status !== Image::STATUS_ACTIVE) {
                throw new NotAcceptableImageStatusException(sprintf(
                    "You can't add image %s because of its status: %s", $newImage->id, $newImage->status
                ));
            }

            if ($newImage->getOs()->family && $newImage->getOs()->generation) {
                // check only if they are set
                if ($this->getOs()->family != $newImage->getOs()->family || $this->getOs()->generation != $newImage->getOs()->generation) {
                    throw new OsMismatchException(sprintf("OS mismatch between Image: %s, family: %s and Role: %d, family: %s",
                        $newImage->id, $newImage->getOs()->family, $this->id, $this->getOs()->family
                    ));
                }
            }

            $history->imageId = $newImage->id;

            if ($oldImage) {
                $oldImage->delete();
            }

            $newRoleImage = new RoleImage();
            $newRoleImage->roleId = $this->id;
            $newRoleImage->imageId = $newImage->id;
            $newRoleImage->platform = $newImage->platform;
            $newRoleImage->cloudLocation = $newImage->cloudLocation;

            $newRoleImage->save();
        } else if ($oldImage) {
            if ($oldImage->isUsed()) {
                throw new ImageInUseException(sprintf(
                    "The Image for roleId: %d, platform: %s, cloudLocation: %s is used by some FarmRole",
                    $oldImage->roleId, $oldImage->platform, $oldImage->cloudLocation
                ));
            }

            $oldImage->delete();
        }

        $history->save();
    }

    /**
     * Checks whether the Role is already used in some Farm
     *
     * @return boolean Returns TRUE if the Role is already used or FALSE otherwise
     */
    public function isUsed()
    {
        return !!$this->db()->GetOne("
            SELECT EXISTS(SELECT 1 FROM farm_roles WHERE role_id = ? OR new_role_id = ?)
        ", [$this->id, $this->id]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        switch ($this->getScope()) {
            case static::SCOPE_ACCOUNT:
                return $this->accountId == $user->accountId && (empty($environment) || !$modify);

            case static::SCOPE_ENVIRONMENT:
                return $environment
                     ? $this->envId == $environment->id
                     : $user->hasAccessToEnvironment($this->envId);

            case static::SCOPE_SCALR:
                return !$modify;

            default:
                return false;
        }
    }

    /**
     * Gets role scripts
     *
     * @param    array        $criteria     optional The search criteria.
     * @param    array        $order        optional The results order looks like [[property1 => true|false], ...]
     * @param    int          $limit        optional The records limit
     * @param    int          $offset       optional The offset
     * @param    bool         $countRecords optional True to calculate total number of the records without limit
     *
     * @return Script[]
     */
    public function getScripts(array $criteria = null, array $order = null, $limit = null, $offset = null, $countRecords = null)
    {
        return Script::find(array_merge(['roleId' => $this->id], $criteria), $order, $limit, $offset, $countRecords);
    }
}
