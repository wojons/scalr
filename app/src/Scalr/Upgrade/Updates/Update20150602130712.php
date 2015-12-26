<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity\Image;
use SERVER_PLATFORMS;

class Update20150602130712 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '90297f49-0a22-442b-87ac-6ea91ee393d1';

    protected $depends = [];

    protected $description = 'Update type (if missing) for EC2 images (it may take a long time)';

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

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $cntAll = 0;
        $cntError = 0;
        $cntUpdated = 0;

        foreach (Image::find([
            ['type'     => null],
            ['platform' => SERVER_PLATFORMS::EC2],
            ['envId'    => ['$ne' => null]]
        ]) as $image) {
            /* @var $image Image */
            $type = $image->type;
            $cntAll++;
            if ($image->checkImage()) {
                $image->save();
                if ($type != $image->type) {
                    $cntUpdated++;
                }
            } else {
                $cntError++;
            }
        }

        $this->console->out('Processed images: %d', $cntAll);
        $this->console->out('Updated images: %d', $cntUpdated);
        $this->console->out('Invalid images: %d', $cntError);
    }
}