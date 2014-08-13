<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140220012647 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '56f50cac-0129-42a1-a221-fd78e3339be7';

    protected $depends = array();

    protected $description = 'Adding cloud_location column to servers table';

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
        return $this->hasTableColumn('servers', 'cloud_location') &&
               $this->hasTableColumnType('servers', 'cloud_location', 'varchar(255)');
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
        return $this->hasTable('servers') && $this->hasTableColumn('servers', 'index');
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
        if (!$this->hasTableColumn('servers', 'cloud_location')) {
            $this->db->Execute("ALTER TABLE `servers`
                    ADD `cloud_location` VARCHAR(255) NULL AFTER `index`,
                    ADD `cloud_location_zone` VARCHAR(255) NULL AFTER `cloud_location`
            ");
        } else if (!$this->hasTableColumnType('servers', 'cloud_location', 'varchar(255)')) {
            $this->db->Execute("ALTER TABLE `servers`
                    CHANGE `cloud_location` `cloud_location` VARCHAR(255) NULL,
                    CHANGE `cloud_location_zone` `cloud_location_zone` VARCHAR(255) NULL
            ");
        }

        $servers = $this->db->Execute("SELECT server_id FROM servers");
        while ($server = $servers->FetchRow()) {
            try {
                $dbServer = \DBServer::LoadByID($server['server_id']);
                $dbServer->cloudLocation = $dbServer->GetCloudLocation();

                if ($dbServer->platform == \SERVER_PLATFORMS::EC2)
                    $dbServer->cloudLocationZone = $dbServer->GetProperty(\EC2_SERVER_PROPERTIES::AVAIL_ZONE);

                if ($dbServer->platform == \SERVER_PLATFORMS::EUCALYPTUS)
                    $dbServer->cloudLocationZone = $dbServer->GetProperty(\EUCA_SERVER_PROPERTIES::AVAIL_ZONE);

                if ($dbServer->platform == \SERVER_PLATFORMS::GCE)
                    $dbServer->cloudLocationZone = $dbServer->GetProperty(\GCE_SERVER_PROPERTIES::CLOUD_LOCATION);

                $dbServer->Save();
            } catch (\Exception $e) {
            }
        }
    }
}