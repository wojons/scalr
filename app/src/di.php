<?php
/**
 * Dependency injection container configuration
 * @author  Vitaliy Demidov    <zend@i.ua>
 * @since   01.11.2012
 */
$container = Scalr\DependencyInjection\Container::getInstance();
/* @var $cont \Scalr\DependencyInjection\Container */
$container->awsRegion = function ($cont) {
    return $cont->dbServer->GetProperty(EC2_SERVER_PROPERTIES::REGION);
};
$container->awsAccessKeyId = function ($cont) {
    return $cont->environment->getPlatformConfigValue(Modules_Platforms_Ec2::ACCESS_KEY);
};
$container->awsSecretAccessKey = function ($cont) {
    return $cont->environment->getPlatformConfigValue(Modules_Platforms_Ec2::SECRET_KEY);
};
$container->awsAccountNumber = function ($cont) {
    return $cont->environment->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID);
};
$container->user = function ($cont) {
	return $cont->initialized('request') &&
	       $cont->request->getUser() instanceof Scalr_Account_User ?
	       $cont->request->getUser() : null;
};
$container->aws = function ($cont, array $arguments = null) {
    static $instances;
    if (is_object($arguments[0])) {
        //Makes it possible to get aws instance by dbserver object
        if ($arguments[0] instanceof DBServer) {
        	$env = $arguments[0]->GetEnvironmentObject();
        	$region = $arguments[0]->GetProperty(EC2_SERVER_PROPERTIES::REGION);
        } elseif ($arguments[0] instanceof DBFarmRole) {
        	$env = $arguments[0]->GetFarmObject()->GetEnvironmentObject();
        	$region = $arguments[0]->CloudLocation;
        } else {
        	throw new InvalidArgumentException(
        		'RegionName|DBServer|DBFarmRole are only accepted. Invalid argument ' . get_class($arguments[0])
        	);
        }
        $awsAccessKeyId = $env->getPlatformConfigValue(Modules_Platforms_Ec2::ACCESS_KEY);
        $awsSecretAccessKey = $env->getPlatformConfigValue(Modules_Platforms_Ec2::SECRET_KEY);
    } else {
        $region = isset($arguments[0]) ? $arguments[0] : ($cont->initialized('dbServer') ? $cont->awsRegion : null);
        $awsAccessKeyId = isset($arguments[1]) ? $arguments[1] : $cont->awsAccessKeyId;
        $awsSecretAccessKey = isset($arguments[2]) ? $arguments[2] : $cont->awsSecretAccessKey;
    }
    $key = "{$awsAccessKeyId}|{$awsSecretAccessKey}|{$region}";
    if (!isset($instances[$key])) {
        $instances[$key] = new \Scalr\Service\Aws($awsAccessKeyId, $awsSecretAccessKey, $region);
    }
    return $instances[$key];
};
$container->auditLogStorage = $container->asShared(function ($cont) {
	$cont->auditLogStorageType = CONFIG::$AUDITLOG_ENABLED ? CONFIG::$AUDITLOG_STORAGE : 'MongoDb';
	$cont->auditLogStorageDsn = CONFIG::$AUDITLOG_DSN ? CONFIG::$AUDITLOG_DSN : 'mongodb://localhost:27017/db/auditlog';
	$storageClass = 'Scalr\\Logger\\' . ucfirst($cont->auditLogStorageType) . 'LoggerStorage';
	return new $storageClass (array('dsn' => $cont->auditLogStorageDsn));
});
$container->auditLog = function ($cont) {
	static $i;
	$cont->auditLogEnabled = CONFIG::$AUDITLOG_ENABLED ? true : false;
	$user = $cont->user;
	$key = (string) $cont->user->getId();
	if (!isset($i[$key])) {
		$i[$key] = new \Scalr\Logger\AuditLog($user, $cont->auditLogStorage, array('enabled' => $cont->auditLogEnabled));
		$i[$key]->setContainer($cont);
	}
	return $i[$key];
};
$container->cloudyn = function ($cont) {
	static $i;
	$acc = $cont->request->getUser()->getAccount();
	$email = $acc->getSetting(Scalr_Account::SETTING_CLOUDYN_USER_EMAIL);
	$password = $acc->getSetting(Scalr_Account::SETTING_CLOUDYN_USER_PASSWD);
	if (!isset($i[$email])) {
		$i[$email] = new \Scalr\Service\Cloudyn(
			$email, $password, isset(CONFIG::$CLOUDYN_ENVIRONMENT) ? CONFIG::$CLOUDYN_ENVIRONMENT : null
		);
	}
	return $i[$email];
};
$container->session = function ($cont) {
	return Scalr_Session::getInstance();
};