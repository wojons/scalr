<?php

use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\Os;
use Scalr\Util\CryptoTool;

class DBRole
{
    public
        $id,
        $name,
        $dateAdded,
        $imageId,
        $envId,
        $catId,
        $origin,
        $clientId,
        $description,
        $isDevel,
        $generation,
        $addedByUserId,
        $dtLastUsed,
        $addedByEmail,
        $osId;

    private
        $db,
        $behaviors,
        $tags = array(),
        $images,
        $behaviorsRaw,
        $environment,
        $__newRoleObject; // temp

    /**
     * @var Os
     */
    private $__os;

    /*Temp*/
    public $instanceType;

    private static $FieldPropertyMap = array(
        'id' 			=> 'id',
        'client_id'		=> 'clientId',
        'origin'		=> 'origin',
        'cat_id'        => 'catId',
        'name'			=> 'name',
        'env_id'		=> 'envId',
        'description'	=> 'description',
        'is_devel'		=> 'isDevel',
        'generation'	=> 'generation',
        'os_id'			=> 'osId',

        'dtadded'         => 'dateAdded',
        'dt_last_used'    => 'dtLastUsed',
        'added_by_userid' => 'addedByUserId',
        'added_by_email'  => 'addedByEmail',
        'behaviors'		=> 'behaviorsRaw'
    );

    const PROPERTY_SSH_PORT = 'system.ssh-port';

    public function __construct($id)
    {
        $this->id = $id;
        $this->db = \Scalr::getDb();
    }

    /**
     * @return \Scalr\Model\Entity\Os
     */
    public function getOs()
    {
        return $this->__os;
    }

    public function setBehaviors($behaviors)
    {
        //TODO: validation

        $this->behaviorsRaw = implode(",", array_unique($behaviors));
        $this->behaviors = null;
    }

    public function setProperty($name, $value)
    {
        //UNIQUE KEY `NewIndex1` (`role_id`,`name`),
        $this->db->Execute("
            INSERT INTO role_properties
            SET `role_id` = ?,
                `name`	= ?,
                `value`	= ?
            ON DUPLICATE KEY UPDATE
                `value` = ?
        ", array(
            $this->id,
            $name,
            $value,
            $value,
        ));
    }

    public function setProperties($properties)
    {
        foreach ($properties as $name => $value) {
            $this->setProperty($name, $value);
        }
    }

    public function getProperty($name)
    {
        return $this->db->GetOne("SELECT value FROM `role_properties` WHERE `role_id` = ? AND `name` = ? LIMIT 1", array(
            $this->id, $name
        ));
    }

    public function getProperties($filter = '')
    {
        $properties = $this->db->Execute("SELECT * FROM `role_properties` WHERE `role_id` = ?" . ($filter ? " AND name LIKE " . $this->db->qstr('%' . $filter . '%') : ''), array($this->id));
        $retval = array();
        while ($property = $properties->FetchRow()) {
            $retval[$property['name']] = $property['value'];
        }

        return $retval;
    }

    public function clearProperties($filter = '')
    {
        $this->db->Execute("DELETE FROM `role_properties` WHERE `role_id`= ?" . ($filter ? " AND name LIKE " . $this->db->qstr('%' . $filter . '%') : ''), array($this->id));
    }

    public function getSecurityRules()
    {
        return $this->db->GetAll("SELECT * FROM role_security_rules WHERE role_id=?", array($this->id));
    }

    public function getCategoryName()
    {
        return $this->db->GetOne("SELECT name FROM role_categories WHERE id=? LIMIT 1", array($this->catId));
    }

    public function getDbMsrBehavior()
    {
        if ($this->hasBehavior(ROLE_BEHAVIORS::REDIS))
            return ROLE_BEHAVIORS::REDIS;
        elseif ($this->hasBehavior(ROLE_BEHAVIORS::POSTGRESQL))
            return ROLE_BEHAVIORS::POSTGRESQL;
        elseif ($this->hasBehavior(ROLE_BEHAVIORS::MYSQL2))
            return ROLE_BEHAVIORS::MYSQL2;
        elseif ($this->hasBehavior(ROLE_BEHAVIORS::PERCONA))
            return ROLE_BEHAVIORS::PERCONA;
        elseif ($this->hasBehavior(ROLE_BEHAVIORS::MARIADB))
            return ROLE_BEHAVIORS::MARIADB;


        return false;
    }

    public function hasBehavior($behavior)
    {
        return (in_array($behavior, $this->getBehaviors()));
    }

    public function getBehaviors()
    {
        if (!$this->behaviors) {
            $this->behaviors = array_unique(explode(",", $this->behaviorsRaw));
        }

        return $this->behaviors;
    }

    /**
     * @return Scalr_Environment
     * Enter description here ...
     */
    public function getEnvironmentObject()
    {
        if (!$this->environment)
            $this->environment = Scalr_Model::init(Scalr_Model::ENVIRONMENT)->loadById($this->envId);

        return $this->environment;
    }

    public static function loadByFilter(array $filter)
    {
        $db = \Scalr::getDb();

        $sql = "SELECT id FROM roles WHERE 1=1";
        $args = array();
        foreach ($filter as $k=>$v)
        {
            $sql .= " AND `{$k}`=?";
            $args[] = $v;
        }

        $roles = $db->GetAll($sql, $args);
        if (count($roles) == 1)
        {
            return self::loadById($roles[0]['id']);
        }
        else
        {
            $retval = array();
            foreach ($roles as $role)
                $retval[] = self::loadById($role['id']);

            return $retval;
        }
    }

    /**
     * @param int $id
     *
     * @return DBRole
     *
     * @throws Exception
     */
    public static function loadById($id)
    {
        $db = \Scalr::getDb();

        $roleinfo = $db->GetRow("SELECT * FROM roles WHERE id=?", array($id));
        if (!$roleinfo)
            throw new Exception(sprintf(_("Role ID#%s not found in database"), $id));

        $DBRole = new DBRole($id);

        foreach(self::$FieldPropertyMap as $k=>$v)
        {
            if (isset($roleinfo[$k]))
                $DBRole->{$v} = $roleinfo[$k];
        }

        if (!$DBRole->__os) {
            $DBRole->__os = Os::findOne([
                ['id' => $DBRole->osId]
            ]);
        }

        return $DBRole;
    }

    private function getVersionInfo($v)
    {
        if (preg_match("/^([0-9]+)\.([0-9]+)[-\.]?([0-9]+)?$/si", $v, $matches)) {
            $verInfo = array_map("intval", array_slice($matches, 1));
            while (count($verInfo) < 3) {
                $verInfo[] = 0;
            }
            return $verInfo;
        } else {
            return array(0, 0, 0);
        }
    }

    public function save()
    {
        if (!$this->id) {
            $this->db->Execute("INSERT INTO roles SET
                name		= ?,
                dtadded     = NOW(),
                description	= ?,
                generation	= ?,
                origin		= ?,
                env_id		= ?,
                cat_id      = ?,
                client_id	= ?,
                behaviors	= ?,

                added_by_userid = ?,
                added_by_email = ?,

                os_id		= ?
            ", array(
                $this->name,
                $this->description,
                $this->generation,
                $this->origin,
                (empty($this->envId) ? null : $this->envId),
                $this->catId,
                (empty($this->clientId) ? null : $this->clientId),
                $this->behaviorsRaw,

                $this->addedByUserId,
                $this->addedByEmail,

                $this->osId
            ));

            $this->id = $this->db->Insert_ID();

            $this->db->Execute("DELETE FROM role_behaviors WHERE role_id = ?", array($this->id));
            foreach ($this->getBehaviors() as $behavior)
                $this->db->Execute("INSERT IGNORE INTO role_behaviors SET role_id = ?, behavior = ?", array($this->id, $behavior));

        } else {
            $this->db->Execute("
                UPDATE roles SET
                    name		= ?,
                    description	= ?,
                    behaviors	= ?,
                    dt_last_used = ?
                WHERE id =?
            ", array($this->name, $this->description, $this->behaviorsRaw, $this->dtLastUsed, $this->id));

            $this->db->Execute("DELETE FROM role_behaviors WHERE role_id = ?", array($this->id));

            foreach ($this->getBehaviors() as $behavior) {
                $this->db->Execute("INSERT IGNORE INTO role_behaviors SET role_id = ?, behavior = ?", array($this->id, $behavior));
            }

        }

        $this->syncAnalyticsTags();

        return $this;
    }

    /**
     * Synchronizes analytics role related tags
     */
    public function syncAnalyticsTags()
    {
        if (Scalr::getContainer()->analytics->enabled) {
            //Role name
            Scalr::getContainer()->analytics->tags->syncValue(
                $this->clientId, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_ROLE, $this->id, $this->name
            );

            //Role behavior
            $baseBehavior = !empty($this->behaviorsRaw) ? preg_split('/[,\s]+/', trim($this->behaviorsRaw))[0] : '';

            Scalr::getContainer()->analytics->tags->syncValue(
                $this->clientId, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_ROLE_BEHAVIOR, $this->id, $baseBehavior
            );
        }
    }

    /**
     * @deprecated
     */
    public function remove()
    {
        $this->db->Execute("DELETE FROM roles WHERE id = ?", array($this->id));
    }

    /**
     * @return \Scalr\Model\Entity\Role
     * @throws Exception
     */
    public function __getNewRoleObject()
    {
        if (! $this->__newRoleObject) {
            if ($this->id) {
                $this->__newRoleObject = Role::findPk($this->id);
            }

            if (! $this->__newRoleObject)
                throw new Exception('Role object is not found');
        }

        return $this->__newRoleObject;
    }

    public function getParameters()
    {
        $dbParams = $this->db->Execute("SELECT * FROM role_parameters WHERE role_id = ?", array($this->id));
        $retval = array();
        while ($param = $dbParams->FetchRow()) {
            $retval[] = array(
                'name'	=> $param['name'],
                'hash'	=> $param['hash'],
                'type'	=> $param['type'],
                'required'	=> $param['isrequired'],
                'defval'	=> $param['defval']
            );
        }

        return $retval;
    }

    public function setParameters(array $params = array())
    {
        $this->db->Execute("DELETE FROM role_parameters WHERE role_id = ?", array($this->id));
        foreach ($params as $param) {
            $param = (array)$param;

            $this->db->Execute("INSERT INTO role_parameters SET
                `role_id`		= ?,
                `name`			= ?,
                `type`			= ?,
                `isrequired`	= ?,
                `defval`		= ?,
                `allow_multiple_choice`	= 0,
                `options`		= '',
                `hash`			= ?,
                `issystem`		= 1
            ", array(
                $this->id,
                $param['name'],
                $param['type'],
                $param['required'],
                $param['defval'],
                str_replace(" ", "_", strtolower($param['name']))
            ));
        }
    }

    public function getScripts()
    {
        $dbParams = $this->db->Execute("SELECT role_scripts.*, scripts.name AS script_name FROM role_scripts LEFT JOIN scripts ON role_scripts.script_id = scripts.id WHERE role_id = ?", array($this->id));
        $retval = array();
        while ($script = $dbParams->FetchRow()) {
            $retval[] = array(
                'role_script_id' => (int) $script['id'],
                'event_name' => $script['event_name'],
                'target' => $script['target'],
                'script_id' => (int) $script['script_id'],
                'script_name' => $script['script_name'],
                'version' => (int) $script['version'],
                'timeout' => $script['timeout'],
                'isSync' => (int) $script['issync'],
                'params' => unserialize($script['params']),
                'order_index' => $script['order_index'],
                'hash' => $script['hash'],
                'script_path' => $script['script_path'],
                'run_as' => $script['run_as'],
                'script_type' => $script['script_type'],
                'os' => $script['os']
            );
        }

        return $retval;
    }

    public function setScripts(array $scripts)
    {
        if (! $this->id)
            return;

        if (! is_array($scripts))
            return;

        $ids = array();
        foreach ($scripts as $script) {
            // TODO: check permission for script_id
            if (!$script['role_script_id']) {
                $this->db->Execute('INSERT INTO role_scripts SET
                    `role_id` = ?,
                    `event_name` = ?,
                    `target` = ?,
                    `script_id` = ?,
                    `version` = ?,
                    `timeout` = ?,
                    `issync` = ?,
                    `params` = ?,
                    `order_index` = ?,
                    `hash` = ?,
                    `script_path` = ?,
                    `run_as` = ?,
                    `script_type` = ?
                ', array(
                    $this->id,
                    $script['event_name'],
                    $script['target'],
                    $script['script_id'] != 0 ? $script['script_id'] : NULL,
                    $script['version'],
                    $script['timeout'],
                    $script['isSync'],
                    serialize($script['params']),
                    $script['order_index'],
                    (!$script['hash']) ? CryptoTool::sault(12) : $script['hash'],
                    $script['script_path'],
                    $script['run_as'],
                    $script['script_type']
                ));
                $ids[] = $this->db->Insert_ID();
            } else {
                $this->db->Execute('UPDATE role_scripts SET
                    `event_name` = ?,
                    `target` = ?,
                    `script_id` = ?,
                    `version` = ?,
                    `timeout` = ?,
                    `issync` = ?,
                    `params` = ?,
                    `order_index` = ?,
                    `script_path` = ?,
                    `run_as` = ?,
                    `script_type` = ?
                    WHERE id = ? AND role_id = ?
                ', array(
                    $script['event_name'],
                    $script['target'],
                    $script['script_id'] != 0 ? $script['script_id'] : NULL,
                    $script['version'],
                    $script['timeout'],
                    $script['isSync'],
                    serialize($script['params']),
                    $script['order_index'],
                    $script['script_path'],
                    $script['run_as'],
                    $script['script_type'],

                    $script['role_script_id'],
                    $this->id
                ));
                $ids[] = $script['role_script_id'];
            }
        }

        $toRemove = $this->db->Execute('SELECT id, hash FROM role_scripts WHERE role_id = ? AND id NOT IN (\'' . implode("','", $ids) . '\')', array($this->id));
        while ($rScript = $toRemove->FetchRow()) {
            $this->db->Execute("DELETE FROM farm_role_scripting_params WHERE hash = ? AND farm_role_id IN (SELECT id FROM farm_roles WHERE role_id = ?)",
                array($rScript['hash'], $this->id)
            );
            $this->db->Execute("DELETE FROM role_scripts WHERE id = ?", array($rScript['id']));
        }
    }

    /**
     * @param   string              $newRoleName
     * @param   Scalr_Account_User  $user
     * @param   int                 $envId
     * @return  int
     * @throws Exception
     */
    public function cloneRole($newRoleName, $user, $envId)
    {
        $this->db->BeginTrans();

        $accountId = $user->getAccountId();
        try {
            $this->db->Execute("INSERT INTO roles SET
                name            = ?,
                origin          = ?,
                client_id       = ?,
                env_id          = ?,
                cat_id          = ?,
                description     = ?,
                behaviors       = ?,
                generation      = ?,
                os_id           = ?,
                dtadded         = NOW(),
                added_by_userid = ?,
                added_by_email  = ?
            ", array(
                $newRoleName,
                $accountId ? ROLE_TYPE::CUSTOM : ROLE_TYPE::SHARED,
                empty($accountId) ? null : intval($accountId),
                empty($envId) ? null : intval($envId),
                $this->catId,
                $this->description,
                $this->behaviorsRaw,
                2,
                $this->osId,
                $user->getId(),
                $user->getEmail()
            ));

            $newRoleId = $this->db->Insert_Id();

            //Set behaviors
            foreach ($this->getBehaviors() as $behavior)
                $this->db->Execute("INSERT IGNORE INTO role_behaviors SET role_id = ?, behavior = ?", array($newRoleId, $behavior));

            // Set images
            $rsr7 = $this->db->Execute("SELECT * FROM role_images WHERE role_id = ?", array($this->id));
            while ($r7 = $rsr7->FetchRow()) {
                $this->db->Execute("INSERT INTO role_images SET
                    `role_id` = ?,
                    `cloud_location` = ?,
                    `image_id` = ?,
                    `platform` = ?
                ", array($newRoleId, $r7['cloud_location'], $r7['image_id'], $r7['platform']));
            }

            $props = $this->db->Execute("SELECT * FROM role_properties WHERE role_id=?", array($this->id));
            while ($p1 = $props->FetchRow()) {
                $this->db->Execute("
                    INSERT INTO role_properties
                    SET `role_id` = ?,
                        `name`	= ?,
                        `value`	= ?
                    ON DUPLICATE KEY UPDATE
                        `value` = ?
                ", array(
                    $newRoleId,
                    $p1['name'],
                    $p1['value'],
                    $p1['value']
                ));
            }

            //Set global variables
            $variables = new Scalr_Scripting_GlobalVariables($this->clientId, $this->envId, Scalr_Scripting_GlobalVariables::SCOPE_ROLE);
            $variables->setValues($variables->getValues($this->id), $newRoleId);

            //Set scripts
            $rsr8 = $this->db->Execute("SELECT * FROM role_scripts WHERE role_id = ?", array($this->id));
            while ($r8 = $rsr8->FetchRow()) {
                $this->db->Execute("INSERT INTO role_scripts SET
                    role_id = ?,
                    event_name = ?,
                    target = ?,
                    script_id = ?,
                    version = ?,
                    timeout = ?,
                    issync = ?,
                    params = ?,
                    order_index = ?,
                    script_type = ?,
                    script_path = ?,
                    hash = ?
                ", array(
                    $newRoleId, $r8['event_name'], $r8['target'], $r8['script_id'], $r8['version'],
                    $r8['timeout'], $r8['issync'], $r8['params'], $r8['order_index'], $r8['script_type'], $r8['script_path'], CryptoTool::sault(12)
                ));
            }
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }

        $this->db->CommitTrans();

        if (!empty($newRoleId)) {
            $newRole = self::loadById($newRoleId);
            $newRole->syncAnalyticsTags();
        }

        return $newRoleId;
    }

    public static function createFromBundleTask(BundleTask $BundleTask)
    {
        $db = \Scalr::getDb();

        $DBServer = DBServer::LoadByID($BundleTask->serverId);

        if ($BundleTask->prototypeRoleId) {
            $proto_role = $db->GetRow("SELECT * FROM roles WHERE id=? LIMIT 1", array($BundleTask->prototypeRoleId));
            if (!$proto_role['architecture'])
                $proto_role['architecture'] = $DBServer->GetProperty(SERVER_PROPERTIES::ARCHITECTURE);
        } else {
            $proto_role = array(
                "behaviors" => $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR),
                "architecture" => $DBServer->GetProperty(SERVER_PROPERTIES::ARCHITECTURE),
                "name" => "*import*"
            );
        }

        if (!$proto_role['architecture'])
            $proto_role['architecture'] = 'x86_64';

        if (!$BundleTask->cloudLocation) {
            if ($DBServer)
                $BundleTask->cloudLocation = $DBServer->GetCloudLocation();
        }

        $osId = $BundleTask->osId;
        $meta = $BundleTask->getSnapshotDetails();

        if (!$osId) {
            if ($proto_role) {
                $osId = $proto_role['os_id'];
            } elseif ($meta['os'] && $meta['os']->version) {
                /*
                if ($meta['os']->version == '2008Server') {
                    $osInfo->name = 'Windows 2008 Server';
                    $osInfo->family = 'windows';
                    $osInfo->generation = '2008';
                    $osInfo->version = '2008Server';
                } elseif ($meta['os']->version == '2008ServerR2') {
                    $osInfo->name = 'Windows 2008 Server R2';
                    $osInfo->family = 'windows';
                    $osInfo->generation = '2008';
                    $osInfo->version = '2008ServerR2';
                }*/
                //TODO:
            }
        }

        if ($proto_role['cat_id'])
            $catId = $proto_role['cat_id'];
        else
            $catId = ROLE_BEHAVIORS::GetCategoryId($proto_role['behaviors']);

        $db->Execute("INSERT INTO roles SET
            name			= ?,
            origin			= ?,
            dtadded         = NOW(),
            client_id		= ?,
            env_id			= ?,
            cat_id          = ?,
            description		= ?,
            behaviors		= ?,
            generation		= ?,
            added_by_email  = ?,
            added_by_userid = ?,
            os_id			= ?
        ", array(
            $BundleTask->roleName,
            ROLE_TYPE::CUSTOM,
            $BundleTask->clientId,
            $BundleTask->envId,
            $catId,
            $BundleTask->description,
            $proto_role['behaviors'],
            2,
            $BundleTask->createdByEmail,
            $BundleTask->createdById,
            $osId
        ));

        $role_id = $db->Insert_Id();

        $BundleTask->roleId = $role_id;
        $BundleTask->Save();

        $BundleTask->Log(sprintf("Created new role. Role name: %s. Role ID: %s",
            $BundleTask->roleName, $BundleTask->roleId
        ));

        $role = self::loadById($role_id);

        $behaviors = explode(",", $proto_role['behaviors']);
        foreach ($behaviors as $behavior) {
            $db->Execute("INSERT IGNORE INTO role_behaviors SET
                role_id			= ?,
                behavior		= ?
            ", array(
                $role_id,
                $behavior
            ));
        }

        // Set image
        $role->__getNewRoleObject()->setImage(
            $BundleTask->platform,
            $BundleTask->cloudLocation,
            $BundleTask->snapshotId,
            $BundleTask->createdById,
            $BundleTask->createdByEmail
        );

        // Set params
        if ($proto_role['id']){
            $dbParams = $db->GetAll("SELECT name,type,isrequired,defval,allow_multiple_choice,options,hash,issystem
                FROM role_parameters WHERE role_id = ?", array($proto_role['id'])
            );
            $role->setParameters($dbParams);

            $dbSecRules = $db->GetAll("SELECT * FROM role_security_rules WHERE role_id = ?", array($proto_role['id']));
            foreach ($dbSecRules as $dbSecRule) {
                $db->Execute("INSERT INTO role_security_rules SET role_id = ?, rule = ?", array(
                    $role_id, $dbSecRule['rule']
                ));
            }

            $props = $db->GetAll("SELECT * FROM role_properties WHERE role_id=?", array($proto_role['id']));
            foreach ($props as $prop) {
                $role->setProperty($prop['name'], $prop['value']);
            }

            $scripts = $db->GetAll("SELECT * FROM role_scripts WHERE role_id=?", array($proto_role['id']));
            foreach ($scripts as &$script)
                $script['params'] = unserialize($script['params']);

            $role->setScripts($scripts);

            $variables = new Scalr_Scripting_GlobalVariables($BundleTask->clientId, $proto_role['env_id'], Scalr_Scripting_GlobalVariables::SCOPE_ROLE);
            $variables->setValues($variables->getValues($proto_role['id']), $role->id);
        }

        $role->syncAnalyticsTags();

        return $role;
    }

    /**
     * Gets farm role's count
     *
     * @param  int  $envId optional Current enviroment id
     * @return int  Returns farm role's count which uses current role
     */
    public function getFarmRolesCount($envId = null)
    {
        if ($envId !== null) {
            $join = sprintf(" JOIN farms f ON fr.farmid = f.id AND f.env_id = %d ", $envId);
        } else {
            $join = '';
        }

        $usedBy = $this->db->GetOne("
            SELECT SUM(number) FROM
                (SELECT COUNT(*) AS number
                FROM farm_roles fr
                " . $join . "
                WHERE fr.role_id=?
            UNION ALL
                SELECT COUNT(*) AS number
                FROM farm_roles fr
                " . $join . "
                WHERE fr.new_role_id=?)
            AS result",
            array($this->id, $this->id));

        return $usedBy;
    }

    /**
     * Gets an array of farms' ids
     *
     * @param  int    $envId optional Current enviroment id
     * @return array  Returns array of farms' ids which uses current role
     */
    public function getFarms($envId = null)
    {
        if ($envId !== null) {
            $join = sprintf(" JOIN farms f ON fr.farmid = f.id AND f.env_id = %d ", $envId);
        } else {
            $join = '';
        }

        $usedBy = $this->db->GetCol("
            SELECT fr.farmid
            FROM farm_roles fr
            " . $join . "
            WHERE fr.role_id=?
            UNION
            SELECT fr.farmid
            FROM farm_roles fr
            " . $join . "
            WHERE fr.new_role_id=?", array($this->id, $this->id));

        return $usedBy;
    }

}
