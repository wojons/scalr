<?php

include __DIR__ . '/../src/prepend.inc.php';

use Scalr\Api\Rest\ApiApplication;

ini_set('display_errors', '0');
ini_set('html_errors', '0');

$app = new ApiApplication();

register_shutdown_function(function () use ($app) {
    $error = error_get_last();
    if ($error && (in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR]))) {
        if (!headers_sent()) {
            header("HTTP/1.0 500");
        }
    }

    //Collects access log with processing time
    $accessLogPath = \Scalr::config('scalr.system.monitoring.access_log_path');
    if ($accessLogPath && is_writable($accessLogPath)) {
        @error_log(
            sprintf("%s,%s,\"%s\",%0.4f,%0.4f,%d\n",
                date('M d H:i:s P'),
                $app->response->getStatus(),
                str_replace('"', '""', $app->request->getMethod() . " " . $app->request->getPath()),
                $app->response->getHeader('X-Scalr-Inittime'),
                $app->response->getHeader('X-Scalr-Actiontime'),
                \Scalr::getDb()->numberQueries + (\Scalr::getContainer()->analytics->enabled ? \Scalr::getContainer()->cadb->numberQueries : 0)
            ),
            3,
            $accessLogPath
        );
    }
});

$app->setupRoutes()
    ->run();