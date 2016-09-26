<?php
use Scalr\UI\Request\RawData;
use Scalr\UI\Request\Validator;
use Scalr\Model\Entity\Account\User;

class Scalr_UI_Controller_Admin_Users extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'userId';

    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/admin/users/view.js');
    }

    public function xListUsersAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json')
        ));

        $sql = 'SELECT id, status, email, type, fullname, dtcreated, dtlastlogin, comments FROM account_users WHERE (type = ? OR type = ?) AND :FILTER:';
        $response = $this->buildResponseFromSql2($sql, array('id', 'status', 'email', 'type', 'fullname', 'dtcreated', 'dtlastlogin'), array('email', 'fullname'), array(Scalr_Account_User::TYPE_SCALR_ADMIN, Scalr_Account_User::TYPE_FIN_ADMIN));
        foreach ($response["data"] as &$row) {
            $user = Scalr_Account_User::init();
            $user->loadById($row['id']);

            $row['dtcreated'] = Scalr_Util_DateTime::convertTz($row["dtcreated"]);
            $row['dtlastlogin'] = $row['dtlastlogin'] ? Scalr_Util_DateTime::convertTz($row["dtlastlogin"]) : 'Never';
            $row['is2FaEnabled'] = $user->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == '1' ? true : false;
        }
        $this->response->data($response);
    }

    public function createAction()
    {
        $this->response->page('ui/admin/users/create.js');
    }

    public function editAction()
    {
        $user = Scalr_Account_User::init();
        $user->loadById($this->getParam('userId'));

        if ($user->getEmail() == 'admin' && $user->getId() != $this->user->getId())
            throw new Scalr_Exception_InsufficientPermissions();

        $this->response->page('ui/admin/users/create.js', array(
            'user' => array(
                'id'        => $user->getId(),
                'email'     => $user->getEmail(),
                'type'      => $user->getType(),
                'fullname'  => $user->fullname,
                'status'    => $user->status,
                'comments'  => $user->comments,
                'password'  => true,
                'cpassword' => true
            )
        ));
    }

    /**
     * @param int     $id
     * @param string  $email
     * @param string  $type
     * @param RawData $password
     * @param string  $status
     * @param string  $fullname
     * @param string  $comments
     * @param RawData $currentPassword optional
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xSaveAction($id = 0, $email, $type, RawData $password, $status, $fullname, $comments, RawData $currentPassword = null)
    {
        $user = Scalr_Account_User::init();
        $validator = new Validator();
        $isNewUser = empty($id);
        $isExistingPasswordChanged = false;
        $password = (string) $password;

        if (!$isNewUser && $password && !$this->user->checkPassword($currentPassword, false)) {
            $this->response->data(['errors' => ['currentPassword' => 'Invalid password']]);
            $this->response->failure();
            return;
        }

        if ($password || $isNewUser) {
            $validator->validate($password, 'password', Validator::PASSWORD, ['admin']);
        }

        $validator->validate($email, 'email', Validator::NOEMPTY);
        if ($type == User::TYPE_FIN_ADMIN) {
            $validator->validate($email, 'email', Validator::EMAIL);
        }

        if ($isNewUser) {
            $validator->addErrorIf($this->db->GetOne("SELECT EXISTS(SELECT 1 FROM `account_users` WHERE email = ?)", [$email]), 'email', 'This email is already in use.');
        }

        $validator->addErrorIf(!in_array($type, [User::TYPE_SCALR_ADMIN, User::TYPE_FIN_ADMIN]), 'type', 'Type is not valid');
        $validator->addErrorIf(!in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE]), 'type', 'Status is not valid');

        if (!$validator->isValid($this->response)) {
            return;
        }

        if (!$isNewUser) {
            $user->loadById($id);

            if ($user->getEmail() == 'admin' && $user->getId() != $this->user->getId())
                throw new Scalr_Exception_InsufficientPermissions();

            if ($user->getEmail() != 'admin')
                $user->updateEmail($email);
        } else {
            $user->create($email, $this->user->getAccountId());
            $user->type = $type;
        }

        if ($password) {
            $user->updatePassword($password);
            if (!$isNewUser) {
                $isExistingPasswordChanged = true;
            }
        }

        if ($user->getEmail() != 'admin') {
            $user->status = $status;
            $user->type = $type;

            $user->fullname = $fullname;
            $user->comments = $comments;
        }

        $user->save();
        // Send notification E-mail
        if ($isExistingPasswordChanged) {
            $this->getContainer()->mailer->sendTemplate(
                SCALR_TEMPLATES_PATH . '/emails/password_change_admin_notification.eml',
                array(
                    '{{fullname}}' => $user->fullname ? $user->fullname : $user->getEmail(),
                    '{{administratorFullName}}' => $this->user->fullname ? $this->user->fullname : $this->user->getEmail()
                ),
                $user->getEmail(), $user->fullname
            );
        } else if ($isNewUser) {
            $this->getContainer()->mailer->sendTemplate(
                SCALR_TEMPLATES_PATH . '/emails/user_new_admin_notification.eml',
                array(
                    '{{fullname}}' => $user->fullname ? $user->fullname : $user->getEmail(),
                    '{{subject}}' => $user->type == Scalr_Account_User::TYPE_FIN_ADMIN ? 'Financial Admin for Scalr Cost Analytics' : 'Admin for Scalr',
                    '{{user_type}}' => $user->type == Scalr_Account_User::TYPE_FIN_ADMIN ? 'a Financial Admin' : 'an Admin',
                    '{{link}}' => Scalr::config('scalr.endpoint.scheme') . "://" . Scalr::config('scalr.endpoint.host')
                ),
                $user->getEmail(), $user->fullname
            );
        }

        $this->response->success('User successfully saved');
    }

    public function xRemoveAction()
    {
        $user = Scalr_Account_User::init();
        $user->loadById($this->getParam('userId'));

        if ($user->getEmail() == 'admin')
            throw new Scalr_Exception_InsufficientPermissions();

        $user->delete();
        $this->response->success('User successfully removed');
    }
}
