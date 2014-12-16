<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Image;

class Update20141020093250 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '24a7d1ab-dc20-428e-85bc-a5361846af2f';

    protected $depends = [];

    protected $description = 'Refactoring tables images, role_images';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 6;
    }

    protected function run1($stage)
    {
        $this->console->notice('Updating type');
        // type is defined only on EC2
        $this->db->Execute('UPDATE images SET type = NULL WHERE platform != ?', [\SERVER_PLATFORMS::EC2]);
        $this->db->Execute('UPDATE images SET type = NULL WHERE type = ""');

        // we could simply translate to new names
        $this->db->Execute('UPDATE images SET type = ? WHERE type = ?', ['ebs', 'ec2.ebs']);
        $this->db->Execute('UPDATE images SET type = ? WHERE type = ?', ['ebs-hvm', 'ec2.ebs-hvm']);

        // check others
        $this->console->notice('Checking images');
        $images = Image::find([
            ['type' => ['$ne' => '']],
            ['type' => ['$ne' => 'ebs']],
            ['type' => ['$ne' => 'ebs-hvm']],
            ['type' => ['$ne' => 'instance-store']],
            ['type' => ['$ne' => 'instance-store-hvm']]
        ]);

        foreach ($images as $im) {
            /* @var Image $im */
            $im->checkImage();
            $im->save();
        }
    }

    protected function run2()
    {
        $this->console->notice('Removing unnecessary fields and indexes in role_images');

        $fields = ['architecture', 'os_family', 'os_name', 'os_version', 'agent_version'];
        $indexes = ['role_id_location', 'unique', 'NewIndex1', 'location'];

        if (! $this->hasTableIndex('role_images', 'idx_role_id')) {
            $this->console->notice('Add key to role_images');
            $this->db->Execute('ALTER TABLE role_images ADD INDEX idx_role_id(role_id)');
        }

        foreach ($indexes as $i) {
            if ($this->hasTableIndex('role_images', $i)) {
                $this->db->Execute('ALTER TABLE role_images DROP KEY `' . $i . '`');
            }
        }

        foreach ($fields as $i) {
            if ($this->hasTableColumn('role_images', $i)) {
                $this->db->Execute('ALTER TABLE role_images DROP COLUMN `' . $i . '`');
            }
        }

        if (! $this->hasTableIndex('role_images', 'key_idx')) {
            $this->console->notice('Add uniq key to role_images');
            $this->db->Execute('ALTER TABLE role_images ADD UNIQUE INDEX key_idx(role_id, platform, cloud_location, image_id)');
        }
    }

    protected function isApplied3()
    {
        return !$this->hasTable('role_tags');
    }

    protected function run3()
    {
        $this->db->Execute('DROP TABLE role_tags');
    }

    protected function run4()
    {
        $this->console->notice('Fix shared images, remove duplicates');

        foreach ($this->db->GetAll('SELECT id, platform, cloud_location FROM images GROUP BY id HAVING count(*) > 1') as $im) {
            if ($this->db->GetOne('SELECT 1 FROM images WHERE id = ? and platform = ? and cloud_location = ? and env_id is null', [$im['id'], $im['platform'], $im['cloud_location']])) {
                $size = $this->db->GetRow('SELECT `size`, name, architecture FROM images WHERE id = ? and platform = ? and cloud_location = ? and size > 0', [$im['id'], $im['platform'], $im['cloud_location']]);
                if ($size) {
                    $this->db->Execute('UPDATE images SET size = ? WHERE id = ? and platform = ? and cloud_location = ? and env_id is null', [$size['size'], $im['id'], $im['platform'], $im['cloud_location']]);
                    if ($size['architecture']) {
                        $this->db->Execute('UPDATE images SET architecture = ? WHERE id = ? and platform = ? and cloud_location = ? and env_id is null', [$size['architecture'], $im['id'], $im['platform'], $im['cloud_location']]);
                    }
                    if ($size['name']) {
                        $this->db->Execute('UPDATE images SET name = ? WHERE id = ? and platform = ? and cloud_location = ? and env_id is null', [$size['name'], $im['id'], $im['platform'], $im['cloud_location']]);
                    }
                }

                // found shared image, remove others
                $this->db->Execute('DELETE FROM images WHERE id = ? and platform = ? and cloud_location = ? and env_id is not null', [$im['id'], $im['platform'], $im['cloud_location']]);
            }
        }

        $this->console->notice('Fill shared images with info');
        foreach ($this->db->GetAll('SELECT * FROM roles WHERE env_id = 0') as $role) {
            $hvm = stristr($role['name'], '-hvm-') ? 1 : 0;
            $architecture = '';
            if ((stristr($role['name'], '64-')))
                $architecture = 'x86_64';
            if ((stristr($role['name'], 'i386')))
                $architecture = 'i386';

            foreach ($this->db->GetAll('SELECT * FROM role_images WHERE role_id = ?', [$role['id']]) as $im) {
                /* @var Image $image */
                $image = Image::findOne([['id' => $im['image_id']], ['platform' => $im['platform']], ['cloudLocation' => $im['cloud_location']], ['envId' => NULL]]);
                if ($image) {
                    if ($architecture)
                        $image->architecture = $architecture;
                    else if (!$image->architecture)
                        $image->architecture = 'i386';

                    if ($role['os_family']) {
                        $image->os = $role['os'];
                        $image->osFamily = $role['os_family'];
                        $image->osVersion = $role['os_version'];
                        $image->osGeneration = $role['os_generation'];
                    }

                    if ($image->name == $image->id && $role['name'])
                        $image->name = $role['name'];

                    if ($hvm) {
                        $image->type = 'ebs-hvm';
                    } else {
                        $image->type = 'ebs';
                    }

                    $image->save();
                } else {
                    $this->console->warning('Image not found: %s, %s, %s', $im['platform'], $im['cloud_location'], $im['image_id']);
                }
            }
        }
    }

    protected function isApplied5()
    {
        return !$this->hasTable('roles_queue');
    }

    protected function run5()
    {
        $this->console->notice('Removing roles from queue');
        $this->db->Execute('DELETE FROM roles WHERE id IN (SELECT role_id FROM roles_queue)');

        $this->console->notice('Removing roles_queue');
        $this->db->Execute('DROP TABLE roles_queue');
    }

    protected function run6()
    {
        $this->db->Execute('UPDATE images SET status = ? WHERE status = ?', [Image::STATUS_ACTIVE, 'deleted']);
        $this->db->Execute('UPDATE images SET status = ? WHERE status = ?', [Image::STATUS_ACTIVE, 'invalid']);
    }
}
