<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Account2_Roles extends Scalr_UI_Controller
{

    public function hasAccess()
    {
        return parent::hasAccess() && $this->user->canManageAcl();
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/account2/roles/view.js', array(
            'definitions' => Acl::getResources(true)
        ), array('ui/account2/dataconfig.js'), array('ui/account2/roles/view.css'), array('account.roles', 'base.roles'));
    }

    public function xRemoveAction()
    {
        $acl = \Scalr::getContainer()->acl;
        $acl->deleteAccountRole($this->getParam('id'), $this->request->getUser()->getAccountId());
        $this->response->success('ACL successfully deleted');
    }

    public function xSaveAction()
    {
        $acl = \Scalr::getContainer()->acl;

        $this->request->defineParams(array(
            'id' => array('type' => 'string'),
            'name' => array('type' => 'string'),
            'baseRoleId' => array('type' => 'int'),
            'color' => array('type' => 'string'),
            'resources' => array('type' => 'json')
        ));

        $accountRoleId = $this->getParam('id');
        $baseRoleId = $this->getParam('baseRoleId');
        $accountId = $this->request->getUser()->getAccountId();
        $name = $this->getParam('name');
        $color = hexdec($this->getParam('color'));

        $r = $this->getParam('resources');
        $resources = array();
        if (is_array($r)) {
            foreach ($r as $v) {
                $resources[$v['id']] = $v;
            }
        }

        try {
            $accountRoleId = $acl->setAccountRole($accountId, $baseRoleId, $name, $color, $resources, $accountRoleId);
        } catch (\Exception $e) {
            if ($e instanceof \Scalr\Acl\Exception\AclException) {
                return $this->response->failure($e->getMessage());
            } else throw $e;
        }

        $this->response->data(array('role' => $acl->getAccountRoleComputed($accountRoleId)));
        $this->response->success('ACL successfully saved');
    }

    public function usageAction()
    {
        $acl = \Scalr::getContainer()->acl;
        $accountRole = $acl->getAccountRole($this->getParam('accountRoleId'));
        $this->user->getPermissions()->validate($accountRole);
        $this->response->page('ui/account2/roles/usage.js', array(
            'users' => array_values($acl->getUsersHaveAccountRole($this->getParam('accountRoleId'), $this->request->getUser()->getAccountId())),
            'role' => array(
                    'id' => $accountRole->getRoleId(),
                    'name' => $accountRole->getName()
            )
        ));
    }
}
