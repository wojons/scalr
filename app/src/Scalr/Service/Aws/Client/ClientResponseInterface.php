<?php

namespace Scalr\Service\Aws\Client;


/**
 * Client response interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     21.09.2012
 */
interface ClientResponseInterface
{
    /**
     * Gets raw response content.
     *
     * Gets raw content of the response that is obtained from Amazon web service.
     *
     * @return   string  Returns raw content of the response from AWS.
     */
    public function getRawContent();

    /**
     * Gets and error if it happens
     *
     * @return  boolean         Returns FALSE if no error or throws an ClientException.
     * @throws  \Scalr\Service\Aws\Client\ClientException
     */
    public function getError();

    /**
     * Gets an exception when AWS responds with an error
     *
     * @return  \Scalr\Service\Aws\Client\ClientException|null Returns client exception
     */
    public function getException();

    /**
     * Checks whether AWS responses with an error message.
     *
     * This method does not cause exception.
     *
     * @return  boolean        Returns TRUE on error or FALSE otherwise
     */
    public function hasError();

    /**
     * Gets message headers.
     *
     * @return array Returns an associative array containing the messages HTTP headers.
     */
    public function getHeaders();

    /**
     * Gets a header value.
     *
     * @param   string        $headername A header name.
     * @return  string        Returns the header value on success or NULL if the header does not exist.
     */
    public function getHeader($headername);

    /**
     * Gets a response code
     *
     * @return  int Returns HTTP Response code.
     */
    public function getResponseCode();

    /**
     * Gets a response status
     *
     * @return  string Returns HTTP Response Status.
     */
    public function getResponseStatus();

    /**
     * Gets raw request message
     *
     * @return  \HttpRequest  Returns request object
     */
    public function getRequest();

    /**
     * Sets raw request message
     *
     * @param   \HttpRequest|\SoapRequest   $request Request object
     * @return  ClientResponseInterface
     */
    public function setRequest($request);

    /**
     * Sets current query number during the user session
     *
     * @param    int     $number  The number
     * @return   ClientResponseInterface
     */
    public function setQueryNumber($number);
}