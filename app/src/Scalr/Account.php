<?php

use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Model\Collections\ArrayCollection;

class Scalr_Account extends Scalr_Model
{
    protected $dbTableName = 'clients';
    protected $dbPrimaryKey = "id";
    protected $dbMessageKeyNotFound = "Account #%s not found in database";

    const STATUS_ACTIVE = 'Active';
    const STATUS_INACIVE = 'Inactive';
    const STATUS_SUSPENDED = 'Suspended';

    const SETTING_SUSPEND_REASON = 'system.suspend.reason';
    const SETTING_OWNER_PWD_RESET_HASH = 'system.owner_pwd.hash';
    const SETTING_AUTH_HASH = 'system.auth.hash';

    const SETTING_DATE_FIRST_LOGIN = 'date.first_login';
    const SETTING_DATE_ENV_CONFIGURED = 'date.env_configured';
    const SETTING_DATE_FARM_CREATED = 'date.farm_created';

    const SETTING_TRIAL_MAIL_SENT = 'mail.trial_sent';
    const SETTING_IS_TRIAL          = 'billing.is_trial';
    const SETTING_UI_VARS = 'ui.vars';

    //MOVE TO Scalr_Billing
    const SETTING_BILLING_PAY_AS_YOU_GO_DATE = 'billing.pay-as-you-go-date';

    const SETTING_BILLING_ALERT_OLD_PKG = 'alerts.billing.old_package';
    const SETTING_BILLING_ALERT_PAYPAL = 'alerts.billing.paypal';
    const SETTING_BILLING_EMERG_SUPPORT_PENDING = 'alerts.emerg.support.pending';
    const SETTING_BILLING_EMERG_SUPPORT_DATE = 'alerts.emerg.support.date';

    const SETTING_CLOUDYN_ENABLED = 'cloudyn.enabled';
    const SETTING_CLOUDYN_USER_EMAIL   = 'cloudyn.user.email';
    const SETTING_CLOUDYN_USER_PASSWD   = 'cloudyn.user.passwd';
    const SETTING_CLOUDYN_MASTER_EMAIL   = 'cloudyn.master.email';
    const SETTING_CLOUDYN_MASTER_PASSWD   = 'cloudyn.master.passwd';

    protected $dbPropertyMap = array(
        'id'            => 'id',
        'name'          => 'name',
        'status'        => 'status',
        'comments'      => 'comments',
        'dtadded'       => array('property' => 'dtAdded', 'update' => false, 'type' => 'datetime', 'createSql' => 'NOW()'),
        'priority'      => 'priority'
    );

    public $name;
    public $dtAdded;
    public $status;
    public $comments;
    public $priority = 0;

    /**
     * @return Scalr_Account
     */
    public function loadById($id)
    {
        return parent::loadById($id);
    }

    /**
     * @return Scalr_Account_User
     */
    public function getOwner()
    {
        $userId = $this->db->GetOne("SELECT id FROM account_users WHERE `account_id` = ? AND `type` = ? LIMIT 1", array($this->id, Scalr_Account_User::TYPE_ACCOUNT_OWNER));
        return Scalr_Account_User::init()->loadById($userId);
    }

    /**
     *
     * @return Scalr_Account
     */
    public function loadBySetting($name, $value)
    {
        $id = $this->db->GetOne("SELECT clientid FROM client_settings WHERE `key` = ? AND `value` = ? LIMIT 1",
            array($name, $value)
        );
        if (!$id)
            return false;
        else
            return $this->loadById($id);
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::delete()
     */
    public function delete($id = null)
    {
        $servers = \DBServer::listByFilter(['clientId' => $this->id]);

        foreach ($servers as $server) {
            /* @var $server \DBServer */
            $server->Remove();
        }

        try {
            $this->db->StartTrans();

            //TODO: Use models
            $this->db->Execute("
                DELETE account_team_users FROM account_team_users, account_teams
                WHERE account_teams.account_id = ?
                AND account_team_users.team_id = account_teams.id
            ", array($this->id));

            $this->db->Execute("DELETE FROM account_users WHERE account_id=?", array($this->id));
            $this->db->Execute("DELETE FROM account_teams WHERE account_id=?", array($this->id));

            $this->db->Execute("DELETE FROM account_limits WHERE account_id=?", array($this->id));
            $this->db->Execute("DELETE FROM client_environments WHERE client_id=?", array($this->id));

            $this->db->Execute("
                DELETE account_team_user_acls FROM account_team_user_acls, acl_account_roles
                WHERE acl_account_roles.account_id = ?
                AND account_team_user_acls.account_role_id = acl_account_roles.account_role_id
            ", array($this->id));

            $this->db->Execute("DELETE FROM acl_account_roles WHERE account_id=?", array($this->id));

            $this->db->Execute("DELETE FROM ec2_ebs WHERE client_id=?", array($this->id));
            $this->db->Execute("DELETE FROM apache_vhosts WHERE client_id=?", array($this->id));
            $this->db->Execute("DELETE FROM scheduler WHERE account_id=?", array($this->id));

            foreach ($this->db->Execute("SELECT id FROM farms WHERE clientid=?", [$this->id]) as $farm) {
                $this->db->Execute("DELETE FROM farms WHERE id=?", array($farm["id"]));
                $this->db->Execute("DELETE FROM farm_roles WHERE farmid=?", array($farm["id"]));
                $this->db->Execute("DELETE FROM farm_event_observers WHERE farmid=?", array($farm["id"]));
                $this->db->Execute("DELETE FROM elastic_ips WHERE farmid=?", array($farm["id"]));
            }

            $roles = $this->db->GetAll("SELECT id FROM roles WHERE client_id = '{$this->id}'");
            foreach ($roles as $role) {
                $this->db->Execute("DELETE FROM roles WHERE id = ?", array($role['id']));

                $this->db->Execute("DELETE FROM role_behaviors WHERE role_id = ?", array($role['id']));
                $this->db->Execute("DELETE FROM role_images WHERE role_id = ?", array($role['id']));
                $this->db->Execute("DELETE FROM role_properties WHERE role_id = ?", array($role['id']));
                $this->db->Execute("DELETE FROM role_security_rules WHERE role_id = ?", array($role['id']));
            }

            //Removing cost centres and projects which are set up from this account
            $this->db->Execute("
                DELETE project_properties FROM project_properties, projects
                WHERE projects.project_id = project_properties.project_id
                AND projects.account_id = ?
            ", [$this->id]);
            $this->db->Execute("DELETE FROM projects WHERE account_id = ?", [$this->id]);
            $this->db->Execute("
                DELETE cc_properties FROM cc_properties, ccs
                WHERE ccs.cc_id = cc_properties.cc_id
                AND ccs.account_id = ?
            ", [$this->id]);
            $this->db->Execute("DELETE FROM ccs WHERE account_id = ?", [$this->id]);

            parent::delete();

            $this->db->CompleteTrans();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }

    /**
     * Initializes ACL Roles for account
     *
     * @returns array Returns Account Roles
     * @throws  \Exception
     */
    public function initializeAcl()
    {
        $ret = array();
        if (!$this->id) {
            throw new \Exception("Account is not created.");
        }
        $acl = $this->getContainer()->acl;

        $ret['account_role'][$acl::ROLE_ID_FULL_ACCESS] = $acl->getFullAccessAccountRole($this->id, true);
        $ret['account_role'][$acl::ROLE_ID_EVERYTHING_FORBIDDEN] = $acl->getNoAccessAccountRole($this->id, true);

        return $ret;
    }

    /**
     *
     * @param integer $groupId
     * @param string $login
     * @param string $password
     * @param string $email
     * @return Scalr_Account_User
     */
    public function createUser($email, $password, $type)
    {
        if (!$this->id)
            throw new Exception("Account is not created");

        $this->validateLimit(Scalr_Limits::ACCOUNT_USERS, 1);

        $user = Scalr_Account_User::init()->create($email, $this->id);
        $user->updatePassword($password);
        $user->type = $type;
        $user->status = Scalr_Account_User::STATUS_ACTIVE;

        $user->save();

        $keys = Scalr::GenerateAPIKeys();

        $user->setSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY, $keys['id']);
        $user->setSetting(Scalr_Account_User::SETTING_API_SECRET_KEY, $keys['key']);

        return $user;
    }

    /**
     * Creates a new environment assosiated with the account
     *
     * @param string $name
     * @throws Scalr_Exception_LimitExceeded
     * @return Scalr_Environment
     */
    public function createEnvironment($name)
    {
        $config = [];

        if (!$this->id) {
            throw new Exception("Account has not been created yet.");
        }

        $this->validateLimit(Scalr_Limits::ACCOUNT_ENVIRONMENTS, 1);

        $env = Scalr_Environment::init()->create($name, $this->id);

        $config[Scalr_Environment::SETTING_TIMEZONE] = "America/Adak";
        //$config[Scalr_Environment::SETTING_API_LIMIT_ENABLED] = 1;
        //$config[Scalr_Environment::SETTING_API_LIMIT_REQPERHOUR] = 18000;

        $env->setPlatformConfig($config, false);

        return $env;
    }

    /**
     * Returns client setting value by name
     *
     * @param string $name
     * @return mixed $value
     */
    public function getSetting($name)
    {
        return $this->db->GetOne("SELECT value FROM client_settings WHERE clientid=? AND `key`=? LIMIT 1",
            array($this->id, $name)
        );
    }

    /**
     * Set client setting
     * @param string $name
     * @param mixed $value
     * @return Scalr_Account
     */
    public function setSetting($name, $value)
    {
        //UNIQUE KEY `NewIndex1` (`clientid`,`key`),
        $this->db->Execute("
            INSERT client_settings
            SET clientid=?,
                `key`=?,
                `value`=?
            ON DUPLICATE KEY UPDATE
                `value`=?
        ", array(
            $this->id, $name, $value, $value
        ));

        return $this;
    }

    public function clearSettings ($filter)
    {
        $this->db->Execute(
            "DELETE FROM client_settings WHERE `key` LIKE '{$filter}' AND clientid = ?",
            array($this->id)
        );
    }

    public function setLimit($limitName, $limitValue) {
        $limit = Scalr_Limits::init()->Load($limitName, $this->id);
        $limit->setLimitValue($limitValue);
        $limit->save();
    }

    public function setLimits(array $limits) {
        foreach ($limits as $k=>$v)
            $this->setLimit($k, $v);
    }

    /**
     *
     * @param string $limitName
     * @param string $limitValue
     * @throws Scalr_Exception_LimitExceeded
     */
    public function validateLimit($limitName, $limitValue) {
        if (!$this->checkLimit($limitName, $limitValue))
            throw new Scalr_Exception_LimitExceeded($limitName);
    }

    /**
     *
     * @param string $limitName
     * @param integer $limitValue
     * @return boolean
     */
    public function checkLimit($limitName, $limitValue) {
        return Scalr_Limits::init()->Load($limitName, $this->id)->check($limitValue);
    }

    /*
     * @return boolean
     */
    public function resetLimits()
    {
        foreach ($this->getLimits() as $limitName => $limit) {
            $this->setLimit($limitName, -1);
        }
    }

    /**
     * @return array $limits
     */
    public function getLimits()
    {
        $l = array(Scalr_Limits::ACCOUNT_ENVIRONMENTS, Scalr_Limits::ACCOUNT_FARMS, Scalr_Limits::ACCOUNT_SERVERS, Scalr_Limits::ACCOUNT_USERS);
        $limits = array();
        foreach ($l as $limitName) {
            $limit = Scalr_Limits::init()->Load($limitName, $this->id);
            $limits[$limitName] = array(
                'limit' => $limit->getLimitValue(),
                'usage' => $limit->getCurrentUsage()
            );
        }

        return $limits;
    }

    public function getUsers()
    {
        $users = $this->db->GetAll('SELECT account_users.id, email, fullname, type, account_team_users.permissions FROM account_users
            LEFT JOIN account_team_users ON account_users.id = account_team_users.user_id WHERE account_id = ?', array($this->id));

        foreach ($users as &$user) {
            if ($user['permissions'] == 'owner')
                $user['type'] = 'TeamOwner';
        }

        return $users;
    }

    /**
     * Init
     * @return Scalr_Account
     */
    public static function init($className = null)
    {
        return parent::init();
    }

    /**
     * Gets the list of the Cost Centers which correspond to Account
     *
     * @return  \Scalr\Model\Collections\ArrayCollection  Returns collection of the entities
     */
    public function getCostCenters()
    {
        $ccs = new ArrayCollection;
        foreach (AccountCostCenterEntity::findByAccountId($this->id) as $accountCc) {
            $cc = CostCentreEntity::findPk($accountCc->ccId);
            if (!($cc instanceof CostCentreEntity)) {
                continue;
            }
            $ccs->append($cc);
        }
        return $ccs;
    }
}
