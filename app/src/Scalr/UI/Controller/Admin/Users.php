<?php
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
        $response = $this->buildResponseFromSql2($sql, array('id', 'status', 'email', 'fullname', 'dtcreated', 'dtlastlogin'), array('email', 'fullname'), array(Scalr_Account_User::TYPE_SCALR_ADMIN, Scalr_Account_User::TYPE_FIN_ADMIN));
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
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'type' => $user->getType(),
                'fullname' => $user->fullname,
                'status' => $user->status,
                'comments' => $user->comments
            )
        ));
    }

    /**
     * @param int $id
     * @param $email
     * @param $type
     * @param $password
     * @param $status
     * @param $fullname
     * @param $comments
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xSaveAction($id = 0, $email, $type, $password, $status, $fullname, $comments)
    {
        $user = Scalr_Account_User::init();
        $validator = new Scalr_Validator();

        if (! $email)
            throw new Scalr_Exception_Core('Email cannot be empty');

        if ($type == Scalr_Account_User::TYPE_FIN_ADMIN && $validator->validateEmail($email, null, true) !== true)
            throw new Scalr_Exception_Core('Email is not valid');

        if (! in_array($type, [Scalr_Account_User::TYPE_SCALR_ADMIN, Scalr_Account_User::TYPE_FIN_ADMIN]))
            throw new Scalr_Exception_Core('Type is not valid');

        if (! in_array($status, [Scalr_Account_User::STATUS_ACTIVE, Scalr_Account_User::STATUS_INACTIVE]))
            throw new Scalr_Exception_Core('Status is not valid');

        if ($id) {
            $user->loadById($id);

            if ($user->getEmail() == 'admin' && $user->getId() != $this->user->getId())
                throw new Scalr_Exception_InsufficientPermissions();

            if ($user->getEmail() != 'admin')
                $user->updateEmail($email);
        } else {
            $user->create($email, $this->user->getAccountId());
            $user->type = $type;
        }

        if ($password != '******')
            $user->updatePassword($password);

        if ($user->getEmail() != 'admin') {
            $user->status = $status;
            $user->type = $type;

            $user->fullname = $fullname;
            $user->comments = $comments;
        }

        $user->save();
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
