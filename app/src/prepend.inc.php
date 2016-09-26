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
define("SCALR_TEMPLATES_PATH", APPPATH . "/templates/en_US");
defined("STDOUT") or define("STDOUT", fopen("php://output", "w"));

$ADODB_CACHE_DIR = CACHEPATH . "/adodb";

// Require autoload definition
set_include_path(get_include_path() . PATH_SEPARATOR . $base);

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
    if (!$res) {
        exit("ERROR: Unable to write ID file ({$idFilePath}).");
    }
}

define("SCALR_ID", $id);

//ADODB requirements
require_once SRCPATH . '/externals/adodb5-18/adodb-exceptions.inc.php';
require_once SRCPATH . '/externals/adodb5-18/adodb.inc.php';
