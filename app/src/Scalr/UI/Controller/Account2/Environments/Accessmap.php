<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Account2_Environments_Accessmap extends Scalr_UI_Controller
{
    /**
     *
     * @var Scalr_Environment
     */
    private $env;

    public function init()
    {
        $this->env = Scalr_Environment::init()->loadById($this->getParam(Scalr_UI_Controller_Environments::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($this->env);
    }

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
        $users = array();
        foreach ($this->env->getTeams() as $teamId) {
            $team = Scalr_Account_Team::init()->loadById($teamId);
            foreach ($team->getUsers() as $user) {
                if (!isset($users[$user['id']])) {
                    $users[$user['id']] = array(
                        'id' => $user['id'],
                        'name' => !empty($user['fullname']) ? $user['fullname']: $user['email'],
                        'email' => $user['email'],
                        'teams' => array()
                    );
                }
                $users[$user['id']]['teams'][] = array(
                    'id' => $team->id,
                    'name' => $team->name
                );
            }
        }


        $this->response->page('ui/account2/environments/accessmap.js', array(
            'definitions' => Acl::getResources(true),
            'users' => array_values($users),
            'env' => array(
                    'id' => $this->env->id,
                    'name' => $this->env->name
            )
        ), [], ['ui/account2/acl/view.css']);
    }

    public function xGetAction() {
        $this->response->data(array(
            'resources' => \Scalr::getContainer()->acl->getUserRolesByEnvironment($this->request->getParam('userId'), $this->request->getParam('envId'), $this->user->getAccountId())->getArray()
        ));
    }
}
