<?php

class Scalr_UI_Controller_Account_Teams extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'teamId';

    public static function getApiDefinitions()
    {
        return array('xListTeams', 'xCreate', 'xRemove', 'xAddUser', 'xRemoveUser', 'xAddEnvironment', 'xRemoveEnvironment');
    }

    public function hasAccess()
    {
        return parent::hasAccess() && $this->user->canManageAcl();
    }

    public function xListTeamsAction()
    {
        $acl = $this->getEnvironment()->acl;

        $this->request->defineParams(array(
            'sort' => array('type' => 'json')
        ));

        // account owner, team owner
        if ($this->user->isAccountOwner() || $this->user->isAccountAdmin()) {
            $sql = "
                SELECT id, name FROM account_teams
                WHERE account_id='" . $this->user->getAccountId() . "'
            ";
        } else {
            // team user
            $sql = '
                SELECT account_teams.id, name FROM account_teams
                JOIN account_team_users ON account_teams.id = account_team_users.team_id
                WHERE user_id="' . $this->user->getId() . '"
            ';
        }
        $response = $this->buildResponseFromSql($sql, array('id', 'name'));
        foreach ($response["data"] as &$row) {
            $team = Scalr_Account_Team::init();
            $team->loadById($row['id']);

            $row['environments'] = $team->getEnvironments();
            try {
                $owner = $team->getOwner();
                $row['owner'] = array(
                    'id'    => $owner->getId(),
                    'email' => $owner->getEmail(),
                );
            } catch (Exception $e) {
            }

            $row['ownerTeam'] = !empty($row['owner']['id']) && $row['owner']['id'] == $this->user->getId() ?
                true : $this->user->isAccountAdmin();

            $row['groups'] = array();
            //Gets ACL roles of account level
            $accRoles = $acl->getAccountRoles($this->user->getAccountId());
            foreach ($accRoles as $accRole) {
                /* @var $accRole \Scalr\Acl\Role\AccountRoleObject */
                $row['groups'][] = array(
                    'id'   => $accRole->getRoleId(),
                    'name' => $accRole->getName(),
                );
            }

            $users = $team->getUsers();
            $teamUserIds = array_map(function($u){ return $u['id']; }, $users);
            if (!empty($teamUserIds)) {
                //Gets ACL roles which are assigned to this user with this team.
                $userRoles = $acl->getUserRoleIdsByTeam($teamUserIds, $team->id, $this->user->getAccountId());
                foreach ($users as &$user) {
                    $user['groups'] = array();
                    if (isset($userRoles[$user['id']])) {
                        foreach ($userRoles[$user['id']] as $accountRoleId) {
                            $user['groups'][] = array(
                                'id'   => $accountRoleId,
                                'name' => isset($accRoles[$accountRoleId]) ? $accRoles[$accountRoleId]->getName() : null,
                            );
                        }
                    }
                }
            }

            $row['users'] = $users;
        }
        $this->response->data($response);
    }

    public function diffUsersCmp($u1, $u2)
    {
        $v = ($u1['id'] == $u2['id']) ? 0 : (($u1['id'] > $u2['id']) ? 1 : -1);
        return $v;
    }

    public function xCreateAction()
    {
        if (!$this->user->isAccountOwner())
            throw new Scalr_Exception_InsufficientPermissions();

        $acl = $this->getEnvironment()->acl;

        $this->request->defineParams(array(
            'name' => array('type' => 'string', 'validator' => array(
                Scalr_Validator::NOHTML   => true,
                Scalr_Validator::REQUIRED => true
            )),
            'ownerId' => array('type' => 'int'), 'validator' => array(
                Scalr_Validator::REQUIRED => true
            ),
            'envId' => array('type' => 'int')
        ));

        $this->request->validate();

        try {
            $teamOwner = Scalr_Account_User::init();
            $teamOwner->loadById($this->getParam('ownerId'));
            if ($teamOwner->getAccountId() != $this->user->getAccountId()) {
                //Specified user belongs to different account.
                throw new Scalr_Exception_InsufficientPermissions();
            }
        } catch (Exception $e) {
            $this->request->addValidationErrors('ownerId', array($e->getMessage()));
        }

        try {
            if ($this->getParam('envId')) {
                $env = Scalr_Environment::init();
                $env->loadById($this->getParam('envId'));
                if ($env->clientId != $this->user->getAccountId()) {
                    //Specified environment belongs to different account
                    throw new Scalr_Exception_InsufficientPermissions();
                }
            }
        } catch (Exception $e) {
            $this->request->addValidationErrors('envId', array($e->getMessage()));
        }

        if (!$this->request->isValid()) {
            $this->response->failure();
            $this->response->data($this->request->getValidationErrors());
            return;
        }

        $team = Scalr_Account_Team::init();
        $team->name = $this->getParam('name');
        $team->accountId = $this->user->getAccountId();
        //Assigns the full access role of account level to the team.
        //If role does not exist it will be initialized.
        $team->accountRoleId = $acl->getFullAccessAccountRole($this->user->getAccountId(), true)->getRoleId();
        $team->save();

        $team->addUser($this->getParam('ownerId'), Scalr_Account_Team::PERMISSIONS_OWNER);
        //We need to make sure the specified team owner has AccountAdmin type.
        if (!$teamOwner->isAccountOwner() && !$teamOwner->isAccountAdmin()) {
            $teamOwner->type = \Scalr_Account_User::TYPE_ACCOUNT_ADMIN;
            $teamOwner->save();
        }

        if ($this->getParam('envId'))
            $team->addEnvironment($this->getParam('envId'));

        $this->response->success('Team has been successfully created.');
        $this->response->data(array('teamId' => $team->id));
    }

    /**
     * @return \Scalr_Account_Team
     */
    public function getTeam()
    {
        $team = Scalr_Account_Team::init();
        $team->loadById($this->getParam('teamId'));

        if ($team->accountId != $this->user->getAccountId())
            throw new Scalr_Exception_InsufficientPermissions();

        return $team;
    }

    public function xAddUserAction()
    {
        $this->request->defineParams(array(
            'userId'          => array('type' => 'int'),
            'userPermissions' => array('type' => 'string', 'validator' => array(
                Scalr_Validator::REQUIRED => false,
            )),
            'userGroups'       => array('type' => 'json')
        ));

        $team = $this->getTeam();

        $acl = $this->getContainer()->acl;

        if ($this->user->isAccountOwner() || $this->user->isAccountAdmin() || $this->user->isTeamOwner($team->id)) {
            if ($this->request->validate()->isValid()) {
                $user = Scalr_Account_User::init();
                $user->loadById($this->getParam('userId'));
                $this->user->getPermissions()->validate($user);

                if (!$this->user->isAccountOwner()) {
                    if ($this->getParam('userPermissions') == Scalr_Account_Team::PERMISSIONS_OWNER) {
                        throw new Scalr_Exception_InsufficientPermissions();
                    }
                }

                $team->addUser($user->id, $this->getParam('userPermissions'));

                $roles = $acl->getAccountRoles($this->user->getAccountId());

                $groups = array();
                foreach ((array)$this->getParam('userGroups') as $id) {
                    if (isset($roles[$id])) {
                        $groups[] = $id;
                    }
                }

                $acl->setUserRoles($team->id, $user->id, $groups, $this->user->getAccountId());

                $this->response->success('User has been successfully added to the team.');
            } else {
                $this->response->failure();
                $this->response->data($this->request->getValidationErrors());
                return;
            }
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    public function xRemoveUserAction()
    {
        $team = $this->getTeam();
        if ($this->user->canManageAcl() || $this->user->isTeamOwner($team->id)) {
            $user = Scalr_Account_User::init();
            $user->loadById($this->getParam('userId'));
            $this->user->getPermissions()->validate($user);

            if (!$this->user->isAccountOwner()) {
                if ($team->isTeamOwner($user->id)) {
                    throw new Scalr_Exception_InsufficientPermissions();
                }
            }

            $team->removeUser($user->id);
            $this->response->success('User has been successfully removed from the team.');
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    public function xAddEnvironmentAction()
    {
        $team = $this->getTeam();
        if ($this->user->isAccountOwner()) {
            $env = Scalr_Environment::init();
            $env->loadById($this->getParam('envId'));
            $this->user->getPermissions()->validate($env);

            $team->addEnvironment($env->id);
            $this->response->success('Environment has been successfully added to the team.');
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    public function xRemoveEnvironmentAction()
    {
        $team = $this->getTeam();
        if ($this->user->isAccountOwner()) {
            $env = Scalr_Environment::init();
            $env->loadById($this->getParam('envId'));
            $this->user->getPermissions()->validate($env);

            $team->removeEnvironment($env->id);
            $this->response->success('Environment has been successfully removed from the team.');
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    public function xRemoveAction()
    {
        $team = Scalr_Account_Team::init();
        $team->loadById($this->getParam('teamId'));

        if ($this->user->isAccountOwner() && $team->accountId == $this->user->getAccountId()) {
            $team->delete();
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $this->response->success();
    }
}
