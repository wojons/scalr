<?php

use Scalr\Util\CryptoTool;

class Scalr_UI_Controller_Account_Users extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'userId';

    public static function getApiDefinitions()
    {
        return array('xListUsers', 'xGetInfo', 'xGetApiKeys', 'xSave', 'xRemove');
    }

    protected function fillUserInfo(&$row)
    {
        $user = Scalr_Account_User::init();
        $user->loadById($row['id']);

        $row['status'] = $user->status;
        $row['email'] = $user->getEmail();
        $row['fullname'] = $user->fullname;
        $row['dtcreated'] = Scalr_Util_DateTime::convertTz(isset($user->dtcreated) ? $user->dtcreated : null);
        $row['dtlastlogin'] = !empty($user->dtlastlogin) ? Scalr_Util_DateTime::convertTz($user->dtlastlogin) : 'Never';
        $row['type'] = $user->type;
        $row['comments'] = $user->comments;

        $row['teams'] = $user->getTeams();
        $row['is2FaEnabled'] = $user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == '1' ? true : false;
        $row['password'] = isset($row['password']) ? true : false;

        switch ($row['type']) {
            case Scalr_Account_User::TYPE_ACCOUNT_OWNER:
                $row['type'] = 'Account Owner';
                break;
            default:
                $row['type'] = $user->isTeamOwner() ? 'Team Owner' : 'Team User';
                break;
        }
    }

    public function getList()
    {
        $sql = '';
        $params = array();

        // account owner, team owner
        if ($this->user->canManageAcl()) {
            $sql = 'SELECT u.id FROM account_users u WHERE u.account_id = ?';
            $params[] = $this->user->getAccountId();
        } else {
            // team user
            $teams = $this->user->getTeams();
            if (empty($teams)) {
                throw new Exception('You do not belong to any team.');
            }

            $sql = "
                SELECT u.id
                FROM account_users u
                JOIN account_team_users tu ON u.id = tu.user_id
                WHERE u.account_id = ?
            ";
            $params[] = $this->user->getAccountId();

            foreach ($this->user->getTeams() as $team) {
                $r[] = "tu.team_id = ?";
                $params[] = intval($team['id']);
            }

            $sql .= ' AND (' . implode(' OR ', $r) . ')';

            $sql .= ' GROUP BY u.id';
        }

        $usersList = $this->db->getAll($sql, $params);

        foreach ($usersList as &$row) {
            $this->fillUserInfo($row);
        }

        return $usersList;
    }

    public function xGetListAction()
    {
        $this->response->data(array('usersList' => $this->getList()));
    }

    public function xListUsersAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json')
        ));

        $selectCols = "SELECT u.id, u.status, u.email, u.fullname, u.dtcreated, u.dtlastlogin, u.type, u.comments";

        $accountId = intval($this->user->getAccountId());

        $sqlGroup = "GROUP BY u.id";
        $sqlWhere = "WHERE u.account_id = " . $accountId . " ";

        // account owner, team owner
        if ($this->user->canManageAcl() || $this->user->isTeamOwner()) {
            $sql = "
                $selectCols
                FROM account_users u
                LEFT JOIN account_team_users tu ON u.id = tu.user_id
            ";
        } else {
            // team user
            $teams = $this->user->getTeams();

            if (!count($teams))
                throw new Exception('You do not belong to any team.');

            $sql = "
                $selectCols
                FROM account_users u
                JOIN account_team_users tu ON u.id = tu.user_id
            ";

            $s = '';
            foreach ($this->user->getTeams() as $team) {
                $s .= intval($team['id']) . ",";
            }

            if (!empty($s)) {
                $sqlWhere .= " AND tu.team_id IN (" . rtrim($s, ',') . ") ";
            }
        }

        if ($this->getParam('teamId'))
            $sqlWhere .= " AND tu.team_id = " . intval($this->getParam('teamId')) . " ";

        if ($this->getParam('userId'))
            $sqlWhere .= " AND u.id = " . intval($this->getParam('userId')) . " ";

        if ($this->getParam('groupPermissionId')) {
            $sql .= "
                LEFT JOIN account_team_user_acls tua ON tua.account_team_user_id = tu.id
            ";
            $sqlWhere .= ' AND tua.account_role_id = ' . $this->db->qstr($this->getParam('groupPermissionId'));
        }

        $response = $this->buildResponseFromSql($sql . $sqlWhere, array('email', 'fullname'), $sqlGroup);

        foreach ($response["data"] as &$row) {
            $user = Scalr_Account_User::init();
            $user->loadById($row['id']);

            $row['dtcreated'] = Scalr_Util_DateTime::convertTz($row["dtcreated"]);
            $row['dtlastlogin'] = $row['dtlastlogin'] ? Scalr_Util_DateTime::convertTz($row["dtlastlogin"]) : 'Never';
            $row['teams'] = $user->getTeams();
            $row['is2FaEnabled'] = $user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == '1' ? true : false;

            switch ($row['type']) {
                case Scalr_Account_User::TYPE_ACCOUNT_OWNER:
                    $row['type'] = 'Account Owner';
                    break;

                default:
                    $row['type'] = $user->isTeamOwner() ? 'Team Owner' : 'Team User';
                    break;
            }
        }

        $this->response->data($response);
    }

    public function getUser($obj = false)
    {
        $user = Scalr_Account_User::init();
        $user->loadById($this->getParam('userId'));

        if ($user->getAccountId() == $this->user->getAccountId() &&
            ($this->user->isAccountOwner() || $this->user->isAccountAdmin() || $this->user->isTeamOwner())) {
            if ($this->user->isTeamOwner() && $this->user->getId() != $user->getId()) {
                if ($user->isAccountOwner() || $user->isTeamOwner()) {
                    throw new Scalr_Exception_InsufficientPermissions();
                }
            }

            if ($obj) {
                return $user;
            } else {
                return array(
                    'id'       => $user->getId(),
                    'email'    => $user->getEmail(),
                    'fullname' => $user->fullname,
                    'status'   => $user->status,
                    'comments' => $user->comments,
                );
            }
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    public function xGetInfoAction()
    {
        $this->response->data(array('user' => $this->getUser()));
    }

    public function xGetApiKeysAction()
    {
        $user = $this->getUser(true);

        if ($this->user->getId() != $user->getId() && !$this->user->canManageAcl()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        if ($user->getSetting(Scalr_Account_User::SETTING_API_ENABLED) == 1) {
            $this->response->data(array(
                'accessKey' => $user->getSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY),
                'secretKey' => $user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY)
            ));
        } else {
            $this->response->failure('API has not been enabled for this user yet.');
        }
    }

    public function xSaveAction()
    {
        $user = Scalr_Account_User::init();
        $validator = new Scalr_Validator();

        if (!$this->getParam('email'))
            throw new Scalr_Exception_Core('Email must be provided.');

        if ($validator->validateEmail($this->getParam('email'), null, true) !== true)
            throw new Scalr_Exception_Core('Email should be correct');

        if ($this->user->canManageAcl() || $this->user->isTeamOwner()) {
            $newUser = false;
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

            $sendResetLink = false;
            if (!$this->getParam('password')) {
                $password = CryptoTool::sault(10);
                $sendResetLink = true;
            } else {
                $password = $this->getParam('password');
            }

            if ($password != '******') {
                $user->updatePassword($password);
            }

            if (in_array($this->getParam('status'), array(Scalr_Account_User::STATUS_ACTIVE, Scalr_Account_User::STATUS_INACTIVE)) &&
                !$user->isAccountOwner()) {
                $user->status = $this->getParam('status');
            }

            $user->fullname = $this->getParam('fullname');
            $user->comments = $this->getParam('comments');

            $user->save();

            if ($this->getParam('enableApi')) {
                $keys = Scalr::GenerateAPIKeys();
                $user->setSetting(Scalr_Account_User::SETTING_API_ENABLED, true);
                $user->setSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY, $keys['id']);
                $user->setSetting(Scalr_Account_User::SETTING_API_SECRET_KEY, $keys['key']);
            }

            if ($newUser) {
                if ($sendResetLink) {
                    try {
                        $hash = $this->getCrypto()->sault(10);

                        $user->setSetting(Scalr_Account::SETTING_OWNER_PWD_RESET_HASH, $hash);

                        $clientinfo = array(
                            'email'    => $user->getEmail(),
                            'fullname' => $user->fullname
                        );

                        // Send reset password E-mail
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
            }

            $this->response->data(array('user' => array(
                'id'       => $user->getId(),
                'email'    => $user->getEmail(),
                'fullname' => $user->fullname
            )));

            $this->response->success('User successfully saved');
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    public function xRemoveAction()
    {
        $user = Scalr_Account_User::init();
        $user->loadById($this->getParam('userId'));

        if ($user->getAccountId() == $this->user->getAccountId()) {
            if ($this->user->canManageAcl() || $this->user->isTeamOwner()) {
                $user->delete();
                $this->response->success('User has been successfully removed.');
                return;
            }
        }

        throw new Scalr_Exception_InsufficientPermissions();
    }
}
