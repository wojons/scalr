<?php

use Scalr\Acl\Acl;

class Scalr_UI_Controller_Dashboard_Widget_Farm extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return array(
            'type' => 'local'
        );
    }

    public function getContent($params = array())
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS);

        try {
            $dbFarm = DBFarm::LoadByID($params['farmId']);
            $this->user->getPermissions()->validate($dbFarm);
            $farmRoles = array();
            foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {
                $servCount = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_roleid = ? AND status IN ('Pending', 'Initializing', 'Running', 'Temporary')", array($dbFarmRole->ID));
                if ($servCount)
                    $farmRoles[] = array(
                        'farmId' 		=> $params['farmId'],
                        'roleId'		=> $this->db->GetOne("SELECT role_id FROM farm_roles WHERE id=? AND farmid=? LIMIT 1", array($dbFarmRole->ID,$params['farmId'])),
                        'farmRoleId' 	=> $dbFarmRole->ID,
                        'behaviors'		=> implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
                        'servCount'		=> $servCount
                    );
            }
            return array('servers'=>$farmRoles, 'name'=>$dbFarm->Name);
        } catch (Exception $e) {
            return '';
        }
    }
}
