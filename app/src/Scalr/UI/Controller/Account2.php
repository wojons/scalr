<?php
use Scalr\Acl\Acl;
use Scalr\DataType\CloudPlatformSuspensionInfo;

class Scalr_UI_Controller_Account2 extends Scalr_UI_Controller
{

    public function addUiCacheKeyPatternChunk($chunk)
    {
        if ($chunk == 'account2')
            $chunk = 'account';

        $this->uiCacheKeyPattern .= "/{$chunk}";
    }

    public static function getApiDefinitions()
    {
        return array('xGetData');
    }

    public function xGetDataAction()
    {
        $this->request->defineParams(array(
            'stores' => array('type' => 'json'), 'action'
        ));

        $stores = array();
        foreach ($this->getParam('stores') as $storeName) {
            $method = 'get' . implode('', array_map('ucfirst', explode('.', strtolower($storeName)))) . 'List';
            if (method_exists($this, $method)) {
                $stores[$storeName] = $this->$method();
            }
        }

        $this->response->data(array(
            'stores' => $stores
        ));
    }

    public function getAccountEnvironmentsList()
    {
        $environments = $this->user->getEnvironments();
        $result = array();
        foreach ($environments as &$row) {
            $env = Scalr_Environment::init()->loadById($row['id']);
            $row['platforms'] = $env->getEnabledPlatforms();
            $row['suspendedPlatforms'] = [];
            foreach ($row['platforms'] as $platform) {
                $suspensionInfo = new CloudPlatformSuspensionInfo($env->id, $platform);
                if ($suspensionInfo->isPendingSuspend() || $suspensionInfo->isSuspended()) {
                    $row['suspendedPlatforms'][] = $platform;
                }
            }
            $row['teams'] = array();
            if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap') {
                $row['teamIds'] = array();
            }
            foreach ($env->getTeams() as $teamId) {
                if ($this->getContainer()->config->get('scalr.auth_mode') == 'ldap') {
                    $team = new Scalr_Account_Team();
                    $team->loadById($teamId);
                    $row['teams'][] = $team->name;
                    $row['teamIds'][] = $teamId;
                } else {
                    $row['teams'][] = $teamId;
                }
            }
            $row['dtAdded'] = Scalr_Util_DateTime::convertTz($env->dtAdded);
            $row['status'] = $env->status;

            if ($this->getContainer()->analytics->enabled) {
                $row['ccId'] = $env->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);
            }

            $result[] = &$row;
        }

        return $result;
    }

    public function getAccountUsersList()
    {
        if ($this->user->canManageAcl()) {
            $result = $this->db->getAll('SELECT account_users.id FROM account_users WHERE account_id = ?', array($this->user->getAccountId()));
            foreach ($result as &$row) {
                $row = Scalr_Account_User::init()->loadById($row['id'])->getUserInfo();
            }
        } else {
            $result = array();
            $teams = $this->user->getTeams();
            if (!empty($teams)) {
                $sql = '
                    SELECT u.id, u.fullname, u.email
                    FROM account_users u
                    INNER JOIN account_team_users tu ON u.id = tu.user_id
                    WHERE account_id= ?';
                $params[] = $this->user->getAccountId();

                foreach ($teams as $team) {
                    $r[] = 'tu.team_id = ?';
                    $params[] = $team['id'];
                }

                $sql .= ' AND (' . implode(' OR ', $r) . ')';
                $result = $this->db->getAll($sql, $params);
            }
        }

        return $result;
    }

    /**
     * Gets all account teams list
     *
     * @return array
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function getAccountTeamsList()
    {
        $acl = \Scalr::getContainer()->acl;

        if ($this->user->canManageAcl()) {
            $teamIds = $this->db->getAll('SELECT id FROM account_teams WHERE account_id = ?', array($this->user->getAccountId()));
        } else {
            $teamIds = $this->user->getTeams();
        }

        $result = array();
        foreach ($teamIds as &$row) {
            $team = Scalr_Account_Team::init()->loadById($row['id']);
            $resultRow = array(
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'account_role_id' => $team->accountRoleId
            );
            $users = array_map(function($arr) {
                return $arr['id'];
            }, $team->getUsers());
            if (!empty($users)) {
                foreach ($acl->getUserRoleIdsByTeam($users, $row['id'], $this->user->getAccountId()) as $userId => $roles) {
                    $resultRow['users'][] = array(
                        'id'    => $userId,
                        'roles' => $roles,
                    );
                }
            }
            $result[] = $resultRow;
        }

        return $result;
    }

    public function getAccountAclList()
    {
        if ($this->user->canManageAcl()) {
            return \Scalr::getContainer()->acl->getAccountRolesComputed($this->request->getUser()->getAccountId());
        } else {
            return $this->db->getAll('SELECT `account_role_id` as `id`, `name`, HEX(`color`) AS color FROM `acl_account_roles` WHERE `account_id` = ?', $this->request->getUser()->getAccountId());
        }

    }

    public function getBaseAclList()
    {
        if (!$this->user->canManageAcl()) {
            throw new Scalr_Exception_InsufficientPermissions();
        }
        return \Scalr::getContainer()->acl->getRolesComputed();
    }

    /*
     * Redirects for account scope
     */
    public function dashboardAction()
    {
        self::loadController('Dashboard')->defaultAction();
    }

    public function eventsAction()
    {
        self::loadController('Events', 'Scalr_UI_Controller_Scripts')->defaultAction();
    }

    public function scriptsAction()
    {
        self::loadController('Scripts')->defaultAction();
    }
}
