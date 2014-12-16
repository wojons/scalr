<?php
namespace Scalr\Role;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Image;

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
     * @return Image|NULL
     * @throws \Exception
     */
    public function getImage()
    {
        /* @var Role $role */
        $role = Role::findPk($this->roleId);
        return Image::findOne([['id' => $this->imageId], ['$or' => [['envId' => $role->envId == 0 ? NULL : $role->envId], ['envId' => NULL]]], ['platform' => $this->platform], ['cloudLocation' => $this->cloudLocation]]);
    }

    public function isUsed()
    {
        if (in_array($this->platform, [\SERVER_PLATFORMS::GCE, \SERVER_PLATFORMS::ECS])) {
            return !!$this->db()->GetOne('SELECT EXISTS(SELECT 1 FROM farm_roles WHERE role_id = ? AND platform = ?)', [$this->roleId, $this->platform]);
        } else {
            return !!$this->db()->GetOne('SELECT EXISTS(SELECT 1 FROM farm_roles WHERE role_id = ? AND platform = ? AND cloud_location = ?)', [$this->roleId, $this->platform, $this->cloudLocation]);
        }
    }
}
