<?php

namespace Scalr\Modules\Platforms\Nimbula;

use \DBServer;
use \DBRole;
use \BundleTask;
use Scalr\Modules\Platforms\Nimbula\Adapters\StatusAdapter;
use Scalr\Modules\AbstractPlatformModule;

class NimbulaPlatformModule extends AbstractPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{

    /** Properties **/
    const API_URL 	= 'nimbula.api_url';
    const USERNAME	= 'nimbula.username';
    const PASSWORD	= 'nimbula.password';

    const IMAGE_LIST_ENTRY_VALUE = 'nimbula.image_list_entry';

    private $instancesListCache;

    /**
     * Gets NIMBULA api client
     *
     * @param  \Scalr_Environment $environment
     * @param  string             $region
     * @return Scalr_Service_Cloud_Nimbula_Client
     */
    private function getNimbulaClient($environment, $region)
    {
        return \Scalr_Service_Cloud_Nimbula::newNimbula(
            $environment->getPlatformConfigValue(self::API_URL),
            $environment->getPlatformConfigValue(self::USERNAME),
            $environment->getPlatformConfigValue(self::PASSWORD)
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::getLocations()
     */
    public function getLocations(\Scalr_Environment $environment = null) {
        return array(
            'nimbula-default'	=> 'Nimbula default'
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::hasCloudPrices()
     */
    public function hasCloudPrices(\Scalr_Environment $env)
    {
        if (!$this->container->analytics->enabled) return false;

        $url = $env->getPlatformConfigValue(static::API_URL);

        if (empty($url)) return false;

        return $this->container->analytics->prices->hasPriceForUrl(\SERVER_PLATFORMS::NIMBULA, $url) ?: $url;
    }

    public function getPropsList()
    {
        return array(
            self::API_URL			=> 'API URL',
            self::USERNAME			=> 'Username',
            self::PASSWORD			=> 'Password'
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerCloudLocation()
     */
    public function GetServerCloudLocation(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\NIMBULA_SERVER_PROPERTIES::CLOUD_LOCATION);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerID()
     */
    public function GetServerID(DBServer $DBServer)
    {
        return $DBServer->GetProperty(\NIMBULA_SERVER_PROPERTIES::NAME);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerFlavor()
     */
    public function GetServerFlavor(DBServer $DBServer)
    {
        return NULL;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::IsServerExists()
     */
    public function IsServerExists(DBServer $DBServer)
    {
        return in_array(
            $DBServer->GetProperty(\NIMBULA_SERVER_PROPERTIES::NAME),
            array_keys($this->GetServersList($DBServer->GetEnvironmentObject(), $this->GetServerCloudLocation($DBServer)))
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(DBServer $DBServer)
    {
        $nimbulaClient = $this->getNimbulaClient(
            $DBServer->GetEnvironmentObject(),
            $this->GetServerCloudLocation($DBServer)
        );

        $info = $nimbulaClient->instancesList($DBServer->GetProperty(\NIMBULA_SERVER_PROPERTIES::NAME));
        $server = $info;

        return array(
            'localIp'	=> $server->ip,
            'remoteIp'	=> $server->ip
        );
    }

    private function GetServersList(\Scalr_Environment $environment, $region, $skipCache = false)
    {
        if (!$region)
            return array();

        if (!$this->instancesListCache[$environment->id][$region] || $skipCache) {
            $nimbulaClient = $this->getNimbulaClient($environment, $region);

            try {
                $results = $nimbulaClient->instancesList();
            } catch (\Exception $e) {
                throw new \Exception(sprintf("Cannot get list of servers for platfrom ec2: %s", $e->getMessage()));
            }


            if (count($results) > 0) {
                foreach ($results as $item)
                {
                    $id = str_replace($environment->getPlatformConfigValue(self::USERNAME)."/", "", $item->name);
                    $this->instancesListCache[$environment->id][$region][$id] = $item->state;
                }
            }
        }

        return $this->instancesListCache[$environment->id][$region];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerRealStatus()
     */
    public function GetServerRealStatus(DBServer $DBServer)
    {
        $region = $this->GetServerCloudLocation($DBServer);
        $iid = $DBServer->GetProperty(\NIMBULA_SERVER_PROPERTIES::NAME);

        if (!$iid || !$region) {
            $status = 'not-found';
        }
        elseif (!$this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$iid]) {
            $nimbulaClient = $this->getNimbulaClient(
                $DBServer->GetEnvironmentObject(),
                $region
            );

            try {
                $iinfo = $nimbulaClient->instancesList($iid);

                if ($iinfo)
                    $status = $iinfo->state;
                else
                    $status = 'not-found';
            }
            catch(\Exception $e) {
                if (stristr($e->getMessage(), "Not Found"))
                    $status = 'not-found';
            }
        }
        else {
            $status = $this->instancesListCache[$DBServer->GetEnvironmentObject()->id][$region][$DBServer->GetProperty(\NIMBULA_SERVER_PROPERTIES::NAME)];
        }

        return StatusAdapter::load($status);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::TerminateServer()
     */
    public function TerminateServer(DBServer $DBServer)
    {
        $nimbulaClient = $this->getNimbulaClient(
            $DBServer->GetEnvironmentObject(),
            $this->GetServerCloudLocation($DBServer)
        );

        $nimbulaClient->instanceDelete($DBServer->GetProperty(\NIMBULA_SERVER_PROPERTIES::NAME));

        return true;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RebootServer()
     */
    public function RebootServer(DBServer $DBServer, $soft = true)
    {
        //NOT SUPPORTED
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::RemoveServerSnapshot()
     */
    public function RemoveServerSnapshot(DBRole $DBRole)
    {
        //TODO:
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CheckServerSnapshotStatus()
     */
    public function CheckServerSnapshotStatus(BundleTask $BundleTask)
    {
        //TODO:
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CreateServerSnapshot()
     */
    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        $DBServer = DBServer::LoadByID($BundleTask->serverId);
        $BundleTask->status = \SERVER_SNAPSHOT_CREATION_STATUS::IN_PROGRESS;
        $BundleTask->bundleType = \SERVER_SNAPSHOT_CREATION_TYPE::NIMBULA_DEF;

        $msg = new \Scalr_Messaging_Msg_Rebundle(
            $BundleTask->id,
            $BundleTask->roleName,
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

        $BundleTask->setDate('started');
        $BundleTask->Save();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerConsoleOutput()
     */
    public function GetServerConsoleOutput(DBServer $DBServer)
    {
        //NOT SUPPORTED
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::GetServerExtendedInformation()
     */
    public function GetServerExtendedInformation(DBServer $DBServer)
    {
        $nimbulaClient = $this->getNimbulaClient(
            $DBServer->GetEnvironmentObject(),
            $this->GetServerCloudLocation($DBServer)
        );

        try {
            $iinfo = $nimbulaClient->instancesList($DBServer->GetProperty(\NIMBULA_SERVER_PROPERTIES::NAME));
        } catch (\Exception $e) { var_dump($e);}

        if ($iinfo->name)
        {
            return array(
                'Cloud Server ID' => str_replace($DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::USERNAME)."/", "", $iinfo->name),
                'Public IP'		=> $iinfo->ip,
                'Start time'	=> $iinfo->start_time,
                'Shape'			=> $iinfo->shape,
                'State'			=> $iinfo->state,
                'Image list'	=> $iinfo->imagelist,
                'Entry'			=> $iinfo->entry
            );
        }

        return false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::LaunchServer()
     */
    public function LaunchServer(DBServer $DBServer, \Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $environment = $DBServer->GetEnvironmentObject();

        $farmRole = $DBServer->GetFarmRoleObject();
        $location = $farmRole->CloudLocation;

        $nimbulaClient = $this->getNimbulaClient(
            $environment,
            $location
        );

        $dbRole = $DBServer->GetFarmRoleObject()->GetRoleObject();

        if (!$dbRole->getProperty(DBRole::PROPERTY_NIMBULA_ENTRY))
        {
            $username = $environment->getPlatformConfigValue(self::USERNAME);

            $imageLists = $nimbulaClient->imageListList();
            $listFound = false;
            foreach ($imageLists as $list)
            {
                if ($list->name == "{$username}/scalr")
                    $listFound = true;
            }

            if (!$listFound)
                $nimbulaClient->imageListCreate('scalr', 'Image list for scalr images');

            $entry = (int)$environment->getPlatformConfigValue(self::IMAGE_LIST_ENTRY_VALUE);
            $entry++;

            $nimbulaClient->imageListAddEntry('scalr', $dbRole->getImageId($DBServer->platform, $location), $entry);

            $environment->setPlatformConfig(array(
                self::IMAGE_LIST_ENTRY_VALUE => $entry
            ));

            $dbRole->setProperty(DBRole::PROPERTY_NIMBULA_ENTRY, $entry);
        }

        $instance = $nimbulaClient->instanceLaunch(
            $DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_NIMBULA_SHAPE),
            $DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::USERNAME)."/scalr",
            $dbRole->getProperty(DBRole::PROPERTY_NIMBULA_ENTRY),
            "Scalr server: FarmID: {$DBServer->farmId} RoleName: {$DBServer->GetFarmRoleObject()->GetRoleObject()->name}"
        );
        $instanceInfo = $instance->instances[0];

        if ($instanceInfo->name) {
            $serverName = str_replace($DBServer->GetEnvironmentObject()->getPlatformConfigValue(self::USERNAME)."/", "", $instanceInfo->name);

            $DBServer->SetProperties([
                \NIMBULA_SERVER_PROPERTIES::NAME           => $serverName,
                \NIMBULA_SERVER_PROPERTIES::CLOUD_LOCATION => $location
            ]);

            return $DBServer;
        } else {
            throw new \Exception("Scalr unable to launch Nimbula server");
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

        if ($put) {
            $environment = $DBServer->GetEnvironmentObject();
            $accessData = new \stdClass();
            $accessData->username = $environment->getPlatformConfigValue(self::USERNAME);
            $accessData->apiUrl = $environment->getPlatformConfigValue(self::API_URL);
            $accessData->password = $environment->getPlatformConfigValue(self::PASSWORD);

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
        if (!($env instanceof \Scalr_Environment)) {
            throw new \InvalidArgumentException(sprintf(
                "Method %s requires environment object to be specified.", __METHOD__
            ));
        }

        $nimbula = $this->getNimbulaClient($env, $cloudLocation);

        $ret = array();

        foreach ($nimbula->listShapes() as $shape) {
            $ret[(string)$shape->name] = "{$shape->name} (CPUs: {$shape->cpus} RAM: {$shape->ram})";
        }

        return $ret;
    }
}
