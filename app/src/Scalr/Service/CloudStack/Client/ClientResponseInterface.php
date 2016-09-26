<?php
namespace Scalr\Service\CloudStack\Client;

use Scalr\Service\CloudStack\Exception\RestClientException;

/**
 * ClientResponseInterface
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
interface ClientResponseInterface
{
    /**
     * Gets a content
     *
     * @return  string  Returns a content that is received by server
     */
    public function getContent();

    /**
     * Checks, whether the server returns an error.
     *
     * @return  bool  Returns FALSE if no Error or throws an exception
     * @throws  RestClientException
     */
    public function hasError();

    /**
     * Gets response code
     *
     * @return  number Returns response code
     */
    public function getResponseCode();

    /**
     * Gets response headers
     *
     * @return  array Returns response headers
     */
    public function getHeaders();

    /**
     * Gets response header by its name.
     *
     * @param  string $headerName Response header name.
     * @return string Returns response header value or null if header does not exist.
     */
    public function getHeader($headerName);
}