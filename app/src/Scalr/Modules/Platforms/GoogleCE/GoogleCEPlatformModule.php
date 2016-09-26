<?php

namespace Scalr\Modules\Platforms\GoogleCE;

use DBServer;
use BundleTask;
use Exception;
use Google_Client;
use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Modules\Platforms\GoogleCE\Adapters\StatusAdapter;
use Scalr\Modules\AbstractPlatformModule;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\SshKey;
use Scalr\Model\Entity\CloudLocation;
use Scalr\Model\Entity;
use Scalr\Modules\Platforms\GoogleCE\Exception\InstanceNotFoundException;
use Scalr\Farm\Role\FarmRoleStorageConfig;
use GCE_SERVER_PROPERTIES;
use Scalr\Farm\Role\FarmRoleStorage;
use Scalr_Environment;
use SERVER_PLATFORMS;

class GoogleCEPlatformModule extends AbstractPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{

    const CLIENT_ID 			= 'gce.client_id';
    const SERVICE_ACCOUNT_NAME	= 'gce.service_account_name';
    const KEY					= 'gce.key';
    const PROJECT_ID			= 'gce.project_id';
    const ACCESS_TOKEN			= 'gce.access_token';
    const JSON_KEY				= 'gce.json_key';

    const RESOURCE_BASE_URL = 'https://www.googleapis.com/compute/v1/projects/';

    public $instancesListCache;

    /**
     * @param \Scalr_Environment $environment             Scalr Environment object
     * @param array              $config        optional  Config array
     * @return \Google_Service_Compute
     */
    public function getClient(\Scalr_Environment $environment = null, array $config = [])
    {
        $ccProps = null;
        if (empty($config)) {
            $ccProps = $environment->keychain(\SERVER_PLATFORMS::GCE)->properties;
            $config = $ccProps;
        }

        $client = new \Google_Client();
        $client->setApplicationName("Scalr GCE");
        $client->setScopes(array('https://www.googleapis.com/auth/compute'));

        $key = base64_decode($config[Entity\CloudCredentialsProperty::GCE_KEY]);
        // If it's not a json key we need to convert PKCS12 to PEM
        if (!$config[Entity\CloudCredentialsProperty::GCE_JSON_KEY]) {
            @openssl_pkcs12_read($key, $certs, 'notasecret');
            $key = $certs['pkey'];
        }

        $client->setAuthConfig([
            'type' => 'service_account',
            'project_id' => $config[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
            'private_key' => $key,
            'client_email' => $config[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME],
            'client_id' => $config[Entity\CloudCredentialsProperty::GCE_CLIENT_ID]
        ]);

        $client->setClientId($config[Entity\CloudCredentialsProperty::GCE_CLIENT_ID]);

        $gce = new \Google_Service_Compute($client);

        //**** Store access token ****//
        $jsonAccessToken = $config[Entity\CloudCredentialsProperty::GCE_ACCESS_TOKEN];
        $accessToken = @json_decode($jsonAccessToken);
        if ($accessToken && $accessToken->created+$accessToken->expires_in > time())
            $client->setAccessToken($jsonAccessToken);
        else {
            $gce->zones->listZones($config[Entity\CloudCredentialsProperty::GCE_PROJECT_ID]);

            if ($ccProps) {
                $token = $client->getAccessToken();
                $ccProps[Entity\CloudCredentialsProperty::GCE_ACCESS_TOKEN] = $token;
                $ccProps->save();
            }
        }

        return $gce;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getLocations()
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        $retval = [];

        if ($environment && $environment->isPlatformEnabled(\SERVER_PLATFORMS::GCE)) {
            try {
                $client = $this->getClient($environment);

                $zones = $client->zones->listZones($environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID]);

                foreach ($zones->getItems() as $zone) {
                    if ($zone->status == 'UP')
                        $retval[$zone->getName()] = $zone->getName();
                }

            } catch (Exception $e) {}
        }

        return $retval;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::hasCloudPrices()
     */
    public function hasCloudPrices(\Scalr_Environment $env)
    {
        if (!$this->container->analytics->enabled) return false;

        return $this->container->analytics->prices->hasPriceForUrl(
            \SERVER_PLATFORMS::GCE, ''
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerCloudLocation()
     */
    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::CLOUD_LOCATION);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerID()
     */
    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::IsServerExists()
     */
    public function IsServerExists(DBServer $DBServer, $debug = false)
    {
        return in_array(
            $DBServer->serverId,
            array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->cloudLocation, true))
        );
    }

    public function determineServerIps($client, $server)
    {
        $network = $server->getNetworkInterfaces();

        return array(
            'localIp'	=> $network[0]->networkIP,
            'remoteIp'	=> $network[0]->accessConfigs[0]->natIP
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $serverId = $DBServer->GetCloudServerID();
        $cacheKey = sprintf('%s:%s', $DBServer->envId, $DBServer->cloudLocation);

        if (!isset($this->instancesListCache[$cacheKey][$serverId])) {
            $gce = $this->getClient($DBServer->GetEnvironmentObject());

            $result = $gce->instances->get(
                $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $DBServer->GetCloudLocation(),
                $serverId
            );

            $network = $result->getNetworkInterfaces();

            $this->instancesListCache[$cacheKey][$serverId] = [
                'localIp'    => $network[0]->networkIP,
                'remoteIp'   => $network[0]->accessConfigs[0]->natIP,
                'status'     => $result->status,
                'type'       => $this->getObjectName($result->machineType),
                '_timestamp' => time()
            ];
        }

        return array(
            'localIp'	=> $this->instancesListCache[$cacheKey][$serverId]['localIp'],
            'remoteIp'	=> $this->instancesListCache[$cacheKey][$serverId]['remoteIp']
        );
    }

    public function GetServersList(\Scalr_Environment $environment, $cloudLocation, $skipCache = false)
    {
        $cacheKey = sprintf('%s:%s', $environment->id, $cloudLocation);

        if (!isset($this->instancesListCache[$cacheKey]) || $skipCache) {
            $cacheValue = array();

            $gce = $this->getClient($environment);

            $pageToken = false;
            $cnt = 0;

            while (true) {
                $opts = $pageToken ? ['pageToken' => $pageToken] : [];

                $result = $gce->instances->listInstances(
                    $environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                    $cloudLocation,
                    $opts
                );

                if (is_array($result->items)) {
                    foreach ($result->items as $server) {
                        $network = $server->getNetworkInterfaces();

                        $cacheValue[$cacheKey][$server->name] = [
                            'localIp'    => $network[0]->networkIP,
                            'remoteIp'   => $network[0]->accessConfigs[0]->natIP,
                            'status'     => $server->status,
                            'type'       => $this->getObjectName($server->machineType),
                            '_timestamp' => time()
                        ];
                    }
                }

                $pageToken = $result->getNextPageToken();

                if (!$pageToken) {
                    break;
                }

                $cnt++;

                if ($cnt == 10) {
                    throw new Exception("Deadloop detected in GCE module");
                }
            }

            foreach ($cacheValue as $offset => $value) {
                $this->instancesListCache[$offset] = $value;
            }
        }

        return isset($this->instancesListCache[$cacheKey]) ? $this->instancesListCache[$cacheKey] : [];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerRealStatus()
     */
    public function GetServerRealStatus(DBServer $DBServer)
    {
        $cloudLocation = $DBServer->cloudLocation;
        $environment = $DBServer->GetEnvironmentObject();
        $cacheKey = sprintf('%s:%s', $environment->id, $cloudLocation);

        $operationId = $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::PROVISIONING_OP_ID);

        $iid = $DBServer->GetCloudServerID();
        if (!$iid) {
            $status = 'not-found';
        } elseif (!isset($this->instancesListCache[$cacheKey][$iid])) {
            $gce = $this->getClient($environment);
            $projectId = $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

            try {
                $result = $gce->instances->get(
                    $projectId,
                    $cloudLocation,
                    $DBServer->serverId
                );
                $status = $result->status;
            }
            catch(Exception $e)
            {
                if (stristr($e->getMessage(), "not found"))
                    $status = 'not-found';
                else
                    throw $e;
            }

            if ($status == 'not-found') {
                if ($operationId) {
                    try {
                        $info = $gce->zoneOperations->get(
                            $projectId,
                            $cloudLocation,
                            $operationId
                        );
                        if ($info->status != 'DONE')
                            $status = 'PROVISIONING';
                    } catch (Exception $e) {
                        \Scalr::getContainer()->logger("GCE")->info("GCE: operation was not found: {$operationId}, ServerID: {$DBServer->serverId}, ServerStatus: {$DBServer->status}");
                    }
                } else {
                    if ($DBServer->status == \SERVER_STATUS::PENDING)
                        $status = 'PROVISIONING';
                    else
                        \Scalr::getContainer()->logger("GCE")->error("GCE: OPID: {$operationId}, ServerID: {$DBServer->serverId}, ServerStatus: {$DBServer->status}");
                }
            }
        } else {
            $status = $this->instancesListCache[$cacheKey][$iid]['status'];
        }

        return StatusAdapter::load($status);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::ResumeServer()
     */
    public function ResumeServer(DBServer $DBServer)
    {
        $gce = $this->getClient($DBServer->GetEnvironmentObject());

        //Check status and allow to resume only if instance is really stopped
        $status = $this->GetServerRealStatus($DBServer);
        if (!$status->isSuspended()) {
            throw new Exception(sprintf("The instance '%s' is not in a state from which it can be started. Please try again in a couple minutes.",
                $DBServer->serverId
            ));
        }

        try {
            $gce->instances->start(
                $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $DBServer->GetCloudLocation(),
                $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME)
            );
        } catch (Exception $e) {
            if (stristr($e->getMessage(), "not found")) {
                throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }

        parent::ResumeServer($DBServer);

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::SuspendServer()
     */
    public function SuspendServer(DBServer $DBServer)
    {
        $gce = $this->getClient($DBServer->GetEnvironmentObject());

        try {
            $gce->instances->stop(
                $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $DBServer->GetCloudLocation(),
                $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME)
            );
        } catch (Exception $e) {
            if (stristr($e->getMessage(), "not found")) {
                throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::TerminateServer()
     */
    public function TerminateServer(DBServer $DBServer)
    {
        $gce = $this->getClient($DBServer->GetEnvironmentObject());

        try {
            $gce->instances->delete(
                $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $DBServer->GetCloudLocation(),
                $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME)
            );
        } catch (Exception $e) {
            if (stristr($e->getMessage(), "not found")) {
                throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RebootServer()
     */
    public function RebootServer(DBServer $DBServer, $soft = true)
    {
        $gce = $this->getClient($DBServer->GetEnvironmentObject());

        try {
            $gce->instances->reset(
                $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $DBServer->GetCloudLocation(),
                $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME)
            );
        } catch (Exception $e) {
            if (stristr($e->getMessage(), "not found")) {
                throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RemoveServerSnapshot()
     */
    public function RemoveServerSnapshot(Image $image)
    {
        if (! $image->getEnvironment())
            return true;

        $gce = $this->getClient($image->getEnvironment());

        try {

            $projectId = $image->getEnvironment()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];
            $imageId = str_replace("{$projectId}/images/", "", $image->id);

            $gce->images->delete($projectId, $imageId);
        } catch(Exception $e) {
            if (stristr($e->getMessage(), "was not found"))
                return true;
            else
                throw $e;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CheckServerSnapshotStatus()
     */
    public function CheckServerSnapshotStatus(BundleTask $BundleTask)
    {
        if ($BundleTask->status != \SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS)
            return;

        if ($BundleTask->osFamily != 'windows')
            return;

        $meta = $BundleTask->getSnapshotDetails();

        $env = \Scalr_Environment::init()->loadById($BundleTask->envId);
        $gce = $this->getClient($env);
        $projectId = $env->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

        if ($meta['gceSnapshotOpPhase3Id']) {
            try {
                $op3 = $gce->globalOperations->get(
                    $projectId,
                    $meta['gceSnapshotOpPhase3Id']
                );

                if ($op3->status == 'DONE') {
                    $BundleTask->SnapshotCreationComplete($BundleTask->snapshotId, $meta);
                } else {
                    $BundleTask->Log("CreateImage operation status: {$op3->status}");
                }

            } catch (Exception $e) {
                $BundleTask->Log("CheckServerSnapshotStatus(2): {$e->getMessage()}");
                return;
            }

        } else {
            //Check operations status
            try {
                $op1 = $gce->zoneOperations->get(
                    $projectId,
                    $meta['gceSnapshotZone'],
                    $meta['gceSnapshotOpPhase1Id']
                );

                $op2 = $gce->zoneOperations->get(
                    $projectId,
                    $meta['gceSnapshotZone'],
                    $meta['gceSnapshotOpPhase2Id']
                );


            } catch (Exception $e) {
                $BundleTask->Log("CheckServerSnapshotStatus(1): {$e->getMessage()}");
                return;
            }

            if ($op1->status == 'DONE' && $op2->status == 'DONE') {
                try {
                    // identifier of google cloud resource must start from [a-z]
                    $imageName = (preg_match('/^[^a-z]/', $BundleTask->roleName) ? 'i' : '') .
                        $BundleTask->roleName . '-' . date('YmdHi');

                    $postBody = new \Google_Service_Compute_Image();
                    $postBody->setName($imageName);
                    $postBody->setSourceDisk(
                        $this->getObjectUrl(
                            $meta['gceSnapshotDeviceName'],
                            'disks',
                            $projectId,
                            $meta['gceSnapshotZone']
                        )
                    );

                    $op3 = $gce->images->insert($projectId, $postBody);
                    $BundleTask->setMetaData(array(
                        'gceSnapshotOpPhase3Id' => $op3->name,
                        'gceSnapshotTargetLink' => $op3->targetLink
                    ));
                    $BundleTask->snapshotId = "{$projectId}/global/images/{$this->getObjectName($op3->targetLink)}";

                    $BundleTask->Log(sprintf(_("Snapshot initialized (ID: %s). Operation: {$op3->name}"),
                        $BundleTask->snapshotId
                    ));

                    $BundleTask->Save();
                } catch (Exception $e) {
                    $BundleTask->Log("CheckServerSnapshotStatus(3): {$e->getMessage()}");
                }
            } else {
                $BundleTask->Log("CheckServerSnapshotStatus(0): {$op1->status}:{$op2->status}");
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CreateServerSnapshot()
     */
    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        $DBServer = DBServer::LoadByID($BundleTask->serverId);
        if ($BundleTask->osFamily == 'windows' || $DBServer->osType == 'windows') {
            $BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::GCE_WINDOWS;
            $BundleTask->status = \SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;

            $gce = $this->getClient($DBServer->GetEnvironmentObject());
            $projectId = $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

            //Set root disk auto-remove to false
            try {
                $instance = $gce->instances->get(
                    $projectId,
                    $DBServer->GetCloudLocation(),
                    $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME)
                );

                $disks = $instance->getDisks();
                if (!count($disks)) {
                    throw new Exception('No disks were found');
                }

                $op1 = $gce->instances->setDiskAutoDelete(
                    $projectId,
                    $DBServer->GetCloudLocation(),
                    $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME),
                    false,
                    $disks[0]['deviceName']
                );

                $BundleTask->Log("Calling setDiskAutoDelete(false) for root device. Operation: {$op1->name}");

            } catch (Exception $e) {
                $BundleTask->Log("Unable to perform setDiskAutoDelete(false) for ROOT device: ". $e->getMessage());
            }
            //TODO: Check operation status
            sleep(2);

            // Kill VM
            try {
                $op2 = $gce->instances->delete(
                    $projectId,
                    $DBServer->GetCloudLocation(),
                    $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME)
                );

                $BundleTask->Log("Terminating VM. Operation: {$op2->name}");

            } catch (Exception $e) {
                if (stristr($e->getMessage(), "not found")) {

                } else {
                    $BundleTask->Log("Unable to terminate VM: ". $e->getMessage());
                }
            }

            $BundleTask->setMetaData(array(
                'gceSnapshotOpPhase1Id' => $op1->name,
                'gceSnapshotOpPhase2Id' => $op2->name,
                'gceSnapshotZone'       => $DBServer->cloudLocationZone,
                'gceSnapshotDeviceName' => $this->getObjectName($disks[0]['source'])
            ));
        } else {
            $BundleTask->status = \SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
            $BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::GCE_STORAGE;

            // identifier of google cloud resource must start from [a-z]
            $imageName = (preg_match('/^[^a-z]/', $BundleTask->roleName) ? 'i' : '') . $BundleTask->roleName;

            $msg = new \Scalr_Messaging_Msg_Rebundle(
                $BundleTask->id,
                $imageName,
                array()
            );

            if (!$DBServer->SendMessage($msg)) {
                $BundleTask->SnapshotCreationFailed("Cannot send rebundle message to server. Please check event log for more details.");
                return;
            } else {
                $BundleTask->Log(sprintf(_("Snapshot creating initialized (MessageID: %s). Bundle task status changed to: %s"),
                    $msg->messageId, $BundleTask->status
                ));
            }
        }

        $BundleTask->setDate('started');
        $BundleTask->Save();
    }

    private function ApplyAccessData(\Scalr_Messaging_Msg $msg)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerConsoleOutput()
     */
    public function GetServerConsoleOutput(DBServer $DBServer)
    {
        $gce = $this->getClient($DBServer->GetEnvironmentObject());

        try {
            $retval = $gce->instances->getSerialPortOutput(
                $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $DBServer->GetCloudLocation(),
                $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME)
            );

            $contents = $retval->getContents();
        } catch (Exception $e) {
            $contents = $e->getMessage();
        }

        // Check for errors
        $json = @json_decode($contents);
        if ($json != null) {
            if ($json->error) {
                throw new Exception($json->error->message);
            }
        }

        return base64_encode($contents);
    }

    public function getObjectUrl($objectName, $objectType, $projectName, $cloudLocation = null)
    {
        if ($objectType == 'images') {
            if (!stristr($objectName, "/global"))
                return str_replace($projectName, "{$projectName}/global", self::RESOURCE_BASE_URL."{$objectName}");
            else
                return self::RESOURCE_BASE_URL."{$objectName}";
        } elseif ($objectType == 'machineTypes' || $objectType == 'disks' || $objectType == 'diskTypes') {
            return self::RESOURCE_BASE_URL."{$projectName}/zones/{$cloudLocation}/{$objectType}/{$objectName}";
        } elseif ($objectType == 'subnetworks') {
            return self::RESOURCE_BASE_URL . "{$projectName}/regions/{$cloudLocation}/{$objectType}/{$objectName}";
        } elseif ($objectType == 'regions' || $objectType == 'zones') {
            return self::RESOURCE_BASE_URL."{$projectName}/{$objectType}/{$objectName}";
        } else {
            return self::RESOURCE_BASE_URL."{$projectName}/global/{$objectType}/{$objectName}";
        }
    }

    public function getObjectName($objectURL)
    {
        return substr($objectURL, strrpos($objectURL, "/")+1);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerExtendedInformation()
     */
    public function GetServerExtendedInformation(DBServer $DBServer, $extended = false)
    {
        try {
            $gce = $this->getClient($DBServer->GetEnvironmentObject());

            $info = $gce->instances->get(
                $DBServer->GetEnvironmentObject()->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                $DBServer->GetCloudLocation(),
                $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::SERVER_NAME)
            );

            if ($info) {
                $network = $info->getNetworkInterfaces();

                return array(
                    'Cloud Server ID'		=> $info->id,
                    'Image ID'				=> $this->getObjectName($info->image),
                    'Machine Type'			=> $this->getObjectName($info->machineType),
                    'Public IP'				=> $network[0]->accessConfigs[0]->natIP,
                    'Private IP'			=> $network[0]->networkIP,
                    'Status'				=> $info->status,
                    'Name'					=> $info->name,
                    'Zone'					=> $this->getObjectName($info->zone)
                );
            }
        } catch(Exception $e) {
            if (stristr($e->getMessage(), "not found")) {
                return false;
            } else {
                throw $e;
            }
        }

        return false;
    }

    /**
     *
     * @param \Scalr_Environment $environment
     * @param string $operationId
     * @param string $cloudLocation
     * @param string $scope
     * @return Google_Service_Compute_Operation $operation
     */
    public function GetAsyncOperationStatus(\Scalr_Environment $environment, $operationId, $cloudLocation, $scope = 'zones')
    {
        $projectName = $environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];
        $gce = $this->getClient($environment);


        if ($scope == 'zones') {
            $operation = $gce->zoneOperations->get($projectName, $cloudLocation, $operationId);
        } elseif ($scope == 'regions') {
            $operation = $gce->regionOperations->get($projectName, $cloudLocation, $operationId);
        }

        return $operation;
    }

    public function LaunchServer(DBServer $DBServer, \Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $environment = $DBServer->GetEnvironmentObject();
        $ccProps = $environment->keychain(SERVER_PLATFORMS::GCE)->properties;
        $governance = new \Scalr_Governance($environment->id);

        $rootDeviceSettings = null;
        $ssdDisks = array();
        $scopes = [
            "https://www.googleapis.com/auth/userinfo.email",
            "https://www.googleapis.com/auth/compute",
            "https://www.googleapis.com/auth/devstorage.full_control"
        ];

        if (!$launchOptions) {
            $launchOptions = new \Scalr_Server_LaunchOptions();
            $DBRole = $DBServer->GetFarmRoleObject()->GetRoleObject();

            $launchOptions->imageId = $DBRole->__getNewRoleObject()->getImage(\SERVER_PLATFORMS::GCE, $DBServer->GetProperty(\GCE_SERVER_PROPERTIES::CLOUD_LOCATION))->imageId;
            $launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::INSTANCE_TYPE);
            $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

            $userData = $DBServer->GetCloudUserData();

            $launchOptions->architecture = 'x86_64';

            $networkName = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::GCE_NETWORK);
            $subnet = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::GCE_SUBNET);

            $onHostMaintenance = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::GCE_ON_HOST_MAINTENANCE);

            $osType = ($DBRole->getOs()->family == 'windows') ? 'windows' : 'linux';

            $rootDevice = json_decode($DBServer->GetFarmRoleObject()->GetSetting(\Scalr_Role_Behavior::ROLE_BASE_ROOT_DEVICE_CONFIG), true);
            if ($rootDevice && $rootDevice['settings'])
                $rootDeviceSettings = $rootDevice['settings'];

            $storage = new FarmRoleStorage($DBServer->GetFarmRoleObject());
            $volumes = $storage->getVolumesConfigs($DBServer);

            if (!empty($volumes)) {
                foreach ($volumes as $volume) {
                    if ($volume->type == FarmRoleStorageConfig::TYPE_GCE_EPHEMERAL)
                        array_push($ssdDisks, $volume);
                }
            }

            if ($governance->isEnabled(\Scalr_Governance::CATEGORY_GENERAL, \Scalr_Governance::GENERAL_HOSTNAME_FORMAT)) {
                $hostNameFormat = $governance->getValue(\Scalr_Governance::CATEGORY_GENERAL, \Scalr_Governance::GENERAL_HOSTNAME_FORMAT);
            } else {
                $hostNameFormat = $DBServer->GetFarmRoleObject()->GetSetting(\Scalr_Role_Behavior::ROLE_BASE_HOSTNAME_FORMAT);
            }
            $hostname = (!empty($hostNameFormat)) ? $DBServer->applyGlobalVarsToValue($hostNameFormat) : '';
            if ($hostname != '') {
                $DBServer->SetProperty(\Scalr_Role_Behavior::SERVER_BASE_HOSTNAME, $hostname);
            }

            $userScopes = json_decode($DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::GCE_INSTANCE_PERMISSIONS));
            if (!empty($userScopes) && is_array($userScopes)) {
                $scopes = array_merge($scopes, $userScopes);
            }
        } else {
            $userData = array();
            $networkName = 'default';
            $osType = 'linux';
            $hostname = '';
        }

        if (!$onHostMaintenance)
            $onHostMaintenance = 'MIGRATE';

        if ($DBServer->status == \SERVER_STATUS::TEMPORARY)
            $keyName = "SCALR-ROLESBUILDER-".SCALR_ID;
        else
            $keyName = "FARM-{$DBServer->farmId}-".SCALR_ID;

        $sshKey = (new SshKey())->loadGlobalByName($DBServer->envId, \SERVER_PLATFORMS::GCE, "", $keyName);
        if (!$sshKey) {
            $sshKey = new SshKey();
            $keys = $sshKey->generateKeypair();
            if ($keys['public']) {
                $sshKey->farmId = $DBServer->farmId;
                $sshKey->envId = $DBServer->envId;
                $sshKey->type = SshKey::TYPE_GLOBAL;
                $sshKey->platform = \SERVER_PLATFORMS::GCE;
                $sshKey->cloudLocation = "";
                $sshKey->cloudKeyName = $keyName;
                $sshKey->save();

                $publicKey = $keys['public'];
            } else {
                throw new Exception("Scalr unable to generate ssh keypair");
            }
        } else {
            $publicKey = $sshKey->publicKey;
        }

        $gce = $this->getClient($environment);
        $projectId = $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

        // Check firewall
        $firewalls = $gce->firewalls->listFirewalls($projectId);
        $firewallFound = false;
        foreach ($firewalls->getItems() as $f) {
            if ($f->getName() == 'scalr-system') {
                $firewallFound = true;
                break;
            }
        }

        // Create scalr firewall
        if (!$firewallFound) {
            $firewall = new \Google_Service_Compute_Firewall();
            $firewall->setName('scalr-system');
            $firewall->setNetwork($this->getObjectUrl(
                $networkName,
                'networks',
                $projectId
            ));

            //Get scalr IP-pool IP list and set source ranges
            $firewall->setSourceRanges(\Scalr::config('scalr.aws.ip_pool'));

            // Set ports
            $tcp = new \Google_Service_Compute_FirewallAllowed();
            $tcp->setIPProtocol('tcp');
            $tcp->setPorts(array('1-65535'));
            $udp = new \Google_Service_Compute_FirewallAllowed();
            $udp->setIPProtocol('udp');
            $udp->setPorts(array('1-65535'));
            $firewall->setAllowed(array($tcp, $udp));

            // Set target tags
            $firewall->setTargetTags(array('scalr'));

            $gce->firewalls->insert($projectId, $firewall);
        }

        $instance = new \Google_Service_Compute_Instance();
        $instance->setKind("compute#instance");


        // Set scheduling
        $scheduling = new \Google_Service_Compute_Scheduling();
        $scheduling->setAutomaticRestart(true);
        $scheduling->setOnHostMaintenance($onHostMaintenance);
        $instance->setScheduling($scheduling);

        $accessConfig = new \Google_Service_Compute_AccessConfig();
        $accessConfig->setName("External NAT");
        $accessConfig->setType("ONE_TO_ONE_NAT");

        $network = new \Google_Service_Compute_NetworkInterface();
        $network->setNetwork($this->getObjectUrl(
            $networkName,
            'networks',
            $projectId
        ));

        if (!empty($subnet)) {
            $network->setSubnetwork($this->getObjectUrl(
                $subnet,
                'subnetworks',
                $projectId,
                $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::GCE_REGION)
            ));
        }

        $network->setAccessConfigs(array($accessConfig));
        $instance->setNetworkInterfaces(array($network));

        $serviceAccount = new \Google_Service_Compute_ServiceAccount();
        $serviceAccount->setEmail("default");

        $serviceAccount->setScopes($scopes);
        $instance->setServiceAccounts(array($serviceAccount));

        if ($launchOptions->cloudLocation != 'x-scalr-custom') {
            $availZone = $launchOptions->cloudLocation;
        } else {
            $location = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::GCE_CLOUD_LOCATION);

            $availZones = array();
            if (stristr($location, "x-scalr-custom")) {
                $zones = explode("=", $location);
                foreach (explode(":", $zones[1]) as $zone)
                    if ($zone != "")
                    array_push($availZones, $zone);
            }

            sort($availZones);
            $availZones = array_reverse($availZones);

            $servers = $DBServer->GetFarmRoleObject()->GetServersByFilter(array("status" => array(
                \SERVER_STATUS::RUNNING,
                \SERVER_STATUS::INIT,
                \SERVER_STATUS::PENDING
            )));
            $availZoneDistribution = array();
            foreach ($servers as $cDbServer) {
                if ($cDbServer->serverId != $DBServer->serverId)
                    $availZoneDistribution[$cDbServer->GetProperty(\GCE_SERVER_PROPERTIES::CLOUD_LOCATION)]++;
            }

            $sCount = 1000000;
            foreach ($availZones as $zone) {
                if ((int)$availZoneDistribution[$zone] <= $sCount) {
                    $sCount = (int)$availZoneDistribution[$zone];
                    $availZone = $zone;
                }
            }

            $aZones = implode(",", $availZones); // Available zones
            $dZones = ""; // Zones distribution
            foreach ($availZoneDistribution as $zone => $num)
                $dZones .= "({$zone}:{$num})";
        }

        $instance->setZone($this->getObjectUrl(
            $availZone,
            'zones',
            $projectId
        ));


        $instance->setMachineType($this->getObjectUrl(
            $launchOptions->serverType,
            'machineTypes',
            $projectId,
            $availZone
        ));


        //Create root disk
        $image = $this->getObjectUrl(
            $launchOptions->imageId,
            'images',
            $projectId
        );

        $disks = array();

        $diskName = "root-{$DBServer->serverId}";

        $initializeParams = new \Google_Service_Compute_AttachedDiskInitializeParams();
        $initializeParams->sourceImage = $image;
        $initializeParams->diskName = $diskName;

        if ($rootDeviceSettings) {
            $initializeParams->diskType = $this->getObjectUrl(
                $rootDeviceSettings[FarmRoleStorageConfig::SETTING_GCE_PD_TYPE] ? $rootDeviceSettings[FarmRoleStorageConfig::SETTING_GCE_PD_TYPE] : 'pd-standard',
                'diskTypes',
                $projectId,
                $availZone
            );

            $initializeParams->diskSizeGb = $rootDeviceSettings[FarmRoleStorageConfig::SETTING_GCE_PD_SIZE];
        }

        $attachedDisk = new \Google_Service_Compute_AttachedDisk();
        $attachedDisk->setKind("compute#attachedDisk");
        $attachedDisk->setBoot(true);
        $attachedDisk->setMode("READ_WRITE");
        $attachedDisk->setType("PERSISTENT");
        $attachedDisk->setDeviceName("root");
        $attachedDisk->setAutoDelete(true);
        $attachedDisk->setInitializeParams($initializeParams);
        array_push($disks, $attachedDisk);

        if (count($ssdDisks) > 0) {
            foreach ($ssdDisks as $disk) {
                $attachedDisk = new \Google_Service_Compute_AttachedDisk();
                $attachedDisk->setKind("compute#attachedDisk");
                $attachedDisk->setBoot(false);
                $attachedDisk->setMode("READ_WRITE");
                $attachedDisk->setType("SCRATCH");
                $attachedDisk->setDeviceName(str_replace("google-", "", $disk->name));
                $attachedDisk->setInterface('SCSI');
                $attachedDisk->setAutoDelete(true);

                $initializeParams = new \Google_Service_Compute_AttachedDiskInitializeParams();
                $initializeParams->diskType = $this->getObjectUrl(
                    'local-ssd',
                    'diskTypes',
                    $projectId,
                    $availZone
                );

                $attachedDisk->setInitializeParams($initializeParams);
                array_push($disks, $attachedDisk);
            }
        }


        $instance->setDisks($disks);

        $instance->setName($DBServer->serverId);

        $tags = array(
            'scalr',
            "env-{$DBServer->envId}"
        );

        if ($DBServer->farmId)
            $tags[] = "farm-{$DBServer->farmId}";

        if ($DBServer->farmRoleId)
            $tags[] = "farmrole-{$DBServer->farmRoleId}";

        $gTags = new \Google_Service_Compute_Tags();
        $gTags->setItems($tags);

        $instance->setTags($gTags);

        $metadata = new \Google_Service_Compute_Metadata();
        $items = array();

        // Set user data
        $uData = '';

        foreach ($userData as $k=>$v)
            $uData .= "{$k}={$v};";

        $uData = trim($uData, ";");

        if ($uData) {
            $item = new \Google_Service_Compute_MetadataItems();
            $item->setKey('scalr');
            $item->setValue($uData);
            $items[] = $item;
        }

        if ($osType == 'windows') {
            // Add Windows credentials
            $item = new \Google_Service_Compute_MetadataItems();
            $item->setKey("gce-initial-windows-user");
            $item->setValue("scalr");
            $items[] = $item;

            $item = new \Google_Service_Compute_MetadataItems();
            $item->setKey("gce-initial-windows-password");
            $item->setValue(\Scalr::GenerateRandomKey(16) . rand(0,9));
            $items[] = $item;
        } else {
            // Add SSH Key
            $item = new \Google_Service_Compute_MetadataItems();
            $item->setKey("sshKeys");
            $item->setValue("scalr:{$publicKey}");
            $items[] = $item;
        }

        //Set hostname
        if ($hostname != '') {
            $item = new \Google_Service_Compute_MetadataItems();
            $item->setKey("hostname");
            $item->setValue($hostname);
            $items[] = $item;
        }


        $metadata->setItems($items);

        $instance->setMetadata($metadata);

        try {
            $result = $gce->instances->insert(
                $projectId,
                $availZone,
                $instance
            );
        } catch (Exception $e) {
            $json = json_decode($e->getMessage());

            if (!empty($json->error->message)) {
                $message = $json->error->message;
            } else {
                $message = $e->getMessage();
            }

            throw new Exception(sprintf(_("Cannot launch new instance. %s (%s, %s)"), $message, $image, $launchOptions->serverType));
        }

        if ($result->id) {
            $instanceTypeInfo = $this->getInstanceType(
                $launchOptions->serverType,
                $environment,
                $availZone
            );
            /* @var $instanceTypeInfo CloudInstanceType */
            $DBServer->SetProperties([
                \GCE_SERVER_PROPERTIES::PROVISIONING_OP_ID   => $result->name,
                \GCE_SERVER_PROPERTIES::SERVER_NAME          => $DBServer->serverId,
                \GCE_SERVER_PROPERTIES::CLOUD_LOCATION       => $availZone,
                \GCE_SERVER_PROPERTIES::CLOUD_LOCATION_ZONE  => $availZone,
                \SERVER_PROPERTIES::ARCHITECTURE             => $launchOptions->architecture,
                'debug.region'                               => $result->region,
                'debug.zone'                                 => $result->zone,
                \SERVER_PROPERTIES::INFO_INSTANCE_VCPUS      => $instanceTypeInfo ? $instanceTypeInfo->vcpus : null,
            ]);

            $DBServer->setOsType($osType);
            $DBServer->cloudLocation = $availZone;
            $DBServer->cloudLocationZone = $availZone;
            $DBServer->imageId = $launchOptions->imageId;
            $DBServer->update(['type' => $launchOptions->serverType, 'instanceTypeName' => $launchOptions->serverType]);
            // we set server history here
            $DBServer->getServerHistory()->update(['cloudServerId' => $DBServer->serverId]);

            return $DBServer;
        } else {
            throw new Exception(sprintf(_("Cannot launch new instance. %s (%s, %s)"), serialize($result), $launchOptions->imageId, $launchOptions->serverType));
        }
    }

    /**
     * @param Scalr_Environment $environment
     * @param DBServer          $DBServer
     *
     * @return object
     */
    public function GetPlatformAccessData($environment, $DBServer)
    {
        $ccProps = $environment->keychain(SERVER_PLATFORMS::GCE)->properties;

        $accessData = new \stdClass();
        $accessData->clientId = $ccProps[Entity\CloudCredentialsProperty::GCE_CLIENT_ID];
        $accessData->serviceAccountName = $ccProps[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME];
        $accessData->projectId = $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];
        $accessData->key = $ccProps[Entity\CloudCredentialsProperty::GCE_KEY];

        return $accessData;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::PutAccessData()
     */
    public function PutAccessData(DBServer $DBServer, \Scalr_Messaging_Msg $message)
    {
        $put = false;
        $put |= $message instanceof \Scalr_Messaging_Msg_Rebundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_BeforeHostUp;
        $put |= $message instanceof \Scalr_Messaging_Msg_HostInitResponse;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_PromoteToMaster;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_CreateDataBundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_CreateBackup;
        $put |= $message instanceof \Scalr_Messaging_Msg_BeforeHostTerminate;

        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_PromoteToMaster;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_CreateDataBundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_CreateBackup;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_NewMasterUp;

        if ($put) {
            $environment = $DBServer->GetEnvironmentObject();
            $message->platformAccessData = $this->GetPlatformAccessData($environment, $DBServer);
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::ClearCache()
     */
    public function ClearCache()
    {
        $this->instancesListCache = [];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceTypes()
     */
    public function getInstanceTypes(\Scalr_Environment $env = null, $cloudLocation = null, $details = false)
    {
        if (!($env instanceof \Scalr_Environment) || empty($cloudLocation)) {
            throw new \InvalidArgumentException(sprintf(
                "Method %s requires both environment object and cloudLocation to be specified.", __METHOD__
            ));
        }

        $gceClient = $this->getClient($env);

        $projectId = $env->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];

        $ret = [];
        $detailed = [];

        //Trying to retrieve instance types from the cache
        $collection = $this->getCachedInstanceTypes(\SERVER_PLATFORMS::GCE, '', $cloudLocation);

        if ($collection === false || $collection->count() == 0) {
            //No cache. Fetching data from the cloud
            $types = $gceClient->machineTypes->listMachineTypes($projectId, $cloudLocation);

            foreach ($types->items as $item) {
                $isEphemeral = (substr($item->name, -2) == '-d');

                if (!$isEphemeral) {
                    $detailed[(string)$item->name] = [
                        'name'        => (string) $item->name,
                        'description' => (string) $item->description,
                        'ram'         => (string) $item->memoryMb,
                        'vcpus'       => (string) $item->guestCpus,
                        'disk'        => (string) $item->imageSpaceGb,
                        'type'        => "HDD",
                    ];

                    if (!$details) {
                        $ret[(string)$item->name] = "{$item->name} ({$item->description})";
                    } else {
                        $ret[(string)$item->name] = $detailed[(string)$item->name];
                    }
                }
            }

            //Refreshes/creates a cache
            CloudLocation::updateInstanceTypes(\SERVER_PLATFORMS::GCE, '', $cloudLocation, $detailed);
        } else {
            //Takes data from cache
            foreach ($collection as $cloudInstanceType) {
                /* @var $cloudInstanceType \Scalr\Model\Entity\CloudInstanceType */
                if (!$details) {
                    $ret[$cloudInstanceType->instanceTypeId] = $cloudInstanceType->name . "(" . $cloudInstanceType->options->description . ")";
                } else {
                    $ret[$cloudInstanceType->instanceTypeId] = $cloudInstanceType->getProperties();
                }
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceIdPropertyName()
     */
    public function getInstanceIdPropertyName()
    {
        return GCE_SERVER_PROPERTIES::SERVER_NAME;
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getgetClientByDbServer()
     *
     * @return Google_Client
     */
    public function getHttpClient(DBServer $dbServer)
    {
        return $this->getClient($dbServer->GetEnvironmentObject())->getClient();
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getImageInfo()
     */
    public function getImageInfo(\Scalr_Environment $environment, $cloudLocation, $imageId)
    {
        /* @var $client \Google_Service_Compute */
        $client = $this->getClient($environment);
        // for global images we use another projectId
        $ind = strpos($imageId, '/global/');
        if ($ind !== false) {
            $projectId = substr($imageId, 0, $ind);
            $id = str_replace("{$projectId}/global/images/", '', $imageId);
        } else {
            $ind = strpos($imageId, '/images/');
            $projectId = $ind !== false ?
                substr($imageId, 0, $ind) :
                $environment->keychain(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_PROJECT_ID];
            $id = str_replace("{$projectId}/images/", '', $imageId);
        }

        $snap = $client->images->get($projectId, $id);

        return [
            "name"         => $snap->name,
            "size"         => $snap->diskSizeGb,
            "architecture" => "x86_64",
        ];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceType()
     */
    public function getInstanceType($instanceTypeId, \Scalr_Environment $env, $cloudLocation = null)
    {
        $cloudLocationId = CloudLocation::calculateCloudLocationId(SERVER_PLATFORMS::GCE, $cloudLocation, $this->getEndpointUrl($env));
        $cit = CloudInstanceType::findPk($cloudLocationId, $instanceTypeId);

        if ($cit === null) {
            $instanceTypes = $this->getInstanceTypes($env, $cloudLocation, true);

            if (!empty($instanceTypes[$instanceTypeId])) {
                $cit = CloudInstanceType::findPk($cloudLocationId, $instanceTypeId);
            }
        }

        return $cit;
    }

}
