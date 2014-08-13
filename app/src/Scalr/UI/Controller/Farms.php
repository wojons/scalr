<?php

use Scalr\Acl\Acl;
use Scalr\Farm\FarmLease;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Iterator\SharedProjectsFilterIterator;

class Scalr_UI_Controller_Farms extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'farmId';

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function xSaveSzrUpdSettingsAction()
    {
        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);

        $schedule = implode(" ", array($this->getParam("hh"), $this->getParam("dd"), $this->getParam("dw")));
        $repo = $this->getParam("szrRepository");

        $oldRepo = $dbFarm->GetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY);
        if ($oldRepo == 'latest' && $repo == 'stable' && $dbFarm->Status == FARM_STATUS::RUNNING)
            throw new Exception("Switching from 'latest' repository to 'stable' is not supported for running farms");

        $dbFarm->SetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY, $repo);
        $dbFarm->SetSetting(DBFarm::SETTING_SZR_UPD_SCHEDULE, $schedule);

        $extendedMsg = false;
        $servers = $dbFarm->GetServersByFilter(array('status' => array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING)));
        foreach ($servers as $dbServer) {
            if (!$dbServer->IsSupported('2.8.0')) {
                try {
                    $port = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_UPDC_PORT);
                    if (!$port)
                        $port = 8008;

                    $updClient = new Scalr_Net_Scalarizr_UpdateClient($dbServer, $port);
                    $updClient->configure($repo, $schedule);
                } catch (Exception $e) {
                    Logger::getLogger('Farm')->error(new FarmLogMessage($dbFarm->ID, sprintf("Unable to update scalarizr update settings on server %s: %s",
                        $dbServer->serverId, $e->getMessage()
                    )));
                    $err = true;
                }
            } else {
                $extendedMsg = true;
            }
        }

        if (!$err) {
            if ($extendedMsg)
                $this->response->success('Scalarizr auto-update settings successfully saved. Running servers will be updated according to schedule.');
            else
                $this->response->success('Scalarizr auto-update settings successfully saved');
        }
        else
            $this->response->warning('Scalarizr auto-update settings successfully saved, but some servers were not updated. Please check "Logs -> System log" for more details.');
    }

    public function extendedInfoAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS);

        if (!$this->getParam('farmId'))
            throw new Exception(_('Server not found'));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);

        $tz = $dbFarm->GetSetting(DBFarm::SETTING_TIMEZONE);

        $form = array(
            array(
                'xtype' => 'fieldset',
                'title' => 'General',
                'labelWidth' => 220,
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
        );

        ///Update settings
        $scalarizrRepos = array_keys(Scalr::config('scalr.scalarizr_update.repos'));

        $repo = $dbFarm->GetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY);
        if (!$repo)
            $repo = Scalr::config('scalr.scalarizr_update.default_repo');

        $schedule = $dbFarm->GetSetting(DBFarm::SETTING_SZR_UPD_SCHEDULE);
        if (!$schedule)
            $schedule = "* * *";

        $sChunks = explode(" ", $schedule);

        $store = new stdClass();
        $store->fields = array('name', 'description');
        $store->proxy = 'object';

        $itm = array(
            'xtype' => 'fieldset',
            'title' => 'Scalr agent update settings',
            'labelWidth' => 220,
            'items' => array(
                array(
                    'xtype' => 'combo',
                    'itemId' => 'repo',
                    'editable' => false,
                    'name' => 'szrRepository',
                    'fieldLabel' => 'Repository',
                    'queryMode' => 'local',
                    'store' => $store,
                    'value' => $repo,
                    'valueField' => 'name',
                    'displayField' => 'name'
                ),
                array(
                    'xtype' => 'fieldcontainer',
                    'fieldLabel' => 'Schedule',
                    'layout' => 'hbox',
                    'items' => array(
                        array(
                            'xtype' => 'textfield',
                            'hideLabel' => true,
                            'width' => 50,
                            'margin' => '0 3 0 0',
                            'value' => $sChunks[0],
                            'name' => 'hh'
                        ), array(
                            'xtype' => 'textfield',
                            'hideLabel' => true,
                            'value' => $sChunks[1],
                            'width' => 50,
                            'margin' => '0 3 0 0',
                            'name' => 'dd'
                        ), array(
                            'xtype' => 'textfield',
                            'hideLabel' => true,
                            'width' => 50,
                            'value' => $sChunks[2],
                            'name' => 'dw',
                            'margin' => '0 3 0 0'
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
                ),
                array(
                    'xtype' => 'button',
                    'itemId' => 'updSettingsSave',
                    'text' => 'Save',
                    'flex' => 1
                )
            )
        );

        $form[] = $itm;

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
                    'fieldCls' => 'x-form-field-info',
                    'anchor' => '100%',
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
                'labelWidth' => 220,
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
                    'labelWidth' => 220,
                    'items' => $it
            );
        }

        $governance = new Scalr_Governance($this->getEnvironmentId());
        if ($governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE) && $dbFarm->GetSetting(DBFarm::SETTING_LEASE_STATUS) && $dbFarm->Status == FARM_STATUS::RUNNING) {
            $terminateDate = new DateTime($dbFarm->GetSetting(DBFarm::SETTING_LEASE_TERMINATE_DATE));
            $localeTerminateDate = Scalr_Util_DateTime::convertDateTime($terminateDate, $tz, 'M j, Y');
            $config = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE, null);

            $standardExtend = $config['leaseExtension'] == 'allow' &&
                ($terminateDate->diff(new DateTime())->days < $config['defaultLifePeriod']) &&
                ($dbFarm->GetSetting(DBFarm::SETTING_LEASE_EXTEND_CNT) < $config['leaseExtensionStandardNumber']);

            $standardExtendRemain = $config['leaseExtensionStandardNumber'] - $dbFarm->GetSetting(DBFarm::SETTING_LEASE_EXTEND_CNT);

            if (
                $config['leaseExtension'] == 'allow' &&
                ($terminateDate->diff(new DateTime())->days >= $config['defaultLifePeriod']) &&
                ($dbFarm->GetSetting(DBFarm::SETTING_LEASE_EXTEND_CNT) < $config['leaseExtensionStandardNumber'])
            ) {
                $standardExtendNextDate = new DateTime($dbFarm->GetSetting(DBFarm::SETTING_LEASE_TERMINATE_DATE));
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
                'labelWidth' => 220,
                'itemId' => 'lease',
                'params' => array(
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

        $this->response->page('ui/farms/extendedinfo.js', array('scalarizr_repos' => $scalarizrRepos, 'id' => $dbFarm->ID, 'name' => $dbFarm->Name, 'info' => $form));
    }

    public function xLeaseExtendAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS);

        if (!$this->getParam('farmId'))
            throw new Exception(_('Server not found'));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);

        $governance = new Scalr_Governance($this->getEnvironmentId());
        if (! ($governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE) && $dbFarm->GetSetting(DBFarm::SETTING_LEASE_STATUS) && $dbFarm->Status == FARM_STATUS::RUNNING))
            throw new Scalr_Exception_Core('You can\'t manage lease for this farm');

        $config = $governance->getValue(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE, null);
        $terminateDate = new DateTime($dbFarm->GetSetting(DBFarm::SETTING_LEASE_TERMINATE_DATE));

        if ($config['leaseExtension'] != 'allow')
            throw new Scalr_Exception_Core('You can\'t extend lease for this farm');

        if ($this->getParam('extend') == 'standard') {
            $standardExtend = ($terminateDate->diff(new DateTime())->days < $config['defaultLifePeriod']) &&
                ($dbFarm->GetSetting(DBFarm::SETTING_LEASE_EXTEND_CNT) < $config['leaseExtensionStandardNumber']);

            if ($standardExtend) {
                $terminateDate->add(new DateInterval('P' . intval($config['defaultLifePeriod']) . 'D'));

                $dbFarm->SetSetting(DBFarm::SETTING_LEASE_EXTEND_CNT, $dbFarm->GetSetting(DBFarm::SETTING_LEASE_EXTEND_CNT) + 1);
                $dbFarm->SetSetting(DBFarm::SETTING_LEASE_TERMINATE_DATE, $terminateDate->format('Y-m-d H:i:s'));
                $dbFarm->SetSetting(DBFarm::SETTING_LEASE_NOTIFICATION_SEND, null);

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

        $sql = "SELECT  name, id FROM farms WHERE env_id = ?";
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

        //If user has no permission to view farms which are not owned by him
        //it imposes additional filter to the query.
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS)) {
            $sql .= " AND created_by_id = ? ";
            array_push($args, $this->request->getUser()->getId());
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
     *      'addEmpty' - add "*empty*" option
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
        $farms = $this->db->GetAll('SELECT id, name FROM farms WHERE env_id = ? ORDER BY name', $this->getEnvironmentId());
        if (in_array('addEmpty', $options))
            array_unshift($farms, array('id' => '', 'name' => ''));

        if (in_array('addAllFarm', $options))
            array_unshift($farms, array('id' => '0', 'name' => 'On all farms'));

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

        $displayConvention = Scalr::config('scalr.ui.server_display_convention');

        foreach ($dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING)) as $value) {
            $hostname = $value->GetProperty(Scalr_Role_Behavior::SERVER_BASE_HOSTNAME);
            $name = "#{$value->index}: ";

            if ($displayConvention == 'hostname')
                $name .= $hostname;
            elseif (($displayConvention == 'auto' && $value->remoteIp) || $displayConvention == 'public')
                $name .= $value->remoteIp;
            elseif ($value->localIp)
                $name .= $value->localIp;

            array_push($servers, array('id' => $value->serverId, 'name' => $name));
        }

        if (count($servers) && in_array('addAll', $options)) {
            array_unshift($servers, array('id' => 0, 'name' => 'On all instances of a role in this farm'));
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
        $this->request->restrictAccess(Acl::RESOURCE_FARMS);
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

    public function xCloneAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_CLONE);

        $this->request->defineParams(array(
            'farmId' => array('type' => 'int')
        ));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);

        $newDbFarm = $dbFarm->cloneFarm(null, $this->user, $this->getEnvironmentId());

        $this->response->success("Farm successfully cloned. New farm: '{$newDbFarm->Name}'");
    }

    public function xLockAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS);

        $this->request->defineParams(array(
            'farmId' => array('type' => 'int'),
            'comment', 'restrict'
        ));

        if (! $this->getParam('comment')) {
            $this->response->failure('Comment is required');
            return;
        }

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
        $dbFarm->isLocked();

        $dbFarm->lock($this->user->getId(), $this->getParam('comment'), !!$this->getParam('restrict'));

        $this->response->success('Farm successfully locked');
    }

    public function xUnlockAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS);

        $this->request->defineParams(array(
            'farmId' => array('type' => 'int')
        ));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
        if ($dbFarm->isLocked(false)) {
            if ($dbFarm->GetSetting(DBFarm::SETTING_LOCK_RESTRICT) &&
                $dbFarm->createdByUserId != $this->user->getId() &&
                $this->user->getType() != Scalr_Account_User::TYPE_ACCOUNT_OWNER
            ) {
                // farm lock restricted, user has no access
                throw new Exception('You can\'t unlock farm. Only farm owner or account owner can do that.');
            }

            $dbFarm->unlock($this->user->getId());
            $this->response->success('Farm successfully unlocked');
        } else {
            $this->response->failure('Farm isn\'t locked');
        }
    }

    public function xTerminateAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_TERMINATE);

        $this->request->defineParams(array(
            'farmId' => array('type' => 'int'),
            'deleteDNSZones' => array('type' => 'string'),
            'deleteCloudObjects' => array('type' => 'string'),
            'unTermOnFail' => array('type' => 'string'),
            'forceTerminate' => array('type' => 'string'),
            'sync' => array('type' => 'array'),
            'syncInstances' => array('type' => 'array'),
        ));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
        $dbFarm->isLocked();

        $removeZoneFromDNS = ($this->getParam('deleteDNSZones') == 'on') ? 1 : 0;
        $keepCloudObjects = ($this->getParam('deleteCloudObjects') == 'on') ? 0 : 1;
        $termOnFail = ($this->getParam('unTermOnFail') == 'on') ? 0 : 1;
        $forceTerminate = ($this->getParam('forceTerminate') == 'on') ? 1 : 0;

        $event = new FarmTerminatedEvent(
            $removeZoneFromDNS, $keepCloudObjects, $termOnFail, $keepCloudObjects, $forceTerminate, $this->user->id
        );
        Scalr::FireEvent($this->getParam('farmId'), $event);

        $this->response->success('Farm successfully terminated. Instances termination can take a few minutes.');
    }

    public function xGetTerminationDetailsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_TERMINATE);
        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
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

    public function xLaunchAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_LAUNCH);

        $this->request->defineParams(array(
            'farmId' => array('type' => 'int')
        ));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
        $dbFarm->isLocked();

        Scalr::FireEvent($dbFarm->ID, new FarmLaunchedEvent(true, $this->user->id));

        $this->response->success('Farm successfully launched');
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

        $this->request->defineParams(array(
            'farmId' => array('type' => 'int')
        ));

        $dbFarm = DBFarm::LoadByID($this->getParam('farmId'));
        $this->user->getPermissions()->validate($dbFarm);
        $dbFarm->isLocked();

        if ($dbFarm->Status != FARM_STATUS::TERMINATED)
            throw new Exception(_("Cannot delete a running farm. Please terminate a farm before deleting it."));

        $servers = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id=? AND status!=?", array($dbFarm->ID, SERVER_STATUS::TERMINATED));
        if ($servers != 0)
            throw new Exception(sprintf(_("Cannot delete a running farm. %s server%s still running on this farm."), $servers, $servers > 1 ? 's are' : ' is'));

        $this->db->BeginTrans();

        try
        {
            foreach ($this->db->GetAll("SELECT * FROM farm_roles WHERE farmid = ?", array($dbFarm->ID)) as $value) {
                $this->db->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type IN(?,?)", array(
                    $value['id'],
                    Scalr_SchedulerTask::TARGET_ROLE,
                    Scalr_SchedulerTask::TARGET_INSTANCE
                ));
            }

            $this->db->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type = ?", array(
                $dbFarm->ID,
                Scalr_SchedulerTask::TARGET_FARM
            ));

            //We should not remove farm_settings because it is used in stats!

            $this->db->Execute("DELETE FROM farms WHERE id=?", array($dbFarm->ID));
            $this->db->Execute("DELETE FROM farm_roles WHERE farmid=?", array($dbFarm->ID));
            $this->db->Execute("DELETE FROM logentries WHERE farmid=?", array($dbFarm->ID));
            $this->db->Execute("DELETE FROM elastic_ips WHERE farmid=?", array($dbFarm->ID));
            $this->db->Execute("DELETE FROM events WHERE farmid=?", array($dbFarm->ID));
            $this->db->Execute("DELETE FROM ec2_ebs WHERE farm_id=?", array($dbFarm->ID));

            $this->db->Execute("DELETE FROM farm_role_options WHERE farmid=?", array($dbFarm->ID));
            $this->db->Execute("DELETE FROM farm_role_scripts WHERE farmid=?", array($dbFarm->ID));
            $this->db->Execute("DELETE FROM farm_lease_requests WHERE farm_id=?", array($dbFarm->ID));


            //TODO: Remove servers
            $servers = $this->db->Execute("SELECT server_id FROM servers WHERE farm_id=?", array($dbFarm->ID));
            while ($server = $servers->FetchRow()) {
                $dbServer = DBServer::LoadByID($server['server_id']);
                $dbServer->Remove();
            }

            $this->db->Execute("UPDATE dns_zones SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($dbFarm->ID));
            $this->db->Execute("UPDATE apache_vhosts SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($dbFarm->ID));
        } catch(Exception $e) {
            $this->db->RollbackTrans();
            throw new Exception(_("Cannot delete farm at the moment ({$e->getMessage()}). Please try again later."));
        }

        $this->db->CommitTrans();

        $this->db->Execute("DELETE FROM scripting_log WHERE farmid=?", array($dbFarm->ID));

        $this->response->success('Farm successfully removed');
    }

    public function xListFarmsAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS);

        $this->request->defineParams(array(
            'clientId' => array('type' => 'int'),
            'farmId' => array('type' => 'int'),
            'sort' => array('type' => 'json'),
            'expirePeriod' => array('type' => 'int')
        ));

        $governance = new Scalr_Governance($this->getEnvironmentId());
        $leaseStatus = $governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE);

        $sql = 'SELECT f.clientid, f.id, f.name, f.status, f.dtadded, f.created_by_id, f.created_by_email FROM farms f WHERE env_id = ? AND :FILTER:';
        $args = array($this->getEnvironmentId());

        if ($leaseStatus && $this->getParam('expirePeriod')) {
            $dt = new DateTime();
            $dt->add(new DateInterval('P' . $this->getParam('expirePeriod') . 'D'));
            $sql = str_replace('FROM farms f', 'FROM farms f LEFT JOIN farm_settings fs ON f.id = fs.farmid', $sql);
            $sql = str_replace('WHERE', 'WHERE fs.name = ? AND fs.value < ? AND f.status = ? AND', $sql);
            array_unshift($args, DBFarm::SETTING_LEASE_TERMINATE_DATE, $dt->format('Y-m-d H:i:s'), FARM_STATUS::RUNNING);
        }

        if ($this->getParam('farmId')) {
            $sql .= ' AND id = ?';
            $args[] = $this->getParam('farmId');
        }

        if ($this->getParam('clientId')) {
            $sql .= ' AND clientid = ?';
            $args[] = $this->getParam('clientId');
        }

        if ($this->getParam('status') != '') {
            $sql .= ' AND status = ?';
            $args[] = $this->getParam('status');
        }

        if ($this->getParam('showOnlyMy') || !$this->request->isAllowed(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_NOT_OWNED_FARMS)) {
            $sql .= ' AND created_by_id = ?';
            $args[] = $this->user->getId();
        }

        $response = $this->buildResponseFromSql2($sql, array('id', 'name', 'dtadded', 'created_by_email', 'status'), array('name', 'id', 'comments'), $args);

        foreach ($response["data"] as &$row) {
            $row["running_servers"] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id='{$row['id']}' AND status IN ('Pending', 'Initializing', 'Running', 'Temporary','Resuming')");
            $row["suspended_servers"] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id='{$row['id']}' AND status IN ('Suspended', 'Pending suspend')");
            $row["non_running_servers"] = $this->db->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id='{$row['id']}' AND status NOT IN ('Suspended', 'Pending suspend', 'Resuming', 'Pending', 'Initializing', 'Running', 'Temporary', 'Pending launch')");

            $row["roles"] = $this->db->GetOne("SELECT COUNT(*) FROM farm_roles WHERE farmid='{$row['id']}'");
            $row["zones"] = $this->db->GetOne("SELECT COUNT(*) FROM dns_zones WHERE farm_id='{$row['id']}'");

            //TODO: Use Alerts class
            $row['alerts'] = $this->db->GetOne("SELECT COUNT(*) FROM server_alerts WHERE farm_id='{$row['id']}' AND status='failed'");

            $row['dtadded'] = Scalr_Util_DateTime::convertTz($row["dtadded"]);
            $dbFarm = DBFarm::LoadByID($row['id']);
            $row['lock'] = $dbFarm->GetSetting(DBFarm::SETTING_LOCK);
            if ($row['lock'])
                $row['lock_comment'] = $dbFarm->isLocked(false);

            if ($leaseStatus && $dbFarm->GetSetting(DBFarm::SETTING_LEASE_STATUS)) {
                $row['lease'] = $dbFarm->GetSetting(DBFarm::SETTING_LEASE_NOTIFICATION_SEND) ? 'Expire' : $dbFarm->GetSetting(DBFarm::SETTING_LEASE_STATUS);
                if ($row['lease'] == 'Expire') {
                    $dt = new DateTime();
                    $td = new DateTime($dbFarm->GetSetting(DBFarm::SETTING_LEASE_TERMINATE_DATE));
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

            $b = (array)$this->db->GetAll("SELECT DISTINCT(behavior) FROM farm_roles
                INNER JOIN role_behaviors ON role_behaviors.role_id = farm_roles.role_id WHERE farmid = ?", array(
                $row['id']
            ));
            $behaviors = array();
            foreach ($b as $behavior)
                $behaviors[] = $behavior['behavior'];


            $row["havemysqlrole"] = in_array(ROLE_BEHAVIORS::MYSQL, $behaviors);
            $row["havemysql2role"] = in_array(ROLE_BEHAVIORS::MYSQL2, $behaviors);
            $row["havepgrole"] = in_array(ROLE_BEHAVIORS::POSTGRESQL, $behaviors);
            $row["haveredisrole"] = in_array(ROLE_BEHAVIORS::REDIS, $behaviors);
            $row["haverabbitmqrole"] = in_array(ROLE_BEHAVIORS::RABBITMQ, $behaviors);
            $row["havemongodbrole"] = in_array(ROLE_BEHAVIORS::MONGODB, $behaviors);
            $row["haveperconarole"] = in_array(ROLE_BEHAVIORS::PERCONA, $behaviors);
            $row["havemariadbrole"] = in_array(ROLE_BEHAVIORS::MARIADB, $behaviors);

            $row['status_txt'] = FARM_STATUS::GetStatusName($row['status']);

            if ($row['status'] == FARM_STATUS::RUNNING)
            {
                $row['shortcuts'] = [];
                foreach (\Scalr\Model\Entity\ScriptShortcut::find(array(
                    array('farmId' => $row['id']),
                    array('farmRoleId' => NULL)
                )) as $shortcut) {
                    /* @var \Scalr\Model\Entity\ScriptShortcut $shortcut */
                    $row['shortcuts'][] = array(
                        'id' => $shortcut->id,
                        'name' => $shortcut->getScriptName()
                    );
                }
            }
        }

        $this->response->data($response);
    }

    //backward compatibility
    public function edit2Action()
    {
        $this->buildAction();
    }

    //backward compatibility
    public function build2Action()
    {
        $this->buildAction();
    }

    public function editAction()
    {
        $this->buildAction();
    }

    public function buildAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

        $this->request->defineParams(array(
            'farmId' => array('type' => 'int'),
            'roleId' => array('type' => 'int')
        ));

        $farmId = $this->getParam('farmId');
        $roleId = $this->getParam('roleId');

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
        if (empty($platforms))
            throw new Exception('Before building new farm you need to configure environment and setup cloud credentials');


        //categories list
        $categories = $this->db->GetAll(
            "SELECT c.id, c.name, COUNT(DISTINCT r.id) AS total
             FROM role_categories c
             LEFT JOIN roles r ON c.id = r.cat_id AND r.env_id IN(0, ?) AND r.id IN (
                SELECT role_id
                FROM role_images
                WHERE role_id = r.id
                AND platform IN ('".implode("','", array_keys($platforms))."')
             )
             LEFT JOIN roles_queue q ON r.id = q.role_id
             WHERE c.env_id IN (0, ?)
             AND q.id IS NULL
             GROUP BY c.id
            ",
            array($this->environment->id, $this->environment->id)
        );
        $moduleParams['categories'] = array();
        foreach ($categories as $g)
            $moduleParams['categories'][$g['id']] = $g;

        $moduleParams['farmVpcEc2Enabled'] = $this->getEnvironment()->isPlatformEnabled(SERVER_PLATFORMS::EC2);
        if ($moduleParams['farmVpcEc2Enabled']) {
            $moduleParams['farmVpcEc2Locations'] = self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false);
        }

        if ($farmId) {
            $c = self::loadController('Builder', 'Scalr_UI_Controller_Farms');
            $moduleParams['farm'] = $c->getFarm2($farmId);
        } else {
            // TODO: remove hack, do better
            $vars = new Scalr_Scripting_GlobalVariables($this->user->getAccountId(), $this->getEnvironmentId(), Scalr_Scripting_GlobalVariables::SCOPE_FARM);
            $moduleParams['farmVariables'] = $vars->getValues();
        }

        $moduleParams['tabs'] = array(
            'vpcrouter', 'dbmsr', 'mongodb', 'mysql', 'scaling', 'network', 'gce', 'cloudfoundry', 'rabbitmq', 'haproxy', 'proxy',
            'rds',   'scripting',
            'nimbula', 'ec2', 'security', 'devel', 'storage', 'variables', 'advanced'
        );

        if ($this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_CHEF)) {
            $moduleParams['tabs'][] = 'chef';
        }
        //deprecated tabs
        $moduleParams['tabs'][] = 'deployments';
        $moduleParams['tabs'][] = 'ebs';
        $moduleParams['tabs'][] = 'params';
        $moduleParams['tabs'][] = 'servicesconfig';

        $conf = $this->getContainer()->config->get('scalr.load_statistics.connections.plotter');
        $moduleParams['tabParams'] = array(
            'farmId'        => $farmId,
            'farmHash'      => $moduleParams['farm'] ? $moduleParams['farm']['farm']['hash'] : '',
            'accountId'     => $this->environment->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID),
            'remoteAddress' => $this->request->getRemoteAddr(),
            'monitoringHostUrl' => "{$conf['scheme']}://{$conf['host']}:{$conf['port']}",
            'nginx'         => array(
                'server_section' => @file_get_contents("../templates/services/nginx/server_section.tpl"),
                'server_section_ssl' => @file_get_contents("../templates/services/nginx/server_section_ssl.tpl")
            )
        );

        // TODO: Features
        $moduleParams['tabParams']['featureRAID'] = $this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_RAID);
        $moduleParams['tabParams']['featureMFS'] = $this->user->getAccount()->isFeatureEnabled(Scalr_Limits::FEATURE_MFS);
        $moduleParams['tabParams']['scalr.dns.global.enabled'] = \Scalr::config('scalr.dns.global.enabled');
        $moduleParams['tabParams']['scalr.instances_connection_policy'] = \Scalr::config('scalr.instances_connection_policy');
        $moduleParams['tabParams']['scalr.scalarizr_update.repos'] = array_keys(\Scalr::config('scalr.scalarizr_update.repos'));
        $moduleParams['tabParams']['scalr.scalarizr_update.default_repo'] = \Scalr::config('scalr.scalarizr_update.default_repo');

        $moduleParams['metrics'] = self::loadController('Metrics', 'Scalr_UI_Controller_Scaling')->getList();
        $moduleParams['timezones_list'] = Scalr_Util_DateTime::getTimezones();
        $moduleParams['timezone_default'] = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);

        if ($moduleParams['farm']['farm']['ownerEditable']) {
            $moduleParams['usersList'] = Scalr_Account_User::getList($this->user->getAccountId());
        }

        $governance = new Scalr_Governance($this->getEnvironmentId());
        $moduleParams['governance'] = $governance->getValues(true);

        $defaultFarmRoleSecurityGroups = array('default');
        if (\Scalr::config('scalr.aws.security_group_name')) {
            $defaultFarmRoleSecurityGroups[] = \Scalr::config('scalr.aws.security_group_name');
        }

        $moduleParams['roleDefaultSettings'] = array(
            'base.keep_scripting_logs_time' => \Scalr::config('scalr.system.scripting.default_instance_log_rotation_period'),
            'security_groups.list' => json_encode($defaultFarmRoleSecurityGroups)
        );

        //cost analytics
        if ($this->getContainer()->analytics->enabled && $this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)) {
            $costCenter = $this->getContainer()->analytics->ccs->get($this->getEnvironment()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID));

            $projects = [];

            if ($costCenter instanceof CostCentreEntity) {
                $projectsIterator = new SharedProjectsFilterIterator($costCenter->getProjects(), $costCenter->ccId, $this->user, $this->getEnvironment());
                foreach ($projectsIterator as $item) {
                    /* @var $item ProjectEntity */
                    $projects[] = array(
                        'projectId' => $item->projectId,
                        'name'      => $item->name
                    );
                }
                $costCentreName = $costCenter->name;
            } else {
                $costCentreName = '';
            }

            $moduleParams['analytics'] = array(
                'costCenterName' => $costCentreName,
                'projects'       => $projects
            );

            if ($farmId) {
                $dbFarm = DBFarm::LoadByID($farmId);
                $moduleParams['farm']['farm']['projectId'] = $dbFarm->GetSetting(DBFarm::SETTING_PROJECT_ID);
            }

        }
        $this->response->page('ui/farms/builder.js', $moduleParams, array(
            'ui/farms/builder/selroles.js',
            'ui/farms/builder/roleedit.js',
            'ui/farms/builder/roleslibrary.js',
            //tabs
            'ui/farms/builder/tabs/dbmsr.js',
            'ui/farms/builder/tabs/cloudfoundry.js',
            'ui/farms/builder/tabs/rabbitmq.js',
            'ui/farms/builder/tabs/mongodb.js',
            'ui/farms/builder/tabs/haproxy.js',
            'ui/farms/builder/tabs/proxy.js',
            'ui/farms/builder/tabs/mysql.js',
            'ui/farms/builder/tabs/nimbula.js',
            'ui/farms/builder/tabs/rds.js',
            'ui/farms/builder/tabs/gce.js',
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
            'ui/farms/builder/tabs/deployments.js',
            'ui/farms/builder/tabs/ebs.js',
            'ui/farms/builder/tabs/params.js',
            'ui/farms/builder/tabs/servicesconfig.js',
            //roleslibrary add role settings
            'ui/farms/builder/roleslibrary/ec2.js',
            'ui/farms/builder/roleslibrary/vpc.js',
            'ui/farms/builder/roleslibrary/euca.js',
            'ui/farms/builder/roleslibrary/rackspace.js',
            'ui/farms/builder/roleslibrary/openstack.js',
            'ui/farms/builder/roleslibrary/cloudstack.js',
            'ui/farms/builder/roleslibrary/gce.js',
            'ui/farms/builder/roleslibrary/mongodb.js',
            'ui/farms/builder/roleslibrary/dbmsr.js',
            'ui/farms/builder/roleslibrary/proxy.js',
            'ui/farms/builder/roleslibrary/haproxy.js',
            'ui/farms/builder/roleslibrary/chef.js',
            //other
            'codemirror/codemirror.js',
            'ui/core/variablefield.js',
            'ui/scripts/scriptfield.js',
            'ux-boxselect.js',
            'ui/monitoring/window.js',
            'ui/services/chef/chefsettings.js',
            'ui/security/groups/sgeditor.js'
        ), array(
            'ui/farms/builder/selroles.css',
            'ui/farms/builder/roleedit.css',
            'ui/farms/builder/roleslibrary.css',
            'codemirror/codemirror.css',
            'ui/core/variablefield.css',
            'ui/scripts/scriptfield.css',
            'ui/farms/builder/tabs/scaling.css',
        ));
    }

    /**
     * @param $farmId
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xGetOwnerHistoryAction($farmId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

        $dbFarm = DBFarm::LoadByID($farmId);
        $this->user->getPermissions()->validate($dbFarm);

        if ($dbFarm->createdByUserId == $this->user->getId() || $this->user->isAccountOwner()) {
            $history = $dbFarm->GetSetting(DBFarm::SETTING_OWNER_HISTORY);
            if ($history)
                $history = unserialize($history);

            if (! $history)
                $history = [];

            $history = array_map(function($item) {
                $item['dt'] = Scalr_Util_DateTime::convertTz($item['dt']);
                return $item;
            }, $history);

            $this->response->data(['history' => $history]);
        } else {
            throw new Scalr_Exception_InsufficientPermissions();
        }
    }
}
