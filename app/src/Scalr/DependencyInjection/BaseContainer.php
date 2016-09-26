<?php

namespace Scalr\DependencyInjection;

/**
 * DependencyInjection container.
 * Inspired by Fabien Potencier.
 *
 * @author   Vitaliy Demidov    <vitaliy@scalr.com>
 * @since    19.10.2012
 */
class BaseContainer
{
    /**
     * Container of services
     *
     * @var array
     */
    protected $values = array();

    /**
     * Shared objects pseudo-static cache
     *
     * @var array
     */
    protected $shared = array();

    /**
     * Associated services for release memory
     *
     * @var array
     */
    protected $releasehooks = array();

    /**
     * This method is only used for sub-containers
     */
    public function __construct()
    {
    }

    private final function __clone()
    {
    }

    /**
     * @param   string           $id
     * @throws  RuntimeException
     * @return  mixed
     */
    public function __get($id)
    {
        return $this->get($id);
    }

    /**
     * @param   string     $id
     * @param   mixed      $value
     */
    public function __set($id, $value)
    {
        $this->set($id, $value);
    }

    /**
     * Sets parameter
     *
     * @param   string     $id     Service id
     * @param   mixed      $value  Value
     * @return  BaseContainer
     */
    public function set($id, $value)
    {
        $this->values[$id] = $value;
        if ($value === null) {
            $this->release($id);
        }
        return $this;
    }

    /**
     * Gets parameter
     *
     * @param   string    $id   Identifier of the service
     * @throws  \RuntimeException
     * @return  mixed
     */
    public function get($id)
    {
        if (!isset($this->values[$id])) {
            throw new \RuntimeException(sprintf(
                'Could not find the service "%s" in the DI container.', $id
            ));
        }

        return is_callable($this->values[$id]) ? $this->values[$id]($this) : $this->values[$id];
    }

    /**
     * Invoker
     *
     * Gets possible to use $container($id) instead of $container->get($id)
     * @param   string   $id Service ID
     * @return  mixed
     */
    public function __invoke($id)
    {
        return $this->get($id);
    }

    /**
     * @param   string     $id
     * @param   array      $arguments
     * @throws  \RuntimeException
     */
    public function __call($id, $arguments)
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
     * @return  BaseContainer
     */
    public function setShared($id, $callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException(sprintf(
                'Second argument of the "%s" method must be callable.', __FUNCTION__
            ));
        }

        $ptr =& $this->shared;

        if (($t = strpos($id, '.')) !== false) {
            //We need to register release hook which is needed to remove all
            //associated objects from the memory.
            $parentid = substr($id, 0, $t);

            if (!isset($this->releasehooks[$parentid])) {
                $this->releasehooks[$parentid] = array();
            }

            $this->releasehooks[$parentid][$id] = true;
        }

        $this->values[$id] = function (BaseContainer $container, $arguments = null) use ($id, $callable, &$ptr) {
            if (!isset($ptr[$id])) {
                $ptr[$id] = $callable($container);
            }

            //Invokes magic method for the specified object if it does exist.
            if (!empty($arguments) && is_array($arguments) && is_object($ptr[$id]) && method_exists($ptr[$id], '__invoke')) {
                return call_user_func_array([$ptr[$id], '__invoke'], $arguments);
            }

            return $ptr[$id];
        };

        return $this;
    }

    /**
     * Releases shared object from the pseudo-static cache
     *
     * @param   string    $id  The ID of the service
     * @return  BaseContainer
     */
    public function release($id)
    {
        if (isset($this->shared[$id])) {
            if (is_object($this->shared[$id]) && method_exists($this->shared[$id], '__destruct')) {
                $this->shared[$id]->__destruct();
            }

            unset($this->shared[$id]);
        }

        //Releases all children shared objects
        if (!empty($this->releasehooks[$id])) {
            foreach ($this->releasehooks[$id] as $serviceid => $b) {
                $this->release($serviceid);
            }

            unset($this->releasehooks[$id]);
        }

        return $this;
    }

    /**
     * Checks, whether service with required id is initialized.
     *
     * @param   string   $id        Service id
     * @param   bool     $callable  optional If true it will check whether service is callable.
     * @return  bool     Returns true if required service is initialized or false otherwise.
     */
    public function initialized($id, $callable = false)
    {
        return isset($this->values[$id]) && (!$callable || is_callable($this->values[$id]));
    }
}