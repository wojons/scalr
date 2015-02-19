<?php

use Scalr\Util\CryptoTool;

class Scalr_Net_Scalarizr_UpdateClient
    {
        private $dbServer,
            $port,
            $timeout,
            $cryptoTool,
            $isVPC = false;


        public function __construct(DBServer $dbServer, $port = 8008, $timeout = 5) {
            $this->dbServer = $dbServer;
            $this->port = $port;
            $this->timeout = $timeout;

            if ($this->dbServer->farmId)
                if (DBFarm::LoadByID($this->dbServer->farmId)->GetSetting(DBFarm::SETTING_EC2_VPC_ID))
                    $this->isVPC = true;

            $this->cryptoTool = \Scalr::getContainer()->srzcrypto($this->dbServer->GetKey(true));
        }

        public function configure($repo, $schedule)
        {
            $params = new stdClass();
            $params->schedule = $schedule;
            $params->repository = $repo;

            return $this->request("configure", $params)->result;
        }

        public function getStatus($cached = false)
        {
            $r = new stdClass();
            if ($this->dbServer->IsSupported('2.7.7'))
                $r->cached = $cached;

            return $this->request("status", $r)->result;
        }

        public function updateScalarizr($force = false)
        {
            $r = new stdClass();
            $r->force = $force;
            return $this->request("update", $r);
        }

        public function restartScalarizr($force = false)
        {
            $r = new stdClass();
            $r->force = $force;
            return $this->request("restart", $r);
        }

        public function executeCmd($cmd) {
            $r = new stdClass();
            $r->command = $cmd;
            return $this->request("execute", $r);
        }

        public function putFile($path, $contents)
        {
            $r = new stdClass();
            $r->name = $path;
            $r->content = base64_encode($contents);
            $r->makedirs = true;
            return $this->request("put_file", $r);
        }

        private function request($method, $params = null)
        {
            $requestObj = new stdClass();
            $requestObj->id = microtime(true);
            $requestObj->method = $method;
            $requestObj->params = $params;

            $jsonRequest = json_encode($requestObj);
            $newEncryptionProtocol = false;
            //TODO:
            if ($this->dbServer->farmRoleId) {
                if ($this->dbServer->IsSupported('2.7.7'))
                    $newEncryptionProtocol = true;
            }

            $dt = new DateTime('now', new DateTimeZone("UTC"));
            $timestamp = $dt->format("D d M Y H:i:s e");

            if ($newEncryptionProtocol) {
                $jsonRequest = $this->cryptoTool->encrypt($jsonRequest, $this->dbServer->GetKey(true));

                $signature = $this->cryptoTool->sign(
                    $jsonRequest,
                    $this->dbServer->GetKey(true),
                    $timestamp,
                    Scalr_Net_Scalarizr_Client::HASH_ALGO
                );
            } else {
                $signature = $this->cryptoTool->sign(
                    $jsonRequest,
                    $this->dbServer->GetProperty(SERVER_PROPERTIES::SZR_KEY),
                    $timestamp,
                    Scalr_Net_Scalarizr_Client::HASH_ALGO
                );
            }

            $request = new HttpRequest();
            $request->setMethod(HTTP_METH_POST);

            $requestHost = $this->dbServer->getSzrHost() . ":{$this->port}";

            if ($this->isVPC) {
                $routerFarmRoleId = $this->dbServer->GetFarmRoleObject()->GetSetting(Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID);
                if ($routerFarmRoleId) {
                    $routerRole = DBFarmRole::LoadByID($routerFarmRoleId);
                } else {
                    $routerRole = $this->dbServer->GetFarmObject()->GetFarmRoleByBehavior(ROLE_BEHAVIORS::VPC_ROUTER);
                }
                if ($routerRole) {
                    // No public IP need to use proxy
                    if (!$this->dbServer->remoteIp) {
                        $requestHost = $routerRole->GetSetting(Scalr_Role_Behavior_Router::ROLE_VPC_IP) . ":80";
                        $request->addHeaders(array(
                            "X-Receiver-Host" =>  $this->dbServer->localIp,
                            "X-Receiver-Port" => $this->port
                        ));
                        // There is public IP, can use it
                    } else {
                        $requestHost = "{$this->dbServer->remoteIp}:{$this->port}";
                    }
                }
            }

            $request->setUrl($requestHost);
            $request->setOptions(array(
                'timeout'	=> $this->timeout,
                'connecttimeout' => $this->timeout
            ));

            $request->addHeaders(array(
                "Date" =>  $timestamp,
                "X-Signature" => $signature,
                "X-Server-Id" => $this->dbServer->serverId
            ));
            $request->setBody($jsonRequest);

            try {
                // Send request
                $request->send();

                if ($request->getResponseCode() == 200) {

                    $response = $request->getResponseData();
                    $body = $response['body'];
                    if ($newEncryptionProtocol) {
                        $this->cryptoTool->setCryptoKey($this->dbServer->GetKey(true));
                        $body = $this->cryptoTool->decrypt($body);
                    }

                    $jResponse = @json_decode($body);

                    if ($jResponse->error)
                        throw new Exception("{$jResponse->error->message} ({$jResponse->error->code}): {$jResponse->error->data} ({$response['body']})");

                    return $jResponse;
                } else {
                    throw new Exception(sprintf("Unable to perform request to update client (%s). Server returned error %s", $requestHost, $request->getResponseCode()));
                }
            } catch(HttpException $e) {
                if (isset($e->innerException))
                    $msg = $e->innerException->getMessage();
                else
                    $msg = $e->getMessage();

                throw new Exception(sprintf("Unable to perform request to update client (%s): %s", $requestHost, $msg));
            }
        }
    }