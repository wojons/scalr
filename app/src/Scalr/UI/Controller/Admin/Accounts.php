<?php
use Scalr\UI\Request\Validator;
use Scalr\UI\Request\RawData;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Model\Entity\Account\User;


class Scalr_UI_Controller_Admin_Accounts extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'accountId';

    public function hasAccess()
    {
        return $this->user->isScalrAdmin();
    }

    public static function getApiDefinitions()
    {
        return array('xRemove', 'xSave', 'xListAccounts', 'xGetInfo');
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/admin/accounts/view.js');
    }

    /**
     * @param   int     $accountId
     * @throws  Exception
     */
    public function xUnlockOwnerAction($accountId)
    {
        $account = Scalr_Account::init()->loadById($accountId);
        $owner = $account->getOwner();
        if ($owner->status == User::STATUS_INACTIVE) {
            $owner->status = User::STATUS_ACTIVE;
            $owner->save();
            $this->response->success('Account owner was unlocked');
        } else {
            throw new Exception('Account owner is not suspended');
        }
    }

    public function xListAccountsAction()
    {
        $this->request->defineParams(array(
            'sort' => array('type' => 'json'),
            'accountId' => array('type' => 'int')
        ));

        $sql = "SELECT id, name, dtadded, status FROM clients WHERE :FILTER:";
        $args = array();

        if ($this->getParam('serverId')) {
            $sql .= " AND `id` IN (SELECT `client_id` FROM `servers_history` WHERE `server_id` = ?)";
            $args[] = $this->getParam('serverId');
        }

        if ($this->getParam('farmId')) {
            $sql .= ' AND id IN (SELECT clientid FROM farms WHERE id = ?)';
            $args[] = $this->getParam('farmId');
        }

        if ($this->getParam('owner')) {
            $sql .= ' AND id IN (SELECT account_id FROM account_users WHERE `type` = ? AND email LIKE ?)';
            $args[] = Scalr_Account_User::TYPE_ACCOUNT_OWNER;
            $args[] = '%' . $this->getParam('owner') . '%';
        }

        if ($this->getParam('user')) {
            $sql .= ' AND id IN (SELECT account_id FROM account_users WHERE email LIKE ?)';
            $args[] = '%' . $this->getParam('user') . '%';
        }

        if ($this->getParam('envId')) {
            $sql .= ' AND id IN (SELECT client_id FROM client_environments WHERE id = ?)';
            $args[] = $this->getParam('envId');
        }

        $response = $this->buildResponseFromSql2($sql, array('id', 'name', 'dtadded', 'status'), array('id', 'name'), $args);
        foreach ($response['data'] as &$row) {
            $account = Scalr_Account::init()->loadById($row['id']);

            try {
                $owner = $account->getOwner();
                $row['ownerEmail'] = $owner->getEmail();
                $row['ownerLocked'] = $owner->status == User::STATUS_INACTIVE;
            } catch (Exception $e){
                $row['ownerEmail'] = '*No owner*';
            }
            $row['dtadded'] = Scalr_Util_DateTime::convertTz($row['dtadded']);

            $row['isTrial'] = (int)$account->getSetting(Scalr_Account::SETTING_IS_TRIAL);

            $limit = Scalr_Limits::init()->Load(Scalr_Limits::ACCOUNT_ENVIRONMENTS, $row['id']);
            $row['envs'] = $limit->getCurrentUsage();
            $row['limitEnvs'] = $limit->getLimitValue() > -1 ? $limit->getLimitValue() : '-';

            $limit = Scalr_Limits::init()->Load(Scalr_Limits::ACCOUNT_FARMS, $row['id']);
            $row['farms'] = $limit->getCurrentUsage();
            $row['limitFarms'] = $limit->getLimitValue() > -1 ? $limit->getLimitValue() : '-';

            $limit = Scalr_Limits::init()->Load(Scalr_Limits::ACCOUNT_USERS, $row['id']);
            $row['users'] = $limit->getCurrentUsage();
            $row['limitUsers'] = $limit->getLimitValue() > -1 ? $limit->getLimitValue() : '-';

            $limit = Scalr_Limits::init()->Load(Scalr_Limits::ACCOUNT_SERVERS, $row['id']);
            $row['servers'] = $limit->getCurrentUsage();
            $row['limitServers'] = $limit->getLimitValue() > -1 ? $limit->getLimitValue() : '-';

            $row['dnsZones'] = $this->db->GetOne("SELECT COUNT(*) FROM dns_zones WHERE client_id = ?", array($row['id']));
        }

        $this->response->data($response);
    }

    public function xRemoveAction()
    {
        $this->request->defineParams(array(
            'accounts' => array('type' => 'json')
        ));

        foreach ($this->getParam('accounts') as $dd) {
            Scalr_Account::init()->loadById($dd)->delete();
        }

        $this->response->success("Selected account(s) successfully removed");
    }

    public function createAction()
    {
        $this->response->page('ui/admin/accounts/edit.js', array(
            'account' => array(
                'id' => 0,
                'name' => '',
                'comments' => '',

                'limitEnv' => -1,
                'limitFarms' => -1,
                'limitServers' => -1,
                'limitUsers' => -1,

                'featureApi' => '1',
                'featureScripting' => '1',
                'featureCsm' => '1'
            ),
            'ccs' => $this->getCostCenters()
        ));
    }

    public function getAccount()
    {
        $account = Scalr_Account::init()->loadById($this->getParam(self::CALL_PARAM_NAME));
        $result = array(
            'id' => $account->id,
            'name' => $account->name,
            'comments' => $account->comments,

            'limitEnv' => Scalr_Limits::init()->Load(Scalr_Limits::ACCOUNT_ENVIRONMENTS, $account->id)->getLimitValue(),
            'limitFarms' => Scalr_Limits::init()->Load(Scalr_Limits::ACCOUNT_FARMS, $account->id)->getLimitValue(),
            'limitServers' => Scalr_Limits::init()->Load(Scalr_Limits::ACCOUNT_SERVERS, $account->id)->getLimitValue(),
            'limitUsers' => Scalr_Limits::init()->Load(Scalr_Limits::ACCOUNT_USERS, $account->id)->getLimitValue()
        );

        if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap')
            $result['ownerEmail'] = $account->getOwner()->getEmail();

        $result['ccs'] = [];
        if ($this->getContainer()->analytics->enabled) {
            foreach (AccountCostCenterEntity::findByAccountId($account->id) as $accountCcsEntity) {
                $result['ccs'][] = $accountCcsEntity->ccId;
            }
        }
        return $result;
    }

    public function editAction()
    {
        $account = $this->getAccount();
        $this->response->page('ui/admin/accounts/edit.js', array(
            'account' => $account,
            'ccs'     => $this->getCostCenters($account['ccs']),

        ));
    }

    public function xGetInfoAction()
    {
        $this->response->data(array('account' => $this->getAccount()));
    }

    public function xSaveAction()
    {
        $this->request->defineParams(array(
            'id' => array('type' => 'int'),
            'name' => array('type' => 'string'),
            'ownerEmail' => array('type' => 'string'),
            'ownerPassword' => array('type' => 'string', 'rawValue' => true),
            'comments' => array('type' => 'string'),
            'ccs' => array('type' => 'json'),
        ));

        $account = Scalr_Account::init();
        $validator = new Validator;

        $id            = (int) $this->getParam('id');
        $name          = $this->getParam('name');
        $ownerEmail    = $this->getParam('ownerEmail');
        $ownerPassword = $this->getParam('ownerPassword');

        $validator->validate($name, "name", Validator::NOEMPTY, [], "Name is required");
        $validator->validate($id, "id", Validator::INTEGERNUM);

        if ($id) {
            $account->loadById($id);
        } else {
            $account->status = Scalr_Account::STATUS_ACTIVE;

            if ($this->getContainer()->config->get('scalr.auth_mode') == 'scalr') {
                $validator->validate($ownerEmail, "ownerEmail", Validator::EMAIL);
                $validator->validate($ownerPassword, "ownerPassword", Validator::PASSWORD, ["admin"]);
            } elseif ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap') {
                $validator->validate($ownerEmail, "ownerEmail", Validator::NOEMPTY, [], "Email is required");
            }
        }

        if (!$validator->isValid($this->response)) {
            return;
        }

        $this->db->BeginTrans();
        try {
            $account->name = $name;
            $account->comments = $this->getParam('comments');

            $account->save();

            $account->initializeAcl();

            $account->setLimits(array(
                Scalr_Limits::ACCOUNT_ENVIRONMENTS => $this->getParam('limitEnv'),
                Scalr_Limits::ACCOUNT_FARMS => $this->getParam('limitFarms'),
                Scalr_Limits::ACCOUNT_SERVERS => $this->getParam('limitServers'),
                Scalr_Limits::ACCOUNT_USERS => $this->getParam('limitUsers')
            ));

            if (!$id) {
                $user = $account->createUser($ownerEmail, $ownerPassword, Scalr_Account_User::TYPE_ACCOUNT_OWNER);

                if ($this->getContainer()->analytics->enabled) {
                    
                        //Default Cost Center should be assigned
                        $cc = $this->getContainer()->analytics->ccs->get($this->getContainer()->analytics->usage->autoCostCentre());
                    

                    //Assigns account with Cost Center
                    $accountCcEntity = new AccountCostCenterEntity($account->id, $cc->ccId);
                    $accountCcEntity->save();
                }

                $account->createEnvironment("default");
            }

            if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap' && $id) {
                if ($ownerEmail != $account->getOwner()->getEmail()) {
                    $prev = $account->getOwner();
                    $prev->type = Scalr_Account_User::TYPE_TEAM_USER;
                    $prev->save();

                    $user = new Scalr_Account_User();
                    if ($user->loadByEmail($ownerEmail, $account->id)) {
                        $user->type = Scalr_Account_User::TYPE_ACCOUNT_OWNER;
                        $user->save();
                    } else {
                        $account->createUser($ownerEmail, $ownerPassword, Scalr_Account_User::TYPE_ACCOUNT_OWNER);
                    }
                }
            }

            if ($this->getContainer()->analytics->enabled) {
                if (!Scalr::isHostedScalr()) {
                    //save ccs
                    $ccs = (array)$this->getParam('ccs');
                    foreach(AccountCostCenterEntity::findByAccountId($account->id) as $accountCcsEntity) {
                        $index = array_search($accountCcsEntity->ccId, $ccs);
                        if ($index === false) {
                            $accountCcsEntity->delete();
                        } else {
                            unset($ccs[$index]);
                        }
                    }
                    foreach ($ccs as $ccId) {
                        $accountCcsEntity = new AccountCostCenterEntity($account->id, $ccId);
                        $accountCcsEntity->save();
                    }
                }
            }

        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }

        $this->db->CommitTrans();
        $this->response->data(array('accountId' => $account->id));
    }

    public function xGetUsersAction()
    {
        $account = new Scalr_Account();
        $account->loadById($this->getParam('accountId'));

        $this->response->data(array(
            'users' => $account->getUsers()
        ));
    }

    public function xLoginAsAction()
    {
        if ($this->getParam('accountId')) {
            $account = new Scalr_Account();
            $account->loadById($this->getParam('accountId'));
            $user = $account->getOwner();
        } else {
            $user = new Scalr_Account_User();
            $user->loadById($this->getParam('userId'));
        }
        if ($user->status != User::STATUS_ACTIVE) {
            throw new Exception('User account has been deactivated. You cannot login into it.');
        }

        Scalr_Session::create($user->getId(), $this->user->getId());

        try {
            $envId = $this->getEnvironmentId(true) ?: $user->getDefaultEnvironment()->id;
        } catch (Exception $e) {
            $envId = null;
        }

        $this->getContainer()->auditlogger->setEnvironmentId($envId)->setRuid($this->user->getId());

        $this->auditLog("user.auth.login", $user);

        $this->response->success();
    }

    /**
     * @param int $accountId
     * @throws Exception
     */
    public function changeOwnerPasswordAction($accountId)
    {
        $account = new Scalr_Account();
        $account->loadById($accountId);
        $this->response->page('ui/admin/accounts/changeOwnerPassword.js', array(
            'accountName' => $account->name,
            'email'       => $account->getOwner()->getEmail()
        ));
    }

    /**
     * @param  int     $accountId
     * @param  RawData $password
     * @param  RawData $currentPassword
     * @throws Exception
     */
    public function xSaveOwnerPasswordAction($accountId, RawData $password, RawData $currentPassword)
    {
        $account = new Scalr_Account();
        $account->loadById($accountId);
        $password = (string) $password;

        $validator = new Validator();
        $validator->addErrorIf(!$this->user->checkPassword($currentPassword), "currentPassword", "Invalid password");
        $validator->validate($password, "password", Validator::PASSWORD, ['admin']);

        if ($validator->isValid($this->response)) {
            $user = $account->getOwner();
            $user->updatePassword($password);
            $user->save();
            // Send notification E-mail
            $this->getContainer()->mailer->sendTemplate(
                SCALR_TEMPLATES_PATH . '/emails/password_change_admin_notification.eml',
                array(
                    '{{fullname}}' => $user->fullname ? $user->fullname : $user->getEmail(),
                    '{{administratorFullName}}' => $this->user->fullname ? $this->user->fullname : $this->user->getEmail()
                ),
                $user->getEmail(), $user->fullname
            );

            $this->response->success('Password successfully updated');
        }
    }

    private function getCostCenters($requiredCcIds = [])
    {
        $ccs = [];
        if ($this->getContainer()->analytics->enabled) {
            foreach ($this->getContainer()->analytics->ccs->all(true) as $ccEntity) {
                /* @var $ccEntity \Scalr\Stats\CostAnalytics\Entity\CostCentreEntity */
                $isRequiredCcId = in_array($ccEntity->ccId, $requiredCcIds);
                if (!$isRequiredCcId && ($ccEntity->archived || Scalr::isHostedScalr())) {
                    continue;
                }
                $cc = get_object_vars($ccEntity);

                $cc['envs'] = \Scalr::getDb()->GetAll("
                    SELECT e.id, e.name FROM client_environments e
                    JOIN client_environment_properties ep ON ep.env_id = e.id
                    WHERE ep.name = ? AND ep.value = ?
                ", [
                    \Scalr_Environment::SETTING_CC_ID,
                    strtolower($ccEntity->ccId)
                ]);
                $ccs[] = $cc;
            }
        }
        return $ccs;
    }

}
