<?php
use Scalr\Acl\Acl;
use Scalr\Server\Alerts;

class Scalr_UI_Controller_Alerts extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'alertId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed([Acl::RESOURCE_FARMS, Acl::RESOURCE_TEAM_FARMS, Acl::RESOURCE_OWN_FARMS]);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/alerts/view.js');
    }

    public function xListAction($serverId = null, $farmId = null, $farmRoleId = null, $status = null)
    {
        $sql = "SELECT sa.* FROM server_alerts sa LEFT JOIN farms f ON f.id = sa.farm_id WHERE sa.env_id = ? AND " . $this->request->getFarmSqlQuery();
        $args = [$this->getEnvironmentId()];

        if ($serverId) {
            $sql .= " AND sa.server_id = ?";
            $args[] = $serverId;
        }

        if ($farmId) {
            $sql .= " AND sa.farm_id = ?";
            $args[] = $farmId;

            if ($farmRoleId) {
                $sql .= " AND sa.farm_roleid = ?";
                $args[] = $farmRoleId;
            }
        }

        if ($status) {
            $sql .= " AND sa.status = ?";
            $args[] = $status;
        }

        $response = $this->buildResponseFromSql2($sql, ['metric', 'status', 'dtoccured', 'dtlastcheck', 'dtsolved', 'details'], ['server_id', 'details'], $args);

        foreach ($response['data'] as $i => $row) {
            $row['dtoccured'] = Scalr_Util_DateTime::convertTz($row['dtoccured']);

            if ($row['dtlastcheck'])
                $row['dtlastcheck'] = Scalr_Util_DateTime::convertTz($row['dtlastcheck']);
            else
                $row['dtlastcheck'] = false;

            if ($row['status'] == Alerts::STATUS_RESOLVED)
                $row['dtsolved'] = Scalr_Util_DateTime::convertTz($row['dtsolved']);
            else
                $row['dtsolved'] = false;

            $row['metric'] = Alerts::getMetricName($row['metric']);

            $row['farm_name'] = DBFarm::LoadByID($row['farm_id'])->Name;

            try {
                $row['role_name'] = DBFarmRole::LoadByID($row['farm_roleid'])->GetRoleObject()->name;

                $dbServer = DBServer::LoadByID($row['server_id']);
                $row['server_exists'] = ($dbServer->status == SERVER_STATUS::RUNNING) ? true : false;
            } catch (Exception $e) {

            }

            $response['data'][$i] = $row;
        }

        $this->response->data($response);
    }
}
