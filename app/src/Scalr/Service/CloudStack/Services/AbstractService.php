<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\Exception\ServiceException;

/**
 * CloudStack abstract service interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
abstract class AbstractService
{
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
     * @var CloudStack
     */
    private $cloudstack;

    /**
     * Constructor
     *
     * @param CloudStack $cloudstack
     */
    public function __construct(CloudStack $cloudstack)
    {
        $this->cloudstack = $cloudstack;
    }

    /**
     * Gets an CloudStack instance
     *
     * @return CloudStack Returns CloudStack instance
     */
    public function getCloudStack()
    {
        return $this->cloudstack;
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
     * Gets an API Handler for the service
     *
     * @return  object Returns an API Handler for the service
     */
    public function getApiHandler()
    {
        if ($this->apiHandler === null) {
            //This method is declared in the ServiceInterface and must be defined in children classes.
            $ver = $this->getVersion();
            $class = get_class($this);
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

}