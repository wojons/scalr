<?php

namespace Scalr\Service\Azure\Client;
use http\Client\Response;

/**
 * ClientResponseInterface
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
interface ClientResponseInterface
{
    /**
     * Gets an HTTP Response
     *
     * @return Response Returns an http Response object
     */
    public function getHttpResponse();

    /**
     * Gets a content
     *
     * @return  string  Returns a content that is received by server
     */
    public function getContent();

    /**
     * Gets response result data
     *
     * @return  mixed  Returns json-decoded response body or false
     */
    public function getResult();

    /**
     * Checks, whether the server returns an error.
     *
     * @return  bool  Returns FALSE if no Error or throws an exception
     * @throws  \Scalr\Service\Azure\Exception\RestClientException
     */
    public function hasError();

    /**
     * Gets response code
     *
     * @return  number Returns response code
     */
    public function getResponseCode();

    /**
     * Gets response status string
     *
     * @return  string  Returns response status string
     */
    public function getResponseStatus();

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