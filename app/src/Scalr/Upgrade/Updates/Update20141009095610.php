<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Image;

class Update20141009095610 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'f90496f9-aae4-4acf-add3-86e851bdc03a';

    protected $depends = [];

    protected $description = 'Update table images';

    protected $ignoreChanges = false;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function isValid1()
    {
        return $this->hasTable('role_image_history');
    }

    protected function isApplied1()
    {
        return $this->hasTableColumn('role_image_history', 'added_by_id');
    }

    protected function run1()
    {
        $this->db->Execute('ALTER TABLE role_image_history ADD `added_by_id` int(11) DEFAULT NULL, ADD `added_by_email` varchar(50) DEFAULT NULL');
        $this->db->Execute('UPDATE role_image_history rh LEFT JOIN roles r ON r.id = rh.role_id SET rh.added_by_id = r.added_by_userid,
            rh.added_by_email = r.added_by_email');
    }

    protected function run2()
    {
        $cnt = 0;
        $images = $this->db->GetAll('SELECT ri.*, r.env_id FROM role_images ri LEFT JOIN roles r ON r.id = ri.role_id');
        foreach ($images as $i) {
            /* @var Image $imObj */
            $i['env_id'] = $i['env_id'] == 0 ? NULL : $i['env_id'];

            $imObj = Image::findOne([
                ['id'            => $i['image_id']],
                ['$or'           => [['envId' => $i['env_id']], ['envId' => null]]],
                ['platform'      => $i['platform']],
                ['cloudLocation' => $i['cloud_location']]
            ]);

            if (!$imObj) {
                $imObj = new Image();
                $imObj->id = $i['image_id'];
                $imObj->envId = $i['env_id'];
                $imObj->platform = $i['platform'];
                $imObj->cloudLocation = $i['cloud_location'];
                $imObj->architecture = $i['architecture'] ? $i['architecture'] : 'x84_64';
                $imObj->osId = $i['os_id'];
                $imObj->isDeprecated = 0;
                $imObj->dtAdded = NULL;
                $imObj->source = Image::SOURCE_MANUAL;
                if ($imObj->envId) {
                    $imObj->checkImage();
                } else {
                    $imObj->status = Image::STATUS_ACTIVE;
                }

                if (is_null($imObj->status))
                    $imObj->status = Image::STATUS_ACTIVE;

                if (is_null($imObj->cloudLocation))
                    $imObj->cloudLocation = '';

                $imObj->save();
                $cnt++;
            }
        }

        $this->console->notice('Added %s images', $cnt);
    }

    protected function run3()
    {
        $this->db->Execute('UPDATE images SET name = id WHERE ISNULL(name)');
    }
}
