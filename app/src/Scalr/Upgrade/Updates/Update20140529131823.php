<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140529131823 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'dd5e9186-1e19-46ac-b256-1e316eb83332';

    protected $depends = array('767a1246-28e7-4ac4-9cf2-d0ee616c953c');

    protected $description = 'Add reason_id to table servers_history';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('servers_history', 'launch_reason_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('servers_history');
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE servers_history ADD launch_reason_id TINYINT(1) NULL DEFAULT NULL AFTER dtterminated');

        $replacements = [
            'LAUNCH_REASON_REPLACE_SERVER_FROM_SNAPSHOT' => 'Server replacement after snapshotting',
            'LAUNCH_REASON_SCALING_UP' => 'Scaling%',
            'LAUNCH_REASON_FARM_LAUNCHED' => 'Farm launched',
            'LAUNCH_REASON_MANUALLY_API' => 'API Request',
            'LAUNCH_REASON_MANUALLY' => 'Manually launched using UI'
        ];

        foreach ($replacements as $key => $str) {
            $this->db->Execute('UPDATE servers_history SET launch_reason_id = ? WHERE launch_reason LIKE ?', [ constant('DBServer::' . $key), $str]);
        }
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('servers_history', 'terminate_reason_id');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('servers_history');
    }

    protected function run2($stage)
    {
        $this->db->Execute('ALTER TABLE servers_history ADD terminate_reason_id TINYINT(1) NULL DEFAULT NULL AFTER launch_reason');

        $replacements = [
            ['TERMINATE_REASON_SHUTTING_DOWN_CLUSTER', 'Shutting-down %'],
            ['TERMINATE_REASON_REMOVING_REPLICA_SET_FROM_CLUSTER', 'Removing replica set from %'],
            ['TERMINATE_REASON_ROLE_REMOVED', 'Farm role does not exist%'],
            ['TERMINATE_REASON_ROLE_REMOVED', 'Role removed from farm%'],
            ['TERMINATE_REASON_ROLE_REMOVED', 'Role was removed from farm%'],
            ['TERMINATE_REASON_SERVER_DID_NOT_SEND_EVENT', 'Server did not send%'],
            ['TERMINATE_REASON_TEMPORARY_SERVER', 'Terminating temporary server%'],
            ['TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER', 'Terminating role builder temporary server%'],
            ['TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER', 'RoleBuilder temporary server%'],
            ['TERMINATE_REASON_SCALING_DOWN', 'Scaling down%'],
            ['TERMINATE_REASON_SCALING_DOWN', 'Terminated during scaling down%'],
            ['TERMINATE_REASON_SNAPSHOT_CANCELLATION', 'Snapshot cancellation%'],
            ['TERMINATE_REASON_SNAPSHOT_CANCELLATION', 'Cancelled snapshotting operation%'],
            ['TERMINATE_REASON_MANUALLY', 'Manually terminated by %'],
            ['TERMINATE_REASON_MANUALLY', 'Manually terminated via UI%'],
            ['TERMINATE_REASON_MANUALLY', 'Terminated via user interface%'],
            ['TERMINATE_REASON_MANUALLY_API', 'Terminated through the Scalr API by %'],
            ['TERMINATE_REASON_MANUALLY_API', 'Terminated via API. TransactionID%'],
            ['TERMINATE_REASON_BUNDLE_TASK_FINISHED', 'Farm was in%'],
            ['TERMINATE_REASON_FARM_TERMINATED',  'Terminating server because the farm has been terminated%'],
            ['TERMINATE_REASON_FARM_TERMINATED',  'Farm was terminated%'],
            ['TERMINATE_REASON_FARM_TERMINATED',  'Farm terminated%'],
            ['TERMINATE_REASON_REPLACE_SERVER_FROM_SNAPSHOT', 'Server replaced with new one after snapshotting%'],
            ['TERMINATE_REASON_OPERATION_CANCELLATION', 'Operation cancellation%'],
            ['TERMINATE_REASON_CRASHED', 'Server was crashed or terminated outside scalr%']
        ];

        foreach ($replacements as $ar) {
            $this->db->Execute('UPDATE servers_history SET terminate_reason_id = ? WHERE terminate_reason LIKE ?', [ constant('DBServer::' . $ar[0]), $ar[1]]);
        }
    }
}
