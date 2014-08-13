<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140616115035 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '345ba2c3-bd0b-4f20-93b8-995a118cfc85';

    protected $depends = [];

    protected $description = 'Update ECS images to multi-regional configuration';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function run1($stage)
    {
        $roles = $this->db->Execute("SELECT DISTINCT(role_id) as role_id FROM role_images WHERE platform=?", array(\SERVER_PLATFORMS::ECS));
        while ($role = $roles->FetchRow()) {
            $images = $this->db->GetAll("SELECT * FROM role_images WHERE role_id = ? AND platform=?", array(
                $role['role_id'], \SERVER_PLATFORMS::ECS
            ));

            $safeToUpdate = true;
            $imageId = null;
            $image = null;
            foreach ($images as $image) {
                if ($imageId == null)
                    $imageId = $image['image_id'];

                if ($imageId != $image['image_id']) {
                    $safeToUpdate = false;
                    break;
                }
            }

            if ($safeToUpdate && $imageId) {
                $this->db->Execute("DELETE FROM role_images WHERE image_id = ? AND role_id = ?", array(
                    $imageId, $role['role_id']
                ));
                $this->db->Execute("INSERT INTO role_images SET
                    role_id = ?,
                    cloud_location = '',
                    image_id = ?,
                    platform = ?,
                    architecture = ?,
                    agent_version = ?
                ", array(
                    $role['role_id'],
                    $imageId,
                    \SERVER_PLATFORMS::ECS,
                    $image['architecture'],
                    $image['agent_version'],
                ));
            }
        }
    }
}