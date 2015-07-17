<?php

namespace Scalr\Api\Rest;

/**
 * Application environment variables
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (10.02.2015)
 */
class Environment implements \ArrayAccess, \IteratorAggregate
{
    /**
     * The set of the properties
     *
     * @var array
     */
    private $properties;

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        return isset($this->properties[$offset]);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetGet()
     */
    public function &offsetGet($offset)
    {
        $item = &$this->properties[$offset];

        return $item;
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value)
    {
        $this->properties[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        unset($this->properties[$offset]);
    }

    /**
     * {@inheritdoc}
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->properties);
    }

    /**
     * Constructor
     *
     * @param array $properties optional Array of custom properties
     */
    public function __construct(array $properties = null)
    {
        if (!empty($properties)) {
            foreach ($properties as $key => $property) {
                $this->properties[$key] = $property;
            }
        } else {
            $this->properties = [
                'request.headers' => [],
            ];

            foreach(['REQUEST_METHOD', 'REMOTE_ADDR', 'SERVER_NAME', 'SERVER_PORT', 'CLIENT_IP'] as $key) {
                if (!isset($_SERVER[$key])) {
                    continue;
                }

                $this->properties[$key] =  $_SERVER[$key];
            }

            $this->properties['QUERY_STRING'] = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

            if (strpos($requestUri, $_SERVER['SCRIPT_NAME']) !== false) {
                $path = $_SERVER['SCRIPT_NAME'];
            } else {
                $path = str_replace('\\', '', dirname($_SERVER['SCRIPT_NAME']));
            }

            $this->properties['SCRIPT_NAME'] = rtrim($path, '/');
            $this->properties['PATH_INFO'] = '/' . ltrim(str_replace('?' . $this->properties['QUERY_STRING'], '',
                    substr_replace($requestUri, '', 0, strlen($path))), '/');

            foreach ($_SERVER as $key => $value) {
                $p = [];
                if (($p[0] = strpos($key, 'X_')) === 0 || ($p[1] = strpos($key, 'HTTP_')) === 0 ||
                    in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'PHP_AUTH_USER', 'PHP_AUTH_PW', 'PHP_AUTH_DIGEST', 'AUTH_TYPE'])) {
                    if ($key === 'HTTP_CONTENT_LENGTH') {
                        continue;
                    }

                    $this->properties[$key] = $value;

                    $name = strtolower(str_replace('_', '-', (!empty($p) ? substr($key, isset($p[1]) ? 5 : 2) : $key)));

                    $this->properties['request.headers'][$name] = $value;

                    unset($p);
                }
            }

            $this->properties['SCHEME'] = empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http' : 'https';

            $rawBody = @file_get_contents('php://input');
            $this->properties['raw.body'] = $rawBody ? $rawBody : '';
        }

    }
}