<?php

namespace Scalr\Service\CloudStack\Client;

use http\Client\Response;
use Scalr\Service\CloudStack\Exception\RestClientException;
use Scalr\Service\CloudStack\CloudStack;
use Scalr\System\Http\Client\Request;
use Scalr\Util\CallbackInterface;
use Scalr\Util\CallbackTrait;

/**
 * CloudStack Rest Client
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class QueryClient implements ClientInterface, CallbackInterface
{

    use CallbackTrait;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * Secret Access Key
     *
     * @var string
     */
    protected $secretKey;

    /**
     *
     * @var string
     */
    protected $endpoint;

    /**
     *
     * @var string
     */
    protected $platformName;

    /**
     *
     * @var array
     */
    protected $zonesCache;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var CloudStack
     */
    private $cloudstack;

    /**
     * Constructor
     *
     */
    public function __construct($endpoint, $apiKey, $secretKey, $cloudstack, $platform = 'cloudstack')
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->endpoint = $endpoint;
        $this->cloudstack = $cloudstack;
        $this->platformName = $platform;
    }

    /**
     * Creates new http Request object
     *
     * @return Request Returns new http Request object
     */
    protected function createHttpRequest()
    {
        $req = new Request();
        $req->setOptions(array(
            'redirect'   => 10,
            'verifypeer' => false,
            'verifyhost' => false,
            'cookiesession' => true
        ));
        return $req;
    }

    /**
     * Tries to send request on several attempts.
     *
     * @param    Request         $httpRequest
     * @param    int             $attempts     Attempts count.
     * @param    int             $interval     An sleep interval between an attempts in microseconds.
     * @throws   RestClientException
     * @return   Response    Returns http Response if success.
     */
    protected function tryCall($httpRequest, $attempts = 3, $interval = 200)
    {
        try {
            $response = \Scalr::getContainer()->http->sendRequest($httpRequest);

            if (is_callable($this->callback)) {
                call_user_func($this->callback, $httpRequest, $response);
            }
        } catch (\http\Exception $e) {
            if (--$attempts > 0) {
                usleep($interval);
                $response = $this->tryCall($httpRequest, $attempts, $interval * 2);
            } else {
                throw new RestClientException(sprintf(
                        'Cannot establish connection with CloudStack server. (%s).',
                        (isset($e->innerException) ? preg_replace('/(\(.*\))/', '', $e->innerException->getMessage()) : $e->getMessage())
                    ));
            }
        }
        return $response;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Client.ClientInterface::call()
     */
    public function call($command, array $args = null, $verb = 'GET')
    {
        $attempts = 3;

        if ($args === null) {
            $args = array();
        }

        foreach ($args as $key => $value) {
            if (is_null($value)) {
                unset($args[$key]);
            }

            // Workaround for zones.
            if ($key == 'zoneid' && !is_null($value)) {
                if (empty($this->zonesCache)) {
                    foreach ($this->cloudstack->zone->describe() as $zone) {
                        $this->zonesCache[$zone->name] = $zone->id;
                    }
                }

                if (!empty($this->zonesCache[$value])) {
                    $args[$key] = $this->zonesCache[$value];
                }
                else {
                    throw new RestClientException("Availability zone '{$value}' no longer supported");
                }
            }
        }

        $args['apikey'] = $this->apiKey;
        $args['command'] = $command;
        $args['response'] = 'json';
        ksort($args);
        $query = http_build_query($args, null, '&', PHP_QUERY_RFC3986);

        if ('GET' == $verb) {
            $query .= "&signature=" . $this->getSignature(strtolower($query));
        }
        else {
            $args['signature'] = $this->getSignature(strtolower($query));
        }
        $httpRequest = new Request();
        $httpRequest->setRequestMethod($verb);
        $url = ('GET' == $verb) ? ($this->endpoint . "?" . $query) : $this->endpoint;

        $httpRequest->setRequestUrl($url);
        if ('POST' == $verb) {
            $httpRequest->append($args);
        }
        $message = $this->tryCall($httpRequest, $attempts);

        $response = new QueryClientResponse($message, $command);
        $response->setRawRequestMessage($httpRequest->toString());
        if ($this->debug) {
            echo "\nURL: " . $httpRequest->getRequestUrl() . "\n",
                 "{$httpRequest}\n",
                 "{$response->getResponse()}\n";
        }

        return $response;
    }

    public function getSignature($queryString)
    {
        $hash = @hash_hmac("SHA1", $queryString, $this->secretKey, true);
        $base64encoded = base64_encode($hash);
        return urlencode($base64encoded);
    }

    /**
     * Sets debug mode
     *
     * @param   bool $bDebug  optional If true it will output debug per request into stdout
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