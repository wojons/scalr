<?php
namespace Scalr\Service\Aws\Client;

use http\Client\Request;
use http\Client\Response;
use Scalr\Service\Aws\Exception\AwsResponseErrorFactory;
use Scalr\Service\Aws\Plugin\EventObserver;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\DataType\Loader\ErrorLoader;

/**
 * Query Client Response
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     23.09.2012
 */
class QueryClientResponse implements ClientResponseInterface
{

    /**
     * Http Response instance
     *
     * @var Response
     */
    private $response;

    /**
     * Information about error if it's occured.
     *
     * @var ErrorData|boolean
     */
    private $errorData;

    /**
     * Exception
     *
     * @var ClientException
     */
    private $exception;

    /**
     * Http request
     *
     * @var Request
     */
    private $request;

    /**
     * The number of the query during current user session
     *
     * @var int
     */
    private $queryNumber;

    /**
     * EventObserver
     *
     * @var EventObserver
     */
    private $eventObserver;

    /**
     * Constructor
     *
     * @param   Response $response  HTTP Message object
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * Invokes http Response object methods
     *
     * @param    string    $method
     * @param    array     $args
     * @return   mixed
     */
    public function __call($method, $args)
    {
        if (method_exists($this->response, $method)) {
            return call_user_method_array($method, $this->response, $args);
        }
        throw new \BadMethodCallException(sprintf('Method "%s" does not exist for class "%s".', $method, get_class($this)));
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientResponseInterface::getHeaders()
     */
    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientResponseInterface::getHeader()
     */
    public function getHeader($headername)
    {
        return $this->response->getHeader($headername);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\Client\ClientResponseInterface::getRawContent()
     */
    public function getRawContent()
    {
        return $this->response->getBody()->toString();
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientResponseInterface::getError()
     */
    public function getError()
    {
        if ($this->hasError()) {
            throw $this->exception;
        }

        return false;
    }


    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientResponseInterface::hasError()
     */
    public function hasError()
    {
        if (!isset($this->errorData)) {
            $this->errorData = false;
            $this->exception = null;
            $code = $this->getResponseCode();
            if ($code < 200 || $code > 299) {
                if ($code == 404) {
                    //Workaround for the Amazon S3 response with Delete Marker object
                    if ($this->getHeader('x-amz-delete-marker') !== null &&
                        $this->getHeader('x-amz-delete-marker') != '') {
                        return $this->errorData;
                    }
                }

                $loader = new ErrorLoader();
                $xml = $this->getRawContent();

                if (empty($xml) && $code == 403) {
                    throw new \Exception('AWS Error. Permission denied.');
                }

                $this->errorData = $loader->load($xml);
                $this->errorData->request = $this->getRequest();
                $this->errorData->queryNumber = $this->queryNumber;

                $this->exception = AwsResponseErrorFactory::make($this->errorData);
            }
        }

        return $this->errorData instanceof ErrorData;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientResponseInterface::getException()
     */
    public function getException()
    {
        return $this->hasError() ? $this->exception : null;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientResponseInterface::getResponseCode()
     */
    public function getResponseCode()
    {
        return $this->response->getResponseCode();
    }

    /**
     * @param EventObserver $eventObserver
     * @return \Scalr\Service\Aws\Client\QueryClientResponse
     */
    public function setEventObserver(EventObserver $eventObserver = null)
    {
        $this->eventObserver = $eventObserver;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientResponseInterface::getResponseStatus()
     */
    public function getResponseStatus()
    {
        return $this->response->getResponseStatus();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\Client\ClientResponseInterface::getRequest()
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\Client\ClientResponseInterface::setRequest()
     * @return QueryClientResponse
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientResponseInterface::setQueryNumber()
     * @return QueryClientResponse
     */
    public function setQueryNumber($number)
    {
        $this->queryNumber = $number;
        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
}