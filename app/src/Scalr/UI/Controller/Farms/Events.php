<?php
use Scalr\Acl\Acl;
use Scalr\Model\Entity\WebhookHistory;

class Scalr_UI_Controller_Farms_Events extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'eventId';

    /**
     *
     * @var DBFarm
     */
    private $dbFarm;

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_FARMS);
    }

    public function init()
    {
        $this->dbFarm = DBFarm::LoadByID($this->getParam(Scalr_UI_Controller_Farms::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($this->dbFarm);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/farms/events/view.js', array(
            'farmName' => $this->dbFarm->Name
        ));
    }

    public function xListEventsAction()
    {
        $this->request->defineParams(array(
            'farmId' => array('type' => 'int'),
            'eventServerId',
            'eventId',
            'query' => array('type' => 'string'),
            'sort' => array('type' => 'string', 'default' => 'id'),
            'dir' => array('type' => 'string', 'default' => 'DESC')
        ));

        $sql = "SELECT farmid, message, type, dtadded, event_server_id, event_id FROM events WHERE farmid='{$this->dbFarm->ID}'";

        if ($this->getParam('eventServerId'))
            $sql .= " AND event_server_id = ".$this->db->qstr($this->getParam('eventServerId'));

        if ($this->getParam('eventId'))
            $sql .= " AND event_id = ".$this->db->qstr($this->getParam('eventId'));

        $response = $this->buildResponseFromSql($sql, array("message", "type", "dtadded", "event_server_id", "event_id"));

        $cache = array();

        foreach ($response['data'] as &$row) {
            $row['message'] = nl2br($row['message']);
            $row["dtadded"] = Scalr_Util_DateTime::convertTz($row["dtadded"]);

            $row['scripts'] = $this->db->GetOne("SELECT COUNT(*) FROM scripting_log WHERE event_id = ?", array($row['event_id']));

            if ($row['event_server_id']) {
                
                try {
                    $es = DBServer::LoadByID($row['event_server_id']);
                } catch (Exception $e) {}

                if ($es) {
                    if (!$cache['farm_names'][$es->farmId])
                        $cache['farm_names'][$es->farmId] = $this->db->GetOne("SELECT name FROM farms WHERE id=?", array($es->farmId));
                    $row['event_farm_name'] = $cache['farm_names'][$es->farmId];
                    $row['event_farm_id'] = $es->farmId;

                    $row['event_farm_roleid'] = $es->farmRoleId;

                    if (!$cache['role_names'][$es->GetFarmRoleObject()->RoleID])
                        $cache['role_names'][$es->GetFarmRoleObject()->RoleID] = $es->GetFarmRoleObject()->GetRoleObject()->name;
                    $row['event_role_name'] = $cache['role_names'][$es->GetFarmRoleObject()->RoleID];

                    $row['event_server_index'] = $es->index;
                }
            }
            $row['webhooks_count'] = count(WebhookHistory::findByEventId($row['event_id']));
        }

        $this->response->data($response);
    }
}
