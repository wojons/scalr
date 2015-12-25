<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\Image;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140915094208 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '1f035e8c-a16a-4e34-be82-138178db3f86';

    protected $depends = [];

    protected $description = 'Update table images and fill with name and size';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('images') && $this->hasTableColumn('images', 'name');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('images');
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE `images` ADD `name` VARCHAR(255) NULL DEFAULT NULL AFTER `cloud_location`, ADD `size` INT(11) NULL DEFAULT NULL AFTER `architecture`');
    }

    protected function isApplied2()
    {
        return $this->hasTableColumn('images', 'os');
    }

    protected function validateBefore2()
    {
        return $this->hasTable('images');
    }

    protected function run2()
    {
        $this->console->notice('Modify os columns and re-fill it from bundle tasks');

        $this->db->Execute('ALTER TABLE images CHANGE `os_name` `os` varchar(60) DEFAULT NULL AFTER `name`,
             MODIFY `os_family` varchar(30) DEFAULT NULL,
             ADD `os_generation` varchar(10) DEFAULT NULL AFTER `os_family`,
             MODIFY `os_version` varchar(10) DEFAULT NULL
        ');

        $bunimages = Image::find([
            ['source' => Image::SOURCE_BUNDLE_TASK]
        ]);

        foreach ($bunimages as $image) {
            /* @var Image $image */
            try {
                $task = \BundleTask::LoadById($image->bundleTaskId);
                $os = $task->getOsDetails();
                $image->osId = $os->id;
                $image->save();
            } catch (\Exception $e) {
                $this->console->warning($e->getMessage());
            }
        }
    }

    protected function validateBefore3()
    {
        return $this->hasTable('images');
    }

    protected function run3()
    {
        $this->console->notice('Updating images');
        $cnt = 0;
        $cntError = 0;

        $images = Image::find([
            ['status' => 'active'],
            ['size'   => null],
            ['name'   => null]
        ]);

        foreach ($images as $i) {
            /* @var Image $i */
            if ($i->checkImage())
                $cnt++;
            else
                $cntError++;

            $i->save();
        }

        $this->console->notice('Proceed %d images, mark as deleted: %d', $cnt + $cntError, $cntError);
    }
}
