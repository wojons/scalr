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
        $client = $this->getOsClient($DBServer->GetEnvironmentObject(), $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION));
        $result = $client->servers->getServerDetails($DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID));

        if ($result->accessIPv4)
            $remoteIp = $result->accessIPv4;

        if (!$remoteIp) {
            if (is_array($result->addresses->public))
                foreach ($result->addresses->public as $addr)
                    if ($addr->version == 4) {
                        $remoteIp = $addr->addr;
                        break;
                    }
        }

        if (is_array($result->addresses->private))
            foreach ($result->addresses->private as $addr)
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
