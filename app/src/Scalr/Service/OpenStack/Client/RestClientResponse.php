<?php
namespace Scalr\Service\OpenStack\Client;

use http\Client\Response;
use Scalr\Service\OpenStack\Exception\OpenStackResponseErrorFactory;
use Scalr\Service\OpenStack\Type\AppFormat;

/**
 * OpenStack Rest Client Response
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    06.12.2012
 */
class RestClientResponse implements ClientResponseInterface
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
     * @var AppFormat
     */
    private $format;

    /**
     * Raw request message
     * @var string
     */
    private $rawRequestMessage;

    /**
     * Constructor
     *
     * @param   Response     $response  An HTTP response
     * @param   AppFormat    $format    An response body application format
     */
    public function __construct(Response $response, AppFormat $format)
    {
        $this->response = $response;
        $this->format = $format;
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
     * {@inheritdoc}
     * @see \Scalr\Service\OpenStack\Client\ClientResponseInterface::getContent()
     */
    public function getContent()
    {
        return $this->response->getBody()->toString();
    }


    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Client.ClientResponseInterface::getResponseCode()
     */
    public function getResponseCode()
    {
        return $this->response->getResponseCode();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Client.ClientResponseInterface::getHeader()
     */
    public function getHeader($headerName)
    {
        return $this->response->getHeader($headerName);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Client.ClientResponseInterface::getHeaders()
     */
    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Client.ClientResponseInterface::hasError()
     */
    public function hasError()
    {
        if (!isset($this->errorData)) {
            $this->errorData = false;
            $code = $this->response->getResponseCode();
            if ($code < 200 || $code > 299) {
                $this->errorData = new ErrorData();
                if ($this->format == AppFormat::APP_JSON) {
                    $d = @json_decode($this->getContent());
                    if ($d === null) {
                        $this->errorData->code = $code;
                        $this->errorData->message = strip_tags($this->getContent());
                        $this->errorData->details = '';
                    } else {
                        list(, $v) = each($d);
                        if (is_object($v)) {
                            $this->errorData->code = isset($v->code) ? $v->code : 0;
                            $this->errorData->message = $v->message;
                            $this->errorData->details = isset($v->details) ? (string)$v->details : '';
                        } else {
                            //QuantumError
                            $this->errorData->code = $code;
                            $this->errorData->message = (string)$v;
                            $this->errorData->details = '';
                        }
                    }
                } else if ($this->format == AppFormat::APP_XML) {
                    $d = simplexml_load_string($this->getContent());
                    $this->errorData->code = $code;
                    $this->errorData->message = isset($d->message) ? (string)$d->message : '';
                    $this->errorData->details = isset($d->details) ? (string)$d->details : '';
                } else {
                    throw new \InvalidArgumentException(sprintf(
                        'Unexpected application format "%s" in class %s', (string)$this->format, get_class($this)
                    ));
                }

                throw OpenStackResponseErrorFactory::make($this->errorData);
            }
        }
        return $this->errorData;
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
     * @return  RestClientResponse
     */
    public function setRawRequestMessage($rawRequestMessage)
    {
        $this->rawRequestMessage = $rawRequestMessage;
        return $this;
    }
}