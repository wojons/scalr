<?php
	class Scalr_Net_Scalarizr_Client
	{
		private $dbServer,
			$port,
			$cryptoTool;
		
		protected $namespace;
		
		public static function getClient($dbServer, $namespace = null, $port = 8010)
		{
			switch ($namespace) {
				case "service":
					return new Scalr_Net_Scalarizr_Services_Service($dbServer, $port);
					break;
				default:
					return new Scalr_Net_Scalarizr_Client($dbServer, $port);
					break;
			}
		}
		
		public function __construct(DBServer $dbServer, $port = 8010) {
			$this->dbServer = $dbServer;
			$this->port = $port;
			
			$this->cryptoTool = Scalr_Messaging_CryptoTool::getInstance();
		}
		
		public function request($method, Object $params = null, $namespace = null)
		{
			if (!$namespace)
				$namespace = $this->namespace;
			
			$requestObj = new stdClass();
			$requestObj->id = microtime(true);
			$requestObj->method = $method;
			$requestObj->params = new stdClass();
			
			$this->walkSerialize($params, $requestObj->params);			
			$jsonRequest = $this->cryptoTool->encrypt(json_encode($requestObj), $this->dbServer->GetKey(true));
			
			$timestamp = date("D d M Y H:i:s T");
			$dt = new DateTime($timestamp, new DateTimeZone("CDT"));
			$timestamp = Scalr_Util_DateTime::convertDateTime($dt, new DateTimeZone("UTC"), new DateTimeZone("CDT"))->format("D d M Y H:i:s");
			$timestamp .= " UTC";
			
			$canonical_string = $jsonRequest . $timestamp;
			$signature = base64_encode(hash_hmac('SHA1', $canonical_string, $this->dbServer->GetKey(true), 1));
			
			$request = new HttpRequest("http://{$this->dbServer->remoteIp}:{$this->port}/{$namespace}", HTTP_METH_POST);
		  	$request->setOptions(array(
		  		'timeout'	=> 5,
		  		'connecttimeout' => 5
		  	));
		  	
		  	$request->setHeaders(array(
				"Date" =>  $timestamp, 
				"X-Signature" => $signature,
		  		"X-Server-Id" => $this->dbServer->serverId
		  	));
			$request->setRawPostData($jsonRequest);
			
			try {
				// Send request
				$request->send();
				
				if ($request->getResponseCode() == 200) {
					
					$response = $request->getResponseData();
					$body = $this->cryptoTool->decrypt($response['body'], $this->dbServer->GetKey(true));
					
					$jResponse = @json_decode($body);
					
					if ($jResponse->error)
						throw new Exception("{$jResponse->error->message} ({$jResponse->error->code}): {$jResponse->error->data}");
						
					return $jResponse;
				} else {
					$response = $request->getResponseData();
					throw new Exception(sprintf("Unable to perform request to update client: %s (%s)", $response['body'], $request->getResponseCode()));	
				}
			} catch(HttpException $e) {
				if (isset($e->innerException))
					$msg = $e->innerException->getMessage();
			    else
					$msg = $e->getMessage();
				
				throw new Exception(sprintf("Unable to perform request to update client: %s", $msg));
			}
		}
		
		private function walkSerialize ($object, &$retval) {
			foreach ($object as $k=>$v) {
				if (is_object($v) || is_array($v)) {
					$this->walkSerialize($v, $retval->{$this->underScope($k)});
				} else {
					$retval->{$this->underScope($k)} = $v;
				}
			}
		}
	
		private function underScope ($name) {
			$parts = preg_split("/[A-Z]/", $name, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$ret = "";
			foreach ($parts as $part) {
				if ($part[1]) {
					$ret .= "_" . strtolower($name{$part[1]-1});
				}
				$ret .= $part[0];
			}
			return $ret;
		}
	}