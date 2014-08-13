<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140225143104 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '958970bf-4a91-404c-85d4-cc136a54b869';

    protected $depends = array(
        'fe638b60-64cf-4967-84bd-1740dd2088c0'
    );

    protected $description = 'Combine indexes of logentries table in one';

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
        return !($this->hasTableIndex('logentries', 'NewIndex1') && $this->hasTableIndex('logentries', 'NewIndex2'));
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
        return $this->hasTable('logentries');
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
        $this->db->Execute('ALTER TABLE `logentries` DROP INDEX `NewIndex1`, DROP INDEX `NewIndex2`');
        $this->db->Execute('ALTER TABLE `logentries` ADD INDEX `idx_farmid_severity` (`farmid`, `severity`)');
    }
}