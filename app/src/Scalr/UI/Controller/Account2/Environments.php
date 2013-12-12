<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Account2_Environments extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'envId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/account2/environments/view.js',
            array(),
            array('ui/account2/dataconfig.js'),
            array('ui/account2/environments/view.css'),
            array('account.environments', 'account.teams')
        );
    }

    public function xRemoveAction()
    {
        if (!$this->user->isAccountOwner()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $env = Scalr_Environment::init()->loadById($this->getParam('envId'));
        $this->user->getPermissions()->validate($env);
        $env->delete();

        if ($env->id == $this->getEnvironmentId()) {
            Scalr_Session::getInstance()->setEnvironmentId(null); // reset
        }

        $this->response->success("Environment successfully removed");
        $this->response->data(array('env' => array('id' => $env->id), 'flagReload' => $env->id == $this->getEnvironmentId() ? true : false));
    }

    public function xSaveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_ADMINISTRATION_ENV_CLOUDS);

        $params = array(
            'envId' => array('type' => 'int'),
            'teams' => array('type' => 'json')
        );
        if ($this->user->isAccountOwner()) {
            $params['name'] = array('type' => 'string', 'validator' => array(
                Scalr_Validator::REQUIRED => true,
                Scalr_Validator::NOHTML => true
            ));
        }
        $this->request->defineParams($params);

        $this->request->validate();
        if ($this->request->isValid()) {
            $isNew = false;
            if (!$this->getParam('envId')) {//create new environment
                if (!$this->user->isAccountOwner()) {
                    throw new Scalr_Exception_InsufficientPermissions();
                }

                $this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_ENVIRONMENTS, 1);
                $env = $this->user->getAccount()->createEnvironment($this->getParam('name'));
                $isNew = true;
            } else {
                $env = Scalr_Environment::init()->loadById($this->getParam('envId'));
            }

            $this->user->getPermissions()->validate($env);

            if (!$this->user->getAclRolesByEnvironment($env->id)->isAllowed(Acl::RESOURCE_ADMINISTRATION_ENV_CLOUDS))
                throw new Scalr_Exception_InsufficientPermissions();

            //set name and status
            if ($this->user->isAccountOwner()) {
                $env->name = $this->getParam('name');
            }

            if ($this->user->canManageAcl()) {
                $env->status = $this->getParam('status') == Scalr_Environment::STATUS_ACTIVE ? Scalr_Environment::STATUS_ACTIVE : Scalr_Environment::STATUS_INACTIVE;
            }

            $env->save();

            if ($this->user->canManageAcl()) {
                //set teams
                $env->clearTeams();
                if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap') {
                    foreach ($this->getParam('teams') as $name) {
                        $name = trim($name);
                        if ($name) {
                            $id = $this->db->GetOne('SELECT id FROM account_teams WHERE name = ? AND account_id = ? LIMIT 1', array($name, $this->user->getAccountId()));
                            if (! $id) {
                                $team = new Scalr_Account_Team();
                                $team->name = $name;
                                $team->accountId = $this->user->getAccountId();
                                $team->save();
                                $id = $team->id;
                            }

                            $env->addTeam($id);
                        }
                    }
                    // remove unused teams
                    $ids = $this->db->GetAll('
                        SELECT account_teams.id
                        FROM account_teams
                        LEFT JOIN account_team_envs ON account_team_envs.team_id = account_teams.id
                        WHERE ISNULL(account_team_envs.env_id) AND account_teams.account_id = ?
                    ', array($this->user->getAccountId()));

                    foreach ($ids as $id) {
                        $team = new Scalr_Account_Team();
                        $team->loadById($id['id']);
                        $team->delete();
                    }
                } else {
                    foreach ($this->getParam('teams') as $id)
                        $env->addTeam($id);
                }
            }

            $this->response->success($isNew?'Environment successfully created':'Environment saved');

            $env = Scalr_Environment::init()->loadById($env->id);//reload env to be sure we have actual params

            $teams = array();
            foreach ($env->getTeams() as $teamId) {
                if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap') {
                    $team = new Scalr_Account_Team();
                    $team->loadById($teamId);
                    $teams[] = $team->name;
                } else {
                    $teams[] = $teamId;
                }
            }

            $this->response->data(array(
                'env' => array(
                    'id' => $env->id,
                    'name' => $env->name,
                    'status' => $env->status,
                    'platforms' => $env->getEnabledPlatforms(),
                    'teams' => $teams
                )
            ));

        } else {
            $this->response->failure($this->request->getValidationErrorsMessage());
        }
    }
}
