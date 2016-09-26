<?php

use Scalr\Acl\Acl;
use Scalr\Farm\FarmLease;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Iterator\SharedProjectsFilterIterator;
use Scalr\Stats\CostAnalytics\Entity\QuarterlyBudgetEntity;
use Scalr\Stats\CostAnalytics\Quarters;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Model\Entity;
use Scalr\DataType\ScopeInterface;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Utils;

class Scalr_UI_Controller_Farms extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'farmId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function extendedInfoAction()
    {
        if (!$this->getParam('farmId'))
            throw new Exception(_('Server not found'));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);

        $tz = $dbFarm->GetSetting(Entity\FarmSetting::TIMEZONE);

        $form = array(
            array(
                'xtype' => 'container',
                'layout' => array(
                    'type' => 'hbox',
                    'align' => 'stretch'
                ),
                'cls' => 'x-fieldset-separator-bottom',
                'items' => array(
                    array(
                        'xtype' => 'fieldset',
                        'title' => 'General',
                        'flex' => 1,
                        'cls' => 'x-fieldset-separator-none',
                        'defaults' => array(
                            'labelWidth' => 130
                        ),
                        'items' => array(
                            array(
                                'xtype' => 'displayfield',
                                'fieldLabel' => 'ID',
                                'value' => $dbFarm->ID
                            ),
                            array(
                                'xtype' => 'displayfield',
                                'fieldLabel' => 'Name',
                                'value' => $dbFarm->Name
                            ),
                            array(
                                'xtype' => 'displayfield',
                                'fieldLabel' => 'Hash',
                                'value' => $dbFarm->Hash
                            ),
                            array(
                                'xtype' => 'displayfield',
                                'fieldLabel' => 'Timezone',
                                'value' => ($tz) ? $tz : date_default_timezone_get()
                            )
                        )
                    )
                )
            )
        );

        $repo = $dbFarm->GetSetting(Entity\FarmSetting::SZR_UPD_REPOSITORY);
        if (!$repo)
            $repo = Scalr::config('scalr.scalarizr_update.default_repo');

        $schedule = $dbFarm->GetSetting(Entity\FarmSetting::SZR_UPD_SCHEDULE);
        if (!$schedule)
            $schedule = "* * *";

        $sChunks = explode(" ", $schedule);

        $itm = array(
            'xtype' => 'fieldset',
            'title' => 'Scalr agent update settings',
            'flex' => 1,
            'cls' => 'x-fieldset-separator-left',
            'defaults' => array(
                'labelWidth' => 150
            ),
            'items' => array(
                array(
                    'xtype' => 'displayfield',
                    'fieldLabel' => 'Repository',
                    'value' => Scalr::isHostedScalr() ? Utils::getScalarizrUpdateRepoTitle($repo) : $repo
                ),
                array(
                    'xtype' => 'fieldcontainer',
                    'fieldLabel' => 'Schedule (UTC time)',
                    'layout' => 'hbox',
                    'defaults' => array(
                        'margin' => '0 6 0 0'
                    ),
                    'items' => array(
                        array(
                            'xtype' => 'textfield',
                            'hideLabel' => true,
                            'readOnly' => true,
                            'width' => 50,
                            'value' => $sChunks[0],
                            'name' => 'hh'
                        ), array(
                            'xtype' => 'textfield',
                            'hideLabel' => true,
                            'readOnly' => true,
                            'value' => $sChunks[1],
                            'width' => 50,
                            'name' => 'dd'
                        ), array(
                            'xtype' => 'textfield',
                            'hideLabel' => true,
                            'readOnly' => true,
                            'width' => 50,
                            'value' => $sChunks[2],
                            'name' => 'dw',
                        ), array(
                            'xtype' => 'displayinfofield',
                            'info' => '
*&nbsp;&nbsp;&nbsp;*&nbsp;&nbsp;&nbsp;*<br>
─&nbsp;&nbsp;&nbsp;─&nbsp;&nbsp;&nbsp;─<br>
│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>
│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;│<br>
│&nbsp;&nbsp;&nbsp;│&nbsp;&nbsp;&nbsp;└───── day of week (0 - 6) (0 is Sunday)<br>
│&nbsp;&nbsp;&nbsp;└─────── day of month (1 - 31)<br>
└───────── hour (0 - 23)<br>'
                        )
                    )
                )
            )
        );

        $form[0]['items'][] = $itm;

        $b = $this->db->GetAll("SELECT DISTINCT(behavior) FROM farm_roles
            INNER JOIN role_behaviors ON role_behaviors.role_id = farm_roles.role_id WHERE farmid=?", array(
            $this->getParam('farmId')
        ));
        $behaviors = array();
        foreach ($b as $behavior)
            $behaviors[] = $behavior['behavior'];

        //TODO get rid of code duplication here
        $haveMysqlRole = in_array(ROLE_BEHAVIORS::MYSQL, $behaviors);

        if (!$haveMysqlRole)
            $haveMysql2Role = in_array(ROLE_BEHAVIORS::MYSQL2, $behaviors);

        if (!$haveMysql2Role && !$haveMysqlRole)
            $havePerconaRole = in_array(ROLE_BEHAVIORS::PERCONA, $behaviors);

        $havePgRole = in_array(ROLE_BEHAVIORS::POSTGRESQL, $behaviors);

        $haveRedisRole = in_array(ROLE_BEHAVIORS::REDIS, $behaviors);

        $haveCFController = in_array(ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER, $behaviors);

        $type = array();

        if ($havePgRole)
            $type['postgresql'] = 'PostgreSQL';

        if ($haveRedisRole)
            $type['redis'] = 'Redis';


        if ($haveMysqlRole)
            $type['mysql'] = 'MySQL';

        if ($haveMysql2Role)
            $type['mysql2'] = 'MySQL 5.5';

        if ($havePerconaRole)
            $type['percona'] = 'Percona Server';

        foreach ($type as $dbMsr => $name) {
            $it = array(
                array(
                    'xtype' => 'displayfield',
                    'anchor' => '100%',
                    'cls' => 'x-form-field-info',
                    'value' => 'Public - To connect to the service from the Internet<br / >Private - To connect to the service from another instance'
                ),
                array(
                    'xtype' => 'displayfield',
                    'fieldLabel' => 'Writes endpoint (Public)',
                    'value' => "ext.master.{$dbMsr}.{$dbFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name')
                ),
                array(
                    'xtype' => 'displayfield',
                    'fieldLabel' => 'Reads endpoint (Public)',
                    'value' => "ext.slave.{$dbMsr}.{$dbFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name')
                ),
                array(
                    'xtype' => 'displayfield',
                    'fieldLabel' => 'Writes endpoint (Private)',
                    'value' => "int.master.{$dbMsr}.{$dbFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name')
                ),
                array(
                    'xtype' => 'displayfield',
                    'fieldLabel' => 'Reads endpoint (Private)',
                    'value' => "int.slave.{$dbMsr}.{$dbFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name')
                )
            );

            $form[] = array(
                'xtype' => 'fieldset',
                'title' => "{$name} DNS endpoints",
                'defaults' => array(
                    'labelWidth' => 220
                ),
                'items' => $it
            );
        }

        if ($haveCFController) {
            $it = array(
                array(
                    'xtype' => 'displayfield',
                    'fieldLabel' => 'VMC target endpoint',
                    'value' => "api.ext.cloudfoundry.{$dbFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name')
                )
            );

            $form[] = array(
                    'xtype' => 'fieldset',
                    'title' => "CloudFoundry connection information",
                    'defaults' => array(
                        'labelWidth' => 220
                    ),
                    'items' => $it
            );
        }

        $governance = new Scalr_Governance($this->getEnvironmentId());
        if ($governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE) && $dbFarm->GetSetting(Entity\FarmSetting::LEASE_STATUS) && $dbFarm->Status == FARM_STATUS::RUNNING) {
            $terminateDate = new DateTime($dbFarm->GetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE));
            $localeTerminateDate = Scalr_Util_DateTime::convertDateTime($terminateDate, $tz, 'M j, Y');
            $config = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE, null);

            $standardExtend = $config['leaseExtension'] == 'allow' &&
                ($terminateDate->diff(new DateTime())->days < $config['defaultLifePeriod']) &&
                ($dbFarm->GetSetting(Entity\FarmSetting::LEASE_EXTEND_CNT) < $config['leaseExtensionStandardNumber']);

            $standardExtendRemain = $config['leaseExtensionStandardNumber'] - $dbFarm->GetSetting(Entity\FarmSetting::LEASE_EXTEND_CNT);

            if (
                $config['leaseExtension'] == 'allow' &&
                ($terminateDate->diff(new DateTime())->days >= $config['defaultLifePeriod']) &&
                ($dbFarm->GetSetting(Entity\FarmSetting::LEASE_EXTEND_CNT) < $config['leaseExtensionStandardNumber'])
            ) {
                $standardExtendNextDate = new DateTime($dbFarm->GetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE));
                $standardExtendNextDate->sub(new DateInterval('P' . intval($config['defaultLifePeriod']) . 'D'));

                $standardExtendRemain .= ' (Next will be available on ' . Scalr_Util_DateTime::convertDateTime($standardExtendNextDate, $tz, 'M j, Y') . ')';
            }

            $nonStandardExtend = $config['leaseExtension'] == 'allow' &&
                ((bool) $config['leaseExtensionNonStandard']);

            $lease = new FarmLease($dbFarm);
            $last = $lease->getLastRequest();
            $extendInProgress = NULL;
            $extendLastError = NULL;
            if ($last) {
                if ($last['status'] == FarmLease::STATUS_PENDING) {
                    $standardExtend = false;
                    $nonStandardExtend = false;

                    $extendInProgress = 'Last request was sent at ' . Scalr_Util_DateTime::convertDateTime($last['request_time'], $tz, 'M j, Y');
                    if ($last['request_user_email'])
                        $extendInProgress .= ' by ' . $last['request_user_email'];
                } else if ($last['status'] == FarmLease::STATUS_DECLINE) {
                    $extendLastError = sprintf('Last request was declined by reason "%s"', $last['answer_comment']);
                }
            }

            $form[] = array(
                'xtype' => 'fieldset',
                'title' => "Lease information",
                'defaults' => array(
                    'labelWidth' => 220
                ),
                'itemId' => 'lease',
                'params' => array(
                    'farmLaunchPermission' => $this->request->hasPermissions($dbFarm->__getNewFarmObject(), Acl::PERM_FARMS_LAUNCH_TERMINATE),
                    'standardExtend' => $standardExtend,
                    'standardLifePeriod' => $config['defaultLifePeriod'],
                    'standardExtendRemain' => $standardExtendRemain,
                    'nonStandardExtend' => $nonStandardExtend,
                    'nonStandardExtendInProgress' => $extendInProgress,
                    'nonStandardExtendLastError' => $extendLastError,
                    'terminateDate' => $terminateDate->format('Y-m-d'),
                    'localeTerminateDate' => $localeTerminateDate
                )
            );
        }

        $moduleParams = array(
            'id' => $dbFarm->ID,
            'name' => $dbFarm->Name,
            'info' => $form
        );

        if ($this->getContainer()->analytics->enabled && $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)) {
            $farmCostData = $this->getFarmCostData($dbFarm->ID);
            $moduleParams['analytics'] = $farmCostData['analytics'];

            $moduleParams['projectId'] = $farmCostData['projectId'];
            $moduleParams['analytics']['farmCostMetering'] = $farmCostData['farmCostMetering'];

            $c = self::loadController('Builder', 'Scalr_UI_Controller_Farms');
            $moduleParams['roles'] = [];
            foreach ($c->getFarm2($dbFarm->ID)['roles'] as $role) {
                $moduleParams['roles'][] = [
                    'platform' => $role['platform'],
                    'alias' => $role['alias'],
                    'running_servers' => $role['running_servers'],
                    'suspended_servers' => $role['suspended_servers'],
                    'hourly_rate' => $role['hourly_rate'],
                    Entity\FarmRoleSetting::SCALING_ENABLED => $role['settings'][Entity\FarmRoleSetting::SCALING_ENABLED],
                    Entity\FarmRoleSetting::SCALING_MIN_INSTANCES => $role['settings'][Entity\FarmRoleSetting::SCALING_MIN_INSTANCES],
                    Entity\FarmRoleSetting::SCALING_MAX_INSTANCES => $role['settings'][Entity\FarmRoleSetting::SCALING_MAX_INSTANCES],
                    Scalr_Role_Behavior::ROLE_BASE_CONSIDER_SUSPENDED => $role['settings'][Scalr_Role_Behavior::ROLE_BASE_CONSIDER_SUSPENDED]
                ];
            }

        }
        $this->response->page('ui/farms/extendedinfo.js', $moduleParams,
            array('ui/farms/builder/costmetering.js')/*, array('ui/analytics/analytics.css')*/);
    }

    public function xLeaseExtendAction()
    {
        if (!$this->getParam('farmId'))
            throw new Exception(_('Server not found'));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
        $this->request->checkPermissions($dbFarm->__getNewFarmObject(), Acl::PERM_FARMS_LAUNCH_TERMINATE);

        $governance = new Scalr_Governance($this->getEnvironmentId());
        if (! ($governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE) && $dbFarm->GetSetting(Entity\FarmSetting::LEASE_STATUS) && $dbFarm->Status == FARM_STATUS::RUNNING))
            throw new Scalr_Exception_Core('You can\'t manage lease for this farm');

        $config = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE, null);
        $terminateDate = new DateTime($dbFarm->GetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE));

        if ($config['leaseExtension'] != 'allow')
            throw new Scalr_Exception_Core('You can\'t extend lease for this farm');

        if ($this->getParam('extend') == 'standard') {
            $standardExtend = ($terminateDate->diff(new DateTime())->days < $config['defaultLifePeriod']) &&
                ($dbFarm->GetSetting(Entity\FarmSetting::LEASE_EXTEND_CNT) < $config['leaseExtensionStandardNumber']);

            if ($standardExtend) {
                $terminateDate->add(new DateInterval('P' . intval($config['defaultLifePeriod']) . 'D'));

                $dbFarm->SetSetting(Entity\FarmSetting::LEASE_EXTEND_CNT, $dbFarm->GetSetting(Entity\FarmSetting::LEASE_EXTEND_CNT) + 1);
                $dbFarm->SetSetting(Entity\FarmSetting::LEASE_TERMINATE_DATE, $terminateDate->format('Y-m-d H:i:s'));
                $dbFarm->SetSetting(Entity\FarmSetting::LEASE_NOTIFICATION_SEND, null);

                Entity\SettingEntity::increase(Entity\SettingEntity::LEASE_STANDARD_REQUEST);

                $this->response->success('Farm expiration date was changed');
            } else {
                $this->response->failure('Limit of changes was reached');
            }
        } else if ($this->getParam('extend') == 'non-standard') {
            $lease = new FarmLease($dbFarm);
            $comment = $this->getParam('comment');
            $last = $lease->getLastRequest();

            if ($last && $last['status'] == FarmLease::STATUS_PENDING) {
                $this->response->failure('You\'ve already made expiration request. Before make another one, wait for answer.');
            } else {
                if ($this->getParam('by') == 'days' && intval($this->getParam('byDays')) > 0) {
                    $lease->addRequest(intval($this->getParam('byDays')), $comment, $this->user->getId());

                } else if ($this->getParam('by') == 'date' && strtotime($this->getParam('byDate'))) {
                    $dt = new DateTime($this->getParam('byDate'));
                    $lease->addRequest($terminateDate->diff($dt)->days, $comment, $this->user->getId());

                } else if ($this->getParam('by') == 'forever') {
                    $lease->addRequest(0, $comment, $this->user->getId());

                } else {
                    throw new Scalr_Exception_Core('Invalid period format');
                }

                Entity\SettingEntity::increase(Entity\SettingEntity::LEASE_NOT_STANDARD_REQUEST);

                $mailer = Scalr::getContainer()->mailer;

                if ($this->getContainer()->config('scalr.auth_mode') == 'ldap') {
                    $owner = $this->user->getAccount()->getOwner();
                    if ($owner->getSetting(Scalr_Account_User::SETTING_LDAP_EMAIL))
                        $mailer->addTo($owner->getSetting(Scalr_Account_User::SETTING_LDAP_EMAIL));
                    else
                        $mailer->addTo($owner->getEmail());
                } else {
                    $mailer->addTo($this->user->getAccount()->getOwner()->getEmail());
                }

                if ($config['leaseExtensionNonStandardNotifyEmails']) {
                    $emails = explode(',', $config['leaseExtensionNonStandardNotifyEmails']);
                    foreach ($emails as $email) {
                        $email = trim($email);
                        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $mailer->addTo($email);
                        }
                    }
                }

                $mailer->sendTemplate(
                    SCALR_TEMPLATES_PATH . '/emails/farm_lease_non_standard_request.eml',
                    array(
                        '{{farm_name}}' => $dbFarm->Name,
                        '{{user_name}}' => $this->user->getEmail(),
                        '{{comment}}' => $comment,
                        '{{envName}}' => $dbFarm->GetEnvironmentObject()->name,
                        '{{envId}}' => $dbFarm->GetEnvironmentObject()->id
                    )
                );

                $this->response->success('Farm expiration request was sent');
            }
        } else if ($this->getParam('extend') == 'cancel') {
            $lease = new FarmLease($dbFarm);
            if ($lease->cancelLastRequest())
                $this->response->success('Non-standard extend was cancelled');
            else
                $this->response->failure('Last request wasn\'t found');
        } else {
            $this->response->failure('Invalid extend type');
        }
    }

    public function getList(array $filterArgs = array())
    {
        $retval = array();

        $sql = "SELECT name, id FROM farms f WHERE env_id = ? AND " . $this->request->getFarmSqlQuery();
        $args = array($this->getEnvironmentId());
        foreach ((array)$filterArgs as $k => $v) {
            if (!empty($v) && is_array($v)) {
                foreach ($v as $vv) {
                    array_push($args, $vv);
                }
                $sql .= " AND `{$k}` IN (". join(",", array_fill(0, count($v), "?")) . ")";
            } else {
                $sql .= " AND `{$k}`=?";
                array_push($args, $v);
            }
        }

        $sql .= " ORDER BY name, id";

        $s = $this->db->execute($sql, $args);
        while ($farm = $s->fetchRow()) {
            $retval[] = $farm;
        }

        return $retval;
    }

    /**
     * @param array $values (farmId, farmRoleId, serverId)
     * @param array|string $options
     *      'addAll' - add "On all *" option to roles and servers
     *      'addAllFarms' - add "On all farms" options to farms
     *      'disabledFarmRole' - remove farmRole field
     *      'disabledServer' - remove server field
     *      'addEmpty' - add "*empty*" option (to all lists)
     *      'addEmptyFarm' - add "*empty*" options to farms list only
     *      'requiredFarm', 'requiredFarmRole', 'requiredServer' - add allowBlank = false to field
     * @return array
     */
    // TODO: may be move to separated class
    public function getFarmWidget($values = array(), $options)
    {
        if ($options) {
            if (!is_array($options))
                $options = array($options);

        } else
            $options = array();

        if ($values['serverId']) {
            try {
                $dbServer = DBServer::LoadByID($values['serverId']);
                $this->user->getPermissions()->validate($dbServer);
                $values['farmRoleId'] = $dbServer->farmRoleId;
            } catch (Exception $e) {}
        }

        if ($values['farmRoleId']) {
            try {
                $dbFarmRole = DBFarmRole::LoadByID($values['farmRoleId']);
                $this->user->getPermissions()->validate($dbFarmRole);

                $values['dataServers'] = $this->getFarmWidgetServers($values['farmRoleId'], $options);
                $values['farmId'] = $dbFarmRole->FarmID;

                if (! $values['serverId'])
                    $values['serverId'] = 0;
            } catch (Exception $e) {}
        }

        if ($values['farmId']) {
            $values['dataFarmRoles'] = $this->getFarmWidgetRoles($values['farmId'], $options);
        }

        $values['dataFarms'] = $this->getFarmWidgetFarms($options);
        $values['options'] = $options;

        return $values;
    }

    public function getFarmWidgetFarms($options)
    {
        $perm = in_array('permServers', $options) ? Acl::PERM_FARMS_SERVERS : null;
        $sql = "SELECT id, name FROM farms f WHERE env_id = ? AND " . $this->request->getFarmSqlQuery($perm);
        $args = [$this->getEnvironmentId()];

        $sql .= ' ORDER BY name';
        $farms = $this->db->GetAll($sql, $args);

        if (in_array('addAllFarm', $options))
            array_unshift($farms, array('id' => '0', 'name' => 'On all farms'));

        if (in_array('addEmpty', $options) || in_array('addEmptyFarm', $options))
            array_unshift($farms, array('id' => '', 'name' => ''));

        return $farms;
    }

    public function getFarmWidgetRoles($farmId, $options)
    {
        $dbFarm = DBFarm::LoadById($farmId);
        $this->user->getPermissions()->validate($dbFarm);
        $dataFarmRoles = array();
        $behaviors = array();

        foreach($options as $key => $value) {
            $matches = explode('_', $value);
            if ($matches[0] == 'behavior' && $matches[1])
                $behaviors[] = $matches[1];
        }

        foreach($this->db->GetAll("SELECT id, platform, role_id, alias AS name FROM farm_roles WHERE farmid = ?", array($dbFarm->ID)) as $farmRole) {
            try {
                $dbRole = DBRole::loadById($farmRole['role_id']);
                $farmRole['isScalarized'] = $dbRole->isScalarized;
                if (!empty($behaviors)) {
                    $bFilter = false;
                    foreach($behaviors as $behavior) {
                        if ($dbRole->hasBehavior($behavior)) {
                            $bFilter = true;
                            break;
                        }
                    }

                    if (!$bFilter)
                        continue;
                }

            } catch (Exception $e) {
                $farmRole['isScalarized'] = 1;
                $farmRole['name'] = '*removed*';
            }

            array_push($dataFarmRoles, $farmRole);
        }

        if (count($dataFarmRoles) && in_array('addAll', $options))
            array_unshift($dataFarmRoles, array('id' => '0', 'name' => 'On all roles'));

        if (in_array('addEmpty', $options))
            array_unshift($dataFarmRoles, array('id' => '', 'name' => ''));

        if (!empty($dataFarmRoles))
            return $dataFarmRoles;
        else
            return null;
    }
    //todo: restrictAccess check?
    public function xGetFarmWidgetRolesAction()
    {
        $this->response->data(array(
            'dataFarmRoles' => $this->getFarmWidgetRoles($this->getParam('farmId'), explode(',', $this->getParam('options')))
        ));
    }

    public function getFarmWidgetServers($farmRoleId, $options)
    {
        $servers = array();
        $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
        $this->user->getPermissions()->validate($dbFarmRole);

        foreach ($dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING)) as $value) {
            array_push($servers, array('id' => $value->serverId, 'name' => $value->getNameByConvention()));
        }

        if (count($servers) && in_array('addAll', $options)) {
            array_unshift($servers, array('id' => 0, 'name' => 'All instances of this Farm Role'));
        }

        if (in_array('addEmpty', $options))
            array_unshift($servers, array('id' => '', 'name' => ''));

        if (!empty($servers))
            return $servers;
        else
            return null;
    }
    //todo: restrictAccess check?
    public function xGetFarmWidgetServersAction()
    {
        $this->response->data(array(
            'dataServers' => $this->getFarmWidgetServers($this->getParam('farmRoleId'), explode(',', $this->getParam('options')))
        ));
    }

    public function viewAction()
    {
        $this->request->restrictAccess([Acl::RESOURCE_FARMS, Acl::RESOURCE_TEAM_FARMS, Acl::RESOURCE_OWN_FARMS]);
        $governance = new Scalr_Governance($this->getEnvironmentId());

        $this->response->page('ui/farms/view.js', array('leaseEnabled' => $governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE)));
    }

    public function dnszonesAction()
    {
        $this->request->setParams(array('farmId' => $this->getParam('farmId')));
        self::loadController('Dnszones')->viewAction();
    }

    public function vhostsAction()
    {
        $this->request->setParams(array('farmId' => $this->getParam('farmId')));
        self::loadController('Vhosts', 'Scalr_UI_Controller_Services_Apache')->viewAction();
    }

    public function serversAction()
    {
        $this->request->setParams(array('farmId' => $this->getParam('farmId')));
        self::loadController('Servers')->viewAction();
    }

    /**
     * @param   int     $farmId
     */
    public function xCloneAction($farmId)
    {
        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);
        $this->request->checkPermissions($dbFarm->__getNewFarmObject(), Acl::PERM_FARMS_CLONE);

        $newDbFarm = $dbFarm->cloneFarm(null, $this->user, $this->getEnvironmentId());

        $this->response->success("Farm successfully cloned. New farm: '{$newDbFarm->Name}'");
    }

    /**
     * @param   int     $farmId
     * @param   string  $comment
     * @param   string  $restrict
     * @throws  Exception
     */
    public function xLockAction($farmId, $comment, $restrict = '')
    {
        if (!$comment) {
            throw new Exception('Comment is required');
        }

        if (!in_array($restrict, ['', 'team', 'owner'])) {
            throw new Exception('Restrict should be owner, team or empty string');
        }

        /* @var $farm Entity\Farm */
        if (!$farmId || !($farm = Entity\Farm::findPk($farmId))) {
            throw new Exception('Farm not found');
        }

        $this->request->checkPermissions($farm, Acl::PERM_FARMS_UPDATE);
        $farm->checkLocked();
        $farm->lock($this->getUser(), $comment, $restrict);

        $this->response->success('Farm successfully locked');
    }

    /**
     * @param   int     $farmId
     * @throws  Exception
     */
    public function xUnlockAction($farmId)
    {
        /* @var $farm Entity\Farm */
        if (!$farmId || !($farm = Entity\Farm::findPk($farmId))) {
            throw new Exception('Farm not found');
        }

        $this->request->checkPermissions($farm, Acl::PERM_FARMS_UPDATE);

        if ($farm->settings[Entity\FarmSetting::LOCK]) {
            $restrict = $farm->settings[Entity\FarmSetting::LOCK_RESTRICT];
            if ($restrict && !$this->getUser()->isAccountOwner()) {
                if ($restrict == 'owner') {
                    if ($farm->ownerId != $this->getUser()->id) {
                        throw new Exception('You can\'t unlock this Farm. Only the Farm Owner or an Account Owner may do so.');
                    }
                }

                if ($restrict == 'team') {
                    if (!$farm->hasUserTeamOwnership($this->getUser())) {
                        throw new Exception("You can't unlock this Farm. Only the members of the Farm's Team or an Account Owner may do so.");
                    }
                }
            }

            $farm->unlock($this->getUser());
            $this->response->success('Farm successfully unlocked');
        } else {
            $this->response->failure('Farm isn\'t locked');
        }
    }

    /**
     * @param   int     $farmId
     * @param   string  $deleteDNSZones
     * @param   string  $deleteCloudObjects
     * @param   string  $unTermOnFail
     * @param   string  $forceTerminate
     */
    public function xTerminateAction($farmId, $deleteDNSZones = '', $deleteCloudObjects = '', $unTermOnFail = '', $forceTerminate = '')
    {
        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);
        $this->request->checkPermissions($dbFarm->__getNewFarmObject(), Acl::PERM_FARMS_LAUNCH_TERMINATE);
        $dbFarm->isLocked();

        $removeZoneFromDNS = ($deleteDNSZones == 'on') ? 1 : 0;
        $keepCloudObjects = ($deleteCloudObjects == 'on') ? 0 : 1;
        $termOnFail = ($unTermOnFail == 'on') ? 0 : 1;
        $forceTerminate = ($forceTerminate == 'on') ? 1 : 0;

        Scalr::FireEvent($farmId, new FarmTerminatedEvent(
            $removeZoneFromDNS,
            $keepCloudObjects,
            $termOnFail,
            $keepCloudObjects,
            $forceTerminate,
            $this->user->id
        ));

        $this->response->success('Farm successfully terminated. Instances termination can take a few minutes.');
    }

    /**
     * @param   int     $farmId
     */
    public function xGetTerminationDetailsAction($farmId)
    {
        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);
        $this->request->checkPermissions($dbFarm->__getNewFarmObject(), Acl::PERM_FARMS_LAUNCH_TERMINATE);
        $dbFarm->isLocked();

        $outdatedFarmRoles = $this->db->GetAll("SELECT id FROM farm_roles WHERE farmid=?",
            array($dbFarm->ID)
        );
        $data = array();
        $isMongoDbClusterRunning = false;
        $isMysql = false;
        $isRabbitMQ = false;
        foreach ($outdatedFarmRoles as $farmRole) {
            $dbFarmRole = DBFarmRole::LoadByID($farmRole['id']);

            if (!$isMongoDbClusterRunning) {
                $isMongoDbClusterRunning = $dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MONGODB) && ($dbFarmRole->GetSetting(Scalr_Role_Behavior_MongoDB::ROLE_CLUSTER_STATUS) != Scalr_Role_Behavior_MongoDB::STATUS_TERMINATED);
            }

            if (!$isMysql) {
                $isMysql = $dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL);
            }

            if (!$isRabbitMQ) {
                $isRabbitMQ = $dbFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::RABBITMQ);
            }

            $row = array(
                'dtLastSync'      => (strtotime($dbFarmRole->dtLastSync) ?
                                      Scalr_Util_DateTime::getFuzzyTime(strtotime($dbFarmRole->dtLastSync), false) :
                                      "Never"),
                'name'            => $dbFarmRole->GetRoleObject()->name,
                'id'              => $dbFarmRole->ID,
                'isBundleRunning' => $this->db->GetOne("
                    SELECT id FROM bundle_tasks
                    WHERE status NOT IN ('success','failed')
                    AND role_id=?
                    AND farm_id IN (SELECT id FROM farms WHERE client_id=?)
                 ", array(
                    $dbFarmRole->RoleID,
                    $dbFarm->ClientID
                 ))
            );

            foreach ($dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING)) as $dbServer) {
                $row['servers'][] = array(
                    'server_id'	=> $dbServer->serverId,
                    'remoteIp'	=> $dbServer->remoteIp
                );
            }

            $data[] = $row;
        }

        $this->response->data(array(
            'roles' => $data,
            'isMongoDbClusterRunning' => $isMongoDbClusterRunning,
            'isMysqlRunning' => $isMysql,
            'isRabbitMQ' => $isRabbitMQ,
            'farmId' => $dbFarm->ID,
            'farmName' => $dbFarm->Name
        ));
    }

    /**
     * @param    int     $farmId
     * @throws   Exception
     */
    public function xLaunchAction($farmId)
    {
        /* @var $farm Entity\Farm */
        if (!$farmId || !($farm = Entity\Farm::findPk($farmId))) {
            throw new Exception('Farm not found');
        }

        $this->request->checkPermissions($farm, Acl::PERM_FARMS_LAUNCH_TERMINATE);
        $farm->checkLocked();

        Scalr::FireEvent($farm->id, new FarmLaunchedEvent(true, $this->getUser()->id));

        $this->response->success('Farm successfully launched');
    }

    /**
     * @param   int     $farmId
     * @throws  Exception
     */
    public function xRemoveAction($farmId)
    {
        /* @var $farm Entity\Farm */
        if (!$farmId || !($farm = Entity\Farm::findPk($farmId))) {
            throw new Exception('Farm not found');
        }

        $this->request->checkPermissions($farm, Acl::PERM_FARMS_DELETE);
        $farm->checkLocked();

        if ($farm->status != Entity\Farm::STATUS_TERMINATED) {
            throw new Exception("Cannot delete a running farm. Please terminate a farm before deleting it.");
        }

        $servers = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id=? AND status!=?", array($farm->id, SERVER_STATUS::TERMINATED));
        if ($servers != 0) {
            throw new Exception(sprintf(_("Cannot delete a running farm. %s server%s still running on this farm."), $servers, $servers > 1 ? 's are' : ' is'));
        }

        $this->db->BeginTrans();

        try {
            foreach ($this->db->GetAll("SELECT * FROM farm_roles WHERE farmid = ?", array($farm->id)) as $value) {
                $this->db->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type IN(?,?)", array(
                    $value['id'],
                    Scalr_SchedulerTask::TARGET_ROLE,
                    Scalr_SchedulerTask::TARGET_INSTANCE
                ));
            }

            $this->db->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type = ?", array(
                $farm->id,
                Scalr_SchedulerTask::TARGET_FARM
            ));

            //We should not remove farm_settings because it is used in stats!

            $this->db->Execute("DELETE FROM farms WHERE id=?", array($farm->id));
            $this->db->Execute("DELETE FROM farm_roles WHERE farmid=?", array($farm->id));
            $this->db->Execute("DELETE FROM logentries WHERE farmid=?", array($farm->id));
            $this->db->Execute("DELETE FROM elastic_ips WHERE farmid=?", array($farm->id));
            $this->db->Execute("DELETE FROM events WHERE farmid=?", array($farm->id));
            $this->db->Execute("DELETE FROM ec2_ebs WHERE farm_id=?", array($farm->id));

            $this->db->Execute("DELETE FROM farm_role_scripts WHERE farmid=?", array($farm->id));
            $this->db->Execute("DELETE FROM farm_lease_requests WHERE farm_id=?", array($farm->id));


            //TODO: Remove servers
            $servers = $this->db->Execute("SELECT server_id FROM servers WHERE farm_id=?", array($farm->id));
            while ($server = $servers->FetchRow()) {
                $dbServer = DBServer::LoadByID($server['server_id']);
                $dbServer->Remove();
            }

            $this->db->Execute("UPDATE dns_zones SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($farm->id));
            $this->db->Execute("UPDATE apache_vhosts SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($farm->id));
        } catch(Exception $e) {
            $this->db->RollbackTrans();
            throw new Exception(_("Cannot delete farm at the moment ({$e->getMessage()}). Please try again later."));
        }

        $this->db->CommitTrans();

        $this->db->Execute("DELETE FROM orchestration_log WHERE farmid=?", array($farm->id));

        $this->response->success('Farm successfully removed');
    }

    /**
     * @param   int         $farmId
     * @param   int         $expirePeriod
     * @param   bool        $manageable
     * @param   string      $owner
     * @param   int         $chefServerId
     * @param   string      $projectId
     * @param   int         $status
     * @throws  Exception
     * @throws  Scalr_Exception_Core
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xListFarmsAction($farmId = null, $expirePeriod = null, $manageable = false,
                                     $owner = null, $chefServerId = null, $projectId = null, $status = null)
    {
        $this->request->restrictAccess([Acl::RESOURCE_FARMS, Acl::RESOURCE_TEAM_FARMS, Acl::RESOURCE_OWN_FARMS]);

        $governance = new Scalr_Governance($this->getEnvironmentId());
        $leaseStatus = $governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE);

        $f = new Entity\Farm();

        $sql = "
            SELECT {$f->fields('f')}, au.email AS ownerEmail,
                (SELECT " . Entity\Farm::getUserTeamOwnershipSql($this->getUser()->id) . ") AS farmTeamIdPerm,
                GROUP_CONCAT(DISTINCT at.name SEPARATOR ', ') AS farmTeams,
                GROUP_CONCAT(DISTINCT behavior) AS behaviors,
                (SELECT COUNT(*) FROM farm_roles WHERE farmid = f.id) AS rolesCnt,
                (SELECT COUNT(*) FROM dns_zones WHERE farm_id = f.id) AS zonesCnt,
                (SELECT COUNT(*) FROM server_alerts WHERE farm_id= f.id AND status = ?) AS alertsCnt,
                (SELECT COUNT(*) FROM servers WHERE farm_id = f.id AND status IN (?,?,?,?,?)) AS runningServers,
                (SELECT COUNT(*) FROM servers WHERE farm_id = f.id AND status IN (?,?)) AS suspendedServers,
                (SELECT COUNT(*) FROM servers WHERE farm_id = f.id AND status IN (?,?)) AS nonRunningServers
            FROM {$f->table('f')}
            LEFT JOIN account_users au ON au.id = f.created_by_id
            LEFT JOIN farm_teams ft ON ft.farm_id = {$f->columnId('f')}
            LEFT JOIN account_teams at ON at.id = ft.team_id
            LEFT JOIN farm_roles fr ON fr.farmid = {$f->columnId('f')}
            LEFT JOIN role_behaviors rb ON rb.role_id = fr.role_id
            WHERE {$f->columnEnvId('f')} = ?
            AND :FILTER:
            AND " . $this->request->getFarmSqlQuery($manageable ? Acl::PERM_FARMS_UPDATE : null);

        $args = [
            Entity\Server\Alert::STATUS_FAILED,
            Entity\Server::STATUS_PENDING, Entity\Server::STATUS_INIT, Entity\Server::STATUS_RUNNING, Entity\Server::STATUS_TEMPORARY, Entity\Server::STATUS_RESUMING, // running servers
            Entity\Server::STATUS_SUSPENDED, Entity\Server::STATUS_PENDING_SUSPEND, // suspended servers
            Entity\Server::STATUS_TERMINATED, Entity\Server::STATUS_PENDING_TERMINATE, // non-running servers
            $this->getEnvironmentId()
        ];

        if ($leaseStatus && $expirePeriod) {
            $dt = new DateTime();
            $dt->add(new DateInterval('P' . $expirePeriod . 'D'));

            $sql .= " AND EXISTS (
                        SELECT 1 FROM farm_settings
                        WHERE farm_settings.farmid = f.id
                        AND farm_settings.name = ?
                        AND farm_settings.value != ''
                        AND farm_settings.value < ?
                    ) AND f.status = ?";
            $args[] = Entity\FarmSetting::LEASE_TERMINATE_DATE;
            $args[] = $dt->format('Y-m-d H:i:s');
            $args[] = Entity\Farm::STATUS_RUNNING;
        }

        if ($farmId) {
            $sql .= ' AND f.id = ?';
            $args[] = $farmId;
        }

        if ($owner) {
            if ($owner == 'me') {
                $sql .= " AND {$f->columnOwnerId('f')} = ?";
                $args[] = $this->getUser()->id;
            } else if ($owner == 'team') {
                $sql .= " AND " . Entity\Farm::getUserTeamOwnershipSql($this->getUser()->id);
            }
        }

        if ($chefServerId) {
            $sql .= " AND f.id IN (
                SELECT fr.farmid
                FROM farm_roles fr
                INNER JOIN farm_role_settings frs1 ON fr.id = frs1.farm_roleid AND frs1.name = ? AND frs1.value = ?
                INNER JOIN farm_role_settings frs2 ON fr.id = frs2.farm_roleid AND frs2.name = ? AND frs2.value = ?
            )";
            $args[] = \Scalr_Role_Behavior_Chef::ROLE_CHEF_SERVER_ID;
            $args[] = $chefServerId;
            $args[] = \Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP;
            $args[] = 1;
        }

        if ($this->getContainer()->analytics->enabled && $projectId) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM farm_settings
                WHERE farm_settings.farmid = f.id
                AND farm_settings.name = " . $this->db->qstr(Entity\FarmSetting::PROJECT_ID) . "
                AND farm_settings.value = ?) ";
            $args[] = $projectId;
        }

        $sql .= " GROUP BY {$f->columnId('f')}";

        $response = $this->buildResponseFromSql2($sql, array('id', 'name', 'dtadded', 'ownerEmail', 'status'), array('f.name', 'f.id', 'f.comments'), $args);
        foreach ($response["data"] as &$r) {
            $farm = new Entity\Farm();
            $farm->load($r);

            $row = get_object_vars($farm);
            $row['ownerEmail'] = $r['ownerEmail'];
            $row['farmTeams'] = $r['farmTeams'];
            $row['behaviors'] = $r['behaviors'];
            $row['rolesCnt'] = $r['rolesCnt'];
            $row['zonesCnt'] = $r['zonesCnt'];
            $row['alertsCnt'] = $r['alertsCnt'];
            $row['runningServers'] = $r['runningServers'];
            $row['suspendedServers'] = $r['suspendedServers'];
            $row['nonRunningServers'] = $r['nonRunningServers'];

            $row['added'] = Scalr_Util_DateTime::convertTz($farm->added);
            $row['lock'] = $farm->settings[Entity\FarmSetting::LOCK];
            if ($row['lock']) {
                try {
                    $farm->checkLocked();
                } catch (\Scalr\Exception\LockedException $e) {
                    $row['lockComment'] = $e->getMessage();
                }
            }

            if ($leaseStatus && $farm->settings[Entity\FarmSetting::LEASE_STATUS]) {
                $row['lease'] = $farm->settings[Entity\FarmSetting::LEASE_NOTIFICATION_SEND] ? 'Expire' : $farm->settings[Entity\FarmSetting::LEASE_STATUS];

                if ($row['lease'] == 'Expire') {
                    $dt = new DateTime();
                    $td = new DateTime($farm->settings[Entity\FarmSetting::LEASE_TERMINATE_DATE]);
                    $days = 0;
                    $hours = 1;
                    $interval = $dt->diff($td);
                    if ($interval) {
                        $days = $interval->days;
                        $hours = $interval->h ? $interval->h : 1;
                    }

                    $row['leaseMessage'] = sprintf('Your farm lease is about to expire in %d %s, after which this farm will be terminated', $days ? $days : $hours, $days ? ($days > 1 ? 'days' : 'day') : ($hours > 1 ? 'hours' : 'hour'));
                }
            }

            $behaviors = explode(',', $row['behaviors']);
            $row["havemysqlrole"] = in_array(ROLE_BEHAVIORS::MYSQL, $behaviors);
            $row["havemysql2role"] = in_array(ROLE_BEHAVIORS::MYSQL2, $behaviors);
            $row["havepgrole"] = in_array(ROLE_BEHAVIORS::POSTGRESQL, $behaviors);
            $row["haveredisrole"] = in_array(ROLE_BEHAVIORS::REDIS, $behaviors);
            $row["haverabbitmqrole"] = in_array(ROLE_BEHAVIORS::RABBITMQ, $behaviors);
            $row["havemongodbrole"] = in_array(ROLE_BEHAVIORS::MONGODB, $behaviors);
            $row["haveperconarole"] = in_array(ROLE_BEHAVIORS::PERCONA, $behaviors);
            $row["havemariadbrole"] = in_array(ROLE_BEHAVIORS::MARIADB, $behaviors);

            $row['statusTxt'] = Entity\Farm::getStatusName($farm->status);

            if ($row['status'] == Entity\Farm::STATUS_RUNNING) {
                $row['shortcuts'] = [];
                foreach (\Scalr\Model\Entity\ScriptShortcut::find([
                    ['farmId'     => $farm->id],
                    ['farmRoleId' => null]
                ]) as $shortcut) {
                    /* @var $shortcut \Scalr\Model\Entity\ScriptShortcut */
                    $row['shortcuts'][] = array(
                        'id'   => $shortcut->id,
                        'name' => $shortcut->getScriptName()
                    );
                }
            }

            $row['farmOwnerIdPerm'] = $farm->ownerId && $this->getUser()->id == $farm->ownerId;
            $row['farmTeamIdPerm'] = !!$r['farmTeamIdPerm'];
            $r = $row;
        }

        $this->response->data($response);
    }

    public function buildAction()
    {
        $this->designerAction();
    }

    public function editAction()
    {
        $this->designerAction();
    }

    public function calcFarmDesignerHash()
    {
        return $this->response->calculateFilesHash([
            'ui/farms/builder.js',
            'ui/farms/builder/plugins.js',
            'ui/farms/builder/roleslibrary.js',
            'ui/farms/builder/costmetering.js',
            //tabs
            'ui/farms/builder/tabs/dbmsr.js',
            'ui/farms/builder/tabs/cloudfoundry.js',
            'ui/farms/builder/tabs/rabbitmq.js',
            'ui/farms/builder/tabs/mongodb.js',
            'ui/farms/builder/tabs/haproxy.js',
            'ui/farms/builder/tabs/proxy.js',
            'ui/farms/builder/tabs/mysql.js',
            'ui/farms/builder/tabs/gce.js',
            'ui/farms/builder/tabs/openstack.js',
            'ui/farms/builder/tabs/azure.js',
            'ui/farms/builder/tabs/scaling.js',
            'ui/farms/builder/tabs/scripting.js',
            'ui/farms/builder/tabs/advanced.js',
            'ui/farms/builder/tabs/ec2.js',
            'ui/farms/builder/tabs/security.js',
            'ui/farms/builder/tabs/storage.js',
            'ui/farms/builder/tabs/variables.js',
            'ui/farms/builder/tabs/devel.js',
            'ui/farms/builder/tabs/chef.js',
            'ui/farms/builder/tabs/vpcrouter.js',
            'ui/farms/builder/tabs/network.js',
            //deprecated tabs
            'ui/farms/builder/tabs/ebs.js',
            //roleslibrary add role settings
            'ui/farms/builder/roleslibrary/azure.js',
            'ui/farms/builder/roleslibrary/vpc.js',
            'ui/farms/builder/roleslibrary/openstack.js',
            'ui/farms/builder/roleslibrary/cloudstack.js',
            'ui/farms/builder/roleslibrary/mongodb.js',
            'ui/farms/builder/roleslibrary/dbmsr.js',
            'ui/farms/builder/roleslibrary/proxy.js',
            'ui/farms/builder/roleslibrary/haproxy.js',
            'ui/farms/builder/roleslibrary/chef.js',
            'ui/farms/builder/roleslibrary/gce.js',
            //other
            'codemirror/codemirror.js',
            'ui/core/variablefield.js',
            'ui/scripts/scriptfield.js',
            'ui/monitoring/window.js',
            'ui/services/chef/chefsettings.js',
            'ui/security/groups/sgeditor.js',
            'ui/farms/builder/builder.css',
            'codemirror/codemirror.css',
            'ui/scripts/scriptfield.css',
            'ui/analytics/analytics.css'
        ]);
    }

    /**
     * @param   int     $farmId
     * @throws  Exception
     * @throws  Scalr_Exception_InsufficientPermissions
     * @throws  Scalr_UI_Exception_NotFound
     */
    public function designerAction($farmId = null)
    {
        $this->request->restrictFarmDesignerAccess();

        if ($farmId) {
            /* @var $farm Entity\Farm */
            $farm = Entity\Farm::findPk($farmId);
            if ($farm) {
                $this->request->checkPermissions($farm, Acl::PERM_FARMS_UPDATE);
            }
        }

        $platforms = self::loadController('Platforms')->getEnabledPlatforms();
        if (empty($platforms)) {
            throw new Exception('Before building new farm you need to configure environment and setup cloud credentials');
        }

        // all files should be listed in method calcFarmDesignerHash
        $this->response->page(['ui/farms/builder.js', 'ui/farms/builder/plugins.js'], [
            'scalrPageHash' => $this->calcFarmDesignerHash()
        ], array(
            'ui/farms/builder/roleslibrary.js',
            'ui/farms/builder/costmetering.js',
            //tabs
            'ui/farms/builder/tabs/dbmsr.js',
            'ui/farms/builder/tabs/cloudfoundry.js',
            'ui/farms/builder/tabs/rabbitmq.js',
            'ui/farms/builder/tabs/mongodb.js',
            'ui/farms/builder/tabs/haproxy.js',
            'ui/farms/builder/tabs/proxy.js',
            'ui/farms/builder/tabs/mysql.js',
            'ui/farms/builder/tabs/gce.js',
            'ui/farms/builder/tabs/openstack.js',
            'ui/farms/builder/tabs/azure.js',
            'ui/farms/builder/tabs/scaling.js',
            'ui/farms/builder/tabs/scripting.js',
            'ui/farms/builder/tabs/advanced.js',
            'ui/farms/builder/tabs/ec2.js',
            'ui/farms/builder/tabs/security.js',
            'ui/farms/builder/tabs/storage.js',
            'ui/farms/builder/tabs/variables.js',
            'ui/farms/builder/tabs/devel.js',
            'ui/farms/builder/tabs/chef.js',
            'ui/farms/builder/tabs/vpcrouter.js',
            'ui/farms/builder/tabs/network.js',
            //deprecated tabs
            'ui/farms/builder/tabs/ebs.js',
            //roleslibrary add role settings
            'ui/farms/builder/roleslibrary/azure.js',
            'ui/farms/builder/roleslibrary/vpc.js',
            'ui/farms/builder/roleslibrary/openstack.js',
            'ui/farms/builder/roleslibrary/cloudstack.js',
            'ui/farms/builder/roleslibrary/mongodb.js',
            'ui/farms/builder/roleslibrary/dbmsr.js',
            'ui/farms/builder/roleslibrary/proxy.js',
            'ui/farms/builder/roleslibrary/haproxy.js',
            'ui/farms/builder/roleslibrary/chef.js',
            'ui/farms/builder/roleslibrary/gce.js',
            //other
            'codemirror/codemirror.js',
            'ui/core/variablefield.js',
            'ui/scripts/scriptfield.js',
            'ui/monitoring/window.js',
            'ui/services/chef/chefsettings.js',
            'ui/security/groups/sgeditor.js'
        ), array(
            'ui/farms/builder/builder.css',
            'codemirror/codemirror.css',
            'ui/scripts/scriptfield.css',
            'ui/analytics/analytics.css',
        ));
    }

    /**
     * @param int $farmId optional
     * @param int $roleId optional
     * @param string $scalrPageHash optional
     * @param string $scalrPageUiHash optional
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xGetFarmAction($farmId = null, $roleId = null, $scalrPageHash = null, $scalrPageUiHash = null)
    {
        if ($scalrPageHash && $scalrPageHash != $this->calcFarmDesignerHash()) {
            $this->response->data([
                'scalrPageHashMismatch' => true
            ]);
            return;
        }

        if ($scalrPageUiHash && $scalrPageUiHash != $this->response->pageUiHash()) {
            $this->response->data([
                'scalrPageUiHashMismatch' => true
            ]);
            return;
        }

        $moduleParams = array(
            'farmId' => $farmId,
            'roleId' => $roleId,
            'behaviors' => ROLE_BEHAVIORS::GetName(null, true)
        );

        unset($moduleParams['behaviors'][ROLE_BEHAVIORS::CASSANDRA]);
        unset($moduleParams['behaviors'][ROLE_BEHAVIORS::CUSTOM]);
        unset($moduleParams['behaviors'][ROLE_BEHAVIORS::HAPROXY]);

        //platforms list
        $platforms = self::loadController('Platforms')->getEnabledPlatforms();
        if (empty($platforms)) {
            throw new Exception('Before building new farm you need to configure environment and setup cloud credentials');
        }

        $moduleParams['categories'] = self::loadController('Roles')->listRoleCategories(true, true);

        $moduleParams['farmVpcEc2Enabled'] = $this->getEnvironment()->isPlatformEnabled(SERVER_PLATFORMS::EC2);
        if ($moduleParams['farmVpcEc2Enabled']) {
            $moduleParams['farmVpcEc2Locations'] = self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false);
        }

        if ($farmId) {
            $this->request->checkPermissions(DBFarm::LoadByID($farmId)->__getNewFarmObject(), Acl::PERM_FARMS_UPDATE);

            $c = self::loadController('Builder', 'Scalr_UI_Controller_Farms');
            $moduleParams['farm'] = $c->getFarm2($farmId);
        } else {
            $this->request->restrictAccess(Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_CREATE);

            // TODO: remove hack, do better
            $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), ScopeInterface::SCOPE_FARM);
            $moduleParams['farmVariables'] = $vars->getValues();
        }

        $moduleParams['tabs'] = array(
            'vpcrouter', 'dbmsr', 'mongodb', 'mysql', 'scaling', 'network', 'cloudfoundry', 'rabbitmq', 'haproxy', 'proxy',
            'scripting',
            'ec2', 'openstack', 'gce', 'azure', 'security', 'devel', 'storage', 'variables', 'advanced', 'chef'
        );

        //deprecated tabs
        if (\Scalr::config('scalr.ui.show_deprecated_features')) {
            $moduleParams['tabs'][] = 'ebs';
        }
        $conf = $this->getContainer()->config->get('scalr.load_statistics.connections.plotter');
        $moduleParams['tabParams'] = array(
            'farmId'        => $farmId,
            'farmHash'      => $moduleParams['farm'] ? $moduleParams['farm']['farm']['hash'] : '',
            'accountId'     => $this->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
            'remoteAddress' => $this->request->getRemoteAddr(),
            'monitoringHostUrl' => "{$conf['scheme']}://{$conf['host']}:{$conf['port']}",
            'nginx'         => array(
                'server_section' => file_get_contents(APPPATH . '/templates/services/nginx/server_section.tpl'),
                'server_section_ssl' => file_get_contents(APPPATH . '/templates/services/nginx/server_section_ssl.tpl')
            )
        );

        $moduleParams['tabParams']['scalr.instances_connection_policy'] = \Scalr::config('scalr.instances_connection_policy');
        $moduleParams['tabParams']['scalr.scalarizr_update.default_repo'] = \Scalr::config('scalr.scalarizr_update.default_repo');
        
        if (Scalr::isHostedScalr()) {
            $moduleParams['tabParams']['scalr.scalarizr_update.repos'] = [
                'latest' => Utils::getScalarizrUpdateRepoTitle('latest')
            ];
            if ($this->user->getAccount()->priority == 100) {
                $moduleParams['tabParams']['scalr.scalarizr_update.repos']['stable'] = Utils::getScalarizrUpdateRepoTitle('stable');    
            }
        } else {
            $repos = array_keys(\Scalr::config('scalr.scalarizr_update.repos')); 
            $moduleParams['tabParams']['scalr.scalarizr_update.repos'] = array_combine($repos, $repos);
        }
        
        $moduleParams['tabParams']['scalr.scalarizr_update.devel_repos'] = is_array(\Scalr::config('scalr.scalarizr_update.devel_repos')) ? array_keys(\Scalr::config('scalr.scalarizr_update.devel_repos')) : [];
        $moduleParams['tabParams']['scalr.aws.ec2.limits.security_groups_per_instance'] = \Scalr::config('scalr.aws.ec2.limits.security_groups_per_instance');

        $moduleParams['metrics'] = Entity\ScalingMetric::getList($this->getEnvironmentId());
        $moduleParams['timezones_list'] = Scalr_Util_DateTime::getTimezones();
        $moduleParams['timezone_default'] = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);

        if ($moduleParams['farm']['farm']['ownerEditable']) {
            $moduleParams['usersList'] = [];
            foreach (Entity\Account\User::findByAccountId($this->user->getAccountId()) as $user) {
                $moduleParams['usersList'][] = ['id' => $user->id, 'email' => $user->email];
            }
        }

        $defaultFarmRoleSecurityGroups = array('default');
        if (\Scalr::config('scalr.aws.security_group_name')) {
            $defaultFarmRoleSecurityGroups[] = \Scalr::config('scalr.aws.security_group_name');
        }

        $moduleParams['roleDefaultSettings'] = array(
            'base.keep_scripting_logs_time' => \Scalr::config('scalr.system.scripting.default_instance_log_rotation_period'),
            'security_groups.list' => json_encode($defaultFarmRoleSecurityGroups),
            'base.abort_init_on_script_fail' => \Scalr::config('scalr.system.scripting.default_abort_init_on_script_fail') ? 1 : 0,
            'base.disable_firewall_management' => \Scalr::config('scalr.system.default_disable_firewall_management') ? 1 : 0,
        );

        //cost analytics
        if ($this->getContainer()->analytics->enabled && $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)) {
            $farmCostData = $this->getFarmCostData($farmId);
            $moduleParams['analytics'] = $farmCostData['analytics'];

            if ($farmId) {
                $moduleParams['farm']['farm']['projectId'] = $farmCostData['projectId'];
                $moduleParams['analytics']['farmCostMetering'] = $farmCostData['farmCostMetering'];
           }
        }

        $moduleParams['farmLaunchPermission'] = $farmId ? $moduleParams['farm']['farm']['launchPermission'] : $this->request->isAllowed([Acl::RESOURCE_FARMS, Acl::RESOURCE_TEAM_FARMS, Acl::RESOURCE_OWN_FARMS], Acl::PERM_FARMS_LAUNCH_TERMINATE);
        $this->response->data($moduleParams);
    }

    /**
     * @param int $farmId
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xGetOwnerHistoryAction($farmId)
    {
        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);
        $this->request->checkPermissions($dbFarm->__getNewFarmObject(), Acl::PERM_FARMS_UPDATE);

        if ($dbFarm->ownerId == $this->user->getId() || $this->request->hasPermissions($dbFarm->__getNewFarmObject(), Acl::PERM_FARMS_CHANGE_OWNERSHIP)) {
            $data = array_map(function($item) {
                $item['dtTm'] = Scalr_Util_DateTime::convertTz($item['dt']);
                return $item;
            }, Entity\FarmSetting::getOwnerHistory(Entity\Farm::findPk($farmId)));

            $this->response->data(['history' => $data]);
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }

    private function getFarmCostData($farmId)
    {
        $result = [];
        $costCenter = $this->getContainer()->analytics->ccs->get($this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID));

        $currentYear = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y');
        $quarters = new Quarters(SettingEntity::getQuarters());
        $currentQuarter = $quarters->getQuarterForDate(new \DateTime('now', new \DateTimeZone('UTC')));

        $projects = [];

        if ($farmId) {
            $farm = DBFarm::LoadByID($farmId);
            $currentProjectId = $farm->GetSetting(Entity\FarmSetting::PROJECT_ID);
            $currentProject = ProjectEntity::findPk($currentProjectId);
            /* @var $currentProject ProjectEntity */
            if (!empty($currentProject)) {
                $quarterBudget = QuarterlyBudgetEntity::findOne([['year' => $currentYear], ['subjectType' => QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT], ['subjectId' => $currentProject->projectId], ['quarter' => $currentQuarter]]);
                $projects[] = [
                    'projectId' => $currentProject->projectId,
                    'name' => "{$currentProject->name} / {$currentProject->getCostCenter()->name}",
                    'budgetRemain' => (!is_null($quarterBudget) && $quarterBudget->budget > 0)
                        ? max(0, round($quarterBudget->budget - $quarterBudget->cumulativespend))
                        : null,
                ];
            }

            $result['projectId'] = $farm->GetSetting(Entity\FarmSetting::PROJECT_ID);
            $result['farmCostMetering'] = $result['projectId'] ? $this->getContainer()->analytics->usage->getFarmCostMetering($this->user->getAccountId(), $farmId) : null;

        }

        if ($costCenter instanceof CostCentreEntity) {
            $projectsIterator = new SharedProjectsFilterIterator($costCenter->getProjects(), $costCenter->ccId, $this->user, $this->getEnvironment());

            foreach ($projectsIterator as $item) {
                /* @var $item Scalr\Stats\CostAnalytics\Entity\ProjectEntity */
                if (!empty($currentProjectId) && $item->projectId == $currentProjectId) {
                    continue;
                }

                $quarterBudget = QuarterlyBudgetEntity::findOne([['year' => $currentYear], ['subjectType' => QuarterlyBudgetEntity::SUBJECT_TYPE_PROJECT], ['subjectId' => $item->projectId], ['quarter' => $currentQuarter]]);
                $projects[] = array(
                    'projectId'     => $item->projectId,
                    'name'          => "{$item->name} / {$costCenter->name}",
                    'budgetRemain'  => (!is_null($quarterBudget) && $quarterBudget->budget > 0)
                                        ? max(0, round($quarterBudget->budget - $quarterBudget->cumulativespend))
                                        : null,
                );
            }
            $costCentreName = $costCenter->name;
            $isLocked = $costCenter->getProperty(CostCentrePropertyEntity::NAME_LOCKED);
            $accountCcs = AccountCostCenterEntity::findOne([['accountId' => $this->environment->clientId], ['ccId' => $costCenter->ccId]]);

            if ($isLocked || !($accountCcs instanceof AccountCostCenterEntity)) {
                $costCentreLocked = 1;
            } else {
                $costCentreLocked = 0;
            }

        } else {
            $costCentreName = '';
            $costCentreLocked = 0;
        }

        $supportedClouds = $this->getContainer()->analytics->prices->getSupportedClouds();

        $result['analytics'] = array(
            'costCenterName'    => $costCentreName,
            'costCenterLocked'  => $costCentreLocked,
            'projects'          => $projects,
            'unsupportedClouds' => array_values(array_diff($this->environment->getEnabledPlatforms(), $supportedClouds))
        );
        return $result;
    }
}
