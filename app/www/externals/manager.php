<?php
require_once(dirname(__FILE__) . "/../src/prepend.inc.php");

use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Service\OpenStack\Services\Network\Type\CreateSubnet;
use Scalr\Service\OpenStack\Services\Network\Type\CreateRouter;

$validator = new Scalr_Validator();
$crypto = new Scalr_Util_CryptoTool(MCRYPT_TRIPLEDES, MCRYPT_MODE_CFB, 24, 8);

if (!$_REQUEST['name'])
    $err['name'] = _("Account name required");

$name = $_REQUEST['name'];
$password = $crypto->sault(10);

if ($validator->validateEmail($_REQUEST['email'], null, true) !== true)
    $err['email'] = _("Invalid E-mail address");

$email = $_REQUEST['email'];

// Check email
/*
$DBEmailCheck = $db->GetOne("SELECT COUNT(*) FROM account_users WHERE email=? LIMIT 1", array($email));

if ($DBEmailCheck > 0)
    $err['email'] = _("E-mail already exists in database");
*/


if (count($err) == 0) {
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

    try {
        $retval = array('success' => true, 'account' => array(
            'id' => $account->id,
            'userId' => $user->id,
    	    'password' => $password,
            'envId' => $env->id,
            'api_access_key' => $user->getSetting(Scalr_Account_User::SETTING_API_ACCESS_KEY),
            'api_secret_key' => $user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY)
        ));

        //CONFIGURE OPENSTACK:
        $pars[SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::KEYSTONE_URL] = $_REQUEST['openstack_keystone_url'];
        $pars[SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::USERNAME] = $_REQUEST['openstack_username'];
        $pars[SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::PASSWORD] = $_REQUEST['openstack_password'];
        $pars[SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::TENANT_NAME] = $_REQUEST['openstack_tenant_name'];

        $env->setPlatformConfig(array(
            SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::AUTH_TOKEN => false
        ));

        $os = new OpenStack(new OpenStackConfig(
            $pars[SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::USERNAME],
            $pars[SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::KEYSTONE_URL],
            'fake-region',
            null,
            null, // Closure callback for token
            null, // Auth token. We should be assured about it right now
            $pars[SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::PASSWORD],
            $pars[SERVER_PLATFORMS::ECS . "." . Modules_Platforms_Openstack::TENANT_NAME]
        ));

        $os->listZones();

       $env->enablePlatform(SERVER_PLATFORMS::ECS, true);
       $env->setPlatformConfig($pars);

       unset($env);
       $env = Scalr_Environment::init()->loadById($envId);
       \Scalr::getContainer()->environment = $env;

       $osClient = $env->openstack(SERVER_PLATFORMS::ECS, 'ItalyMilano1');

       // Get Public network
       $networks = $osClient->network->listNetworks();
       foreach ($networks as $network) {
            if ($network->{"router:external"} == true) {
                $publicNetworkId = $network->id;
            }
       }

       if (!$publicNetworkId)
           throw new Exception("Unable to find Public Network");

       // Create router connected to public network
       $request = new CreateRouter("{$_REQUEST['openstack_tenant_name']}_router", true, $publicNetworkId);
       $router = $osClient->network->routers->create($request);

       // Create private network
       $privateNetwork = $osClient->network->createNetwork("{$_REQUEST['openstack_tenant_name']}_net");

       //Create private subnet
       $request = new CreateSubnet($privateNetwork->id, '192.168.192.0/24');
       $request->name = "{$_REQUEST['openstack_tenant_name']}_subnet";
       $request->dns_nameservers = array('8.8.8.8', '8.8.4.4');
       $privateSubnet = $osClient->network->createSubnet($request);

       // Add interface on router that linked to private subnet
       $osClient->network->routers->addInterface($router->id, $privateSubnet->id);

    } catch (Exception $e) {
    	$err['openstack'] = $e->getMessage();
    	$account->delete();
    }

}

if (count($err) == 0)
    print json_encode($retval);
else
    print json_encode(array('success' => false, 'error' => $err));

exit();
