<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Bundletasks extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'bundleTaskId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_BUNDLETASKS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/bundletasks/view.js');
    }

    /**
     * @param       int $bundleTaskId
     * @throws      Exception
     */
    public function xCancelAction($bundleTaskId)
    {
        $task = BundleTask::LoadById($bundleTaskId);
        $this->user->getPermissions()->validate($task);

        if (in_array($task->status, array(
            SERVER_SNAPSHOT_CREATION_STATUS::CANCELLED,
            SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
            SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS)
        ))
            throw new Exception('Selected task cannot be cancelled');

        $task->SnapshotCreationFailed('Cancelled by client');
        $this->response->success(_('Bundle task successfully cancelled.'));
    }

    /**
     * @param   int     $bundleTaskId       ID of BundleTask
     * @param   string  $status   optional  Status of BundleTask
     * @param   bool    $taskInfo optional  Get updated information about task
     */
    public function xListLogsAction($bundleTaskId, $status = '', $taskInfo = false)
    {
        $task = BundleTask::LoadById($bundleTaskId);
        $this->user->getPermissions()->validate($task);

        $sql = "SELECT * FROM bundle_task_log WHERE bundle_task_id = ?";
        $response = $this->buildResponseFromSql2($sql, array('id', 'dtadded', 'message'), array(), array($bundleTaskId));
        foreach ($response["data"] as &$row) {
            $row['dtadded'] = Scalr_Util_DateTime::convertTz($row['dtadded']);
        }

        if ($taskInfo && $task->status != $status) {
            // status has been changed, also include information about task
            $row = $this->db->GetRow("SELECT b.*, (SELECT EXISTS (SELECT 1 FROM servers s WHERE s.server_id = b.server_id)) as server_exists FROM bundle_tasks b WHERE id = ?", [$task->id]);
            // temporary solution, refactor all on new model and replace this code

            $row['dtadded'] = Scalr_Util_DateTime::convertTz($row['dtadded']);

            if (!$row['bundle_type']) {
                $row['bundle_type'] = "*";
            }

            if ($row['dtfinished'] && $row['dtstarted']) {
                $row['duration'] = Scalr_Util_DateTime::getDateTimeDiff($row['dtfinished'], $row['dtstarted']);
            }

            if ($row['dtfinished']) {
                $row['dtfinished'] = Scalr_Util_DateTime::convertTz($row['dtfinished']);
            }

            if ($row['dtstarted']) {
                $row['dtstarted'] = Scalr_Util_DateTime::convertTz($row['dtstarted']);
            }

            $response['task'] = $row;
        }

        $this->response->data($response);
    }

    /**
     * @param  int  $id
     */
    public function xListTasksAction($id = 0)
    {
        $sql = "
            SELECT bt.*, (SELECT EXISTS (SELECT 1 FROM servers WHERE server_id = bt.server_id)) as server_exists
            FROM bundle_tasks AS bt
            LEFT JOIN farms AS f ON f.id = bt.farm_id
            WHERE bt.env_id = ?
            AND :FILTER:
            AND (bt.farm_id IS NULL OR bt.farm_id IS NOT NULL AND {$this->request->getFarmSqlQuery()})
        ";

        $args = [$this->getEnvironmentId()];

        if ($id) {
            $sql .= " AND bt.id = ?";
            $args[] = $id;
        }

        $response = $this->buildResponseFromSql2(
            $sql,
            ['id', 'server_id', 'rolename', 'status', 'os_family', 'dtadded', 'dtstarted', 'created_by_email'],
            ['bt.id', 'rolename'],
            $args
        );

        foreach ($response["data"] as &$row) {
            $row['dtadded'] = Scalr_Util_DateTime::convertTz($row['dtadded']);

            if (!$row['bundle_type']) {
                $row['bundle_type'] = "*";
            }

            if ($row['dtfinished'] && $row['dtstarted']) {
                $row['duration'] = Scalr_Util_DateTime::getDateTimeDiff($row['dtfinished'], $row['dtstarted']);
            }

            if ($row['dtfinished']) {
                $row['dtfinished'] = Scalr_Util_DateTime::convertTz($row['dtfinished']);
            }

            if ($row['dtstarted']) {
                $row['dtstarted'] = Scalr_Util_DateTime::convertTz($row['dtstarted']);
            }
        }

        $this->response->data($response);
    }
}
