<?php

use http\Client\Request;
use Scalr\Service\Cloud\Rackspace\Exception\ClientException;
use Scalr\Service\Cloud\Rackspace\Exception\RackspaceResponseErrorFactory;
use Scalr\Util\CallbackInterface;
use Scalr\Util\CallbackTrait;

class Scalr_Service_Cloud_Rackspace_Connection implements CallbackInterface
{

    use CallbackTrait;

        protected	$xAuthUser;
        protected	$xAuthKey;
        public		$LastResponseHeaders 	= array();
        public      $lastRequestBody         = "";
        public      $LastResponseBody       = "";
        private		$xSessionUrl			= null;		// session id which returned as X-Server-Management-Url  from header
        public		$httpRequest			= null;
        private		$xAuthToken				= null;

        const		ACCEPT_JSON				= "application/json";
        const		CONTENT_TYPE_JSON		= "application/json";
        const		API_VERSION				= "v1.0";
        const		URL						= "auth.api.rackspacecloud.com";

        protected 	$apiAuthURL = '';

        protected function __construct($xAuthUser, $xAuthKey, $cloudLocation)
        {
            $this->xAuthUser = $xAuthUser;
            $this->xAuthKey  = $xAuthKey;
            $this->httpRequest = new Request();

            switch($cloudLocation)
            {
                case 'rs-ORD1':
                    $this->apiAuthURL = 'auth.api.rackspacecloud.com';
                    break;

                case 'rs-LONx':
                    $this->apiAuthURL = 'lon.auth.api.rackspacecloud.com';
                    break;
            }

        }

        /**
        * Authorizes if the user didn't do it before
        * before first API call you have to authorizes yourself and
        * recieve your unique X-Server-Management-Url which will be added
        * to the end of your next URLs
        * @name auth
        * @param mixed $url
        * @return  void
        */
        private function auth()
        {
            try
            {
                $this->setRequestOptions("https://{$this->apiAuthURL}/" . self::API_VERSION, "GET");
                $this->sendRequest();
                $this->xAuthToken	= $this->LastResponseHeaders['X-Auth-Token'];
                $this->xSessionUrl	= $this->LastResponseHeaders['X-Server-Management-Url'];
            }
            catch(Exception $e)
            {
                throw $e;
            }
        }

      public function authToReturn()
      {
          try
          {
              $this->setRequestOptions("https://{$this->apiAuthURL}/" . self::API_VERSION, "GET");
              $this->sendRequest();
              $this->xAuthToken	= $this->LastResponseHeaders['X-Auth-Token'];
              $this->xSessionUrl = $this->LastResponseHeaders['X-Server-Management-Url'];
              return $this->LastResponseHeaders;
          }
          catch(Exception $e)
          {
              throw $e;
          }
      }


        /**
        * Makes request itself to the set or default url
        *
        * @name  sendRequest
        * @param mixed $url
        * @return array  $data
        */
        private function sendRequest()
        {
           try
           {
                $response = \Scalr::getContainer()->http->sendRequest($this->httpRequest);

                $data = $response->getBody()->toString();
                $this->LastResponseHeaders = $response->getHeaders();
                $this->LastResponseBody = $data;

                if($response->getResponseCode() >= 400)
                {
                    $errMsg = json_decode($data);

                    if (is_object($errMsg)) {
                        $errMsg = @array_values(@get_object_vars($errMsg));
                        $errMsg = $errMsg[0];
                    }

                    $code = ($errMsg->code) ? $errMsg->code : 0;
                    $msg = ($errMsg->details) ? $errMsg->details : trim($data);


                    throw new Exception(sprintf('Request to Rackspace failed (Code: %s): %s',
                        $code,
                        $msg
                    ));
                }
            }
            catch (Exception $e)
            {
                if ($e->innerException)
                    $message = $e->innerException->getMessage();
                else
                    $message = $e->getMessage();

                throw new Exception($message);
            }

            return $data;
        }


       /**
        * Makes a request to the cloud server
        *
        * @param mixed $method
        * @param mixed $uri
        * @param mixed $args
        * @param mixed $url
        * @param int   $k
        *
        * @return mixed
        * @throws ClientException
        */
        protected function request($method, $uri = "", $args = null, $url = null, $k = 1)
        {
            try
            {
                if(!$this->xSessionUrl)
                {
                    // authorization request
                    $this->auth();
                }

                if(!$url)
                    $url = $this->xSessionUrl."/{$uri}";

                $this->setRequestOptions($url, $method, $args);
                $response = $this->sendRequest();

                if (is_callable($this->callback)) {
                    call_user_func($this->callback, $this->httpRequest, $response);
                }
            }
            catch (Exception $e)
            {
                if($e->getCode() == 401)
                    $this->xSessionUrl = null;

                //if ($k < 3 && stristr($e->getMessage(), 'Timeout was reached; Operation timed out after'))
                //    return $this->request($method, $uri, $args, $url, $k++);

                $info = "Method: " . $this->httpRequest->getRequestMethod();
                $info .= " ({$this->httpRequest})";

                throw RackspaceResponseErrorFactory::make("[Attempt {$k}] ".$e->getMessage() . " [{$info}]");
            }

            return json_decode($response);

        }


        /**
         * Set request headers and options
         *
         * @param mixed $url
         * @param mixed $method
         * @param mixed $args
         * @return Request
         */
        private function setRequestOptions($url, $method, $args = null)
        {

            $this->httpRequest->setRequestUrl($url);

            $this->httpRequest->setOptions(array(
                "redirect" 		=> 2,
                'timeout'		=> 20,
                'connecttimeout'=> 5
            ));

            $this->httpRequest->setRequestMethod($method);
            $this->httpRequest->setHeaders(array("X-Auth-User" => $this->xAuthUser,
                "X-Auth-Key"	=> $this->xAuthKey,
                "Accept"		=> self::ACCEPT_JSON,
                "Content-Type"	=> self::CONTENT_TYPE_JSON,
                "X-Auth-Token"	=> $this->xAuthToken
            ));

            $this->lastRequestBody = json_encode($args);

            $c11dQueryString = "";

             $time = time();
             switch($method)
             {
                 case "GET":
                        $args['t'] = $time;

                        ksort($args);
                        $c11dQueryString = http_build_query($args);
                        $this->httpRequest->setQuery($c11dQueryString);
                        break;

                 case "POST":
                 case "PUT":

                        $this->httpRequest->setQuery("");

                        if($args)
                            $this->httpRequest->append(json_encode($args));
                        break;
             }

             // unique time to disable caching
             //if($method !== "GET")
             //   $this->httpRequest->setQueryData("&t={$time}");

        }
  }
