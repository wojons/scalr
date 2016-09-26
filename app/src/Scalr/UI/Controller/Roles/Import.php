<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\Os;
use Scalr\Model\Entity\CloudCredentialsProperty;

class Scalr_UI_Controller_Roles_Import extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_IMPORT);
    }

    public function defaultAction()
    {
        $this->callActionMethod("importAction");
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

        if (!$this->getParam('osId')) {
            throw new Exception("OS should be specified");
        }

        $bundleTask = BundleTask::LoadById($this->getParam('bundleTaskId'));
        $this->user->getPermissions()->validate($bundleTask);

        // $this->getParam('behaviors') = comma separated behaviors, eg: chef,mysql
        $dbServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BEHAVIOR, trim($this->getParam('behaviors')));
        $dbServer->SetProperty(SERVER_PROPERTIES::SZR_IMPORTING_STEP, 2);

        $bundleTask->osId = $this->getParam('osId');
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
                $osDetails = $bundleTask->getOsDetails();

                $criteria = [
                    ['family'     => $osDetails->family],
                    ['generation' => $osDetails->generation],
                    ['status'     => Os::STATUS_ACTIVE]
                ];
                $os = Os::find($criteria);
            }
        }

        $this->response->data(array(
            'inbound' => $inboundConnection,
            'outbound' => $outboundConnection,
            'connectionError' => $connectionError,
            'bundleTaskId' => $bundleTaskId,
            'behaviors' => $behaviors ? explode(',', $behaviors) : null,
            'os' => (isset($os) ? $os->getArrayCopy() : []),
            'serverOs' => ($osDetails) ? $osDetails->name : ''
        ));
    }

    /**
     * @param   string      $platform
     * @param   string      $cloudLocation
     * @param   string      $cloudServerId
     * @param   string      $name
     * @param   bool        $createImage
     * @throws  Exception
     */
    public function xInitiateImportAction($platform, $cloudLocation, $cloudServerId, $name, $createImage = false)
    {
        if (!Role::isValidName($name))
            throw new Exception(_("Name is incorrect"));

        if (!$createImage) {
            $this->request->restrictAccess(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);
        }

        if (!$createImage && Role::isNameUsed($name, $this->user->getAccountId(), $this->getEnvironmentId())) {
            throw new Exception('Selected role name is already used. Please select another one.');
        }

        $cryptoKey = Scalr::GenerateRandomKey(40);

        $creInfo = new ServerCreateInfo($platform, null, 0, 0);
        $creInfo->clientId = $this->user->getAccountId();
        $creInfo->envId = $this->getEnvironmentId();
        $creInfo->farmId = 0;
        $creInfo->SetProperties(array(
            SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME => $name,
            SERVER_PROPERTIES::SZR_IMPORTING_OBJECT => $createImage ? BundleTask::BUNDLETASK_OBJECT_IMAGE : BundleTask::BUNDLETASK_OBJECT_ROLE,
            SERVER_PROPERTIES::SZR_KEY => $cryptoKey,
            SERVER_PROPERTIES::SZR_KEY_TYPE => SZR_KEY_TYPE::PERMANENT,
            SERVER_PROPERTIES::SZR_VESION => "0.14.0",
            SERVER_PROPERTIES::SZR_IMPORTING_VERSION => 2,
            SERVER_PROPERTIES::SZR_IMPORTING_STEP => 1,

            SERVER_PROPERTIES::LAUNCHED_BY_ID => $this->user->id,
            SERVER_PROPERTIES::LAUNCHED_BY_EMAIL => $this->user->getEmail()
        ));

        $platformObj = PlatformFactory::NewPlatform($platform);
        $availZone = null;
        $osType = '';

        if ($platform == SERVER_PLATFORMS::EC2) {
            $client = $this->environment->aws($cloudLocation)->ec2;
            $r = $client->instance->describe($cloudServerId);
            $instance = $r->get(0)->instancesSet->get(0);
            $availZone = $instance->placement->availabilityZone;
            $osType = $instance->platform == 'windows' ? 'windows' : 'linux';

            $creInfo->SetProperties(array(
                EC2_SERVER_PROPERTIES::REGION => $cloudLocation,
                EC2_SERVER_PROPERTIES::INSTANCE_ID => $cloudServerId,
                EC2_SERVER_PROPERTIES::AMIID => $instance->imageId,
                EC2_SERVER_PROPERTIES::AVAIL_ZONE => $instance->placement->availabilityZone
            ));
        } else if ($platform == SERVER_PLATFORMS::GCE) {
            $gce = $platformObj->getClient($this->environment);
            $result = $gce->instances->get(
                $this->environment->keychain(SERVER_PLATFORMS::GCE)->properties[CloudCredentialsProperty::GCE_PROJECT_ID],
                $cloudLocation,
                $cloudServerId
            );

            $creInfo->SetProperties(array(
                GCE_SERVER_PROPERTIES::SERVER_NAME => $cloudServerId,
                GCE_SERVER_PROPERTIES::CLOUD_LOCATION => $cloudLocation
            ));
        } else if ($platform == SERVER_PLATFORMS::AZURE) {
            //$this->getEnvironment()->azure()->compute->virtualMachine->getInstanceViewInfo()
            // $r->properties->osProfile->linuxConfiguration != NULL

        } else if (PlatformFactory::isOpenstack($platform)) {
            $creInfo->SetProperties(array(
                OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $cloudLocation,
                OPENSTACK_SERVER_PROPERTIES::SERVER_ID => $cloudServerId
            ));
        } else if (PlatformFactory::isCloudstack($platform)) {
            $creInfo->SetProperties(array(
                CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $cloudLocation,
                CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID => $cloudServerId
            ));
        }

        $dbServer = DBServer::Create($creInfo, true);
        $dbServer->osType = $osType;

        $ips = $platformObj->GetServerIPAddresses($dbServer);
        $dbServer->localIp = $ips['localIp'];
        $dbServer->remoteIp = $ips['remoteIp'];

        $dbServer->cloudLocation = $cloudLocation;

        if ($platform == SERVER_PLATFORMS::GCE)
            $dbServer->cloudLocationZone = $cloudLocation;
        else
            $dbServer->cloudLocationZone = $availZone;

        $dbServer->Save();

        $this->response->data(array(
            'command'           => $this->getSzrCmd($dbServer),
            'installCommand'    => $this->getInstallCmd($dbServer),
            'osType'            => $dbServer->osType,
            'serverId'          => $dbServer->serverId
        ));
    }

    private function getInstallCmd(DBServer $dbServer)
    {
        $baseUrl = \Scalr::config('scalr.endpoint.scheme') . "://" . \Scalr::config('scalr.endpoint.host');

        return [
            'linux' => sprintf("curl -L \"{$baseUrl}/public/linux/latest/{$dbServer->platform}/install_scalarizr.sh\" | sudo bash"),
            'windows' => sprintf("iex ((New-Object Net.WebClient).DownloadString('{$baseUrl}/public/windows/latest/install_scalarizr.ps1'))")
        ];
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
            'scalr-id'		=> SCALR_ID/*,
            'messaging-p2p.message-format' => 'json'*/
        );

        $command = 'scalarizr --import -y';
        foreach ($options as $k => $v) {
            $command .= sprintf(' -o %s=%s', $k, $v);
        }

        return $command;
    }

    /**
     * @param   string  $platform
     * @param   string  $cloudLocation
     * @throws  Exception
     */
    public function xGetCloudServersListAction($platform, $cloudLocation)
    {
        if (!$this->environment->isPlatformEnabled($platform))
            throw new Exception(sprintf('Cloud %s is not enabled for current environment', $platform));

        $results = [];

        $platformObj = PlatformFactory::NewPlatform($platform);

        if ($platform == SERVER_PLATFORMS::GCE) {
            $gce = $platformObj->getClient($this->environment);

            $result = $gce->instances->listInstances(
                $this->environment->keychain(SERVER_PLATFORMS::GCE)->properties[CloudCredentialsProperty::GCE_PROJECT_ID],
                $cloudLocation,
                []
            );

            if (is_array($result->items)) {
                foreach ($result->items as $server) {
                    if ($server->status != 'RUNNING')
                        continue;

                    $ips = $platformObj->determineServerIps($gce, $server);
                    $itm = [
                        'id'          => $server->name,
                        'localIp'     => $ips['localIp'],
                        'publicIp'    => $ips['remoteIp'],
                        'zone'        => $cloudLocation,
                        'isImporting' => false,
                        'isManaged'   => false
                    ];

                    //Check is instance already importing
                    try {
                        $dbServer = DBServer::LoadByPropertyValue(GCE_SERVER_PROPERTIES::SERVER_NAME, $server->name);
                        if ($dbServer && $dbServer->status != SERVER_STATUS::TERMINATED) {
                            if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                                $itm['isImporting'] = true;
                            } else {
                                $itm['isManaged'] = true;
                            }
                            $itm['serverId'] = $dbServer->serverId;
                        }
                    } catch (Exception $e) {
                    }

                    $results[] = $itm;
                }
            }
        } else if ($platform == SERVER_PLATFORMS::AZURE) {
            // cloudLocation is resourceGroup
            $t = $this->getEnvironment()
                      ->azure()
                      ->compute
                      ->virtualMachine
                      ->getList(
                          $this->getEnvironment()
                               ->keychain(SERVER_PLATFORMS::AZURE)
                               ->properties[CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                          $cloudLocation
                      );
            foreach ($t as $server) {
                $itm = [
                    'id' => $server->name,
                    'isImporting' => false,
                    'isManaged' => false
                ];

                $nicInfo = $server->properties->networkProfile->networkInterfaces[0]->id; // get id and call
                if (!empty($nicInfo->properties->ipConfigurations)) {
                    foreach ($nicInfo->properties->ipConfigurations as $ipConfig) {
                        $privateIp = $ipConfig->properties->privateIPAddress;
                        if ($ipConfig->properties->publicIPAddress) {
                            $publicIp = $ipConfig->properties->publicIPAddress->properties->ipAddress;
                            if ($publicIp)
                                break;
                        }
                    }
                }

                $itm['localIp'] = $privateIp;
                $itm['publicIp'] = $publicIp;
                $itm['zone'] = $server->location;

                $results[] = $itm;
            }
        } elseif (PlatformFactory::isOpenstack($platform)) {
            $client = $this->environment->openstack($platform, $cloudLocation);
            $r = $client->servers->list(true);
            do {
                foreach ($r as $server) {
                    if ($server->status != 'ACTIVE')
                        continue;

                    $ips = $platformObj->determineServerIps($client, $server);

                    $itm = array(
                        'id' => $server->id,
                        'localIp' => $ips['localIp'],
                        'publicIp' => $ips['remoteIp'],
                        'zone' => $cloudLocation,
                        'isImporting' => false,
                        'isManaged' => false,
                        'fullInfo' => $server
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
                    } catch (Exception $e) {
                    }

                    $results[] = $itm;
                }
            } while (false !== ($r = $r->getNextPage()));
        } elseif (PlatformFactory::isCloudstack($platform)) {
            $client = $this->environment->cloudstack($platform);
            $platformObj = PlatformFactory::NewPlatform($platform);

            $r = $client->instance->describe(array('zoneid' => $cloudLocation));
            if (count($r) > 0) {
                foreach ($r as $server) {
                    $ips = $platformObj->determineServerIps($client, $server);

                    $itm = array(
                        'id' => $server->id,
                        'localIp' => $ips['localIp'],
                        'publicIp' => $ips['remoteIp'],
                        'zone' => $cloudLocation,
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
            }
        } elseif ($platform == SERVER_PLATFORMS::EC2) {
            $client = $this->environment->aws($cloudLocation)->ec2;
            $nextToken = null;

            do {
                if (isset($r)) {
                    $nextToken = $r->getNextToken();
                }
                $r = $client->instance->describe(null, null, $nextToken);
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
            } while ($r->getNextToken());
        }

        $this->response->data(array(
            'data' => $results
        ));
    }

    /**
     * Prepare imporing of either new role and/or image
     *
     * @param  string $serverId      optional
     * @param  string $cloudServerId optional
     * @param  string $platform      optional
     * @param  string $cloudLocation optional
     * @throws Exception
     */
    public function importAction($serverId = null, $cloudServerId = null, $platform = null, $cloudLocation = null)
    {
        $unsupportedPlatforms = [SERVER_PLATFORMS::AZURE];
        $platforms = [];
        foreach ($this->environment->getEnabledPlatforms() as $plt) {
            if (!in_array($plt, $unsupportedPlatforms)) {
                $platforms[] = $plt;
            }
        }

        if (empty($platforms)) {
            return $this->response->failure('Please <a href="#/account/environments?envId='.$this->getEnvironmentId().'">configure cloud credentials</a> before importing roles.', true);
        }

        if (!empty($platform) && !in_array($platform, $platforms)) {
            return $this->response->failure(sprintf("Platform '%s' doesn't support importing", $platform));
        }

        $orphan = $server = $command = null;

        if (!empty($serverId)) {
            $dbServer = DBServer::LoadByID($serverId);
            $this->user->getPermissions()->validate($dbServer);

            if ($dbServer->status != SERVER_STATUS::IMPORTING) {
                throw new Exception('Server is not in importing state');
            }

            $command = $this->getSzrCmd($dbServer);
            $step = $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_STEP);
            $server = [
                "localIp"       => $dbServer->localIp,
                "remoteIp"      => $dbServer->remoteIp,
                "cloudServerId" => $dbServer->GetCloudServerID(),
                "cloudLocation" => $dbServer->GetCloudLocation(),
                "platform"      => $dbServer->platform,
                "object"        => $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_OBJECT),
                "roleName"      => $dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_ROLE_NAME)
            ];
        } elseif (!empty($cloudServerId)) {
            $orphan = compact("cloudServerId", "platform", "cloudLocation");
        }

        $this->response->page('ui/roles/import/view.js', [
            "platforms" => $platforms,
            "command"   => $command,
            "step"      => $step,
            "server"    => $server,
            "orphan"    => $orphan
        ], ['ui/bundletasks/view.js'], ['ui/roles/import/view.css']);
    }

    public function xGetBundleTaskDataAction()
    {
        $task = BundleTask::LoadById($this->getParam('bundleTaskId'));
        $this->user->getPermissions()->validate($task);

        $logs = $this->db->GetAll("SELECT * FROM bundle_task_log WHERE bundle_task_id = " . $this->db->qstr($this->getParam('bundleTaskId')) . ' ORDER BY id DESC LIMIT 3');
        foreach ($logs as &$row) {
            $row['dtadded'] = Scalr_Util_DateTime::convertTz($row['dtadded']);
        }

        $image = $task->getImageEntity();

        $this->response->data(array(
            'status'        => $task->status,
            'failureReason' => $task->failureReason,
            'logs'          => $logs,
            'roleId'        => $task->roleId,
            'roleName'      => $task->roleName,
            'platform'      => $task->platform,
            'imageId'       => $task->snapshotId,
            'imageHash'     => !empty($image) ? $image->hash : null
        ));
    }

}
