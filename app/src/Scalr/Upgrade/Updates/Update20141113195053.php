<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141113195053 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '21f91afc-c01c-4394-b3d0-d554a1240a15';

    protected $depends = [];

    protected $description = 'Fix type field';

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

    protected function validateBefore1($stage)
    {
        return $this->hasTable('images');
    }

    protected function run1($stage)
    {
        // we can operate with type only on ec2 platform
        $this->db->Execute('UPDATE images SET type = NULL WHERE platform != ?', [\SERVER_PLATFORMS::EC2]);
    }
}