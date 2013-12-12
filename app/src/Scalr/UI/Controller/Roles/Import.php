<?php
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Roles_Import extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_CREATE);
    }

    public function defaultAction()
    {
        $this->importAction();
    }

    public function xSetBehaviorsAction()
    {
        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        if ($dbServer->status != SERVER_STATUS::IMPORTING) {
            throw new Exception('Server is not in importing state');
        }

        if ($dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_STEP) == 2) {
            throw new Exception('Role creation is already started');
        }

        $bundleTask = BundleTask::LoadById($this->getParam('bundleTaskId'));
        $this->user->getPermissions()->validate($bundleTask);

        // $this->getParam('behaviors') = comma separated behaviors, eg: chef,mysql
        $dbServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR, trim($this->getParam('behaviors')));
        $dbServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_STEP, 2);

        $bundleTask->status = SERVER_SNAPSHOT_CREATION_STATUS::PENDING;
        $bundleTask->Log(sprintf(_("Prebuild automation was enabled for: %s. Bundle task status: %s"), trim($this->getParam('behaviors')), $bundleTask->status));
        $bundleTask->Save();

        $this->response->data(array(
            'bundleTaskId' => $bundleTask->id,
        ));
    }

    public function xCheckCommunicationAction()
    {
        $dbServer = DBServer::LoadByID($this->getParam('serverId'));
        $this->user->getPermissions()->validate($dbServer);

        if ($dbServer->status != SERVER_STATUS::IMPORTING) {
            throw new Exception('Server is not in importing state');
        }

        $inboundConnection = false;
        $outboundConnection = false;

        $row = $this->db->GetRow("SELECT * FROM messages WHERE server_id = ? AND type = ? LIMIT 1",
            array($dbServer->serverId, "in"));

        if ($row) {
            $inboundConnection = true;
            $outboundConnection = (bool)$dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION);
            if ($outboundConnection)
                $behaviors = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR);
            else {
                $connectionError = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OUT_CONNECTION_ERROR);
            }

            $bundleTaskId = $this->db->GetOne(
                "SELECT id FROM bundle_tasks WHERE server_id = ? ORDER BY dtadded DESC LIMIT 1",
                array($dbServer->serverId)
            );

            if ($bundleTaskId) {
                $bundleTask = BundleTask::LoadById($bundleTaskId);
                $os = array('family' => $bundleTask->osFamily, 'version' => $bundleTask->osVersion, 'name' => $bundleTask->osName);
            }
        }

        $this->response->data(array(
            'inbound' => $inboundConnection,
            'outbound' => $outboundConnection,
            'connectionError' => $connectionError,
            'bundleTaskId' => $bundleTaskId,
            'behaviors' => $behaviors ? explode(',', $behaviors) : null,
            'os' => $os
        ));
    }

    public function xInitiateImportAction()
    {
        $this->request->defineParams(array(
            'platform',
            'cloudLocation',
            'cloudServerId',
            'roleName'
        ));

        $validator = new Scalr_Validator();

        if ($validator->validateNotEmpty($this->getParam('roleName')) !== true)
            $err['roleName'] = 'Role name cannot be empty';

        if (strlen($this->getParam('roleName')) < 3)
            $err['roleName'] = _("Role name should be greater than 3 chars");

        if (! preg_match("/^[A-Za-z0-9-]+$/si", $this->getParam('roleName')))
            $err['roleName'] = _("Role name is incorrect");

        if ($this->db->GetOne("SELECT id FROM roles WHERE name=? AND (env_id = '0' OR env_id = ?) LIMIT 1",
            array($this->getParam('roleName'), $this->getEnvironmentId()))
        )
            $err['roleName'] = 'Selected role name is already used. Please select another one.';


        $cryptoKey = Scalr::GenerateRandomKey(40);

        $creInfo = new ServerCreateInfo($this->getParam('platform'), null, 0, 0);
        $creInfo->clientId = $this->user->getAccountId();
        $creInfo->envId = $this->getEnvironmentId();
        $creInfo->farmId = 0;
        $creInfo->SetProperties(array(
            SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME => $this->getParam('roleName'),
            SERVER_PROPERTIES::SZR_KEY => $cryptoKey,
            SERVER_PROPERTIES::SZR_KEY_TYPE => SZR_KEY_TYPE::PERMANENT,
            SERVER_PROPERTIES::SZR_VESION => "0.14.0",
            SERVER_PROPERTIES::SZR_IMPORTING_VERSION => 2,
            SERVER_PROPERTIES::SZR_IMPORTING_STEP => 1,

            SERVER_PROPERTIES::LAUNCHED_BY_ID => $this->user->id,
            SERVER_PROPERTIES::LAUNCHED_BY_EMAIL => $this->user->getEmail()
        ));

        $platform = PlatformFactory::NewPlatform($this->getParam('platform'));

        if ($this->getParam('platform') == SERVER_PLATFORMS::EC2) {

            $client = $this->environment->aws($this->getParam('cloudLocation'))->ec2;
            $r = $client->instance->describe($this->getParam('cloudServerId'));
            $instance = $r->get(0)->instancesSet->get(0);

            $creInfo->SetProperties(array(
                EC2_SERVER_PROPERTIES::REGION => $this->getParam('cloudLocation'),
                EC2_SERVER_PROPERTIES::INSTANCE_ID => $this->getParam('cloudServerId'),
                EC2_SERVER_PROPERTIES::AMIID => $instance->imageId,
                EC2_SERVER_PROPERTIES::AVAIL_ZONE => $instance->placement->availabilityZone
            ));
        } else if ($this->getParam('platform') == SERVER_PLATFORMS::GCE) {

            $gce = $platform->getClient($this->environment, $this->getParam('cloudLocation'));

            $result = $gce->instances->get(
                $this->environment->getPlatformConfigValue(Modules_Platforms_GoogleCE::PROJECT_ID),
                $this->getParam('cloudLocation'),
                $this->getParam('cloudServerId')
            );

            $creInfo->SetProperties(array(
                GCE_SERVER_PROPERTIES::SERVER_ID => $result->id,
                GCE_SERVER_PROPERTIES::SERVER_NAME => $this->getParam('cloudServerId'),
                GCE_SERVER_PROPERTIES::CLOUD_LOCATION => $this->getParam('cloudLocation')
            ));
        } else if (PlatformFactory::isOpenstack($this->getParam('platform'))) {
            $creInfo->SetProperties(array(
                OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $this->getParam('cloudLocation'),
                OPENSTACK_SERVER_PROPERTIES::SERVER_ID => $this->getParam('cloudServerId')
            ));
        } else if (PlatformFactory::isCloudstack($this->getParam('platform'))) {
            $creInfo->SetProperties(array(
                CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $this->getParam('cloudLocation'),
                CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID => $this->getParam('cloudServerId')
            ));
        }

        $dbServer = DBServer::Create($creInfo, true);

        $platform = PlatformFactory::NewPlatform($this->getParam('platform'));
        $ips = $platform->GetServerIPAddresses($dbServer);
        $dbServer->localIp = $ips['localIp'];
        $dbServer->remoteIp = $ips['remoteIp'];
        $dbServer->Save();

        $this->response->data(array(
            'command' => $this->getSzrCmd($dbServer),
            'serverId' => $dbServer->serverId
        ));
    }

    private function getSzrCmd(DBServer $dbServer)
    {
        $baseurl = \Scalr::config('scalr.endpoint.scheme') . "://" .
            \Scalr::config('scalr.endpoint.host');

        $platform = $dbServer->isOpenstack() ? SERVER_PLATFORMS::OPENSTACK : $dbServer->platform;
        $options = array(
            'server-id' 	=> $dbServer->serverId,
            'role-name' 	=> $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME),
            'crypto-key' 	=> $dbServer->GetProperty(SERVER_PROPERTIES::SZR_KEY),
            'platform' 		=> $platform,
            'queryenv-url' 	=> $baseurl . "/query-env",
            'messaging-p2p.producer-url' => $baseurl . "/messaging",
            'env-id'		=> $dbServer->envId,
            'region'		=> $dbServer->GetCloudLocation(),
            'scalr-id'		=> SCALR_ID
        );

        $command = 'scalarizr --import -y';
        foreach ($options as $k => $v) {
            $command .= sprintf(' -o %s=%s', $k, $v);
        }

        return $command;
    }

    public function xGetCloudServersListAction()
    {
        $this->request->defineParams(array(
            'platform',
            'cloudLocation'
        ));

        if (!$this->environment->isPlatformEnabled($this->getParam('platform')))
            throw new Exception(sprintf('Cloud %s is not enabled for current environment', $this->getParam('platform')));

        $results = array();

        $platform = PlatformFactory::NewPlatform($this->getParam('platform'));

        //TODO: Added support for GCE
        if ($this->getParam('platform') == SERVER_PLATFORMS::GCE) {
            $gce = $platform->getClient($this->environment, $this->getParam('cloudLocation'));

            $result = $gce->instances->listInstances($this->environment->getPlatformConfigValue(
                Modules_Platforms_GoogleCE::PROJECT_ID),
                $this->getParam('cloudLocation'),
                array()
            );

            if (is_array($result->items)) {
                foreach ($result->items as $server) {
                    if ($server->status != 'RUNNING')
                        continue;

                    $ips = $platform->determineServerIps($gce, $server);
                    $itm = array(
                       'id' => $server->name,
                       'localIp' => $ips['localIp'],
                       'publicIp' => $ips['remoteIp'],
                       'zone' => $this->getParam('cloudLocation'),
                       'isImporting' => false,
                       'isManaged' => false
                    );

                    //Check is instance already importing
                    try {
                        $dbServer = DBServer::LoadByPropertyValue(GCE_SERVER_PROPERTIES::SERVER_ID, $server->id);
                        if ($dbServer && $dbServer->status != SERVER_STATUS::TERMINATED) {
                            if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                                $itm['isImporting'] = true;
                            } else {
                                $itm['isManaged'] = true;
                            }
                            $itm['serverId'] = $dbServer->serverId;
                        }
                    } catch (Exception $e) {}

                    $results[] = $itm;
                }
            }


        } elseif (PlatformFactory::isOpenstack($this->getParam('platform'))) {
            $client = $this->environment->openstack($this->getParam('platform'), $this->getParam('cloudLocation'));
            $r = $client->servers->list(true);

            foreach ($r as $server) {
                if ($server->status != 'ACTIVE')
                    continue;

                $ips = $platform->determineServerIps($client, $server);

                $itm = array(
                    'id' => $server->id,
                    'localIp' => $ips['localIp'],
                    'publicIp' => $ips['remoteIp'],
                    'zone' => $this->getParam('cloudLocation'),
                    'isImporting' => false,
                    'isManaged' => false
                );

                //Check is instance already importing
                try {
                    $dbServer = DBServer::LoadByPropertyValue(OPENSTACK_SERVER_PROPERTIES::SERVER_ID, $server->id);
                    if ($dbServer && $dbServer->status != SERVER_STATUS::TERMINATED) {
                        if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                            $itm['isImporting'] = true;
                        } else {
                            $itm['isManaged'] = true;
                        }
                        $itm['serverId'] = $dbServer->serverId;
                    }
                } catch (Exception $e) {}

                $results[] = $itm;
            }
        } elseif (PlatformFactory::isCloudstack($this->getParam('platform'))) {
            $client = Scalr_Service_Cloud_Cloudstack::newCloudstack(
                $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $this->environment),
                $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $this->environment),
                $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $this->environment),
                $this->getParam('platform')
            );

            $platform = PlatformFactory::NewPlatform($this->getParam('platform'));

            $r = $client->listVirtualMachines(null, $this->getParam('cloudLocation'));
            foreach ($r as $server) {
                $ips = $platform->determineServerIps($client, $server);

                $itm = array(
                    'id' => $server->id,
                    'localIp' => $ips['localIp'],
                    'publicIp' => $ips['remoteIp'],
                    'zone' => $this->getParam('cloudLocation'),
                    'isImporting' => false,
                    'isManaged' => false
                );

                //Check is instance already importing
                try {
                    $dbServer = DBServer::LoadByPropertyValue(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID, $server->id);
                    if ($dbServer && $dbServer->status != SERVER_STATUS::TERMINATED) {
                        if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                            $itm['isImporting'] = true;
                        } else {
                            $itm['isManaged'] = true;
                        }
                        $itm['serverId'] = $dbServer->serverId;
                    }
                } catch (Exception $e) {}

                $results[] = $itm;
            }
        } elseif ($this->getParam('platform') == SERVER_PLATFORMS::EC2) {
            $client = $this->environment->aws($this->getParam('cloudLocation'))->ec2;
            $r = $client->instance->describe();
            if (count($r)) {
                foreach ($r as $reservation) {
                    /* @var $reservation Scalr\Service\Aws\Ec2\DataType\ReservationData */
                    foreach ($reservation->instancesSet as $instance) {
                        /* @var $instance Scalr\Service\Aws\Ec2\DataType\InstanceData */

                        if ($instance->instanceState->name != 'running')
                            continue;

                        $itm = array(
                            'id' => $instance->instanceId,
                            'localIp' => $instance->privateIpAddress,
                            'publicIp' => $instance->ipAddress,
                            'zone' => $instance->placement->availabilityZone,
                            'isImporting' => false,
                            'isManaged' => false
                        );

                        //Check is instance already importing
                        try {
                            $dbServer = DBServer::LoadByPropertyValue(EC2_SERVER_PROPERTIES::INSTANCE_ID, $instance->instanceId);
                            if ($dbServer && $dbServer->status != SERVER_STATUS::TERMINATED) {
                                if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                                    $itm['isImporting'] = true;
                                } else {
                                    $itm['isManaged'] = true;
                                }
                                $itm['serverId'] = $dbServer->serverId;
                            }
                        } catch (Exception $e) {}

                        $results[] = $itm;
                    }
                }
            }
        }

        $this->response->data(array(
            'data' => $results
        ));
    }

    public function importAction()
    {
        $unsupportedPlatforms = array('rds', SERVER_PLATFORMS::RACKSPACE, SERVER_PLATFORMS::EUCALYPTUS, SERVER_PLATFORMS::NIMBULA);
        $platforms = array();
        $env = Scalr_Environment::init()->loadById($this->getEnvironmentId());
        foreach ($env->getEnabledPlatforms() as $platform) {
            if (!in_array($platform, $unsupportedPlatforms)) {
                $platforms[$platform] = array('locations' => array());
                if ($platform !== SERVER_PLATFORMS::GCE) {
                    foreach (PlatformFactory::NewPlatform($platform)->getLocations() as $lk=>$lv) {
                        $platforms[$platform]['locations'][$lk] = $lv;
                    }
                }
            }
        }

        $command = null;
        if ($this->getParam('serverId')) {
            $dbServer = DBServer::LoadByID($this->getParam('serverId'));
            $this->user->getPermissions()->validate($dbServer);

            if ($dbServer->status != SERVER_STATUS::IMPORTING)
                throw new Exception('Server is not in importing state');

            $command = $this->getSzrCmd($dbServer);
            $step = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_STEP);
            $server = array(
                'localIp' => $dbServer->localIp,
                'remoteIp' => $dbServer->remoteIp,
                'cloudServerId' => $dbServer->GetCloudServerID(),
                'cloudLocation' => $dbServer->GetCloudLocation(),
                'platform'      => $dbServer->platform,
                'roleName'      => $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME)
            );
        }

        $this->response->page('ui/roles/import/view.js', array(
            'platforms' 	=> $platforms,
            'command'       => $command,
            'step'          => $step,
            'server'        => $server
        ), array(), array('ui/roles/import/view.css'));
    }

    public function xGetBundleTaskDataAction()
    {
        $task = BundleTask::LoadById($this->getParam('bundleTaskId'));
        $this->user->getPermissions()->validate($task);

        $logs = $this->db->GetAll("SELECT * FROM bundle_task_log WHERE bundle_task_id = " . $this->db->qstr($this->getParam('bundleTaskId')) . ' ORDER BY id DESC LIMIT 3');
        foreach ($logs as &$row) {
            $row['dtadded'] = Scalr_Util_DateTime::convertTz($row['dtadded']);
        }
        $this->response->data(array(
            'status'        => $task->status,
            'failureReason' => $task->failureReason,
            'logs'          => $logs,
            'roleId'        => $task->roleId,
            'roleName'      => $task->roleName
        ));
    }

}