<?php

namespace Scalr\Modules\Platforms\RackspaceNgUs;

use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;

class RackspaceNgUsPlatformModule extends OpenstackPlatformModule implements \Scalr\Modules\PlatformModuleInterface
{

    const EXT_IS_ACCOUNT_MANAGED    = 'ext.is_account_managed';

    /**
     * Constructor
     *
     * @param   string    $platform  The name of the rackspace platform
     */
    public function __construct($platform = \SERVER_PLATFORMS::RACKSPACENG_US)
    {
        parent::__construct($platform);
    }


    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule::getLocations()
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        return array(
            'ORD' => 'Rackspace US / ORD',
            'DFW' => 'Rackspace US / DFW',
            'IAD' => 'Rackspace US / IAD',
            'SYD' => 'Rackspace US / SYD'
        );
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule::GetServerIPAddresses()
     */
    public function GetServerIPAddresses(\DBServer $DBServer)
    {
        $config = \Scalr::getContainer()->config;
        
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));
        $result = $client->servers->getServerDetails($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID));

        $publicNetworkName = 'public';
        $privateNetworkName = 'private';

        if (is_array($result->addresses->{$publicNetworkName}))
            foreach ($result->addresses->{$publicNetworkName} as $addr)
                if ($addr->version == 4) {
                    $remoteIp = $addr->addr;
                    break;
                }
        
        if (!$remoteIp && $result->accessIPv4)
            $remoteIp = $result->accessIPv4;

        if (is_array($result->addresses->{$privateNetworkName}))
            foreach ($result->addresses->{$privateNetworkName} as $addr)
                if ($addr->version == 4) {
                    $localIp = $addr->addr;
                    break;
                }

        if (!$localIp)
            $localIp = $remoteIp;

        return array(
            'localIp'   => $localIp,
            'remoteIp'  => $remoteIp
        );
    }
}
