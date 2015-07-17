<?php

require __DIR__ . "/../src/prepend.inc.php";

$Logger = Logger::getLogger('Application');

/*
 * Date: 2008-11-25
 * Initial Query-env interface
 */
require  __DIR__ . "/../src/class.ScalrEnvironment20081125.php";

/*
 * Date: 2008-12-16
 * Added /list-ebs-mountpoints method
 * Added /get-latest-version method
 */
require __DIR__ ."/../src/class.ScalrEnvironment20081216.php";

/*
 * Date: 2009-03-05
 * Improved /list-role-params method (Added mysql options)
 */
require __DIR__ . "/../src/class.ScalrEnvironment20090305.php";

/*
 * Date: 2010-09-23
 */
require __DIR__ . "/../src/class.ScalrEnvironment20100923.php";

/*
 * Date: 2012-04-17
 */
require __DIR__ . "/../src/class.ScalrEnvironment20120417.php";

/*
 * Date: 2012-07-01
 */
require __DIR__ . "/../src/class.ScalrEnvironment20120701.php";

/*
 * Date: 2015-04-10
 */
require __DIR__ . "/../src/class.ScalrEnvironment20150410.php";

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
