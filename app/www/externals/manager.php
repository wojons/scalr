<?php

require_once __DIR__ . "/../src/prepend.inc.php";

use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Service\OpenStack\Services\Network\Type\CreateSubnet;
use Scalr\Service\OpenStack\Services\Network\Type\CreateRouter;
use Scalr\Modules\Platforms\Openstack\OpenstackPlatformModule;
use Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;

$db = Scalr::getDb();

$validator = new Scalr_Validator();
$crypto = \Scalr::getContainer()->crypto;

if (!$_REQUEST['update'] && !$_REQUEST['delete']) {
    if (!$_REQUEST['name'])
        $err['name'] = _("Account name required");

    $name = $_REQUEST['name'];
    $password = $crypto->sault(10);
}

if ($validator->validateEmail($_REQUEST['email'], null, true) !== true)
    $err['email'] = _("Invalid E-mail address");

$email = $_REQUEST['email'];


function getOpenStackOption($name)
{
    return SERVER_PLATFORMS::ECS . "." . constant('Scalr\\Modules\\Platforms\\Openstack\\OpenstackPlatformModule::' . $name);
}

if (count($err) == 0) {
	if ($_REQUEST['delete']) {
		$user = Scalr_Account_User::init()->loadByEmail($email);
		if (!$user)
			throw new Exception("User Not Found");

		$account = $user->getAccount();
		$account->delete();

		print json_encode(array('success' => true));

		exit();
	}

    if (!$_REQUEST['update']) {
        $account = Scalr_Account::init();
        $account->name = $name;
        $account->status = Scalr_Account::STATUS_ACTIVE;
        $account->save();

        $env = $account->createEnvironment("Environment 1");
        $envId = $env->id;

        $user = $account->createUser($email, $password, Scalr_Account_User::TYPE_ACCOUNT_OWNER);
        $user->fullname = $name;
        $user->save();


        $clientSettings[CLIENT_SETTINGS::RSS_LOGIN] = $email;
        $clientSettings[CLIENT_SETTINGS::RSS_PASSWORD] = $crypto->sault(10);

        foreach ($clientSettings as $k=>$v)
            $account->setSetting($k, $v);

        try {
            $db->Execute("INSERT INTO default_records SELECT null, '{$account->id}', type, ttl, priority, value, name FROM default_records WHERE clientid='0'");
        } catch(Exception $e) {
    	   $err['db'] = $e->getMessage();
        }
    } else {
        $user = Scalr_Account_User::init()->loadByEmail($email);
        if (!$user)
            throw new Exception("User Not Found");

        $account = $user->getAccount();

        $env = $user->getDefaultEnvironment();

        $envId = $env->id;
    }

    try {
        $retval = array('success' => true, 'account' => array(
            'id' => $account->id,
            'userId' => $user->id,
    	    'password' => $password,
            'envId' => $env->id,
            'api_access_key' => $user->getSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY),
            'api_secret_key' => $user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY)
        ));

        $updateEnv = false;
        if (!$_REQUEST['update']) {
            //CONFIGURE OPENSTACK:
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::KEYSTONE_URL] = $_REQUEST['openstack_keystone_url'];
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::USERNAME] = $_REQUEST['openstack_username'];
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::PASSWORD] = $_REQUEST['openstack_password'];
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::TENANT_NAME] = $_REQUEST['openstack_tenant_name'];
            $updateEnv = true;
        } else {
            if ($_REQUEST['openstack_keystone_url']) {
                $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::KEYSTONE_URL] = $_REQUEST['openstack_keystone_url'];
                $updateEnv = true;
            } else {
                $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::KEYSTONE_URL] = $env->getPlatformConfigValue(SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::KEYSTONE_URL);
            }

            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::USERNAME] = $env->getPlatformConfigValue(SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::USERNAME);
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::PASSWORD] = $env->getPlatformConfigValue(SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::PASSWORD);
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::TENANT_NAME] = $env->getPlatformConfigValue(SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::TENANT_NAME);
        }

        if ($updateEnv) {
            $env->setPlatformConfig(array(
                SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::AUTH_TOKEN => false
            ));
        }

        //var_dump($pars);

        $os = new OpenStack(new OpenStackConfig(
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::USERNAME],
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::KEYSTONE_URL],
            'fake-region',
            null,
            null, // Closure callback for token
            null, // Auth token. We should be assured about it right now
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::PASSWORD],
            $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::TENANT_NAME]
        ));

       $zones = $os->listZones();

       if ($updateEnv) {
           $env->enablePlatform(SERVER_PLATFORMS::ECS, true);
           $env->setPlatformConfig($pars);
       }

       unset($env);
       $env = Scalr_Environment::init()->loadById($envId);
       \Scalr::getContainer()->environment = $env;
       $configSet = false;

       foreach ($zones as $zone) {
           $osClient = $env->openstack(SERVER_PLATFORMS::ECS, $zone->name);

           // Check SG Extension
           if (!$configSet) {
               $pars2 = array();
               $pars2[getOpenStackOption('EXT_SECURITYGROUPS_ENABLED')] = (int)$osClient->servers->isExtensionSupported(ServersExtension::securityGroups());
               // Check Floating Ips Extension
               $pars2[getOpenStackOption('EXT_FLOATING_IPS_ENABLED')] = (int)$osClient->servers->isExtensionSupported(ServersExtension::floatingIps());
               // Check Cinder Extension
               $pars2[getOpenStackOption('EXT_CINDER_ENABLED')] = (int)$osClient->hasService('volume');
               // Check Swift Extension
               $pars2[getOpenStackOption('EXT_SWIFT_ENABLED')] = (int)$osClient->hasService('object-store');
               // Check LBaas Extension
               $pars2[getOpenStackOption('EXT_LBAAS_ENABLED')] = $osClient->hasService('network') ? (int)$osClient->network->isExtensionSupported('lbaas') : 0;
               $env->setPlatformConfig($pars2);
               $configSet = true;
           }

           // Get Public network
           $nName = $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::TENANT_NAME] . "_net";
           $rName = $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::TENANT_NAME] . "_router";
           $sName = $pars[SERVER_PLATFORMS::ECS . "." . OpenstackPlatformModule::TENANT_NAME] . "_subnet";

           $networks = $osClient->network->listNetworks();
           $privateNetworkFound = false;
           foreach ($networks as $network) {
                if ($network->{"router:external"} == true) {
                    $publicNetworkId = $network->id;
                }

                if ($network->name == $nName)
                    $privateNetworkFound = true;
           }

           if ($privateNetworkFound)
               continue;

           if (!$publicNetworkId)
               throw new Exception("Unable to find Public Network");

           // Create router connected to public network
           try {
               $request = new CreateRouter($rName, true, $publicNetworkId);
               $router = $osClient->network->routers->create($request);
           } catch (Exception $e) {
                if (stristr($e->getMessage(), "Quota exceeded for resources: ['router']")) {
                    $routers = $osClient->network->routers->list();
                    foreach ($routers as $r) {
                        if ($r->name == $rName)
                            $router = $r;
                    }
                } else {
                    throw $e;
                }
           }

           // Create private network
           $privateNetwork = $osClient->network->createNetwork($nName);

           //Create private subnet
           $request = new CreateSubnet($privateNetwork->id, '192.168.192.0/24');
           $request->name = $sName;
           $request->dns_nameservers = array('8.8.8.8', '8.8.4.4');
           $privateSubnet = $osClient->network->createSubnet($request);

           // Add interface on router that linked to private subnet
           $osClient->network->routers->addInterface($router->id, $privateSubnet->id);
       }

    } catch (Exception $e) {
    	$err['openstack'] = $e->getMessage();

    	if (!$_REQUEST['update'])
    	   $account->delete();
    }

}

if (count($err) == 0)
    print json_encode($retval);
else
    print json_encode(array('success' => false, 'error' => $err));

exit();
