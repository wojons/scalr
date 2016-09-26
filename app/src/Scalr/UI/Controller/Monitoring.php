<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Monitoring extends Scalr_UI_Controller
{

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed([Acl::RESOURCE_FARMS, Acl::RESOURCE_TEAM_FARMS, Acl::RESOURCE_OWN_FARMS], Acl::PERM_FARMS_STATISTICS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $sql = "SELECT `id`, `name`, `hash` FROM farms f WHERE env_id = ? AND status = ? AND " . $this->request->getFarmSqlQuery(Acl::PERM_FARMS_STATISTICS);
        $args = [$this->getEnvironmentId(), FARM_STATUS::RUNNING];
        $farms = $this->db->GetAll($sql, $args);

        $conf = $this->getContainer()->config->get('scalr.load_statistics.connections.plotter');

        $children = array();
        foreach ($farms as $farm) {
            $farm['roles'] = $this->db->GetAll("
                SELECT fr.`id`, fr.`alias` AS `name`, r.`is_scalarized` AS isScalarized
                FROM `farm_roles` fr
                INNER JOIN `roles` r ON fr.`role_id` = r.`id`
                WHERE fr.`farmid` = ?
            ", [$farm['id']]);

            $childrenRoles = array();
            foreach ($farm['roles'] as $role) {
                $servers = array();
                if ($role['isScalarized'] == 1) {
                    $role['servers'] = $this->db->GetAll("SELECT `index`, `remote_ip`, `local_ip` FROM `servers` WHERE farm_roleid = ?", $role['id']);

                    foreach ($role['servers'] as $serv) {
                        $servers[] = array(
                            'text' => '#' . $serv['index'] . ' (' . ($serv['remote_ip'] ? $serv['remote_ip'] : $serv['local_ip']) . ')',
                            'leaf' => true,
                            'checked' => false,
                            'params' => array(
                                'farmId' => $farm['id'],
                                'farmName' => $farm['name'],
                                'farmRoleId' => $role['id'],
                                'farmRoleName' => $role['name'],
                                'index' => $serv['index'],
                                'hash' => $farm['hash']
                            ),
                            'value' => '#' . $serv['index'],
                            'icon' => '/ui2/images/space.gif'
                        );
                    }
                }

                $ritem = array(
                    'text' => $role['name'],
                    'leaf' => true,
                    'checked' => false,
                    'isScalarized' => $role['isScalarized'],
                    'params' => array(
                        'farmId' => $farm['id'],
                        'farmName' => $farm['name'],
                        'farmRoleId' => $role['id'],
                        'farmRoleName' => $role['name'],
                        'hash' => $farm['hash']
                    ),
                    'value' => $role['name'],
                    'icon' => '/ui2/images/space.gif'
                );

                if (count($servers)) {
                    $ritem['expanded'] = true;
                    $ritem['leaf'] = false;
                    $ritem['children'] = $servers;
                }

                $childrenRoles[] = $ritem;
            }

            $item = array(
                'text' => $farm['name'],
                'params' => array(
                    'farmId' => $farm['id'],
                    'farmName' => $farm['name'],
                    'hash' => $farm['hash']
                ),
                'value' => $farm['name'],
                'checked' => false,
                'leaf' => true,
                'icon' => '/ui2/images/space.gif'
            );

            if (!empty($farm['roles'])) {
                $item['expanded'] = true;
                $item['leaf'] = false;
                $item['children'] = $childrenRoles;
            }

            $children[] = $item;
        }
        if (empty($children)) {
            throw new Exception('No Farms in running state found.');
        }
        $this->response->page('ui/monitoring/view.js', array('children' => $children, 'hostUrl' => "{$conf['scheme']}://{$conf['host']}:{$conf['port']}"), array('ui/monitoring/window.js'), array('ui/monitoring/view.css'));
    }
}

