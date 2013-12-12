<?php

class Scalr_Account_Team extends Scalr_Model
{
    protected $dbTableName = 'account_teams';
    protected $dbPrimaryKey = "id";
    protected $dbMessageKeyNotFound = "Team #%s not found in database";

    const PERMISSIONS_OWNER = 'owner';
    const PERMISSIONS_FULL = 'full';
    const PERMISSIONS_GROUPS = 'groups';

    protected $dbPropertyMap = array(
        'id'			  => 'id',
        'account_id'	  => 'accountId',
        'account_role_id' => 'accountRoleId',
        'name'			  => 'name'
    );

    public
        $accountId,
        $name,
        $accountRoleId;

    /**
     * @return Scalr_Account_Team
     */
    public static function init($className = null) {
        return parent::init();
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::save()
     */
    public function save($forceInsert = false)
    {
        $id = $this->db->getOne('SELECT id FROM `account_teams` WHERE name = ? AND account_id = ? LIMIT 1', array($this->name, $this->accountId));
        if ($id && $this->id != $id)
            throw new Exception('Team with such name already exists');

        return parent::save();
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::delete()
     */
    public function delete($id = null)
    {
        parent::delete();

        $this->db->Execute('DELETE FROM `account_team_users` WHERE team_id = ?', array($this->id));
        $this->db->Execute('DELETE FROM `account_team_envs` WHERE team_id = ?', array($this->id));

    }

    /**
     * Gets the all members of the Team
     *
     * @return  array Returns all members of the Team
     */
    public function getUsers()
    {
        return $this->db->getAll("
            SELECT au.`id`, `email`, `fullname`, `permissions`
            FROM `account_users` au
            JOIN `account_team_users` atu ON au.`id` = atu.`user_id`
            WHERE atu.`team_id` = ?
        ", array($this->id));
    }

    /**
     * Saves all relations between all users of this team and ACL roles
     *
     * @param   array   $data Roles array should look like array(user_id => array(account_role_id, ...))
     * @throws  \Scalr\Acl\Exception\AclException
     */
    public function setUserRoles(array $data = array())
    {
        if (empty($this->id)) {
            throw new \Scalr\Acl\Exception\AclException(sprintf(
                "ID of the team is expected. It hasn't been initialized yet."
            ));
        }
        \Scalr::getContainer()->acl->setAllRolesForTeam($this->id, $data, $this->accountId);
    }

    /**
     * Gets the owner of the team
     *
     * Team Owner is the first user of Account Admin type which belongs to the team.
     * If there are nobody assigned, team owner is considered to be either the AccountAdmin
     * or Account Owner regardless of membership of the Team.
     *
     * @var     bool $onlyFirst     Deprecated option
     * @return  Scalr_Account_User  Returns the user object
     * @throws  \Exception
     */
    public function getOwner($onlyFirst = true)
    {
        $id = $this->db->getOne("
            SELECT u.id FROM `account_users` u
            JOIN `account_team_users` tu ON tu.user_id = u.id
            WHERE u.`type` = ? AND tu.team_id = ? AND u.`account_id` = ?
            LIMIT 1
        ", array(\Scalr_Account_User::TYPE_ACCOUNT_ADMIN, $this->id, $this->accountId));

        if (!$id) {
            //If nothing found it will try to return any account admin
            $id = $this->db->getOne("
                SELECT u.id FROM `account_users` u WHERE u.`type` = ? AND u.`account_id` = ? LIMIT 1
            ", array(\Scalr_Account_User::TYPE_ACCOUNT_ADMIN, $this->accountId));
        }

        if (!$id) {
            //If any account admin is found it will return account owner
            $id = $this->db->getOne("
                SELECT u.id FROM `account_users` u WHERE u.`type` = ? AND u.`account_id` = ?
            ", array(\Scalr_Account_User::TYPE_ACCOUNT_OWNER, $this->accountId));
        }

        return Scalr_Account_User::init()->loadById($id);
    }

    /**
     * Checks whether specified user is the owner of the team
     *
     * Team Owner is the first user of Account Admin type which belongs to the team.
     * If there are nobody assigned, team owner is considered to be either the AccountAdmin
     * or Account Owner regardless of membership of the Team.
     *
     * @param  string    $userId  The identifier of the User
     * @return boolean   Returns true if the specified user is considered to be team owner.
     */
    public function isTeamOwner($userId)
    {
        return (bool)$this->db->GetOne("
            SELECT u.id
            FROM `account_users` u
            JOIN `account_team_users` tu ON u.id = tu.user_id
            WHERE u.`account_id` = ? AND u.`id` = ? AND u.`type` IN (?, ?)
            AND tu.`team_id` = ?
            LIMIT 1
        ", array(
            $this->accountId,
            $userId,
            \Scalr_Account_User::TYPE_ACCOUNT_ADMIN,
            $this->id
        ));
    }

    public function isTeamUser($userId)
    {
        return $this->db->getOne('SELECT user_id FROM `account_team_users` WHERE team_id = ? AND user_id = ? LIMIT 1', array($this->id, $userId)) ? true : false;
    }

    /**
     *
     * @return array of Scalr_Environment
     */
    public function getEnvironments()
    {
        $result = array();
        foreach($this->db->getAll('SELECT env_id FROM `account_team_envs` WHERE team_id = ?', array($this->id)) as $r) {
            $env = Scalr_Environment::init()->loadById($r['env_id']);
            $result[] = array('id' => $env->id, 'name' => $env->name);
        }
        return $result;
    }

    /**
     * Adds user to the team
     *
     * @param   int        $userId      The identifier of the user
     * @param   string     $permissions This parameter has been deprecated since new ACL.
     * @throws  Exception
     */
    public function addUser($userId, $permissions = null)
    {
        $user = Scalr_Account_User::init();
        $user->loadById($userId);

        if ($user->getAccountId() == $this->accountId) {
            $this->removeUser($userId);
            $this->db->Execute("
                INSERT INTO `account_team_users` (team_id, user_id, permissions)
                VALUES (?, ?, ?)
            ", array(
                $this->id, $userId, $permissions
            ));
        } else {
            throw new Exception(sprintf(
                'The specified user "%d" doesn\'t belong to account "%d".',
                $userId, $this->accountId
            ));
        }
    }

    public function removeUser($userId)
    {
        $this->db->Execute("
            DELETE FROM `account_team_users`
            WHERE user_id = ? AND team_id = ?
        ", array(
            $userId, $this->id
        ));
    }

    public function addEnvironment($envId)
    {
        $env = Scalr_Environment::init();
        $env->loadById($envId);

        if ($this->accountId == $env->clientId) {
            $this->removeEnvironment($envId);
            $this->db->Execute('INSERT IGNORE INTO `account_team_envs` (team_id, env_id) VALUES(?, ?)', array(
                $this->id, $envId
            ));
        } else {
            throw new Exception(sprintf(
                'Specified environment "%d" doesn\'t belong to your account "%d".',
                $envId, $env->clientId
            ));
        }
    }

    public function removeEnvironment($envId)
    {
        $this->db->Execute('DELETE FROM `account_team_envs` WHERE env_id = ? AND team_id = ?', array($envId, $this->id));
    }
}
