<?php

use Scalr\Acl\Acl;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;

class Scalr_UI_Controller_Farms_Roles extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'farmRoleId';

    /**
     * @var DBFarm
     */
    private $dbFarm;

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES);
    }

    public function init()
    {
        $this->dbFarm = DBFarm::LoadByID($this->getParam(Scalr_UI_Controller_Farms::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($this->dbFarm);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function getListAction()
    {
        $this->request->defineParams(array(
            'behaviors' => array('type' => 'json')
        ));

        $list = $this->getList($this->getParam('behaviors'));

        $this->response->data(array('farmRoles' => $list));
    }

    public function getList(array $behaviors = array())
    {
        $retval = array();
        $s = $this->db->execute("SELECT id, platform, role_id, alias AS `name` FROM farm_roles WHERE farmid = ?", array($this->dbFarm->ID));
        while ($farmRole = $s->fetchRow()) {
            try {
                $dbRole = DBRole::loadById($farmRole['role_id']);
                if (! $farmRole['name'])
                    $farmRole['name'] = $dbRole->name;

                if (!empty($behaviors)) {
                    $bFilter = false;
                    foreach ($behaviors as $behavior) {
                        if ($dbRole->hasBehavior($behavior)) {
                            $bFilter = true;
                            break;
                        }
                    }

                    if (!$bFilter)
                        continue;
                }
            } catch (Exception $e) {
                $farmRole['name'] = '*removed*';
            }

            $retval[$farmRole['id']] = $farmRole;
        }

        return $retval;
    }

    public function viewAction()
    {
        $this->response->page('ui/farms/roles/view.js', array('farmName' => $this->dbFarm->Name));
    }

    public function extendedInfoAction()
    {
        $dbFarmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
        $this->user->getPermissions()->validate($dbFarmRole);

        $scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
        $scaling_algos = array();
        foreach ($scalingManager->getFarmRoleMetrics() as $farmRoleMetric)
            $scaling_algos[] = array(
                'name' => $farmRoleMetric->getMetric()->name,
                'last_value' => $farmRoleMetric->lastValue ? $farmRoleMetric->lastValue : 'Unknown',
                'date'		=> Scalr_Util_DateTime::convertTz($farmRoleMetric->dtLastPolled)
            );

        $form = array(
            array(
                'xtype' => 'fieldset',
                'title' => 'General',
                'labelWidth' => 250,
                'defaults' => array('labelWidth' => 250),
                'items' => array(
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Farm Role ID',
                        'value' => $dbFarmRole->ID
                    ),
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Farm ID',
                        'value' => $dbFarmRole->FarmID
                    ),
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Role ID',
                        'value' => $dbFarmRole->RoleID
                    ),
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Role name',
                        'value' => $dbFarmRole->GetRoleObject()->name
                    ),
                    array(
                        'xtype' => 'displayfield',
                        'fieldLabel' => 'Platform',
                        'value' => $dbFarmRole->Platform
                    )
                )
            )
        );

        $it = array();
        foreach ($scaling_algos as $algo) {
            $it[] = array(
                'xtype' => 'displayfield',
                'fieldLabel' => $algo['name'],
                'value' => ($algo['date']) ? "Checked at {$algo['date']}. Value: {$algo['last_value']}" : "Never checked"
            );
        }

        $form[] = array(
            'xtype' => 'fieldset',
            'labelWidth' => 250,
            'defaults' => array('labelWidth' => 250),
            'title' => 'Scalr information',
            'items' => $it
        );


        $it = array();
        foreach ($dbFarmRole->GetAllSettings() as $name => $value) {
            $it[] = array(
                'xtype' => 'displayfield',
                'fieldLabel' => $name,
                'value' => $value
            );
        }

        $form[] = array(
            'xtype' => 'fieldset',
            'labelWidth' => 250,
            'defaults' => array('labelWidth' => 250),
            'title' => 'Scalr internal properties',
            'items' => $it
        );

        $this->response->page('ui/farms/roles/extendedinfo.js', array(
            'form' => $form, 'farmName' => $this->dbFarm->Name, 'roleName' => $dbFarmRole->GetRoleObject()->name
        ));
    }

    public function xDowngradeAction()
    {
        if (!$this->getParam('roleId'))
            throw new Exception("Please select role on which you want to downgrade");


        $dbFarmRole = DBFarmRole::LoadByID($this->getParam(self::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($dbFarmRole);

        if ($dbFarmRole->NewRoleID)
            throw new Exception("You cannot downgrade role that has active bundle task");

        $newRole = DBRole::loadById($this->getParam('roleId'));
        if ($newRole->envId != 0)
            $this->user->getPermissions()->validate($newRole);

        $currentRole = $dbFarmRole->GetRoleObject();

        if ($currentRole->id == $newRole->id)
            throw new Exception("You're already using selected role");

        $dbFarmRole->RoleID = $newRole->id;
        $dbFarmRole->Save();

        $this->response->success("Farm role successfully downgraded to older version of role. This action doesn't affect current running instances.");
    }

    public function downgradeAction()
    {
        $dbFarmRole = DBFarmRole::LoadByID($this->getParam(self::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($dbFarmRole);

        $dbRole = $dbFarmRole->GetRoleObject();

        $history = array();
        foreach ($dbRole->getRoleHistory(false) as $roleName)
        {
            $item = false;
            try {
                $hRole = DBRole::loadByFilter(array('name' => $roleName));
                if ($hRole->envId != 0)
                    $this->user->getPermissions()->validate($hRole);
            } catch (Exception $e) { $hRole = false; }

            if ($hRole){
                $item = array(
                    'xtype' => 'radiofield',
                    'name' => 'roleId',
                    'boxLabel' => $hRole->name,
                    'inputValue' => $hRole->id
                );
            } elseif ($roleName) {
                $item = array(
                    'xtype' => 'displayfield',
                    'hideLabel' => true,
                    'value' => $roleName . " [ <span style='color:gray;font-style:italic;'>Role was removed and no longer available</span> ]"
                );
            }

            if ($item)
                $history[] = $item;
        }

        $history[]  = array(
            'xtype' => 'displayfield',
            'hideLabel' => true,
            'value' => "<b>".$dbRole->name."</b> [ Current role ]"
        );

        $this->response->page('ui/farms/roles/downgrade.js', array(
            'history' => $history
        ));
    }

    public function xLaunchNewServerAction()
    {
        $dbFarmRole = DBFarmRole::LoadByID($this->getParam('farmRoleId'));
        $dbFarm = $dbFarmRole->GetFarmObject();

        $this->user->getPermissions()->validate($dbFarmRole);

        if ($dbFarm->Status != FARM_STATUS::RUNNING)
            throw new Exception("You can launch servers only on running farms");

        $dbRole = $dbFarmRole->GetRoleObject();

        if ($dbRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER))
            throw new Exception("Manual launch of VPC Router insatnces is not allowed");

        $pendingInstancesCount = $dbFarmRole->GetPendingInstancesCount();

        $maxInstances = $dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MAX_INSTANCES);
        $minInstances = $dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MIN_INSTANCES);

        if ($maxInstances < $minInstances+1) {
            $dbFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_MAX_INSTANCES, $maxInstances+1, DBFarmRole::TYPE_CFG);

            $warnMsg = sprintf(_("Server count has been increased. Scalr will now request a new server from your cloud. Since the server count was already at the maximum set for this role, we increased the maximum by one."),
                $dbRole->name, $dbRole->name
            );
        }

        $runningInstancesCount = $dbFarmRole->GetRunningInstancesCount();

        if ($runningInstancesCount+$pendingInstancesCount >= $minInstances)
            $dbFarmRole->SetSetting(DBFarmRole::SETTING_SCALING_MIN_INSTANCES, $minInstances+1, DBFarmRole::TYPE_CFG);

        $serverCreateInfo = new ServerCreateInfo($dbFarmRole->Platform, $dbFarmRole);

        Scalr::LaunchServer($serverCreateInfo, null, false, DBServer::LAUNCH_REASON_MANUALLY, $this->user);

        if ($warnMsg)
            $this->response->warning($warnMsg);
        else
            $this->response->success('Server successfully launched');
    }

    public function xListFarmRolesAction()
    {
        $this->request->defineParams(array(
            'farmId' => array('type' => 'int'),
            'farmRoleId' => array('type' => 'int'),
            'roleId' => array('type' => 'int'),
            'id' => array('type' => 'int'),
            'sort' => array('type' => 'json')
        ));

        $allFarms = $this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS);

        $sql = "
            SELECT farm_roles.*
            FROM farm_roles
            JOIN roles ON farm_roles.role_id = roles.id
            WHERE farmid = ?
            AND :FILTER:
        ";

        $params = array($this->getParam('farmId'));

        if (!$allFarms) {
            $sql .= " AND `farmid` IN (SELECT id FROM `farms` WHERE env_id = " . intval($this->getEnvironmentId()) . " AND created_by_id = " . intval($this->user->getId()) . ") ";
        }

        if ($this->getParam('roleId')) {
            $sql .= ' AND role_id = ?';
            $params[] = $this->getParam('roleId');
        }

        if ($this->getParam('farmRoleId')) {
            $sql .= ' AND farm_roles.id = ?';
            $params[] = $this->getParam('farmRoleId');
        }

        $response = $this->buildResponseFromSql(
            $sql,
            array('platform', 'name', 'alias'),
            array('name'),
            $params
        );

        foreach ($response['data'] as &$row) {
            $row["running_servers"] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_roleid='{$row['id']}' AND status IN ('Pending', 'Initializing', 'Running', 'Temporary')");
            $row["suspended_servers"] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_roleid='{$row['id']}' AND status IN ('Suspended', 'Pending suspend')");
            $row["non_running_servers"] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_roleid='{$row['id']}' AND status NOT IN ('Pending', 'Initializing', 'Running', 'Temporary')");

            $row['farm_status'] = $this->db->GetOne("SELECT status FROM farms WHERE id=? LIMIT 1", array($row['farmid']));

            $row["domains"] = $this->db->GetOne("SELECT COUNT(*) FROM dns_zones WHERE farm_roleid=? AND status != ? AND farm_id=?",
                array($row["id"], DNS_ZONE_STATUS::PENDING_DELETE, $row['farmid'])
            );

            $DBFarmRole = DBFarmRole::LoadByID($row['id']);

            $row['min_count'] = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MIN_INSTANCES);
            $row['max_count'] = $DBFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_MAX_INSTANCES);
            $row['allow_launch_instance'] = (!$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB) && !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER));

            $vpcId = $this->environment->getPlatformConfigValue(Ec2PlatformModule::DEFAULT_VPC_ID.".{$DBFarmRole->CloudLocation}");
            $row['is_vpc'] = ($vpcId || $DBFarmRole->GetFarmObject()->GetSetting(DBFarm::SETTING_EC2_VPC_ID)) ? true : false;

            $row['location'] = $DBFarmRole->CloudLocation;

            $DBRole = DBRole::loadById($row['role_id']);
            $row["name"] = $DBRole->name;
            $row['image_id'] = $DBRole->getImageId(
                $DBFarmRole->Platform,
                $DBFarmRole->CloudLocation
            );

            if ($DBFarmRole->GetFarmObject()->Status == FARM_STATUS::RUNNING) {
                $row['shortcuts'] = [];
                foreach (\Scalr\Model\Entity\ScriptShortcut::find(array(
                    array('farmRoleId' => $row['id'])
                )) as $shortcut) {
                    /* @var \Scalr\Model\Entity\ScriptShortcut $shortcut */
                    $row['shortcuts'][] = array(
                        'id' => $shortcut->id,
                        'name' => $shortcut->getScriptName()
                    );
                }
            }

            $scalingManager = new Scalr_Scaling_Manager($DBFarmRole);
            $scaling_algos = array();
            foreach ($scalingManager->getFarmRoleMetrics() as $farmRoleMetric)
                $scaling_algos[] = $farmRoleMetric->getMetric()->name;

            if (count($scaling_algos) == 0)
                $row['scaling_algos'] = _("Scaling disabled");
            else
                $row['scaling_algos'] = implode(', ', $scaling_algos);
        }

        $this->response->data($response);
    }

    public function replaceRoleAction()
    {
        $dbFarmRole = DBFarmRole::LoadByID($this->getParam(self::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($dbFarmRole);

        $roles = $dbFarmRole->getReplacementRoles(true);
        $this->response->page('ui/farms/roles/replaceRole.js', array(
            'roleId' => $dbFarmRole->RoleID,
            'roles' => $roles
        ));
    }

    public function xReplaceRoleAction()
    {
        if (!$this->request->getParam('roleId')) {
            throw new Exception("Please select role");
        }

        $dbFarmRole = DBFarmRole::LoadByID($this->getParam(self::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($dbFarmRole);

        $newRole = DBRole::loadById($this->request->getParam('roleId'));
        if ($newRole->envId != 0) {
            $this->user->getPermissions()->validate($newRole);
        }

        //TODO: Add validation of cloud/location/os_family and behavior

        $oldName = $dbFarmRole->GetRoleObject()->name;

        $dbFarmRole->RoleID = $newRole->id;
        $dbFarmRole->Save();

        Logger::getLogger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage($dbFarmRole->FarmID,
            sprintf("Role '%s' was upgraded to role '%s'",
                $oldName,
                $newRole->name
            )
        ));

        $imageInfo = $newRole->getImageDetails($dbFarmRole->Platform, $dbFarmRole->CloudLocation);

        $this->response->success("Role successfully replaced.");
        $this->response->data(array(
            'role' => array(
                'role_id'       => $newRole->id,
                'name'          => $newRole->name,
                'os'			=> $newRole->os,
                'os_family'     => $newRole->osFamily,
                'os_generation' => $newRole->osGeneration,
                'os_version'    => $newRole->osVersion,
                'generation'	=> $newRole->generation,
                'tags'			=> $newRole->getTags(),
                'image_id'      => $imageInfo['image_id'],
                'arch'          => $imageInfo['architecture'] ? $imageInfo['architecture'] : (stristr($newRole->name, '64-') ? 'x86_64' : null)
            ),
        ));
    }

}
