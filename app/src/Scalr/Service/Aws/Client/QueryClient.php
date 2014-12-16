<?php
namespace Scalr\Service\Aws\Client;

use HttpRequest;
use Scalr\Service\Aws;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\Event\EventType;
use Scalr\Service\Aws\Event\ErrorResponseEvent;

/**
 * Amazon Query API client.
 *
 * HTTP Query-based requests are defined as any HTTP requests using the HTTP verb GET or POST
 * and a Query parameter named either Action or Operation.
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     21.09.2012
 */

class QueryClient extends AbstractClient implements ClientInterface
{
    /**
     * Base url for API requests
     *
     * @var string
     */
    protected $url;

    /**
     * AWS Access Key Id
     *
     * @var string
     */
    protected $awsAccessKeyId;

    /**
     * Secret Access Key
     *
     * @var string
     */
    protected $secretAccessKey;

    /**
     * AWS API Version
     *
     * @var string
     */
    protected $apiVersion;

    /**
     * Useragent
     *
     * @var string
     */
    protected $useragent;

    /**
     * Constructor
     *
     * @param    string    $awsAccessKeyId    AWS Access Key Id
     * @param    string    $secretAccessKey   AWS Secret Access Key
     * @param    string    $apiVersion        YYYY-MM-DD representation of AWS API version
     * @param    string    $url
     */
    public function __construct($awsAccessKeyId, $secretAccessKey, $apiVersion, $url = null)
    {
        $this->awsAccessKeyId = $awsAccessKeyId;
        $this->secretAccessKey = $secretAccessKey;
        $this->setApiVersion($apiVersion);
        $this->setUrl($url);
        $this->useragent = sprintf('Scalr AWS Client (http://scalr.com) PHP/%s pecl_http/%s',
            phpversion(), phpversion('http')
        );
    }

    /**
     * Sets Api Version
     *
     * @param     string    $apiVersion  YYYY-MM-DD representation of AWS API version
     */
    public function setApiVersion($apiVersion)
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $apiVersion, $m)) {
            $apiVersion = $m[1] . '-' . $m[2] . '-' . $m[3];
        } else if (!preg_match('/^[\d]{4}\-[\d]{2}\-[\d]{2}$/', $apiVersion)) {
            throw new QueryClientException(
                'Invalid API version ' . $apiVersion . '. '
              . 'You should have used following format YYYY-MM-DD.'
            );
        }
        $this->apiVersion = $apiVersion;
    }

    /**
     * Gets API Version date
     *
     * @return string Returns API Version Date in YYYY-MM-DD format
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Sets query url
     *
     * @param    string   $url  Base url for API requests
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Gets base url for API requests
     *
     * @return   string  Returns base url for API requests
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Gets expiration time for Expires option.
     *
     * @return   string   Returns expiration time form Expires option
     *                    that's used in AWS api requests.
     */
    protected function getExpirationTime()
    {
        return gmdate('c', time() + 3600);
    }

    /**
     * Calls Amazon web service method.
     *
     * It ensures execution of the certain AWS action by transporting the request
     * and receiving response.
     *
     * @param     string    $action           An Web service API action name.
     * @param     array     $options          An options array. It may contain "_host" option which overrides host.
     * @param     string    $path    optional A relative path.
     * @return    ClientResponseInterfa
     * @throws    ClientException
     */
    public function call($action, $options, $path = '/')
    {
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }

        $this->lastApiCall = null;

        $options['Action'] = isset($options['Action']) ? $options['Action'] : $action;
        $options['Version'] = isset($options['Version']) ? $options['Version'] : $this->getApiVersion();

        $this->lastApiCall = $options['Action'];

        if (isset($options['_host'])) {
            $host = $options['_host'];
            unset($options['_host']);
        } else {
            $host = $this->url;
        }

        if (isset($options['_region'])) {
            $region = $options['_region'];
            unset($options['_region']);
        } else {
            $region = null;
        }

        if (strpos($host, 'http') === 0) {
            $arr = parse_url($host);
            $scheme = $arr['scheme'];
            $host = $arr['host'] . (isset($arr['port']) ? ':' . $arr['port'] : '');
            $path = (!empty($arr['path']) && $arr['path'] != '/' ? rtrim($arr['path'], '/') : '') . $path;
        } else {
            $scheme = 'https';
        }

        $httpRequest = $this->createRequest();

        $httpRequest->addHeaders([
            'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);

        $httpRequest->setUrl($scheme . '://' . $host . $path);

        $httpRequest->setMethod(HTTP_METH_POST);

        $httpRequest->addPostFields($options);

        //Signature version 4 sign for China region
        if (preg_match('/^(cn|eu\-central)\-/', ($region ?: $this->getAws()->getRegion() ?: ''))) {
            $this->signRequestV4($httpRequest, $region);
        } else {
            $this->signRequestV2($httpRequest);
        }

        $response = $this->tryCall($httpRequest);

        if ($this->getAws() && $this->getAws()->getDebug()) {
            echo "\n";
            echo $httpRequest->getRawRequestMessage() . "\n";
            echo $httpRequest->getRawResponseMessage() . "\n";
        }

        return $response;
    }

    /**
     * Signs request with signature version 4
     *
     * @param   \HttpRequest $request     Http Request
     * @param   string       $region      optional Overrides region as destination region for multiregional operations
     * @throws  QueryClientException
     */
    protected function signRequestV4($request, $region = null)
    {
        $time = time();

        //Gets the http method name
        $httpMethod = self::$httpMethods[$request->getMethod()];

        //Region is mandatory part for this type of authentication
        $region = $region ?: $this->getAws()->getRegion();

        //Gets host from the url
        $components = parse_url($request->getUrl());

        $crd = gmdate('Ymd', $time) . '/' . $region . '/' . $this->getServiceName() . '/aws4_request';

        $opt = [
            'X-Amz-Algorithm'       => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'      => $this->awsAccessKeyId . '/' . $crd,
            'X-Amz-Date'            => gmdate('Ymd\THis\Z', $time),
            'X-Amz-Expires'         => '86400',
        ];

        $headers = ['X-Amz-Date' => $opt['X-Amz-Date']];

        //Calculating canonicalized query string
        $canonicalizedQueryString = '';
        if (!empty($components['query'])) {
            parse_str($components['query'], $pars);

            ksort($pars);

            foreach ($pars as $k => $v) {
                $canonicalizedQueryString .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
            }

            $canonicalizedQueryString = ltrim($canonicalizedQueryString, '&');
        }

        //Calculating payload
        $payload = '';
        if ($httpMethod == 'POST') {
            $pars = $request->getPostFields();

            foreach ($pars as $k => $v) {
                //We do not use rawurlencode because httpRequest does not do this, and we need to hash payload
                $payload .= '&' . urlencode($k) . '=' . urlencode($v);
            }

            $payload = ltrim($payload, '&');
        } elseif ($httpMethod == 'PUT') {
            $payload = $request->getPutData();

            if (empty($payload) && $request->getPutFile()) {
                $headers['X-Amz-Content-Sha256'] = hash_file('sha256', $request->getPutFile());
            }
        }

        if (!isset($headers['X-Amz-Content-Sha256'])) {
            $headers['X-Amz-Content-Sha256'] = hash('sha256', $payload);
        }

        //Adding x-amz headers
        $request->addHeaders($headers);

        //Calculating canonical headers
        $allHeaders = $request->getHeaders();
        $allHeaders['X-Amz-SignedHeaders'] = 'host';
        $signedHeaders = ['host' => $components['host']];

        foreach ($allHeaders as $k => $v) {
            $lk = strtolower($k);

            //This x-amz header does not have to be signed
            if ($lk == 'x-amz-signedheaders') {
                continue;
            }

            if (preg_match('/x-amz-/i', $k) || $lk == 'content-type' || $lk == 'content-md5') {
                $signedHeaders[$lk] = $v;
            }
        }

        ksort($signedHeaders);

        $allHeaders['X-Amz-SignedHeaders'] = join(';', array_keys($signedHeaders));

        $canonicalHeaders = '';
        foreach ($signedHeaders as $k => $v) {
            $canonicalHeaders .= $k . ':' . $v . "\n";
        }

        $canonicalRequest =
            $httpMethod . "\n"
          . $components['path'] . "\n"
          . $canonicalizedQueryString . "\n"
          . $canonicalHeaders . "\n"
          . $allHeaders['X-Amz-SignedHeaders'] . "\n"
          . $headers['X-Amz-Content-Sha256']
        ;

        $stringToSign = $opt['X-Amz-Algorithm'] . "\n"
                      . $opt['X-Amz-Date'] . "\n"
                      . $crd . "\n"
                      . hash('sha256', $canonicalRequest);

        $dateKey = hash_hmac('sha256', gmdate('Ymd', $time), "AWS4" . $this->secretAccessKey, true);
        $dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', $this->getServiceName(), $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

        //X-Amz-Signature
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $headers['Authorization'] = $opt['X-Amz-Algorithm'] . ' '
                    . 'Credential=' . $opt['X-Amz-Credential'] . ','
                    . 'SignedHeaders=' . $allHeaders['X-Amz-SignedHeaders'] . ','
                    . 'Signature=' . $signature;

        $request->addHeaders($headers);
    }

    /**
     * Signs request with signature version 2
     *
     * Only POST http method is supported
     *
     * @param   \HttpRequest $request Http request object
     * @throws  QueryClientException
     */
    protected function signRequestV2($request)
    {
        $time = time();

        //Gets the http method name
        $httpMethod = self::$httpMethods[$request->getMethod()];

        //Gets both host and path from the url
        $components = parse_url($request->getUrl());

        $common = [
            'AWSAccessKeyId'   => $this->awsAccessKeyId,
            'SignatureVersion' => '2',
            'SignatureMethod'  => 'HmacSHA1',
            'Timestamp'        => gmdate('Y-m-d\TH:i:s', $time) . "Z",
        ];

        $request->addPostFields($common);

        //Gets adjusted options
        $options = $request->getPostFields();

        //Calculating canonicalized query string
        ksort($options);

        $canonicalizedQueryString = '';
        foreach ($options as $k => $v) {
            $canonicalizedQueryString .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
        }
        $canonicalizedQueryString = ltrim($canonicalizedQueryString, '&');

        $stringToSign =
            $httpMethod . "\n"
          . strtolower($components['host']) . "\n"
          . $components['path'] . "\n"
          . $canonicalizedQueryString
        ;

        switch ($common['SignatureMethod']) {
            case 'HmacSHA1':
            case 'HmacSHA256':
                $algo = strtolower(substr($common['SignatureMethod'], 4));
                break;
            default:
                throw new QueryClientException(
                    'Unknown SignatureMethod ' . $common['SignatureMethod']
                );
        }

        $request->addPostFields([
            'Signature' => base64_encode(hash_hmac($algo, $stringToSign, $this->secretAccessKey, 1))
        ]);

        $request->addHeaders([
            'X-Amz-Date'    => gmdate(\DateTime::ISO8601, $time),
        ]);
    }

    /**
     * Creates a new HttpRequest object.
     *
     * @return \HttpRequest Returns a new HttpRequest object.
     */
    public function createRequest()
    {
        $q = new HttpRequest();
        //HttpRequest has a pitfall which persists cookies between different requests.
        //IMPORTANT! This line causes error with old version of curl
        //$q->resetCookies();
        $q->setOptions(array(
            'redirect'       => 10,
            'useragent'      => $this->useragent,
            'verifypeer'     => false,
            'verifyhost'     => false,
            'timeout'        => 30,
            'connecttimeout' => 30/*,
            'ssl' => array('version' => 1)*/
        ));

        $proxySettings = $this->getAws()->getProxy();

        if ($proxySettings !== false) {
            $q->setOptions([
                'proxyhost' => $proxySettings['host'],
                'proxyport' => $proxySettings['port'],
                'proxytype' => $proxySettings['type']
            ]);

            if ($proxySettings['user']) {
                $q->setOptions([
                    'proxyauth'     => "{$proxySettings['user']}:{$proxySettings['pass']}",
                    'proxyauthtype' => HTTP_AUTH_BASIC
                ]);
            }
        }

        return $q;
    }

    /**
     * Tries to send request on several attempts.
     *
     * @param    \HttpRequest    $httpRequest
     * @param    int             $attempts     Attempts count.
     * @param    int             $interval     An sleep interval between an attempts in microseconds.
     * @returns  QueryClientResponse  Returns response on success
     * @throws   QueryClientException
     */
    protected function tryCall($httpRequest, $attempts = 1, $interval = 200)
    {
        try {
            $message = $httpRequest->send();

            if (preg_match('/^<html.+ Service Unavailable/', $message->getBody()) && --$attempts > 0) {
                usleep($interval);
                return $this->tryCall($httpRequest, $attempts, $interval * 2);
            }

            //Increments the queries quantity
            $this->_incrementQueriesQuantity();

            $response = new QueryClientResponse($message);
            $response->setRequest($httpRequest);
            $response->setQueryNumber($this->getQueriesQuantity());

            if ($response->hasError()) {
                $eventObserver = $this->getAws()->getEventObserver();
                /* @var $clientException ClientException */
                $clientException = $response->getException();
                //It does not need anymore
                //$response->setEventObserver($eventObserver);
                if (isset($eventObserver) && $eventObserver->isSubscribed(EventType::EVENT_ERROR_RESPONSE)) {
                    $eventObserver->fireEvent(new ErrorResponseEvent(array(
                        'exception' => $clientException,
                        'apicall'   => $clientException->getApiCall(),
                    )));
                }
                if ($clientException->getErrorData() instanceof ErrorData &&
                    $clientException->getErrorData()->getCode() == ErrorData::ERR_REQUEST_LIMIT_EXCEEDED) {
                    if (--$attempts > 0) {
                        //Tries to handle RequestLimitExceeded AWS Response
                        sleep(3);
                        return $this->tryCall($httpRequest, $attempts, $interval);
                    }
                }
            }
        } catch (\HttpException $e) {
            if (--$attempts > 0) {
                usleep($interval);
                return $this->tryCall($httpRequest, $attempts, $interval * 2);
            } else {
                $error = new ErrorData();
                $error->message = 'Cannot establish connection to AWS server. ' . (isset($e->innerException) ? preg_replace('/(\(.*\))/', '', $e->innerException->getMessage()) : $e->getMessage());
                throw new ClientException($error);
            }
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Client.ClientInterface::getType()
     */
    public function getType()
    {
        return Aws::CLIENT_QUERY;
    }
}