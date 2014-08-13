<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140222231138 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'fe638b60-64cf-4967-84bd-1740dd2088c0';

    protected $depends = array();

    protected $description = 'Updating aws.instance_name_format content';

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
        return false;
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
        $records = $this->db->Execute("
            SELECT * FROM farm_role_settings
            WHERE name = 'aws.instance_name_format'
        ");
        while ($record = $records->FetchRow()) {
            $record['value'] = str_replace(array(
                '%farm_name%',
                '%instance_index%',
                '%role_name%',
                '%avail_zone%'
            ), array(
                '{SCALR_FARM_NAME}',
                '{SCALR_INSTANCE_INDEX}',
                '{SCALR_ROLE_NAME}',
                '{SCALR_CLOUD_LOCATION_ZONE}'
            ), $record['value']);

            $this->db->Execute("UPDATE farm_role_settings SET value=? WHERE farm_roleid = ? AND name = ?", array(
                $record['value'], $record['farm_roleid'], $record['name']
            ));
        }
    }
}