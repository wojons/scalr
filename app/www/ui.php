<?php

//This handler is rewritten by prepend.inc.php
register_shutdown_function(function () {
    $error = error_get_last();

    if ($error && (
        $error['type'] == E_ERROR ||
        $error['type'] == E_PARSE ||
        $error['type'] == E_COMPILE_ERROR
    )) {
        if (!headers_sent()) {
            header("HTTP/1.0 500");
        }
    }

    //Collects access log with processing time
    $accessLogPath = \Scalr::config('scalr.system.monitoring.access_log_path');
    if ($accessLogPath && is_writable($accessLogPath)) {
        global $response, $path;
        if (isset($path) && $response instanceof Scalr_UI_Response) {
            @error_log(
                sprintf("%s,%s,\"%s\",%0.4f,%0.4f,%d\n",
                    date('M d H:i:s P'),
                    ($error && !empty($error['type']) ? $error['type'] : 'OK'),
                    str_replace('"', '""', $path),
                    $response->getHeader('X-Scalr-Inittime'),
                    $response->getHeader('X-Scalr-Actiontime'),
                    \Scalr::getDb()->numberQueries + (\Scalr::getContainer()->analytics->enabled ? \Scalr::getContainer()->cadb->numberQueries : 0)
                ),
                3,
                $accessLogPath
            );
        }
    }
});

//NOTE: Apache mod_rewrite sets REDIRECT_URL instead of REQUEST_URI environment variable, we need to get final overridden URI
$path = trim(str_replace("?{$_SERVER['QUERY_STRING']}", "", isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['REQUEST_URI']), '/');

$logMysqlExcepton = function($e) {
    \Scalr::logException($e);
    Scalr_UI_Response::getInstance()->data(array('errorDB' => true));
    Scalr_UI_Response::getInstance()->debugException($e);
    Scalr_UI_Response::getInstance()->failure($e instanceof \Scalr\Exception\MysqlConnectionException ? 'Database connection issue' : 'Database error');
    Scalr_UI_Response::getInstance()->sendResponse();
};

try {
    $startTime = microtime(true);
    require __DIR__ . '/src/prepend.inc.php';
    $prependTime = microtime(true);

    // public controller for link like /public/*; don't check CSRF
    $publicController = !strncmp('public', $path, strlen('public'));

    $headers = Scalr::getAllHeaders();

    $session = Scalr_Session::getInstance(isset($headers['Scalr-Autoload-Request']));

    $time1 = microtime(true);

    try {
        $request = Scalr_UI_Request::initializeInstance(Scalr_UI_Request::REQUEST_TYPE_UI, $headers, $_SERVER, $_REQUEST, $_FILES, $session->getUserId(), null);
    } catch (Exception $e) {
        if ($path == 'guest/logout') {
            // hack
            Scalr_Session::destroy();
            Scalr_UI_Response::getInstance()->setRedirect('/');
            Scalr_UI_Response::getInstance()->sendResponse();
            exit;
        }
        $message = $e->getMessage();
        if ($e->getCode() != 1) {
            $message = htmlspecialchars($message) . ' <a href="/guest/logout">Click here to login as another user</a>';
            Scalr_UI_Response::getInstance()->debugException($e);
            Scalr_UI_Response::getInstance()->failure($message, true);
            throw new Exception();
        } else {
            throw new Exception($message);
        }
    }

    $time2 = microtime(true);

    $response = Scalr_UI_Response::getInstance();

    $time3 = microtime(true);

    if ($session->getDebugMode()) {
        $response->debugEnabled(true);
    }

    $time4 = microtime(true);

    if ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST') {
        // check against CSRF
        $possibleCsrf = false;
        $header = $request->getHeaderVar('Token');
        $var = $request->getParam('X-Requested-Token');
        // check header, otherwise check var
        if ($header != 'key' || $session->isAuthenticated()) {
            // authenticated users validated by token ONLY
            if (($var != $session->getToken()) || !$session->getToken())
                $possibleCsrf = true;
        }

        $time5 = microtime(true);

        if ($path == 'guest/logout' || $path == 'guest/xCreateAccount' || $publicController)
            $possibleCsrf = false;

        if ($possibleCsrf) {
            //Scalr_Session::destroy();
            $response->failure('Your session became invalid. <a href="/guest/logout">Click here to login again</a>', true);
            $response->sendResponse();
        } else {
            $initTime = microtime(true);

            $response->setHeader("X-Scalr-PrependTime", $prependTime-$startTime);
            $response->setHeader("X-Scalr-InitTime", $initTime-$prependTime);
            $response->setHeader("X-Scalr-InitTime1", $time1-$prependTime);
            $response->setHeader("X-Scalr-InitTime2", $time2-$prependTime);
            $response->setHeader("X-Scalr-InitTime3", $time3-$prependTime);
            $response->setHeader("X-Scalr-InitTime4", $time4-$prependTime);
            $response->setHeader("X-Scalr-InitTime5", $time5-$prependTime);

            Scalr_UI_Controller::handleRequest(explode('/', $path));
            Scalr_UI_Response::getInstance()->sendResponse();
        }
    } else {
        Scalr_UI_Response::getInstance()->setHeader("X-Scalr-Forbiden", "3: {$_SERVER['HTTP_HOST']}");
        Scalr_UI_Response::getInstance()->setHttpResponseCode(403);
        Scalr_UI_Response::getInstance()->sendResponse();
    }

} catch (ADODB_Exception $e) {
    $logMysqlExcepton($e);
} catch (\Scalr\Exception\MysqlConnectionException $e) {
    $logMysqlExcepton($e);
} catch (\Scalr\Exception\FileNotFoundException $e) {
    Scalr_UI_Response::getInstance()->failure(sprintf("File '%s' not found", $e->getPath()));
    Scalr_UI_Response::getInstance()->setHttpResponseCode(404);
    Scalr_UI_Response::getInstance()->sendResponse();
} catch (Exception $e) {
    Scalr_UI_Response::getInstance()->failure($e->getMessage());
    Scalr_UI_Response::getInstance()->debugException($e);
    Scalr_UI_Response::getInstance()->sendResponse();
}
