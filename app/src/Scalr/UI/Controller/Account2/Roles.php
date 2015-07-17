<?php
use Scalr\Acl\Acl;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\Validator;

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

    /**
     * Removes ACL
     *
     * @param string $id
     * @throws InvalidArgumentException
     */
    public function xRemoveAction($id)
    {
        $acl = \Scalr::getContainer()->acl;
        $acl->deleteAccountRole($id, $this->request->getUser()->getAccountId());
        $this->response->success('ACL successfully deleted');
    }

    /**
     * Saves ACL
     *
     * @param string   $id
     * @param string   $name
     * @param int      $baseRoleId
     * @param string   $color
     * @param JsonData $resources
     * @throws InvalidArgumentException
     */
    public function xSaveAction($id, $name, $baseRoleId, $color, JsonData $resources)
    {
        $validator = new Validator();
        $validator->validate($name, 'name', Validator::NOEMPTY);

        if (!$validator->isValid($this->response))
            return;


        $acl = \Scalr::getContainer()->acl;

        $r = [];
        foreach ($resources as $v) {
            $r[$v['id']] = $v;
        }

        try {
            $id = $acl->setAccountRole($this->request->getUser()->getAccountId(), $baseRoleId, $name, hexdec($color), $r, $id);
        } catch (\Exception $e) {
            if ($e instanceof \Scalr\Acl\Exception\AclException) {
                return $this->response->failure($e->getMessage());
            } else throw $e;
        }

        $this->response->data(['role' => $acl->getAccountRoleComputed($id)]);
        $this->response->success('ACL successfully saved');
    }

    /**
     * Returns ACL usage
     *
     * @param string $accountRoleId
     * @throws InvalidArgumentException
     */
    public function usageAction($accountRoleId)
    {
        $acl = \Scalr::getContainer()->acl;
        $accountRole = $acl->getAccountRole($accountRoleId);
        $this->user->getPermissions()->validate($accountRole);
        $this->response->page('ui/account2/roles/usage.js', array(
            'users' => array_values($acl->getUsersHaveAccountRole($accountRoleId, $this->request->getUser()->getAccountId())),
            'role' => array(
                'id' => $accountRole->getRoleId(),
                'name' => $accountRole->getName()
            )
        ));
    }
}
