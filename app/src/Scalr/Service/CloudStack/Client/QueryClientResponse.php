<?php

namespace Scalr\Service\CloudStack\Client;

use http\Client\Response;
use Scalr\Service\CloudStack\DataType\ErrorData;
use Scalr\Service\CloudStack\Exception\CloudStackResponseErrorFactory;

/**
 * CloudStack Query Client Response
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class QueryClientResponse implements ClientResponseInterface
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var ErrorData|bool
     */
    private $errorData;

    /**
     * @var string
     */
    private $command;

    /**
     * Raw request message
     * @var string
     */
    private $rawRequestMessage;

    /**
     * Constructor
     *
     * @param   Response  $response  An HTTP response
     * @param   string    $command   Command name
     */
    public function __construct(Response $response, $command)
    {
        $this->response = $response;
        $this->command = strtolower($command);
    }

    /**
     * Gets an HTTP Response
     *
     * @return Response Returns an http Response object
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Gets a cloudstack command name
     *
     * @return string Returns an http Response object
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Client.ClientResponseInterface::getContent()
     */
    public function getContent()
    {
        return $this->response->getBody()->toString();
    }


    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Client.ClientResponseInterface::getResponseCode()
     */
    public function getResponseCode()
    {
        return $this->response->getResponseCode();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Client.ClientResponseInterface::getHeader()
     */
    public function getHeader($headerName)
    {
        return $this->response->getHeader($headerName);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Client.ClientResponseInterface::getHeaders()
     */
    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    public function getResult()
    {
        $result = null;
        $propertyResponse = "{$this->command}response";
        $message = json_decode($this->getContent());

        if (!is_null($message)) {
            if (!property_exists($message, $propertyResponse)) {
                if (property_exists($message, "errorresponse") && property_exists($message->errorresponse, "errortext")) {
                    $result = $message->errorresponse;
                }
            }
            else {
                $result = $message->{$propertyResponse};
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Client.ClientResponseInterface::hasError()
     */
    public function hasError()
    {
        if (!isset($this->errorData)) {
            $this->errorData = false;
            $code = $this->getResponseCode();
            if ($code < 200 || $code > 299) {
                $this->errorData = new ErrorData();
                $propertyResponse = "{$this->command}response";
                $message = json_decode($this->getContent());
                if (is_object($message) && !property_exists($message, $propertyResponse)) {
                    if (property_exists($message, "errorresponse") && property_exists($message->errorresponse, "errortext")) {
                        $errorBody = $message->errorresponse;
                    }
                }
                else if (!is_null($message)) {
                    $errorBody = $message->{$propertyResponse};
                }

                if (empty($errorBody)) {
                    $this->errorData->code = $code;
                    $this->errorData->message = 'Can not decode json response data: ' . strip_tags($this->getContent());
                } else {
                    $this->errorData->code = $errorBody->errorcode;
                    $this->errorData->message = $errorBody->errortext;
                }

                throw CloudStackResponseErrorFactory::make($this->errorData);
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
        return $this->rawRequestMessage;
    }

    /**
     * Sets raw request message
     *
     * @param   string   $rawRequestMessage  Raw request message
     * @return  QueryClientResponse
     */
    public function setRawRequestMessage($rawRequestMessage)
    {
        $this->rawRequestMessage = $rawRequestMessage;
        return $this;
    }
}