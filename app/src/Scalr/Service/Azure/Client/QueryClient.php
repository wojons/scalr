<?php

namespace Scalr\Service\Azure\Client;

use http\Client\Response;
use http\Message\Body;
use Exception;
use http\QueryString;
use InvalidArgumentException;
use Scalr\Service\Azure;
use Scalr\Service\Azure\Exception\RestClientException;
use Scalr\Service\AzureException;
use Scalr\Util\CallbackInterface;
use Scalr\Util\CallbackTrait;
use Scalr\System\Http\Client\Request;

class QueryClient implements ClientInterface, CallbackInterface
{

    use CallbackTrait;

    /**
     * Instance of Azure.
     *
     * @var \Scalr\Service\Azure
     */
    private $azure;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * Array of http methods
     *
     * @var array
     */
    protected static $httpMethods = [
        'DELETE',
        'GET',
        'PATCH',
        'POST',
        'PUT'
    ];

    /**
     * Constructor
     *
     * @param Azure $azure Azure client
     */
    public function __construct(Azure $azure)
    {
        $this->azure = $azure;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientInterface::prepareRequest()
     */
    public function prepareRequest($path, $method, $apiVersion, $baseUrl = Azure::URL_MANAGEMENT_WINDOWS, $queryData = [], $postFields = [], $headers = [])
    {
        if (!in_array($method, static::$httpMethods)) {
            throw new InvalidArgumentException(sprintf('Http method %s not supported or not exists.', $method));
        }

        try {
            $url = $baseUrl . $path;

            $parts = parse_url($url);

            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);
            }

            if (!isset($query['api-version'])) {
                $queryData['api-version'] = $apiVersion;
            }

            $request = new Request($method, $url);

            $proxySettings = $this->azure->getProxy();

            if ($proxySettings !== false) {
                $request->setOptions([
                    'proxyhost' => $proxySettings['host'],
                    'proxyport' => $proxySettings['port'],
                    'proxytype' => $proxySettings['type']
                ]);

                if ($proxySettings['user']) {
                    $request->setOptions([
                        'proxyauth'     => "{$proxySettings['user']}:{$proxySettings['pass']}",
                        'proxyauthtype' => $proxySettings['authtype']
                    ]);
                }
            }

            $request->addQuery($queryData);

            if ($baseUrl === Azure::URL_MANAGEMENT_WINDOWS) {
                $headers['Content-Type'] = 'application/json';
            }

            if (empty($headers['Authorization']) && $baseUrl === Azure::URL_MANAGEMENT_WINDOWS) {
                $headers['Authorization'] = 'Bearer ' . $this->azure->getClientToken(Azure::URL_MANAGEMENT_WINDOWS)->token;
            }

            if (count($postFields)) {
                $request->append(json_encode($postFields));
            } else if ($method == 'POST' && !isset($headers['Content-Length'])) {
                // pecl_http does not include Content-Length for empty posts what breaks integration with Azure.
                $headers['Content-Length'] = 1;
                $request->append(" ");
            } else if (in_array($method, ['PUT', 'PATCH']) && !isset($headers['Content-Length'])) {
                // pecl_http does not include Content-Length for empty posts what breaks integration with Azure.
                $headers['Content-Length'] = 0;
            }

            if (count($headers)) {
                $request->addHeaders($headers);
            }
        } catch (Exception $e) {
            throw new AzureException($e->getMessage());
        }

        return $request;
    }

    /**
     * Tries to send request on several attempts.
     *
     * @param Request $httpRequest
     * @throws RestClientException
     * @return Response Returns http Response if success.
     */
    public function tryCall($httpRequest)
    {
        try {
            $response = \Scalr::getContainer()->http->sendRequest($httpRequest);

            if (is_callable($this->callback)) {
                call_user_func($this->callback, $httpRequest, $response);
            }
        } catch (\http\Exception $e) {
            $response = 'Cannot establish connection to Azure server. '.(isset($e->innerException) ? $e->innerException->getMessage() : $e->getMessage());
            throw new RestClientException($response);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Azure\Client\ClientInterface::call()
     */
    public function call($httpRequest)
    {
        $httpResponse = $this->tryCall($httpRequest);

        $response = new QueryClientResponse($httpResponse, $httpRequest);

        if ($this->debug) {
            echo "\nURL: {$httpRequest->getRequestUrl()}\n",
                 "{$httpRequest}\n",
                 "{$httpResponse}\n";
        }

        return $response;
    }

    /**
     * Waits for finishing current request and returns response
     *
     * @param QueryClientResponse $response   Http response
     * @param string              $baseUrl    Current base url of endpoint
     * @param string              $apiVersion Current api version of endpoint
     *
     * @return QueryClientResponse
     * @throws AzureException
     * @throws RestClientException
     */
    public function waitFinishingProcess(QueryClientResponse $response, $baseUrl, $apiVersion)
    {
        while ($response->getResponseCode() == 202) {
            $headers = $response->getHeaders();

            $retryAfter = isset($headers['Retry-After']) ? $headers['Retry-After'] : 2;

            usleep($retryAfter * 100000);

            $url = $headers['Location'];

            $path = str_replace($baseUrl, '', $url);

            $request = $this->prepareRequest($path, 'GET', $apiVersion);

            $response = $this->call($request);
        }

        return $response;
    }

    /**
     * Sets debug mode
     *
     * @param   bool $bDebug optional If true it will output debug per request into stdout
     *
     * @return  QueryClient
     */
    public function setDebug($bDebug = true)
    {
        $this->debug = (bool)$bDebug;

        return $this;
    }

    /**
     * Resets debug mode
     *
     * @return  QueryClient
     */
    public function resetDebug()
    {
        $this->debug = false;

        return $this;
    }
}
