<?php

include __DIR__ . '/../src/prepend.inc.php';

use Scalr\Api\Rest\ApiApplication;

ini_set('display_errors', '0');
ini_set('html_errors', '0');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && (in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR]))) {
        if (!headers_sent()) {
            header("HTTP/1.0 500");
        }
    }
});

$app = new ApiApplication();
$app->setupRoutes();
$app->run();