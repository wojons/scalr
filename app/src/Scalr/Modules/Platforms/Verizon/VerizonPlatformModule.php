<?php

namespace Scalr\Modules\Platforms\Verizon;


use \DBServer;
use \BundleTask;
use Exception;
use Scalr\Service\OpenStack\Services\Servers\Type\Personality;
use Scalr\Service\OpenStack\Services\Servers\Type\PersonalityList;
use Scalr\Service\OpenStack\Services\Servers\Type\NetworkList;
use Scalr\Service\OpenStack\Services\Servers\Type\Network;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;

class VerizonPlatformModule extends OpenstackPlatformModule
{
    /**
     * Constuctor
     *
     * @param   string    $platform The name of the openstack based platform
     */
    public function __construct($platform = \SERVER_PLATFORMS::VERIZON)
    {
        parent::__construct(\SERVER_PLATFORMS::VERIZON);
    }

    public function determineServerIps(\Scalr\Service\OpenStack\OpenStack $client, $server)
    {
        $config = \Scalr::getContainer()->config;

        $publicNetworkName = 'public';
        $privateNetworkName = 'private';

        if (is_array($server->addresses->{$publicNetworkName})) {
            foreach ($server->addresses->{$publicNetworkName} as $addr)
            if ($addr->version == 4) {
                $remoteIp = $addr->addr;
                break;
            }
        }

        if (is_array($server->addresses->{$privateNetworkName})) {
            foreach ($server->addresses->{$privateNetworkName} as $addr)
            if ($addr->version == 4) {
                $localIp = $addr->addr;
                break;
            }
        }

        if (!$localIp)
            $localIp = $remoteIp;

        return array(
            'localIp'	=> $localIp,
            'remoteIp'	=> $remoteIp
        );
    }

    

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::CreateServerSnapshot()
     */
    public function CreateServerSnapshot(BundleTask $BundleTask)
    {
        //NOT SUPPORTED

        return false;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\PlatformModuleInterface::LaunchServer()
     */
    public function LaunchServer(DBServer $DBServer, \Scalr_Server_LaunchOptions $launchOptions = null)
    {
        $config = \Scalr::getContainer()->config;
        $environment = $DBServer->GetEnvironmentObject();
        $governance = new \Scalr_Governance($environment->id);

        if (!$launchOptions) {
            $launchOptions = new \Scalr_Server_LaunchOptions();
            $DBRole = $DBServer->GetFarmRoleObject()->GetRoleObject();

            $launchOptions->imageId = $DBRole->__getNewRoleObject()->getImage($this->platform, $DBServer->GetCloudLocation())->imageId;
            $launchOptions->serverType = $DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_OPENSTACK_FLAVOR_ID);
            $launchOptions->cloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

            $launchOptions->userData = $DBServer->GetCloudUserData();
            $launchOptions->userData['region'] = $launchOptions->cloudLocation;
            $launchOptions->userData['vzc.adminpassword'] = \Scalr::GenerateRandomKey(20);
            $launchOptions->userData['platform'] = \SERVER_PLATFORMS::VERIZON;
                        
            // Apply tags
            $launchOptions->userData = array_merge($DBServer->getOpenstackTags(), $launchOptions->userData);

            $launchOptions->networks = @json_decode($DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_OPENSTACK_NETWORKS));
            $gevernanceNetworks = $governance->getValue($this->platform, 'openstack.networks');
            if (count($launchOptions->networks) == 0 && $gevernanceNetworks) {
                $launchOptions->networks = $gevernanceNetworks[$launchOptions->cloudLocation];
            }

            foreach ($launchOptions->userData as $k => $v) {
                if (!$v)
                    unset($launchOptions->userData[$k]);
            }

            $launchOptions->architecture = 'x86_64';
            $isWindows = ($DBServer->osType == 'windows' || $DBRole->getOs()->family == 'windows');

            $customUserData = $DBServer->GetFarmRoleObject()->GetSetting('base.custom_user_data');
            
            $serverNameFormat = $governance->getValue($DBServer->platform, \Scalr_Governance::OPENSTACK_INSTANCE_NAME_FORMAT);
            if (!$serverNameFormat)
                $serverNameFormat = $DBServer->GetFarmRoleObject()->GetSetting(\Scalr_Role_Behavior::ROLE_INSTANCE_NAME_FORMAT);
            
        } else {
            $launchOptions->userData = array();
            $customUserData = false;

            if (!$launchOptions->networks) {
                $launchOptions->networks = array();
            }
            $isWindows = ($DBServer->osType == 'windows');
        }

        $client = $this->getOsClient($environment, $launchOptions->cloudLocation);

        // Prepare user data
        $u_data = "";
        foreach ($launchOptions->userData as $k => $v)
            $u_data .= "{$k}={$v};";

        $u_data = trim($u_data, ";");
        if ($customUserData) {
            $repos = $DBServer->getScalarizrRepository();

            $extProperties["user_data"] = base64_encode(str_replace(array(
                '{SCALR_USER_DATA}',
                '{RPM_REPO_URL}',
                '{DEB_REPO_URL}'
            ), array(
                $u_data,
                $repos['rpm_repo_url'],
                $repos['deb_repo_url']
            ), $customUserData));
        }

        $personality = new PersonalityList();

        if ($isWindows) {
            $personality->append(new Personality(
                'C:\\Program Files\\Scalarizr\\etc\\private.d\\.user-data',
                base64_encode($u_data)
            ));
        } else {
            if ($DBServer->platform == \SERVER_PLATFORMS::ECS) {
                $personality->append(new Personality(
                    '/etc/.scalr-user-data',
                    base64_encode($u_data)
                ));
            } else {
                $personality->append(new Personality(
                    '/etc/scalr/private.d/.user-data',
                    base64_encode($u_data)
                ));
            }
        }

        //TODO: newtorks
        $networks = new NetworkList();
        foreach ((array)$launchOptions->networks as $network) {
            if ($network)
                $networks->append(new Network($network));
        }

        //$osUserData = null;
        $osPersonality = null;
        $userDataMethod = 'meta-data';
        
        $osPersonality = null;
        $userDataMethod = $config->defined("scalr.{$this->platform}.user_data_method") ? $config("scalr.{$this->platform}.user_data_method") : null;
        if (!$userDataMethod || $userDataMethod == 'both' || $userDataMethod == 'personality') {
            $osPersonality = $personality;
        }
        

        if (!$userDataMethod || $userDataMethod == 'both' || $userDataMethod == 'meta-data' || $isWindows) {
            $osUserData = $launchOptions->userData;
        }

        
        $serverName = ($serverNameFormat) ? $DBServer->applyGlobalVarsToValue($serverNameFormat) : $DBServer->serverId;
        
        try {
            $result = $client->servers->createServer(
                $serverName,
                $launchOptions->serverType,
                $launchOptions->imageId,
                null,
                $osUserData,
                $osPersonality,
                $networks,
                $extProperties
            );

            $DBServer->SetProperties([
                \OPENSTACK_SERVER_PROPERTIES::SERVER_ID      => $result->id,
                \OPENSTACK_SERVER_PROPERTIES::IMAGE_ID       => $launchOptions->imageId,
                \OPENSTACK_SERVER_PROPERTIES::FLAVOR_ID      => $launchOptions->serverType,
                \OPENSTACK_SERVER_PROPERTIES::ADMIN_PASS     => ($launchOptions->userData['vzc.adminpassword']) ? $launchOptions->userData['vzc.adminpassword'] : $result->adminPass,
                \OPENSTACK_SERVER_PROPERTIES::NAME           => $DBServer->serverId,
                \SERVER_PROPERTIES::ARCHITECTURE             => $launchOptions->architecture,
                \OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION => $launchOptions->cloudLocation,
                \SERVER_PROPERTIES::SYSTEM_USER_DATA_METHOD  => $userDataMethod,
            ]);

            if ($DBServer->farmRoleId) {
                $ipPool = $DBServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_OPENSTACK_IP_POOL);
                if ($ipPool)
                    $DBServer->SetProperty(\SERVER_PROPERTIES::SYSTEM_IGNORE_INBOUND_MESSAGES, 1);
                }

            $DBServer->setOsType($isWindows ? 'windows' : 'linux');
            $DBServer->cloudLocation = $launchOptions->cloudLocation;
            $DBServer->cloudLocationZone = ""; // Not supported by openstack
            $DBServer->imageId = $launchOptions->imageId;
            // we set server history here
            $DBServer->getServerHistory();

            return $DBServer;
        } catch (\Exception $e) {
            throw new \Exception(sprintf(_("Cannot launch new instance. %s"), $e->getMessage()));
        }
    }
}
