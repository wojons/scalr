<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Image;
use SERVER_PLATFORMS;

/**
 * Role images model
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (07.08.2014)
 *
 * @Entity
 * @Table(name="role_images")
 */
class RoleImage extends AbstractEntity
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
     * @Column(type="integer")
     * @var string
     */
    public $roleId;

    /**
     * @Column(type="string")
     * @var string
     */
    public $imageId;

    /**
     * @Column(type="string")
     * @var integer
     */
    public $platform;

    /**
     * @Column(type="string")
     * @var integer
     */
    public $cloudLocation;

    /**
     * @deprecated Use method FarmRole::getImage
     *
     * @return Image|NULL
     * @throws \Exception
     */
    public function getImage()
    {
        /* @var $role Role */
        $role = Role::findPk($this->roleId);
        $criteria = [
            ['id'            => $this->imageId],
            ['platform'      => $this->platform],
            ['cloudLocation' => $this->cloudLocation],
            ['$or' => [
                ['accountId' => null],
                ['$and' => [
                    ['accountId' => $role->accountId],
                    ['$or' => [
                        ['envId' => null],
                        ['envId' => $role->envId]
                    ]]
                ]]
            ]]
        ];

        return Image::findOne($criteria);
    }

    /**
     * Check whether the Role is used in any farm
     *
     * @return bool Returns true if the Role is used in some farm or false otherwise
     */
    public function isUsed()
    {
        if (in_array($this->platform, [SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::AZURE])) {
            return !!$this->db()->GetOne("SELECT EXISTS(SELECT 1 FROM farm_roles WHERE role_id = ? AND platform = ?)", [$this->roleId, $this->platform]);
        } else {
            return !!$this->db()->GetOne("SELECT EXISTS(SELECT 1 FROM farm_roles WHERE role_id = ? AND platform = ? AND cloud_location = ?)", [$this->roleId, $this->platform, $this->cloudLocation]);
        }
    }
}
