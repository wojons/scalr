<?php
namespace Scalr\Service\OpenStack\Services;

use Scalr\Service\OpenStack\Exception\OpenStackException;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\Exception\ServiceException;
use Scalr\Service\OpenStack\Exception\RestClientException;
use Scalr\Exception\NotSupportedException;

/**
 * OpenStack abstract service interface class
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    04.12.2012
 */
abstract class AbstractService
{

    const VERSION_DEFAULT = 'V1';

    /**
     * Conventional service name.
     * @var array
     */
    private static $serviceName = array();

    /**
     * Api handler for the service.
     * @var object
     */
    private $apiHandler;

    /**
     * @var OpenStack
     */
    private $openstack;

    /**
     * @var array
     */
    private $availableHandlers;

    /**
     * Misc. cache
     *
     * @var array
     */
    private $cache;

    /**
     * The current version of the API
     *
     * @var string
     */
    private $version = self::VERSION_DEFAULT;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceInterface::getVersion()
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceInterface::getSupportedVersions()
     */
    public function getSupportedVersions()
    {
        return [static::VERSION_DEFAULT];
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceInterface::setVersion()
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Constructor
     *
     * @param OpenStack $openstack
     */
    public function __construct(OpenStack $openstack)
    {
        $this->openstack = $openstack;
    }

    /**
     * Gets an OpenStack instance
     *
     * @return OpenStack Returns OpenStack instance
     */
    public function getOpenStack()
    {
        return $this->openstack;
    }

    /**
     * Gets service interface name.
     *
     * Returned name must start with the lower case letter.
     *
     * @return string Returns service interface name.
     */
    public static function getName()
    {
        $class = get_called_class();
        if (!isset(self::$serviceName[$class])) {
            $name = self::getOriginalServiceName($class);
            if ($name !== null) {
                self::$serviceName[$class] = lcfirst($name);
            } else {
                throw new ServiceException(sprintf(
                    'Invalid service interface class name "%s". It should end with "Service".', $class
                ));
            }
        }
        return self::$serviceName[$class];
    }

    /**
     * Gets an original service name
     *
     * @param   string    $class A Service class name
     * @return  string    Returns service name or NULL if class is not a service.
     */
    protected static function getOriginalServiceName($class)
    {
        if (preg_match('#(?<=\\\\|^)([^\\\\]+)Service$#', $class, $m)) {
            $name = $m[1];
        } else {
            $name = null;
        }
        return $name;
    }

    /**
     * Gets endpoint url.
     *
     * @return string Returns Endpoint url without trailing slash
     */
    public function getEndpointUrl()
    {
        $type = $this->getType();
        $cfg = $this->getOpenStack()->getConfig();
        $region = $cfg->getRegion();
        if ($cfg->getAuthToken() === null) {
            $url = $cfg->getIdentityEndpoint();
        } else {
            if (!isset($this->cache['endpoint'])) {
                $version = substr($this->getVersion(), 1);
                $this->cache['endpoint'] = $cfg->getAuthToken()->getEndpointUrl($type, $region, $version);
            }
            $url = $this->cache['endpoint'];
        }
        return $url;
    }

    /**
     * Gets an API Handler for the service
     *
     * @return  object Returns an API Handler for the service
     */
    public function getApiHandler()
    {
        $class = get_class($this);

        if ($this->apiHandler === null) {
            //This method is declared in the ServiceInterface and must be defined in children classes.

            if (!$this->getOpenStack()->getConfig()->getAuthToken()) {
                $this->getOpenStack()->auth();
            }

            $ver = $this->getVersion();

            $config = $this->getOpenStack()->getConfig();
            $type = $this->getType();
            $region = $config->getRegion();

            try {
                $config->getAuthToken()->getEndpointUrl($type, $region, substr($ver, 1));
            } catch (OpenStackException $e) {
                $endpoints = $config->getAuthToken()->getRegionEndpoints();

                if (empty($endpoints[$type][$region])) {
                    throw $e;
                }

                $supported = array_map(function ($v) {
                    return substr($v, 1);
                }, $this->getSupportedVersions());

                $versions = array_intersect($supported, array_keys($endpoints[$type][$region]));

                if (empty($versions)) {
                    throw new NotSupportedException(sprintf(
                        "There is not version number which is supported for the %s service. Available: %s, Supported: %s",
                        $type, join(", ", array_keys($endpoints[$type][$region])), join(", ", $supported)
                    ));
                }

                $ver = 'V' . array_shift($versions);

                $this->setVersion($ver);
            }

            $name = self::getOriginalServiceName($class);

            if ($name === null) {
                throw new ServiceException(sprintf(
                    'Invalid service interface class name "%s". It should end with "Service".', $class
                ));
            }
            $apiClass = __NAMESPACE__ . '\\' . $name . '\\' . $ver . '\\' . $name . 'Api';
            $this->apiHandler = new $apiClass($this);
        }

        return $this->apiHandler;
    }

    /**
     * Gets the list of available handlers
     *
     * @return  array Returns the list of available handlers
     */
    public function getAvailableHandlers()
    {
        if (!isset($this->availableHandlers)) {
            $this->availableHandlers = array();
            $class = get_class($this);
            $name = self::getOriginalServiceName($class);
            $list = @glob(__DIR__ . '/' . $name . '/Handler/*Handler.php');
            if (!empty($list)) {
                foreach ($list as $filename) {
                    if (preg_match('#[\\\\|/]([a-z0-9]+)Handler\.php$#i', $filename, $m)) {
                        $this->availableHandlers[lcfirst($m[1])] = null;
                    }
                }
            }
        }
        return $this->availableHandlers;
    }

    /**
     * Used to retrieve service handlers
     * @param   string    $name
     */
    public function __get($name)
    {
        $available = $this->getAvailableHandlers();
        if (array_key_exists($name, $available)) {
            if ($this->availableHandlers[$name] instanceof AbstractServiceHandler) {
                return $this->availableHandlers[$name];
            } else {
                $class = __NAMESPACE__
                    . '\\' . self::getOriginalServiceName(get_class($this))
                    . '\\Handler\\' . ucfirst($name) . 'Handler';
                $this->availableHandlers[$name] = new $class ($this);
                return $this->availableHandlers[$name];
            }
        }
    }

    /**
     * List Extensions action
     *
     * This operation returns a response body. In the response body, each extension is identified
     * by two unique identifiers, a namespace and an alias. Additionally an extension contains
     * documentation links in various formats
     *
     * @return  array      Returns list of available extensions
     * @throws  RestClientException
     */
    public function listExtensions()
    {
        if (!isset($this->cache['extensions'])) {
            $ret = $this->getApiHandler()->listExtensions();
            $this->cache['extensions'] = array();
            foreach ($ret as $v) {
                $this->cache['extensions'][$v->name] = $v;
                //Adds feature to resolve extension by alias
                if (!empty($v->alias) && empty($this->cache['extensions'][$v->alias])) {
                    $this->cache['extensions'][$v->alias] = $v;
                }
            }
        }
        return $this->cache['extensions'];
    }

    /**
     * Checks whether given extension is supported by the service.
     *
     * @param   StringType|string  $extensionName  An extension name
     * @return  bool   Returns true if an extension is supported.
     */
    public function isExtensionSupported($extensionName)
    {
        $list = $this->listExtensions();
        return isset($list[(string)$extensionName]);
    }

    /**
     * Gets an identifier of the tenant for current authenticated token
     *
     * @return   string  Gets an identifier of the tenant
     */
    public function getTenantId()
    {
        $this->getOpenStack()->getConfig()->getAuthToken()->getTenantId();
    }
}