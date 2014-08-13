<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140210185356 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'd9a4b5a1-55f0-4cc8-831d-c479c3b98727';

    protected $depends = array();

    protected $description = "Update hostnames for all servers";

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
        $count = $this->db->GetOne("
            SELECT COUNT(*) FROM servers
            WHERE server_id NOT IN(
                SELECT servers.server_id FROM servers
                INNER JOIN server_properties ON server_properties.server_id = servers.server_id
                WHERE server_properties.name = 'base.hostname' AND value != ''
            )
        ");

        return ($count == 0);
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
        $servers = $this->db->Execute("
            SELECT server_id FROM servers WHERE status='Running'
            AND server_id NOT IN (
                SELECT servers.server_id FROM servers
                INNER JOIN server_properties ON server_properties.server_id = servers.server_id
                WHERE server_properties.name = 'base.hostname' AND value != ''
            )
        ");
        $cnt = 0;
        while ($server = $servers->FetchRow()) {
            $dbServer = \DBServer::LoadByID($server['server_id']);
            if ($dbServer->IsSupported('0.23.0')) {
                try {
                    $hostname = $dbServer->scalarizr->system->getHostname();
                    if ($hostname) {
                        $dbServer->SetProperty(\Scalr_Role_Behavior::SERVER_BASE_HOSTNAME, $hostname);
                        $cnt++;
                    }
                } catch (\Exception $e) {
                    $this->console->out("Unable to get hostname from server: {$dbServer->remoteIp} ($dbServer->serverId)");
                }
            }
        }

        $this->console->out("Successfully updated {$cnt} servers");
    }
}