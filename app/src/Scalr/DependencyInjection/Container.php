<?php

namespace Scalr\DependencyInjection;

/**
 * DependencyInjection container.
 * Inspired by Fabien Potencier.
 *
 * @author   Vitaliy Demidov    <zend@i.ua>
 * @since    19.10.2012
 *
 * @property string                               $awsRegion            An Aws region derived from user's environment.
 * @property string                               $awsSecretAccessKey   An Aws sercret access key taken from user's environment.
 * @property string                               $awsAccessKeyId       An Aws access key id taken from user's environment.
 * @property string                               $awsAccountNumber     An Aws account number.
 * @property \Scalr_Session                       $session              An Scalr Session isntance.
 * @property \Scalr\Service\Cloudyn               $cloudyn              An Cloudyn instance for the current user
 * @property \Scalr_Environment                   $environment          Recent Scalr_Environment instance.
 * @property \Scalr\Service\Aws                   $aws                  An Aws instance for the last instantiated user's environment.
 * @property \Scalr_UI_Request                    $request              A Scalr_UI_Request instance.
 * @property \Scalr_Account_User                  $user                 A Scalr_Account_User instance which is property for the request.
 * @property \Scalr\Logger\AuditLog               $auditLog             An AuditLog.
 * @property \Scalr\Logger\LoggerStorageInterface $auditLogStorage An AuditLogStorage
 * @method   \Scalr\Service\Aws    aws() aws(string|\DBServer $awsRegion = null, $awsAccessKeyId = null, $awsSecretAccessKey = null) Gets an Aws instance.
 */
class Container
{
    /**
     * @var Container
     */
    static private $instance;

    /**
     * Container of services
     *
     * @var array
     */
    protected $values = array();

    protected function __construct() {}

    private final function __clone() {}

    /**
     * Gets singleton instance of the Container
     *
     * @return Container
     */
    static public function getInstance ()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Container ();
        }
        return self::$instance;
    }

    /**
     * Resets singleton object.
     *
     * It can be used for phpunit testing purposes.
     */
    static public function reset ()
    {
        self::$instance = null;
    }

    /**
     * @param   string           $id
     * @throws  RuntimeException
     * @return  mixed
     */
    public function __get ($id)
    {
        return $this->get($id);
    }

    /**
     * @param   string     $id
     * @param   mixed      $value
     */
    public function __set ($id, $value)
    {
        $this->set($id, $value);
    }

    /**
     * Sets parameter
     *
     * @param   string     $id     Service id
     * @param   mixed      $value  Value
     * @return  Container
     */
    public function set ($id, $value)
    {
        $this->values[$id] = $value;

        return $this;
    }

    /**
     * Gets parameter
     *
     * @param   string $id Service Id
     * @throws  \RuntimeException
     * @return  mixed
     */
    public function get ($id)
    {
        if (!isset($this->values[$id])) {
            throw new \RuntimeException(
                sprintf('Could not find the service "%s"' , $id)
            );
        }
        return is_callable($this->values[$id]) ? $this->values[$id]($this) : $this->values[$id];
    }

    /**
     * @param   string     $id
     * @param   array      $arguments
     * @throws  \RuntimeException
     */
    public function __call ($id, $arguments)
    {
        if (!is_callable($this->values[$id])) {
            throw new \RuntimeException(sprintf(
                '%s() is not callable or does not exist.', $id
            ));
        }
        return $this->values[$id]($this, $arguments);
    }

    /**
     * Creates lambda function for making single instance of services.
     *
     * @param   callback   $callable
     */
    public function asShared ($callable)
    {
        return function (Container $container) use ($callable) {
            static $object;
            if (is_null($object)) {
                $object = $callable ($container);
            }
            return $object;
        };
    }

    /**
     * Checks, whether service with required id is initialized.
     *
     * @param   string   $id        Service id
     * @param   bool     $callable  optional If true it will check whether service is callable.
     * @return  bool     Returns true if required service is initialized or false otherwise.
     */
    public function initialized ($id, $callable = false)
    {
        return isset($this->values[$id]) && (!$callable || is_callable($this->values[$id]));
    }
}