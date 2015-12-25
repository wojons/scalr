<?php

namespace Scalr\Service\Azure\Client;

use http\Client\Request;
use http\Client\Response;
use Scalr\Service\Azure\DataType\ErrorData;
use Scalr\Service\Azure\Exception\AzureResponseErrorFactory;
use Scalr\Service\Azure\Exception\NotFoundException;

/**
 * Azure Query Client Response
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class QueryClientResponse implements ClientResponseInterface
{
    /**
     * @var Response
     */
    private $httpResponse;

    /**
     * @var ErrorData|bool
     */
    private $errorData;

    /**
     * Request data
     *
     * @var Request
     */
    private $request;

    /**
     * Constructor
     *
     * @param  Response $response An HTTP message
     * @param  Request  $httpRequest HTTP request data
     */
    public function __construct(Response $response, Request $httpRequest)
    {
        $this->httpResponse = $response;
        $this->request = $httpRequest;
    }

    /**
     * Gets an HTTP Response
     *
     * @return Response Returns an http Response object
     */
    public function getHttpResponse()
    {
        return $this->httpResponse;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientResponseInterface::getContent()
     */
    public function getContent()
    {
        return $this->httpResponse->getBody()->toString();
    }


    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientResponseInterface::getResponseCode()
     */
    public function getResponseCode()
    {
        return $this->httpResponse->getResponseCode();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientResponseInterface::getResponseStatus()
     */
    public function getResponseStatus()
    {
        return $this->httpResponse->getResponseStatus();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientResponseInterface::getHeader()
     */
    public function getHeader($headerName)
    {
        return $this->httpResponse->getHeader($headerName);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientResponseInterface::getHeaders()
     */
    public function getHeaders()
    {
        return $this->httpResponse->getHeaders();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientResponseInterface::getResult()
     */
    public function getResult()
    {
        $responseArray = json_decode($this->getContent(), true);

        if (is_array($responseArray) && isset($responseArray['value'])) {
            return $responseArray['value'];
        }

        return $responseArray;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientResponseInterface::hasError()
     */
    public function hasError()
    {
        if (!isset($this->errorData)) {
            $this->errorData = false;
            $code = $this->getResponseCode();

            if ($code < 200 || $code > 299) {
                $this->errorData = new ErrorData();
                $responseObj = json_decode($this->getContent());

                if (empty($responseObj)) {
                    $this->errorData->message = 'Bad server response: ' . $this->getResponseStatus();
                    $this->errorData->code = $code;
                }

                $errFieldName = 'odata.error';

                if (isset($responseObj->error)) {
                    $this->errorData->message = $responseObj->error->message;
                    $this->errorData->code = $responseObj->error->code;
                } else if (isset($responseObj->$errFieldName) && $responseObj->$errFieldName->message->value) {
                    $this->errorData->message = $responseObj->$errFieldName->message->value;
                    $this->errorData->code = $responseObj->$errFieldName->code;
                } else if (isset($responseObj->message)) {
                    $this->errorData->message = $responseObj->message;
                    $this->errorData->code = $responseObj->code;
                }

                throw AzureResponseErrorFactory::make($this->errorData, $code);
            } else if ($code == 204 && $this->request->getRequestMethod() == 'DELETE') {
                throw new NotFoundException('Azure error. The Resource was not found.', 404);
            }
        }

        return $this->errorData instanceof ErrorData;
    }

    /**
     * Gets raw request message
     *
     * @return  string  Returns raw request message
     */
    public function getRawRequestMessage()
    {
        return (string) $this->httpResponse;
    }
}