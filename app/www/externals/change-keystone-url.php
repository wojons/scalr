<?php

require_once __DIR__ . "/../src/prepend.inc.php";

use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Service\OpenStack\Services\Network\Type\CreateSubnet;
use Scalr\Service\OpenStack\Services\Network\Type\CreateRouter;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;

$validator = new Scalr_Validator();
$crypto = new Scalr_Util_CryptoTool(MCRYPT_TRIPLEDES, MCRYPT_MODE_CFB, 24, 8);

$envs = $db->Execute("SELECT id FROM client_environments");
while ($env = $envs->FetchRow()) {
    $environment = Scalr_Environment::init()->loadById($env['id']);
    if ($environment->isPlatformEnabled(SERVER_PLATFORMS::ECS)) {
        $url = $environment->getPlatformConfigValue(SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::KEYSTONE_URL);
        if ($url == 'https://api.entercloudsuite.com:5000/v2.0') {
            $environment->setPlatformConfig(array(
                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::KEYSTONE_URL => 'https://api-legacy.entercloudsuite.com:5000/v2.0'
            ));
        }
    }
}

exit();
