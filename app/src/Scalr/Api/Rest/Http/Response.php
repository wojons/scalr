<?php

namespace Scalr\Api\Rest\Http;


/**
 * REST API Response
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (10.02.2015)
 */
class Response
{

    /**
     * Response status code
     *
     * @var int
     */
    private $status;

    /**
     * Response headers
     *
     * @var array
     */
    private $headers;

    /**
     * The response body
     *
     * @var string
     */
    private $body;

    /**
     * Response content type
     *
     * @var string
     */
    private $contentType = 'text/html';

    /**
     * Response encoding
     *
     * @var string
     */
    private $encoding = 'utf-8';

    /**
     * Content length
     *
     * @var int
     */
    private $contentLength;

    protected static $messages = array(
        100 => '100 Continue',
        101 => '101 Switching Protocols',
        200 => '200 OK',
        201 => '201 Created',
        202 => '202 Accepted',
        203 => '203 Non-Authoritative Information',
        204 => '204 No Content',
        205 => '205 Reset Content',
        206 => '206 Partial Content',
        300 => '300 Multiple Choices',
        301 => '301 Moved Permanently',
        302 => '302 Found',
        303 => '303 See Other',
        304 => '304 Not Modified',
        305 => '305 Use Proxy',
        306 => '306 (Unused)',
        307 => '307 Temporary Redirect',
        400 => '400 Bad Request',
        401 => '401 Unauthorized',
        402 => '402 Payment Required',
        403 => '403 Forbidden',
        404 => '404 Not Found',
        405 => '405 Method Not Allowed',
        406 => '406 Not Acceptable',
        407 => '407 Proxy Authentication Required',
        408 => '408 Request Timeout',
        409 => '409 Conflict',
        410 => '410 Gone',
        411 => '411 Length Required',
        412 => '412 Precondition Failed',
        413 => '413 Request Entity Too Large',
        414 => '414 Request-URI Too Long',
        415 => '415 Unsupported Media Type',
        416 => '416 Requested Range Not Satisfiable',
        417 => '417 Expectation Failed',
        418 => '418 I\'m a teapot',
        422 => '422 Unprocessable Entity',
        423 => '423 Locked',
        500 => '500 Internal Server Error',
        501 => '501 Not Implemented',
        502 => '502 Bad Gateway',
        503 => '503 Service Unavailable',
        504 => '504 Gateway Timeout',
        505 => '505 HTTP Version Not Supported'
    );

    /**
     * Gets the message for the specified HTTP code
     *
     * @param     int     $code
     * @return    string  Returns the message for the specified HTTP code
     */
    public static function getCodeMessage($code)
    {
        return isset(static::$messages[$code]) ? static::$messages[$code] : null;
    }

    /**
     * Constructor
     *
     * @param   string $body    The HTTP response body
     * @param   int    $status  The HTTP response status
     * @param   array  $headers The HTTP response headers
     */
    public function __construct($body = '', $status = 200, $headers = [])
    {
        $this->setStatus($status);
        $this->setHeaders($headers);
        $this->setBody($body);
    }

    /**
     * Sets status code
     *
     * @param   int      $status
     * @return  Response
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Gets response status code
     *
     * @return   number Returns status code
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * Gets response body
     *
     * @return   string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Sets response body
     *
     * @param   string    $body  The response body
     * @return  Response
     */
    public function setBody($body)
    {
        $this->body = $body;

        $this->contentLength = strlen($this->body);

        return $this;
    }

    /**
     * Add to body
     *
     * @param    string    $body  The body
     * @return   Response
     */
    public function addBody($body)
    {
        $this->body .= $body;

        $this->contentLength += strlen($body);

        return $this;
    }

    /**
     * Gets content length
     *
     * @return   number  Returns content length
     */
    public function getContentLength()
    {
        return $this->contentLength;
    }

    /**
     * Gets content type as header Content-Type value
     *
     * @return string
     */
    public function getContentType()
    {
        return "{$this->contentType}" . (empty ($this->encoding) ? "" : "; {$this->encoding}");
    }

    /**
     * Sets content MIME type and encoding
     *
     * @param   string  $type                Content MIME type
     * @param   string  $encoding   optional Content encoding
     */
    public function setContentType($type, $encoding = "utf-8")
    {
        $this->contentType = $type;
        $this->encoding = $encoding;
    }

    /**
     * Sets headers
     *
     * @param    array    $headers The response headers looks like array('header-name' => value)
     * @return   Response
     */
    public function setHeaders(array $headers)
    {
        $this->headers = [];

        return $this->addHeaders($headers);
    }

    /**
     * Adds headers
     *
     * @param    array    $headers The response headers
     * @return   Response
     */
    public function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        return $this;
    }

    /**
     * Sets header
     *
     * @param    string    $name  A header name ('heaer-name')
     * @param    string    $value A value
     * @return   Response
     */
    public function setHeader($name, $value)
    {
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    /**
     * Gets specified header
     *
     * @param    string   $name  The name of the HTTP Header
     * @return   string   Returns the value of the specified HTTP Header
     */
    public function getHeader($name)
    {
        $name = strtolower($name);

        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    /**
     * Gets all headers
     *
     * @return array Returns all HTTP headers as array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Removes specified header
     *
     * @param   string    $name  A header name
     * @return  Response
     */
    public function removeHeader($name)
    {
        $name = strtolower($name);

        if (isset($this->headers[$name])) {
            unset($this->headers[$name]);
        }

        return $this;
    }

    /**
     * Redirect
     *
     * @param   string $url    The redirect url
     * @param   int    $status optional The redirect HTTP status code
     * @return  Response
     */
    public function redirect($url, $status = 302)
    {
        $this->setStatus($status);
        $this->setHeader('location', $url);

        return $this;
    }

    /**
     * Prepares response to be sent
     *
     * @return array  Returns array looks like array(status, headers, body)
     */
    public function finalize()
    {
        $contentType = $this->getContentType();

        if (!empty($contentType)) {
            $this->setHeader("content-type", $contentType);
        }

        if (in_array($this->status, array(204, 304))) {
            $this->removeHeader('content-type')
                 ->removeHeader('content-length')
                 ->setBody('');
        }

        return [$this->status, $this->headers, $this->body];
    }
}