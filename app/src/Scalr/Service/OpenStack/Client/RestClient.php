<?php

namespace Scalr\Service\OpenStack\Client;

use http\Client\Response;
use Scalr\Service\OpenStack\Client\Auth\LoaderFactory;
use Scalr\Service\OpenStack\Services\ServiceInterface;
use Scalr\Service\OpenStack\Exception\RestClientException;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Service\OpenStack\Type\AppFormat;
use Scalr\System\Http\Client\Request;
use Scalr\Util\CallbackInterface;
use Scalr\Util\CallbackTrait;

/**
 * OpenStack Rest Client
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    06.12.2012
 */
class RestClient implements ClientInterface, CallbackInterface
{

    use CallbackTrait;

    /**
     * OpenStack config
     *
     * @var OpenStackConfig
     */
    protected $config;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * Constructor
     *
     * @param   OpenStackConfig $config  A OpenStack config.
     */
    public function __construct(OpenStackConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Gets OpenStack config
     *
     * @return  OpenStackConfig A OpenStack config.
     */
    public function getConfig()
    {
        return $this->config;
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
            'redirect'       => 10,
            'verifypeer'     => false,
            'verifyhost'     => false,
            'timeout'        => \Scalr::config('scalr.openstack.api_client.timeout'),
            'connecttimeout' => 30,
            'cookiesession' => true
        ));

        $proxySettings = $this->getConfig()->getProxySettings();

        if (!empty($proxySettings)) {
            $req->setOptions([
                'proxyhost' => $proxySettings['host'],
                'proxyport' => $proxySettings['port'],
                'proxytype' => $proxySettings['type']
            ]);

            if ($proxySettings['user']) {
                $req->setOptions([
                    'proxyauth'     => "{$proxySettings['user']}:{$proxySettings['pass']}",
                    'proxyauthtype' => $proxySettings['authtype']
                ]);
            }
        }

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
    protected function tryCall($httpRequest, $attempts = 1, $interval = 200)
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
                    'Cannot establish connection with OpenStack server (%s). (%s).',
                    $httpRequest->getRequestUrl(),
                    (isset($e->innerException) ? preg_replace('/(\(.*\))/', '', $e->innerException->getMessage()) : $e->getMessage())
                ));
            }
        }
        return $response;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Client.ClientInterface::call()
     */
    public function call($service, $path = '/', array $options = null, $verb = 'GET',
                         AppFormat $accept = null, $auth = true)
    {
        $attempts = 1;

        if ($accept === null) {
            $accept = AppFormat::json();
        }
        if (substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }
        if ($options === null) {
            $options = array();
        }
        if (isset($options['content-type'])) {
            $ctype = (string) $options['content-type'];
            unset($options['content-type']);
        }

        $req = $this->createHttpRequest();

        if (isset($options['__speedup'])) {
            $curOptions = $req->getOptions();
            $curOptions['timeout'] = 3;
            $curOptions['connecttimeout'] = 3;
            $req->setOptions($curOptions);
            unset($options['__speedup']);
        }

        if (isset($options['_headers'])) {
            $xHeaders = (array) $options['_headers'];
            unset($options['_headers']);
        }

        $customOptions = array();
        foreach ($options as $key => $value) {
            if (substr($key, 0, 1) === '_') {
                $customOptions[substr($key, 1)] = $value;
                unset($options[$key]);
            }
        }

        $req->setRequestMethod($verb);
        $ctype = 'json';
        $headers = array(
            'Accept'       => 'application/' . (string)$accept,
            'Content-Type' => 'application/' . (isset($ctype) ? $ctype : 'json') . '; charset=UTF-8',
        );

        if (isset($xHeaders)) {
            foreach ($xHeaders as $k => $v) {
                if (is_string($k)) {
                    $headers[$k] = $v;
                }
            }
        }

        if ($auth) {
            $token = $this->getConfig()->getAuthToken();
            if (!($token instanceof AuthToken) || $token->isExpired()) {
                $this->getConfig()->setAuthToken(null);
                //We need to sign on at first.
                $bAuthRequestSent = true;
                $authResult = $this->auth();
                if (($token = $this->getConfig()->getAuthToken()) === null) {
                    throw new RestClientException('Authentication to OpenStack server failed.');
                }
            }
            $headers['X-Auth-Token'] = $token->getId();
        }

        $endpoint = $service instanceof ServiceInterface ? $service->getEndpointUrl() : $service;
        if (substr($endpoint, -1) === '/') {
            //removes trailing slashes
            $endpoint = rtrim($endpoint, '/');
        }

        $req->addHeaders($headers);
        $req->setRequestUrl($endpoint . $path);

        if ($verb == 'POST') {
            $req->append(json_encode($options));
        } elseif ($verb == 'PUT') {
            if (isset($customOptions['putData'])) {
                $req->append($customOptions['putData']);
            } else if (isset($customOptions['putFile'])) {
                $req->addFiles([ $customOptions['putFile'] ]);
            }
        } else {
            $req->addQuery($options);
        }

        $message = $this->tryCall($req, $attempts);

        $response = new RestClientResponse($message, $accept);
        $response->setRawRequestMessage($req->toString());
        if ($this->debug) {
            echo "\nURL: " . $req->getRequestUrl() . "\n",
                 "{$req}\n",
                 "{$message}\n";
        }

        if ($response->getResponseCode() === 401 && !isset($bAuthRequestSent) && $this->getConfig()->getAuthToken() !== null) {
            //When this token isn't expired and by some reason it causes unauthorized response
            //we should reset authurization token and force authentication request
            $this->getConfig()->setAuthToken(null);
            $response = call_user_func_array(array($this, __METHOD__), func_get_args());
        }

        return $response;
    }

    /**
     * Performs an authentication request
     *
     * @return  object Returns result
     */
    public function auth()
    {
        $cfg = $this->getConfig();

        $options = $cfg->getAuthQueryString();

        $response = $this->call(
            $cfg->getIdentityEndpoint(),
            $cfg->getIdentityVersion() == 3 ? '/auth/tokens' : '/tokens', $options, 'POST', null, false
        );

        if ($response->hasError() === false) {
            $str = $response->getContent();
            $result = json_decode($str);

            $cfg->setAuthToken(LoaderFactory::makeToken($response, $cfg));

            //Trying to fetch stage keystone
            $regionEndpoints = $cfg->getAuthToken()->getRegionEndpoints();

            /*
            if (isset($regionEndpoints['identity'][$cfg->getRegion()][''][0]->publicURL)) {
                $regionKeystoneURL = $regionEndpoints['identity'][$cfg->getRegion()][''][0]->publicURL;

                if ($cfg->getIdentityEndpoint() != $regionKeystoneURL) {
                    $response = $this->call($regionKeystoneURL, '/tokens', $options, 'POST', null, false);

                    if ($response->hasError() === false) {
                        $str = $response->getContent();
                        $result = json_decode($str);

                        //Sets original auth token for current region
                        $cfg->setAuthToken(AuthToken::loadJson($str));
                        $cfg->setIdentityEndpoint($regionKeystoneURL);
                    }
                }
            }
            */
        }

        return $result;
    }

    /**
     * Sets debug mode
     *
     * @param   bool $bDebug  ptional If true it will output debug per request into stdout
     * @return  RestClient
     */
    public function setDebug($bDebug = true)
    {
        $this->debug = (bool)$bDebug;
        return $this;
    }

    /**
     * Resets debug mode
     *
     * @return  RestClient
     */
    public function resetDebug()
    {
        $this->debug = false;
        return $this;
    }
}