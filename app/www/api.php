<?php

use Scalr\Util\CryptoTool;

$path = trim(str_replace("?{$_SERVER['QUERY_STRING']}", "", $_SERVER['REQUEST_URI']), '/');

@session_start();

try {
    require "src/prepend.inc.php";

    $keyId = $_SERVER['HTTP_X_SCALR_AUTH_KEY'];
    $token = $_SERVER['HTTP_X_SCALR_AUTH_TOKEN'];
    $envId = (int)$_SERVER['HTTP_X_SCALR_ENV_ID'];
    $pathChunks = explode('/', $path);
    $version = array_shift($pathChunks);
    $path = '/' . $path;

    $user = Scalr_Account_User::init();
    $user->loadByApiAccessKey($keyId);

    if (!$user->getSetting(Scalr_Account_User::SETTING_API_ENABLED))
        throw new Exception("API disabled for this account");

    //Check IP whitelist

    $postData = isset($_POST['rawPostData']) ? $_POST['rawPostData'] : '';
    $secretKey = $user->getSetting(Scalr_Account_User::SETTING_API_SECRET_KEY);
    $stringToSign = "{$path}:{$keyId}:{$envId}:{$postData}:{$secretKey}";
    $validToken = CryptoTool::hash($stringToSign);

    if ($validToken != $token)
        throw new Exception("Invalid authentification token");

    // prepate input data
    $postDataConvert = array();
    foreach (json_decode($postData, true) as $key => $value) {
        $postDataConvert[str_replace('.', '_', $key)] = $value;
    }

    $request = Scalr_UI_Request::initializeInstance(Scalr_UI_Request::REQUEST_TYPE_API, apache_request_headers(), $_SERVER, $postDataConvert, $_FILES, $user->id, $envId);
    $request->requestApiVersion = intval(trim($version, 'v'));

    Scalr_Api_Controller::handleRequest($pathChunks);
    Scalr_UI_Response::getInstance()->sendResponse();
} catch (Exception $e) {
    Scalr_UI_Response::getInstance()->failure($e->getMessage());
    Scalr_UI_Response::getInstance()->sendResponse();
}
