<?php

namespace Scalr\Modules\Platforms\Rackspace;

use \DBServer;
use \Exception;
use \BundleTask;
use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Modules\Platforms\Rackspace\Adapters\StatusAdapter;
use Scalr\Modules\AbstractPlatformModule;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity;
use Scalr\Service\Cloud\Rackspace\Exception\InstanceNotFoundException;
use Scalr\Service\Cloud\Rackspace\Exception\NotFoundException;
use Scalr_Service_Cloud_Rackspace_CS;
use \RACKSPACE_SERVER_PROPERTIES;
use SERVER_PLATFORMS;

class RackspacePlatformModule extends AbstractPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{

    /** Properties **/
    const USERNAME 		= 'rackspace.username';
    const API_KEY		= 'rackspace.api_key';
    const IS_MANAGED	= 'rackspace.is_managed';

    public $instancesListCache = array();

    public $_tmpVar;

    /**
     * @return Scalr_Service_Cloud_Rackspace_CS
     */
    private function getRsClient(\Scalr_Environment $environment, $cloudLocation)
    {
        $ccProps = $environment->cloudCredentials("{$cloudLocation}." . SERVER_PLATFORMS::RACKSPACE);
        return \Scalr_Service_Cloud_Rackspace::newRackspaceCS(
            $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_USERNAME],
            $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_API_KEY],
            $cloudLocation
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getLocations()
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        if ($environment === null)
            return array();

        $locations = Entity\Account\EnvironmentProperty::find([['envId' => $environment->id], ['name' => Entity\Account\EnvironmentProperty::RACKSPACE_LOCATIONS]]);

        $retval = [];
        /* @var $location Entity\Account\EnvironmentProperty */
        foreach ($locations as $location)
            $retval[$location->group] = "Rackspace / {$location->group}";

        return $retval;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerCloudLocation()
     */
    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerID()
     */
    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::IsServerExists()
     */
    public function IsServerExists(DBServer $DBServer, $debug = false)
    {
        return in_array(
            $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID),
            array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER), true))
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $rsClient = $this->getRsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER));

        $result = $rsClient->getServerDetails($DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID));

        return array(
            'localIp'	=> $result->server->addresses->private[0],
            'remoteIp'	=> $result->server->addresses->public[0]
        );
    }

    public function GetServersList(\Scalr_Environment $environment, $cloudLocation, $skipCache = false)
    {
        if (!$this->instancesListCache[$environment->id] || !$this->instancesListCache[$environment->id][$cloudLocation] || $skipCache)
        {
            $rsClient = $this->getRsClient($environment, $cloudLocation);

            $result = $rsClient->listServers(true);

            $this->_tmpVar = $result;

            foreach ($result->servers as $server)
                $this->instancesListCache[$environment->id][$cloudLocation][$server->id] = $server->status;
        }

        return $this->instancesListCache[$environment->id][$cloudLocation];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerRealStatus()
     */
    public function GetServerRealStatus(DBServer $DBServer)
    {
        $cloudLocation = $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER);
        $environment = $DBServer->GetEnvironmentObject();

        $iid = $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID);
        if (!$iid) {
            $status = 'not-found';
        } elseif (!$this->instancesListCache[$environment->id][$cloudLocation][$iid]) {
            $rsClient = $this->getRsClient($environment, $cloudLocation);

            try {
                $result = $rsClient->getServerDetails($DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID));
                $status = $result->server->status;
            } catch(NotFoundException $e) {
                $status = 'not-found';
            }
        } else {
            $status = $this->instancesListCache[$environment->id][$cloudLocation][$DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID)];
        }

        return StatusAdapter::load($status);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::TerminateServer()
     */
    public function TerminateServer(DBServer $DBServer)
    {
        $rsClient = $this->getRsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER));

        try {
            $rsClient->deleteServer($DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID));
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RebootServer()
     */
    public function RebootServer(DBServer $DBServer, $soft = true)
    {
        $rsClient = $this->getRsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER));

        try {
            $rsClient->rebootServer($DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID));
        } catch (NotFoundException $e) {
            throw new InstanceNotFoundException($e->getMessage(), $e->getCode(), $e);
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

        $rsClient = $this->getRsClient($image->getEnvironment(), $image->cloudLocation);

        try {
            $rsClient->deleteImage($image->id);
        }
        catch(Exception $e)
        {
            if (stristr($e->getMessage(), "Cannot destroy a destroyed snapshot") ||
                stristr($e->getMessage(), "com.rackspace.cloud.service.servers.ItemNotFoundFault") ||
                stristr($e->getMessage(), "NotFoundException")
            )
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
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CreateServerSnapshot()
     */
    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        $DBServer = DBServer::LoadByID($BundleTask->serverId);
        $BundleTask->status = \SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
        $BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::RS_CFILES;

        $msg = new \Scalr_Messaging_Msg_Rebundle(
            $BundleTask->id,
            $BundleTask->roleName,
            array()
        );

        if (!$DBServer->SendMessage($msg))
        {
            $BundleTask->SnapshotCreationFailed("Cannot send rebundle message to server. Please check event log for more details.");
            return;
        }
        else
        {
            $BundleTask->Log(sprintf(_("Snapshot creating initialized (MessageID: %s). Bundle task status changed to: %s"),
                $msg->messageId, $BundleTask->status
            ));
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
        throw new Exception("Not supported by Rackspace");
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerExtendedInformation()
     */
    public function GetServerExtendedInformation(DBServer $DBServer, $extended = false)
    {
        try
        {
            try	{
                $rsClient = $this->getRsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER));
                $iinfo = $rsClient->getServerDetails($DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID));
            }
            catch(Exception $e){}

            if ($iinfo)
            {
                return array(
                    'Cloud Server ID'		=> $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::SERVER_ID),
                    'Image ID'				=> $iinfo->server->imageId,
                    'Flavor ID'				=> $iinfo->server->flavorId,
                    'Public IP'				=> implode(", ", $iinfo->server->addresses->public),
                    'Private IP'			=> implode(", ", $iinfo->server->addresses->private),
                    'Status'				=> $iinfo->server->status,
                    'Name'					=> $iinfo->server->name,
                    'Host ID'				=> $iinfo->server->hostId,
                    'Progress'				=> $iinfo->server->progress
                );
            }
        }
        catch(Exception $e){}

        return false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::LaunchServer()
     */
    public function LaunchServer(DBServer $DBServer, \Scalr_Server_LaunchOptions $launchOptions = null)
    {
        if (!$launchOptions)
        {
            $launchOptions = new \Scalr_Server_LaunchOptions();
            $DBRole = $DBServer->GetFarmRoleObject()->GetRoleObject();

            $launchOptions->imageId = $DBRole->__getNewRoleObject()->getImage(\SERVER_PLATFORMS::RACKSPACE, $DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER))->imageId;
            $launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::RS_FLAVOR_ID);
            $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

            $u_data = '';

            foreach ($DBServer->GetCloudUserData() as $k=>$v)
                $u_data .= "{$k}={$v};";

            $launchOptions->userData = trim($u_data, ";");

            $launchOptions->architecture = 'x86_64';
        }

        $environment = $DBServer->GetEnvironmentObject();

        $rsClient = $this->getRsClient($environment, $launchOptions->cloudLocation);
        //Cannot launch new instance. Request to Rackspace failed (Code: 413): {"overLimit":{"message":"Too many requests...","code":413,"retryAfter":"2012-03-12T09:44:56.343-05:00"}} (19641119, 4)


        try {
            $result = $rsClient->createServer(
                $DBServer->serverId,
                $launchOptions->imageId,
                $launchOptions->serverType,
                array(),
                array(
                    'path'     => ($DBServer->osType == 'windows' ? 'C:\\Program Files\\Scalarizr\\etc\\private.d\\.user-data' : '/etc/scalr/private.d/.user-data'),
                    'contents' => base64_encode($launchOptions->userData)
                )
            );
        } catch (Exception $e) {
             //\Scalr::getContainer()->logger('RACKSPACE')->fatal(json_encode(array($rsClient->lastRequestBody, $rsClient->LastResponseHeaders, $rsClient->LastResponseBody)));
             throw new Exception(sprintf(_("Cannot launch new instance. %s (%s, %s)"), $e->getMessage(), $launchOptions->imageId, $launchOptions->serverType));
        }

        if ($result->server) {
            $instanceTypeInfo = $this->getInstanceType(
                $result->server->flavorId,
                $environment,
                $launchOptions->cloudLocation
            );
            /* @var $instanceTypeInfo CloudInstanceType */
            $DBServer->SetProperties([
                \RACKSPACE_SERVER_PROPERTIES::SERVER_ID        => $result->server->id,
                \RACKSPACE_SERVER_PROPERTIES::IMAGE_ID         => $result->server->imageId,
                \RACKSPACE_SERVER_PROPERTIES::ADMIN_PASS       => $result->server->adminPass,
                \RACKSPACE_SERVER_PROPERTIES::NAME             => $DBServer->serverId,
                \RACKSPACE_SERVER_PROPERTIES::HOST_ID          => $result->server->hostId,
                \SERVER_PROPERTIES::ARCHITECTURE               => $launchOptions->architecture,
                \RACKSPACE_SERVER_PROPERTIES::DATACENTER       => $launchOptions->cloudLocation,
                \SERVER_PROPERTIES::INFO_INSTANCE_VCPUS        => $instanceTypeInfo ? $instanceTypeInfo->vcpus : null,
            ]);

            $params = ['type' => $result->server->flavorId];

            if ($instanceTypeInfo) {
                $params['instanceTypeName'] = $instanceTypeInfo->name;
            }

            $DBServer->imageId = $launchOptions->imageId;

            $DBServer->update($params);
            // we set server history here
            $DBServer->getServerHistory();

            return $DBServer;
        } else {
            throw new Exception(sprintf(_("Cannot launch new instance. %s (%s, %s)"), serialize($result), $launchOptions->imageId, $launchOptions->serverType));
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::PutAccessData()
     */
    public function PutAccessData(DBServer $DBServer, \Scalr_Messaging_Msg $message)
    {
        $put = false;
        $put |= $message instanceof \Scalr_Messaging_Msg_Rebundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_HostInitResponse;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_PromoteToMaster;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_NewMasterUp;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_CreateDataBundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_Mysql_CreateBackup;

        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_PromoteToMaster;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_CreateDataBundle;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_CreateBackup;
        $put |= $message instanceof \Scalr_Messaging_Msg_DbMsr_NewMasterUp;


        if ($put) {
            $environment = $DBServer->GetEnvironmentObject();
            $ccProps = $environment->cloudCredentials("{$DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER)}." . SERVER_PLATFORMS::RACKSPACE)->properties;
            $accessData = new \stdClass();
            $accessData->username = $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_USERNAME];
            $accessData->apiKey = $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_API_KEY];

            switch ($DBServer->GetProperty(\RACKSPACE_SERVER_PROPERTIES::DATACENTER))
            {
                case 'rs-ORD1':
                    $accessData->authHost = 'auth.api.rackspacecloud.com';
                break;

                case 'rs-LONx':
                    $accessData->authHost = 'lon.auth.api.rackspacecloud.com';
                break;
            }

            $message->platformAccessData = $accessData;
        }

    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::ClearCache()
     */
    public function ClearCache()
    {
        $this->instancesListCache = array();
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

        $cs = $this->getRsClient($env, $cloudLocation);

        $ret = array();
        foreach ($cs->listFlavors(true)->flavors as $flavor) {
            if (!$details)
                $ret[(string)$flavor->id] = sprintf('RAM: %s MB Disk: %s GB', $flavor->ram, $flavor->disk);
            else
                $ret[(string)$flavor->id] = array(
                    'name' => (string) $flavor->name,
                    'ram' => (string) $flavor->ram,
                    'vcpus' => (string) $flavor->vcpus,
                    'disk' => (string) $flavor->disk,
                    'type' => 'hdd'
                );
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getInstanceIdPropertyName()
     */
    public function getInstanceIdPropertyName()
    {
        return RACKSPACE_SERVER_PROPERTIES::SERVER_ID;
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getgetClientByDbServer()
     *
     * @return Scalr_Service_Cloud_Rackspace_CS
     */
    public function getHttpClient(DBServer $dbServer)
    {
        return $this->getRsClient($dbServer->GetEnvironmentObject(), $dbServer->cloudLocation);
    }

    /**
     * {@inheritdoc}
     * @see PlatformModuleInterface::getImageInfo()
     */
    public function getImageInfo(\Scalr_Environment $environment, $cloudLocation, $imageId)
    {
        $ccProps = $environment->cloudCredentials("{$cloudLocation}." . SERVER_PLATFORMS::RACKSPACE)->properties;
        $client = \Scalr_Service_Cloud_Rackspace::newRackspaceCS(
            $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_USERNAME],
            $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_API_KEY],
            $cloudLocation
        );

        $snap = $client->getImageDetails($imageId);

        return $snap ? ["name" => $snap->image->name] : [];
    }
}
