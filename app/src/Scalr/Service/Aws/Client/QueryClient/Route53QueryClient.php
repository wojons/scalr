<?php
namespace Scalr\Service\Aws\Client\QueryClient;

use finfo;
use http\QueryString;
use Scalr\Service\Aws\Client\QueryClient;
use Scalr\Service\Aws\Event\EventType;

/**
 * Amazon Route53 Query API client.
 *
 * HTTP Query-based requests are defined as any HTTP requests using the HTTP verb GET or POST
 * and a Query parameter named either Action or Operation.
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class Route53QueryClient extends QueryClient
{

    /**
     * Calls Amazon web service method.
     *
     * It ensures execution of the certain AWS action by transporting the request
     * and receiving response.
     *
     * @param     string    $action           REST METHOD (GET|PUT|DELETE|HEAD etc).
     * @param     array     $options          An options array. It's used to send headers for the HTTP request.
     *                                        extra options: _subdomain, _putData, _putFile
     * @param     string    $path    optional A relative path.
     * @return    ClientResponseInterface
     * @throws    ClientException
     */
    public function call($action, $options, $path = '/')
    {
        $httpRequest = $this->createRequest();
        $httpMethod = $action ?: 'GET';
        if (substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }
        $path = '/' . $this->getApiVersion() . (!empty($path) ? $path : '/');

        $this->lastApiCall = null;
        $eventObserver = $this->getAws()->getEventObserver();
        if ($eventObserver && $eventObserver->isSubscribed(EventType::EVENT_SEND_REQUEST)) {
            foreach (debug_backtrace() as $arr) {
                if (empty($arr['class']) ||
                    !preg_match("/\\\\Service\\\\Aws\\\\.+Api$/", $arr['class']) ||
                    $arr['type'] !== '->') {
                    continue;
                }
                $this->lastApiCall = ucfirst($arr['function']);
                break;
            }
        }

        //Wipes out extra options from headers and moves them to separate array.
        $extraOptions = ['region' => null];
        foreach ($options as $key => $val) {
            if (substr($key, 0, 1) === '_') {
                $extraOptions[substr($key, 1)] = $val;
                unset($options[$key]);
            }
        }

        if (!isset($options['Date'])) {
            $options['Date'] = gmdate(DATE_RFC1123);
        }

        if (!isset($options['Host'])) {
            $options['Host'] = (isset($extraOptions['subdomain']) ? $extraOptions['subdomain'] . '.' : '') . $this->url;
        }

        if ($httpMethod === 'POST') {
            $options['Content-Type'] = 'application/xml';

            if (array_key_exists('putData', $extraOptions)) {
                $httpRequest->append($extraOptions['putData']);
            } elseif (array_key_exists('putFile', $extraOptions)) {
                $httpRequest->addFiles([ $extraOptions['putFile'] ]);
            }
        }

        $httpRequest->setRequestUrl('https://' . $options['Host'] . $path);
        $httpRequest->setRequestMethod($httpMethod);
        $httpRequest->addHeaders($options);

        if (true) {
            $this->signRequestV4($httpRequest, (!empty($extraOptions['region']) ? $extraOptions['region'] : null));
        } else {
            $httpRequest->addHeaders([
                'X-Amzn-Authorization' => "AWS3-HTTPS AWSAccessKeyId=" . $this->awsAccessKeyId . ", Algorithm=HmacSHA1,Signature="
                                        . base64_encode(hash_hmac('sha1', $options['Date'], $this->secretAccessKey, 1))
            ]);
        }

        $response = $this->tryCall($httpRequest);

        if ($this->getAws() && $this->getAws()->getDebug()) {
            echo "\n",
                 "{$httpRequest}\n",
                 "{$response->getResponse()}\n";
        }

        return $response;
    }
}