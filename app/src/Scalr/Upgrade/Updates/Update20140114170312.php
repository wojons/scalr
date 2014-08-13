<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140114170312 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'cc9bb284-6180-4345-bc46-ec2b0d472b7a';

    protected $depends = array();

    protected $description = 'Add is_deprecated field for roles';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    /**
     * Checks whether the update of the stage ONE is applied.
     *
     * Verifies whether current update has already been applied to this install.
     * This ensures avoiding the duplications. Implementation of this method should give
     * the definite answer to question "has been this update applied or not?".
     *
     * @param   int  $stage  optional The stage number
     * @return  bool Returns true if the update has already been applied.
     */
    protected function isApplied1($stage)
    {
        return $this->hasTable('roles') && $this->hasTableColumn('roles', 'is_deprecated');
    }

    /**
     * Validates an environment before it will try to apply the update of the stage ONE.
     *
     * Validates current environment or inspects circumstances that is expected to be in the certain state
     * before the update is applied. This method may not be overridden from AbstractUpdate class
     * which means current update is always valid.
     *
     * @param   int  $stage  optional The stage number
     * @return  bool Returns true if the environment meets the requirements.
     */
    protected function validateBefore1($stage)
    {
        return true;
    }

    /**
     * Performs upgrade literally for the stage ONE.
     *
     * Implementation of this method performs update steps needs to be taken
     * to accomplish upgrade successfully.
     *
     * If there are any error during an execution of this scenario it must
     * throw an exception.
     *
     * @param   int  $stage  optional The stage number
     * @throws  \Exception
     */
    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `roles` ADD `is_deprecated` TINYINT(1) NULL DEFAULT '0' AFTER `is_devel`");
    }
}