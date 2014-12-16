<?php

class Scalr_UI_Response
{
    public $body = '';
    public $headers = [];
    public $httpResponseCode = 200;
    public $jsResponse = ['success' => true];
    public $jsResponseFlag = false;
    public $serverDebugLog = [];
    public $uiDebugLog = [];

    private $_serverDebugEnabled = false;

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

    /**
     * Normalizes a header name to X-Capitalized-Names
     *
     * @param  string $name
     */
    protected function normalizeHeader($name)
    {
        $filtered = str_replace(['-', '_'], ' ', (string) $name);
        $filtered = ucwords(strtolower($filtered));
        $filtered = str_replace(' ', '-', $filtered);

        return $filtered;
    }

    /**
     * Sets a header with a value
     *
     * @param   string    $name     A name of the header
     * @param   string    $value    A value
     * @param   boolean   $replace  optional
     */
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

    /**
     * Gets a value of the header
     *
     * @param    string    $name   The name of the header
     * @return   string    Returns the value of the header or NULL if it does not exist
     */
    public function getHeader($name)
    {
        $name = $this->normalizeHeader($name);

        //Last header being set
        foreach (array_reverse($this->headers) as $v) {
            if ($name == $v['name']) {
                $value = $v['value'];
                break;
            }
        }

        return isset($value) ? $value : null;
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
        $this->headers = [];
        $this->httpResponseCode = 200;
        $this->jsResponse = ['success' => true];
        $this->jsResponseFlag = false;
        $this->serverDebugLog = [];
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
        //if (! isset($_REQUEST['X-Requested-With']) && $_REQUEST['X-Requested-With'] == 'XMLHttpRequest') {
            // when we do file uploads, big log break json parser, may be some issue in extjs 4.2.2
        if ($this->_serverDebugEnabled)
            $this->jsResponse['scalrDebugLog'] = $this->serverDebugLog;
        //}

        $this->setResponse(json_encode($this->jsResponse));

        if (count($this->uiDebugLog))
            $this->setHeader('X-Scalr-Debug', json_encode($this->uiDebugLog));

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
            $this->getModuleName("override.js"),
            $this->getModuleName("init.js"),
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

    public function success($message = null, $rawHtml = false)
    {
        if ($message) {
            if (! $rawHtml) {
                // Ext.decode can't decode encoded-quotas
                $message = str_replace("\n", '<br />', htmlspecialchars($message, ENT_NOQUOTES | ENT_HTML5));
            }

            $this->jsResponse['successMessage'] = $message;
        }

        $this->jsResponseFlag = true;
    }

    public function failure($message = null, $rawHtml = false)
    {
        if ($message) {
            if (! $rawHtml) {
                $message = str_replace("\n", '<br />', htmlspecialchars($message, ENT_NOQUOTES | ENT_HTML5));
            }

            $this->jsResponse['errorMessage'] = $message;
        }

        $this->jsResponse['success'] = false;
        $this->jsResponseFlag = true;
    }

    public function warning($message = null, $rawHtml = false)
    {
        if ($message) {
            if (! $rawHtml) {
                $message = str_replace("\n", '<br />', htmlspecialchars($message, ENT_NOQUOTES | ENT_HTML5));
            }

            $this->jsResponse['warningMessage'] = $message;
        }

        $this->jsResponseFlag = true;
    }

    public function data($arg)
    {
        $this->jsResponse = array_merge($this->jsResponse, $arg);
        $this->jsResponseFlag = true;
    }

    public function varDump($value)
    {
        $this->debugVar($value, 'var', true);
    }

    public function debugVar($value, $key = 'var', $sentByHeader = false)
    {
        if ($sentByHeader) {
            $this->uiDebugLog[] = array($key => print_r($value, true));
        } else {
            $this->serverDebugLog[] = array('name' => $key, 'value' => print_r($value, true));
        }
    }

    public function debugException(Exception $e)
    {
        $this->serverDebugLog[] = ['name' => 'exception', 'value' => $e->getMessage() . '<br>' . $e->getTraceAsString()];
    }

    public function debugEnabled($flag)
    {
        $this->_serverDebugEnabled = $flag;
        $this->debugMysql($flag);
    }

    public function debugMysql($enabled = true)
    {
        global $ADODB_OUTP;

        if ($enabled) {
            $ADODB_OUTP = function($msg, $newline) {
                static $i = 1;

                $msg = str_replace('<br>', '', $msg);
                $msg = str_replace('(mysqli): ', '', $msg);
                Scalr_UI_Response::getInstance()->serverDebugLog[] = array('name' => 'sql', 'value' => $msg);
            };

            Scalr::getDb()->debug = -1;
            if (Scalr::getContainer()->analytics->enabled) {
                Scalr::getContainer()->cadb->debug = -1;
            }
        } else {
            if (Scalr::getContainer()->analytics->enabled) {
                Scalr::getContainer()->cadb->debug = false;
            }
            Scalr::getDb()->debug = false;
        }
    }
}
