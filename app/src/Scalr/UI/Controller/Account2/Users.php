<?php
class Scalr_UI_Controller_Account2_Users extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'userId';

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
        $this->response->page('ui/account2/users/view.js',
            array(),
            array('ui/account2/dataconfig.js'),
            array('ui/account2/users/view.css'),
            array('account.users', 'account.teams', 'account.roles')
        );
    }

    public function xGroupActionHandlerAction()
    {
        $this->request->defineParams(array(
            'ids' => array('type' => 'json'), 'action'
        ));

        $processed = array();
        $errors = array();

        foreach($this->getParam('ids') as $userId) {
            try {
                $user = Scalr_Account_User::init();
                $user->loadById($userId);

                switch($this->getParam('action')) {
                    case 'delete':
                        if ($this->user->canRemoveUser($user)) {
                            $user->delete();
                            $processed[] = $user->getId();
                        } else {
                            throw new Scalr_Exception_Core('Insufficient permissions to remove user');
                        }
                        break;

                    case 'activate':
                        if ($this->user->getId() !== $user->getId() && $this->user->canEditUser($user)) {
                            $user->status = Scalr_Account_User::STATUS_ACTIVE;
                            $user->save();
                            $processed[] = $user->getId();
                        } else {
                            throw new Scalr_Exception_Core('Insufficient permissions to activate user');
                        }
                        break;

                    case 'deactivate':
                        if ($this->user->getId() !== $user->getId() && $this->user->canEditUser($user)) {
                            $user->status = Scalr_Account_User::STATUS_INACTIVE;
                            $user->save();
                            $processed[] = $user->getId();
                        } else {
                            throw new Scalr_Exception_Core('Insufficient permissions to deactivate user');
                        }
                        break;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $num = count($this->getParam('ids'));

        if (count($processed) == $num) {
            $this->response->success('All users processed');
        } else {
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully processed only %d from %d users. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(array('processed' => $processed));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'teams' => array('type' => 'json'), 'action',
            'password' => array('type' => 'string', 'rawValue' => true)
        ));

        $user = Scalr_Account_User::init();
        $validator = new Scalr_Validator();

        if (! $this->getParam('email'))
            throw new Scalr_Exception_Core('Email cannot be null');

        if ($validator->validateEmail($this->getParam('email'), null, true) !== true)
            throw new Scalr_Exception_Core('Email should be correct');

        if ($this->getParam('id')) {
            $user->loadById((int)$this->getParam('id'));

            if (!$this->user->canEditUser($user)) {
                throw new Scalr_Exception_InsufficientPermissions();
            }

            $user->updateEmail($this->getParam('email'));
        } else {
            $this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_USERS, 1);
            $user->create($this->getParam('email'), $this->user->getAccountId());

            $user->type = Scalr_Account_User::TYPE_TEAM_USER;
            $newUser = true;
        }

        $password = $this->getParam('password');
        if (!$password && ($this->request->hasParam('password') || $newUser)) {
            $password = $this->getCrypto()->sault(10);
            $sendResetLink = true;
        }
        if ($password) {
            $user->updatePassword($password);
        }

        if ($user->getId() != $this->user->getId() &&
            in_array($this->getParam('status'), array(Scalr_Account_User::STATUS_ACTIVE, Scalr_Account_User::STATUS_INACTIVE))) {
            $user->status = $this->getParam('status');
        }

        if (!$user->isAccountOwner()) {
            if ($this->getParam('isAccountAdmin')) {
                if ($this->user->isAccountOwner() && $this->getParam('isAccountSuperAdmin')) {
                    $user->type = Scalr_Account_User::TYPE_ACCOUNT_SUPER_ADMIN;
                } else if ($user->type != Scalr_Account_User::TYPE_ACCOUNT_SUPER_ADMIN) {
                    $user->type = Scalr_Account_User::TYPE_ACCOUNT_ADMIN;
                }
            } else {
                $user->type = Scalr_Account_User::TYPE_TEAM_USER;
            }
        }

        $user->fullname = $this->getParam('fullname');
        $user->comments = $this->getParam('comments');

        $user->save();

        $user->setAclRoles($this->getParam('teams'));

        if ($this->getParam('enableApi')) {
            $keys = Scalr::GenerateAPIKeys();
            $user->setSetting(Scalr_Account_User::SETTING_API_ENABLED, true);
            $user->setSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY, $keys['id']);
            $user->setSetting(Scalr_Account_User::SETTING_API_SECRET_KEY, $keys['key']);
        }

        $creatorName = $this->user->fullname;
        if (empty($creatorName)) {
            $creatorName = $this->user->isAccountOwner() ? 'Account owner' : ($this->user->isAccountAdmin() ? 'Account admin' : 'Team user');
        }

        if ($newUser) {
            try {
                $clientinfo = array(
                    'fullname'	=> $user->fullname,
                    'firstname' => $user->fullname,
                    'email'		=> $user->getEmail(),
                    'password'	=> $password,
                );

                $res = $this->getContainer()->mailer->sendTemplate(
                    SCALR_TEMPLATES_PATH . '/emails/referral.eml.php',
                    array(
                        "creatorName"     => $creatorName,
                        "clientFirstname" => $clientinfo['firstname'],
                        "email"           => $clientinfo['email'],
                        "password"        => $clientinfo['password'],
                        "siteUrl"         => "http://{$_SERVER['HTTP_HOST']}",
                        "wikiUrl"         => \Scalr::config('scalr.ui.wiki_url'),
                        "supportUrl"      => \Scalr::config('scalr.ui.support_url'),
                        "isUrl"           => (preg_match('/^http(s?):\/\//i', \Scalr::config('scalr.ui.support_url'))),
                    ),
                    $user->getEmail()
                );
            } catch (Exception $e) {
            }
        } elseif ($sendResetLink) {
            try {
                $hash = $this->getCrypto()->sault(10);

                $user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash);

                $clientinfo = array(
                    'email'    => $user->getEmail(),
                    'fullname' => $user->fullname
                );

                $res = $this->getContainer()->mailer->sendTemplate(
                    SCALR_TEMPLATES_PATH . '/emails/user_account_confirm.eml',
                    array(
                        "{{fullname}}" => $clientinfo['fullname'],
                        "{{pwd_link}}" => "https://{$_SERVER['HTTP_HOST']}/#/guest/updatePassword/?hash={$hash}"
                    ),
                    $clientinfo['email'],
                    $clientinfo['fullname']
                );
            } catch (Exception $e) {
            }
        }

        $userTeams = array();
        $troles = $this->environment->acl->getUserRoleIdsByTeam(
            $user->id, array_map(create_function('$v', 'return $v["id"];'), $user->getTeams()), $user->getAccountId()
        );
        foreach ($troles as $teamId => $roles) {
            $userTeams[$teamId] = array(
                'roles' => $roles,
            );
        }

        $this->response->data(array('user' => $user->getUserInfo(), 'teams' => $userTeams));
        $this->response->success('User successfully saved');
    }

    public function xRemoveAction()
    {
        $user = Scalr_Account_User::init();
        $user->loadById($this->getParam('userId'));

        if (!$this->user->canRemoveUser($user)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $user->delete();
        $this->response->success('User successfully removed');
        return;
    }
}
