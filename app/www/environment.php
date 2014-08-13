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

if (empty($_REQUEST["version"])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$args = "";
foreach ($_REQUEST as $k => $v) {
    $args .= "{$k} = {$v}, ";
}
$args = trim($args, ",");

try {
    $EnvironmentObject = ScalrEnvironmentFactory::CreateEnvironment($_REQUEST['version']);
    $response = $EnvironmentObject->Query($_REQUEST['operation'], array_merge($_GET, $_POST));
} catch (\Scalr\Exception\Http\HttpException $e) {
	$e->terminate();
    exit;
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

header("Content-Type: text/xml");
print $response;
exit;
