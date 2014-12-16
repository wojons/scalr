<?php
namespace Scalr\Role;

use Scalr\Model\AbstractEntity;
use Scalr\Role\ImageHistory;
use Scalr\Role\RoleImage;
use Scalr\Model\Entity\Image;
use Scalr\Modules\PlatformFactory;

/**
 * Role model
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (23.07.2014)
 *
 * @Entity
 * @Table(name="roles")
 */

class Role extends AbstractEntity
{
    /**
     * ID
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * @Column(type="string")
     * @var string
     */
    public $description;

    /**
     * @Column(type="integer",name="client_id",nullable=true)
     * @var integer
     */
    public $accountId;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $envId;

    /**
     * Nullable = ?
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $catId;

    /**
     * @Column(type="string")
     * @var string
     */
    public $os;

    /**
     * @Column(type="string")
     * @var string
     */
    public $osFamily;

    /**
     * @Column(type="string")
     * @var string
     */
    public $osVersion;

    /**
     * @Column(type="string")
     * @var string
     */
    public $osGeneration;

    /**
     * @param $name Role name
     * @return bool
     */
    public static function validateName($name)
    {
        return !!preg_match("/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/si", $name);
    }

    /**
     * @param $platform
     * @param $cloudLocation
     * @return RoleImage
     * @throws \Exception
     */
    public function getImage($platform, $cloudLocation)
    {
        if (in_array($platform, [\SERVER_PLATFORMS::GCE, \SERVER_PLATFORMS::ECS]))
            $cloudLocation = '';

        $image = RoleImage::findOne([
            [ 'roleId' => $this->id ],
            [ 'platform' => $platform ],
            [ 'cloudLocation' => $cloudLocation ]
        ]);

        if (! $image)
            throw new \Exception(sprintf('No valid role image found for roleId: %d, platform: %s, cloudLocation: %s', $this->id, $platform, $cloudLocation));

        return $image;
    }

    /**
     * Return array of all role's images in format: [platform][cloudLocation] = [id, architecture, type]
     *
     * @return array
     * @throws \Exception
     */
    public function getImages()
    {
        $result = [];

        foreach (RoleImage::find([['roleId' => $this->id]]) as $image) {
            /* @var RoleImage $image */
            if (!$result[$image->platform])
                $result[$image->platform] = [];

            $i = ['id' => $image->imageId, 'architecture' => '', 'type' => ''];

            /* @var Image $im */
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
     * Add, replace or remove image in role
     *
     * @param   string    $platform
     * @param   string    $cloudLocation
     * @param   string    $imageId ImageId or false to remove
     * @param   integer   $userId
     * @param   string    $userEmail
     * @throws  \Exception
     */
    public function setImage($platform, $cloudLocation, $imageId, $userId, $userEmail)
    {
        $history = new ImageHistory();
        $history->roleId = $this->id;
        $history->platform = $platform;
        $history->cloudLocation = $cloudLocation;
        $history->addedById = $userId;
        $history->addedByEmail = $userEmail;

        $oldImage = NULL;
        try {
            $oldImage = $this->getImage($platform, $cloudLocation);
            $history->oldImageId = $oldImage->imageId;

            if ($imageId) {
                if ($oldImage->imageId == $imageId)
                    return;
            }
        } catch (\Exception $e) {}

        if ($imageId) {
            /* @var Image $newImage */
            $newImage = Image::findOne([
                ['id' => $imageId],
                ['platform' => $platform],
                ['cloudLocation' => $cloudLocation],
                ['$or' => [['envId' => $this->envId == 0 ? NULL : $this->envId], ['envId' => NULL]]]
            ]);

            if (! $newImage)
                throw new \Exception(sprintf("This Image does not exist, or isn't usable by your account: %s, %s, %s", $platform, $cloudLocation, $imageId));

            if ($newImage->status != Image::STATUS_ACTIVE)
                throw new \Exception(sprintf('You can\'t add image %s because of its status: %s', $newImage->id, $newImage->status));

            if ($this->osFamily && $this->osGeneration && $newImage->osFamily && $newImage->osGeneration) {
                // check only if they are set
                if ($this->osFamily != $newImage->osFamily || $this->osGeneration != $newImage->osGeneration) {
                    throw new \Exception("OS values are mismatched ({$this->osFamily}, {$newImage->osFamily}, {$this->osGeneration}, {$newImage->osGeneration})");
                }
            }

            $history->imageId = $newImage->id;
            if ($oldImage)
                $oldImage->delete();

            $newRoleImage = new RoleImage();
            $newRoleImage->roleId = $this->id;
            $newRoleImage->imageId = $newImage->id;
            $newRoleImage->platform = $newImage->platform;
            $newRoleImage->cloudLocation = $newImage->cloudLocation;
            $newRoleImage->save();
        } else if ($oldImage) {
            if ($oldImage->isUsed())
                throw new \Exception(sprintf('Image for roleId: %d, platform: %s, cloudLocation: %s is used by some FarmRoles', $oldImage->roleId, $oldImage->platform, $oldImage->cloudLocation));

            $oldImage->delete();
        }

        $history->save();
    }

    public function isUsed()
    {
        return !!$this->db()->GetOne('SELECT EXISTS(SELECT 1 FROM farm_roles WHERE role_id = ? OR new_role_id = ?)', [$this->id, $this->id]);
    }
}
