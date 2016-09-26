<?php

namespace Scalr\System\Http\Client;

use finfo;
use http\Exception\RuntimeException;
use http\Message\Body;
use http\QueryString;
use http\Url;

/**
 * PECL http v2 client request wrapper
 *
 * @author N.V.
 */
class Request extends \http\Client\Request
{

    /**
     * Parse given URL representation to \http\Url object
     *
     * @param   mixed   $url
     *
     * @return Url|string
     */
    public static function parseUrl($url, $newParts = null, $flags = null)
    {
        if ($flags === null) {
            $flags = Url::PARSE_MBLOC | Url::PARSE_MBUTF8 | Url::PARSE_TOPCT;
        }

        if (!$url instanceof Url) {
            $url = new Url($url, $newParts, $flags);
        }

        return $url;
    }

    /**
     * {@inheritdoc}
     * @see \http\Client\Request::__construct()
     */
    public function __construct($meth = null, $url = null, array $headers = null, Body $body = null)
    {
        parent::__construct($meth, static::parseUrl($url), $headers, $body);
    }

    /**
     * Append plain bytes to the message body.
     *
     * @param string|array $data The data to append to the body.
     *
     * @return Request
     *
     * @throws \http\Exception\InvalidArgumentException
     * @throws RuntimeException
     */
    public function append($data)
    {
        $this->getBody()->append(is_array($data) ? (new QueryString($data))->toString() : $data);

        return $this;
    }

    /**
     * Returns key-value array of POST/PUT parameters
     *
     * @return array
     */
    public function getPostParams()
    {
        return (new QueryString($this->getBody()))->toArray();
    }

    /**
     * Add files to the message body.
     *
     * @param array $files List of form files to add.
     *
     * @return Request
     *
     * @throws \http\Exception\InvalidArgumentException
     * @throws RuntimeException
     */
    public function addFiles($files)
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($files as &$file) {
            if (is_array($file)) {
                $fileName = $file['file'];
            } else {
                $fileName = $file;
                $file = [ 'file' => $fileName ];
            }

            if (!isset($file['name'])) {
                $file['name'] = pathinfo($fileName, PATHINFO_BASENAME);
            }

            if (!isset($file['type'])) {
                $file['type'] = $finfo->file($fileName);
            }
        }

        $this->getBody()->addForm(null, $files);

        return $this;
    }

    /**
     * Retrieve the message’s body as string.
     *
     * @return string
     */
    public function getRawBody()
    {
        return $this->getBody()->toString();
    }

    /**
     * {@inheritdoc}
     * @see \http\Client::setRequestUrl()
     */
    public function setRequestUrl($url)
    {
        return parent::setRequestUrl($this->parseUrl($url));
    }
}