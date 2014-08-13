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
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_FARMS_STATISTICS);
    }

    public function viewAction()
    {
        $farms = self::loadController('Farms')->getList(array('status' => FARM_STATUS::RUNNING));
        $conf = $this->getContainer()->config->get('scalr.load_statistics.connections.plotter');

        $children = array();
        $hasServers = false;
        $hasRoles = false;
        foreach ($farms as $farm) {
            $hash = $this->db->GetOne('SELECT hash FROM farms WHERE id = ?', array($farm['id']));

            $this->request->setParam('farmId', $farm['id']);
            $farm['roles'] = self::loadController('Roles', 'Scalr_UI_Controller_Farms')->getList();

            if (!empty($farm['roles'])) {
                $hasRoles = true;
            }

            $childrenRoles = array();
            foreach ($farm['roles'] as $role) {

                $this->request->setParam('farmRoleId', $role['id']);
                $role['servers'] = self::loadController('Servers')->getList(array(SERVER_STATUS::RUNNING, SERVER_STATUS::INIT));

                if (count($role['servers'])) {
                    $hasServers = true;
                }

                $servers = array();
                foreach ($role['servers'] as $serv) {

                    $ip = $serv['remote_ip'] ? $serv['remote_ip'] : $serv['local_ip'];

                    $servers[] = array(
                        'text' => '#'.$serv['index']. ' ('.$ip.')',
                        'leaf' => true,
                        'checked' => false,
                        'params' => array(
                            'farmId' => $farm['id'],
                            'farmRoleId' => $role['id'],
                            'index' => $serv['index'],
                            'hash' => $hash
                        ),
                        'value' => '#'.$serv['index'],
                        'icon' => '/ui2/images/space.gif'
                    );
                }

                $ritem = array(
                    'text' => $role['name'],
                    'leaf' => true,
                    'checked' => false,
                    'params' => array(
                        'farmId' => $farm['id'],
                        'farmRoleId' => $role['id'],
                        'hash' => $hash
                    ),
                    'value' => $role['name'],
                    'icon' => '/ui2/images/space.gif'
                );

                if ($hasServers) {
                    $ritem['expanded'] = true;
                    $ritem['leaf'] = false;
                    $ritem['children'] = $servers;
                }

                $childrenRoles[] = $ritem;

                $hasServers = false;
            }

            $item = array(
                'text' => $farm['name'],
                'params' => array(
                    'farmId' => $farm['id'],
                    'hash' => $hash
                ),
                'value' => $farm['name'],
                'checked' => false,
                'leaf' => true,
                'icon' => '/ui2/images/space.gif'
            );

            if ($hasRoles) {
                $item['expanded'] = true;
                $item['leaf'] = false;
                $item['children'] = $childrenRoles;
            }

            $children[] = $item;
            $hasRoles = false;
        }
        $this->response->page('ui/monitoring/view.js', array('children' => $children, 'hostUrl' => "{$conf['scheme']}://{$conf['host']}:{$conf['port']}"), array('ui/monitoring/window.js'), array('ui/monitoring/view.css'));
    }
}

