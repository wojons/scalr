<?php

declare(ticks = 1);

define('SCALR_MULTITHREADING', true);
define("NO_TEMPLATES", true);
define("NO_SESSIONS", true);

require_once __DIR__ . "/../src/prepend.inc.php";

$fname = basename($argv[0]);

$JobLauncher = new \Scalr\System\Pcntl\JobLauncher(dirname(__FILE__));

// DBQueueEvent - it is a daemon process so we must skeep this check
if ($JobLauncher->GetProcessName() != 'DBQueueEvent') {
    $Shell = new Scalr_System_Shell();
    // Set terminal width
    putenv("COLUMNS=200");

    // Execute command
    $parent_pid = posix_getppid();
    $ps = $Shell->queryRaw("ps x -o pid,command | grep -v -E '^ *(" . $parent_pid . "|" . posix_getpid() . ") | ps x' | grep '".dirname(__FILE__)."' | grep '\-\-{$JobLauncher->GetProcessName()}'");

    if ($ps) {
        $Logger->info("'{$fname} --{$JobLauncher->GetProcessName()}' already running. Exiting.");
        exit();
    }
}

/*
if ($JobLauncher->GetProcessName() == 'EBSManager') {
    print "SET DEBUG LEVEL\n";
    $Logger->removeAllAppenders();
    $Logger->resetConfiguration();
    $Logger->addAppender(new LoggerAppenderConsole());
    $Logger->setLevel(LoggerOptionConverter::toLevel('DEBUG', LoggerLevel::getLevelDebug()));

    Logger::getRootLogger()->removeAllAppenders();
    Logger::getRootLogger()->resetConfiguration();
    Logger::getRootLogger()->addAppender(new LoggerAppenderConsole());
    Logger::getRootLogger()->setLevel(LoggerOptionConverter::toLevel('DEBUG', LoggerLevel::getLevelDebug()));
}
*/

$Logger->info(sprintf("Starting %s cronjob...", $JobLauncher->GetProcessName()));
$JobLauncher->Launch(7, 180);
