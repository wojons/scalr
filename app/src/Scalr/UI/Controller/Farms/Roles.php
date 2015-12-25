<?php

use Scalr\Acl\Acl;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Farms_Roles extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'farmRoleId';

    /**
     * @var DBFarm
     */
    private $dbFarm;

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
            'title' => 'Scaling information',
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

    /**
     * Launches new server
     * @param   int     $farmRoleId
     * @param   bool    $increaseMinInstances
     * @param   bool    $needConfirmation
     * @throws  Scalr_Exception_Core
     */
    public function xLaunchNewServerAction($farmRoleId, $increaseMinInstances = false, $needConfirmation = true)
    {
        $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
        $dbFarm = $dbFarmRole->GetFarmObject();

        $this->user->getPermissions()->validate($dbFarmRole);
        $this->request->restrictFarmAccess($dbFarm, Acl::PERM_FARMS_SERVERS);

        if ($dbFarm->Status != FARM_STATUS::RUNNING) {
            throw new Scalr_Exception_Core('You can launch servers only on running farms');
        }

        $dbRole = $dbFarmRole->GetRoleObject();

        if ($dbRole->hasBehavior(ROLE_BEHAVIORS::VPC_ROUTER)) {
            throw new Scalr_Exception_Core('Manual launch of VPC Router insatnces is not allowed');
        }

        if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_ENABLED) == 1) {
            $scalingManager = new Scalr_Scaling_Manager($dbFarmRole);
            $scalingMetrics = $scalingManager->getFarmRoleMetrics();
            $hasScalingMetrics = count($scalingMetrics) > 0;

            $curInstances = $dbFarmRole->GetPendingInstancesCount() + $dbFarmRole->GetRunningInstancesCount();

            $maxInstances = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES);
            $minInstances = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES);

            if ($needConfirmation) {
                $res = ['showConfirmation' => true];
                if ($maxInstances < $curInstances+1) {
                    $res['showIncreaseMaxInstancesWarning'] = true;
                    $res['maxInstances'] = $maxInstances+1;
                }

                if ($hasScalingMetrics && $curInstances >= $minInstances) {
                    $res['showIncreaseMinInstancesConfirm'] = true;
                }

                $this->response->data($res);
                return;
            } else {

                if ($maxInstances < $curInstances+1) {
                    $dbFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES, $maxInstances+1, Entity\FarmRoleSetting::TYPE_CFG);
                }

                if ($increaseMinInstances && $hasScalingMetrics && $curInstances >= $minInstances) {
                    $dbFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES, $minInstances+1, Entity\FarmRoleSetting::TYPE_CFG);
                }
            }
        }

        $serverCreateInfo = new ServerCreateInfo($dbFarmRole->Platform, $dbFarmRole);

        Scalr::LaunchServer($serverCreateInfo, null, false, DBServer::LAUNCH_REASON_MANUALLY, $this->user);

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

        $sql = "
            SELECT farm_roles.*
            FROM farm_roles
            JOIN roles ON farm_roles.role_id = roles.id
            WHERE farmid = ?
            AND :FILTER:
        ";

        $params = array($this->getParam('farmId'));

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
            $servers = $this->db->GetRow("
                SELECT SUM(IF(`status` IN (?,?,?,?,?),1,0)) AS running_servers,
                    SUM(IF(`status` IN (?,?),1,0)) AS suspended_servers,
                    SUM(IF(`status` IN (?,?),1,0)) AS non_running_servers
                FROM `servers` WHERE `farm_roleid` = ?
            ", [Entity\Server::STATUS_PENDING, Entity\Server::STATUS_INIT, Entity\Server::STATUS_RUNNING, Entity\Server::STATUS_TEMPORARY, Entity\Server::STATUS_RESUMING,
                Entity\Server::STATUS_SUSPENDED, Entity\Server::STATUS_PENDING_SUSPEND,
                Entity\Server::STATUS_TERMINATED, Entity\Server::STATUS_PENDING_TERMINATE,
                $row['id']
            ]);
            if (is_null($servers['running_servers'])) {
                $servers = ['running_servers' => 0, 'suspended_servers' => 0, 'non_running_servers' => 0];
            }
            $row = array_merge($row, $servers);

            $row['farm_status'] = $this->db->GetOne("SELECT status FROM farms WHERE id=? LIMIT 1", array($row['farmid']));

            $row["domains"] = $this->db->GetOne("SELECT COUNT(*) FROM dns_zones WHERE farm_roleid=? AND status != ? AND farm_id=?",
                array($row["id"], DNS_ZONE_STATUS::PENDING_DELETE, $row['farmid'])
            );

            $DBFarmRole = DBFarmRole::LoadByID($row['id']);

            $row['allow_launch_instance'] = (!$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB) && !$DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER));

            $vpcId = $this->environment->getPlatformConfigValue(Ec2PlatformModule::DEFAULT_VPC_ID.".{$DBFarmRole->CloudLocation}");
            $row['is_vpc'] = ($vpcId || $DBFarmRole->GetFarmObject()->GetSetting(Entity\FarmSetting::EC2_VPC_ID)) ? true : false;

            $row['location'] = $DBFarmRole->CloudLocation;

            $DBRole = DBRole::loadById($row['role_id']);
            $row["name"] = $DBRole->name;
            $row['image_id'] = $DBRole->__getNewRoleObject()->getImage(
                $DBFarmRole->Platform,
                $DBFarmRole->CloudLocation
            )->imageId;

            if ($DBFarmRole->GetFarmObject()->Status == FARM_STATUS::RUNNING) {
                $row['shortcuts'] = [];
                foreach (\Scalr\Model\Entity\ScriptShortcut::find([['farmRoleId' => $row['id']]]) as $shortcut) {
                    /* @var $shortcut \Scalr\Model\Entity\ScriptShortcut */
                    $row['shortcuts'][] = array(
                        'id'   => $shortcut->id,
                        'name' => $shortcut->getScriptName()
                    );
                }
            }

            $row['scaling_enabled'] = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_ENABLED);
            if ($row['scaling_enabled'] == 1) {
                $row['min_count'] = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES);
                $row['max_count'] = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES);
                $scalingManager = new Scalr_Scaling_Manager($DBFarmRole);
                $scaling_algos = [];
                foreach ($scalingManager->getFarmRoleMetrics() as $farmRoleMetric)
                    $scaling_algos[] = $farmRoleMetric->getMetric()->name;

                $row['scaling_algos'] = implode(', ', $scaling_algos);
            }

            $row['farmOwnerIdPerm'] = $DBFarmRole->GetFarmObject()->createdByUserId == $this->user->getId();
            $row['farmTeamIdPerm'] = $DBFarmRole->GetFarmObject()->teamId ? $this->user->isInTeam($DBFarmRole->GetFarmObject()->teamId) : false;
        }

        $this->response->data($response);
    }

    public function replaceRoleAction()
    {
        $dbFarmRole = DBFarmRole::LoadByID($this->getParam(self::CALL_PARAM_NAME));
        $this->user->getPermissions()->validate($dbFarmRole);
        $this->request->restrictFarmAccess($this->dbFarm, Acl::PERM_FARMS_MANAGE);

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
        $this->request->restrictFarmAccess($this->dbFarm, Acl::PERM_FARMS_MANAGE);

        $newRole = DBRole::loadById($this->request->getParam('roleId'));
        $this->checkPermissions($newRole->__getNewRoleObject());

        if (!empty(($envs = $newRole->__getNewRoleObject()->getAllowedEnvironments()))) {
            if (!in_array($this->getEnvironmentId(), $envs)) {
                throw new Exception("You don't have access to this role");
            }
        }
        //TODO: Add validation of cloud/location/os_family and behavior

        $oldName = $dbFarmRole->GetRoleObject()->name;

        $dbFarmRole->RoleID = $newRole->id;
        $dbFarmRole->Save();

        \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->warn(new FarmLogMessage(
            $dbFarmRole->FarmID,
            sprintf("Role '%s' was upgraded to role '%s'",
                $oldName,
                $newRole->name
            )
        ));

        $image = $newRole->__getNewRoleObject()->getImage($dbFarmRole->Platform, $dbFarmRole->CloudLocation)->getImage();

        $this->response->success("Role successfully replaced.");
        $this->response->data(array(
            'role' => array(
                'role_id'       => $newRole->id,
                'name'          => $newRole->name,
                'os'            => $newRole->getOs()->name,
                'osId'          => $newRole->getOs()->id,
                'generation'    => $newRole->generation,
                'image'         => [
                    'id' => $image->id,
                    'type' => $image->type,
                    'architecture' => $image->architecture
                ],
                'behaviors'     => join(",", $newRole->getBehaviors())
            )
        ));
    }
}
