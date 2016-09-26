<?php

require __DIR__ . "/../src/prepend.inc.php";

$Logger = \Scalr::getContainer()->logger('Application');

if (empty($_REQUEST["version"])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

try {
    $EnvironmentObject = ScalrEnvironmentFactory::CreateEnvironment($_REQUEST['version']);
    print $EnvironmentObject->Query($_REQUEST['operation'], array_merge($_GET, $_POST));
    exit;
} catch (\Scalr\Exception\Http\HttpException $e) {
	$e->terminate();
    exit;
} catch (DOMException $e) {
    header("HTTP/1.0 500 XMLSerializer error");

    print "--------- Error ----------\n";
    print $e->getMessage() . "\n\n";
    print "--------- Trace ----------\n";
    print $e->getTraceAsString() . "\n\n";
    print "--------- JSON Object ----------\n";
    print json_encode($EnvironmentObject->debugObject);
    exit();

} catch (Exception $e) {
    if ($e instanceof Scalr_Exception_InsufficientPermissions) {
        (new \Scalr\Exception\Http\ForbiddenException($e->getMessage()))->terminate();
        exit;
    }

    header("HTTP/1.0 500 Error");

    if (!stristr($e->getMessage(), "not found in database") && !stristr($e->getMessage(), "ami-scripts")) {
       $Logger->error(sprintf(_("Exception thrown in query-env interface: %s"), $e->getMessage()));
    }

    die($e->getMessage());
}
