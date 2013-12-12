<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Scripts_Shortcuts extends Scalr_UI_Controller
{

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_FARMS_SCRIPTS);
    }

	public function defaultAction()
    {
        $this->viewAction();
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'shortcuts' => array('type' => 'json')
        ));

        $allFarms = $this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS);

        foreach ($this->getParam('shortcuts') as $scId) {
            $this->db->Execute("
                DELETE FROM farm_role_scripts
                WHERE farmid IN (SELECT id FROM farms WHERE env_id=?" . (!$allFarms ? " AND created_by_id = " . $this->db->qstr($this->user->getId()) : "") . ")
                AND id=? AND ismenuitem='1'",
                array($this->getEnvironmentId(), $scId)
            );
        }

        $this->response->success();
    }

    public function viewAction()
    {
        $this->response->page('ui/scripts/shortcuts/view.js');
    }

    public function xListShortcutsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'string', 'default' => 'id'),
            'dir' => array('type' => 'string', 'default' => 'DESC')
        ));

        $allFarms = $this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS);

        $sql = "
            SELECT farm_role_scripts.*, scripts.name as scriptname
            FROM farm_role_scripts
            JOIN scripts ON scripts.id = farm_role_scripts.scriptid
            WHERE ismenuitem='1'
            AND farmid IN (SELECT id FROM farms WHERE env_id='" . intval($this->getEnvironmentId()) . "'
            " . (!$allFarms ? " AND created_by_id = " . $this->db->qstr($this->user->getId()) : "") . ")
        ";

        $response = $this->buildResponseFromSql($sql);

        foreach ($response['data'] as &$row) {
            $row['farmname'] = $this->db->GetOne("
                SELECT name FROM farms
                WHERE id=?" . (!$allFarms ? " AND created_by_id = " . $this->db->qstr($this->user->getId()) : "") . "
                LIMIT 1
            ", array($row['farmid']));
            if ($row['farm_roleid']) {
                try {
                    $DBFarmRole = DBFarmRole::LoadByID($row['farm_roleid']);
                    $row['rolename'] = $DBFarmRole->GetRoleObject()->name;
                } catch (Exception $e) {
                }
            }
        }

        $this->response->data($response);
    }
}
