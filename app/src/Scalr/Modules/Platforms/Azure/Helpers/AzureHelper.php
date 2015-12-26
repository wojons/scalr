<?php

namespace Scalr\Modules\Platforms\Azure\Helpers;

use Scalr\Service\Azure\Services\Compute\DataType\ResourceExtensionProperties;
use Scalr\Service\Azure\Services\Compute\DataType\CreateResourceExtension;
use Scalr\Modules\Platforms\Azure\AzurePlatformModule;
use Scalr\Model\Entity;
use SERVER_PLATFORMS;

class AzureHelper
{
    public static function cleanupServerObjects(\DBServer $dbServer)
    {
        $env = $dbServer->GetEnvironmentObject();
        $azure = $env->azure();

        // Remove NIC
        $nic = $dbServer->GetProperty(\AZURE_SERVER_PROPERTIES::NETWORK_INTERFACE);
        if ($nic) {
            try {
                $res1 = $azure->network->interface->delete(
                    $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                    $dbServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
                    $nic
                );
            } catch (\Exception $e) {
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->error(
                    new \FarmLogMessage($dbServer->farmId, sprintf(
                        _("Unable to remove NIC object on server termination: %s"),
                        $e->getMessage()
                    ), $dbServer->serverId
                ));
            }
        }

        // Remove Public IP
        $publicIpName = $dbServer->GetProperty(\AZURE_SERVER_PROPERTIES::PUBLIC_IP_NAME);
        if ($publicIpName) {
            try {
                $res2 = $azure->network->publicIPAddress->delete(
                    $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                    $dbServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
                    $publicIpName
                );
            } catch (\Exception $e) {
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->error(
                    new \FarmLogMessage($dbServer->farmId, sprintf(
                        _("Unable to remove PublicIP object on server termination: %s"),
                        $e->getMessage()
                    ), $dbServer->serverId
                ));
            }
        }
    }

    public static function setupScalrAgent(\DBServer $dbServer)
    {
        $baseurl = \Scalr::config('scalr.endpoint.scheme') . "://" .
            \Scalr::config('scalr.endpoint.host');

        $env = $dbServer->GetEnvironmentObject();
        $azure = $env->azure();

        // TODO: check for default branch (stable or latest)
        // right now azure is worked only with latest szr
        $branch = 'latest';
        $develRepos = \Scalr::getContainer()->config->get('scalr.scalarizr_update.devel_repos');
        $scmBranch = $dbServer->GetFarmRoleObject()->GetSetting('user-data.scm_branch');
        if ($scmBranch != '' && $develRepos) {
            $branch = $dbServer->GetFarmRoleObject()->GetSetting('base.devel_repository');
            $scmBranch = "{$scmBranch}/";
        } else {
            $scmBranch = '';
        }

        if ($dbServer->osType == 'linux') {
            $extensionProperties = new ResourceExtensionProperties(
                'Microsoft.OSTCExtensions',
                'CustomScriptForLinux',
                '1.2'
            );
            $extensionProperties->setSettings([
                'commandToExecute' => "bash -c 'curl -L \"{$baseurl}/public/linux/{$branch}/azure/{$scmBranch}install_scalarizr.sh\" | bash && service scalr-upd-client start'"
            ]);
        } else {
            $extensionProperties = new ResourceExtensionProperties(
                'Microsoft.Compute',
                'CustomScriptExtension',
                '1.4'
            );

            $extensionProperties->setSettings([
                "commandToExecute" => "powershell -NoProfile -ExecutionPolicy Bypass -Command \"iex ((new-object net.webclient).DownloadString('{$baseurl}/public/windows/{$branch}/{$scmBranch}install_scalarizr.ps1')); start-service ScalrUpdClient\""
            ]);
        }

        $createExtension = new CreateResourceExtension('scalarizr', $dbServer->cloudLocation, $extensionProperties);

        try {
            $response = $azure->compute->resourceExtension->create(
                $env->cloudCredentials(SERVER_PLATFORMS::AZURE)->properties[Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID],
                $dbServer->GetProperty(\AZURE_SERVER_PROPERTIES::RESOURCE_GROUP),
                $dbServer->GetProperty(\AZURE_SERVER_PROPERTIES::SERVER_NAME),
                $createExtension
            );

            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(
                new \FarmLogMessage($dbServer->farmId, sprintf(
                    _("Created azure resource extension to install and launch scalr agent")
                ), $dbServer->serverId
            ));

            $dbServer->SetProperty(\AZURE_SERVER_PROPERTIES::SZR_EXTENSION_DEPLOYED, 1);

        } catch (\Exception $e) {
            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->fatal(
                new \FarmLogMessage($dbServer->farmId, sprintf(
                    _("Unable to create azure resource extension to install and launch scalr agent: %s"),
                    $e->getMessage()
                ), $dbServer->serverId
            ));
        }
    }
}
