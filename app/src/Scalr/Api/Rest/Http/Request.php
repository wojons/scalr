<?php

namespace Scalr\Api\Rest\Http;


/**
 * REST API Request
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (09.02.2015)
 */
class Request
{
    const METHOD_HEAD    = 'HEAD';
    const METHOD_GET     = 'GET';
    const METHOD_POST    = 'POST';
    const METHOD_PUT     = 'PUT';
    const METHOD_PATCH   = 'PATCH';
    const METHOD_DELETE  = 'DELETE';
    const METHOD_OPTIONS = 'OPTIONS';

    protected static $formDataMediaTypes = ['application/x-www-form-urlencoded'];

    protected static $jsonDataMediaTypes = ['application/json'];

    /**
     * Application environment variables
     *
     * @var \Scalr\Api\Rest\Environment
     */
    private $env;

    /**
     * Constructor
     *
     * @param   \Scalr\Api\Rest\Environment   $appEnv Application environment variables
     */
    public function __construct(\Scalr\Api\Rest\Environment $appEnv)
    {
        $this->env = $appEnv;
    }

    /**
     * Checks whether specified method is accepted for the request
     *
     * @param    string     $method HTTP Method
     * @return   boolean    Returns TRUE if the specified method is accepted for the request
     */
    public static function hasMethod($method)
    {
        return defined("static::METHOD_" . strtoupper($method));
    }

    /**
     * Gets HTTP Request method
     *
     * @return  string  Returns HTTP Request method
     */
    public function getMethod()
    {
        return isset($this->env['REQUEST_METHOD']) ? $this->env['REQUEST_METHOD'] : null;
    }

    /**
     * Gets Content length
     *
     * @return   int   Returns content length
     */
    public function getContentLength()
    {
        return isset($this->env['CONTENT_LENGTH']) ? $this->env['CONTENT_LENGTH'] : 0;
    }

    /**
     * Gets server port
     *
     * @return   number Returns server port
     */
    public function getPort()
    {
        return (int)$this->env['SERVER_PORT'];
    }

    /**
     * Gets server host
     *
     * @return   string  Returns server host
     */
    public function getHost()
    {
        if (isset($this->env['HTTP_HOST'])) {
            if (strpos($this->env['HTTP_HOST'], ':') !== false) {
                $r = explode(':', $this->env['HTTP_HOST'], 2);

                return $r[0];
            }

            return $this->env['HTTP_HOST'];
        }

        return $this->env['SERVER_NAME'];
    }

    /**
     * Gets scheme
     *
     * @return   string  Returns scheme (http or https)
     */
    public function getScheme()
    {
        return $this->env['SCHEME'];
    }

    /**
     * Gets script name
     *
     * @return   string   Returns script name
     */
    public function getScriptName()
    {
        return $this->env['SCRIPT_NAME'];
    }

    /**
     * Gets path info
     *
     * @return   string Returns path info
     */
    public function getPathInfo()
    {
        return $this->env['PATH_INFO'];
    }

    /**
     * Gets path
     *
     * @return string Returns path
     */
    public function getPath()
    {
        return $this->env['SCRIPT_NAME'] . $this->env['PATH_INFO'];
    }

    /**
     * Gets URL
     *
     * @return   string  Returns URL
     */
    public function getUrl()
    {
        $url = $this->getScheme() . '://' . $this->getHost();

        if (($this->getScheme() === 'https' && $this->getPort() !== 443) ||
            ($this->getScheme() === 'http' && $this->getPort() !== 80)) {
            $url .= sprintf(':%s', $this->getPort());
        }

        return $url;
    }

    /**
     * Gets IP address
     *
     * @return   string|null  Returns IP address
     */
    public function getIp()
    {
        $ret = null;

        foreach (['X_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (isset($this->env[$key])) {
                $ret = $this->env[$key];
                break;
            }
        }

        return $ret;
    }

    /**
     * Gets HTTP Referer
     *
     * @return string|null Gets HTTP Referer
     */
    public function getReferer()
    {
        return isset($this->env['HTTP_REFERER']) ? $this->env['HTTP_REFERER'] : null;
    }

    /**
     * Gets User Agent
     *
     * @return string|null Returns User Agent
     */
    public function getUserAgent()
    {
        return isset($this->env['HTTP_USER_AGENT']) ? $this->env['HTTP_USER_AGENT'] : null;
    }

    /**
     * Gets HTTP origin
     *
     * @return string|null
     */
    public function getOrigin()
    {
        return isset($this->env['HTTP_ORIGIN']) ? $this->env['HTTP_ORIGIN'] : null;
    }

    /**
     * Whether content type is application/x-www-form-urlencoded
     *
     * @return bool Returns true if content type is a form data
     */
    public function isFormData()
    {
        return $this->getMethod() === self::METHOD_POST && $this->getContentType() === null ||
               in_array($this->getMediaType(), self::$formDataMediaTypes);
    }

    /**
     * Whether content type is application/json
     *
     * @return bool Returns TRUE if content type is json
     */
    public function isJsonData()
    {
        return in_array($this->getMediaType(), [self::$jsonDataMediaTypes]);
    }

    /**
     * Gets request raw body
     *
     * @return string Returns request raw body
     */
    public function getBody()
    {
        return $this->env['raw.body'];
    }

    /**
     * Gets request's json decoded body
     *
     * @return mixed Returns json decoded body
     */
    public function getJsonBody()
    {
        return @json_decode($this->env['raw.body']);
    }

    /**
     * Gets Content Type
     *
     * @return   string|null
     */
    public function getContentType()
    {
        return isset($this->env['CONTENT_TYPE']) ? $this->env['CONTENT_TYPE'] : null;
    }

    /**
     * Gets media type
     *
     * @return string|null Returns media type
     */
    public function getMediaType()
    {
        $ret = null;
        $contentType = $this->getContentType();

        if ($contentType) {
            $parts = preg_split('/\s*[;,]\s*/', $contentType, 2);
            $ret = strtolower($parts[0]);
        }

        return $ret;
    }

    /**
     * Gets media type parameters
     *
     * @return   array  Returns media type parameters
     */
    public function getMediaTypeParams()
    {
        $contentType = $this->getContentType();
        $params = [];

        if ($contentType) {
            $parts = preg_split('/\s*[;,]\s*/', $contentType);
            $len = count($parts);
            for ($i = 1; $i < $len; $i++) {
                $paramParts = explode('=', $parts[$i]);
                $params[strtolower($paramParts[0])] = $paramParts[1];
            }
        }

        return $params;
    }

    /**
     * Gets GET data
     *
     * @param    string    $key      optional Variable name
     * @param    string    $default  optional Default value for the variable
     * @return   mixed     Returns variable value
     */
    public function get($key = null, $default = null)
    {
        if (!isset($this->env['request.query'])) {
            $data = [];

            if (function_exists('mb_parse_str')) {
                mb_parse_str($this->env['QUERY_STRING'], $data);
            } else {
                parse_str($this->env['QUERY_STRING'], $data);
            }

            $this->env['request.query'] = $data;
        }

        return $key !== null ? (isset($this->env['request.query'][$key]) ? $this->env['request.query'][$key] : $default) :
               $this->env['request.query'];
    }

    /**
     * Gets POST data
     *
     * @param    string    $key      optional Variable name
     * @param    string    $default  optional Default value for the variable
     * @return   mixed     Returns variable value
     */
    public function post($key = null, $default = null)
    {
        if (!isset($this->env['request.post'])) {
            if ($this->isJsonData() && !empty($this->env['raw.body'])) {
                $this->env['request.post'] = @json_decode(trim($this->env['raw.body']));
            } else if ($this->isFormData() && is_string($this->env['raw.body'])) {
                $data = [];

                if (function_exists('mb_parse_str')) {
                    mb_parse_str($this->env['raw.body'], $data);
                } else {
                    parse_str($this->env['raw.body'], $data);
                }

                $this->env['request.post'] = $data;
            } else {
                $this->env['request.post'] = $_POST;
            }
        }

        if ($key !== null) {
            if (is_object($this->env['request.post'])) {
                return isset($this->env['request.post']->{$key}) ? $this->env['request.post']->{$key} : $default;
            } else {
                return isset($this->env['request.post'][$key]) ? $this->env['request.post'][$key] : $default;
            }
        } else {
            return $this->env['request.post'];
        }
    }

    /**
     * Gets PUT data
     *
     * @param    string    $key      optional Variable name
     * @param    string    $default  optional Default value for the variable
     * @return   mixed     Returns variable value
     */
    public function put($key = null, $default = null)
    {
        return $this->post($key, $default);
    }

    /**
     * Gets DELETE data
     *
     * @param    string    $key      optional Variable name
     * @param    string    $default  optional Default value for the variable
     * @return   mixed     Returns variable value
     */
    public function delete($key = null, $default = null)
    {
        return $this->post($key, $default);
    }

    /**
     * Gets PATCH data
     *
     * @param    string    $key      optional Variable name
     * @param    string    $default  optional Default value for the variable
     * @return   mixed     Returns variable value
     */
    public function patch($key = null, $default = null)
    {
        return $this->post($key, $default);
    }

    /**
     * Gets header
     *
     * @param   string $key      optional The header name
     * @param   string $default  optional Default value
     * @return  string Returns header value
     */
    public function headers($key = null, $default = null)
    {
        if (!is_null($key)) {
            $key = strtolower($key);
        }

        return $key !== null ? (isset($this->env['request.headers'][$key]) ? $this->env['request.headers'][$key] : $default) :
               $this->env['request.headers'];
    }
}