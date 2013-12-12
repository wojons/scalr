<?php

class Scalr_UI_Response
{
    public $body = '';
    public $headers = array();
    public $httpResponseCode = 200;
    public $jsResponse = array('success' => true);
    public $jsResponseFlag = false;
    public $serverDebugLog = array();
    public $serverDebugSql = array();
    public $uiDebugLog = array();

    private static $_instance = null;

    /**
     *
     * @return Scalr_UI_Response
     */
    public static function getInstance()
    {
        if (self::$_instance === null)
            self::$_instance = new Scalr_UI_Response();

        return self::$_instance;
    }

    public function pageNotFound()
    {
        $this->setHttpResponseCode(404);
    }

    public function pageAccessDenied()
    {
        $this->setHttpResponseCode(403);
        //throw new Exception('Access denied');
    }

    /*
     *Normalizes a header name to X-Capitalized-Names
     */
    protected function normalizeHeader($name)
    {
        $filtered = str_replace(array('-', '_'), ' ', (string) $name);
        $filtered = ucwords(strtolower($filtered));
        $filtered = str_replace(' ', '-', $filtered);
        return $filtered;
    }

    public function setHeader($name, $value, $replace = false)
    {
        $name = $this->normalizeHeader($name);
        $value = (string) $value;

        if ($replace) {
            foreach ($this->headers as $key => $header) {
                if ($name == $header['name'])
                    unset($this->headers[$key]);
            }
        }

        $this->headers[] = array(
            'name' => $name,
            'value' => $value,
            'replace' => $replace
        );
    }

    public function setRedirect($url, $code = 302)
    {
        $this->setHeader('Location', $url, true);
        $this->setHttpResponseCode($code);
    }

    public function setHttpResponseCode($code)
    {
        $this->httpResponseCode = $code;
    }

    public function setResponse($value)
    {
        $this->body = $value;
    }

    public function sendResponse()
    {
        $response = $this->getResponse();

        foreach ($this->headers as $header) {
            header($header['name'] . ': ' . $header['value'], $header['replace']);
        }

        $cookies = array();
        foreach(headers_list() as $header) {
            $matches = explode(':', $header, 2);
            if (trim($matches[0]) == 'Set-Cookie') {
                $kv = explode('=', trim($matches[1]), 2);
                $cookies[$kv[0]] = $kv[1];
            }
        }

        header_remove('Set-Cookie');
        foreach ($cookies as $key => $value)
            header("Set-Cookie: {$key}={$value}", false);

        header("HTTP/1.0 {$this->httpResponseCode}");
        echo $response;
    }

    public function resetResponse()
    {
        $this->body = '';
        $this->headers = array();
        $this->httpResponseCode = 200;
        $this->jsResponse = array('success' => true);
        $this->jsResponseFlag = false;
        $this->serverDebugLog = array();
        $this->uiDebugLog = array();
    }

    public function getResponse()
    {
        if ($this->jsResponseFlag)
            $this->prepareJsonResponse();

        return $this->body;
    }

    /* JS response methods */
    // divide into set headers and set body
    public function prepareJsonResponse()
    {
        if (count($this->serverDebugSql))
            $this->jsResponse['scalrDebugModeSql'] = $this->serverDebugSql;

        $this->setResponse(json_encode($this->jsResponse));

        $output = array();
        $createdVars = array();
        foreach ($this->uiDebugLog as $value) {
            if (isset($createdVars[$value['name']])) {
                $output[$value['name']][] = $value['value'];
            } else if (! isset($createdVars[$value['name']])) {
                if (isset($output[$value['name']])) {
                    $output[$value['name']] = array($output[$value['name']]);
                    $output[$value['name']][] = $value['value'];
                    $createdVars[$value['name']] = true;
                } else {
                    $output[$value['name']] = $value['value'];
                }
            }
        }

        if ($output)
            $this->setHeader('X-Scalr-Debug', json_encode($output));

        if (isset($_REQUEST['X-Requested-With']) && $_REQUEST['X-Requested-With'] == 'XMLHttpRequest')
            $this->setHeader('content-type', 'text/html', true); // hack for ajax file uploads and other cases
        else
            $this->setHeader('content-type', 'text/javascript', true);
    }

    public function getModuleName($name)
    {
        $v = Scalr_UI_Request::getInstance()->getHeaderVar('Interface');
        $vPath = !is_null($v) ? intval(trim($v, 'v')) : '2';
        $path = "ui{$vPath}/js/";

        $fl = APPPATH . "/www/{$path}{$name}";
        if (file_exists($fl))
            $tm = filemtime(APPPATH . "/www/{$path}{$name}");
        else
            throw new Scalr_UI_Exception_NotFound(sprintf('Js file not found'));

        $nameTm = str_replace('.js', "-{$tm}.js", $name);
        $nameTm = str_replace('.css', "-{$tm}.css", $nameTm);

        return "/{$path}{$nameTm}";
    }

    public function pageUiHash()
    {
        return sha1(join(';', array(
            $this->getModuleName("init.js"),
            $this->getModuleName("override.js"),
            $this->getModuleName("utils.js"),
            $this->getModuleName("ui-form.js"),
            $this->getModuleName("ui-grid.js"),
            $this->getModuleName("ui-plugins.js"),
            $this->getModuleName("ui.js"),
            $this->getModuleName("ui.css")
        )));
    }

    public function page($name, $params = array(), $requires = array(), $requiresCss = array(), $requiresData = array())
    {
        $this->jsResponse['moduleName'] = $this->getModuleName($name);
        $this->jsResponse['moduleParams'] = $params;

        if (count($requires)) {
            foreach ($requires as $key => $value)
                $this->jsResponse['moduleRequires'][] = $this->getModuleName($value);
        }

        if (count($requiresCss)) {
            foreach ($requiresCss as $key => $value)
                $this->jsResponse['moduleRequiresCss'][] = $this->getModuleName($value);
        }

        if (count($requiresData)) {
            $this->jsResponse['moduleRequiresData'] = $requiresData;
        }

        $this->jsResponse['moduleUiHash'] = $this->pageUiHash();
        $this->jsResponseFlag = true;
    }

    public function success($message = null)
    {
        if ($message)
            $this->jsResponse['successMessage'] = $message;

        $this->jsResponseFlag = true;
    }

    public function failure($message = null)
    {
        if ($message)
            $this->jsResponse['errorMessage'] = $message;

        $this->jsResponse['success'] = false;
        $this->jsResponseFlag = true;
    }

    public function warning($message = null)
    {
        if ($message)
            $this->jsResponse['warningMessage'] = $message;

        $this->jsResponseFlag = true;
    }

    public function data($arg)
    {
        $this->jsResponse = array_merge($this->jsResponse, $arg);
        $this->jsResponseFlag = true;
    }

    public function varDump($value, $name = 'var')
    {
        $this->uiDebugLog[] = array('name' => $name, 'value' => $value);
    }

    public function debugLog($key, $value)
    {
        $this->serverDebugLog[] = array('key' => $key, 'value' => print_r($value, true));
    }

    public function debugMysql($enabled = true)
    {
        global $ADODB_OUTP;

        $db = Scalr::getDb();
        if ($enabled) {
            $ADODB_OUTP = function($msg, $newline) {
                static $i = 1;

                $msg = str_replace('<br>', '', $msg);
                $msg = str_replace('(mysqli): ', '', $msg);
                Scalr_UI_Response::getInstance()->serverDebugSql[] = array('sql' => $msg);
            };

            $db->debug = -1;
        } else {
            $db->debug = false;
        }
    }
}
