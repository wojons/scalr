<?php

use Scalr\Model\Entity;
use Scalr\System\Http\Client\Request;

/**
 * Scalrizr API Client
 *
 * @author   Igor Savhenko  <igor@scalr.com>
 * @since    18.09.2012
 *
 * @property-read  Scalr_Net_Scalarizr_Services_Service        $service        Service namespace
 * @property-read  Scalr_Net_Scalarizr_Services_Mysql          $mysql          Mysql namespace
 * @property-read  Scalr_Net_Scalarizr_Services_Postgresql     $postgresql     PostgreSQL namespace
 * @property-read  Scalr_Net_Scalarizr_Services_Redis          $redis          Redis namespace
 * @property-read  Scalr_Net_Scalarizr_Services_Sysinfo        $sysinfo        SysInfo namespace
 * @property-read  Scalr_Net_Scalarizr_Services_System         $system         System namespace
 * @property-read  Scalr_Net_Scalarizr_Services_Operation      $operation      Operation namespace
 * @property-read  Scalr_Net_Scalarizr_Services_Image          $image          Image namespace
 */
class Scalr_Net_Scalarizr_Client
{
    const NAMESPACE_SERVICE = 'service';
    const NAMESPACE_MYSQL = 'mysql';
    const NAMESPACE_POSTGRESQL = 'postgresql';
    const NAMESPACE_REDIS = 'redis';
    const NAMESPACE_SYSINFO = 'sysinfo';
    const NAMESPACE_SYSTEM = 'system';
    const NAMESPACE_OPERATION = 'operation';
    const NAMESPACE_IMAGE = 'image';

    const CRYPTO_ALGO = MCRYPT_TRIPLEDES;
    const CIPHER_MODE = MCRYPT_MODE_CBC;
    const CRYPTO_KEY_SIZE = 24;
    const CRYPTO_BLOCK_SIZE = 8;

    const HASH_ALGO = 'SHA1';

    private $dbServer;

    private $port;

    private $cryptoTool;

    private $isVPC = false;

    protected $namespace;

    public $timeout = 15;

    public $debug;

    public static function getClient($dbServer, $namespace = null, $port = 8010)
    {
        switch ($namespace) {
            case "service":
                return new Scalr_Net_Scalarizr_Services_Service($dbServer, $port);
                break;

            case "mysql":
                return new Scalr_Net_Scalarizr_Services_Mysql($dbServer, $port);
                break;

            case "postgresql":
                return new Scalr_Net_Scalarizr_Services_Postgresql($dbServer, $port);
                break;

            case "redis":
                return new Scalr_Net_Scalarizr_Services_Redis($dbServer, $port);
                break;

            case "sysinfo":
                return new Scalr_Net_Scalarizr_Services_Sysinfo($dbServer, $port);
                break;

            case "system":
                return new Scalr_Net_Scalarizr_Services_System($dbServer, $port);
                break;

            case "operation":
                return new Scalr_Net_Scalarizr_Services_Operation($dbServer, $port);
                break;

            case "image":
                return new Scalr_Net_Scalarizr_Services_Image($dbServer, $port);
                break;

            default:
                return new Scalr_Net_Scalarizr_Client($dbServer, $port);
                break;
        }
    }

    public function __construct(DBServer $dbServer, $port = 8010)
    {
        $this->dbServer = $dbServer;
        $this->port = $port;

        if ($this->dbServer->farmId)
            if (DBFarm::LoadByID($this->dbServer->farmId)->GetSetting(Entity\FarmSetting::EC2_VPC_ID)) {
                $this->isVPC = true;
        }

        $this->cryptoTool = \Scalr::getContainer()->srzcrypto($this->dbServer->GetKey(true));
    }

    public function __get($name)
    {
        $className = "Scalr_Net_Scalarizr_Services_".ucfirst($name);

        if (class_exists($className)) {
            $this->{$name} = new $className($this->dbServer, $this->port);
        }
    }

    public function request($method, stdClass $params = null, $namespace = null)
    {
        if (!$namespace)
            $namespace = $this->namespace;

        $requestObj = new stdClass();
        $requestObj->id = microtime(true);
        $requestObj->method = $method;
        $requestObj->params = new stdClass();

        $this->walkSerialize($params, $requestObj->params, 'underScope');

        $this->cryptoTool->setCryptoKey($this->dbServer->GetKey(true));
        $jsonRequest = $this->cryptoTool->encrypt(json_encode($requestObj));

        $dt = new DateTime('now', new DateTimeZone("UTC"));
        $timestamp = $dt->format("D d M Y H:i:s e");

        $signature = $this->cryptoTool->sign($jsonRequest, null, $timestamp, Scalr_Net_Scalarizr_Client::HASH_ALGO);

        $request = new Request("POST");

        // If no VPC router communicating via local inteface (Scalr should be setup within the esame network)
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

        $request->setRequestUrl("http://{$requestHost}/{$namespace}");

        $request->setOptions(array(
            'timeout'        => $this->timeout,
            'connecttimeout' => 10
        ));

        $request->addHeaders(array(
            "Date"        => $timestamp,
            "X-Signature" => $signature,
            "X-Server-Id" => $this->dbServer->serverId
        ));

        $request->append($jsonRequest);

        try {
            // Send request
            $response = \Scalr::getContainer()->srzhttp->sendRequest($request);

            $this->debug['responseCode'] = $response->getResponseCode();
            $this->debug['fullResponse'] = $response->toString();

            if ($response->getResponseCode() == 200) {
                $body = $this->cryptoTool->decrypt($response->getBody()->toString());

                $jResponse = @json_decode($body);

                if (!empty($jResponse->error)) {
                    throw new Exception("{$jResponse->error->message} ({$jResponse->error->code}): {$jResponse->error->data}");
                }

                return $jResponse;
            } else {
                throw new Exception(sprintf(
                    "Unable to perform request to scalarizr: %s (%s)",
                    $response->getBody()->toString(), $response->getResponseCode()
                ));
            }
        } catch (\http\Exception $e) {
            if (isset($e->innerException)) {
                $msg = $e->innerException->getMessage();
            } else {
                $msg = $e->getMessage();
            }

            if (stristr($msg, "Namespace not found")) {
                $msg = "Feature not supported by installed version of scalarizr. Please update it to the latest version and try again.";
            }

            throw new Exception(sprintf("Unable to perform request to scalarizr: %s", $msg));
        }
    }

    private function walkSerialize ($object, &$retval, $normalizationMethod)
    {
        if ($object === null) {
            return false;
        }

        foreach ($object as $k=>$v) {
            if ($v === null) {
                $v = '';
            }

            $valueType = gettype($v);
            $objectType = gettype($retval);

            $normalizedString = call_user_func(array($this, $normalizationMethod), $k);

            if (is_object($v) || is_array($v)) {
                if ($objectType == 'object') {
                    if (is_array($v)) {
                        $retval->{$normalizedString} = array();
                    } else {
                        $retval->{$normalizedString} = new stdClass();
                    }

                    call_user_func_array(array($this, 'walkSerialize'), array($v, &$retval->{$normalizedString}, $normalizationMethod));
                } else {
                    if (is_array($v)) {
                        $retval[$normalizedString] = array();
                    } else {
                        $retval[$normalizedString] = new stdClass();
                    }

                    call_user_func_array(array($this, 'walkSerialize'), array($v, &$retval[$normalizedString], $normalizationMethod));
                }
            } else {
                if (is_object($retval)) {
                    $retval->{$normalizedString} = $v;
                } else {
                    if (!is_int($k)) {
                        $retval[$normalizedString] = $v;
                    } else {
                        $retval[] = $v;
                    }
                }
            }
        }
    }

    private function underScope($name)
    {
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

    public static function getCryptoTool($cryptoKey = null)
    {
        return \Scalr::getContainer()->srzcrypto($cryptoKey);
    }
}
