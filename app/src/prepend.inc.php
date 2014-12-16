<?php

define("TRANSACTION_ID", uniqid("tran"));

@date_default_timezone_set(@date_default_timezone_get());

@error_reporting(E_ALL);

// Increase execution time limit
set_time_limit(180);

// Environment stuff
$base = dirname(__FILE__);
define("SRCPATH", $base);
define("APPPATH", "{$base}/..");
define("CACHEPATH", "$base/../cache");
define("SCALR_CONFIG_FILE", APPPATH . '/etc/config.yml');
define("SCALR_CONFIG_CACHE_FILE", CACHEPATH . '/.config');
define("SCALR_VERSION", trim(@file_get_contents(APPPATH . "/etc/version")));

$ADODB_CACHE_DIR = CACHEPATH . "/adodb";

define("SCALR_TEMPLATES_PATH", APPPATH . "/templates/en_US");

// Require autoload definition
$classpath = [
    $base,
    $base . "/externals/ZF-1.10.8",
    $base . "/externals/google-api-php-client-git-03102014/src"
];
set_include_path(get_include_path() . PATH_SEPARATOR . join(PATH_SEPARATOR, $classpath));

require_once SRCPATH . "/autoload.inc.php";

spl_autoload_register("__autoload");

set_error_handler("Scalr::errorHandler");

if (file_exists(APPPATH . "/../vendor/autoload.php")) {
    require_once APPPATH . "/../vendor/autoload.php";
}

//Container witn adodb service needs to be defined in the first turn, as much depends on it.
Scalr::initializeContainer();

$idFilePath = APPPATH . '/etc/id';
$id = trim(@file_get_contents($idFilePath));
if (!$id) {
    $uuid = Scalr::GenerateUID();
    $id = dechex(abs(crc32($uuid)));

    $res = @file_put_contents($idFilePath, $id);
    if (!$res)
        exit("ERROR: Unable to write ID file ({$idFilePath}).");
}

define("SCALR_ID", $id);

require_once SRCPATH . '/externals/adodb5-18/adodb-exceptions.inc.php';
require_once SRCPATH . '/externals/adodb5-18/adodb.inc.php';

// Define log4php contants
define("LOG4PHP_DIR", SRCPATH . '/externals/apache-log4php-2.0.0-incubating/src/main/php');
require_once LOG4PHP_DIR . '/Logger.php';
Logger::configure(APPPATH . '/etc/log4php.xml', 'LoggerConfiguratorXml');
