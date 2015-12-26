<?php

use Scalr\UI\Request\Validator;
use Scalr\Model\Entity\Account\User;

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
            array('account.users', 'account.teams', 'account.acl')
        );
    }

    public function xGroupActionHandlerAction()
    {
        $this->request->defineParams(array(
            'ids' => array('type' => 'json'), 'action'
        ));

        $processed = array();
        $errors = array();
        $actionMsg = '';

        foreach($this->getParam('ids') as $userId) {
            try {
                $user = Scalr_Account_User::init();
                $user->loadById($userId);

                switch($this->getParam('action')) {
                    case 'delete':
                        $actionMsg = 'removed';
                        if ($this->user->canRemoveUser($user)) {
                            $user->delete();
                            $processed[] = $user->getId();
                        } else {
                            throw new Scalr_Exception_Core('Insufficient permissions to remove user');
                        }
                        break;

                    case 'activate':
                        $actionMsg = 'activated';
                        if ($this->user->getId() !== $user->getId() && $this->user->canEditUser($user)) {
                            if ($user->status == Scalr_Account_User::STATUS_ACTIVE) {
                                throw new Scalr_Exception_Core('User(s) has already been activated');
                            }

                            $user->status = Scalr_Account_User::STATUS_ACTIVE;
                            $user->save();
                            $processed[] = $user->getId();
                        } else {
                            throw new Scalr_Exception_Core('Insufficient permissions to activate user');
                        }

                        break;

                    case 'deactivate':
                        $actionMsg = 'deactivated';
                        if ($this->user->getId() !== $user->getId() && $this->user->canEditUser($user)) {
                            if ($user->status == Scalr_Account_User::STATUS_INACTIVE) {
                                throw new Scalr_Exception_Core('User(s) has already been suspended');
                            }

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
            $this->response->success("Selected user(s) successfully {$actionMsg}");
        } else {
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully {$actionMsg} only %d from %d users. \nFollowing errors occurred:\n%s", count($processed), $num, join(array_unique($errors), "\n")));
        }

        $this->response->data(array('processed' => $processed));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'teams' => array('type' => 'json'), 'action',
            'password' => array('type' => 'string', 'rawValue' => true),
            'currentPassword' => array('type' => 'string', 'rawValue' => true)
        ));

        $newUser = $existingPasswordChanged = $sendResetLink = false;

        $user = Scalr_Account_User::init();
        $validator = new Validator();

        $id = (int) $this->getParam('id');

        if ($id) {
            $user->loadById($id);
        } else {
            if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap')
                throw new Exception("Adding new users is not supported with LDAP user management");

            $newUser = true;
        }

        if ($this->getContainer()->config->get('scalr.auth_mode') != 'ldap') {
            $email = $this->getParam('email');

            if (($isEmailValid = $validator->validateEmail($email)) !== true) {
                throw new Scalr_Exception_Core($isEmailValid);
            }

            $password = $this->getParam('password');

            if (empty($password) && ($this->request->hasParam('password') || $newUser)) {
                if ($user->id == $this->user->id) {
                    $this->response->data(['errors' => ['password' => 'You cannot reset password for yourself']]);
                    $this->response->failure();
                    return;
                }

                $password = Scalr::GenerateSecurePassword(($user->isAccountAdmin() || $user->isAccountOwner()) ? User::PASSWORD_ADMIN_LENGTH : User::PASSWORD_USER_LENGTH);
                $sendResetLink = true;
            } else {
                if ($this->request->hasParam('password') && ($isPasswordValid = $validator->validatePassword($password, $user->isAccountAdmin() || $user->isAccountOwner() ? ['admin'] : [])) !== true) {
                    $this->response->data(['errors' => ['password' => $isPasswordValid]]);
                    $this->response->failure();
                    return;
                }

                if (!empty($password)) {
                    $existingPasswordChanged = true;
                }
            }

            if (!$newUser &&
                ($sendResetLink || $existingPasswordChanged) &&
                $this->request->hasParam('password') &&
                !$this->user->checkPassword($this->getParam('currentPassword'), false)) {
                $this->response->data(['errors' => ['currentPassword' => 'Invalid password']]);
                $this->response->failure();
                return;
            }

            if ($id) {
                if (!$this->user->canEditUser($user)) {
                    throw new Scalr_Exception_InsufficientPermissions();
                }

                $user->updateEmail($email);
            } else {
                $this->user->getAccount()->validateLimit(Scalr_Limits::ACCOUNT_USERS, 1);
                $user->type = Scalr_Account_User::TYPE_TEAM_USER;
                $user->create($email, $this->user->getAccountId());
            }

            if (!empty($password)) {
                $user->updatePassword($password);
            }
        }

        if ($user->getId() != $this->user->getId() &&
            in_array($this->getParam('status'), array(Scalr_Account_User::STATUS_ACTIVE, Scalr_Account_User::STATUS_INACTIVE))) {
            $user->status = $this->getParam('status');
        }

        if (!$user->isAccountOwner()) {
            if ($this->getParam('isAccountAdmin')) {
                if ($this->user->isAccountOwner()) {
                    $user->type = $this->getParam('isAccountSuperAdmin') ? Scalr_Account_User::TYPE_ACCOUNT_SUPER_ADMIN : Scalr_Account_User::TYPE_ACCOUNT_ADMIN;
                } else if ($this->user->isAccountAdmin() && $user->type != Scalr_Account_User::TYPE_ACCOUNT_SUPER_ADMIN) {
                    $user->type = Scalr_Account_User::TYPE_ACCOUNT_ADMIN;
                }
            } else {
                $user->type = Scalr_Account_User::TYPE_TEAM_USER;
            }
        }

        $user->fullname = $this->getParam('fullname');
        $user->comments = $this->getParam('comments');

        $user->save();

        if (!empty($password)) {
            $user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, "");
        }

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

                $url = Scalr::config('scalr.endpoint.scheme') . "://" . Scalr::config('scalr.endpoint.host');

                $res = $this->getContainer()->mailer->sendTemplate(
                    SCALR_TEMPLATES_PATH . '/emails/referral.eml.php',
                    array(
                        "creatorName"     => $creatorName,
                        "clientFirstname" => $clientinfo['firstname'],
                        "email"           => $clientinfo['email'],
                        "password"        => $clientinfo['password'],
                        "siteUrl"         => $url,
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
                $url = Scalr::config('scalr.endpoint.scheme') . "://" . Scalr::config('scalr.endpoint.host');

                $user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash);

                $clientinfo = array(
                    'email'    => $user->getEmail(),
                    'fullname' => $user->fullname
                );

                $res = $this->getContainer()->mailer->sendTemplate(
                    SCALR_TEMPLATES_PATH . '/emails/user_account_confirm.eml',
                    array(
                        "{{fullname}}" => $clientinfo['fullname'],
                        "{{pwd_link}}" => "{$url}/?resetPasswordHash={$hash}"
                    ),
                    $clientinfo['email'],
                    $clientinfo['fullname']
                );
            } catch (Exception $e) {
            }
        } else if ($existingPasswordChanged) {
            // Send notification E-mail
            $this->getContainer()->mailer->sendTemplate(
                SCALR_TEMPLATES_PATH . '/emails/password_change_notification.eml',
                array(
                    '{{fullname}}' => $user->fullname ? $user->fullname : $user->getEmail()
                ),
                $user->getEmail(), $user->fullname
            );
        }

        $userTeams = array();
        $troles = $this->getContainer()->acl->getUserRoleIdsByTeam(
            $user->id, array_map(create_function('$v', 'return $v["id"];'), $user->getTeams()), $user->getAccountId()
        );
        foreach ($troles as $teamId => $roles) {
            $userTeams[$teamId] = array(
                'roles' => $roles,
            );
        }

        $data = ['user' => $user->getUserInfo(), 'teams' => $userTeams];
        if ($existingPasswordChanged && $user->getId() == $this->user->getId()) {
            Scalr_Session::create($this->user->getId());
            $data['specialToken'] = Scalr_Session::getInstance()->getToken();
        }
        $this->response->data($data);
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
        $this->response->success('Selected user successfully removed');
        return;
    }
}
