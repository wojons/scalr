<?php

class Scalr_UI_Controller_Account2_Teams extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'teamId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/account2/teams/view.js',
            array(),
            array('ui/account2/dataconfig.js'),
            array('ui/account2/teams/view.css'),
            array('account.environments', 'account.users', 'account.teams', 'account.roles')
        );
    }

    public function xSaveAction()
    {
        if (!$this->user->canManageAcl()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->request->defineParams(array(
            'envs' => array('type' => 'json'),
            'users' => array('type' => 'json'),
            'teamName' => array('type' => 'string', 'validator' => array(
                Scalr_Validator::REQUIRED => true,
                Scalr_Validator::NOHTML => true
            )),
            'accountRoleId' => array('type' => 'string', 'validator' => array(
                Scalr_Validator::REQUIRED => true,
                Scalr_Validator::NOHTML => true
            )),
        ));

        $team = Scalr_Account_Team::init();
        if ($this->getParam('teamId')) {
            $team->loadById($this->getParam('teamId'));
            if ($team->accountId != $this->user->getAccountId()) {
                throw new Scalr_Exception_InsufficientPermissions();
            }
        } else {
            $team->accountId = $this->user->getAccountId();
        }

        $this->request->validate();
        if (! $this->request->isValid()) {
            $this->response->failure();
            $this->response->data($this->request->getValidationErrors());
            return;
        }

        $team->name = $this->getParam('teamName');
        $team->accountRoleId = $this->getParam('accountRoleId');
        $team->save();
        $team->setUserRoles($this->getParam('users'));

        $users = $team->getUsers();
        if (!empty($users)) {
            $uroles = $this->environment->acl->getUserRoleIdsByTeam(
                array_map(create_function('$a', 'return $a["id"];'), $users),
                $team->id, $team->accountId
            );
            foreach ($users as &$user) {
                $user = array(
                    'id'    => $user['id'],
                    'roles' => $uroles[$user['id']],
                );
            }
        }

        $this->response->data(array('team' => array(
            'id' => $team->id,
            'name' => $team->name,
            'users' => $users,
            'account_role_id' => $team->accountRoleId
        )));
        $this->response->success('Team successfully saved');
    }

    public function xRemoveAction()
    {
        if (!$this->user->canManageAcl()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $team = Scalr_Account_Team::init();
        $team->loadById($this->getParam('teamId'));

        if ($team->accountId != $this->user->getAccountId()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $team->delete();

        $this->response->success('Team successfully removed');
    }

}
