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

    private $file = null;

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
        $isFileOutput = is_readable($this->file);
        $response = !$isFileOutput ? $this->getResponse() : '';

        if ($isFileOutput &&
            isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) &&
            filemtime($this->file) < DateTime::createFromFormat('D, d M Y H:i:s T', $_SERVER["HTTP_IF_MODIFIED_SINCE"])->getTimestamp()) {
                $this->setHttpResponseCode(304);
        }

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
        foreach ($cookies as $key => $value) {
            header("Set-Cookie: {$key}={$value}", false);
        }

        header("HTTP/1.0 {$this->httpResponseCode}");

        $isFileOutput ? readfile($this->file) : print($response);
    }

    /**
     * Sends file content with response
     *
     * @param   string   $path     File path
     * @param   string[] $headers  optional Response headers
     * @param   string   $fileName optional File name
     * @param   string   $content  optional File content
     */
    public function sendFile($path, $headers = [], $fileName = null, $content = null)
    {
        if (empty($fileName)) {
            $fileName = pathinfo($path, PATHINFO_BASENAME);
        }

        $defaults = [
            'Content-Description'   => 'File Transfer',
            'Content-Type'          => 'application/octet-stream',
            'Content-Disposition'   => "attachment; filename=" . ($fileName ?: 'file'),
            'Expires'               => 0,
            'Cache-Control'         => 'must-revalidate',
            'Content-Length'        => $content === null ? filesize($path) : strlen($content)
        ];

        foreach (array_filter(array_merge($defaults, $headers), function ($entry) {
            return $entry !== false;
        }) as $header => $value) {
            $this->setHeader($header, $value);
        }

        $content === null ? $this->file = $path : $this->setResponse($content);
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
        if ($this->_serverDebugEnabled) {
            $this->jsResponse['scalrDebugLog'] = $this->serverDebugLog;
        }

        if (count($this->uiDebugLog))
            $this->setHeader('X-Scalr-Debug', json_encode($this->uiDebugLog));

        $responseText = json_encode($this->jsResponse);

        if (isset($_REQUEST['X-Requested-With']) && $_REQUEST['X-Requested-With'] == 'XMLHttpRequest') {
            // hack for ajax file uploads and other cases (use htmlentities because browser is trying to parse html tags in result)
            $this->setHeader('content-type', 'text/html', true);
            $this->setResponse(htmlentities($responseText));
        } else {
            $this->setHeader('content-type', 'text/javascript', true);
            $this->setResponse($responseText);
        }
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

    /**
     * @param array $files
     * @return string
     */
    public function calculateFilesHash($files)
    {
        return sha1(join(';', array_map(function($item) {
            return $this->getModuleName($item);
        }, $files)));
    }

    public function pageUiHash()
    {
        return $this->calculateFilesHash([
            "override.js",
            "init.js",
            "utils.js",
            "ui-form.js",
            "ui-grid.js",
            "ui-plugins.js",
            "ui.js",
            "ui.css"
        ]);
    }

    public function page($name, $params = array(), $requires = array(), $requiresCss = array(), $requiresData = array())
    {
        if (is_array($name)) {
            $this->jsResponse['moduleName'] = $this->getModuleName($name[0]);
            $this->jsResponse['moduleRequiresMain'] = [];
            foreach ($name as $n) {
                $this->jsResponse['moduleRequiresMain'][] = $this->getModuleName($n);
            }
        } else {
            $this->jsResponse['moduleName'] = $this->getModuleName($name);
            $this->jsResponse['moduleRequiresMain'] = [$this->jsResponse['moduleName']];
        }

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
                $backtrace = [];
                $b = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                while (($item = array_shift($b))) {
                    if ($item['class'] == 'Scalr\Db\ConnectionPool') {
                        $item = array_shift($b);
                        $item['file'] = strstr($item['file'], '/app/');

                        if ($item['file'] == '/app/src/Scalr/Model/AbstractEntity.php') {
                            while (($item = array_shift($b))) {
                                if ($item['class'] && $item['class'] != 'Scalr\Model\AbstractEntity') {
                                    break;
                                }
                            }
                        }

                        $item['file'] = strstr($item['file'], '/app/') ?: $item['file'];
                        $backtrace[] = "File: {$item['file']} [{$item['line']}]";
                        $backtrace[] = "Class: {$item['class']}::{$item['function']}";
                        //$backtrace[] = str_replace("\n", "<br>", print_r($item['args'], true));
                        break;
                    }
                }

                Scalr_UI_Response::getInstance()->serverDebugLog[] = array('name' => 'sql', 'value' => $msg, 'backtrace' => join("<br>", $backtrace));
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
