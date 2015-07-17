<?php

namespace Scalr\Model\Entity\Account;

use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Account\User\UserSetting;
use Scalr_Account_Team;

/**
 * User entity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4.0 (21.02.2015)
 *
 * @Entity
 * @Table(name="account_users")
 */
class User extends AbstractEntity
{
    const STATUS_ACTIVE = 'Active';
    const STATUS_INACTIVE = 'Inactive';

    const TYPE_SCALR_ADMIN = 'ScalrAdmin';
    const TYPE_FIN_ADMIN = 'FinAdmin';
    const TYPE_ACCOUNT_OWNER = 'AccountOwner';
    const TYPE_ACCOUNT_ADMIN = 'AccountAdmin';
    const TYPE_ACCOUNT_SUPER_ADMIN = 'AccountSuperAdmin';
    const TYPE_TEAM_USER = 'TeamUser';

    /**
     * The identifier of the User
     *
     * @Id
     * @GeteratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * Identifier of the account which User corresponds to
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $accountId;

    /**
     * The status of the User
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $status;

    /**
     * The email address of the user
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $email;

    /**
     * The full name of the User
     *
     * @Column(name="fullname",type="string",nullable=true)
     * @var string
     */
    public $fullName;

    /**
     * The password
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $password;

    /**
     * The timestamp when the record is created
     *
     * @Column(name="dtcreated",type="datetime",nullable=true)
     * @var \DateTime
     */
    public $created;

    /**
     * The last timemestamp when the User signed in
     *
     * @Column(name="dtlastlogin",type="datetime",nullable=true)
     * @var \DateTime
     */
    public $lastLogin;

    /**
     * The type
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $type;

    /**
     * The comments
     *
     * @var string
     */
    public $comments;

    /**
     * The number of failed sign-in attempts
     *
     * @Column(name="loginattempts",type="integer",nullable=true)
     * @var int
     */
    public $loginAttempts;

    /**
     * The settings
     *
     * @var array
     */
    private $_settings;

    /**
     * Misc. cache
     *
     * @var array
     */
    private $_cache = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->created = new \DateTime();
    }

    /**
     * Gets Identifier of the User
     *
     * @return    int  Returns identifier of the User
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets Identifier of the Account
     *
     * @return    int  Returns identifier of the Account
     */
    public function getAccountId()
    {
        return $this->accountId;
    }

    /**
     * Gets email address of the User
     *
     * @return string Returns user email address
     */
    public function getEmail()
    {
        return $this->email;
    }
    
    /**
     * Gets LDAP username of the User
     *
     * @return string Returns LDAP username
     */
    public function getLdapUsername()
    {
        $ldapUsername = $this->getSetting(UserSetting::NAME_LDAP_USERNAME);
        if (!$ldapUsername)
            $ldapUsername = strtok($this->getEmail(), '@');
    
        return $ldapUsername;
    }

    /**
     * Gets specified setting
     *
     * @param   string      $name     The name of the setting
     * @param   bool        $useCache optional Whether it should use cache
     * @return  string|null Returns the value on success or NULL if it does not exist.
     */
    public function getSetting($name, $useCache = true)
    {
        if (!$useCache || $this->_settings === null || !array_key_exists($name, $this->_settings)) {
            if ($this->_settings === null) {
                $this->fetchSettings();
            } else {
                $this->_settings[$name] = UserSetting::getValue([$this->id, $name]);
            }
        }

        return isset($this->_settings[$name]) ? $this->_settings[$name] : null;
    }

    /**
     * Fetches all settings from the database
     *
     * @return   array  Returns all settings
     */
    public function fetchSettings()
    {
        $this->_settings = [];

        /* @var $userSetting UserSetting */
        foreach (UserSetting::findByUserId($this->id) as $userSetting) {
            $this->_settings[$userSetting->name] = $userSetting->value;
        }

        return $this->_settings;
    }

    /**
     * Sets the value of the specified setting
     *
     * @param    string   $name   The name of the setting
     * @param    string   $value  The value
     * @return   User
     */
    public function setSetting($name, $value)
    {
        $this->_settings[$name] = $value;

        return $this;
    }

    /**
     * Saves the value of the specified setting to the database
     *
     * @param    string   $name   The name of the setting
     * @param    string   $value  The value
     * @return   User
     */
    public function saveSetting($name, $value)
    {
        $setting = UserSetting::setValue([$this->id, $name], $value);

        $this->_settings[$name] = $setting === null ? null : $setting->value;

        return $this;
    }

    /**
     * Checks whether the user is allowed to manage ACL
     *
     * @return  boolean Returns true if user is allowed to manage ACL
     */
    public function canManageAcl()
    {
        return $this->getType() == self::TYPE_ACCOUNT_OWNER ||
               $this->getType() == self::TYPE_ACCOUNT_SUPER_ADMIN ||
               $this->getType() == self::TYPE_ACCOUNT_ADMIN;
    }

    /**
     * Checks if the user is account owher
     *
     * @return  boolean Returns true if user is account owner.
     */
    public function isAccountOwner()
    {
        return $this->getType() == self::TYPE_ACCOUNT_OWNER;
    }

    /**
     * Checks if the user is account admin
     *
     * @return  boolean Returns true if user is account admin
     */
    public function isAccountAdmin()
    {
        return $this->getType() == self::TYPE_ACCOUNT_ADMIN || $this->getType() == self::TYPE_ACCOUNT_SUPER_ADMIN;
    }

    /**
     * Checks if the user is account admin
     *
     * @return  boolean Returns true if user is account super admin
     */
    public function isAccountSuperAdmin()
    {
        return $this->getType() == self::TYPE_ACCOUNT_SUPER_ADMIN;
    }

    /**
     * Checks if the user is Scalr admin
     *
     * @return  boolean Returns true if user is Scalr Admin
     */
    public function isScalrAdmin()
    {
        return $this->getType() == self::TYPE_SCALR_ADMIN;
    }

    /**
     * Checks if the user is Financial admin
     *
     * @return  boolean Returns true if user is Financial Admin
     */
    public function isFinAdmin()
    {
        return $this->getType() == self::TYPE_FIN_ADMIN;
    }

    /**
     * Checks if the user is team user
     *
     * @return  boolean  Returns true if user is team user
     */
    public function isTeamUser()
    {
        return $this->getType() == self::TYPE_TEAM_USER;
    }

    /**
     * Checks if the user is account user (owner, admin, team user)
     *
     * @return  boolean
     */
    public function isUser()
    {
        return in_array($this->type, [
            self::TYPE_ACCOUNT_OWNER, self::TYPE_ACCOUNT_SUPER_ADMIN, self::TYPE_ACCOUNT_ADMIN, self::TYPE_TEAM_USER
        ]);
    }

    /**
     * Checks if the user is admin user (scalr, financial). It means user doesn't have current environment
     *
     * @return  boolean
     */
    public function isAdmin()
    {
        return in_array($this->type, [self::TYPE_SCALR_ADMIN, self::TYPE_FIN_ADMIN]);
    }

    /**
     * Checks whether the user is allowed to remove specified user
     *
     * @param   User $user The user to remove
     * @return  boolean    Returns true if the user is allowed to remove specified user
     */
    public function canRemoveUser(User $user)
    {
        return !$this->isTeamUser() &&
               $user->accountId == $this->accountId &&
               !$user->isAccountOwner() &&
               $this->getId() != $user->getId() &&
               ($this->isAccountOwner() || $this->isAccountSuperAdmin() || !$user->isAccountSuperAdmin()) ;
    }

    /**
     * Checks whether the user is allowed to edit specified user
     *
     * @param   User     $user The user to edit
     * @return  boolean  Returns true if the user is allowed to edit specified user
     */
    public function canEditUser(User $user)
    {
        return
            !$this->isTeamUser() &&
            $user->accountId == $this->accountId &&
            (
                $this->id == $user->id ||
                $this->isAccountOwner() ||
                $this->isAccountSuperAdmin() && !$user->isAccountOwner() ||
                $this->isAccountAdmin() && !$user->isAccountOwner() && !$user->isAccountSuperAdmin()
            );
    }

    /**
     * Checks whether this user has access to the specified environment
     *
     * @param    int     $envId   The identifier of the environment
     * @return   boolean Returns TRUE if the user has access to the specified environment
     */
    public function hasAccessToEnvironment($envId)
    {
        $params = [$this->accountId, $envId];
        $stmt = '';
        $where = '';

        if (!$this->canManageAcl()) {
            $stmt = "
                JOIN account_team_envs te ON te.env_id = ce.id
                JOIN account_teams at ON at.id = te.team_id
                JOIN account_team_users tu ON tu.team_id = at.id
            ";

            $where = "AND tu.user_id = ? AND at.account_id = ?";
            $params[] = $this->id;
            $params[] = $this->accountId;
        }

        $rec = $this->db()->GetOne("
            SELECT 1
            FROM client_environments ce
            $stmt
            WHERE ce.client_id = ? AND ce.id = ?
            $where
            LIMIT 1
        ", $params);

        return $rec ? true : false;
    }

    /**
     * Gets environments of the current user filtered by name
     *
     * @param string $filter optional Filter string
     * @return array
     */
    public function getEnvironments($filter = null)
    {
        $like = '';

        if (isset($filter)) {
            $like = " AND ce.name LIKE '%" . $this->db()->escape($filter) . "%'";
        }

        if ($this->canManageAcl()) {
            return $this->db()->getAll('SELECT ce.id, ce.name FROM client_environments ce WHERE ce.client_id = ?' . $like, array(
                $this->getAccountId()
            ));
        } else {
            $teams = array();
            foreach ($this->getTeams() as $team)
                $teams[] = $team['id'];

            if (count($teams)) {
                return $this->db()->getAll('
                    SELECT ce.id, ce.name FROM client_environments ce
                    JOIN account_team_envs te ON ce.id = te.env_id
                    WHERE te.team_id IN (' . implode(',', $teams) . ')'
                    . $like . '
                    GROUP BY ce.id
                ');
            }
        }

        return array();
    }

    /**
     * Gets roles by specified identifier of the Environment
     *
     * @param   int   $envId       The identifier of the Environment
     * @param   bool  $ingoreCache optional Whether it should ignore cache
     * @return  \Scalr\Acl\Role\AccountRoleSuperposition Returns the list of the roles of account level by specified environment
     */
    public function getAclRolesByEnvironment($envId, $ignoreCache = false)
    {
        $cid = 'roles.env';

        if (!isset($this->_cache[$cid][$envId]) || $ignoreCache) {
            $this->_cache[$cid][$envId] = \Scalr::getContainer()->acl->getUserRolesByEnvironment($this->id, $envId, $this->accountId);
        }

        return $this->_cache[$cid][$envId];
    }

    /**
     * Gets account level roles
     *
     * @param    bool   $ignoreCache  Whether it shoud ignore cache
     * @return   \Scalr\Acl\Role\AccountRoleSuperposition Returns the list of the roles of account level
     */
    public function getAclRoles($ignoreCache = false)
    {
        if (!isset($this->_cache['roles.account'])) {
            $this->_cache['roles.account'] = \Scalr::getContainer()->acl->getUserRoles($this->id);
        }

        return $this->_cache['roles.account'];
    }

    /**
     * Special method for LDAP auth
     * sync LDAP groups to Scalr groups
     *
     * @param $groups
     */
    public function applyLdapGroups($groups)
    {
        // get current teams
        $currentTeamIds = array();
        foreach ($this->getTeams() as $t) {
            $currentTeamIds[$t['id']] = $t['name'];
        }

        if (count($groups)) {
            // create all links between LDAP user and teams ( == LDAP group)
            $inputVars = $groups;
            $inputVars[] = $this->getAccountId();

            $teams = $this->db()->GetCol("
                SELECT id
                FROM account_teams
                WHERE name IN(" . join(',', array_fill(0, count($groups), '?')) . ")
                    AND account_id = ?",
                $inputVars
            );

            // team exists in DB, so we can save link
            foreach ($teams as $id) {
                $team = new Scalr_Account_Team();
                $team->loadById($id);

                if (!$team->isTeamUser($this->id))
                    $team->addUser($this->id);

                unset($currentTeamIds[$id]);
            }
        }

        // remove old teams
        foreach ($currentTeamIds as $id => $name) {
            $team = new Scalr_Account_Team();
            $team->loadById($id);
            $team->removeUser($this->id);
        }
    }

    /**
     * Gets user teams
     *
     * @return array
     */
    public function getTeams()
    {
        return $this->db()->getAll('
            SELECT at.id, at.name
            FROM account_teams at
            JOIN account_team_users tu ON at.id = tu.team_id
            WHERE tu.user_id = ?
        ', array($this->id));
    }

}