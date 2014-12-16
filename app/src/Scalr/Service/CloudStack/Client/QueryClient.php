<?php
namespace Scalr\Service\CloudStack\Client;

use Scalr\Service\CloudStack\Exception\RestClientException;
use Scalr\Service\CloudStack\CloudStack;
use \HttpRequest;
use \HttpException;

/**
 * CloudStack Rest Client
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class QueryClient implements ClientInterface
{

    /**
     * Default useragent
     *
     * @var string
     */
    protected $useragent;

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
        $this->useragent = sprintf('Scalr CloudStack Client (http://scalr.com) PHP/%s pecl_http/%s',
            phpversion(), phpversion('http')
        );
    }

    /**
     * Creates new HttpRequest object
     *
     * @return HttpRequest Returns new HttpRequest object
     */
    protected function createHttpRequest()
    {
        $req = new HttpRequest();
        $req->resetCookies();
        $req->setOptions(array(
            'redirect'   => 10,
            'useragent'  => $this->useragent,
            'verifypeer' => false,
            'verifyhost' => false
        ));
        return $req;
    }

    /**
     * Tries to send request on several attempts.
     *
     * @param    HttpRequest     $httpRequest
     * @param    int             $attempts     Attempts count.
     * @param    int             $interval     An sleep interval between an attempts in microseconds.
     * @throws   RestClientException
     * @return   HttpMessage    Returns HttpMessage if success.
     */
    protected function tryCall($httpRequest, $attempts = 3, $interval = 200)
    {
        try {
            $message = $httpRequest->send();
        } catch (HttpException $e) {
            if (--$attempts > 0) {
                usleep($interval);
                $message = $this->tryCall($httpRequest, $attempts, $interval * 2);
            } else {
                throw new RestClientException(sprintf(
                        'Cannot establish connection with CloudStack server. (%s).',
                        (isset($e->innerException) ? preg_replace('/(\(.*\))/', '', $e->innerException->getMessage()) : $e->getMessage())
                    ));
            }
        }
        return $message;
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
        $query = http_build_query($args);
        $query = str_replace("+", "%20", $query);
        if ('GET' == $verb) {
            $query .= "&signature=" . $this->getSignature(strtolower($query));
        }
        else {
            $args['signature'] = $this->getSignature(strtolower($query));
        }
        $httpRequest = new HttpRequest();
        $httpRequest->setMethod(constant('HTTP_METH_'.$verb));
        $url = ('GET' == $verb) ? ($this->endpoint . "?" . $query) : $this->endpoint;

        $httpRequest->setUrl($url);
        if ('POST' == $verb) {
            $httpRequest->setBody($args);
        }
        $message = $this->tryCall($httpRequest, $attempts);

        $response = new QueryClientResponse($message, $command);
        $response->setRawRequestMessage($httpRequest->getRawRequestMessage());
        if ($this->debug) {
            echo "\nURL: " . $httpRequest->getUrl() . "\n";
            echo $httpRequest->getRawRequestMessage() . "\n";
            echo $httpRequest->getRawResponseMessage() . "\n";
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