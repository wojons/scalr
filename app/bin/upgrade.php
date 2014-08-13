<?php

/**
 * Scalr automatic upgrade script
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    4.5.0 (10.10.2013)
 */

require_once __DIR__ . '/../src/prepend.inc.php';

use Scalr\Upgrade\UpgradeHandler;
use Scalr\Upgrade\Console;
use Scalr\Util\PhpTemplate;

set_time_limit(0);

define('SCALR_UPGRADE_VERSION', '1.1');

$shortopts  = "hnvr:";
$longopts  = array("help", "new", "force");
$opt = getopt($shortopts, $longopts);

$console = new Console();
$console->timeformat = null;
$console->keeplog = false;

$showusage = function() use ($console) {
    $console->out("Scalr upgrade Ver %s php-%s", SCALR_UPGRADE_VERSION, phpversion());
    $console->out("");
    $console->out("Usage: upgrade [OPTIONS]");
    $console->out("  -h, --help          Display this help end exit.");
    $console->out("  -n, --new           Generate a new update class to implement.");
    $console->out("  -r uuid             Run only specified update. UUID is unique identifier.");
    $console->out("  -v                  Turn on verbosity.");
    $console->out("  --force             Run forcefully ignoring pid.");
    $console->out("");
    exit;
};

$options = new \stdClass();
//validates options
if (isset($opt['r'])) {
    if (!preg_match('/^[\da-f]{32}$/i', $opt['r']) &&
        !preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/i', $opt['r'])) {
        $console->error("Error usage. UUID should be 32 hexadecimal digit number");
        $showusage();
    }
    $options->cmd = UpgradeHandler::CMD_RUN_SPECIFIC;
    $options->uuid = strtolower(str_replace('-', '', $opt['r']));
}

if (isset($opt['v'])) {
    $options->verbosity = true;
} else {
    $options->verbosity = false;
}

if (isset($opt['help']) || isset($opt['h'])) {
    $showusage();
}

if (isset($opt['n']) || isset($opt['new'])) {
    $template = UpgradeHandler::getPathToUpdates() . '/Template.php';
    if (!is_readable($template)) {
        $console->error('Could not open template file for reading ' . $template);
        exit();
    }
    $released = gmdate('YmdHis');
    $pathname = UpgradeHandler::getPathToUpdates() . '/Update' . $released . '.php';
    $tpl = PhpTemplate::load($template, array(
        'upd_released' => $released,
        'upd_uuid'     => \Scalr::GenerateUID(),
    ));

    if ($console->confirm("Are you sure you want to create a new upgrade class?")) {
        if (file_put_contents($pathname, $tpl) === false) {
            $console->error('Could not write to file "%s"', $pathname);
            exit();
        }
        $console->success('Upgrade class "%s" has been successfully created.', realpath($pathname));
    }
    exit();
}

if (isset($opt['force'])) {
    UpgradeHandler::removePid();
}

$upgrade = new UpgradeHandler($options);
$upgrade->run();
