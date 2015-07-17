<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Image;
use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20141104183518 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '7bc75d36-5443-41ff-a12f-25d13d3dcbe6';

    protected $depends = [];

    protected $description = 'Migrate software from roles to images';

    protected $ignoreChanges = false;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 5;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('image_software');
    }

    protected function validateBefore1()
    {
        return $this->hasTable('role_software');
    }

    protected function run1($stage)
    {
        $this->db->Execute("CREATE TABLE `image_software` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `image_hash` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
              `name` varchar(45) NOT NULL DEFAULT '',
              `version` varchar(20) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_image_hash` (`image_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");

        $this->db->Execute('ALTER TABLE image_software ADD CONSTRAINT `fk_images_hash_image_software` FOREIGN KEY (`image_hash`) REFERENCES `images` (`hash`) ON DELETE CASCADE ON UPDATE NO ACTION');

        foreach ($this->db->GetAll('SELECT role_images.*, roles.env_id FROM role_images LEFT JOIN roles ON roles.id = role_images.role_id') as $image) {
            $props = [];
            foreach ($this->db->GetAll('SELECT * FROM role_software WHERE role_id = ?', [$image['role_id']]) as $soft) {
                if ($soft['software_name']) {
                    $name = explode(' ', trim($soft['software_name'], '*- '));
                    $version = $soft['software_version'];
                    if (count($name) > 1) {
                        $version = $name[1];
                        $name = strtolower($name[0]);
                    } else {
                        $name = strtolower($name[0]);
                    }

                    if (preg_match('/^[a-z]+$/', $name)) {
                        $props[$name] = $version;
                    }
                }
            }

            if (empty($props)) {
                // check role behaviours
                $beh = $this->db->GetCol('SELECT behavior FROM role_behaviors WHERE role_id = ?', [$image['role_id']]);
                foreach ($beh as $b) {
                    if (in_array($b, [
                        \ROLE_BEHAVIORS::MYSQL,
                        \ROLE_BEHAVIORS::PERCONA,
                        \ROLE_BEHAVIORS::TOMCAT,
                        \ROLE_BEHAVIORS::MEMCACHED,
                        \ROLE_BEHAVIORS::POSTGRESQL,
                        \ROLE_BEHAVIORS::REDIS,
                        \ROLE_BEHAVIORS::RABBITMQ,
                        \ROLE_BEHAVIORS::MONGODB,
                        \ROLE_BEHAVIORS::CHEF,
                        \ROLE_BEHAVIORS::MYSQLPROXY,
                        \ROLE_BEHAVIORS::HAPROXY,
                        \ROLE_BEHAVIORS::MARIADB
                    ])) {
                        $props[$b] = null;
                    } else if ($b == \ROLE_BEHAVIORS::MYSQL2) {
                        $props['mysql'] = null;
                    } else if ($b == \ROLE_BEHAVIORS::NGINX) {
                        $props['nginx'] = null;
                    } else if ($b == \ROLE_BEHAVIORS::APACHE) {
                        $props['apache'] = null;
                    }
                }
            }

            $obj = Image::findOne([
                ['id' => $image['image_id']],
                ['envId' => $image['env_id'] == 0 ? NULL : $image['env_id']],
                ['platform' => $image['platform']],
                ['cloudLocation' => $image['cloud_location']]]
            );

            /* @var Image $obj */
            if ($obj) {
                $obj->setSoftware($props);
            } else {
                if (! Image::findOne([
                        ['id' => $image['image_id']],
                        ['envId' => NULL],
                        ['platform' => $image['platform']],
                        ['cloudLocation' => $image['cloud_location']]]
                ))
                    $this->console->warning('Image not found: %s', $image['image_id']);
            }
        }

        $this->db->Execute('RENAME TABLE role_software TO role_software_deleted');
    }

    protected function isApplied2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_FARMS_IMAGES') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_FARMS_IMAGES,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_FARMS_IMAGES') &&
        Definition::has(Acl::RESOURCE_FARMS_IMAGES);
    }

    protected function run2($stage)
    {
        $this->console->out("Adding Farm Images ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_FARMS_IMAGES
        ));
    }

    protected function isApplied3($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_FARMS_IMAGES') &&
        defined('Scalr\\Acl\\Acl::PERM_FARMS_IMAGES_MANAGE') &&
        $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resource_permissions`
                    WHERE `resource_id` = ?
                    AND `role_id` = ?
                    AND `perm_id` = ?
                    LIMIT 1
                ", array(
            Acl::RESOURCE_FARMS_IMAGES,
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::PERM_FARMS_IMAGES_MANAGE,
        )) == 1;
    }

    protected function validateBefore3($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_FARMS_IMAGES') &&
        defined('Scalr\\Acl\\Acl::PERM_FARMS_IMAGES_MANAGE') &&
        Definition::has(Acl::RESOURCE_FARMS_IMAGES);
    }

    protected function run3($stage)
    {
        $this->console->out('Creating Farm Images manage permission');
        $this->db->Execute("
            INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_FARMS_IMAGES,
            Acl::PERM_FARMS_IMAGES_MANAGE
        ));
    }

    protected function isApplied4($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_FARMS_IMAGES') &&
        defined('Scalr\\Acl\\Acl::PERM_FARMS_IMAGES_CREATE') &&
        $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resource_permissions`
                    WHERE `resource_id` = ?
                    AND `role_id` = ?
                    AND `perm_id` = ?
                    LIMIT 1
                ", array(
            Acl::RESOURCE_FARMS_IMAGES,
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::PERM_FARMS_IMAGES_CREATE
        )) == 1;
    }

    protected function validateBefore4($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_FARMS_IMAGES') &&
        defined('Scalr\\Acl\\Acl::PERM_FARMS_IMAGES_CREATE') &&
        Definition::has(Acl::RESOURCE_FARMS_IMAGES);
    }

    protected function run4($stage)
    {
        $this->console->out('Creating Farm Images create permission');
        $this->db->Execute("
            INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_FARMS_IMAGES,
            Acl::PERM_FARMS_IMAGES_CREATE
        ));
    }

    protected function isApplied5()
    {
        return !$this->db->GetOne('SELECT 1 FROM images WHERE size > 1024 and (platform = ? OR platform = ?)', [\SERVER_PLATFORMS::IDCF, \SERVER_PLATFORMS::CLOUDSTACK]);
    }

    protected function run5()
    {
        $this->db->Execute('UPDATE images SET size = size*3/1024/1024 WHERE size > 1024 AND (platform = ? OR platform = ?)', [\SERVER_PLATFORMS::IDCF, \SERVER_PLATFORMS::CLOUDSTACK]);
    }
}